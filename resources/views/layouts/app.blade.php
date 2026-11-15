<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#071121">
    <meta name="description" content="{{ config('app.name') }} : trading automatisé, formation et investissement.">
    <meta property="og:title" content="{{ $title ?? config('app.name') }}">
    <meta property="og:image" content="{{ asset('assets/faso-digital-trading-logo.jpeg') }}">
    <meta name="twitter:card" content="summary_large_image">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v={{ filemtime(public_path('favicon.svg')) }}">
    <link rel="stylesheet" href="{{ asset('assets/faso-digital-trading.css') }}?v={{ filemtime(public_path('assets/faso-digital-trading.css')) }}">
    @if(request()->routeIs('admin.*'))<link rel="stylesheet" href="{{ asset('assets/admin-console.css') }}?v={{ filemtime(public_path('assets/admin-console.css')) }}">@endif
</head>
<body>
    <a class="skip-link" href="#main">Aller au contenu</a>
    <header class="site-header">
        <nav class="nav-shell" aria-label="Navigation principale">
            <a class="brand" href="{{ route('home') }}#accueil" aria-label="{{ config('app.name') }}, accueil">
                <span class="brand-mark" aria-hidden="true"></span>
                <span class="brand-name"><span class="brand-name-main">FASO <span class="brand-name-accent">DIGITAL</span></span><small>TRADING</small></span>
            </a>
            <div class="nav-links">
                <a class="nav-link {{ request()->routeIs('home') ? 'is-active' : '' }}" href="{{ route('home') }}#accueil">Accueil</a>
                <a class="nav-link" href="{{ route('home') }}#fonctionnement">Fonctionnement</a>
                <a class="nav-link" href="{{ route('home') }}#marches">Marchés</a>
                <a class="nav-link {{ request()->routeIs('subscriptions') ? 'is-active' : '' }}" href="{{ route('subscriptions') }}">Offres</a>
                <a class="nav-link {{ request()->routeIs('brokers') ? 'is-active' : '' }}" href="{{ route('brokers') }}">Brokers</a>
                <a class="nav-link" href="{{ route('home') }}#faq">FAQ</a>
                <a class="nav-link" href="{{ route('home') }}#contact">Contact</a>
                @auth
                    @if(!auth()->user()->hasVerifiedEmail())<a class="nav-link" href="{{ route('verification.notice') }}">Vérifier mon e-mail</a>@endif
                    <a class="nav-link" href="{{ route('dashboard') }}">Mon espace</a>
                    <a class="nav-link" href="{{ route('support.index') }}">Support</a>
                    <a class="nav-link" href="{{ route('notifications.index') }}">Notifications <span class="nav-count">{{ auth()->user()->unreadNotifications()->count() }}</span></a>
                    @if(auth()->user()->role === 'INVESTOR')
                        <a class="nav-link {{ request()->routeIs('investor.brvm.*') ? 'is-active' : '' }}" href="{{ route('investor.brvm.index') }}">Ordres BRVM</a>
                    @endif
                    @if(in_array(auth()->user()->role, ['INVESTOR', 'PARTNER'], true) && auth()->user()->partner()->exists())
                        <a class="nav-link {{ request()->routeIs('partner.dashboard') ? 'is-active' : '' }}" href="{{ route('partner.dashboard') }}">Parrainage</a>
                    @endif
                    @if(auth()->user()->role === 'ADMIN' && auth()->user()->mfa_verified_at)
                        <a class="button button-small" href="{{ route('admin.dashboard') }}">Centre administrateur</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="button button-quiet button-small" type="submit">Déconnexion</button></form>
                @else
                    <a class="nav-link" href="{{ route('portal.investor.login') }}">Espace investisseur</a>
                    <a class="button button-small" href="{{ route('register') }}">Créer mon compte <span aria-hidden="true">↗</span></a>
                @endauth
            </div>
        </nav>
    </header>

    @if(auth()->check() && auth()->user()->role === 'ADMIN' && auth()->user()->mfa_verified_at && request()->routeIs('admin.*'))
        @include('admin._nav')
    @endif

    <main id="main">
        @auth
            @unless(auth()->user()->hasVerifiedEmail())<div class="risk-banner" role="status">Confirmez votre adresse e-mail pour accéder à votre espace. <a href="{{ route('verification.notice') }}">Voir les instructions</a></div>@endunless
        @endauth
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="footer-main">
            <div class="footer-brand">
                <a class="brand" href="{{ route('home') }}#accueil" aria-label="{{ config('app.name') }}, accueil"><span class="brand-mark" aria-hidden="true"></span><span class="brand-name"><span class="brand-name-main">FASO <span class="brand-name-accent">DIGITAL</span></span><small>TRADING</small></span></a>
                <p>Consultez les offres, échangez avec l’équipe et retrouvez vos informations dans votre espace personnel.</p>
            </div>
            <div class="footer-column"><strong>Explorer</strong><a href="{{ route('subscriptions') }}">Nos offres</a><a href="{{ route('brokers') }}">Brokers</a><a href="{{ route('legal') }}#mentions-legales">Mentions légales</a><a href="{{ route('legal') }}#conditions">Conditions</a><a href="{{ route('legal') }}#confidentialite">Confidentialité</a></div>
            <div class="footer-column"><strong>Portails</strong><a href="{{ route('portal.investor.login') }}">Espace investisseur</a><a href="{{ route('portal.admin.login') }}">Portail administrateur</a><a href="{{ route('register') }}">Créer un compte investisseur</a></div>
            <div class="footer-note"><span class="eyebrow">INFORMATION</span><p>Le trading comporte un risque de perte en capital. Les performances passées ne préjugent pas des résultats futurs. Aucun rendement n’est garanti. Le contenu ne constitue pas un conseil financier personnalisé.</p></div>
        </div>
        <div class="footer-bottom"><span>© {{ date('Y') }} FASO DIGITAL TRADING</span><span>Le trading comporte un risque de perte en capital.</span></div>
    </footer>
    @stack('scripts')
</body>
</html>
