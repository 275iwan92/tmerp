<?php

namespace App\Services\Inventory;

use App\Models\Master\WhLoc;
use App\Models\Master\WhZone;
use Illuminate\Support\Facades\DB;

// Pembuat bin untuk satu area penyimpanan (docs/warehouselayout_tier1.md 3.2). Kode bin mengikuti cara simpan areanya:
//   FLOOR  <gudang>-<area>-L<lajur>-<slot>            mis. A10-PPR-L03-05   (pallet di lantai: lajur x slot)
//   RACK   <gudang>-<area>-R<rak>-<kolom>-<tingkat>   mis. A10-INK-R02-03-2 (rak: rak x kolom x tingkat)
//   AREA   <gudang>-<area>                            satu bin untuk seluruh area (receiving, staging, ...)
// Bin yang kodenya sudah ada dilewati, jadi generator aman dijalankan ulang untuk menambah lajur / rak.
// row_code memuat kode area (mis. "PPR-L03") karena wh_loc unik per gudang + row/col/stack; kode area paling panjang 6 huruf.
final class BinGenerator
{
    public const MAX_BINS = 2000;

    /** @param  array{rows_from?: int, rows_to?: int, cols_from?: int, cols_to?: int, levels?: int, capacity_kg?: float|null, capacity_pallet?: int|null}  $o
     *  @return list<array<string, mixed>> baris wh_loc yang akan dibuat (termasuk yang sudah ada, ditandai 'exists') */
    public static function plan(WhZone $zone, array $o): array
    {
        // Area virtual (subkon, In Transit, Loss) tidak punya tempat fisik: binnya dibuat VirtualLocationService, bukan generator ini.
        if ($zone->isVirtualType()) {
            return [];
        }
        $wh = $zone->warehouse;
        $prefix = $wh->warehouse_code.'-'.$zone->zone_code;
        $base = ['warehouse_id' => $zone->warehouse_id, 'zone_id' => $zone->id_zone, 'location_type' => $zone->zone_type, 'is_usable' => true, 'activate' => true,
            'capacity_kg' => ($o['capacity_kg'] ?? null) ?: null, 'capacity_pallet' => ($o['capacity_pallet'] ?? null) ?: null];
        $pad = fn (int $n) => str_pad((string) $n, 2, '0', STR_PAD_LEFT);
        $rows = [];
        if ($zone->storage_mode === 'AREA') {
            $rows[] = $base + ['location_code' => $prefix, 'location_name' => $zone->zone_name, 'row_code' => $zone->zone_code, 'col_code' => '0', 'stack_code' => '0'];
        } else {
            $rack = $zone->storage_mode === 'RACK';
            $levels = $rack ? max(1, min(9, (int) ($o['levels'] ?? 1))) : 1;
            for ($r = max(1, (int) ($o['rows_from'] ?? 1)); $r <= (int) ($o['rows_to'] ?? 1); $r++) {
                for ($c = max(1, (int) ($o['cols_from'] ?? 1)); $c <= (int) ($o['cols_to'] ?? 1); $c++) {
                    for ($lv = 1; $lv <= $levels; $lv++) {
                        $short = ($rack ? 'R' : 'L').$pad($r).'-'.$pad($c).($rack ? '-'.$lv : '');
                        $rows[] = $base + ['location_code' => $prefix.'-'.$short, 'location_name' => $zone->zone_code.' '.$short,
                            'row_code' => $zone->zone_code.'-'.($rack ? 'R' : 'L').$pad($r), 'col_code' => $pad($c), 'stack_code' => (string) $lv];
                        if (count($rows) > self::MAX_BINS) {
                            return $rows;
                        }
                    }
                }
            }
        }
        $existing = WhLoc::query()->whereIn('location_code', array_column($rows, 'location_code'))->pluck('location_code')->flip();

        return array_map(fn (array $r) => $r + ['exists' => $existing->has($r['location_code'])], $rows);
    }

    /** @return array{created: int, skipped: int} */
    public static function generate(WhZone $zone, array $o, string $login): array
    {
        $plan = self::plan($zone, $o);
        abort_if(count($plan) > self::MAX_BINS, 422, t('Too many bins in one run.'));
        $new = array_values(array_filter($plan, fn (array $r) => ! $r['exists']));
        DB::connection('tm2mst')->transaction(function () use ($new, $login) {
            foreach ($new as $r) {
                unset($r['exists']);
                WhLoc::create($r + ['created_by' => $login, 'updated_by' => $login]);
            }
        });

        return ['created' => count($new), 'skipped' => count($plan) - count($new)];
    }
}
