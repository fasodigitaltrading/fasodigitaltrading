@extends('layouts.app')

@section('content')
<section class="container section">
    <span class="eyebrow">PORTAIL ADMINISTRATEUR</span>
    <h1>Demandes d’achat BRVM</h1>
    <p class="muted">File de demandes clients. L’accès est réservé à l’administrateur principal après MFA. Aucun envoi API à Coris Bourse n’est configuré.</p>
    <div class="risk-banner" role="note">La mise à jour de ce suivi ne transmet ni ne place un ordre. Les statuts « Acceptée » et « Exécutée » doivent refléter une confirmation reçue de la SGI. @if(!$corisManualChannelEnabled) Aucun canal Coris autorisé n’est actuellement configuré : les étapes de transmission et d’exécution sont désactivées. @else Enregistrez une transmission uniquement après un envoi réel par le canal Coris autorisé configuré, avec sa référence. @endif</div>
    @if(session('status'))<div class="success-alert" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="form-alert" role="alert">{{ $errors->first() }}</div>@endif

    <div class="request-list" style="margin-top:20px">
        @forelse($requests as $purchaseRequest)
            <article class="request-card">
                <div class="request-heading">
                    <div><span class="request-kind">{{ $purchaseRequest->symbol }} · {{ number_format($purchaseRequest->quantity, 0, ',', ' ') }} action(s)</span><h2>{{ $purchaseRequest->security_name_snapshot ?: 'Titre à confirmer' }}</h2><a href="mailto:{{ $purchaseRequest->user->email }}">{{ $purchaseRequest->user->first_name }} {{ $purchaseRequest->user->last_name }} · {{ $purchaseRequest->user->email }}</a></div>
                    <span class="request-date">{{ $purchaseRequest->created_at->format('d/m/Y H:i') }}</span>
                </div>
                <p>Prix limite client : {{ $purchaseRequest->limit_price ? number_format((float) $purchaseRequest->limit_price, 2, ',', ' ').' FCFA / action' : 'non indiqué' }} · Statut : <strong>{{ $purchaseRequest->status }}</strong></p>
                <p>Référence Coris : {{ $purchaseRequest->coris_reference ?: 'aucune' }} · Accusé non-exécutoire : {{ $purchaseRequest->customer_acknowledged_at?->format('d/m/Y H:i') }}</p>
                @if($purchaseRequest->admin_note)<p class="muted">Dernière note : {{ $purchaseRequest->admin_note }}</p>@endif

                @if(in_array($purchaseRequest->status, ['RECEIVED', 'FORWARDED', 'ACCEPTED'], true))
                    @php
                        $allNextStatuses = [
                            'RECEIVED' => ['FORWARDED'=>'Transmise manuellement','REJECTED'=>'Refuser la demande','CANCELLED'=>'Annuler'],
                            'FORWARDED' => ['ACCEPTED'=>'Acceptée par la SGI','REJECTED'=>'Refusée par la SGI','CANCELLED'=>'Annuler'],
                            'ACCEPTED' => ['EXECUTED'=>'Exécutée (confirmation SGI)','REJECTED'=>'Refusée par la SGI','CANCELLED'=>'Annuler'],
                        ][$purchaseRequest->status];
                        $nextStatuses = $corisManualChannelEnabled
                            ? $allNextStatuses
                            : array_intersect_key($allNextStatuses, ['REJECTED'=>true, 'CANCELLED'=>true]);
                    @endphp
                    <form method="POST" action="{{ route('admin.brvm-orders.update', $purchaseRequest) }}" class="form-grid" style="grid-template-columns:1fr;max-width:760px;margin-top:18px">
                        @csrf @method('PUT')
                        <label for="status-{{ $purchaseRequest->id }}">Prochain statut</label>
                        <select id="status-{{ $purchaseRequest->id }}" name="status" required>
                            @foreach($nextStatuses as $status => $label)<option value="{{ $status }}">{{ $label }}</option>@endforeach
                        </select>
                        <label for="reference-{{ $purchaseRequest->id }}">Référence Coris (obligatoire après transmission)</label>
                        <input id="reference-{{ $purchaseRequest->id }}" name="coris_reference" value="{{ $purchaseRequest->coris_reference }}" maxlength="120" autocomplete="off">
                        <label for="note-{{ $purchaseRequest->id }}">Note de suivi visible par le client</label>
                        <textarea id="note-{{ $purchaseRequest->id }}" name="admin_note" rows="2" maxlength="2000" required minlength="5"></textarea>
                        <button class="button button-small" type="submit">Enregistrer le suivi</button>
                    </form>
                @else
                    <p class="muted">Cette demande est dans un état final; aucune autre transition n’est autorisée.</p>
                @endif
            </article>
        @empty
            <div class="empty-state">Aucune demande d’achat BRVM.</div>
        @endforelse
    </div>
    {{ $requests->links() }}
</section>
@endsection
