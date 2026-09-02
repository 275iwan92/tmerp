<?php

namespace App\Support\Branding;

// 7 aksen mockup docs/mockups/mockup_dashboardcolor.html -> hex, dipakai
// AdminPanelProvider (default statis) DAN filament/partials/theme-matrix.blade.php
// (override per-user di render hook, lihat catatan panic() di
// AdminPanelProvider soal kenapa personalisasi TIDAK BOLEH di panel()).
// 1 sumber, jangan duplikat listnya di 2 tempat.
class DashboardAccent
{
    private const HEX = [
        'blue' => '#2f6fed',
        'green' => '#059669',
        'red' => '#dc2626',
        'purple' => '#8b5cf6',
        'amber' => '#f59e0b',
        'cyan' => '#06b6d4',
        'mono' => '#64748b',
    ];

    public static function hex(string $key): string
    {
        return self::HEX[$key] ?? self::HEX['blue'];
    }
}
