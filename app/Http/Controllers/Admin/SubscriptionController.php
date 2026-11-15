<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Subscription;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['PENDING', 'ACTIVE', 'EXPIRED', 'CANCELLED'])],
        ]);

        $subscriptions = Subscription::with([
            'user:id,first_name,last_name,email',
            'plan:id,name',
            'latestPayment',
        ])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'selectedStatus' => $filters['status'] ?? '',
        ]);
    }

    public function control(Request $request, Subscription $subscription, AdminAuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['activate', 'deactivate'])],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'confirm_payment_received' => ['required_if:action,activate', 'nullable', Rule::in(['yes'])],
            'payment_reference' => ['required_if:action,activate', 'nullable', 'string', 'min:4', 'max:191'],
        ]);

        DB::transaction(function () use ($subscription, $data, $audit, $request): void {
            $actor = $request->user();
            $current = Subscription::with('plan')->lockForUpdate()->findOrFail($subscription->id);
            $before = [
                'status' => $current->status,
                'starts_at' => $current->starts_at?->toIso8601String(),
                'ends_at' => $current->ends_at?->toIso8601String(),
                'admin_override' => $current->admin_override,
            ];

            if ($data['action'] === 'deactivate') {
                abort_unless($current->isOperationallyActive(), 409, 'Cet abonnement n’est pas actif.');
                $current->admin_override = 'DISABLED';
                $current->admin_controlled_at = now();
                $current->admin_controlled_by = $actor->id;
                $current->save();

                License::where('subscription_id', $current->id)
                    ->where('status', 'ACTIVE')
                    ->update(['status' => 'SUSPENDED', 'updated_at' => now()]);
            } else {
                $duration = (int) ($current->plan_duration_days_snapshot ?: $current->plan?->duration_days);
                abort_if($duration < 1, 409, 'La durée de cette formule est introuvable.');

                if ($current->status !== 'ACTIVE' || !$current->ends_at || $current->ends_at->isPast()) {
                    $startsAt = now();
                    $current->status = 'ACTIVE';
                    $current->starts_at = $startsAt;
                    $current->ends_at = $startsAt->copy()->addDays($duration);
                }

                $current->admin_override = 'ACTIVE';
                $current->admin_controlled_at = now();
                $current->admin_controlled_by = $actor->id;
                $current->save();

                $license = License::where('subscription_id', $current->id)->lockForUpdate()->first();
                $licenseData = [
                    'user_id' => $current->user_id,
                    'subscription_id' => $current->id,
                    'status' => 'ACTIVE',
                    'activated_at' => $license?->activated_at ?? now(),
                    'expires_at' => $current->ends_at,
                    'max_accounts' => (int) ($current->plan_max_mt5_accounts_snapshot ?: $current->plan?->max_mt5_accounts ?: 1),
                ];

                if ($license) {
                    $license->forceFill($licenseData)->save();
                } else {
                    if (strlen((string) config('app.key')) < 20) {
                        throw ValidationException::withMessages([
                            'subscription' => 'APP_KEY est manquante ou invalide; aucune licence n’a été créée.',
                        ]);
                    }
                    License::issue($licenseData);
                }
            }

            $audit->record($request,
                'subscription.admin_'.($data['action'] === 'activate' ? 'activated' : 'deactivated'),
                $current,
                $before,
                [
                    'status' => $current->status,
                    'starts_at' => $current->starts_at?->toIso8601String(),
                    'ends_at' => $current->ends_at?->toIso8601String(),
                    'admin_override' => $current->admin_override,
                    'reason' => $data['reason'],
                    'manual_payment_reference' => $data['payment_reference'] ?? null,
                    'manual_payment_confirmed_by_admin' => ($data['action'] === 'activate'),
                ],
            );
        });

        return redirect()->route('admin.subscriptions.index')
            ->with('status', $data['action'] === 'activate'
                ? 'Abonnement activé et licence mise à jour. Le statut du paiement n’a pas été modifié.'
                : 'Abonnement désactivé et licence suspendue. Le statut du paiement n’a pas été modifié.');
    }
}
