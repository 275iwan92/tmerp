<?php

declare(strict_types=1);

/* REMARK
 * Tujuan       : Management Reports > Warehouse & Inventory (v2): nilai stok RM / WIP (SFG) / barang jadi, bahan tidak bergerak > 90 hari, komposisi nilai, nilai RM per gudang dan bahan baku bernilai tertinggi.
 * Sumber hulu  : view v_rm_onhand, v_fg_onhand, v_sfg_onhand (tm2inv, dipakai juga kartu / master stok), mat_raw (tm2mst); aturan sama dengan layar v1 pages::report.warehouse.
 * Input        : tidak ada (angka berjalan).
 * Output       : angka KPI, deret donat, baris progress, baris tabel.
 * Dipakai oleh : layar v2 mgmt-warehouse.
 * Status       : Aktif (T9 Management Reports, 2026-10-20).
 */

namespace App\Support\V2\Data\Mgmt;

use App\Support\DbName;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;

final class WarehouseReport extends MgmtReport
{
    /** @return array{rm: float, fg: float, sfg: float} */
    public function stockValue(): array
    {
        $db = DB::connection('tm2inv');

        return ['rm' => (float) $db->table('v_rm_onhand')->sum('value_onhand'), 'fg' => (float) $db->table('v_fg_onhand')->sum('value_onhand'), 'sfg' => (float) $db->table('v_sfg_onhand')->sum('value_onhand')];
    }

    /** @return array{rm: int, fg: int} */
    public function skuCounts(): array
    {
        $db = DB::connection('tm2inv');

        return ['rm' => $db->table('v_rm_onhand')->where('qty_onhand', '>', 0)->distinct('material_id')->count('material_id'),
            'fg' => $db->table('v_fg_onhand')->where('qty_onhand', '>', 0)->distinct('product_id')->count('product_id')];
    }

    /** Belum bergerak > 90 hari: indikator dead stock (last_movement sudah ada di view). */
    public function staleStock(): int
    {
        return DB::connection('tm2inv')->table('v_rm_onhand')->where('qty_onhand', '>', 0)->where('last_movement', '<', now()->subDays(90))->count();
    }

    /** @return list<array<string, string>> */
    public function topRawMaterialRows(): array
    {
        return DB::connection('tm2inv')->table('v_rm_onhand as v')->join(DbName::table('tm2mst.mat_raw').' as m', 'm.material_id', '=', 'v.material_id')->where('v.qty_onhand', '>', 0)
            ->selectRaw('m.material_name, v.uom_code, SUM(v.qty_onhand) as qty, SUM(v.value_onhand) as value')->groupBy('m.material_name', 'v.uom_code')->orderByDesc('value')->limit(8)->get()
            ->map(fn ($r) => ['name' => raw($r->material_name), 'qty' => Locale::number((float) $r->qty, 2).' '.$r->uom_code, 'value' => Locale::money($r->value)])->all();
    }

    /** @return list<array{name: string, value: float}> */
    public function warehouseSplit(): array
    {
        return DB::connection('tm2inv')->table('v_rm_onhand')->where('qty_onhand', '>', 0)->selectRaw('warehouse_name, SUM(value_onhand) as value')->groupBy('warehouse_name')->orderByDesc('value')->get()
            ->map(fn ($r) => ['name' => $r->warehouse_name ?: t('(no warehouse)'), 'value' => (float) $r->value])->all();
    }
}
