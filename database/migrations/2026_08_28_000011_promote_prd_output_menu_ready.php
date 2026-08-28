<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Production Output (F15) -- catat qty baik/reject/waste per task (tombol
// "Catat Output" di WO Detail, tautan progress ke Scheduling Board) sudah
// diverifikasi jalan (tests/Feature/ProductionWorklistWoTest.php
// test_output_entry_accumulates_and_reflects_on_board). Promosikan menu dari
// NOT_BUILT -> READY_FOR_QC. PROD_MON_WO SENGAJA tidak disentuh -- namanya
// mirip Worklist WO tapi ada di grup Output bukan Planning, kemungkinan
// dashboard/laporan terpisah yang belum jelas spec-nya (tak ada Tier
// dokumen), jangan diklaim selesai tanpa desain lebih lanjut.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'PROD_OUTPUT')
            ->update([
                'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude_code',
                'status_updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'PROD_OUTPUT')
            ->update(['status_code' => 'NOT_BUILT', 'status_updated_by' => null, 'status_updated_at' => null]);
    }
};
