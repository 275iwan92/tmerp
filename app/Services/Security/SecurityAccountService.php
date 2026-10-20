<?php

/*
 * REMARK
 * Tujuan       : Akun login (sec_user) dari layar v2 security-account-form: akun baru (login, nama, e-mail, password awal, grup), ubah nama / e-mail / grup, aktif / nonaktif, reset password dan lihat ulang password bawaan. Reset dan aktif / nonaktif DIPAKAI ULANG dari EmployeeAccountService (aturan satu sumber: tab Account Master Employee memakai kelas yang sama), layanan ini hanya menambah yang khas Security: buat akun, ubah profil dan grup.
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::security.index tab users (diarsipkan di tmerp_unused/security_index_v1_2026-10-20.blade.php): login unik maks 190, nama wajib maks 255, e-mail opsional valid, password awal min 8, tepat satu grup, e-mail berubah = verifikasi lama gugur, admin tak bisa menonaktifkan akunnya sendiri. Baru (Jo 2026-10-20, mode full speed): grup harus aktif, login hanya huruf kecil / angka / titik / garis bawah / minus, semua di dalam transaksi tm2sys.
 * Input        : akun (opsional), array isian, user admin yang bertindak.
 * Output       : tm2sys.sec_user dan sec_user_group berubah; ValidationException bila aturan dilanggar; kartu login (login, nama, password) untuk reset dan lihat ulang.
 * Dipakai oleh : routes/v2_setup.php (v2.setup.account.save), layar security-account-form, tes SecurityAccountServiceTest.
 * Status       : Aktif (T8 Setup, 2026-10-20).
 */

namespace App\Services\Security;

use App\Models\Sys\SecUser;
use App\Services\Hrm\EmployeeAccountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SecurityAccountService
{
    /** Simpan akun: baru bila $u null, selain itu ubah nama / e-mail / grup (login dan password tidak berubah di sini). */
    public function save(?SecUser $u, array $in, SecUser $actor): SecUser
    {
        $new = ! $u?->exists;
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'group_id' => ['required', 'integer', Rule::exists('tm2sys.sec_group', 'id_group')->where('activate', 1)],
        ];
        if ($new) {
            $rules += [
                'login' => ['required', 'string', 'max:190', 'regex:/^[a-z0-9._-]+$/', Rule::unique('tm2sys.sec_user', 'login')],
                'password' => ['required', 'string', 'min:8', 'max:100'],
            ];
        }
        $d = Validator::make($in, $rules, ['group_id.exists' => 'Choose an active group.', 'login.regex' => 'The login may only contain lowercase letters, digits, dot, underscore and minus.'],
            ['name' => 'Name', 'email' => 'E-mail', 'group_id' => 'Group', 'login' => 'Login', 'password' => 'Initial password'])->validate();
        $email = ($d['email'] ?? null) ?: null;

        return DB::connection('tm2sys')->transaction(function () use ($u, $new, $d, $email, $actor) {
            if ($new) {
                $u = SecUser::create(['login' => $d['login'], 'name' => $d['name'], 'email' => $email, 'pswd' => Hash::make($d['password']), 'active' => true, 'activate' => true, 'created_by' => $actor->login]);
                $u->groups()->attach($d['group_id']);

                return $u;
            }
            // Alamat berubah = verifikasi lama gugur (pengguna memverifikasi ulang di profilnya).
            $reverify = strcasecmp((string) $u->email, (string) $email) !== 0 ? ['email_verified' => false, 'email_verified_at' => null] : [];
            $u->update(['name' => $d['name'], 'email' => $email, 'updated_by' => $actor->login] + $reverify);
            $u->groups()->sync([(int) $d['group_id']]);

            return $u;
        });
    }

    /** @return array{login: string, name: string, password: string} kartu login (password baru, tampil sekali) */
    public function resetPassword(SecUser $u): array
    {
        return app(EmployeeAccountService::class)->resetPassword($u);
    }

    public function setActive(SecUser $u, bool $active, SecUser $actor): void
    {
        app(EmployeeAccountService::class)->setActive($u, $active, $actor);
    }

    /** Lihat ulang password bawaan (BUKAN reset): spot-check akun hasil provisioning massal; null bila tidak ada. */
    public function reveal(SecUser $u): ?array
    {
        if (! $u->default_password) {
            throw ValidationException::withMessages(['account' => 'This account has no stored default password.']);
        }

        return ['login' => $u->login, 'name' => $u->name, 'password' => $u->default_password];
    }

    /** Riwayat tab History: informasi akun (tidak ada log perubahan terpisah di tabel ini). */
    public function history(SecUser $u): array
    {
        $rows = [['at' => $u->created_at?->toDateTimeString(), 'by' => (string) ($u->created_by ?? ''), 'action' => 'Created', 'note' => '']];
        if ($u->pswd_last_updated) {
            $rows[] = ['at' => $u->pswd_last_updated->toDateTimeString(), 'by' => '', 'action' => 'Password changed', 'note' => $u->must_change_password ? 'Must change at next login' : ''];
        }

        return array_reverse($rows);
    }
}
