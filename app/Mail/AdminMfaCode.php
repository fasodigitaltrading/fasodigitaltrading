<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminMfaCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $code)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Code de vérification FASO DIGITAL TRADING');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-mfa-code',
            with: ['code' => $this->code],
        );
    }
}
