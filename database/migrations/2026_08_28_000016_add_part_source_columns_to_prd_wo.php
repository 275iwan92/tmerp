<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/production_scheduling_wo_generation_delta.md §4.1 -- WO Generation
// dari Plan Delivery. part_code = identitas part manusiawi (iqp_part.part_code,
// BUKAN FK prod_part yang tak pernah diisi -- lihat delta doc §3).
// source_delivery_id/source_iqp_part_id = LOGICAL FK tm2sdi (lintas-DB,
// TIDAK constrained fisik, CLAUDE.md §2).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2prd')->table('prd_wo', function (Blueprint $table) {
            $table->string('part_code', 50)->nullable()->after('parts_id');
            $table->unsignedBigInteger('source_delivery_id')->nullable()->after('source_forecast_id')
                ->comment('LOGICAL FK tm2sdi.co_order_delivery -- cegah generate WO dobel dari baris Plan Delivery yang sama');
            $table->unsignedBigInteger('source_iqp_part_id')->nullable()->after('source_delivery_id')
                ->comment('LOGICAL FK tm2sdi.iqp_part -- jembatan balik ke BOM/BOP snapshot asli');
            $table->index('source_delivery_id', 'idx_prd_wo_source_delivery');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2prd')->table('prd_wo', function (Blueprint $table) {
            $table->dropIndex('idx_prd_wo_source_delivery');
            $table->dropColumn(['part_code', 'source_delivery_id', 'source_iqp_part_id']);
        });
    }
};
