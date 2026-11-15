<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewRegistrationAdminAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $registeredUser)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nouvelle inscription FASO DIGITAL TRADING');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.new-registration-admin-alert');
    }
}
