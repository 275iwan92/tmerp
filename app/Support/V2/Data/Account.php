<?php

namespace App\Support\V2\Data;

use App\Models\Sys\SecUser;
use App\Models\Sys\SysNotification;
use App\Services\Security\PasswordResetService;
use Devlib\Locale;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

// Menu akun Layer Menu v2 (laci Notifikasi, modal Edit profil & Ubah password) -> database. Aturannya (dulu panel menu lama, diarsipkan T10 tahap 6c):
// profil = sec_user name / email / phone, password lewat PasswordResetService,
// notifikasi = sys_notifications milik user + siaran (user_id NULL).
final class Account
{
    private const TONES = ['error' => 'danger', 'danger' => 'danger', 'warning' => 'warn', 'warn' => 'warn', 'success' => 'success'];

    // Isi prop account x-dl::layer-shell.
    public static function shell(SecUser $u): array
    {
        return [
            'csrf' => csrf_token(),
            'profile' => ['url' => route('v2.account.profile'), 'phone' => (string) $u->phone, 'role' => (string) $u->jobTitle()],
            'password' => ['url' => route('v2.account.password')],
            'notifications' => ['read' => route('v2.account.notifications.read'), 'items' => self::notifications($u)],
        ];
    }

    public static function notifications(SecUser $u): array
    {
        return SysNotification::forUser($u->login)->orderByDesc('created_at')->orderByDesc('id')->limit(30)->get()
            ->map(fn (SysNotification $n) => [
                'id' => $n->id, 'title' => (string) $n->title, 'tone' => self::TONES[strtolower((string) $n->type)] ?? 'info', 'read' => (bool) $n->is_read,
                'sub' => trim(($n->message ? $n->message.' · ' : '').(Locale::dt($n->created_at) ?? ''), ' ·'), 'url' => self::localUrl($n->link),
            ])->all();
    }

    // id = satu notifikasi milik user; null = semua. Kembalian = jumlah yang masih belum dibaca.
    public static function markRead(SecUser $u, ?int $id): int
    {
        $mine = fn () => SysNotification::forUser($u->login)->where('is_read', false);
        ($id ? $mine()->whereKey($id) : $mine())->update(['is_read' => true]);

        return $mine()->count();
    }

    // Kembalian = pesan salah pertama, null = tersimpan. E-mail berubah -> verifikasi diulang (sama dengan Setup > Account).
    public static function saveProfile(SecUser $u, array $in): ?string
    {
        $v = Validator::make($in, ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:64']],
            [], ['name' => t('Full name'), 'email' => t('E-mail'), 'phone' => t('Phone')]);
        if ($v->fails()) {
            return $v->errors()->first();
        }
        $d = $v->validated();
        $reverify = strcasecmp((string) $u->email, $d['email']) !== 0 ? ['email_verified' => false, 'email_verified_at' => null] : [];
        $u->update(['name' => $d['name'], 'email' => $d['email'], 'phone' => $d['phone'] ?? null, 'updated_by' => $u->login] + $reverify);

        return null;
    }

    public static function changePassword(SecUser $u, array $in): ?string
    {
        if (! Hash::check((string) ($in['old_password'] ?? ''), (string) $u->pswd)) {
            return t('The current password is wrong.');
        }
        $v = Validator::make($in, ['new_password' => ['required', 'string', 'min:8', 'confirmed']], [], ['new_password' => t('New password')]);
        if ($v->fails()) {
            return $v->errors()->first();
        }
        try {
            app(PasswordResetService::class)->changeByUser($u, (string) $in['new_password']);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return null;
    }

    // Tautan notifikasi hanya dibuka bila menuju situs ini sendiri.
    private static function localUrl(?string $link): ?string
    {
        if (! $link) {
            return null;
        }
        if (str_starts_with($link, '/') && ! str_starts_with($link, '//')) {
            return $link;
        }

        return str_starts_with($link, rtrim(url('/'), '/').'/') ? $link : null;
    }
}
