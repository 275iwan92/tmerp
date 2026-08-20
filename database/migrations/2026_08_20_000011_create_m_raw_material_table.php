<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Adopsi legacy tm2mst.m_raw_material (item master bahan baku/jasa/sparepart,
// 5893 baris riil) -- PROC_TIER_1.md §1.1/§5.5 & PROC_TIER_3.md §7.1.
// Sumber: dump asli user (database/legacy/m_raw_material.sql, 2026-08-20).
// DEVIASI dari dump asli (structure boleh diubah - arahan user 2026-08-20):
// FK `uom_purchase/uom_stock/uom_usage -> m_uom(uom_code)` DIHAPUS -- tabel
// `m_uom` tak pernah ada di TMERP (UOM riil = `uom_unit`, kolom PK beda:
// id_uom bukan uom_code sbg PK). Kolom uom_* tetap disimpan, jadi LOGICAL
// reference ke uom_unit.uom_code (bukan constraint fisik) supaya data lama
// yang mungkin tak 100% konsisten kapitalisasi/kode tidak memblokir import.
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2mst');

        if ($db->hasTable('m_raw_material')) {
            return;
        }

        $db->create('m_raw_material', function (Blueprint $table) {
            $table->id('material_id');
            $table->string('material_code', 60)->unique();
            $table->string('material_name', 200);
            $table->enum('item_type', ['MATERIAL', 'SERVICE', 'FREE_SAMPLE'])->default('MATERIAL');
            $table->string('category1', 100)->nullable();
            $table->string('category2', 100)->nullable();
            $table->string('category3', 100)->nullable();
            $table->string('category4', 100)->nullable();
            $table->string('category5', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('brand', 100)->nullable();
            $table->string('color', 50)->nullable();
            $table->unsignedInteger('gram_weight')->nullable()->default(0);
            $table->decimal('cm_length', 12, 4)->unsigned()->nullable()->default(0);
            $table->decimal('cm_width', 12, 4)->unsigned()->nullable()->default(0);
            $table->decimal('mm_thickness', 12, 4)->unsigned()->nullable()->default(0);
            $table->string('specification', 255)->nullable();
            $table->string('uom_stock', 50)->nullable()->comment('LOGICAL -> tm2mst.uom_unit.uom_code');
            $table->string('uom_purchase', 50)->nullable()->comment('LOGICAL -> tm2mst.uom_unit.uom_code');
            $table->unsignedTinyInteger('purchasable')->default(1);
            $table->string('uom_usage', 50)->nullable()->comment('LOGICAL -> tm2mst.uom_unit.uom_code');
            $table->decimal('conversion_to_stock', 20, 6)->unsigned()->nullable()->default(1);
            $table->decimal('conversion_to_usage', 20, 6)->unsigned()->nullable();
            $table->unsignedBigInteger('suggest_supplier_id')->nullable()->comment('LOGICAL -> tm2mst.sup_supplier.id_supplier');
            $table->decimal('tol_purchase', 5, 2)->nullable()->comment('toleransi pembelian % - dipakai PROC_TIER_2 §A-4.3');
            $table->string('free_note', 255)->nullable();
            $table->decimal('min_stock', 20, 4)->unsigned()->nullable()->default(0);
            $table->decimal('max_stock', 20, 4)->unsigned()->nullable();
            $table->decimal('cur_stock', 20, 4)->nullable();
            $table->unsignedTinyInteger('is_active')->nullable()->default(1);
            $table->decimal('avg_unitprice', 30, 5)->nullable();
            $table->decimal('last_unitprice', 30, 5)->nullable();
            $table->unsignedBigInteger('def_location_id')->nullable();
            $table->timestamp('create_log')->nullable()->useCurrent();
            $table->timestamp('update_log')->nullable()->useCurrent()->useCurrentOnUpdate();
            $table->string('create_by', 255)->nullable();
            $table->string('update_by', 255)->nullable();
            $table->string('old_item_code', 255)->nullable();
            $table->string('old_item_name', 250)->nullable();

            $table->index('item_type');
            $table->index('category1');
            $table->index('is_active');
        });

        $this->importLegacyData();
    }

    private function importLegacyData(): void
    {
        $path = database_path('legacy/m_raw_material.sql');

        if (! file_exists($path)) {
            return;
        }

        $handle = fopen($path, 'r');
        $buffer = '';
        $count = 0;

        while (($line = fgets($handle)) !== false) {
            if (! str_starts_with(ltrim($line), 'INSERT INTO')) {
                continue;
            }

            $buffer .= $line;
            $count++;

            // Eksekusi per 500 baris supaya tak satu statement raksasa.
            if ($count % 500 === 0) {
                DB::connection('tm2mst')->unprepared($buffer);
                $buffer = '';
            }
        }

        if (trim($buffer) !== '') {
            DB::connection('tm2mst')->unprepared($buffer);
        }

        fclose($handle);

        $max = DB::connection('tm2mst')->table('m_raw_material')->max('material_id');

        if ($max) {
            DB::connection('tm2mst')->statement('ALTER TABLE `m_raw_material` AUTO_INCREMENT = '.((int) $max + 1));
        }
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('m_raw_material');
    }
};
