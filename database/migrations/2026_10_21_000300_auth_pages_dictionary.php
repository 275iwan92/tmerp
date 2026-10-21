<?php

/*
 * REMARK
 * Tujuan       : Terjemahan Indonesia untuk halaman tamu dan paksa-ganti v2 (lupa password, ganti password, setup keamanan, pesan galat); teks Indonesia = teks halaman v1 yang digantikan.
 * Sumber hulu  : app/Http/Controllers/Auth/*, resources/views/auth/*.blade.php.
 * Input        : -
 * Output       : Baris sys_dictionary (ui, grup form) + terjemahan Indonesia; terjemahan yang sudah terisi tidak ditimpa.
 * Dipakai oleh : Halaman auth v2 (x-mp::auth.box).
 * Status       : Jalankan di ddt* dan tm2*: php artisan migrate --path=database/migrations/2026_10_21_000300_auth_pages_dictionary.php [--env=mockup].
 */

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const UI = [
        'Too many attempts. Try again later or contact the Administrator.' => 'Terlalu banyak percobaan. Coba lagi nanti atau hubungi Administrator.',
        'Account not found or the security questions are not set. Contact the Administrator.' => 'Akun tidak ditemukan atau belum mengatur pertanyaan keamanan. Hubungi Administrator.',
        'One of the answers does not match. Check again.' => 'Salah satu jawaban tidak sesuai. Periksa kembali.',
        'This account has no stored default password. Contact the Administrator.' => 'Akun ini belum punya password default tersimpan. Hubungi Administrator.',
        'The password confirmation does not match.' => 'Konfirmasi password tidak sama.',
        'Username or email' => 'Username atau email',
        'Answer the 4 questions of your account.' => 'Jawab 4 pertanyaan sesuai data akun Anda.',
        'Reset password' => 'Reset password',
        'Password reset' => 'Password dikembalikan',
        'Your password is back to the default password given by the Administrator. Sign in with it, then change it.' => 'Password Anda kembali ke password default dari Administrator. Masuk dengan password itu, lalu ganti.',
        'Back to sign in' => 'Kembali ke halaman masuk',
        'Change password' => 'Ganti password',
        'You must set a new password before continuing.' => 'Anda harus mengganti password sebelum melanjutkan.',
        'New password' => 'Password baru',
        'Min. 8 characters, a mix of letters & numbers.' => 'Min. 8 karakter, kombinasi huruf & angka.',
        'Account security setup' => 'Setup keamanan akun',
        'Fill in these 4 items once. They verify your identity if you forget your password (no email).' => 'Isi 4 data ini sekali saja — dipakai untuk verifikasi identitas kalau Anda lupa password nanti (tanpa email).',
        'Date of birth' => 'Tanggal lahir',
        "Mother's maiden name" => 'Nama ibu kandung',
        "Your mother's full name" => 'Nama lengkap ibu kandung Anda',
        'Registered phone' => 'Nomor HP terdaftar',
        'Security question' => 'Pertanyaan keamanan',
        '— choose a question —' => '— pilih pertanyaan —',
        'Your answer' => 'Jawaban Anda',
        'Save & continue' => 'Simpan & lanjutkan',
        'Sign out' => 'Keluar',
        'Sign out and finish later' => 'Keluar dan lengkapi nanti',
        'Taken from your employee record. Wrong? Change it here: HR reviews the change before it takes effect.' => 'Diambil dari data karyawan Anda. Salah? Ubah di sini — perubahan akan direview HR dulu sebelum berlaku.',
    ];

    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        foreach (self::UI as $en => $id) {
            $sys->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'group_code' => 'form', 'origin' => 'app',
                'activate' => 1, 'created_at' => now(), 'created_by' => 'migration']);
            $key = $sys->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
            if ($key && ! $sys->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                $sys->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
            }
        }
        Dictionary::flush();
    }

    public function down(): void {}
};
