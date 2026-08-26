<?php

namespace App\Services\Security;

use App\Models\Sys\SecUser;
use App\Models\Sys\SysSetting;

// security_defaultpassword_spec.md §5 — notifikasi = toggle, bukan
// dependency. Jalur manual (grid admin) SELALU jalan tanpa syarat apapun.
class PasswordNotifier
{
    public function send(SecUser $user, string $plainPassword): void
    {
        $channel = optional(SysSetting::find('notify_channel'))->setting_value ?? 'manual';

        match ($channel) {
            'email' => $this->sendEmail($user, $plainPassword),
            'whatsapp' => $this->sendWhatsapp($user, $plainPassword), // TODO fase depan: no-op sekarang
            default => null, // 'manual' -> tidak kirim apa pun; admin lihat di grid
        };
    }

    protected function sendEmail(SecUser $user, string $plain): void
    {
        if (empty($user->email)) {
            return;
        }
        // Mail::to($user->email)->send(new DefaultPasswordMail($user, $plain));
        // SMTP eksternal (Gmail) via config MAIL_* di .env. Bila server Docker blok SMTP
        // -> exception ditangkap di PasswordResetService; reset tetap sukses.
    }

    protected function sendWhatsapp(SecUser $user, string $plain): void
    {
        // TODO fase depan (gateway WA). Sekarang no-op agar toggle bisa dipilih tanpa error.
    }
}
