<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// UI-20 rollout (backlog01): laporan stok RM / FG / SFG -> Grid Report v2 (Stock Balance, Stock Card, Slow Moving Item).
// Target lama dicatat di status_note supaya down() mengembalikannya persis (MenuRoutes v1 tetap memegang alamat v1).
return new class extends Migration
{
    protected $connection = 'tm2sys';

    private const REPORTS = ['INV_RAW' => 'balance', 'INV_CARD' => 'card', 'INV_SLOW' => 'slow'];

    private const CLASSES = ['RM', 'FG', 'SFG'];

    public function up(): void
    {
        foreach (self::REPORTS as $prefix => $report) {
            foreach (self::CLASSES as $class) {
                DB::connection('tm2sys')->table('sys_app_menu')->where('code', $prefix.'_'.$class)
                    ->where(fn ($q) => $q->whereNull('target')->orWhere('target', 'not like', 'v2:%'))
                    ->update(['target' => 'v2:inv-'.strtolower($class).'-rpt-'.$report, 'object_type' => 'report', 'status_code' => 'READY_FOR_QC',
                        'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::REPORTS as $prefix => $report) {
            foreach (self::CLASSES as $class) {
                DB::connection('tm2sys')->table('sys_app_menu')->where('code', $prefix.'_'.$class)
                    ->where('target', 'v2:inv-'.strtolower($class).'-rpt-'.$report)->update(['target' => null, 'status_code' => 'NOT_BUILT']);
            }
        }
    }
};
