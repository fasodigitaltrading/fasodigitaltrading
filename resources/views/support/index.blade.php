@extends('layouts.app')
@section('content')
<div class="container">
    <p class="brand-accent">ASSISTANCE</p><h1>{{ auth()->user()->role === 'ADMIN' ? 'Demandes de support' : 'Mon support' }}</h1>
    @if(session('status'))<div class="success-alert" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<p style="color:#ff8080">{{ $errors->first() }}</p>@endif
    @if(auth()->user()->role !== 'ADMIN')
        <div class="card"><h2>Créer une demande</h2><form method="POST" action="{{ route('support.store') }}">@csrf
            <label>Sujet</label><input name="subject" maxlength="160" required>
            <label>Message</label><textarea name="body" maxlength="20000" required></textarea>
            <button class="btn" type="submit">Envoyer</button>
        </form></div>
    @endif
    @if(auth()->user()->role === 'ADMIN')
        <form method="GET" class="card" style="display:flex;align-items:end;gap:12px;flex-wrap:wrap;margin:16px 0">
            <div><label>Recherche</label><input name="q" maxlength="120" value="{{ $filters['q']??'' }}" placeholder="Sujet ou e-mail"></div>
            <div><label>Statut</label><select name="status"><option value="">Tous</option>@foreach(\App\Models\SupportTicket::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status']??'')===$status)>{{ $status }}</option>@endforeach</select></div>
            <div><label>Priorité</label><select name="priority"><option value="">Toutes</option>@foreach(\App\Models\SupportTicket::PRIORITIES as $priority)<option value="{{ $priority }}" @selected(($filters['priority']??'')===$priority)>{{ $priority }}</option>@endforeach</select></div>
            <button class="button button-small" type="submit">Filtrer</button>
        </form>
    @endif
    <h2 style="margin-top:28px">{{ auth()->user()->role === 'ADMIN' ? 'Toutes les demandes' : 'Mes demandes' }}</h2>
    @forelse($tickets as $ticket)
        <a class="card" style="display:block;margin-top:12px" href="{{ route('support.show', $ticket) }}">
            <h3>{{ $ticket->subject }}</h3><p class="muted">{{ $ticket->user->email }} · {{ $ticket->status }} · {{ $ticket->priority ?? 'NORMAL' }} · Mis à jour {{ $ticket->updated_at->format('d/m/Y H:i') }}</p>
        </a>
    @empty<p class="muted">Aucune demande pour le moment.</p>@endforelse
    @if($tickets instanceof \Illuminate\Contracts\Pagination\Paginator || $tickets instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator){{ $tickets->links() }}@endif
</div>
@endsection
