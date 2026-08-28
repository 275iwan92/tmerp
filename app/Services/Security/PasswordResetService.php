<?php

namespace App\Services\Security;

use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\Hash;

// security_defaultpassword_spec.md §5 — generate password readable +
// alur reset (dipakai admin manual & self-service ke depan).
class PasswordResetService
{
    // Hindari karakter ambigu (0/O, 1/l/I). Format contoh: Tmk-7fK9p2
    public function generate(): string
    {
        $pool = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $body = '';
        for ($i = 0; $i < 6; $i++) {
            $body .= $pool[random_int(0, strlen($pool) - 1)];
        }

        return 'Tmk-'.$body;
    }

    // Return plaintext baru (untuk ditampilkan ke admin / dikirim notifikasi).
    public function reset(SecUser $user): string
    {
        $plain = $this->generate();

        $user->default_password = $plain; // accessor -> Crypt::encryptString
        $user->pswd = Hash::make($plain);
        $user->must_change_password = true;
        $user->pswd_last_updated = now();
        $user->save();

        // Notifikasi = tambahan, TIDAK memblokir. Gagal kirim tetap sukses reset.
        try {
            app(PasswordNotifier::class)->send($user, $plain);
        } catch (\Throwable $e) {
            report($e); // log saja; admin tetap bisa kasih tahu manual
        }

        return $plain;
    }

    // User mengganti password sendiri (setelah dipaksa / sukarela).
    // default_password TIDAK diubah. Tolak bila sama dengan default.
    public function changeByUser(SecUser $user, string $new): void
    {
        if ($user->default_password && hash_equals($user->default_password, $new)) {
            throw new \InvalidArgumentException('Password baru tidak boleh sama dengan password default.');
        }

        $user->pswd = Hash::make($new);
        $user->must_change_password = false; // default_password TETAP
        $user->pswd_last_updated = now();
        $user->save();
    }
}
