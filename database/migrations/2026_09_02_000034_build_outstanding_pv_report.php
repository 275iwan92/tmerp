<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/BACKLOG.md §7 "Outstanding PV" -- menu placeholder PROC_MON_PV sudah
// ada slotnya sejak 2026-08-21 (NOT_BUILT, "ditunda sampai Stock Out
// dibangun & mekanisme serah terima ada"). Kedua prasyarat sudah terpenuhi
// (Stock Out live 2026-08-22, mekanisme serah terima = migration
// 2026_09_02_000033 pv_receipt.is_handed_over) -- layar live, tinggal flip status.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PROC_MON_PV')->update([
            'status_code' => 'READY_FOR_QC',
            'status_note' => 'Live 2026-09-02: flag is_handed_over per-PV (opsi paling sederhana dari 3 yg diajukan dulu).',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PROC_MON_PV')->update([
            'status_code' => 'NOT_BUILT',
            'status_note' => 'Ditunda -- butuh mekanisme serah terima barang ke pemohon (belum ada di skema). Dikerjakan setelah Stock Out.',
            'status_updated_by' => null,
            'status_updated_at' => null,
        ]);
    }
};
