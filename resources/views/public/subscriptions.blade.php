@extends('layouts.app')

@section('content')
<section class="page-hero"><div class="container"><span class="eyebrow">FASO DIGITAL TRADING · OFFRES</span><h1>Choisissez votre formule</h1><p>Les prix de référence sont en USD. L’équivalence XOF affichée suit le taux fixe de 1 USD = 600 XOF; le paiement s’effectue en USDT BEP20.</p>
    <p class="fx-rate-meta">Taux fixe appliqué aux commandes : 1 USD = 600 XOF.</p>
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
        <p>Après le choix du pack, le récapitulatif affiche le montant en USDT BEP20 et l’adresse de réception. @if($manualPaymentReady)L’administrateur vérifie la transaction avant toute activation.@else Le parcours de paiement est suspendu jusqu’à la configuration sécurisée du serveur.@endif</p>
    </div><a class="text-link" href="{{ route('home') }}#contact">Contacter l’équipe <span aria-hidden="true">→</span></a></div>
</section>
@endsection
