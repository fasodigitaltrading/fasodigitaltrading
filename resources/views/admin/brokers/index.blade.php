@extends('layouts.app')
@section('content')
<div class="container">
    <p class="brand-accent">ADMINISTRATION</p><h1>Gestion des brokers</h1>
    @if(session('status'))<p>{{ session('status') }}</p>@endif
    @if($errors->any())<p style="color:#ff8080">{{ $errors->first() }}</p>@endif
    <div class="card">
        <h2>Ajouter un broker</h2>
        <form method="POST" action="{{ route('admin.brokers.store') }}">@csrf
            <label>Nom</label><input name="name" required maxlength="255">
            <label>Slug</label><input name="slug" required maxlength="255">
            <label>Description</label><textarea name="description"></textarea>
            <label>URL du logo (HTTPS)</label><input type="url" name="logo">
            <label>Site officiel (HTTPS)</label><input type="url" name="website_url">
            <label>Lien affilié (HTTPS)</label><input type="url" name="affiliate_url">
            <label>Ordre</label><input type="number" name="sort_order" min="0" value="0" required>
            <label><input type="checkbox" name="mt5_supported" value="1"> MT5 compatible</label>
            <label><input type="checkbox" name="status" value="1" checked> Publié</label><br>
            <button class="btn" type="submit">Créer</button>
        </form>
    </div>
    @foreach($brokers as $broker)
        <div class="card" style="margin-top:16px">
            <h2>{{ $broker->name }}</h2>
            <form method="POST" action="{{ route('admin.brokers.update', $broker) }}">@csrf @method('PUT')
                <label>Nom</label><input name="name" value="{{ $broker->name }}" required>
                <label>Slug</label><input name="slug" value="{{ $broker->slug }}" required>
                <label>Description</label><textarea name="description">{{ $broker->description }}</textarea>
                <label>URL du logo (HTTPS)</label><input type="url" name="logo" value="{{ $broker->logo }}">
                <label>Site officiel (HTTPS)</label><input type="url" name="website_url" value="{{ $broker->website_url }}">
                <label>Lien affilié (HTTPS)</label><input type="url" name="affiliate_url" value="{{ $broker->affiliate_url }}">
                <label>Ordre</label><input type="number" name="sort_order" min="0" value="{{ $broker->sort_order }}" required>
                <label><input type="checkbox" name="mt5_supported" value="1" @checked($broker->mt5_supported)> MT5 compatible</label>
                <label><input type="checkbox" name="status" value="1" @checked($broker->status)> Publié</label><br>
                <button class="btn" type="submit">Enregistrer</button>
            </form>
        </div>
    @endforeach
</div>
@endsection
