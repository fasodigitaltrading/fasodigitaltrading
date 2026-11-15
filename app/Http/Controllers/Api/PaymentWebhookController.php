<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidPaymentWebhookSignature;
use App\Exceptions\PaymentProviderUnavailable;
use App\Http\Controllers\Controller;
use App\Services\PaymentProviderFactory;
use App\Services\PaymentWebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function __invoke(
        string $providerName,
        Request $request,
        PaymentProviderFactory $providers,
        PaymentWebhookProcessor $processor,
    ): JsonResponse {
        try {
            $provider = $providers->make($providerName);
        } catch (PaymentProviderUnavailable) {
            return response()->json(['message' => 'Payment provider is not configured.'], 404);
        }

        try {
            $event = $provider->verifyWebhook($request->getContent(), $request->headers->all());
        } catch (InvalidPaymentWebhookSignature) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        if (!$event) {
            return response()->json(['received' => true, 'ignored' => true]);
        }

        $canonicalEvent = json_encode([
            'provider' => $provider->name(),
            'event_id' => $event->eventId,
            'event_type' => $event->eventType,
            'transaction_id' => $event->transactionId,
            'status' => $event->status,
            'amount' => $event->amount,
            'currency' => strtoupper($event->currency),
        ], JSON_THROW_ON_ERROR);
        $result = $processor->process($provider->name(), $event, $canonicalEvent);
        if (($result['conflict'] ?? false) === true) {
            return response()->json(['message' => 'Conflicting duplicate event.'], 409);
        }
        if (($result['rejected'] ?? false) === true) {
            return response()->json(['message' => 'Payment details do not match.'], 422);
        }

        return response()->json(['received' => true, ...$result]);
    }
}
