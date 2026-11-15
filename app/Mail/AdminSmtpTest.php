<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminSmtpTest extends Mailable
{
    use Queueable, SerializesModels;

    public function build(): self
    {
        return $this->subject('Test SMTP FASO DIGITAL TRADING')
            ->view('emails.admin-smtp-test');
    }
}
