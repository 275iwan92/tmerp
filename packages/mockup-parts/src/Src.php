<?php

namespace MockupParts;

// Tooltip "nanti tersambung ke tabel apa, database mana" untuk isian/kolom di komponen paket ini.
// Aplikasi pemakai memasang penerjemahnya (mockup.test: App\Support\Src::of, dicek ke Data Dictionary);
// tanpa penerjemah, rujukan "db.tabel.kolom" tampil apa adanya.
final class Src
{
    public static ?\Closure $resolver = null;

    public static function of(string ...$refs): string
    {
        return self::$resolver ? (self::$resolver)(...$refs) : implode(' + ', $refs);
    }
}
