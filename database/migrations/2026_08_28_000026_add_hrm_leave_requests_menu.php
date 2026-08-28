<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu "Approval Cuti/Sakit/Izin" di bawah modul HRM -> pages::hrm.leave-requests
// (route hrm.leave-requests). Item flat langsung di bawah HRM, pola sama
// HRM_CORRECTION (2026_08_27_000015) -- layar review 1-shot, bukan sub-grup.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $hrmId = $sys->table('sys_app_menu')->where('code', 'HRM')->value('id');

        if (! $hrmId || $sys->table('sys_app_menu')->where('code', 'HRM_LEAVE_APPROVAL')->exists()) {
            return;
        }

        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        $sys->table('sys_app_menu')->insert([
            'id' => $nextId,
            'parent_id' => $hrmId,
            'code' => 'HRM_LEAVE_APPROVAL',
            'label' => 'Approval Cuti/Sakit/Izin',
            'menu_type' => 'item',
            'object_type' => 'grid',
            'target_kind' => 'app',
            'sort_order' => 56,
            'activate' => true,
            'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'HRM_LEAVE_APPROVAL')->delete();
    }
};
