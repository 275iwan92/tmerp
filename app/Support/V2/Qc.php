<?php

namespace App\Support\V2;

use Illuminate\Support\Facades\Auth;

// Bilah QC cepat di layar v2 (user 2026-10-07): logika di paket DevLib (Devlib\Qc), di sini hanya tabel sys_qc_review (tm2sys; di
// mockup.test = ddtsys) dan siapa yang boleh memutuskan. Aktif hanya bila UAT_QC=true (.env.mockup); di live mati.
final class Qc
{
    public static function enabled(): bool
    {
        return (bool) config('uat.qc');
    }

    public static function store(): \Devlib\Qc
    {
        return new \Devlib\Qc('tm2sys', 'sys_qc_review');
    }

    // Tahap QC diputuskan akun developer (Administrator / grup DEVELOPER); tahap UAT menyusul.
    public static function bar(string $slug, string $name): array
    {
        return self::store()->bar($slug, $name, route('v2.qc.review'), csrf_token(), (bool) AppMenu::user(Auth::user())['developer']);
    }
}
