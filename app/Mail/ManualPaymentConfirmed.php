<?php

namespace App\Mail;

use App\Models\ManualPaymentSubmission;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class ManualPaymentConfirmed extends Mailable
{
    public function __construct(public ManualPaymentSubmission $submission)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Paiement confirmé · FASO DIGITAL TRADING');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.manual-payment-confirmed');
    }
}
