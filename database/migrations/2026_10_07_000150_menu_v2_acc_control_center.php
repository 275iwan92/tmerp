<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// C3 (a): Finance > Control Center (Dashboard, Process Engine) pindah ke layar v2.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    private const MAP = ['FIN_DASH' => ['v2:acc-dashboard', 'report'], 'FIN_ENGINE' => ['v2:acc-engine', 'form']];

    public function up(): void
    {
        foreach (self::MAP as $code => [$target, $type]) {
            DB::connection('tm2sys')->table('sys_app_menu')->where('code', $code)->update(['target' => $target, 'object_type' => $type,
                'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', array_keys(self::MAP))->update(['target' => null]);
    }
};
