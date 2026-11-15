@extends('layouts.app')

@section('content')
<section class="container form-layout">
    <div class="form-intro">
        <span class="eyebrow">SÉCURITÉ DU COMPTE</span>
        <h1>Choisissez un nouveau mot de passe.</h1>
        <p>Après modification, les autres sessions du compte devront se reconnecter.</p>
    </div>
    <div class="form-card">
        <h2>Nouveau mot de passe</h2>
        @if($errors->any())<div class="form-alert" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('password.update') }}">@csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="field"><label for="email">Adresse e-mail du compte</label><input id="email" type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required></div>
            <div class="field"><label for="password">Nouveau mot de passe</label><input id="password" type="password" name="password" minlength="12" autocomplete="new-password" required><p class="field-help">12 caractères minimum. Choisissez une phrase secrète unique.</p></div>
            <div class="field"><label for="password_confirmation">Confirmer le mot de passe</label><input id="password_confirmation" type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></div>
            <button class="button" type="submit">Modifier le mot de passe <span aria-hidden="true">→</span></button>
        </form>
    </div>
</section>
@endsection
