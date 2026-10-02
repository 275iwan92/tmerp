<?php

namespace App\Mail;

use App\Support\Branding\Brand;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Link verifikasi e-mail user (sec_user.email_verified): user tanpa e-mail terverifikasi tidak bisa mengirim dokumen
// bila Setting E-mail mewajibkannya (backlog09 §18).
class EmailVerifyMail extends Mailable
{
    public function __construct(public string $userName, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: t('E-mail verification').' — '.Brand::companyName());
    }

    public function content(): Content
    {
        return new Content(view: 'mail.email-verify', with: ['company' => Brand::companyName()]);
    }
}
