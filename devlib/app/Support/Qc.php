<?php

namespace App\Support;

// Keputusan QC cepat devlib.test: logikanya di paket (Devlib\Qc), di sini hanya tabel milik database devlib.
final class Qc
{
    public static function store(): \Devlib\Qc
    {
        return new \Devlib\Qc;
    }

    public static function bar(string $key, string $name, ?array $user): array
    {
        return self::store()->bar($key, $name, route('qc.review'), csrf_token(), (bool) ($user['developer'] ?? false));
    }
}
