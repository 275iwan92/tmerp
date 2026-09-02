<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/inquiry_product_costing/productdevelopment_tier1.md §4.2a poin 1 --
// TEMUAN saat eksekusi (2026-09-02): `tm2mst.prod_part_process` TERNYATA
// SUDAH ADA (bukan hasil migration Laravel manapun -- bagian dari DDL awal
// TIER_3A yang di-import langsung ke MySQL sebelum banyak migration
// berikutnya ditulis, makanya tidak kelihatan di grep migrations/*.php).
// Skemanya malah LEBIH LENGKAP dari rencana §4.2a (branch_code/step_seq/
// flow_type/layer_ref/qty_factor, persis simetris dgn iqp_part_process yg
// snapshot-nya di tm2sdi) -- jadi dipakai APA ADANYA, BUKAN dibuat tabel
// baru yang lebih sederhana (hindari duplikasi skema, CLAUDE.md §2). Satu
// gap nyata: machine_id belum punya FK fisik (komentar lama "FK logis
// mesin (menyusul)") -- prod_machine sudah ada sejak lama, momennya sudah
// tiba, migration ini menutup gap itu.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->table('prod_part_process', function (Blueprint $table) {
            $table->foreign('machine_id', 'fk_pproc_machine')
                ->references('machine_id')->on('prod_machine')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('prod_part_process', function (Blueprint $table) {
            $table->dropForeign('fk_pproc_machine');
        });
    }
};
