<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\ManualPaymentSubmission;
use App\Models\Payment;
use App\Models\Partner;
use App\Models\Referral;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TradingAccount;
use App\Models\User;
use App\Models\BrvmPurchaseRequest;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['ACTIVE', 'SUSPENDED', 'PENDING', 'UNVERIFIED', 'DELETED'])],
            'pack' => ['nullable', 'string', Rule::in(SubscriptionPlan::PUBLIC_CATALOGUE_SLUGS)],
        ]);

        $query = User::query()->with(['latestSubscription.plan', 'partner'])->withCount(['payments', 'subscriptions', 'licenses']);
        if (($filters['status'] ?? null) === 'DELETED') {
            $query->withTrashed()->onlyTrashed();
        } elseif (($filters['status'] ?? null) === 'UNVERIFIED') {
            $query->whereNull('email_verified_at');
        } elseif (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['q'])) {
            $search = trim($filters['q']);
            $query->where(function ($builder) use ($search): void {
                $builder->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }
        if (!empty($filters['pack'])) {
            $query->whereHas('subscriptions', fn ($builder) => $builder->whereHas('plan', fn ($plan) => $plan->where('slug', $filters['pack'])));
        }

        return view('admin.users.index', [
            'users' => $query->latest()->paginate(25)->withQueryString(),
            'filters' => $filters,
            'plans' => SubscriptionPlan::whereIn('slug', SubscriptionPlan::PUBLIC_CATALOGUE_SLUGS)->orderBy('sort_order')->get(),
            'totalUsers' => User::count(),
            'activeUsers' => User::where('status', 'ACTIVE')->count(),
            'suspendedUsers' => User::where('status', 'SUSPENDED')->count(),
            'deletedUsers' => User::onlyTrashed()->count(),
        ]);
    }

    public function show(string $userId): View
    {
        $user = User::withTrashed()->findOrFail($userId);

        return view('admin.users.show', [
            'user' => $user,
            'payments' => Payment::where('user_id', $user->id)->with('subscription')->latest()->paginate(15, ['*'], 'payments_page'),
            'manualSubmissions' => ManualPaymentSubmission::where('user_id', $user->id)->with(['order', 'reviewer:id,first_name,last_name'])->latest()->paginate(15, ['*'], 'manual_page'),
            'subscriptions' => Subscription::where('user_id', $user->id)->with(['plan', 'latestPayment'])->latest()->paginate(15, ['*'], 'subscriptions_page'),
            'licenses' => License::where('user_id', $user->id)->with(['subscription', 'accountBindings'])->latest()->get(),
            'accounts' => TradingAccount::where('user_id', $user->id)->with(['broker', 'openPositions', 'trades' => fn ($query) => $query->latest()->limit(10)])->latest()->get(),
            'partner' => Partner::where('user_id', $user->id)->withCount(['referrals', 'commissions'])->first(),
            'referrer' => Referral::where('user_id', $user->id)->with('partner.user')->first(),
            'brvmRequests' => BrvmPurchaseRequest::where('user_id', $user->id)->latest()->get(),
            'paymentTotals' => Payment::where('user_id', $user->id)->selectRaw('currency, status, COUNT(*) AS count, SUM(amount) AS total')->groupBy('currency', 'status')->orderBy('currency')->get(),
        ]);
    }

    public function update(Request $request, User $user, AdminAuditLogger $audit): RedirectResponse
    {
        abort_if($user->role === 'ADMIN', 403, 'Le compte administrateur ne peut pas être modifié depuis la gestion des clients.');
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['ACTIVE', 'SUSPENDED'])],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $before = $user->only(['first_name', 'last_name', 'phone', 'country', 'status']);
        $after = collect($data)->only(['first_name', 'last_name', 'phone', 'country', 'status'])->all();

        DB::transaction(function () use ($user, $after, $data, $before, $audit, $request): void {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->role === 'ADMIN', 403);
            $locked->update($after);
            $audit->record($request, 'admin.user.updated', $locked, $before, $after + ['reason' => trim($data['reason'])]);
        });

        return redirect()->route('admin.users.show', $user)->with('status', 'Compte client mis à jour. Le rôle et l’adresse e-mail n’ont pas été modifiés.');
    }

    public function destroy(Request $request, User $user, AdminAuditLogger $audit): RedirectResponse
    {
        abort_if($user->role === 'ADMIN' || (int) $user->id === (int) $request->user()->id, 403, 'Le compte administrateur ne peut pas être supprimé.');
        $data = $request->validate([
            'confirmation' => ['required', Rule::in(['SUPPRIMER'])],
            'email_confirmation' => ['required', Rule::in([$user->email])],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        DB::transaction(function () use ($user, $data, $audit, $request): void {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->role === 'ADMIN' || (int) $locked->id === (int) $request->user()->id, 403);
            $before = ['status' => $locked->status, 'deleted_at' => null];
            $locked->status = 'SUSPENDED';
            $locked->save();
            $locked->delete();
            $audit->record($request, 'admin.user.soft_deleted', $locked, $before, [
                'status' => $locked->status,
                'deleted_at' => $locked->deleted_at?->toIso8601String(),
                'reason' => trim($data['reason']),
            ]);
        });

        return redirect()->route('admin.users.index')->with('status', 'Compte supprimé logiquement. Les données financières et historiques sont conservées.');
    }

    public function restore(Request $request, string $userId, AdminAuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);
        $user = User::withTrashed()->findOrFail($userId);
        abort_unless($user->trashed() && $user->role !== 'ADMIN', 409);

        DB::transaction(function () use ($user, $data, $audit, $request): void {
            $locked = User::withTrashed()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->trashed() && $locked->role !== 'ADMIN', 409);
            $locked->restore();
            $locked->status = 'SUSPENDED';
            $locked->save();
            $audit->record($request, 'admin.user.restored', $locked, ['deleted_at' => $user->deleted_at?->toIso8601String()], [
                'status' => 'SUSPENDED', 'reason' => trim($data['reason']),
            ]);
        });

        return redirect()->route('admin.users.show', $userId)->with('status', 'Compte restauré mais suspendu. Réactivez-le séparément après vérification.');
    }
}
