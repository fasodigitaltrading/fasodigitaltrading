<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\User;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class PartnerController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(['ACTIVE', 'SUSPENDED', 'PENDING'])]])['status'] ?? null;

        return view('admin.partners.index', [
            'partners' => Partner::with('user:id,first_name,last_name,email,status')
                ->withCount(['referrals', 'commissions'])
                ->withCount(['commissions as pending_commissions_count' => fn ($query) => $query->where('status', 'PENDING')])
                ->withCount(['commissions as approved_commissions_count' => fn ($query) => $query->where('status', 'APPROVED')])
                ->when($status, fn ($query) => $query->where('status', $status))
                ->latest()->paginate(25)->withQueryString(),
            'selectedStatus' => $status ?? '',
            'counts' => Partner::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function update(Request $request, Partner $partner, AdminAuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['ACTIVE', 'SUSPENDED'])],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $partner, $data, $audit): void {
            $locked = Partner::whereKey($partner->id)->lockForUpdate()->firstOrFail();
            if ($data['status'] === 'ACTIVE') {
                $owner = User::withTrashed()->find($locked->user_id);
                abort_unless($owner && !$owner->trashed() && $owner->status === 'ACTIVE', 409, 'Le compte utilisateur doit être actif avant la réactivation du partenaire.');
            }
            $before = ['status' => $locked->status, 'commission_rate' => $locked->commission_rate];
            $locked->status = $data['status'];
            $locked->save();
            $audit->record($request, 'admin.partner.status_changed', $locked, $before, [
                'status' => $locked->status,
                'reason' => trim($data['reason']),
            ]);
        });

        return back()->with('status', 'Statut partenaire enregistré. Le taux de commission existant reste inchangé.');
    }
}
