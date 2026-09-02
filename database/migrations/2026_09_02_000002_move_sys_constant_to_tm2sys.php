<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pindah sys_constant dari tm2mst -> tm2sys (keputusan user 2026-09-02):
// tabel ini konfigurasi sistem/aplikasi (PPN, bank, costing param, dst), bukan
// master data bisnis -- lokasinya di tm2mst sejak awal cuma kebetulan historis
// (lihat catatan di 2026_08_21_000010_create_inv_tier3_delta_and_new_tables.php
// baris ~15 yang sudah mengakui penyimpangan ini). RENAME tabel lama (bukan
// DROP) supaya ada jalan mundur kalau ada yang kelewat.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tm2sys')->hasTable('sys_constant')) {
            return;
        }

        Schema::connection('tm2sys')->create('sys_constant', function (Blueprint $table) {
            $table->id('id_constant');
            $table->string('constant_key', 50)->unique();
            $table->string('category_code', 30)->nullable();
            $table->string('constant_group', 50)->nullable();
            $table->decimal('constant_number', 20, 6)->nullable();
            $table->string('constant_text', 255)->nullable();
            $table->string('description', 255)->nullable();
            $table->boolean('activate')->default(true);
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->foreign('category_code')->references('code')->on('sys_constant_category')->nullOnDelete();
        });

        // constant_group (existing) -> category_code (baru) mapping.
        $groupToCategory = [
            'TAX' => 'FINANCE',
            'PAYMENT' => 'FINANCE',
            'COSTING' => 'PRODUCTION',
            'PROCUREMENT' => 'PROCUREMENT',
            'INV' => 'INVENTORY',
        ];

        if (Schema::connection('tm2mst')->hasTable('sys_constant')) {
            $rows = \Illuminate\Support\Facades\DB::connection('tm2mst')->table('sys_constant')->get();
            $dest = \Illuminate\Support\Facades\DB::connection('tm2sys')->table('sys_constant');

            foreach ($rows as $row) {
                $data = (array) $row;
                unset($data['id_constant']);
                $data['category_code'] = $groupToCategory[$row->constant_group] ?? null;
                $dest->insert($data);
            }

            Schema::connection('tm2mst')->rename('sys_constant', 'sys_constant_migrated_20260902');
        }
    }

    public function down(): void
    {
        if (Schema::connection('tm2mst')->hasTable('sys_constant_migrated_20260902')
            && ! Schema::connection('tm2mst')->hasTable('sys_constant')) {
            Schema::connection('tm2mst')->rename('sys_constant_migrated_20260902', 'sys_constant');
        }

        Schema::connection('tm2sys')->dropIfExists('sys_constant');
    }
};
