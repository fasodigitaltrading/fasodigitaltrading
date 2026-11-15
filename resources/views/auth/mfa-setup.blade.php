@extends('layouts.app')

@section('content')
<section class="auth-shell">
    <div class="auth-card">
        <span class="eyebrow">SÉCURITÉ ADMINISTRATEUR</span>
        <h1>Configure ton application d’authentification</h1>
        <p>Dans Google Authenticator, Microsoft Authenticator ou une application TOTP compatible, ajoute un compte en saisissant cette clé manuellement.</p>
        <div class="totp-secret" aria-label="Clé secrète de configuration">{{ $secret }}</div>
        <p class="form-hint">Garde cette clé privée et conserve-la dans un endroit sûr. Elle permet de générer les codes de connexion administrateur.</p>
        <form method="POST" action="{{ route('mfa.setup.verify') }}" class="auth-form">
            @csrf
            <label for="code">Code à 6 chiffres de l’application</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus>
            @error('code')<span class="field-error">{{ $message }}</span>@enderror
            <button class="button button-primary" type="submit">Activer la double authentification</button>
        </form>
    </div>
</section>
@endsection
