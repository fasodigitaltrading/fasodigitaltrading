@extends('layouts.app')

@section('content')
<section class="container form-layout">
    <div class="form-intro"><span class="eyebrow">{{ $portal === 'administrateur' ? 'PORTAIL ADMINISTRATEUR' : 'ESPACE INVESTISSEUR' }}</span><h1>{{ $portal === 'administrateur' ? 'Administration sécurisée.' : 'Retrouvez votre espace.' }}</h1><p>{{ $portal === 'administrateur' ? 'L’accès administrateur est réservé à la personne responsable du site et protégé par une vérification MFA.' : 'Connectez-vous pour consulter votre espace, vos demandes et les informations de votre offre.' }}</p><div class="form-points"><div class="form-point"><span>✓</span> Accès personnel et protégé</div><div class="form-point"><span>✓</span> {{ $portal === 'administrateur' ? 'Code MFA demandé après identification' : 'Espace investisseur individuel' }}</div></div></div>
    <div class="form-card"><h2>{{ $portal === 'administrateur' ? 'Connexion administrateur' : 'Connexion investisseur' }}</h2><p>Entrez les identifiants associés à votre compte.</p>
        @if($errors->any())<div class="form-alert" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ $portal === 'administrateur' ? route('portal.admin.authenticate') : ($portal === 'investisseur' ? route('portal.investor.authenticate') : route('login.store')) }}">@csrf
            <div class="field"><label for="email">Adresse e-mail</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus placeholder="vous@exemple.com"></div>
            <div class="field"><label for="password">Mot de passe</label><input id="password" type="password" name="password" autocomplete="current-password" required placeholder="Votre mot de passe"></div>
            <button class="button" type="submit">Se connecter <span aria-hidden="true">→</span></button>
        </form>
        @if($portal !== 'administrateur')<p class="form-foot"><a href="{{ route('password.request') }}">Mot de passe oublié ?</a></p>@endif
        @if($portal !== 'administrateur')<p class="form-foot">Vous n’avez pas encore de compte ? <a href="{{ route('register') }}">Créer un espace</a></p>@endif
        @if($portal)<p class="form-foot">Accéder à l’autre portail : <a href="{{ $portal === 'administrateur' ? route('portal.investor.login') : route('portal.admin.login') }}">{{ $portal === 'administrateur' ? 'Investisseur' : 'Administrateur' }}</a></p>@endif
    </div>
</section>
@endsection
