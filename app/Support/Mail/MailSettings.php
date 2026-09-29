<?php

namespace App\Support\Mail;

use App\Models\Sys\SecUser;
use App\Models\Sys\SysMailSetting;
use App\Models\Sys\SysNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

// Setting e-mail dari layar (tm2sys.sys_mail_setting) dipasang ke config mail tepat sebelum kirim; mailer=env -> .env apa adanya.
// Wajib-verifikasi & From=user default MATI sampai admin menyalakannya di Setting E-mail (sesudah SMTP nyata terpasang),
// supaya UAT dengan MAIL_MAILER=log tidak terkunci.
final class MailSettings
{
    public static function current(): SysMailSetting
    {
        $conn = (new SysMailSetting)->getConnectionName();

        return Schema::connection($conn)->hasTable('sys_mail_setting')
            ? (SysMailSetting::query()->first() ?? new SysMailSetting(['mailer' => 'env', 'from_user' => false, 'require_verified' => false]))
            : new SysMailSetting(['mailer' => 'env', 'from_user' => false, 'require_verified' => false]);
    }

    public static function apply(): SysMailSetting
    {
        $s = self::current();

        if ($s->mailer === 'smtp') {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $s->host,
                'mail.mailers.smtp.port' => $s->port ?: 587,
                'mail.mailers.smtp.scheme' => $s->encryption === 'ssl' ? 'smtps' : null,
                'mail.mailers.smtp.username' => $s->username,
                'mail.mailers.smtp.password' => $s->password,
            ]);
        } elseif ($s->mailer === 'log') {
            config(['mail.default' => 'log']);
        }
        if ($s->from_address) {
            config(['mail.from.address' => $s->from_address, 'mail.from.name' => $s->from_name ?: config('mail.from.name')]);
        }
        Mail::purge();

        return $s;
    }

    public static function verified(?SecUser $u): bool
    {
        return $u && $u->email && $u->email_verified && filter_var($u->email, FILTER_VALIDATE_EMAIL);
    }

    // Kirim gagal (ditolak server / alamat salah / kotak penuh yang langsung ditolak) -> peringatan ke user pengirim.
    public static function notifyFailure(string $login, string $to, string $error, ?string $link = null): void
    {
        SysNotification::create(['user_id' => $login, 'title' => 'E-mail tidak terkirim', 'type' => 'error', 'link' => $link, 'is_read' => false,
            'message' => mb_substr("Ke {$to}: {$error}", 0, 500)]);
    }
}
