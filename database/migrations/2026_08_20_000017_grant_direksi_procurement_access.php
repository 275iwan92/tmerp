<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// sec_module 'PROC' sudah ada dari seed dasar tapi belum ada satupun baris
// sec_group_permission untuk itu (dicek 2026-08-20) -- non-admin user tak akan
// bisa buka /purchasing/pr sama sekali. Grant DIREKSI penuh, mirip pola COSTING
// (2026_08_15_000001) & FINACC. Grup khusus Purchasing (Pemohon/Purchasing
// Officer) menyusul saat Security/HRM menugaskan user riil ke posisi itu --
// dicatat di PROC_STATE.yaml, bukan diputuskan sepihak di sini.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $procId = $sys->table('sec_module')->where('module_code', 'PROC')->value('id_module');
        $direksiId = $sys->table('sec_group')->where('group_code', 'DIREKSI')->value('id_group');

        if ($procId && $direksiId) {
            $sys->table('sec_group_permission')->updateOrInsert(
                ['group_id' => $direksiId, 'module_id' => $procId],
                ['can_access' => true, 'can_input' => true, 'can_approve' => true, 'can_export' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');

        $procId = $sys->table('sec_module')->where('module_code', 'PROC')->value('id_module');
        $direksiId = $sys->table('sec_group')->where('group_code', 'DIREKSI')->value('id_group');

        if ($procId && $direksiId) {
            $sys->table('sec_group_permission')->where('group_id', $direksiId)->where('module_id', $procId)->delete();
        }
    }
};
