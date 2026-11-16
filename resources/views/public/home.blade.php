@extends('layouts.app')

@section('content')
<div class="risk-banner">Le trading comporte un risque de perte. Les offres ne garantissent aucun résultat financier.</div>

<section class="hero" id="accueil">
    <div class="container hero-grid">
        <div class="hero-copy">
            <span class="eyebrow">{{ config('app.name') }} · MT5</span>
            <h1><span class="hero-accent">{{ $headline }}</span></h1>
            <p class="hero-lede">{{ $description }}</p>
            <div class="button-row">
                <a class="button" href="#contact">Contacter l’équipe <span class="button-arrow" aria-hidden="true">↗</span></a>
                <a class="button button-quiet" href="#packs">Voir les offres <span aria-hidden="true">↓</span></a>
            </div>
            <div class="metric-row">
                <div><strong>MT5</strong><span>plateforme<br>prise en charge</span></div>
                <div><strong>Lecture seule</strong><span>connexion de compte<br>avec accès dédié</span></div>
                <div><strong>{{ $plans->count() }}</strong><span>formules<br>au catalogue</span></div>
            </div>
        </div>
        <div class="hero-visual" aria-label="Logo officiel {{ config('app.name') }}">
            <div class="hero-logo-frame">
                <img src="{{ asset('assets/faso-digital-trading-logo.jpeg') }}" alt="Logo FASO DIGITAL TRADING : carte du Burkina Faso, taureau, loup et graphiques boursiers" fetchpriority="high">
            </div>
            <p class="hero-logo-caption">IDENTITÉ OFFICIELLE · {{ config('app.name') }}</p>
        </div>
    </div>
</section>

<section class="section market-section section-soft" id="marches">
    <div class="container">
        <div class="market-heading">
            <div>
                <span class="eyebrow">MARCHÉS · DONNÉES TRADINGVIEW</span>
                <h2 class="section-title">Suivez l’or et les actions.</h2>
                <p class="section-copy">Choisissez un instrument pour afficher son graphique interactif fourni par TradingView.</p>
            </div>
            <div class="market-select-wrap">
                <label for="market-symbol">Instrument</label>
                <select id="market-symbol" aria-label="Choisir un instrument de marché">
                    <option value="OANDA:XAUUSD">Or · XAU/USD</option>
                    <option value="NASDAQ:AAPL">Apple · AAPL</option>
                    <option value="NASDAQ:MSFT">Microsoft · MSFT</option>
                    <option value="NASDAQ:NVDA">NVIDIA · NVDA</option>
                    <option value="NYSE:TSLA">Tesla · TSLA</option>
                    <option value="AMEX:SPY">ETF S&amp;P 500 · SPY</option>
                </select>
            </div>
        </div>
        <div class="market-chart-shell" aria-label="Graphique TradingView">
            <tv-advanced-chart id="black-wolf-market-chart" symbol="OANDA:XAUUSD" interval="60" theme="dark" locale="fr"></tv-advanced-chart>
            <div class="market-chart-loading" aria-hidden="true">Chargement du graphique TradingView…</div>
        </div>
        <p class="market-disclaimer">Source : TradingView. Les marchés, symboles et délais de cotation dépendent des flux disponibles auprès du fournisseur et des places boursières. Ce graphique est informatif et ne constitue pas un conseil financier.</p>
    </div>
</section>
<script type="module" src="https://widgets.tradingview-widget.com/w/en/tv-advanced-chart.js"></script>
<script type="module">
    const selector = document.getElementById('market-symbol');
    const chart = document.getElementById('black-wolf-market-chart');
    const chartShell = chart?.parentElement;
    const chartMessage = chartShell?.querySelector('.market-chart-loading');
    selector?.addEventListener('change', () => chart?.setAttribute('symbol', selector.value));
    const unavailableTimer = window.setTimeout(() => {
        if (!customElements.get('tv-advanced-chart') && chartMessage) {
            chartMessage.textContent = 'Le service graphique TradingView est momentanément indisponible.';
            chartShell?.classList.add('market-chart-unavailable');
        }
    }, 10000);
    customElements.whenDefined('tv-advanced-chart').then(() => {
        window.clearTimeout(unavailableTimer);
        chartShell?.classList.add('market-chart-ready');
    });
</script>

<section class="section section-soft" id="fonctionnement">
    <div class="container">
        <span class="eyebrow">COMMENT ÇA FONCTIONNE</span>
        <h2 class="section-title">Des étapes compréhensibles,<br>avec vos accès sous contrôle.</h2>
        <p class="section-copy">La plateforme centralise le catalogue, les demandes et l’accès à votre espace. Les services dépendent de leur configuration réelle.</p>
        <div class="steps-grid">
            <article class="process-step"><span class="step-number">01</span><h3>Consultez le catalogue</h3><p>Comparez les prix USD. L’équivalent XOF est indicatif et daté; la commande verrouille le tarif et le taux utilisé.</p></article>
            <article class="process-step"><span class="step-number">02</span><h3>Créez votre espace</h3><p>Vos demandes et les données réellement rattachées à votre compte y sont regroupées.</p></article>
            <article class="process-step"><span class="step-number">03</span><h3>Rattachez un compte compatible</h3><p>La lecture MT5 utilise un connecteur autorisé et un jeton dédié. Le mot de passe principal n’est pas demandé.</p></article>
        </div>
    </div>
</section>

<section class="section" id="packs">
    <div class="container">
        <div class="section-heading-row"><div><span class="eyebrow">CATALOGUE</span><h2 class="section-title">Les formules FASO DIGITAL TRADING.</h2><p class="section-copy">USD reste la devise de référence. Pour les paiements, l’équivalence XOF applique le taux fixe de 600 F CFA par dollar.</p>
            <p class="fx-rate-meta">Taux fixe utilisé : 1 USD = 600 XOF.</p>
        </div><span class="currency-pill">TARIFS USD</span></div>
        @if($plans->isEmpty())
            <div class="plan-notice">Aucune formule n’est publiée pour le moment. Contactez l’équipe pour obtenir les informations disponibles.</div>
        @else
            <div class="pack-grid pack-grid-model">
                @foreach($plans as $plan)
                    <x-subscription-package-card :plan="$plan" :fx-quote="$fxQuote" :highlight="$plan->slug === 'premium'">
                        @if(in_array($plan->id, $orderPlanIds, true) && auth()->check() && auth()->user()->role === 'INVESTOR' && auth()->user()->hasVerifiedEmail())
                            <form method="POST" action="{{ route('subscriptions.orders.store', $plan) }}">
                                @csrf<input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                                <button class="button" type="submit">Choisir ce pack</button>
                            </form>
                        @elseif(in_array($plan->id, $orderPlanIds, true) && auth()->check() && auth()->user()->role === 'INVESTOR' && !auth()->user()->hasVerifiedEmail())
                            <form method="POST" action="{{ route('subscriptions.orders.prepare', $plan) }}">@csrf<button class="button" type="submit">Choisir ce pack</button></form>
                        @elseif(in_array($plan->id, $orderPlanIds, true) && !auth()->check())
                            <a class="button" href="{{ route('register', ['pack' => $plan->slug]) }}">Choisir ce pack</a>
                        @else
                            <a class="button" href="{{ route('login') }}">Choisir ce pack</a>
                        @endif
                    </x-subscription-package-card>
                @endforeach
            </div>
        @endif
        <p class="projection-disclaimer"><strong>Risque de marché :</strong> Les gains prévisionnels sont des objectifs indicatifs et ne constituent pas une garantie de rendement. Le trading comporte des risques de perte partielle ou totale du capital investi.</p>
        <div class="payment-preview"><div><span class="eyebrow">COMMANDE ET PAIEMENT</span>
                <p>Chaque pack mène à un récapitulatif. Les nouvelles commandes sont réglées uniquement en USDT BEP20.@if($manualPaymentReady) L’administrateur vérifie la transaction avant toute activation.@else Le parcours de paiement est suspendu jusqu’à la configuration sécurisée du serveur.@endif</p>
        </div><span class="payment-status">{{ $manualPaymentReady ? 'USDT BEP20 · vérification manuelle' : 'Paiement en attente de configuration' }}</span></div>
    </div>
</section>

<section class="section section-soft" id="transparence">
    <div class="container risk-panel"><span class="risk-icon">!</span><div><span class="eyebrow">RISQUE DE MARCHÉ</span><h2>Aucun rendement n’est garanti.</h2><p>Le trading peut entraîner la perte partielle ou totale du capital. Les performances passées ne préjugent pas des performances futures. Les informations affichées ne constituent pas un conseil financier.</p></div></div>
</section>

<section class="section" id="faq">
    <div class="container"><span class="eyebrow">QUESTIONS FRÉQUENTES</span><h2 class="section-title">Avant de commencer.</h2><div class="faq-list">
        <details><summary>Quelles données de marché sont affichées ?</summary><p>Le graphique TradingView affiche l’or et une sélection d’actions américaines; la disponibilité et le délai dépendent des flux TradingView et des places concernées. Il n’y a pas de prévisions générées par la plateforme. Les cotations BRVM ne sont pas reproduites ici; consultez le <a href="https://www.brvm.org/fr/flux-en-temps-reel" target="_blank" rel="noopener noreferrer">flux officiel de la BRVM</a>.</p></details>
        <details><summary>Comment payer un abonnement ?</summary><p>Les nouvelles commandes sont réglées uniquement en USDT sur le réseau BEP20. Après le transfert, l’administrateur vérifie la transaction avant toute activation. Aucun paiement mobile ou paiement par prestataire en ligne n’est actuellement proposé.</p></details>
        <details><summary>Comment connecter mon compte MT5 ?</summary><p>La lecture de compte nécessite un accès MetaApi dédié et configuré. N’envoyez jamais votre mot de passe principal MT5 via ce site.</p></details>
        @if($plans->contains(fn($plan) => (float) $plan->capital_required > 0))<details><summary>Le capital indiqué est-il un paiement à FASO DIGITAL TRADING ?</summary><p>Non. Le capital affiché dans le catalogue est distinct du tarif de la formule. Vérifiez les conditions et risques directement auprès de votre broker.</p></details>@endif
    </div></div>
</section>

<section class="section section-soft" id="contact">
    <div class="container contact-layout">
        <div class="contact-copy"><span class="eyebrow">CONTACT</span><h2 class="section-title">Une question sur une formule ?</h2><p class="section-copy">Envoyez une demande à l’équipe. Elle sera enregistrée dans l’espace d’administration pour traitement.</p><div class="contact-reassurance"><span>✓</span> Ce formulaire ne déclenche aucun paiement</div><div class="contact-reassurance"><span>✓</span> Aucune donnée de marché n’est générée par le formulaire</div></div>
        <div class="form-card contact-card">
            @if(session('contact_status'))<div class="success-alert" role="status">{{ session('contact_status') }}</div>@endif
            @if($errors->any())<div class="form-alert" role="alert">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('contact.store') }}">@csrf
                <div class="field"><label for="contact_name">Nom complet</label><input id="contact_name" name="name" value="{{ old('name') }}" autocomplete="name" required maxlength="120" placeholder="Votre nom"></div>
                <div class="field"><label for="contact_email">E-mail</label><input id="contact_email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required maxlength="255" placeholder="vous@exemple.com"></div>
                <div class="field"><label for="contact_intent">Votre besoin</label><select id="contact_intent" name="intent" required><option value="demo">Demande d’information</option><option value="quote">Demande de devis</option><option value="call">Être rappelé</option><option value="question">Question générale</option></select></div>
                <div class="field"><label for="contact_package">Formule souhaitée <span class="muted">(facultatif)</span></label><select id="contact_package" name="package"><option value="">Je ne sais pas encore</option>@foreach($plans as $plan)<option value="{{ $plan->slug }}" @selected(old('package', $selectedPackage) === $plan->slug)>{{ $plan->name }}</option>@endforeach</select></div>
                <div class="field"><label for="contact_message">Votre message</label><textarea id="contact_message" name="message" required minlength="10" maxlength="5000" rows="4" placeholder="Dites-nous comment nous pouvons vous aider…">{{ old('message') }}</textarea></div>
                <div class="honeypot" aria-hidden="true"><label for="company">Société</label><input id="company" name="company" tabindex="-1" autocomplete="off"></div>
                <label class="check-row"><input type="checkbox" name="privacy_consent" value="1" required><span>J’accepte que mes coordonnées soient utilisées pour répondre à cette demande, selon la <a href="{{ route('legal') }}#confidentialite" target="_blank" rel="noopener">notice de confidentialité</a>.</span></label>
                <button class="button" type="submit">Envoyer ma demande <span aria-hidden="true">→</span></button>
                <p class="form-foot">Vos données sont enregistrées afin de traiter votre demande.</p>
            </form>
        </div>
    </div>
</section>
@endsection
