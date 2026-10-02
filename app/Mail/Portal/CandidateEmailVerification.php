<?php

namespace App\Mail\Portal;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CandidateEmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $candidateName, public string $verifyUrl) {}

    public function build(): self
    {
        return $this->subject(str_replace(':company', 'Telasih Makmur', t('Email Verification — :company Careers')))
            ->view('emails.portal.verify-email');
    }
}
