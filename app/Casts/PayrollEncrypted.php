<?php

namespace App\Casts;

use App\Models\Sys\SysConstant;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

// docs/payroll_encryption_decision.md §2-3 -- AES-256-GCM via openssl native
// (BUKAN Laravel Crypt/encrypted cast, itu selalu pakai APP_KEY dari .env;
// keputusan user: kunci hidup di DB/sys_constant, bukan file config). Kunci
// mentah (passphrase VERBA_SANCTA) di-derive sha256 jadi 32 byte sebelum
// dipakai sbg key AES-256. IV+tag+ciphertext digabung 1 kolom base64, IV
// random per nilai (pola standar GCM, tidak butuh kolom IV terpisah).
class PayrollEncrypted implements CastsAttributes
{
    private const CIPHER = 'aes-256-gcm';

    private const IV_LENGTH = 12;

    private const TAG_LENGTH = 16;

    public function get($model, string $key, $value, array $attributes): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = base64_decode($value, true);
        if ($decoded === false || strlen($decoded) <= self::IV_LENGTH + self::TAG_LENGTH) {
            return null;
        }

        $iv = substr($decoded, 0, self::IV_LENGTH);
        $tag = substr($decoded, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($decoded, self::IV_LENGTH + self::TAG_LENGTH);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag);

        return $plaintext === false ? null : (float) $plaintext;
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';
        $ciphertext = openssl_encrypt((string) $value, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag);

        return base64_encode($iv.$tag.$ciphertext);
    }

    private function key(): string
    {
        $passphrase = SysConstant::text('VERBA_SANCTA');

        if (! $passphrase) {
            throw new \RuntimeException('Kunci enkripsi payroll VERBA_SANCTA tidak ditemukan di tm2sys.sys_constant.');
        }

        return hash('sha256', $passphrase, true);
    }
}
