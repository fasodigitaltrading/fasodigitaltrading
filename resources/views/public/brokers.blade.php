@extends('layouts.app')

@section('content')
<section class="page-hero">
    <div class="container">
        <span class="eyebrow">RÉSEAU DE PARTENAIRES</span>
        <h1>Les brokers, en toute clarté.</h1>
        <p>Les prestataires ne seront publiés ici qu’après vérification de leurs informations et de leur compatibilité.</p>
    </div>
</section>
<section class="container">
    <div class="catalog-grid">
        @forelse($brokers as $broker)
            <article class="broker-card">
                <div class="broker-heading">
                    @if($broker->logo)<img class="broker-logo" src="{{ $broker->logo }}" alt="Logo {{ $broker->name }}">@else<span class="broker-logo" aria-hidden="true">{{ mb_substr($broker->name,0,1) }}</span>@endif
                    <div><h2>{{ $broker->name }}</h2><span class="muted" style="font-size:10px">Partenaire indépendant</span></div>
                </div>
                <p>{{ $broker->description }}</p>
                <div class="broker-meta"><span class="pill">{{ $broker->mt5_supported ? 'Compatibilité MT5 confirmée' : 'Compatibilité MT5 à confirmer' }}</span></div>
                @if($broker->affiliate_url)
                    <a class="button button-quiet" href="{{ $broker->affiliate_url }}" target="_blank" rel="noopener noreferrer">Visiter le broker <span aria-hidden="true">↗</span></a>
                @elseif($broker->website_url)
                    <a class="button button-quiet" href="{{ $broker->website_url }}" target="_blank" rel="noopener noreferrer">Visiter le site <span aria-hidden="true">↗</span></a>
                @endif
            </article>
        @empty
            <div class="empty-state"><span class="eyebrow">CATALOGUE EN COURS</span><p>Aucun broker n’est actuellement publié. Les partenaires apparaîtront ici après vérification de leurs informations et de leur compatibilité.</p></div>
        @endforelse
    </div>
    <div style="padding-bottom:75px;color:var(--subtle);font-size:11px">Les brokers sont des prestataires tiers indépendants. Tout lien commercial sera indiqué avant sa publication.</div>
</section>
@endsection
