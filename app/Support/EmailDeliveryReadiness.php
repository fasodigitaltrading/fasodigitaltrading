<?php

namespace App\Support;

final class EmailDeliveryReadiness
{
    public function canSend(): bool
    {
        // Automated mail assertions use Laravel's in-memory fakes. They never
        // make a network connection or claim that real delivery was tested.
        if (app()->environment('testing')) {
            return true;
        }

        return $this->hasSmtpConfiguration() && (bool) config('email.live_verified');
    }

    public function canAttemptTest(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        return $this->hasSmtpConfiguration();
    }

    public function status(): string
    {
        if (app()->environment('testing')) {
            return 'tests locaux — envoi externe désactivé';
        }

        if (!$this->hasSmtpConfiguration()) {
            return 'en attente — paramètres SMTP incomplets';
        }

        return config('email.live_verified')
            ? 'SMTP validé par l’exploitant — disponibilité à surveiller'
            : 'paramètres présents — test d’envoi réel requis';
    }

    private function hasSmtpConfiguration(): bool
    {
        $mailerName = (string) config('mail.default');
        if ($mailerName !== 'smtp') {
            return false;
        }

        $mailer = (array) config('mail.mailers.smtp', []);
        $host = strtolower(trim((string) ($mailer['host'] ?? '')));
        $from = strtolower(trim((string) config('mail.from.address')));
        $username = trim((string) ($mailer['username'] ?? ''));
        $password = trim((string) ($mailer['password'] ?? ''));

        return $host !== ''
            && !in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            && filter_var($from, FILTER_VALIDATE_EMAIL) !== false
            && !in_array($from, ['noreply@example.com', 'noreply@example.test'], true)
            && !str_ends_with($from, '.example')
            && (($username === '' && $password === '') || ($username !== '' && $password !== ''));
    }
}
