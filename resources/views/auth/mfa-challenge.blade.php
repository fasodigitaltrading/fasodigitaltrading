@extends('layouts.app')

@section('content')
<section class="auth-shell">
    <div class="auth-card">
        <span class="eyebrow">DOUBLE AUTHENTIFICATION</span>
        <h1>Vérifie ton identité.</h1>
        <p>Entre le code à 6 chiffres de ton application d’authentification.</p>
        <form method="POST" action="{{ route('mfa.verify') }}" class="auth-form">
            @csrf
            <label for="code">Code à 6 chiffres</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus>
            @error('code')<span class="field-error">{{ $message }}</span>@enderror
            <button class="button button-primary" type="submit">Vérifier le code</button>
        </form>
    </div>
</section>
@endsection
