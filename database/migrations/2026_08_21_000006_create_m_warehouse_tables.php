<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Master Gudang (docs/mockups/mockup_master_warehouse.html) -- lihat
// docs/PROC_STATE.yaml id PROC-WAREHOUSE-MASTER: pv_receipt.i_warehouse_code
// & pv_line.i_location_code masih VARCHAR bebas menunggu tabel ini.
//
// PERBEDAAN dari mockup (didokumentasikan sesuai instruksi user):
// 1. Mockup: 1 baris grid = 1 "gudang" YANG SEKALIGUS 1 bin (row/col/stack
//    nempel langsung di record gudang) -- tak bisa 1 gudang punya banyak rak.
//    DIPISAH jadi 2 tabel: m_warehouse (header gudang) 1:N m_wh_location
//    (rak/bin) -- gudang fisik nyata punya banyak row/col/stack, bukan satu.
// 2. Mockup: field SKU/Nama Barang/Jumlah Stok/UoM nempel langsung di tiap
//    baris gudang (data stok "dikarang" di JS, bukan dari ledger). TIDAK
//    dibuat -- itu bukan master data, itu posisi stok transaksional yang
//    seharusnya datang dari modul Inventory (belum dibangun). Memasukkannya
//    di sini akan jadi sumber kebenaran ganda begitu Inventory jalan.
// 3. KPI "Stok Bahan Baku/FG/Scrap" di mockup ikut didrop di layar (butuh
//    ledger stok asli) -- KPI yang dibuat cuma yang genuinely struktural
//    (jumlah gudang, jumlah lokasi/bin).
// 4. Kode gudang: mockup pakai text bebas manual (bukan auto-generate) --
//    dipertahankan manual (jumlah gudang kecil, beda dgn Item Master yang
//    catalog-nya ribuan & butuh auto-generate dari kategori).
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2mst');

        if (! $db->hasTable('m_warehouse')) {
            $db->create('m_warehouse', function (Blueprint $table) {
                $table->id('id_warehouse');
                $table->string('warehouse_code', 20)->unique();
                $table->string('warehouse_name', 100);
                $table->enum('warehouse_type', ['RAW', 'FG', 'MIX', 'SCRAP'])->default('RAW');
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->useCurrent();
                $table->string('created_by', 100)->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->string('updated_by', 100)->nullable();

                $table->index('warehouse_type');
            });
        }

        if (! $db->hasTable('m_wh_location')) {
            $db->create('m_wh_location', function (Blueprint $table) {
                $table->id('id_wh_location');
                $table->unsignedBigInteger('warehouse_id');
                $table->string('row_code', 10);
                $table->string('col_code', 10);
                $table->string('stack_code', 10);
                $table->string('bin_code', 60)->unique()->comment('generated: {warehouse_code}-{row}-{col}-{stack}');
                $table->decimal('capacity_kg', 14, 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->useCurrent();
                $table->string('created_by', 100)->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->string('updated_by', 100)->nullable();

                $table->unique(['warehouse_id', 'row_code', 'col_code', 'stack_code'], 'uq_wh_loc_coord');
                $table->foreign('warehouse_id')->references('id_warehouse')->on('m_warehouse')->cascadeOnDelete();
            });
        }

        $db->getConnection()->table('m_warehouse')->insertOrIgnore([
            ['warehouse_code' => 'WH-RAW-01', 'warehouse_name' => 'Gudang Utama Bahan Baku', 'warehouse_type' => 'RAW', 'created_by' => 'system', 'updated_by' => 'system'],
            ['warehouse_code' => 'WH-FG-01', 'warehouse_name' => 'Gudang Utama Barang Jadi', 'warehouse_type' => 'FG', 'created_by' => 'system', 'updated_by' => 'system'],
            ['warehouse_code' => 'WH-MIX-01', 'warehouse_name' => 'Gudang Transit & Buffer', 'warehouse_type' => 'MIX', 'created_by' => 'system', 'updated_by' => 'system'],
            ['warehouse_code' => 'WH-SCP-01', 'warehouse_name' => 'Area Penampungan Afval', 'warehouse_type' => 'SCRAP', 'created_by' => 'system', 'updated_by' => 'system'],
        ]);

        $whRaw = $db->getConnection()->table('m_warehouse')->where('warehouse_code', 'WH-RAW-01')->value('id_warehouse');
        $whFg = $db->getConnection()->table('m_warehouse')->where('warehouse_code', 'WH-FG-01')->value('id_warehouse');
        $whMix = $db->getConnection()->table('m_warehouse')->where('warehouse_code', 'WH-MIX-01')->value('id_warehouse');
        $whScrap = $db->getConnection()->table('m_warehouse')->where('warehouse_code', 'WH-SCP-01')->value('id_warehouse');

        $db->getConnection()->table('m_wh_location')->insertOrIgnore([
            ['warehouse_id' => $whRaw, 'row_code' => 'R01', 'col_code' => 'C01', 'stack_code' => 'S01', 'bin_code' => 'WH-RAW-01-R01-C01-S01', 'capacity_kg' => 2000, 'created_by' => 'system', 'updated_by' => 'system'],
            ['warehouse_id' => $whRaw, 'row_code' => 'R01', 'col_code' => 'C02', 'stack_code' => 'S01', 'bin_code' => 'WH-RAW-01-R01-C02-S01', 'capacity_kg' => 1500, 'created_by' => 'system', 'updated_by' => 'system'],
            ['warehouse_id' => $whFg, 'row_code' => 'R02', 'col_code' => 'C01', 'stack_code' => 'S01', 'bin_code' => 'WH-FG-01-R02-C01-S01', 'capacity_kg' => 5000, 'created_by' => 'system', 'updated_by' => 'system'],
            ['warehouse_id' => $whMix, 'row_code' => 'R03', 'col_code' => 'C04', 'stack_code' => 'S02', 'bin_code' => 'WH-MIX-01-R03-C04-S02', 'capacity_kg' => 3000, 'created_by' => 'system', 'updated_by' => 'system'],
            ['warehouse_id' => $whScrap, 'row_code' => 'R09', 'col_code' => 'C01', 'stack_code' => 'S01', 'bin_code' => 'WH-SCP-01-R09-C01-S01', 'capacity_kg' => 500, 'created_by' => 'system', 'updated_by' => 'system'],
        ]);
    }

    public function down(): void
    {
        $db = Schema::connection('tm2mst');
        $db->dropIfExists('m_wh_location');
        $db->dropIfExists('m_warehouse');
    }
};
