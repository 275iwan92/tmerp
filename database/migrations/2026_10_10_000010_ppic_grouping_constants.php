<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Start values for die grouping and ink order (ASSUMED, to be confirmed by the user). Dummy sys database first (--env=mockup).
return new class extends Migration
{
    private const CONSTANTS = [
        'PPIC_GROUP_DUE_HOURS' => [48, 'Scheduling: jobs on the same die whose due dates are within this many hours are put one after the other, so the die is mounted once.'],
        'PPIC_GROUP_SLACK_HOURS' => [8, 'Scheduling: a machine that already has the same die is chosen when it finishes within this many hours of the earliest machine, if the due date holds.'],
        'PPIC_COLOR_ORDER_FACTOR' => [0.7, 'Scheduling: wash minutes are multiplied by this when the next job inks are darker than the previous (light to dark). Assumed, not proven for this plant.'],
    ];

    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        foreach (self::CONSTANTS as $key => [$value, $description]) {
            if (! $sys->table('sys_constant')->where('constant_key', $key)->exists()) {
                $sys->table('sys_constant')->insert(['constant_key' => $key, 'category_code' => 'PRODUCTION', 'constant_group' => 'PPIC', 'constant_number' => $value, 'description' => $description,
                    'activate' => 1, 'created_at' => now(), 'created_by' => 'system']);
            }
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->whereIn('constant_key', array_keys(self::CONSTANTS))->delete();
    }
};
