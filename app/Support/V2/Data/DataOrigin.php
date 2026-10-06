<?php

namespace App\Support\V2\Data;

use App\Services\Data\DataOriginService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Setup > Data Origin (v2, pengganti layar v1 pages::setup.data-origin). Semua logika tetap di DataOriginService;
// kelas ini hanya membaca untuk layar dan menjalankan aksi tombol. Menyembunyikan != menghapus.
class DataOrigin
{
    public const SETTINGS = [
        'show_dummy' => 'DATA_SHOW_DUMMY',
        'show_legacy' => 'DATA_SHOW_LEGACY',
        'hide_incomplete' => 'DATA_HIDE_LEGACY_INCOMPLETE',
        'new_as_dummy' => 'DATA_NEW_AS_DUMMY',
    ];

    private static function svc(): DataOriginService
    {
        return app(DataOriginService::class);
    }

    public static function settings(): array
    {
        $hidden = self::svc()->hiddenOrigins();

        return ['show_dummy' => ! in_array('DUMMY', $hidden, true), 'show_legacy' => ! in_array('LEGACY', $hidden, true),
            'hide_incomplete' => self::svc()->hideLegacyIncomplete(), 'new_as_dummy' => self::svc()->newRowsAsDummy()];
    }

    public static function saveSettings(Request $r): void
    {
        foreach (self::SETTINGS as $field => $key) {
            DB::connection('tm2sys')->table('sys_constant')->where('constant_key', $key)
                ->update(['constant_number' => $r->boolean($field) ? 1 : 0, 'updated_by' => auth()->user()->login, 'updated_at' => now()]);
        }
    }

    /** @return array{0: array<string, array>, 1: array{LIVE: int, DUMMY: int, LEGACY: int}} jumlah per "db.table" + totalnya */
    public static function counts(): array
    {
        $counts = self::svc()->counts();
        $totals = ['LIVE' => 0, 'DUMMY' => 0, 'LEGACY' => 0];
        foreach ($counts as $c) {
            foreach ($totals as $k => $v) {
                $totals[$k] += $c[$k];
            }
        }

        return [$counts, $totals];
    }

    // Tab Tables: hanya tabel yang memuat DUMMY / LEGACY, terbanyak dulu.
    public static function markedTables(array $counts, string $db = ''): array
    {
        return collect($counts)
            ->filter(fn ($c, $k) => ($c['DUMMY'] || $c['LEGACY']) && ($db === '' || str_starts_with($k, $db.'.')))
            ->sortByDesc(fn ($c) => $c['DUMMY'] + $c['LEGACY'])
            ->map(fn ($c, $k) => ['db' => explode('.', $k)[0], 'table' => explode('.', $k)[1]] + $c)
            ->values()->all();
    }

    public static function readiness(): array
    {
        $labels = collect(self::svc()->requirements())->groupBy(fn ($q) => $q->db_name.'.'.$q->table_name)->map(fn ($g) => $g->pluck('label')->implode(', '));

        return collect(self::svc()->readiness())->map(fn ($r, $k) => ['table' => $k, 'required' => $labels[$k] ?? ''] + $r)->values()->all();
    }

    public static function recompute(): int
    {
        return array_sum(array_column(self::svc()->computeReady(false), 'incomplete'));
    }

    public static function promote(?string $only): int
    {
        return array_sum(self::svc()->promoteReadyLegacy(false, $only ?: null));
    }

    public static function classify(): int
    {
        return array_sum(array_map(fn ($c) => $c['DUMMY'] + $c['LEGACY'], self::svc()->classify(false)));
    }

    public static function snapshot(): int
    {
        return self::svc()->snapshot(auth()->user()->login);
    }

    public static function reset(bool $removeNew): array
    {
        $r = self::svc()->reset(false, $removeNew);
        Cache::forget('data_origin.tables');

        return $r;
    }
}
