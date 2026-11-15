<?php

namespace App\Services\Payments;

use App\Exceptions\InvalidPaymentWebhookSignature;
use App\Services\PaymentProviderInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/** Live PayDunya redirection integration. Sandbox endpoints are intentionally unsupported. */
class PayDunyaProvider implements PaymentProviderInterface
{
    private const API = 'https://app.paydunya.com/api/v1';

    public function name(): string
    {
        return 'paydunya';
    }

    public function isConfigured(): bool
    {
        return $this->readinessIssues() === [];
    }

    /** Return safe, non-secret reasons why the live checkout cannot start. */
    public function readinessIssues(): array
    {
        $appUrl = parse_url((string) config('app.url'));
        $bindingKey = (string) config('license.binding_key');
        $appKey = (string) config('app.key');
        $issues = [];

        foreach (['master_key' => 'Master Key', 'private_key' => 'Private Key', 'token' => 'Token'] as $key => $label) {
            if (trim((string) config('payments.paydunya.'.$key)) === '') {
                $issues[] = 'clé marchande PayDunya '.$label.' absente';
            }
        }

        if (strtoupper((string) config('payments.paydunya.currency')) !== 'XOF') {
            $issues[] = 'la devise PayDunya doit être XOF';
        }
        if (trim((string) config('legal.operator_name')) === '') {
            $issues[] = 'nom légal de l’exploitant absent';
        }
        if (trim((string) config('legal.operator_address')) === '') {
            $issues[] = 'adresse légale de l’exploitant absente';
        }
        if (filter_var((string) config('legal.operator_email'), FILTER_VALIDATE_EMAIL) === false) {
            $issues[] = 'adresse e-mail légale de l’exploitant invalide ou absente';
        }
        if (!(bool) config('legal.disclosures_approved')) {
            $issues[] = 'mentions légales et informations de risque non approuvées';
        }
        if (strlen($bindingKey) < 32 || hash_equals($bindingKey, $appKey)) {
            $issues[] = 'clé de liaison des licences absente, trop courte ou réutilisant APP_KEY';
        }
        if (strlen($appKey) < 20) {
            $issues[] = 'APP_KEY invalide';
        }

        $host = strtolower((string) ($appUrl['host'] ?? ''));
        $scheme = strtolower((string) ($appUrl['scheme'] ?? ''));
        if ($scheme !== 'https' || !$this->isExternallyRoutableHost($host)) {
            $issues[] = 'APP_URL doit être une adresse HTTPS publique joignable par PayDunya';
        }

        if (config('app.env') === 'production' && (
            config('session.secure') !== true
            || config('session.encrypt') !== true
            || config('app.debug') !== false
        )) {
            $issues[] = 'sessions sécurisées et APP_DEBUG=false requis en production';
        }

        return $issues;
    }

    public function supportsCheckout(string $currency, string $amount): bool
    {
        return $this->isConfigured()
            && strtoupper($currency) === 'XOF'
            && preg_match('/^[0-9]{1,10}(?:\.00)?$/', $amount) === 1
            && (int) explode('.', $amount, 2)[0] > 0;
    }

    public function createCheckout(CreateCheckoutRequest $request): CheckoutSession
    {
        if (!$this->supportsCheckout($request->currency, $request->amount)) {
            throw new RuntimeException('PayDunya is not configured for this currency or amount.');
        }

        $callbackUrl = rtrim((string) config('app.url'), '/').route('payments.webhook', ['providerName' => $this->name()], false);
        $returnUrl = $this->assertHttpsUrl($request->successUrl);
        $cancelUrl = $this->assertHttpsUrl($request->cancelUrl);

        try {
            $response = $this->request()->post(self::API.'/checkout-invoice/create', [
                'invoice' => [
                    'total_amount' => (int) $request->amount,
                    'description' => 'FASO DIGITAL TRADING — abonnement',
                    'customer' => array_filter([
                        'name' => $request->customerName,
                        'email' => $request->customerEmail,
                        'phone' => $request->customerPhone,
                    ], static fn ($value) => $value !== null && $value !== ''),
                ],
                'store' => [
                    'name' => (string) config('legal.operator_name'),
                    'website_url' => rtrim((string) config('app.url'), '/'),
                ],
                'custom_data' => [
                    ...$request->metadata,
                    'idempotency_key' => $request->idempotencyKey,
                ],
                'actions' => [
                    'cancel_url' => $cancelUrl,
                    'return_url' => $returnUrl,
                    'callback_url' => $callbackUrl,
                ],
            ])->throw();
        } catch (Throwable $exception) {
            throw new RuntimeException('The live PayDunya checkout request failed.', previous: $exception);
        }

        $data = $response->json();
        $token = is_array($data) ? ($data['token'] ?? null) : null;
        $redirectUrl = is_array($data) ? ($data['response_text'] ?? null) : null;

        if (!is_array($data) || (string) ($data['response_code'] ?? '') !== '00'
            || !is_string($token) || !$this->validInvoiceToken($token)
            || !is_string($redirectUrl) || !$this->validPayDunyaRedirect($redirectUrl)) {
            throw new RuntimeException('PayDunya returned an invalid live checkout response.');
        }

        return new CheckoutSession($token, $redirectUrl);
    }

    public function verifyWebhook(string $payload, array $headers): ?VerifiedPaymentEvent
    {
        unset($headers);
        $form = [];
        parse_str($payload, $form);
        $incoming = $form['data'] ?? null;
        if (is_string($incoming)) {
            $incoming = json_decode($incoming, true);
        }

        $masterKey = (string) config('payments.paydunya.master_key');
        if (!$this->isConfigured() || !is_array($incoming)
            || !is_string($incoming['hash'] ?? null)
            || !hash_equals(hash('sha512', $masterKey), $incoming['hash'])) {
            throw new InvalidPaymentWebhookSignature('Invalid PayDunya callback signature.');
        }

        $token = $incoming['invoice']['token'] ?? $incoming['token'] ?? null;
        if (!is_string($token) || !$this->validInvoiceToken($token)) {
            throw new InvalidPaymentWebhookSignature('PayDunya callback does not contain a valid invoice token.');
        }

        $confirmed = $this->confirmLiveInvoice($token);
        $confirmedInvoice = $confirmed['invoice'] ?? null;
        if (!is_array($confirmedInvoice) || ($confirmedInvoice['token'] ?? null) !== $token
            || !is_string($confirmed['hash'] ?? null)
            || !hash_equals(hash('sha512', $masterKey), $confirmed['hash'])
            || strtolower((string) ($confirmed['mode'] ?? '')) !== 'live') {
            throw new RuntimeException('PayDunya live invoice confirmation could not be verified.');
        }

        $status = strtolower((string) ($confirmed['status'] ?? ''));
        $normalized = match ($status) {
            'completed' => 'PAID',
            'cancelled' => 'CANCELLED',
            'failed' => 'FAILED',
            'pending' => null,
            default => throw new RuntimeException('PayDunya returned an unknown invoice status.'),
        };

        if ($normalized === null) {
            return null;
        }

        $amount = $confirmedInvoice['total_amount'] ?? null;
        if (!is_string($amount) && !is_int($amount) && !is_float($amount)) {
            throw new RuntimeException('PayDunya confirmation is missing its settled amount.');
        }
        $amount = (string) $amount;
        if (preg_match('/^[0-9]{1,10}(?:\.0{1,2})?$/', $amount) !== 1) {
            throw new RuntimeException('PayDunya returned an invalid settled amount.');
        }
        $wholeAmount = ltrim(explode('.', $amount, 2)[0], '0');
        $wholeAmount = $wholeAmount === '' ? '0' : $wholeAmount;

        return new VerifiedPaymentEvent(
            eventId: hash('sha256', $token.'|'.$normalized),
            eventType: 'invoice.'.$status,
            transactionId: $token,
            status: $normalized,
            amount: $wholeAmount.'.00',
            currency: 'XOF',
        );
    }

    private function confirmLiveInvoice(string $token): array
    {
        try {
            $response = $this->request()->get(self::API.'/checkout-invoice/confirm/'.rawurlencode($token))->throw();
        } catch (Throwable $exception) {
            throw new RuntimeException('The live PayDunya confirmation request failed.', previous: $exception);
        }

        $data = $response->json();
        if (!is_array($data) || (string) ($data['response_code'] ?? '') !== '00') {
            throw new RuntimeException('PayDunya could not confirm the live invoice.');
        }

        return $data;
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()->withHeaders([
            'PAYDUNYA-MASTER-KEY' => (string) config('payments.paydunya.master_key'),
            'PAYDUNYA-PRIVATE-KEY' => (string) config('payments.paydunya.private_key'),
            'PAYDUNYA-TOKEN' => (string) config('payments.paydunya.token'),
        ])->timeout(max(3, min(60, (int) config('payments.paydunya.timeout_seconds', 15))));
    }

    private function validInvoiceToken(string $token): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{1,191}$/', $token) === 1;
    }

    private function validPayDunyaRedirect(string $url): bool
    {
        $parts = parse_url($url);

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === 'app.paydunya.com';
    }

    private function assertHttpsUrl(string $url): string
    {
        $parts = parse_url($url);
        if (filter_var($url, FILTER_VALIDATE_URL) === false
            || !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || !$this->isExternallyRoutableHost(strtolower((string) ($parts['host'] ?? '')))) {
            throw new RuntimeException('PayDunya return and cancellation URLs must use a public HTTPS host.');
        }

        return $url;
    }

    private function isExternallyRoutableHost(string $host): bool
    {
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        // Reserved test domains are allowed only for isolated automated tests.
        if (config('app.env') === 'testing' && (str_ends_with($host, '.test') || str_ends_with($host, '.example'))) {
            return true;
        }

        foreach (['.local', '.internal', '.lan', '.home', '.home.arpa', '.test', '.example', '.invalid'] as $reservedSuffix) {
            if (str_ends_with($host, $reservedSuffix)) {
                return false;
            }
        }

        return filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }
}
