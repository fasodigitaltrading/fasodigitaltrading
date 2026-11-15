<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SupportReplyReceived extends Notification
{
    use Queueable;

    public function __construct(private readonly SupportTicket $ticket)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Nouvelle réponse du support',
            'subject' => $this->ticket->subject,
            'url' => route('support.show', $this->ticket),
        ];
    }
}
