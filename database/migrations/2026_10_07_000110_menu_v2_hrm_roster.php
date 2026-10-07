<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// C6: Shift Roster (HRM) dan Shift Schedule (Production > Support) -> layar v2 yang sama (v2:hrm-roster).
return new class extends Migration
{
    protected $connection = 'tm2sys';

    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', ['HRM_ROSTER', 'PROD_SHIFT_ROSTER'])->update(['target' => 'v2:hrm-roster', 'object_type' => 'form',
            'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', ['HRM_ROSTER', 'PROD_SHIFT_ROSTER'])->update(['target' => null]);
    }
};
