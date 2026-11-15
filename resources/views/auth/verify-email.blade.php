@extends('layouts.app')

@section('content')
<section class="container form-layout">
    <div class="form-intro">
        <span class="eyebrow">VÉRIFICATION DU COMPTE</span>
        <h1>Confirmez votre adresse e-mail.</h1>
        <p>L’adresse <strong>{{ auth()->user()->email }}</strong> n’est pas encore vérifiée. Les fonctions réservées restent fermées jusqu’à la confirmation du lien signé.</p>
        <div class="form-points"><div class="form-point"><span>✓</span> Lien signé et limité dans le temps</div><div class="form-point"><span>✓</span> Aucune commande ni paiement lancé</div></div>
    </div>
    <div class="form-card">
        <h2>Vérifiez votre boîte de réception</h2>
        <p class="muted">État du service : {{ $emailDeliveryStatus }}.</p>
        @if(session('verification_email_failed'))<div class="form-alert" role="alert">Le serveur e-mail n’a pas accepté l’envoi. Contactez l’assistance ou réessayez une fois le service e-mail configuré.</div>@endif
        @if(session('welcome_email_failed'))<div class="form-alert" role="alert">Le message de bienvenue n’a pas pu être envoyé. Le compte reste créé; vérifiez la configuration e-mail avant de réessayer.</div>@endif
        @if(session('admin_notification_failed'))<div class="form-alert" role="alert">L’alerte d’inscription à l’administrateur n’a pas pu être envoyée. L’équipe ne recevra pas cette inscription tant que le service e-mail n’est pas configuré.</div>@endif
        @if(session('status'))<div class="success-alert" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="form-alert" role="alert">{{ $errors->first() }}</div>@endif
        <p>Un nouveau lien expire après {{ (int) config('auth.verification.expire', 60) }} minutes. Vous pouvez demander au maximum trois renvois par heure.</p>
        <form method="POST" action="{{ route('verification.send') }}">@csrf
            <button class="button" type="submit">Renvoyer le lien <span aria-hidden="true">→</span></button>
        </form>
        <form method="POST" action="{{ route('logout') }}" class="form-foot">@csrf<button class="button button-quiet" type="submit">Se déconnecter</button></form>
    </div>
</section>
@endsection
