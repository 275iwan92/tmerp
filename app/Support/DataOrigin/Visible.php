<?php

namespace App\Support\DataOrigin;

use App\Services\Data\DataOriginService;
use Illuminate\Database\Query\Builder;

// Filter asal data untuk query yang TIDAK lewat model Eloquent (laporan &
// dashboard memakai query builder / view). Tujuan utama user 2026-09-18:
// "filter hide data dummy berjalan saat mau dicheck misal total penjualan
// 2022 berapa -- data dummy harus tidak ikut".
//
// Dipakai: Visible::apply($query, 'tm2sdi', 'co_order', 'o')
class Visible
{
    private static ?array $hidden = null;

    private static ?bool $hideIncomplete = null;

    public static function hidden(): array
    {
        return self::$hidden ??= app(DataOriginService::class)->hiddenOrigins();
    }

    /** Tabel ini punya kolom data_origin? */
    public static function flagged(string $db, string $table): bool
    {
        return isset(app(DataOriginService::class)->flaggedTables()["$db.$table"]);
    }

    public static function apply(Builder $query, string $db, string $table, ?string $alias = null): Builder
    {
        if (! self::flagged($db, $table)) {
            return $query;
        }

        $col = ($alias ?: $table).'.data_origin';

        if ($hidden = self::hidden()) {
            $query->whereNotIn($col, $hidden);
        }

        self::$hideIncomplete ??= app(DataOriginService::class)->hideLegacyIncomplete();

        if (self::$hideIncomplete) {
            $ready = ($alias ?: $table).'.data_ready';
            $query->where(fn ($q) => $q->where($col, '<>', 'LEGACY')->orWhere($ready, 1));
        }

        return $query;
    }
}
