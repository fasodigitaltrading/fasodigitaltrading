<?php

namespace App\Services;

use App\Services\Payments\CheckoutSession;
use App\Services\Payments\CreateCheckoutRequest;
use App\Services\Payments\VerifiedPaymentEvent;

interface PaymentProviderInterface
{
    public function name(): string;

    public function isConfigured(): bool;

    public function supportsCheckout(string $currency, string $amount): bool;

    public function createCheckout(CreateCheckoutRequest $request): CheckoutSession;

    /** Verify the provider signature and return a normalized event, or null for a valid irrelevant event. */
    public function verifyWebhook(string $payload, array $headers): ?VerifiedPaymentEvent;
}
