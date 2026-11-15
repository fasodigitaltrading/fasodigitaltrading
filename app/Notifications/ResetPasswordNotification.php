<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage())
            ->subject('Réinitialisation de votre mot de passe — FASO DIGITAL TRADING')
            ->greeting('Bonjour '.$notifiable->first_name.',')
            ->line('Une demande de réinitialisation du mot de passe de votre espace FASO DIGITAL TRADING a été reçue.')
            ->action('Choisir un nouveau mot de passe', $url)
            ->line('Ce lien expire dans 60 minutes. Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.')
            ->salutation('L’équipe FASO DIGITAL TRADING');
    }
}
