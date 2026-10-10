<?php

namespace App\Support\V2;

// Posisi terakhir breadcrumb layar v2 (user 2026-10-10): identitas dokumen (no. dokumen / nama part), bukan tebakan dari
// query string. Layar memanggil Crumb::set() saat tampil; layout merender isi layar dulu baru breadcrumb. Tanpa set = tanpa label.
class Crumb
{
    private static ?string $label = null;

    public static function set(?string $label): void
    {
        self::$label = $label;
    }

    // ?new=1 = "New"; selain itu label yang diisi layar (null bila tidak ada).
    public static function current(): ?string
    {
        if (self::$label !== null && self::$label !== '') {
            return self::$label;
        }

        return request('new') ? t('New') : null;
    }
}
