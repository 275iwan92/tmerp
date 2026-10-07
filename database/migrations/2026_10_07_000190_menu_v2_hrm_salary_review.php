<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Payroll > Salary Review (Excel): unduhan rincian gaji per pegawai untuk telaah manual direksi (v2:hrm-salary-review).
return new class extends Migration
{
    protected $connection = 'tm2sys';

    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        if ($sys->table('sys_app_menu')->where('code', 'HRM_PAYROLL_SALARYREVIEW')->exists()) {
            return;
        }
        $sys->table('sys_app_menu')->insert([
            'id' => (int) $sys->table('sys_app_menu')->max('id') + 1,
            'parent_id' => $sys->table('sys_app_menu')->where('code', 'HRM_PAYROLL')->value('id'),
            'code' => 'HRM_PAYROLL_SALARYREVIEW', 'label' => 'Salary Review (Excel)', 'menu_type' => 'item', 'object_type' => 'report',
            'target_kind' => 'app', 'target' => 'v2:hrm-salary-review', 'sort_order' => 50, 'activate' => true,
            'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'HRM_PAYROLL_SALARYREVIEW')->delete();
    }
};
