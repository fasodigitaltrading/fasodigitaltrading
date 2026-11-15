<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportReplyReceived;
use App\Notifications\SupportTicketCreated;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SupportTicket::class);
        $filters = [];
        $query = SupportTicket::with(['user', 'assignedAdmin'])->orderByDesc('updated_at');
        if ($request->user()->role === 'ADMIN') {
            $filters = $request->validate([
                'status' => ['nullable', \Illuminate\Validation\Rule::in(SupportTicket::STATUSES)],
                'priority' => ['nullable', \Illuminate\Validation\Rule::in(SupportTicket::PRIORITIES)],
                'q' => ['nullable', 'string', 'max:120'],
            ]);
            $query->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status))
                ->when($filters['priority'] ?? null, fn ($builder, $priority) => $builder->where('priority', $priority))
                ->when($filters['q'] ?? null, fn ($builder, $needle) => $builder->where(fn ($search) => $search->where('subject', 'like', '%'.$needle.'%')->orWhereHas('user', fn ($user) => $user->where('email', 'like', '%'.$needle.'%'))));
        } else {
            $query->where('user_id', $request->user()->id);
        }

        return view('support.index', ['tickets' => $query->paginate(25)->withQueryString(), 'filters' => $filters]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', SupportTicket::class);
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $ticket = DB::transaction(function () use ($request, $data) {
            $ticket = SupportTicket::create([
                'user_id' => $request->user()->id, 'subject' => $data['subject'], 'status' => 'OPEN', 'category' => 'GENERAL',
            ]);
            $ticket->messages()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);

            return $ticket;
        });

        DB::afterCommit(fn () => Notification::send(
            User::where('role', 'ADMIN')->where('status', 'ACTIVE')
                ->whereRaw('LOWER(TRIM(email)) = ?', [(string) config('admin.email')])->get(),
            new SupportTicketCreated($ticket),
        ));

        return redirect()->route('support.show', $ticket)->with('status', 'Votre demande a été transmise.');
    }

    public function show(Request $request, SupportTicket $ticket): View
    {
        $this->authorize('view', $ticket);

        $messages = $ticket->messages()->with('user')->when(
            $request->user()->role !== 'ADMIN',
            fn ($query) => $query->where('is_internal', false),
        )->get();

        return view('support.show', ['ticket' => $ticket->load(['user', 'assignedAdmin'])->setRelation('messages', $messages)]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorize('reply', $ticket);
        $data = $request->validate(['body' => ['required', 'string', 'max:20000']]);

        DB::transaction(function () use ($request, $ticket, $data) {
            SupportMessage::create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'body' => $data['body'],
                'is_internal' => false,
            ]);
            $ticket->update([
                'status' => $request->user()->role === 'ADMIN' ? 'IN_PROGRESS' : 'OPEN',
                'resolved_at' => null,
                'assigned_admin_id' => $request->user()->role === 'ADMIN' ? $request->user()->id : $ticket->assigned_admin_id,
            ]);
        });

        if ($request->user()->role === 'ADMIN') {
            DB::afterCommit(fn () => $ticket->user->notify(new SupportReplyReceived($ticket)));
        }

        return redirect()->route('support.show', $ticket)->with('status', 'Réponse envoyée.');
    }

    public function adminUpdate(Request $request, SupportTicket $ticket, AdminAuditLogger $audit): RedirectResponse
    {
        $this->authorize('view', $ticket);
        abort_unless($request->user()->role === 'ADMIN', 403);
        $data = $request->validate([
            'status' => ['required', \Illuminate\Validation\Rule::in(SupportTicket::STATUSES)],
            'priority' => ['required', \Illuminate\Validation\Rule::in(SupportTicket::PRIORITIES)],
            'category' => ['required', \Illuminate\Validation\Rule::in(SupportTicket::CATEGORIES)],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        DB::transaction(function () use ($request, $ticket, $data, $audit): void {
            $locked = SupportTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $before = $locked->only(['status', 'priority', 'category', 'assigned_admin_id', 'resolved_at']);
            $locked->status = $data['status'];
            $locked->priority = $data['priority'];
            $locked->category = $data['category'];
            $locked->assigned_admin_id = $request->user()->id;
            $locked->resolved_at = $data['status'] === 'CLOSED' ? ($locked->resolved_at ?? now()) : null;
            $locked->save();
            $audit->record($request, 'admin.support.updated', $locked, $before, $locked->only(['status', 'priority', 'category', 'assigned_admin_id', 'resolved_at']) + ['reason' => trim($data['reason'])]);
        });

        return redirect()->route('support.show', $ticket)->with('status', 'Dossier de support mis à jour.');
    }

    public function internalNote(Request $request, SupportTicket $ticket, AdminAuditLogger $audit): RedirectResponse
    {
        $this->authorize('view', $ticket);
        abort_unless($request->user()->role === 'ADMIN', 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000']]);
        DB::transaction(function () use ($request, $ticket, $data, $audit): void {
            $message = SupportMessage::create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'body' => $data['body'],
                'is_internal' => true,
            ]);
            $audit->record($request, 'admin.support.internal_note_added', $ticket, [], ['message_id' => $message->id]);
        });

        return redirect()->route('support.show', $ticket)->with('status', 'Note interne ajoutée. Elle n’est pas visible par le client.');
    }

    public function close(Request $request, SupportTicket $ticket, AdminAuditLogger $audit): RedirectResponse
    {
        $this->authorize('view', $ticket);
        abort_unless($request->user()->role === 'ADMIN', 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $before = ['status' => $ticket->status, 'resolved_at' => $ticket->resolved_at?->toIso8601String()];
        $ticket->update(['status' => 'CLOSED', 'resolved_at' => now(), 'assigned_admin_id' => $request->user()->id]);
        $audit->record($request, 'admin.support.closed', $ticket, $before, ['status' => 'CLOSED', 'reason' => trim($data['reason'])]);

        return redirect()->route('support.show', $ticket)->with('status', 'Demande clôturée.');
    }
}
