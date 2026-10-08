<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Konstanta Maintenance (semua punya layar input di Settings > Constants). Nilai di sini hanya usulan awal; user yang menetapkan.
// Jalankan dengan --env=mockup sampai rilis ke tm2*. Bila konstanta sudah ada (mis. di live), tidak ditimpa.
return new class extends Migration
{
    private const ROWS = [
        ['MNT_LABOR_RATE_HOUR', 50000, 'Maintenance: technician labor rate per hour (IDR) for order cost. A standard rate, not payroll data.'],
        ['MNT_PM_ONTIME_TOLERANCE_PCT', 10, 'Maintenance: how late a preventive order may finish and still count as on time, as a percent of the plan interval.'],
    ];

    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        foreach (self::ROWS as [$key, $value, $desc]) {
            if (! $sys->table('sys_constant')->where('constant_key', $key)->exists()) {
                $sys->table('sys_constant')->insert(['constant_key' => $key, 'category_code' => 'PRODUCTION', 'constant_group' => 'MAINTENANCE', 'constant_number' => $value,
                    'description' => $desc, 'activate' => 1, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'migration']);
            }
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->whereIn('constant_key', array_column(self::ROWS, 0))->delete();
    }
};
