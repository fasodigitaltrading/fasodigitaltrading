<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrvmPurchaseRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BrvmOrderRequestController extends Controller
{
    private const TRANSITIONS = [
        'RECEIVED' => ['FORWARDED', 'REJECTED', 'CANCELLED'],
        'FORWARDED' => ['ACCEPTED', 'REJECTED', 'CANCELLED'],
        'ACCEPTED' => ['EXECUTED', 'REJECTED', 'CANCELLED'],
        'REJECTED' => [],
        'EXECUTED' => [],
        'CANCELLED' => [],
    ];

    public function index(): View
    {
        return view('admin.brvm-orders.index', [
            'requests' => BrvmPurchaseRequest::with(['user:id,first_name,last_name,email'])
                ->latest()
                ->paginate(20),
            'corisManualChannelEnabled' => (bool) config('brvm.coris_manual_channel_enabled'),
        ]);
    }

    public function update(Request $request, BrvmPurchaseRequest $brvmPurchaseRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(BrvmPurchaseRequest::STATUSES)],
            'coris_reference' => ['nullable', 'string', 'max:120'],
            'admin_note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $brvmPurchaseRequest, $data): void {
            $current = BrvmPurchaseRequest::query()->lockForUpdate()->findOrFail($brvmPurchaseRequest->id);
            abort_unless(in_array($data['status'], self::TRANSITIONS[$current->status] ?? [], true), 409, 'Cette évolution de statut n’est pas autorisée.');

            if (in_array($data['status'], ['FORWARDED', 'ACCEPTED', 'EXECUTED'], true)
                && !config('brvm.coris_manual_channel_enabled')) {
                throw ValidationException::withMessages([
                    'status' => 'Aucun canal Coris autorisé n’est configuré; cette étape reste désactivée.',
                ]);
            }

            $reference = trim((string) ($data['coris_reference'] ?? $current->coris_reference ?? ''));
            if (in_array($data['status'], ['FORWARDED', 'ACCEPTED', 'EXECUTED'], true) && $reference === '') {
                throw ValidationException::withMessages([
                    'coris_reference' => 'Une référence Coris est requise pour enregistrer cette étape.',
                ]);
            }

            $before = [
                'status' => $current->status,
                'coris_reference' => $current->coris_reference,
            ];
            $now = now();
            $current->status = $data['status'];
            $current->handled_by = $request->user()->id;
            $current->coris_reference = $reference !== '' ? $reference : null;
            $current->admin_note = $data['admin_note'];
            if ($data['status'] === 'FORWARDED' && $current->forwarded_at === null) {
                $current->forwarded_at = $now;
            }
            if (in_array($data['status'], ['REJECTED', 'EXECUTED', 'CANCELLED'], true)) {
                $current->completed_at = $now;
            }
            $current->save();

            DB::table('audit_logs')->insert([
                'user_id' => $request->user()->id,
                'action' => 'brvm_purchase_request.status_updated',
                'entity_type' => BrvmPurchaseRequest::class,
                'entity_id' => $current->id,
                'old_values' => json_encode($before, JSON_THROW_ON_ERROR),
                'new_values' => json_encode([
                    'status' => $current->status,
                    'coris_reference' => $current->coris_reference,
                ], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return redirect()->route('admin.brvm-orders.index')->with('status', 'Suivi de la demande BRVM mis à jour et journalisé.');
    }
}
