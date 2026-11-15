<?php

namespace App\Mail;

use App\Models\ManualPaymentSubmission;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class ManualPaymentReceivedAlert extends Mailable
{
    public function __construct(
        public ManualPaymentSubmission $submission,
        public string $otpCode,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Transaction à vérifier · FASO DIGITAL TRADING');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.manual-payment-received');
    }
}
