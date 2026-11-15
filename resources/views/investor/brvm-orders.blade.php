@extends('layouts.app')

@section('content')
<section class="container section">
    <span class="eyebrow">ESPACE INVESTISSEUR · BRVM</span>
    <h1>Demander un achat d’actions</h1>
    <p class="muted">Déposez une demande qui pourra être examinée par l’équipe. Toute négociation sur le marché doit être prise en charge par une SGI autorisée, notamment Coris Bourse, après transmission et acceptation de votre demande.</p>

    @if(session('status'))<div class="success-alert" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="form-alert" role="alert">{{ $errors->first() }}</div>@endif

    <div class="content-panel" style="margin:20px 0;border-color:var(--line-strong)">
        <h2>Cotations BRVM en temps réel indisponibles</h2>
        <p>FASO DIGITAL TRADING n’a pas encore de contrat de diffusion BRVM. Aucun cours en direct ni catalogue vérifié n’est affiché ici. Nous ne récupérons ni ne republions les cours du site BRVM. Consultez la source officielle pour vérifier le symbole, le titre et le cours avant de remplir la demande.</p>
        <p><a class="button button-small" href="{{ $officialQuotesUrl }}" target="_blank" rel="noopener noreferrer">Consulter les cours officiels de la BRVM ↗</a></p>
    </div>

    <div class="risk-banner" role="note">
        Cette page enregistre uniquement une demande auprès de FASO DIGITAL TRADING. Elle ne transmet pas automatiquement un ordre à Coris Bourse, ne constitue pas un ordre accepté, ne réserve aucun fonds et n’achète aucune action. Aucun canal de transmission Coris n’est encore configuré. Une éventuelle transmission devra être faite séparément par un canal autorisé; seule la SGI peut prendre en charge et exécuter la négociation.
    </div>

    <section class="content-panel" style="margin-top:20px">
        <h2>Nouvelle demande d’achat</h2>
        <form method="POST" action="{{ route('investor.brvm.orders.store') }}" class="form-grid" style="grid-template-columns:1fr;max-width:760px">
            @csrf
            <label for="symbol">Symbole BRVM <span aria-hidden="true">*</span></label>
            <input id="symbol" name="symbol" value="{{ old('symbol') }}" required maxlength="12" pattern="[A-Za-z0-9]{3,12}" autocomplete="off" placeholder="Vérifiez le symbole sur le site BRVM">
            <label for="security_name">Nom du titre (facultatif)</label>
            <input id="security_name" name="security_name" value="{{ old('security_name') }}" maxlength="255" autocomplete="off">
            <label for="quantity">Nombre d’actions souhaité <span aria-hidden="true">*</span></label>
            <input id="quantity" name="quantity" type="number" min="1" max="1000000" step="1" value="{{ old('quantity', 1) }}" required inputmode="numeric">
            <label for="limit_price">Prix limite maximum par action en FCFA (facultatif)</label>
            <input id="limit_price" name="limit_price" type="number" min="0.01" max="999999999999.99" step="0.01" value="{{ old('limit_price') }}" inputmode="decimal" aria-describedby="limit-price-help">
            <small id="limit-price-help" class="muted">Ce champ est votre indication de prix maximale, pas une cotation vérifiée ni une garantie d’achat.</small>
            <label class="check-row" style="margin-top:12px">
                <input type="checkbox" name="acknowledged_non_executable" value="1" style="width:auto;min-height:auto;padding:0" @checked(old('acknowledged_non_executable')) required>
                <span>Je comprends qu’il s’agit uniquement d’une demande reçue par FASO DIGITAL TRADING, non transmise automatiquement à Coris Bourse et non exécutée.</span>
            </label>
            <button class="button" type="submit">Enregistrer ma demande</button>
        </form>
    </section>

    <section style="margin-top:36px">
        <h2>Mes demandes précédentes</h2>
        <div class="request-list">
            @forelse($requests as $purchaseRequest)
                <article class="request-card">
                    <div class="request-heading">
                        <div><span class="request-kind">{{ $purchaseRequest->symbol }}</span><h3>{{ $purchaseRequest->security_name_snapshot ?: 'Titre à confirmer par la SGI' }}</h3></div>
                        <span class="request-date">{{ $purchaseRequest->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <p>{{ number_format($purchaseRequest->quantity, 0, ',', ' ') }} action(s) demandée(s) · Prix limite : {{ $purchaseRequest->limit_price ? number_format((float) $purchaseRequest->limit_price, 2, ',', ' ').' FCFA' : 'non indiqué' }}</p>
                    <p>État : <strong>{{ ['RECEIVED'=>'Reçue par FASO DIGITAL TRADING','FORWARDED'=>'Transmission déclarée par l’administrateur','ACCEPTED'=>'Acceptée par la SGI (à confirmer)','REJECTED'=>'Refusée','EXECUTED'=>'Exécutée selon le suivi enregistré','CANCELLED'=>'Annulée'][$purchaseRequest->status] ?? $purchaseRequest->status }}</strong></p>
                    @if($purchaseRequest->coris_reference)<p>Référence de suivi : <strong>{{ $purchaseRequest->coris_reference }}</strong></p>@endif
                    @if($purchaseRequest->admin_note)<p class="muted">Mise à jour : {{ $purchaseRequest->admin_note }}</p>@endif
                    @if($purchaseRequest->status === 'RECEIVED')<p class="muted">Aucune transmission externe n’est enregistrée à ce stade.</p>@endif
                </article>
            @empty
                <div class="empty-state">Vous n’avez pas encore déposé de demande.</div>
            @endforelse
        </div>
        {{ $requests->links() }}
    </section>
</section>
@endsection
