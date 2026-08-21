<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// INV_TIER_3.md §8.1 mengasumsikan tabel `m_wh_loc` (location_id/location_code/
// location_name/location_type) dan m_warehouse.warehouse_type berisi
// RM|SFG|FG|WASTE|TRANSIT -- ditulis di channel lain, tidak tahu Master
// Warehouse yang baru dibangun sesi ini (m_wh_location, row/col/stack/bin_code,
// warehouse_type RAW|FG|MIX|SCRAP). Puluhan VIEW di Tier 3 hardcode nama
// `m_wh_loc`/`location_id` -- diselaraskan ke situ (bukan sebaliknya) karena
// permukaan pemakaian Inventory jauh lebih besar & baru dibangun (5 baris
// dummy saja, aman di-rename).
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2mst');

        if ($db->hasTable('m_wh_location') && ! $db->hasTable('m_wh_loc')) {
            $db->rename('m_wh_location', 'm_wh_loc');
        }

        $db->table('m_wh_loc', function (Blueprint $table) use ($db) {
            if ($db->hasColumn('m_wh_loc', 'id_wh_location')) {
                $table->renameColumn('id_wh_location', 'location_id');
            }
            if ($db->hasColumn('m_wh_loc', 'bin_code')) {
                $table->renameColumn('bin_code', 'location_code');
            }

            if (! $db->hasColumn('m_wh_loc', 'location_name')) {
                $table->string('location_name', 100)->nullable()->after('location_code');
            }
            if (! $db->hasColumn('m_wh_loc', 'location_type')) {
                $table->string('location_type', 20)->nullable()->comment('STORAGE|WASTE -- kategori tampilan, terpisah dari is_usable')->after('location_name');
            }
            if (! $db->hasColumn('m_wh_loc', 'is_usable')) {
                $table->boolean('is_usable')->default(true)
                    ->comment('INV-02: 1 = stok boleh dipakai produksi/dijual, 0 = waste/karantina -- masuk saldo tapi tidak masuk qty_available')
                    ->after('location_type');
            }
        });

        // warehouse_type: RAW->RM, SCRAP->WASTE (istilah Inventory), tambah SFG/TRANSIT.
        // Enum diperluas dulu (superset) sebelum data lama diremap -- MySQL strict mode
        // menolak MODIFY langsung ke set final selama masih ada baris berisi nilai lama.
        $db->getConnection()->statement("ALTER TABLE m_warehouse MODIFY COLUMN warehouse_type ENUM('RAW','RM','SFG','FG','MIX','SCRAP','WASTE','TRANSIT') NOT NULL DEFAULT 'RAW'");
        $db->getConnection()->table('m_warehouse')->where('warehouse_type', 'RAW')->update(['warehouse_type' => 'RM']);
        $db->getConnection()->table('m_warehouse')->where('warehouse_type', 'SCRAP')->update(['warehouse_type' => 'WASTE']);
        $db->getConnection()->statement("ALTER TABLE m_warehouse MODIFY COLUMN warehouse_type ENUM('RM','SFG','FG','MIX','WASTE','TRANSIT') NOT NULL DEFAULT 'RM'");

        // is_usable=0 utk lokasi di gudang WASTE yang sudah ada (kalau ada).
        $wasteWarehouseIds = $db->getConnection()->table('m_warehouse')->where('warehouse_type', 'WASTE')->pluck('id_warehouse');
        if ($wasteWarehouseIds->isNotEmpty()) {
            $db->getConnection()->table('m_wh_loc')->whereIn('warehouse_id', $wasteWarehouseIds)->update(['is_usable' => false, 'location_type' => 'WASTE']);
        }
    }

    public function down(): void
    {
        $db = Schema::connection('tm2mst');

        $db->table('m_wh_loc', function (Blueprint $table) use ($db) {
            if ($db->hasColumn('m_wh_loc', 'is_usable')) {
                $table->dropColumn('is_usable');
            }
            if ($db->hasColumn('m_wh_loc', 'location_type')) {
                $table->dropColumn('location_type');
            }
            if ($db->hasColumn('m_wh_loc', 'location_name')) {
                $table->dropColumn('location_name');
            }
            $table->renameColumn('location_code', 'bin_code');
            $table->renameColumn('location_id', 'id_wh_location');
        });

        $db->rename('m_wh_loc', 'm_wh_location');

        $db->getConnection()->table('m_warehouse')->where('warehouse_type', 'RM')->update(['warehouse_type' => 'RAW']);
        $db->getConnection()->table('m_warehouse')->where('warehouse_type', 'WASTE')->update(['warehouse_type' => 'SCRAP']);
        $db->getConnection()->statement("ALTER TABLE m_warehouse MODIFY COLUMN warehouse_type ENUM('RAW','FG','MIX','SCRAP') NOT NULL DEFAULT 'RAW'");
    }
};
