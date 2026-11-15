@extends('layouts.app')

@section('content')
<section class="container form-layout">
    <div class="form-intro"><span class="eyebrow">FASO DIGITAL TRADING</span><h1>Créez votre espace personnel.</h1><p>Vous pourrez y retrouver les informations associées à votre compte et vos demandes.</p><div class="form-points"><div class="form-point"><span>✓</span> Aucun mot de passe principal MT5 demandé</div><div class="form-point"><span>✓</span> Accès limité à votre espace</div><div class="form-point"><span>✓</span> Connexions externes uniquement si configurées</div></div></div>
    <div class="form-card"><h2>Créer mon compte</h2><p>Les champs marqués sont nécessaires à la création de votre espace.</p>
        @unless($registrationAvailable)<div class="form-alert" role="status">L’inscription est momentanément indisponible : la confirmation par e-mail doit d’abord être configurée et testée par l’équipe. Réessayez plus tard ou <a href="{{ route('home') }}#contact">contactez-nous</a>.</div>@endunless
        @if($errors->any())<div class="form-alert" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('register.store') }}">@csrf
            <input type="hidden" name="ref" value="{{ old('ref', $ref) }}">
            <input type="hidden" name="pack" value="{{ old('pack', $pack) }}">
            <div class="form-grid">
                <div class="field"><label for="first_name">Prénom</label><input id="first_name" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name" required placeholder="Votre prénom"></div>
                <div class="field"><label for="last_name">Nom</label><input id="last_name" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name" required placeholder="Votre nom"></div>
                <div class="field field-full"><label for="register_email">Adresse e-mail</label><input id="register_email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required placeholder="vous@exemple.com"></div>
                <div class="field"><label for="phone">Téléphone <span class="muted">(facultatif)</span></label><input id="phone" type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel"></div>
                <div class="field"><label for="country">Pays</label><input id="country" name="country" value="{{ old('country') }}" autocomplete="country-name" required></div>
                <div class="field"><label for="register_password">Mot de passe</label><input id="register_password" type="password" name="password" minlength="12" autocomplete="new-password" required placeholder="12 caractères minimum"><p class="field-help">Utilisez une phrase secrète unique.</p></div>
                <div class="field"><label for="password_confirmation">Confirmer le mot de passe</label><input id="password_confirmation" type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required placeholder="Retapez le mot de passe"></div>
            </div>
            <label class="check-row"><input type="checkbox" name="terms" value="1" required><span>J’ai lu et j’accepte les <a href="{{ route('legal') }}#conditions" target="_blank" rel="noopener">conditions d’utilisation</a> et la <a href="{{ route('legal') }}#confidentialite" target="_blank" rel="noopener">notice de confidentialité</a>.</span></label>
            <button class="button" type="submit" @disabled(!$registrationAvailable)>Créer mon espace <span aria-hidden="true">→</span></button>
        </form>
        <p class="form-foot">Vous avez déjà un compte ? <a href="{{ route('login') }}">Se connecter</a></p>
    </div>
</section>
@endsection
