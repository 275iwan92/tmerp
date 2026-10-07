<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// C4: Pro-Cost Instant pindah ke daftar v2 (v2:sales-procost-instant; buka baris = form Instant v1 apa adanya, zona beku).
return new class extends Migration
{
    protected $connection = 'tm2sys';

    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PRE_COST_INSTANT')->update(['target' => 'v2:sales-procost-instant', 'object_type' => 'grid',
            'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'PRE_COST_INSTANT')->update(['target' => null]);
    }
};
