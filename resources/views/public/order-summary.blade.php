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
                    <div><dt>Cours officiel appliqué</dt><dd>1 USD = {{ number_format((float) $order->fx_rate, 4, ',', ' ') }} XOF · BCEAO · publié le {{ $order->fx_rate_as_of?->format('d/m/Y') ?: 'date non précisée' }} @if($order->fx_source_url)· <a href="{{ $order->fx_source_url }}" target="_blank" rel="noopener noreferrer">source officielle</a>@endif</dd></div>
                @elseif($order->fx_provider === 'fixed-600' && $order->fx_rate === '600.0000')
                    <div><dt>Taux historique appliqué</dt><dd>1 USD = 600 XOF · taux enregistré sur cette ancienne commande</dd></div>
                @else
                    <div><dt>Taux historique enregistré</dt><dd>1 USD = {{ number_format((float) $order->fx_rate, 4, ',', ' ') }} XOF · {{ $order->fx_provider ?: 'source non précisée' }}</dd></div>
                    <div class="plan-notice" role="status">Cette commande conserve son taux historique. Actualisez le récapitulatif pour obtenir une nouvelle référence officielle avant tout paiement.</div>
                @endif
                <div><dt>Montant du transfert mobile</dt><dd>{{ number_format($order->amount_xof, 0, ',', ' ') }} XOF. L’administrateur vérifiera le montant réellement reçu.</dd></div>
                <div><dt>Devise du transfert mobile</dt><dd>XOF</dd></div>
            @else
                <div><dt>Équivalence FCFA</dt><dd>Aucune conversion n’est enregistrée pour cette commande. Le montant doit être vérifié avant tout paiement.</dd></div>
                <div><dt>Devise du transfert</dt><dd>Aucun transfert ne peut être demandé avant l’enregistrement d’une conversion XOF vérifiable.</dd></div>
            @endif
            @if($order->fx_rate && (!$order->quote_expires_at || $order->quote_expires_at->isPast()))
                <div><dt>Validité de la conversion</dt><dd>Le récapitulatif de conversion a expiré.@if($order->quote_expires_at) Expiré le {{ $order->quote_expires_at->format('d/m/Y H:i') }}.@endif Actualisez le cours officiel avant de poursuivre.</dd></div>
            @elseif($order->fx_rate)
                <div><dt>Validité du récapitulatif de conversion</dt><dd>Jusqu’au {{ $order->quote_expires_at->format('d/m/Y H:i') }} (heure du serveur). Le montant doit être confirmé par un prestataire réellement configuré.</dd></div>
            @endif
        </dl>
        @if($order->fx_fetched_at)<p class="muted">Conversion calculée par le serveur le {{ $order->fx_fetched_at->format('d/m/Y H:i:s') }} UTC.</p>@endif
        @if($paymentAttempt?->status === 'PENDING' && $paymentAttempt->checkout_url)
            <p class="form-alert" role="status">Un paiement est déjà en attente. Son état sera confirmé uniquement par PayDunya.</p>
            <a class="button" href="{{ $paymentAttempt->checkout_url }}">Reprendre le paiement sécurisé</a>
        @elseif($paymentAttempt?->status === 'PENDING')
            <div class="plan-notice" role="status">Une tentative de paiement est en cours de vérification par le prestataire. Aucun nouveau paiement ne peut être initié pour cette commande.</div>
        @elseif($order->status === 'PAID')
            <div class="success-alert" role="status">@if($paymentAttempt?->provider === 'manual-transfer') Paiement vérifié par l’administrateur.@else Paiement confirmé par le prestataire.@endif Consultez votre espace pour le statut de l’abonnement.</div>
        @elseif($latestManualSubmission?->status === 'PENDING')
            <div class="plan-notice" role="status">Votre demande de transfert est en cours de vérification. N’initiez pas un autre paiement avant la décision de l’administrateur.</div>
        @elseif($providerAvailable && $order->status !== 'PAID' && in_array($order->status, ['AWAITING_PAYMENT', 'PAYMENT_FAILED'], true))
            <form method="POST" action="{{ route('subscriptions.orders.checkout', $order) }}" style="margin-top:24px">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                <button class="button" type="submit">Continuer vers PayDunya · XOF</button>
                <p class="muted">Le paiement reste en attente jusqu’à confirmation serveur signée et vérification du montant.</p>
            </form>
        @elseif($manualPaymentAvailable && $manualPaymentMethods && $order->status !== 'PAID' && in_array($order->status, ['AWAITING_PAYMENT', 'PAYMENT_FAILED'], true))
            <div class="plan-notice" role="status">PayDunya n’est pas disponible pour cette commande. Vous pouvez transmettre les informations d’un transfert via un moyen manuel disponible ; l’abonnement restera inactif jusqu’à vérification réelle des fonds par l’administrateur.</div>
        @else
            <div class="plan-notice" role="status">Aucun moyen de paiement compatible n’est disponible pour cette commande. Aucun débit n’a été effectué et l’abonnement ne sera pas activé.</div>
            @if(in_array($order->status, ['AWAITING_PAYMENT', 'PAYMENT_FAILED'], true) && $latestManualSubmission?->status !== 'PENDING')
                <form method="POST" action="{{ route('subscriptions.orders.refresh', $order) }}" style="margin-top:16px">@csrf<button class="button button-quiet" type="submit">Actualiser le taux BCEAO et le récapitulatif</button></form>
            @endif
        @endif
        @if($latestManualSubmission?->status === 'PENDING')
            <div class="plan-notice" role="status" style="margin-top:24px">Demande reçue ({{ $latestManualSubmission->transaction_reference }}). L’administrateur doit vérifier que les fonds sont réellement arrivés avant l’activation. État de l’alerte e-mail : {{ ['SENT'=>'transport e-mail accepté, réception à confirmer','FAILED'=>'échec d’envoi; demande conservée dans la file admin','BLOCKED'=>'configuration e-mail en attente; demande conservée dans la file admin','SENDING'=>'envoi en cours','NOT_SENT'=>'envoi non confirmé'][$latestManualSubmission->admin_email_status] ?? $latestManualSubmission->admin_email_status }}. N’effectuez pas un second transfert pour cette commande.</div>
        @elseif($latestManualSubmission?->status === 'REJECTED')
            <div class="form-alert" role="alert" style="margin-top:24px">Le justificatif précédent a été refusé : {{ $latestManualSubmission->review_note }}. Vous pouvez transmettre une nouvelle transaction vérifiable.</div>
        @endif
        @if(!$manualPaymentReady && $order->status !== 'PAID')
            <div class="plan-notice" role="status" style="margin-top:24px">N’effectuez aucun transfert pour le moment : l’administrateur doit d’abord terminer la configuration sécurisée du serveur.</div>
        @endif
        @if($manualPaymentAvailable && $manualPaymentMethods)
            <section class="manual-payment-flow" style="margin-top:28px">
                <div class="card manual-payment-step">
                    <span class="eyebrow">ÉTAPE 1 · CODE DE TRANSACTION</span>
                    <h3>Choisissez le moyen de paiement</h3>
                    <p>Le transfert est réalisé hors du site. Le code OTP du site sert uniquement à relier votre demande à cette commande : il ne valide pas le transfert et ne remplace jamais le code de votre portefeuille.</p>
                    <form method="POST" action="{{ route('subscriptions.orders.manual-payment.otp', $order) }}">
                        @csrf
                        <fieldset class="payment-method-grid">
                            <legend class="sr-only">Moyen de paiement</legend>
                            @foreach($manualPaymentMethods as $methodKey => $method)
                                @php($paymentCurrency = strtoupper($method['currency']))
                                @php($paymentAmount = $paymentCurrency === 'USDT' ? $order->amount_usd : $order->amount_xof)
                                <label class="payment-method-choice">
                                    <input type="radio" name="method" value="{{ $methodKey }}" required @checked(old('method', $activeManualPaymentOtp?->method) === $methodKey)>
                                    <span class="payment-method-name">{{ $method['label'] }}</span>
                                    <span class="muted">Destinataire : <code>{{ $method['recipient'] }}</code></span>
                                    <span>Montant : <strong>{{ number_format((float) $paymentAmount, $paymentCurrency === 'USDT' ? 2 : 0, ',', ' ') }} {{ $paymentCurrency }}</strong></span>
                                    @if($methodKey === 'usdt_bep20')<small class="muted">Réseau BEP20 uniquement. Le montant reprend la valeur numérique du tarif USD.</small>@endif
                                </label>
                            @endforeach
                        </fieldset>
                        @error('method')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                        @error('manual_payment')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                        <button class="button" type="submit" style="margin-top:16px">Générer mon code OTP de transaction</button>
                    </form>
                </div>

                @if($manualPaymentOtpCode && $activeManualPaymentOtp)
                    <div class="otp-panel" role="status">
                        <div><span class="eyebrow">CODE À USAGE UNIQUE · COMMANDE #{{ $order->id }}</span><h3>Votre code de transaction</h3></div>
                        <input class="otp-code" type="text" value="{{ $manualPaymentOtpCode }}" readonly aria-label="Code OTP de transaction" onclick="this.select()">
                        <p>Copiez ce code maintenant. Il sera affiché une seule fois et expire le {{ $activeManualPaymentOtp->expires_at->format('d/m/Y à H:i') }}. N’inscrivez jamais le code PIN ou l’OTP de votre portefeuille dans ce formulaire.</p>
                        <dl class="order-details">
                            <div><dt>Destinataire</dt><dd><code>{{ $activeManualPaymentOtp->recipient_snapshot }}</code></dd></div>
                            <div><dt>Montant à transférer</dt><dd>{{ number_format((float) $activeManualPaymentOtp->expected_amount, $activeManualPaymentOtp->currency === 'XOF' ? 0 : 2, ',', ' ') }} {{ $activeManualPaymentOtp->currency }}</dd></div>
                        </dl>
                    </div>
                @elseif($activeManualPaymentOtp)
                    <div class="plan-notice" role="status">Un code est déjà actif pour cette commande, mais il n’est plus affiché. Pour le retrouver, générez-en un nouveau; l’ancien sera invalidé.</div>
                @endif

                @if($manualPaymentOtpCode && $activeManualPaymentOtp)
                    <div class="card manual-payment-step">
                        <span class="eyebrow">ÉTAPE 2 · INFORMATIONS DU TRANSFERT</span>
                        <h3>Après le transfert, envoyez les détails</h3>
                        <p>La demande et son justificatif privé seront ajoutés à la file d’administration. Une notification sera envoyée à l’adresse administrateur si le service e-mail est vérifié.</p>
                        <form method="POST" action="{{ route('subscriptions.orders.manual-payment', $order) }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="method" value="{{ $activeManualPaymentOtp->method }}">
                            <div class="field"><label for="manual-payment-otp">Code OTP généré</label><input id="manual-payment-otp" name="manual_payment_otp" type="text" inputmode="numeric" autocomplete="off" pattern="[0-9]{8}" maxlength="8" required></div>
                            @error('manual_payment_otp')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            <div class="form-grid">
                                <div class="field"><label for="manual-reference">Référence de transaction @if($activeManualPaymentOtp->method === 'usdt_bep20')(hash BSC)@endif</label><input id="manual-reference" name="transaction_reference" value="{{ old('transaction_reference') }}" required maxlength="191" autocomplete="off"></div>
                                <div class="field"><label for="manual-declared-amount">Montant déclaré ({{ $activeManualPaymentOtp->currency }})</label><input id="manual-declared-amount" name="declared_amount" type="number" min="0.01" step="0.01" value="{{ old('declared_amount', $activeManualPaymentOtp->expected_amount) }}" required></div>
                                <div class="field field-full"><label for="manual-transaction-time">Date et heure du transfert</label><input id="manual-transaction-time" name="transaction_occurred_at" type="datetime-local" value="{{ old('transaction_occurred_at') }}" required><small class="field-help">Utilisez l’heure locale affichée sur le reçu ou dans l’application de paiement.</small></div>
                                <div class="field field-full"><label for="manual-proof">Reçu ou preuve de transaction (PDF, JPG ou PNG · 5 Mo maximum)</label><input id="manual-proof" type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required></div>
                            </div>
                            @error('transaction_reference')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            @error('declared_amount')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            @error('transaction_occurred_at')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            @error('proof')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            <label class="consent-check" style="margin-top:16px"><input type="checkbox" name="payment_acknowledgement" value="yes" required @checked(old('payment_acknowledgement') === 'yes')> Je comprends que le justificatif seul ne confirme pas le paiement et que l’abonnement reste en attente jusqu’à la réception réelle et au contrôle de l’administrateur. Je ne transmettrai aucun code PIN ni code OTP de mon portefeuille.</label>
                            @error('payment_acknowledgement')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            <button class="button" type="submit" style="margin-top:16px">Envoyer la transaction à vérifier</button>
                        </form>
                    </div>
                @endif
            </section>
        @endif
    </article>
    <a class="text-link" href="{{ route('subscriptions') }}">Retour au catalogue</a>
</section>
@endsection
