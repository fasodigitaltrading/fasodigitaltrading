<?php

namespace App\Services;

use App\Exceptions\PaymentProviderUnavailable;

class PaymentProviderFactory
{
    public function make(string $name): PaymentProviderInterface
    {
        $class = config("payments.providers.{$name}");
        if (!is_string($class) || !class_exists($class)) {
            throw new PaymentProviderUnavailable("Payment provider [{$name}] is not configured.");
        }

        $provider = app($class);
        if (!$provider instanceof PaymentProviderInterface || $provider->name() !== $name || !$provider->isConfigured()) {
            throw new PaymentProviderUnavailable("Payment provider [{$name}] has an invalid implementation.");
        }

        return $provider;
    }
}
