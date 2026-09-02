<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/inquiry_product_costing/productdevelopment_tier1.md §5.1 -- toggle
// Manual/Ambil-Data PER-PART (keputusan user, terjawab poin 4). Mode Ambil
// Data tarik parameter plano/UP dari tm2mst.prod_layout_group yang dipilih
// officer (bukan auto-derive dari iqp_part->prod_part -- link itu BELUM ADA,
// lihat docs/BACKLOG.md §6 "iqp_part -> prod_part belum ter-link"; officer
// pilih manual dari daftar layout group CONFIRMED, konsisten pola "Engine
// suggests, Officer confirms"). prod_layout_group_id LOGIS (lintas-DB
// tm2sdi->tm2mst, CLAUDE.md §2 -- FK fisik lintas-DB dilarang).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('iqp_costing_part', function (Blueprint $table) {
            $table->enum('data_source', ['MANUAL', 'PRODEV'])->default('MANUAL')->after('part_type_code');
            $table->unsignedBigInteger('prod_layout_group_id')->nullable()->after('data_source')
                ->comment('LOGIS -> tm2mst.prod_layout_group.id_layout_group, diisi saat data_source=PRODEV');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('iqp_costing_part', function (Blueprint $table) {
            $table->dropColumn(['data_source', 'prod_layout_group_id']);
        });
    }
};
