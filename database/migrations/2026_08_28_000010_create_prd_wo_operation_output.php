<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Production Output Recording -- production_scheduling_tier1.md §4 istilah baku
// & F15 (§5, §5A.6 "rolling average dari Production Output Recording, F15").
// BELUM ADA spec Tier2/Tier3/Tier4 sama sekali untuk fitur ini -- didesain
// dari nol di sini, bukan mengikuti dokumen yang sudah ada (dicatat jujur,
// beda dari migration lain sesi ini yang semua based on Tier3 DDL).
//
// Desain: log TERPISAH per entri (bukan 1 kolom qty_actual di prd_wo_operation),
// karena Tier1 eksplisit bilang "qty baik/reject/waste per shift/task" --
// 1 task bisa dicatat progresnya berkali-kali (per shift/per giliran), bukan
// sekali di akhir. Total actual = SUM semua log utk operation itu.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2prd')->create('prd_wo_operation_output', function (Blueprint $table) {
            $table->id('output_id');
            $table->foreignId('operation_id')->constrained('prd_wo_operation', 'operation_id');
            $table->decimal('qty_good', 18, 4);
            $table->decimal('qty_reject', 18, 4)->default(0);
            $table->decimal('qty_waste', 18, 4)->default(0);
            $table->string('shift_note', 50)->nullable()->comment('Free text mis. "Shift 1" -- belum ada FK ke emp_shift, lihat catatan cross-modul di file migration');
            $table->string('notes', 255)->nullable();
            $table->string('recorded_by', 50);
            $table->dateTime('recorded_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['operation_id', 'recorded_at'], 'idx_prd_wo_op_output_operation');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2prd')->dropIfExists('prd_wo_operation_output');
    }
};
