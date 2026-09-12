<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Poin 10: master pendukung tm2old.mst_* yang belum punya rumah di tm2mst.
//
// Yang PALING bernilai di sini adalah keluarga Master Card -- data rekayasa
// produk (bahan, proses, pisau per produk) yang selama ini cuma hidup di
// ScriptCase. Rantainya: mst_item -> mst_product_mc (jembatan item<->card,
// 11.428 baris) -> mst_card -> mst_card_process/_rawmat/_diecut.
//
// Lookup kecil (status/tipe/kategori, ~25 tabel isi 2-20 baris) TIDAK dibikin
// 25 tabel sendiri-sendiri -- digabung ke satu ref_legacy_lookup dgn kolom
// lookup_group, sesuai konvensi `ref_` (lookup generik lintas-domain) di
// CLAUDE.md Sec.3. Menghindari 25 tabel kurus yang bikin skema berisik.
return new class extends Migration
{
    public function up(): void
    {
        // --- Master Card (rekayasa produk) ---
        Schema::connection('tm2mst')->create('prod_card_legacy', function (Blueprint $table) {
            $table->id('id_card_legacy');
            $table->unsignedBigInteger('legacy_mastercard_id')->unique();
            $table->string('card_name', 100)->nullable();
            $table->string('card_description', 100)->nullable();
            $table->string('form_joint', 20)->nullable();
            $table->string('type_joint', 20)->nullable();
            $table->string('design_filename', 250)->nullable();
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
        });

        Schema::connection('tm2mst')->create('prod_card_process_legacy', function (Blueprint $table) {
            $table->id('id_card_process_legacy');
            $table->foreignId('card_legacy_id')->constrained('prod_card_legacy', 'id_card_legacy')->cascadeOnDelete();
            $table->unsignedInteger('process_seq')->nullable();
            $table->string('process_name', 50)->nullable();
            $table->string('machine_name', 50)->nullable();
            $table->string('diecut_code', 50)->nullable();
            $table->decimal('pack_contains', 12, 2)->nullable();
            $table->string('pack_uom', 10)->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable()->comment('LOGICAL -> tm2mst.sup_supplier (proses subkon)');
            $table->text('process_notes')->nullable();
            $table->timestamps();
        });

        Schema::connection('tm2mst')->create('prod_card_rawmat_legacy', function (Blueprint $table) {
            $table->id('id_card_rawmat_legacy');
            $table->foreignId('card_legacy_id')->constrained('prod_card_legacy', 'id_card_legacy')->cascadeOnDelete();
            $table->unsignedBigInteger('material_id')->nullable()->comment('LOGICAL -> tm2mst.mat_raw');
            $table->string('legacy_rawmat_code', 100)->nullable();
            $table->unsignedTinyInteger('rawmat_grade')->nullable();
            $table->string('rawmat_category', 20)->nullable();
            $table->unsignedTinyInteger('ink_seq')->nullable();
            $table->decimal('plano_contains', 12, 2)->nullable();
            $table->decimal('image_contains', 12, 2)->nullable();
            $table->decimal('cutsize_length', 12, 2)->nullable();
            $table->decimal('cutsize_width', 12, 2)->nullable();
            $table->timestamps();
            $table->index('material_id');
        });

        Schema::connection('tm2mst')->create('prod_card_diecut_legacy', function (Blueprint $table) {
            $table->id('id_card_diecut_legacy');
            $table->foreignId('card_legacy_id')->constrained('prod_card_legacy', 'id_card_legacy')->cascadeOnDelete();
            $table->string('diecut_code', 50)->nullable();
            $table->timestamps();
            $table->index('diecut_code');
        });

        // --- Jembatan item <-> Master Card ---
        Schema::connection('tm2mst')->create('prod_product_component_legacy', function (Blueprint $table) {
            $table->id('id_component_legacy');
            $table->unsignedBigInteger('legacy_detail_product_id')->unique();
            $table->unsignedBigInteger('product_id')->nullable()->comment('LOGICAL -> tm2mst.prod_product');
            $table->unsignedBigInteger('material_id')->nullable()->comment('LOGICAL -> tm2mst.mat_raw (item bisa jatuh ke salah satu)');
            $table->string('legacy_item_code', 100)->nullable();
            $table->unsignedBigInteger('card_legacy_id')->nullable()->comment('-> prod_card_legacy.id_card_legacy');
            $table->string('component_type', 50)->nullable();
            $table->boolean('stat_active')->default(true);
            $table->unsignedTinyInteger('total_rawmat')->nullable();
            $table->timestamps();
            $table->index('product_id');
            $table->index('card_legacy_id');
        });

        // --- Pisau / dieline ---
        Schema::connection('tm2mst')->create('prod_diecut_legacy', function (Blueprint $table) {
            $table->id('id_diecut_legacy');
            $table->string('diecut_code', 50)->unique();
            $table->string('description', 100)->nullable();
            $table->decimal('diecut_length', 12, 3)->nullable();
            $table->decimal('diecut_width', 12, 3)->nullable();
            $table->decimal('product_length', 12, 3)->nullable();
            $table->decimal('product_width', 12, 3)->nullable();
            $table->decimal('product_height', 12, 3)->nullable();
            $table->decimal('product_diameter', 12, 3)->nullable();
            $table->decimal('product_circumference', 12, 3)->nullable();
            $table->decimal('product_area', 12, 3)->nullable();
            $table->string('attachment', 250)->nullable();
            $table->timestamps();
        });

        // --- Lookup kecil terkonsolidasi ---
        Schema::connection('tm2mst')->create('ref_legacy_lookup', function (Blueprint $table) {
            $table->id('id_lookup');
            $table->string('lookup_group', 50)->comment('Nama tabel asal di tm2old, mis. mst_co_status');
            $table->string('code', 100);
            $table->string('description', 255)->nullable();
            $table->json('extra')->nullable()->comment('Kolom sisa dari sumber yang tidak masuk code/description');
            $table->timestamps();
            $table->unique(['lookup_group', 'code']);
        });
    }

    public function down(): void
    {
        foreach (['prod_card_process_legacy', 'prod_card_rawmat_legacy', 'prod_card_diecut_legacy', 'prod_product_component_legacy', 'prod_card_legacy', 'prod_diecut_legacy', 'ref_legacy_lookup'] as $t) {
            Schema::connection('tm2mst')->dropIfExists($t);
        }
    }
};
