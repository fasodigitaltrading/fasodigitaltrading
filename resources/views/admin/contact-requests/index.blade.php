@extends('layouts.app')

@section('content')
<section class="container section">
    <span class="eyebrow">PORTAIL ADMINISTRATEUR</span>
    <h1>Demandes de contact</h1>
    <p class="muted">Les demandes de démo et de devis envoyées depuis le site. Cette page est réservée à l’administrateur vérifié par MFA.</p>
    @if(session('status'))<div class="success-alert" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="form-alert" role="alert">{{ $errors->first() }}</div>@endif
    <form method="GET" action="{{ route('admin.contact-requests.index') }}" class="content-panel" style="display:flex;align-items:center;gap:12px;margin-top:20px">
        <label for="status">Filtrer par statut</label>
        <select id="status" name="status" data-submit-on-change style="min-height:40px;padding:8px;border:1px solid var(--line-strong);border-radius:8px;background:#0a0f15;color:var(--text)">
            <option value="">Tous les statuts</option>
            @foreach(['NEW'=>'Nouvelle','IN_PROGRESS'=>'En cours','RESOLVED'=>'Traitée'] as $value => $label)<option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>@endforeach
        </select>
        <noscript><button class="button button-small" type="submit">Filtrer</button></noscript>
    </form>
    <div class="request-list">
        @forelse($requests as $contactRequest)
            <article class="request-card">
                <div class="request-heading"><div><span class="request-kind">{{ ['demo'=>'Démo','quote'=>'Devis','call'=>'Appel','question'=>'Question'][$contactRequest->intent] ?? 'Contact' }}</span><h2>{{ $contactRequest->name }}</h2><a href="mailto:{{ $contactRequest->email }}">{{ $contactRequest->email }}</a></div><span class="request-date">{{ $contactRequest->created_at->format('d/m/Y H:i') }}</span></div>
                @if($contactRequest->package)<p class="request-pack">Pack demandé : <strong>{{ $plansBySlug[$contactRequest->package] ?? $contactRequest->package }}</strong></p>@endif
                <p class="request-message">{{ $contactRequest->message }}</p>
                <form method="POST" action="{{ route('admin.contact-requests.update', $contactRequest) }}" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:16px">@csrf @method('PUT')
                    <label for="request-status-{{ $contactRequest->id }}">Statut</label>
                    <select id="request-status-{{ $contactRequest->id }}" name="status" style="min-height:38px;padding:7px;border:1px solid var(--line-strong);border-radius:8px;background:#0a0f15;color:var(--text)">
                        @foreach(['NEW'=>'Nouvelle','IN_PROGRESS'=>'En cours','RESOLVED'=>'Traitée'] as $value => $label)<option value="{{ $value }}" @selected($contactRequest->status === $value)>{{ $label }}</option>@endforeach
                    </select>
                    <button class="button button-small" type="submit">Enregistrer le statut</button>
                </form>
            </article>
        @empty
            <div class="empty-state">Aucune demande pour le moment.</div>
        @endforelse
    </div>
    {{ $requests->links() }}
</section>
@endsection
