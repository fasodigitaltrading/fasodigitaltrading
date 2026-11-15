@extends('layouts.app')

@section('content')
<section class="container form-layout">
    <div class="form-intro">
        <span class="eyebrow">ACCÈS AU COMPTE</span>
        <h1>Retrouvez l’accès à votre espace.</h1>
        <p>Indiquez l’adresse e-mail de votre compte. Si elle correspond à un compte, nous vous enverrons un lien valable 60 minutes.</p>
        <div class="form-points"><div class="form-point"><span>✓</span> Lien à usage unique</div><div class="form-point"><span>✓</span> Mot de passe d’au moins 12 caractères</div></div>
    </div>
    <div class="form-card">
        <h2>Mot de passe oublié ?</h2>
        <p>Le message ne révèle pas si cette adresse possède un compte.</p>
        @if(session('status'))<div class="success-alert" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="form-alert" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('password.email') }}">@csrf
            <div class="field"><label for="email">Adresse e-mail</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus></div>
            <button class="button" type="submit">Envoyer le lien <span aria-hidden="true">→</span></button>
        </form>
        <p class="form-foot"><a href="{{ route('login') }}">Retour à la connexion</a></p>
    </div>
</section>
@endsection
