<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu "SOP" -- grup Organization HRM (parent 1006, sebelahan Master Posisi
// & Struktur Organisasi), route hrm.sop-documents.
return new class extends Migration
{
    public function up(): void
    {
        $nextId = (int) DB::connection('tm2sys')->table('sys_app_menu')->max('id') + 1;

        DB::connection('tm2sys')->table('sys_app_menu')->updateOrInsert(
            ['code' => 'HRM_SOP'],
            [
                'id' => $nextId,
                'parent_id' => 1006,
                'label' => 'SOP',
                'menu_type' => 'item',
                'object_type' => 'grid',
                'target_kind' => 'app',
                'sort_order' => 30,
                'activate' => 1,
                'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude_code',
                'status_updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'HRM_SOP')->delete();
    }
};
