@extends('layouts.app')

@section('content')
<section class="page-hero"><div class="container"><span class="eyebrow">FASO DIGITAL TRADING · OFFRES</span><h1>Choisissez votre formule</h1><p>Les prix sont définis en USD. Leur équivalence en FCFA est calculée avec le dernier cours de référence publié par la BCEAO.</p>
    @if($fxQuote)<p class="fx-rate-meta">Cours USD/XOF : 1 USD = {{ number_format((float) $fxQuote->rate, 4, ',', ' ') }} XOF · publié le {{ $fxQuote->asOf->format('d/m/Y') }} · <a href="{{ $fxQuote->sourceUrl }}" target="_blank" rel="noopener noreferrer">Source officielle BCEAO</a></p>@else<p class="fx-rate-meta">Le cours BCEAO n’est pas disponible pour le moment; aucune équivalence XOF ne sera inventée.</p>@endif
</div></section>
<section class="container" style="padding-bottom:80px">
    @if($errors->has('payment'))<div class="form-alert" role="alert">{{ $errors->first('payment') }}</div>@endif
    @if($plans->isEmpty())
        <div class="plan-notice">Aucune formule n’est publiée pour le moment.</div>
    @else
        <div class="pack-grid pack-grid-model" id="packs">
            @foreach($plans as $plan)
                <x-subscription-package-card :plan="$plan" :fx-quote="$fxQuote" :highlight="$plan->slug === 'premium'">
                    @if(in_array($plan->id, $orderPlanIds, true) && auth()->check() && auth()->user()->role === 'INVESTOR' && auth()->user()->hasVerifiedEmail())
                        <form method="POST" action="{{ route('subscriptions.orders.store', $plan) }}">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                            <button class="button" type="submit">Choisir ce pack</button>
                        </form>
                    @elseif(in_array($plan->id, $orderPlanIds, true) && auth()->check() && auth()->user()->role === 'INVESTOR' && !auth()->user()->hasVerifiedEmail())
                        <form method="POST" action="{{ route('subscriptions.orders.prepare', $plan) }}">
                            @csrf
                            <button class="button" type="submit">Choisir ce pack</button>
                            <p class="field-help">La confirmation de votre adresse e-mail sera demandée avant le récapitulatif.</p>
                        </form>
                    @elseif(in_array($plan->id, $orderPlanIds, true) && !auth()->check())
                        <a class="button" href="{{ route('register', ['pack' => $plan->slug]) }}">Choisir ce pack</a>
                        <p class="field-help">Déjà inscrit ? <a href="{{ route('login') }}">Connectez-vous</a>, puis choisissez cette formule.</p>
                    @else
                        <a class="button" href="{{ route('login') }}">Choisir ce pack</a>
                    @endif
                </x-subscription-package-card>
            @endforeach
        </div>
    @endif
    <p class="projection-disclaimer"><strong>Risque de marché :</strong> Les gains prévisionnels sont des objectifs indicatifs et ne constituent pas une garantie de rendement. Le trading comporte des risques de perte partielle ou totale du capital investi.</p>
    <div class="payment-preview"><div><span class="eyebrow">PAIEMENT</span>
        <p>Après le choix du pack, le récapitulatif présente les moyens de paiement réellement configurés. @if($checkoutPlanIds)Un prestataire sécurisé est disponible pour les offres compatibles; l’abonnement ne s’active qu’après confirmation serveur de la transaction.@else Aucun prestataire de paiement compatible n’est configuré. Le parcours de commande reste disponible, mais aucun paiement ni accès payant ne sera activé avant une confirmation réelle.@endif</p>
    </div><a class="text-link" href="{{ route('home') }}#contact">Contacter l’équipe <span aria-hidden="true">→</span></a></div>
</section>
@endsection
