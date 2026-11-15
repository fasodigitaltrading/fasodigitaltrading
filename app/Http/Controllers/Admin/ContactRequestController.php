<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactRequest;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ContactRequestController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->role === 'ADMIN' && $request->user()->mfa_verified_at !== null, 403);

        $filters = $request->validate(['status' => ['nullable', Rule::in(['NEW', 'IN_PROGRESS', 'RESOLVED'])]]);
        $requests = ContactRequest::when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.contact-requests.index', [
            'requests' => $requests,
            'plansBySlug' => SubscriptionPlan::pluck('name', 'slug'),
            'selectedStatus' => $filters['status'] ?? '',
        ]);
    }

    public function update(Request $request, ContactRequest $contactRequest): RedirectResponse
    {
        abort_unless($request->user()->role === 'ADMIN' && $request->user()->mfa_verified_at !== null, 403);
        $data = $request->validate(['status' => ['required', Rule::in(['NEW', 'IN_PROGRESS', 'RESOLVED'])]]);
        $before = $contactRequest->status;

        DB::transaction(function () use ($request, $contactRequest, $data, $before) {
            $contactRequest->update(['status' => $data['status']]);
            DB::table('audit_logs')->insert([
                'user_id' => $request->user()->id,
                'action' => 'contact_request.status_updated',
                'entity_type' => ContactRequest::class,
                'entity_id' => $contactRequest->id,
                'old_values' => json_encode(['status' => $before], JSON_THROW_ON_ERROR),
                'new_values' => json_encode(['status' => $data['status']], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.contact-requests.index')->with('status', 'Statut de la demande mis à jour.');
    }
}
