@extends('layouts.app')
@section('content')
<div class="container">
    <p class="brand-accent">ADMINISTRATION</p><h1>Contenu du site</h1>
    @if(session('status'))<p>{{ session('status') }}</p>@endif
    @if($errors->any())<p style="color:#ff8080">{{ $errors->first() }}</p>@endif
    <div class="card"><form method="POST" action="{{ route('admin.settings.update') }}">@csrf @method('PUT')
        <label>Titre d’accueil</label><input name="home_headline" maxlength="200" value="{{ old('home_headline', $settings['home_headline']) }}" required>
        <label>Description d’accueil</label><textarea name="home_description" maxlength="1000" required>{{ old('home_description', $settings['home_description']) }}</textarea>
        <button class="btn" type="submit">Enregistrer</button>
    </form></div>
</div>
@endsection
