<?php

namespace App\Support\Grid;

// Dipindah dari method per-Livewire-component (duplikat verbatim di co/list.blade.php
// & master/raw-material/index.blade.php) ke helper statis tunggal supaya
// resources/views/components/grid/identity-cell.blade.php (anonymous Blade component,
// scope terisolasi -- lihat griduistandard.md §10) bisa panggil langsung tanpa
// butuh akses ke $this milik Livewire component induk.
class Avatar
{
    private const PALETTE = [
        'from-[#2563eb] to-[#1d4ed8]', 'from-[#10b981] to-[#059669]', 'from-[#ef4444] to-[#dc2626]',
        'from-[#8b5cf6] to-[#6d28d9]', 'from-[#f59e0b] to-[#d97706]', 'from-[#0ea5e9] to-[#0284c7]',
    ];

    public static function initials(string $name): string
    {
        $words = array_values(array_filter(explode(' ', trim($name))));

        if (count($words) >= 2) {
            return mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($name, 0, 2));
    }

    public static function gradient(int $id): string
    {
        return self::PALETTE[$id % count(self::PALETTE)];
    }
}
