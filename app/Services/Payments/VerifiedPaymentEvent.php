<?php

namespace App\Services\Payments;

final readonly class VerifiedPaymentEvent
{
    public function __construct(
        public string $eventId,
        public string $eventType,
        public string $transactionId,
        public string $status,
        public string $amount,
        public string $currency,
    ) {
    }
}
