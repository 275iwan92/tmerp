<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// F15 Production Output Recording -- perluasan atas permintaan user: qty
// reject/waste butuh TIPE (mis. "cacat cetak", "warna meleset"), dan 1 entri
// output boleh punya BEBERAPA tipe sekaligus (bukan 1 angka polos per
// kategori). Restrukturisasi dari migration 2026_08_28_000010:
//   - qty_reject/qty_waste di prd_wo_operation_output DIHAPUS -- pindah jadi
//     baris rinci di prd_wo_operation_output_defect (qty_good header TETAP,
//     itu tidak butuh tipe).
//   - prd_scrap_type: tabel DATA (bukan enum di kode) supaya PPIC/QA bisa
//     kelola sendiri lewat layar tanpa deploy ulang -- pola sama
//     prd_scheduling_rule (Rule Library).
return new class extends Migration
{
    public function up(): void
    {
        $prd = Schema::connection('tm2prd');

        $prd->create('prd_scrap_type', function (Blueprint $table) {
            $table->id('type_id');
            $table->enum('category', ['REJECT', 'WASTE']);
            $table->string('type_code', 30);
            $table->string('type_name', 100);
            $table->boolean('activate')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('created_by', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('updated_by', 50)->nullable();
            $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
            $table->unique(['category', 'type_code'], 'uk_prd_scrap_type_cat_code');
        });

        $prd->create('prd_wo_operation_output_defect', function (Blueprint $table) {
            $table->id('line_id');
            $table->foreignId('output_id')->constrained('prd_wo_operation_output', 'output_id')->cascadeOnDelete();
            $table->foreignId('scrap_type_id')->constrained('prd_scrap_type', 'type_id');
            $table->decimal('qty', 18, 4);
            $table->timestamp('created_at')->useCurrent();
            $table->index('scrap_type_id', 'idx_prd_output_defect_type');
        });

        $prd->table('prd_wo_operation_output', function (Blueprint $table) {
            $table->dropColumn(['qty_reject', 'qty_waste']);
        });
    }

    public function down(): void
    {
        $prd = Schema::connection('tm2prd');

        $prd->table('prd_wo_operation_output', function (Blueprint $table) {
            $table->decimal('qty_reject', 18, 4)->default(0)->after('qty_good');
            $table->decimal('qty_waste', 18, 4)->default(0)->after('qty_reject');
        });

        $prd->dropIfExists('prd_wo_operation_output_defect');
        $prd->dropIfExists('prd_scrap_type');
    }
};
