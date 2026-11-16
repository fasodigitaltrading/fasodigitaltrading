@extends('layouts.app')

@section('content')
<section class="page-hero"><div class="container"><span class="eyebrow">COMMANDE · RÉCAPITULATIF</span><h1>Vérifiez votre formule avant le paiement.</h1><p>Référence de commande #{{ $order->id }} · créée le {{ $order->created_at->format('d/m/Y H:i') }}</p></div></section>
<section class="container" style="max-width:880px;padding-bottom:80px">
    @if($errors->has('payment'))<div class="form-alert" role="alert">{{ $errors->first('payment') }}</div>@endif
    @if(session('status'))<div class="success-alert" role="status">{{ session('status') }}</div>@endif
    @if(session('payment_unavailable'))<div class="form-alert" role="alert">{{ session('payment_unavailable') }}</div>@endif
    @if(session('fx_quote_unavailable'))<div class="form-alert" role="alert">{{ session('fx_quote_unavailable') }}</div>@endif
    <article class="pack-card" style="margin:24px 0">
        <div class="pack-topline"><span class="eyebrow">OFFRE SÉLECTIONNÉE</span><span class="pack-period">{{ $order->duration_days_snapshot }} JOURS</span></div>
        <h2>{{ $order->plan_name_snapshot }}</h2>
        <div class="pack-price"><strong>{{ number_format((float) $order->amount_usd, 2, ',', ' ') }} USD</strong><span>prix de référence enregistré côté serveur</span></div>
        <dl class="order-details">
            <div><dt>Durée</dt><dd>{{ $order->duration_days_snapshot }} jours</dd></div>
            @if($order->estimated_daily_gain_usd_snapshot !== null)<div><dt>Gain journalier prévisionnel</dt><dd>{{ number_format((float) $order->estimated_daily_gain_usd_snapshot, 2, ',', ' ') }} USD · objectif indicatif non garanti</dd></div>@endif
            <div><dt>Capital minimum indiqué</dt><dd>
                @if((float) ($order->capital_required_snapshot ?? 0) > 0)
                    {{ number_format((float) $order->capital_required_snapshot, 2, ',', ' ') }} USD
                @else
                    Non indiqué pour cette offre
                @endif
            </dd></div>
            <div><dt>Description</dt><dd>{{ $order->plan_description_snapshot ?: 'Aucune description complémentaire publiée.' }}</dd></div>
            <div><dt>Avantages</dt><dd>
                @if($order->plan_features_snapshot)
                    {{ implode(' · ', $order->plan_features_snapshot) }}
                @else
                    Voir les conditions de l’offre
                @endif
            </dd></div>
            <div><dt>Conditions d’utilisation</dt><dd><a href="{{ route('legal') }}#conditions" target="_blank" rel="noopener noreferrer">Lire les conditions et les informations sur les risques</a></dd></div>
            <div><dt>Frais</dt><dd>Aucun frais de plateforme n’est ajouté. Vérifiez les frais éventuels directement dans l’application de paiement avant de confirmer le transfert.</dd></div>
            @if($order->amount_xof && $order->fx_rate)
                <div><dt>Équivalence calculée</dt><dd>{{ number_format($order->amount_xof, 0, ',', ' ') }} XOF</dd></div>
                @if($order->fx_provider === 'bceao-reference')
                    <div><dt>Taux historique de cette commande</dt><dd>1 USD = {{ number_format((float) $order->fx_rate, 4, ',', ' ') }} XOF · BCEAO · publié le {{ $order->fx_rate_as_of?->format('d/m/Y') ?: 'date non précisée' }} @if($order->fx_source_url)· <a href="{{ $order->fx_source_url }}" target="_blank" rel="noopener noreferrer">source historique</a>@endif</dd></div>
                @elseif($order->fx_provider === 'fixed-600' && $order->fx_rate === '600.0000')
                    <div><dt>Taux fixe appliqué</dt><dd>1 USD = 600 XOF</dd></div>
                @else
                    <div><dt>Taux historique enregistré</dt><dd>1 USD = {{ number_format((float) $order->fx_rate, 4, ',', ' ') }} XOF · {{ $order->fx_provider ?: 'source non précisée' }}</dd></div>
                    <div class="plan-notice" role="status">Cette commande conserve son ancien taux. Actualisez le récapitulatif pour appliquer le taux fixe de 600 XOF par USD avant tout nouveau paiement.</div>
                @endif
                <div><dt>Équivalence indicative</dt><dd>{{ number_format($order->amount_xof, 0, ',', ' ') }} XOF. Le paiement demandé pour cette commande reste en USDT.</dd></div>
                <div><dt>Devise de paiement</dt><dd>USDT sur BNB Smart Chain (BEP20)</dd></div>
            @else
                <div><dt>Équivalence FCFA</dt><dd>Aucune conversion n’est enregistrée pour cette commande. Le montant doit être vérifié avant tout paiement.</dd></div>
                <div><dt>Devise de paiement</dt><dd>Aucun paiement ne peut être demandé avant l’enregistrement d’un montant de commande valide.</dd></div>
            @endif
            @if($order->fx_rate && (!$order->quote_expires_at || $order->quote_expires_at->isPast()))
                <div><dt>Validité du montant</dt><dd>Le récapitulatif de paiement a expiré.@if($order->quote_expires_at) Expiré le {{ $order->quote_expires_at->format('d/m/Y H:i') }}.@endif Actualisez le montant avant de poursuivre.</dd></div>
            @elseif($order->fx_rate)
                <div><dt>Validité du récapitulatif</dt><dd>Jusqu’au {{ $order->quote_expires_at->format('d/m/Y H:i') }} (heure du serveur). Le montant USDT est calculé à partir du prix USD enregistré.</dd></div>
            @endif
        </dl>
        @if($order->fx_fetched_at)<p class="muted">Conversion calculée par le serveur le {{ $order->fx_fetched_at->format('d/m/Y H:i:s') }} UTC.</p>@endif
        @if($paymentAttempt?->status === 'PENDING' && $paymentAttempt->checkout_url)
            <p class="form-alert" role="status">Un paiement en ligne historique est en attente. Les nouveaux paiements par prestataire en ligne sont suspendus; contactez l’administrateur pour rapprocher cette ancienne commande.</p>
        @elseif($paymentAttempt?->status === 'PENDING')
            <div class="plan-notice" role="status">Un paiement en ligne historique est en cours de rapprochement. Contactez l’assistance avant toute autre transaction.</div>
        @elseif($order->status === 'PAID')
            <div class="success-alert" role="status">@if($paymentAttempt?->provider === 'manual-transfer') Paiement vérifié par l’administrateur.@else Paiement confirmé par le prestataire.@endif Consultez votre espace pour le statut de l’abonnement.</div>
        @elseif($latestManualSubmission?->status === 'PENDING')
            <div class="plan-notice" role="status">Votre demande de transfert est en cours de vérification. N’initiez pas un autre paiement avant la décision de l’administrateur.</div>
        @elseif($providerAvailable && $order->status !== 'PAID' && in_array($order->status, ['AWAITING_PAYMENT', 'PAYMENT_FAILED'], true))
            <form method="POST" action="{{ route('subscriptions.orders.checkout', $order) }}" style="margin-top:24px">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                <button class="button" type="submit">Continuer vers le paiement sécurisé · XOF</button>
                <p class="muted">Le paiement reste en attente jusqu’à confirmation serveur signée et vérification du montant.</p>
            </form>
        @elseif($manualPaymentAvailable && $manualPaymentMethods && $order->status !== 'PAID' && in_array($order->status, ['AWAITING_PAYMENT', 'PAYMENT_FAILED'], true))
            <div class="plan-notice" role="status">Seul le paiement en USDT BEP20 est accepté. L’abonnement restera inactif jusqu’à la vérification de la transaction par l’administrateur.</div>
        @else
            <div class="plan-notice" role="status">Aucun moyen de paiement compatible n’est disponible pour cette commande. Aucun débit n’a été effectué et l’abonnement ne sera pas activé.</div>
            @if(in_array($order->status, ['AWAITING_PAYMENT', 'PAYMENT_FAILED'], true) && $latestManualSubmission?->status !== 'PENDING')
                <form method="POST" action="{{ route('subscriptions.orders.refresh', $order) }}" style="margin-top:16px">@csrf<button class="button button-quiet" type="submit">Appliquer le taux fixe de 600 XOF/USD</button></form>
            @endif
        @endif
        @if($latestManualSubmission?->status === 'PENDING')
            <div class="plan-notice" role="status" style="margin-top:24px">Capture reçue sous la référence {{ $latestManualSubmission->transaction_reference }}. L’administrateur vérifiera le hash sur BNB Smart Chain, le contrat BSC-USD, le destinataire, le montant exact et les confirmations. Objectif de traitement indicatif : 30 minutes, sans validation automatique. État du courriel administrateur : {{ ['SENT'=>'transport accepté; réception à confirmer','FAILED'=>'échec; demande conservée dans la file','BLOCKED'=>'configuration SMTP en attente','SENDING'=>'envoi en cours','NOT_SENT'=>'non envoyé'][$latestManualSubmission->admin_email_status] ?? $latestManualSubmission->admin_email_status }}. N’effectuez pas un second transfert.
                @if($latestManualSubmission->created_at)<br><small>Demande déposée le {{ $latestManualSubmission->created_at->format('d/m/Y à H:i') }} · <span data-review-countdown data-deadline-ms="{{ $latestManualSubmission->created_at->copy()->addMinutes(30)->getTimestamp() * 1000 }}">échéance indicative {{ $latestManualSubmission->created_at->copy()->addMinutes(30)->format('H:i') }}</span></small>@endif
            </div>
        @elseif($latestManualSubmission?->status === 'REJECTED')
            <div class="form-alert" role="alert" style="margin-top:24px">Le justificatif précédent a été refusé : {{ $latestManualSubmission->review_note }}. L’historique est conservé; vous pouvez envoyer une nouvelle capture après avoir corrigé le problème.</div>
        @elseif($latestManualSubmission?->status === 'INFO_REQUIRED')
            <div class="form-alert" role="alert" style="margin-top:24px">L’administrateur demande une information ou une nouvelle capture : {{ $latestManualSubmission->review_note }}. L’historique précédent est conservé.</div>
        @endif
        @if(!$manualPaymentReady && $order->status !== 'PAID')
            <div class="plan-notice" role="status" style="margin-top:24px">N’effectuez aucun transfert pour le moment : l’administrateur doit d’abord terminer la configuration sécurisée du serveur.</div>
        @endif
        @if($manualPaymentAvailable && $manualPaymentMethods)
            @php($cryptoMethod = $manualPaymentMethods['usdt_bep20'] ?? null)
            <section class="manual-payment-flow" style="margin-top:28px">
                <div class="card manual-payment-step">
                    <span class="eyebrow">ÉTAPE 1 · EFFECTUER LE TRANSFERT</span>
                    <h3>Payer par Binance-Peg BSC-USD sur BNB Smart Chain</h3>
                    <p>Le montant à transférer est la valeur numérique du tarif USD enregistré (ex. 30 USD = 30 USDT). Dans votre portefeuille, choisissez explicitement le jeton BSC-USD sur le réseau BNB Smart Chain (BEP20). Le code QR ci-dessous contient uniquement l’adresse, pas le jeton ni le montant.</p>
                    @if($cryptoMethod)
                        <div class="payment-method-choice" style="display:grid;gap:12px">
                            <span class="payment-method-name">{{ $crypto['symbol'] ?? $cryptoMethod['label'] }}</span>
                            <span>Montant exact : <strong>{{ number_format((float) $order->amount_usd, 2, ',', ' ') }} USDT</strong></span>
                            <span>Réseau : <strong>{{ $crypto['network'] ?? 'BNB Smart Chain (BEP20)' }}</strong> · Chain ID {{ $crypto['chain_id'] ?? 56 }}</span>
                            <span>Contrat du jeton : <code>{{ $crypto['token_contract'] ?? '' }}</code></span>
                            <div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap">
                                <canvas data-qr-address="{{ $cryptoMethod['recipient'] }}" width="180" height="180" aria-label="QR code contenant l’adresse du portefeuille"></canvas>
                                <div style="min-width:min(100%,360px)">
                                    <label for="crypto-recipient">Adresse de réception</label>
                                    <input id="crypto-recipient" type="text" value="{{ $cryptoMethod['recipient'] }}" readonly onclick="this.select()" style="width:100%">
                                    <button class="button button-quiet" type="button" data-copy-address="{{ $cryptoMethod['recipient'] }}" style="margin-top:8px">Copier l’adresse</button>
                                    <small class="field-help" data-copy-feedback role="status"></small>
                                </div>
                            </div>
                            <p class="form-alert" role="alert">Envoyez uniquement Binance-Peg BSC-USD sur BEP20. Ne transférez pas de BNB ni de jeton USDT d’un autre réseau. Une transaction envoyée sur le mauvais réseau peut être perdue.</p>
                            <p class="muted">L’adresse et le contrat sont affichés pour contrôler les détails dans votre portefeuille. Ne validez la transaction qu’après les avoir comparés.</p>
                        </div>
                    @else
                        <div class="form-alert" role="alert">Le paiement crypto n’est pas configuré. Aucun transfert ne doit être effectué.</div>
                    @endif
                </div>
                @if($cryptoMethod)
                    <div class="card manual-payment-step">
                        <span class="eyebrow">ÉTAPE 2 · JUSTIFICATIF DU TRANSFERT</span>
                        <h3>Après le transfert, envoyez uniquement la capture d’écran</h3>
                        <p>La capture sera jointe au courriel administrateur si SMTP est configuré et conservée dans le stockage privé. L’équipe retrouvera le hash réel sur le réseau et contrôlera le jeton, le destinataire, le montant et les confirmations. Une image seule ne confirme pas la réception des fonds.</p>
                        <form method="POST" action="{{ route('subscriptions.orders.manual-payment', $order) }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="method" value="usdt_bep20">
                            <div class="field"><label for="manual-proof">Capture d’écran du transfert (JPG ou PNG · 5 Mo maximum)</label><input id="manual-proof" type="file" name="proof" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required></div>
                            @error('proof')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            @error('payment')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            <label class="consent-check" style="margin-top:16px"><input type="checkbox" name="payment_acknowledgement" value="yes" required @checked(old('payment_acknowledgement') === 'yes')> Je comprends que la capture ne prouve pas le paiement. L’abonnement et sa licence restent inactifs jusqu’à vérification réelle et approbation par l’administrateur.</label>
                            @error('payment_acknowledgement')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            <button class="button" type="submit" style="margin-top:16px">J’ai payé · envoyer la capture</button>
                        </form>
                    </div>
                @endif
            </section>
        @endif
    </article>
    <a class="text-link" href="{{ route('subscriptions') }}">Retour au catalogue</a>
</section>
@endsection
@push('scripts')
    @if(($manualPaymentAvailable || $latestManualSubmission?->status === 'PENDING') && !empty($manualPaymentMethods['usdt_bep20']))
        @if(file_exists(public_path('build/manifest.json')))
            @vite('resources/js/app.js')
        @endif
    @endif
@endpush
