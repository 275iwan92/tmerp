<?php

namespace App\Services\Auth;

use App\Models\Sys\SecUser;
use App\Models\Sys\SysNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/*
 * REMARK
 * Tujuan       : Aturan pendaftaran akun mandiri: validasi isian, login diturunkan dari email (sufiks angka bila bentrok), akun dibuat NONAKTIF menunggu persetujuan administrator, dan setiap administrator diberi notifikasi berisi tautan ke daftar akun.
 * Sumber hulu  : tm2sys.sec_user (reg_type, id_number, active/activate = 0) dan sys_notification.
 * Input        : isian form x-dl::register-box (reg_type, name, email, whatsapp, id_no, password, password_confirmation).
 * Output       : SecUser baru (menunggu approval) + notifikasi ke administrator (link = layar v2 security-accounts).
 * Dipakai oleh : App\Http\Controllers\Auth\RegisterController.
 * Status       : Aktif. Dipindahkan apa adanya dari halaman v1 pages::auth.register (diarsipkan 2026-10-21, T10 tahap 6a). Verifikasi email dan OTP WhatsApp tetap demo (format saja, dijalankan x-dl::register-box di browser), seperti v1.
 */
class RegistrationService
{
    public static function rules(string $type): array
    {
        return [
            'reg_type' => ['required', 'in:customer,supplier,internal'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:tm2sys.sec_user,email'],
            'whatsapp' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/'],
            'id_no' => $type === 'internal' ? ['required', 'string', 'max:30'] : ['required', 'digits:16'],
        ];
    }

    // Username = bagian email sebelum @ (huruf kecil, hanya a-z 0-9 . _ -); keunikan diselesaikan di sini dengan sufiks angka.
    public function loginFor(string $email): string
    {
        $base = preg_replace('/[^a-z0-9._-]/', '', Str::of($email)->before('@')->lower()->toString());
        $base = $base !== '' ? $base : 'user';
        $login = $base;
        $suffix = 1;
        while (SecUser::where('login', $login)->exists()) {
            $login = $base.($suffix++);
        }

        return $login;
    }

    public function register(array $d): SecUser
    {
        $user = SecUser::create([
            'login' => $this->loginFor($d['email']),
            'pswd' => Hash::make($d['password']),
            'name' => $d['name'],
            'email' => $d['email'],
            'phone' => $d['whatsapp'],
            'active' => false,
            'activate' => false,
            'reg_type' => $d['reg_type'],
            'id_number' => $d['id_no'],
        ]);

        // TIER_4 6.2: beri tahu admin ada pendaftaran baru yang menunggu approval.
        SecUser::where('is_admin', true)->pluck('login')->each(
            fn (string $adminLogin) => SysNotification::create([
                'user_id' => $adminLogin,
                'title' => 'Pendaftaran baru menunggu approval',
                'message' => "{$user->name} ({$user->login}) mendaftar sebagai {$user->reg_type}.",
                'type' => 'info',
                'link' => route('v2.screen', 'security-accounts'),
            ])
        );

        return $user;
    }
}
