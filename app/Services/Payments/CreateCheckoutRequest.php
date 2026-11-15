<?php

namespace App\Services\Payments;

final readonly class CreateCheckoutRequest
{
    public function __construct(
        public string $idempotencyKey,
        public string $amount,
        public string $currency,
        public string $successUrl,
        public string $cancelUrl,
        public array $metadata,
        public string $customerName = '',
        public string $customerEmail = '',
        public ?string $customerPhone = null,
    ) {
    }
}
