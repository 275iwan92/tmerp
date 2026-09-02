<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Master Hari Libur -- docs/BACKLOG.md item "mudah" #1. Ditaruh sebelah
// Master Kurs (parent_id=5=MDATA module, langsung item, sama pola).
return new class extends Migration
{
    public function up(): void
    {
        $nextId = (int) DB::connection('tm2sys')->table('sys_app_menu')->max('id') + 1;

        DB::connection('tm2sys')->table('sys_app_menu')->updateOrInsert(
            ['code' => 'MD_HOLIDAY'],
            [
                'id' => $nextId,
                'parent_id' => 5,
                'label' => 'Master Hari Libur',
                'menu_type' => 'item',
                'object_type' => 'form',
                'target_kind' => 'app',
                'sort_order' => 92,
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
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'MD_HOLIDAY')->delete();
    }
};
