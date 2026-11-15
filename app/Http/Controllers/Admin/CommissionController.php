<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Payment;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CommissionController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['PENDING', 'APPROVED', 'PAID', 'REVERSED'])],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $commissions = Commission::with([
            'partner:id,user_id,partner_code,commission_rate', 'partner.user:id,first_name,last_name,email',
            'user:id,first_name,last_name,email', 'payment:id,subscription_id,amount,currency,status,paid_at',
            'payment.subscription:id,plan_name_snapshot', 'paidBy:id,first_name,last_name',
        ])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, fn ($query, $needle) => $query->where(function ($search) use ($needle): void {
                $search->whereHas('partner.user', fn ($user) => $user->where('email', 'like', '%'.$needle.'%'))
                    ->orWhereHas('user', fn ($user) => $user->where('email', 'like', '%'.$needle.'%'));
            }))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.commissions.index', [
            'commissions' => $commissions,
            'totals' => Commission::query()->selectRaw('currency, status, SUM(amount) as total')->groupBy('currency', 'status')->orderBy('currency')->orderBy('status')->get(),
            'selectedStatus' => $filters['status'] ?? '',
            'search' => $filters['q'] ?? '',
        ]);
    }

    public function control(Request $request, Commission $commission, AdminAuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'record-payout'])],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'payout_reference' => ['required_if:action,record-payout', 'nullable', 'string', 'min:4', 'max:191'],
            'confirm_payout' => ['required_if:action,record-payout', 'nullable', Rule::in(['yes'])],
        ]);

        DB::transaction(function () use ($request, $commission, $data, $audit): void {
            $locked = Commission::with('payment')->lockForUpdate()->findOrFail($commission->id);
            $before = ['status' => $locked->status, 'paid_at' => $locked->paid_at?->toIso8601String()];

            if ($data['action'] === 'approve') {
                abort_unless($locked->status === 'PENDING', 409, 'Seule une commission en attente peut être approuvée.');
                abort_unless($locked->available_at && $locked->available_at->lte(now()), 409, 'Le délai de réserve de la commission n’est pas terminé.');
                abort_unless($locked->payment?->status === 'PAID' && $locked->payment?->paid_at, 409, 'Le paiement associé n’est pas confirmé.');
                $locked->status = 'APPROVED';
            } else {
                abort_unless($locked->status === 'APPROVED', 409, 'Seule une commission approuvée peut être rapprochée.');
                abort_unless($locked->payment?->status === 'PAID', 409, 'Le paiement source n’est plus confirmé.');
                $locked->status = 'PAID';
                $locked->paid_at = now();
                $locked->paid_by = $request->user()->id;
                $locked->payout_reference = trim((string) $data['payout_reference']);
            }

            $locked->save();
            $audit->record($request, 'admin.commission.'.$data['action'], $locked, $before, [
                'status' => $locked->status,
                'paid_at' => $locked->paid_at?->toIso8601String(),
                'payout_reference' => $locked->payout_reference,
                'reason' => trim($data['reason']),
            ]);
        });

        return back()->with('status', $data['action'] === 'approve'
            ? 'Commission approuvée après vérification du paiement et du délai de réserve.'
            : 'Rapprochement enregistré à partir de la référence de versement externe fournie. Aucun transfert n’a été déclenché par la plateforme.');
    }
}
