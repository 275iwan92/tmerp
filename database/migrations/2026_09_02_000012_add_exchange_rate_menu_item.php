<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Master Kurs -- CRUD utk fin_exchange_rate (docs/configurationstandard.md
// §2.4, BACKLOG.md §2). Ditaruh sebelah Master Currency (parent_id=5=MDATA
// module, langsung jadi item -- sama spt MD_CURRENCY, tanpa grup perantara).
return new class extends Migration
{
    public function up(): void
    {
        $nextId = (int) DB::connection('tm2sys')->table('sys_app_menu')->max('id') + 1;

        DB::connection('tm2sys')->table('sys_app_menu')->updateOrInsert(
            ['code' => 'MD_EXCHRATE'],
            [
                'id' => $nextId,
                'parent_id' => 5,
                'label' => 'Master Kurs',
                'menu_type' => 'item',
                'object_type' => 'form',
                'target_kind' => 'app',
                'sort_order' => 91,
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
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'MD_EXCHRATE')->delete();
    }
};
