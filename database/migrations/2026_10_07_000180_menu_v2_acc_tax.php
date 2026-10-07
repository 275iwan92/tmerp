<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// C3 (d): Finance > Controls & Tax > Indonesian Tax pindah ke layar v2 (v2:acc-tax).
return new class extends Migration
{
    protected $connection = 'tm2sys';

    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'ACC_TAX')->update(['target' => 'v2:acc-tax', 'object_type' => 'form',
            'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'ACC_TAX')->update(['target' => null]);
    }
};
