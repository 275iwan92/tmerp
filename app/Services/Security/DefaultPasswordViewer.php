<?php

/*
 * REMARK
 * Tujuan       : Layar administrator Setup > Security > Default Passwords (SEMENTARA, hanya tahap UAT): daftar semua pengguna dengan password bawaan dan Reset to Default per baris. Kelas ini memegang gerbang (siapa boleh), data baris, dan jejak audit.
 * Sumber hulu  : config/uat.php kunci password_viewers (env UAT_PASSWORD_VIEWERS, bawaan johiwan; kosong = tak seorang pun), tm2sys.sec_user (login, name, birth_date, phone, default_password), SecurityAccountService::resetToDefault (aturan reset DIPAKAI ULANG), saluran log password_audit (config/logging.php).
 * Input        : user yang login, kata kunci kolom sec_user; id pengguna sasaran.
 * Output       : bool gerbang, baris layar (tanpa password), password per id (hanya lewat reveal, tercatat), baris audit di storage/logs/password-audit-*.log (siapa, kapan, akun mana, IP; TIDAK PERNAH password).
 * Dipakai oleh : routes/v2_setup.php (v2.setup.default-passwords.*), layar default-passwords, PayrollAccess::menuAllowed (menu hanya tampil untuk login itu), tes DefaultPasswordViewerTest.
 * Status       : Aktif, SEMENTARA. Jo 2026-10-24: untuk UAT, hanya login johiwan. Password bawaan disimpan POLOS sampai UAT selesai (keputusan Jo 2026-10-22).
 *                SETELAH UAT SELESAI: kembalikan default_password ke terenkripsi (rollback migration 2026_10_22_000300 + accessor SecUser), kosongkan UAT_PASSWORD_VIEWERS, lalu hapus layar ini (kelas ini, layar default-passwords, rute v2.setup.default-passwords.*, baris menu SET_DEFAULT_PW).
 */

namespace App\Services\Security;

use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\Log;

final class DefaultPasswordViewer
{
    public const MENU_CODE = 'SET_DEFAULT_PW';

    public const SLUG = 'default-passwords';

    /** Gerbang murni: $list = "johiwan,nisa" (tanpa beda huruf besar/kecil); kosong = tak seorang pun. */
    public static function permits(?string $login, ?string $list): bool
    {
        $login = strtolower(trim((string) $login));
        $allowed = array_filter(array_map(fn ($v) => strtolower(trim($v)), explode(',', (string) $list)));

        return $login !== '' && in_array($login, $allowed, true);
    }

    public static function allows(?SecUser $user): bool
    {
        return $user !== null && (bool) $user->activate && self::permits($user->login, config('uat.password_viewers'));
    }

    /** Baris layar. Password TIDAK ikut (has_default saja); password diambil lewat reveal() supaya tiap tampil tercatat. */
    public function rows(): array
    {
        return SecUser::query()->orderBy('login')->get()->map(fn (SecUser $u) => [
            'id' => $u->id_user, 'login' => $u->login, 'name' => $u->name, 'birth_date' => $u->birth_date, 'phone' => $u->phone,
            'has_default' => (bool) $u->default_password, 'active' => (bool) $u->activate,
        ])->all();
    }

    /** @param  list<int>  $ids @return array<int, string> id => password bawaan (hanya akun yang punya) */
    public function reveal(array $ids, SecUser $actor): array
    {
        $out = [];
        foreach (SecUser::query()->whereIn('id_user', $ids)->get() as $u) {
            if ($u->default_password) {
                $out[$u->id_user] = $u->default_password;
                $this->audit('reveal', $actor, $u);
            }
        }

        return $out;
    }

    /** @return array{login: string, name: string, password: string} kartu login (tampil sekali) */
    public function reset(SecUser $target, SecUser $actor): array
    {
        $card = app(SecurityAccountService::class)->resetToDefault($target);
        $this->audit('reset', $actor, $target);

        return $card;
    }

    public function audit(string $event, SecUser $actor, ?SecUser $target = null): void
    {
        Log::channel('password_audit')->info('default-passwords.'.$event, ['actor' => $actor->login, 'target' => $target?->login, 'ip' => request()->ip()]);
    }
}
