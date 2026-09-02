<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// docs/inquiry_product_costing/productdevelopment_tier1.md §4.2a poin 4 --
// finishing sbg DATA TERSTRUKTUR (tabel anak), bukan kolom teks bebas
// (prod_part.finishing lama TETAP ADA, tidak dihapus/dipakai lagi di layar
// baru -- legacy, lihat catatan model ProdPart). Lookup `ref_finishing_type`
// dibuat (bukan enum hardcode) krn variasi nyata dari 5 file MC-*.xlsx
// riil cukup banyak & campuran (brand/warna spesifik spt "Hot Print Gold
// Puyeh") -- prefix ref_ = lookup generik lintas-domain (CLAUDE.md §3).
// Detail brand/warna spesifik masuk kolom `notes` per baris finishing,
// BUKAN dibuat 1 baris lookup per varian warna (supaya lookup tetap ramping).
return new class extends Migration
{
    public function up(): void
    {
        $mst = Schema::connection('tm2mst');

        $mst->create('ref_finishing_type', function (Blueprint $table) {
            $table->id('id_finishing_type');
            $table->string('finishing_name', 100);
            $table->boolean('activate')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        $mst->create('prod_part_finishing', function (Blueprint $table) {
            $table->id('id_part_finishing');
            $table->foreignId('part_id')->constrained('prod_part', 'id_part')->cascadeOnDelete();
            $table->unsignedSmallInteger('seq');
            $table->foreignId('finishing_type_id')->constrained('ref_finishing_type', 'id_finishing_type')->restrictOnDelete();
            $table->text('notes')->nullable()->comment('Detail brand/warna spesifik, mis. "Gold Puyeh"');
            $table->decimal('cost_estimate', 18, 4)->nullable()->comment('Opsional, dipakai Costing nanti (§4.2a poin 4)');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->unique(['part_id', 'seq']);
        });

        $now = now();
        $names = ['Laminating Doff', 'Laminating Glossy', 'UV Spot', 'Hot Print', 'Varnish', 'Emboss', 'Deboss', 'Foil Stamping'];
        DB::connection('tm2mst')->table('ref_finishing_type')->insert(array_map(fn ($n) => [
            'finishing_name' => $n,
            'activate' => true,
            'created_by' => 'claude_code',
            'created_at' => $now,
            'updated_at' => $now,
        ], $names));
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('prod_part_finishing');
        Schema::connection('tm2mst')->dropIfExists('ref_finishing_type');
    }
};
