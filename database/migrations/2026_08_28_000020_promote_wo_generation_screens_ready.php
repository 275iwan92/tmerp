<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Screen #1 (PROD_WO_GENERATE) + Screen #3 (PROD_MON_WO, kode legacy V1
// "Monitoring Work Order" -- dipakai ulang, bukan bikin menu baru) sudah
// diverifikasi jalan (tinker + Livewire::test, lihat
// docs/production_scheduling_wo_generation_delta.md §8). Screen #2 (WO
// Detail) & print form tidak py menu sendiri -- diakses via Worklist WO /
// tombol dalam WO Detail, pola sama Nota Inquiry.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $sys->table('sys_app_menu')
            ->whereIn('code', ['PROD_WO_GENERATE', 'PROD_MON_WO'])
            ->update([
                'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude_code',
                'status_updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->whereIn('code', ['PROD_WO_GENERATE', 'PROD_MON_WO'])
            ->update(['status_code' => 'NOT_BUILT', 'status_updated_by' => null, 'status_updated_at' => null]);
    }
};
