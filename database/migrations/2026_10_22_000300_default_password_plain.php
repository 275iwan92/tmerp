<?php

/*
 * REMARK
 * Tujuan       : sec_user.default_password disimpan POLOS (string biasa) sampai UAT selesai (perintah Jo 2026-10-22: "jangan dienkripsi sampai saya bilang UAT selesai"), supaya admin bisa membacanya langsung di tabel dan mudah mereset password user.
 * Sumber hulu  : Spec lama docs/md_for_systems/security_defaultpassword_spec.md §1-4 (terenkripsi Laravel Crypt, key APP_KEY); kolomnya (TEXT) tidak berubah, hanya isinya.
 * Input        : Baris sec_user dengan default_password berbentuk ciphertext Crypt (awalan eyJpdiI6).
 * Output       : up() menulis ulang tiap ciphertext yang bisa didekripsi menjadi teks polos; yang gagal didekripsi dibiarkan dan dilaporkan di keluaran. down() mengenkripsi lagi semua nilai polos (kembali ke spec lama).
 * Dipakai oleh : App\Models\Sys\SecUser::defaultPassword() (accessor polos; tetap membaca ciphertext lama).
 * Status       : Aktif; idempoten; jalan di mockup (ddtsys) dan tmerp (tm2sys).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CIPHER_PREFIX = 'eyJpdiI6';

    public function up(): void
    {
        $failed = [];
        foreach ($this->rows() as $r) {
            if (! str_starts_with($r->default_password, self::CIPHER_PREFIX)) {
                continue;
            }
            try {
                DB::connection('tm2sys')->table('sec_user')->where('id_user', $r->id_user)->update(['default_password' => Crypt::decryptString($r->default_password)]);
            } catch (Throwable) {
                $failed[] = $r->login;
            }
        }
        if ($failed) {
            echo 'default_password tidak bisa didekripsi (dibiarkan): '.implode(', ', $failed).PHP_EOL;
        }
    }

    public function down(): void
    {
        foreach ($this->rows() as $r) {
            if (! str_starts_with($r->default_password, self::CIPHER_PREFIX)) {
                DB::connection('tm2sys')->table('sec_user')->where('id_user', $r->id_user)->update(['default_password' => Crypt::encryptString($r->default_password)]);
            }
        }
    }

    private function rows(): iterable
    {
        return DB::connection('tm2sys')->table('sec_user')->whereNotNull('default_password')->where('default_password', '!=', '')->get(['id_user', 'login', 'default_password']);
    }
};
