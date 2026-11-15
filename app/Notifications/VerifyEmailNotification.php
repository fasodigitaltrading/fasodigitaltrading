<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );

        return (new MailMessage())
            ->subject('Confirmez votre adresse e-mail — FASO DIGITAL TRADING')
            ->greeting('Bonjour '.$notifiable->first_name.',')
            ->line('Confirmez l’adresse e-mail associée à votre espace FASO DIGITAL TRADING pour terminer la création de votre compte.')
            ->action('Confirmer mon adresse e-mail', $url)
            ->line('Ce lien sécurisé expire dans '.(int) config('auth.verification.expire', 60).' minutes. Si vous n’avez pas créé ce compte, ignorez ce message.')
            ->salutation('L’équipe FASO DIGITAL TRADING');
    }
}
