<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// docs/inquiry_product_costing/productdevelopment_tier1.md §4.2a poin 5 --
// tabel referensi rentang lidah valid struktural, pola SAMA persis
// mat_flute_allowance (App\Models\Master\MatFluteAllowance, preseden tabel
// serupa utk geometri corrugated). BUTUH DATA RIIL dari tim produksi
// Telasih Makmur sebelum dipakai kalkulasi harga sungguhan (tier1 §7 poin
// 8 -- riset ke Esko/FEFCO TIDAK ketemu 1 angka mm universal yg valid utk
// semua converter). 1 baris placeholder diseed, notes eksplisit tandai
// "BELUM DIVALIDASI" supaya tidak disangka data final. Formula
// panjang/lebar-tutup (bukan angka flat) yg diminta user §4.3a BELUM
// diimplementasi -- ini baru fallback lookup by material_type/grammage/glue_type.
return new class extends Migration
{
    public function up(): void
    {
        $mst = Schema::connection('tm2mst');

        $mst->create('mat_flap_rule', function (Blueprint $table) {
            $table->id('id_flap_rule');
            $table->string('material_type', 50)->comment('mis. DUPLEX, IVORY_AP, KRAFT -- selaras mat_grammage.paper_kind');
            $table->string('grammage_range', 30)->comment('mis. "250-350" (gsm), teks rentang -- bukan FK tunggal, 1 rule bisa cover beberapa grammage');
            $table->string('glue_type', 50)->nullable()->comment('mis. HOTMELT, WHITE_GLUE -- boleh NULL kalau rule berlaku semua jenis lem');
            $table->decimal('flap_min_mm', 8, 2);
            $table->decimal('flap_max_mm', 8, 2);
            $table->boolean('activate')->default(true);
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        DB::connection('tm2mst')->table('mat_flap_rule')->insert([
            'material_type' => 'DUPLEX',
            'grammage_range' => '250-350',
            'glue_type' => null,
            'flap_min_mm' => 20.00,
            'flap_max_mm' => 50.00,
            'activate' => true,
            'notes' => 'PLACEHOLDER -- BELUM DIVALIDASI data riil tim produksi Telasih Makmur. Dipakai fallback sampai angka lem/struktur sebenarnya diinput (tier1 §7 poin 8). flap_min_mm sinkron PRODEV_FLAP_MIN_MM sys_constant.',
            'created_by' => 'claude_code',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('mat_flap_rule');
    }
};
