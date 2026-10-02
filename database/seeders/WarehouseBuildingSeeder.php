<?php

namespace Database\Seeders;

use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Master\WhZone;
use App\Services\Inventory\BinGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Gedung gudang NYATA A10 / A5 / A6 dengan denah PERKIRAAN (user 2026-10-02: "gedung itu pasti terpisah, A5, A6, A10";
// "untuk saat ini buat saja menurut estimasi kamu ... nanti di-adjust oleh officer"; "denahnya kamu buat dulu tapi nanti
// bisa diubah oleh user atau officer warehouse"): gedung, tinggi tiap lantai, area + tinggi tumpuk, dan bin. TANPA stok,
// palet, atau transfer -- itu data nyata yang diisi petugas. Dipakai untuk tmerp.test (tm2*):
//   php artisan db:seed --class=WarehouseBuildingSeeder --force
// Aman diulang: gedung yang kodenya sudah ada dilewati (di mockup.test gedungnya sudah dibuat UatWarehouseSeeder).
// Ukuran dan letak diubah user lewat Inventory > Warehouse > Setup > Buildings / Storage Areas / Bins dan Edit Layout.
class WarehouseBuildingSeeder extends Seeder
{
    private const BY = 'warehouse_setup';

    public function run(): void
    {
        foreach (UatWarehouseSeeder::BUILDINGS as $code => [$name, $length, $width, $floors, $heights]) {
            if (WhWarehouse::query()->where('warehouse_code', $code)->exists()) {
                $this->command?->line("Warehouse {$code} already exists: skipped.");

                continue;
            }
            DB::connection('tm2mst')->transaction(function () use ($code, $name, $length, $width, $floors, $heights) {
                $wh = WhWarehouse::create(['warehouse_code' => $code, 'warehouse_name' => $name, 'warehouse_type' => 'MIX', 'activate' => true, 'length_m' => $length, 'width_m' => $width,
                    'floor_count' => $floors, 'shape' => 'RECT', 'height_f1_m' => $heights[0] ?? null, 'height_f2_m' => $heights[1] ?? null, 'height_f3_m' => $heights[2] ?? null,
                    'created_by' => self::BY, 'updated_by' => self::BY]);
                foreach (UatWarehouseSeeder::ZONES[$code] as $i => [$floor, $zc, $zn, $type, $mode, $class, $x, $y, $w, $h, $gen, $kg, $pallet]) {
                    $zone = WhZone::create(['warehouse_id' => $wh->id_warehouse, 'floor_no' => $floor, 'zone_code' => $zc, 'zone_name' => $zn, 'zone_type' => $type, 'storage_mode' => $mode,
                        'stock_class' => $class, 'map_x' => $x, 'map_y' => $y, 'map_w' => $w, 'map_h' => $h,
                        'stack_height_m' => UatWarehouseSeeder::STACK[$zc] ?? UatWarehouseSeeder::STACK_DEFAULT, 'sort_order' => ($i + 1) * 10, 'activate' => true,
                        'created_at' => now(), 'updated_at' => now(), 'created_by' => self::BY, 'updated_by' => self::BY]);
                    BinGenerator::generate($zone->setRelation('warehouse', $wh), ['rows_from' => 1, 'rows_to' => $gen[0] ?? 1, 'cols_from' => 1, 'cols_to' => $gen[1] ?? 1,
                        'levels' => $gen[2] ?? 1, 'capacity_kg' => $kg, 'capacity_pallet' => $pallet], self::BY);
                }
                $this->command?->line("Warehouse {$code}: ".WhZone::query()->where('warehouse_id', $wh->id_warehouse)->count().' areas, '
                    .WhLoc::query()->where('warehouse_id', $wh->id_warehouse)->count().' bins.');
            });
        }
    }
}
