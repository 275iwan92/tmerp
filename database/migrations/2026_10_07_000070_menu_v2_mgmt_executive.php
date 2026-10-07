<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// C5: Management Reports > Executive Summary pindah ke layar v2 (v2:mgmt-executive; rincian per bidang masih laporan v1).
return new class extends Migration
{
    protected $connection = 'tm2sys';

    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'MGMT_EXEC')->update(['target' => 'v2:mgmt-executive', 'object_type' => 'report',
            'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'MGMT_EXEC')->update(['target' => 'report.executive']);
    }
};
