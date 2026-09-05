<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Substitusi bahan di Bill of Material WO Detail (permintaan user 2026-09-05,
// didiskusikan dulu -- lihat docs/production_scheduling_wo_generation_delta.md
// §12). Pola MASTER-DETAIL: `iqp_part_material` (snapshot Inquiry, tm2sdi,
// LOGICAL FK lintas-DB) tetap jadi master yg TIDAK PERNAH ditimpa/diubah --
// baris di tabel ini adalah "detail/histori" per baris BOM, bisa banyak
// (diajukan berkali-kali/ditolak/di-undo), tapi cuma 1 yg `is_active=1` dalam
// satu waktu -- itu yg dipakai override tampilan BOM di WO Detail. Undo =
// set is_active=0 lagi (BUKAN hapus baris), balik ke bahan original tanpa
// pengajuan baru.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2prd')->create('prd_wo_material_substitution', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wo_id')->constrained('prd_wo', 'wo_id');
            $table->unsignedBigInteger('iqp_part_material_id')->comment('LOGICAL FK tm2sdi.iqp_part_material.id_part_material -- baris BOM (master) yg disubstitusi');
            $table->unsignedBigInteger('original_rawmat_id')->nullable()->comment('Snapshot audit -- bahan asli saat pengajuan dibuat');
            $table->unsignedBigInteger('substitute_rawmat_id')->comment('LOGICAL FK tm2mst.mat_raw.material_id');
            $table->string('substitute_rawmat_name', 200)->comment('Snapshot nama, jaga kalau master berubah/dihapus');
            $table->decimal('substitute_length_mm', 12, 2)->nullable()->comment('Ukuran bahan pengganti -- manual/koreksi krn mat_raw.cm_length/cm_width kosong di seluruh Master saat ini');
            $table->decimal('substitute_width_mm', 12, 2)->nullable();
            $table->unsignedInteger('computed_up_count')->nullable()->comment('Hasil ImpositionEngine::run() dgn plano = ukuran bahan pengganti');
            $table->decimal('computed_efficiency_pct', 5, 2)->nullable();
            $table->decimal('computed_waste_pct', 5, 2)->nullable();
            $table->decimal('computed_qty_output_pcs', 18, 4)->nullable()->comment('Estimasi pcs yg bisa dihasilkan dari stok bahan pengganti saat ini');
            $table->decimal('original_unit_cost', 30, 5)->nullable();
            $table->decimal('substitute_unit_cost', 30, 5)->nullable();
            $table->decimal('total_cost_delta', 30, 5)->nullable()->comment('(qty_kebutuhan_baru*substitute_unit_cost) - (qty_kebutuhan_asli*original_unit_cost) -- wajib dihitung sblm approval, keputusan user 2026-09-05');
            $table->string('reason', 255)->nullable();
            $table->enum('approval_status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->string('approved_by', 50)->nullable()->comment('Manager Operasional ke atas -- SecUser::isOpsManagerOrAbove(), sama gate subkontrak PO');
            $table->dateTime('approved_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->boolean('is_active')->default(false)->comment('Cuma 1 TRUE per iqp_part_material_id -- ini yg override tampilan BOM');
            $table->string('undone_by', 50)->nullable();
            $table->dateTime('undone_at')->nullable();
            $table->string('created_by', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('updated_by', 50)->nullable();
            $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
            $table->index(['iqp_part_material_id', 'is_active'], 'idx_wo_matsub_line_active');
            $table->index(['wo_id', 'approval_status'], 'idx_wo_matsub_wo_status');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2prd')->dropIfExists('prd_wo_material_substitution');
    }
};
