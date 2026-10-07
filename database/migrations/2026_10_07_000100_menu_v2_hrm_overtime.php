<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// C6: HRM > Time Attendance > Overtime punya layar v2 (v2:hrm-overtime; dulu belum ada layar sama sekali: data emp_overtime_request hanya dari seeder).
return new class extends Migration
{
    protected $connection = 'tm2sys';

    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'HRM_OVERTIME')->update(['target' => 'v2:hrm-overtime', 'object_type' => 'grid',
            'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'HRM_OVERTIME')->update(['target' => null]);
    }
};
