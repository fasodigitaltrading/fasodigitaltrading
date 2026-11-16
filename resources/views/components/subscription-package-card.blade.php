@props(['plan', 'fxQuote' => null, 'highlight' => false])

@php
    $xofAmount = $fxQuote?->amountXof((string) $plan->price);
    $rateUsable = $fxQuote?->canBeUsedForCheckout() ?? false;
@endphp

<article id="plan-{{ $plan->slug }}" class="pack-card subscription-model-card {{ $highlight ? 'subscription-model-card-highlight' : '' }}">
    <div class="subscription-model-heading">
        <h3>{{ $plan->name }}</h3>
        <span class="pack-period">30 JOURS</span>
    </div>

    <dl class="subscription-model-metrics">
        <div><dt>Capital minimum requis</dt><dd>{{ number_format((float) $plan->capital_required, 2, ',', ' ') }} USD</dd></div>
        <div class="subscription-model-price"><dt>Abonnement mensuel</dt><dd>{{ number_format((float) $plan->price, 2, ',', ' ') }} USD</dd></div>
        <div class="subscription-model-conversion">
            <dt>Équivalence FCFA · taux fixe</dt>
            @if($xofAmount !== null)
                <dd>≈ {{ number_format($xofAmount, 0, ',', ' ') }} XOF</dd>
                <small>{{ $rateUsable ? 'Taux appliqué : 1 USD = 600 XOF' : 'Taux fixe indisponible' }}</small>
            @else
                <dd>Indisponible</dd>
                <small>La conversion sera affichée dès que le taux fixe sera valide.</small>
            @endif
        </div>
        <div><dt>Gain journalier prévisionnel</dt><dd>{{ number_format((float) $plan->estimated_daily_gain_usd, 2, ',', ' ') }} USD · indicatif</dd></div>
        <div><dt>Durée de l’abonnement</dt><dd>{{ $plan->duration_days }} jours</dd></div>
    </dl>

    @if(is_array($plan->features) && count($plan->features))
        <div class="subscription-model-benefits">
            <h4>Avantages et fonctionnalités</h4>
            <ul>
                @foreach($plan->features as $feature)
                    <li>{{ $feature }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="subscription-model-action">{{ $slot }}</div>
</article>
