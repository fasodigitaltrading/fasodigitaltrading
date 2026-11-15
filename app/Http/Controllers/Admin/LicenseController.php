<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Payment;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class LicenseController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['ACTIVE', 'EXPIRED', 'SUSPENDED', 'REVOKED'])],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        return view('admin.licenses.index', [
            'licenses' => License::with(['user:id,first_name,last_name,email,status', 'subscription:id,status,ends_at,plan_name_snapshot'])
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($filters['q'] ?? null, fn ($query, $needle) => $query->whereHas('user', fn ($user) => $user->where('email', 'like', '%'.$needle.'%')))
                ->latest()->paginate(25)->withQueryString(),
            'filters' => $filters,
            'counts' => License::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function control(Request $request, License $license, AdminAuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['suspend', 'reactivate', 'revoke'])],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $license, $data, $audit): void {
            $locked = License::with(['subscription', 'user'])->lockForUpdate()->findOrFail($license->id);
            $before = ['status' => $locked->status, 'expires_at' => $locked->expires_at?->toIso8601String()];

            if ($data['action'] === 'reactivate') {
                abort_if($locked->status === 'REVOKED', 409, 'Une licence révoquée ne peut pas être réactivée.');
                $subscription = $locked->subscription;
                abort_unless($locked->user?->status === 'ACTIVE' && $subscription?->isOperationallyActive(), 409, 'Un abonnement actif est requis.');
                abort_unless(Payment::where('subscription_id', $subscription->id)->where('status', 'PAID')->exists(), 409, 'Aucun paiement confirmé n’est associé à cet abonnement.');
                abort_if($locked->expires_at && $locked->expires_at->isPast(), 409, 'Cette licence est expirée. Renouvelez l’abonnement avant de la réactiver.');
                $locked->status = 'ACTIVE';
            } elseif ($data['action'] === 'suspend') {
                abort_unless($locked->status === 'ACTIVE', 409, 'Seule une licence active peut être suspendue.');
                $locked->status = 'SUSPENDED';
            } else {
                abort_if($locked->status === 'REVOKED', 409, 'Cette licence est déjà révoquée.');
                $locked->status = 'REVOKED';
            }

            $locked->save();
            $audit->record($request, 'admin.license.'.$data['action'], $locked, $before, [
                'status' => $locked->status,
                'reason' => trim($data['reason']),
            ]);
        });

        return back()->with('status', 'Statut de licence enregistré et journalisé. La clé secrète n’est jamais affichée.');
    }
}
