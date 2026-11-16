<?php

namespace App\Mail;

use App\Models\ManualPaymentSubmission;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class ManualPaymentReceivedAlert extends Mailable
{
    public function __construct(
        public ManualPaymentSubmission $submission,
        public ?string $otpCode,
        public string $proofContents,
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

    public function attachments(): array
    {
        $extension = strtolower(pathinfo((string) $this->submission->proof_path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf', // Historical proofs may be retried by an administrator.
            default => throw new \RuntimeException('Unsupported stored payment proof type.'),
        };

        return [
            Attachment::fromData(fn (): string => $this->proofContents, 'capture-transaction-'.$this->submission->id.'.'.$extension)
                ->withMime($mime),
        ];
    }
}
