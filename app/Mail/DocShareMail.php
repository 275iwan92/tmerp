<?php

namespace App\Mail;

use App\Models\Sys\SysDocShare;
use App\Support\Branding\Brand;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// E-mail dokumen dari tombol Email (x-dl::doc-share): pesan + tautan dokumen online (QR) dan/atau lampiran PDF hasil Print.
class DocShareMail extends Mailable
{
    public function __construct(public SysDocShare $share, public string $subjectLine, public ?string $body, public bool $withLink, public ?string $image = null, public ?array $pdf = null, public ?array $sender = null) {}

    public function envelope(): Envelope
    {
        // Sementara: From = alamat sistem (MAIL_FROM), balasan ke e-mail user pengirim dari Security. Rencana: From = e-mail user
        // yang sudah diverifikasi + notifikasi bounce ke user (docs/backlog09_platform_facility.md).
        $from = $this->sender ? new Address(config('mail.from.address'), $this->sender['name'].' via '.Brand::companyName()) : null;
        $reply = $this->sender && filter_var($this->sender['email'], FILTER_VALIDATE_EMAIL) ? [new Address($this->sender['email'], $this->sender['name'])] : [];

        return new Envelope(from: $from, replyTo: $reply, subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.doc-share', with: ['company' => Brand::companyName()]);
    }

    public function attachments(): array
    {
        return $this->pdf ? [Attachment::fromData(fn () => $this->pdf[0], $this->pdf[1])->withMime('application/pdf')] : [];
    }
}
