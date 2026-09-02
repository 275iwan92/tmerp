<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/inquiry_product_costing/productdevelopment_tier1.md §4.2a poin 2 --
// hasil ENGINE imposition (Tool #1/§4.3a) DISIMPAN di sini, REUSABLE lintas
// Inquiry (beda dari iqp_layout, tm2sdi, yang 1:1 nempel 1 iqp_part). 1
// group = 1 Master Card imposition, bisa menaungi >1 part (§4.2b -- gabung/
// pisah adalah keputusan ENGINE, bukan aturan tetap skema).
return new class extends Migration
{
    public function up(): void
    {
        $mst = Schema::connection('tm2mst');

        $mst->create('prod_layout_group', function (Blueprint $table) {
            $table->id('id_layout_group');
            $table->foreignId('product_id')->constrained('prod_product', 'id_product')->cascadeOnDelete();
            $table->string('mastercard_code', 50)->nullable()->comment('Placeholder MC-{id} sampai pola mnemonic final diputuskan');
            // mat_plano_size.id_plano_size = int unsigned (bukan bigint) -- unsignedInteger()
            // manual di sini, bukan foreignId(), supaya tipe kolom persis cocok utk FK.
            $table->unsignedInteger('plano_size_id')->nullable();
            $table->foreign('plano_size_id')->references('id_plano_size')->on('mat_plano_size')->nullOnDelete();
            $table->decimal('plano_w_mm', 10, 2)->nullable();
            $table->decimal('plano_h_mm', 10, 2)->nullable();
            $table->string('method', 20)->nullable()->comment('TEGAK|PUTAR|CAMPUR|INTERLOCK (§4.3a)');
            $table->unsignedInteger('up_count')->default(0);
            $table->decimal('efficiency_pct', 5, 2)->nullable();
            $table->decimal('waste_pct', 5, 2)->nullable();
            $table->json('layout_detail')->nullable()->comment('Parameter engine (flap/shift terpilih) + cells koordinat utk render preview');
            $table->string('status', 20)->default('DRAFT')->comment('DRAFT|CONFIRMED');
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirmed_by', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        $mst->create('prod_layout_group_part', function (Blueprint $table) {
            $table->id('id_layout_group_part');
            $table->foreignId('id_layout_group')->constrained('prod_layout_group', 'id_layout_group')->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('prod_part', 'id_part')->cascadeOnDelete();
            $table->unsignedInteger('qty_up_in_group')->default(1)->comment('Berapa kali part ini muncul dalam 1 plano gabungan (§4.2b)');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['id_layout_group', 'part_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('prod_layout_group_part');
        Schema::connection('tm2mst')->dropIfExists('prod_layout_group');
    }
};
