@extends('layouts.app')

@section('content')
<div class="container">
    <p class="brand-accent">FASO DIGITAL TRADING · ADMINISTRATION</p>
    <h1>Catalogue des offres</h1>
    <p class="muted">Les modifications s’appliquent aux nouvelles souscriptions. Chaque souscription enregistre un instantané de ses conditions.</p>
    @if(session('status'))<div class="success-alert" role="status">{{ session('status') }}</div>@endif
    @foreach($plans as $plan)
        <section class="card" style="margin:18px 0">
            <form method="POST" action="{{ route('admin.plans.update', $plan) }}">
                @csrf @method('PUT')
                <div class="grid">
                    <div><label for="name-{{ $plan->id }}">Nom</label><input id="name-{{ $plan->id }}" name="name" value="{{ old('name', $plan->name) }}" required maxlength="120"></div>
                    <div><label for="currency-{{ $plan->id }}">Devise</label><input id="currency-{{ $plan->id }}" name="currency" value="{{ old('currency', $plan->currency) }}" minlength="3" maxlength="3" pattern="[A-Z]{3}" required></div>
                    <div><label for="price-{{ $plan->id }}">Prix actif ({{ $plan->currency }})</label><input id="price-{{ $plan->id }}" type="number" name="price" value="{{ old('price', $plan->price) }}" min="0.01" step="0.01" required></div>
                    <div><label for="list-price-{{ $plan->id }}">Prix affiché avant remise ({{ $plan->currency }})</label><input id="list-price-{{ $plan->id }}" type="number" name="list_price" value="{{ old('list_price', $plan->list_price ?? $plan->price) }}" min="0" step="0.01" required></div>
                    <div><label for="capital-{{ $plan->id }}">Capital indiqué ({{ $plan->currency }})</label><input id="capital-{{ $plan->id }}" type="number" name="capital_required" value="{{ old('capital_required', $plan->capital_required) }}" min="0" step="0.01" required></div>
                    <div><label for="days-{{ $plan->id }}">Durée (jours)</label><input id="days-{{ $plan->id }}" type="number" name="duration_days" value="{{ old('duration_days', $plan->duration_days) }}" min="1" max="3650" required></div>
                    <div><label for="accounts-{{ $plan->id }}">Comptes MT5 autorisés</label><input id="accounts-{{ $plan->id }}" type="number" name="max_mt5_accounts" value="{{ old('max_mt5_accounts', $plan->max_mt5_accounts) }}" min="1" max="100" required></div>
                    <div><label for="sort-{{ $plan->id }}">Ordre d’affichage</label><input id="sort-{{ $plan->id }}" type="number" name="sort_order" value="{{ old('sort_order', $plan->sort_order) }}" min="0" max="65535" required></div>
                </div>
                <div style="margin-top:14px"><label for="description-{{ $plan->id }}">Description</label><textarea id="description-{{ $plan->id }}" name="description" maxlength="2000">{{ old('description', $plan->description) }}</textarea></div>
                <div style="margin-top:14px"><label for="features-{{ $plan->id }}">Avantages (un par ligne)</label><textarea id="features-{{ $plan->id }}" name="features_text" maxlength="3000" rows="5">{{ old('features_text', implode("\n", $plan->features ?? [])) }}</textarea></div>
                <div class="form-grid" style="margin-top:14px">
                    <div class="field field-full"><label for="price-reason-{{ $plan->id }}">Motif requis si le prix change</label><input id="price-reason-{{ $plan->id }}" name="price_change_reason" maxlength="500" value="{{ old('price_change_reason') }}"></div>
                </div>
                <label class="check-row"><input type="checkbox" name="price_change_confirmed" value="yes" @checked(old('price_change_confirmed') === 'yes')><span>Je confirme que tout nouveau prix s’appliquera aux commandes créées après cette modification uniquement.</span></label>
                <label class="check-row"><input type="checkbox" name="status" value="1" @checked(old('status', $plan->status))><span>Offre publiée</span></label>
                <button class="button button-quiet" type="submit">Enregistrer {{ $plan->name }}</button>
                @if($errors->any())<div class="form-alert" role="alert">{{ $errors->first() }}</div>@endif
            </form>
        </section>
    @endforeach
</div>
@endsection
