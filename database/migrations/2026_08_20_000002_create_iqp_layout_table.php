<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// iqp_layout — dicadangkan di prefixlist.md tapi belum pernah dibuat.
// Ditulis Imposition Engine (docs/mockups/inquiry_imposition.html): 1 layout
// per iqp_part, pola "engine suggests, officer confirms" spt iqp_costing.
// plano_size_id = FK logis ke tm2mst.m_plano_size (lintas-DB, TIDAK fisik —
// CLAUDE.md aturan 2: FK MySQL tak bisa lintas-DB).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->create('iqp_layout', function (Blueprint $table) {
            $table->id('id_layout');
            $table->unsignedBigInteger('part_id')->unique()->comment('FK -> iqp_part, 1 layout per part');
            $table->decimal('flat_w_mm', 10, 2);
            $table->decimal('flat_h_mm', 10, 2);
            $table->decimal('gap_mm', 6, 2)->default(3);
            $table->unsignedInteger('plano_size_id')->nullable()->comment('FK logis -> tm2mst.m_plano_size, terakhir dipilih');
            $table->decimal('plano_w_mm', 10, 2);
            $table->decimal('plano_h_mm', 10, 2);
            $table->decimal('gripper_mm', 6, 2)->default(12);
            $table->decimal('margin_mm', 6, 2)->default(8);
            $table->string('method', 20)->comment('TEGAK|PUTAR|CAMPUR');
            $table->unsignedSmallInteger('up_count')->default(0);
            $table->decimal('efficiency_pct', 5, 2)->default(0);
            $table->decimal('waste_pct', 5, 2)->default(0);
            $table->json('layout_detail')->nullable()->comment('Grid/mix detail utk render ulang visual');
            $table->boolean('is_confirmed')->default(false);
            $table->string('confirmed_by', 100)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->foreign('part_id')->references('id_part')->on('iqp_part')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->dropIfExists('iqp_layout');
    }
};
