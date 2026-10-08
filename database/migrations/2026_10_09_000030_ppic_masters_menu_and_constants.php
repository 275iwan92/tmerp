<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// PPIC masters (Tooling Master, Setup and Changeover, Wait Rules) join Production > Master, and the scheduling settings become rows in
// Settings > Constants (every value has an input screen). Values are the ASSUMED start values of config/ppic.php; the learning engine and the user correct them.
// Run on the dummy menu database first (--env=mockup); live at release.
return new class extends Migration
{
    private const MENU = [
        'PROD_MST_TOOLING' => ['Tooling Master', 'fa-solid fa-screwdriver-wrench', 'prod-tooling', 60],
        'PROD_MST_CHANGEOVER' => ['Setup and Changeover', 'fa-solid fa-arrows-rotate', 'prod-changeover', 61],
        'PROD_MST_WAIT_RULE' => ['Wait Rules', 'fa-solid fa-hourglass-half', 'prod-wait-rule', 62],
    ];

    /** key => [value, description] */
    private const CONSTANTS = [
        'PPIC_LOCK_SHIFTS' => [1, 'Scheduling: the plan stays locked for the next N running shifts after the officer confirms it. The engine never moves a locked task.'],
        'PPIC_WEIGHT_URGENCY' => [0.50, 'Scheduling priority score: weight of the due date urgency (0 to 1).'],
        'PPIC_WEIGHT_SETUP' => [0.35, 'Scheduling priority score: weight of the setup saving (0 to 1).'],
        'PPIC_WEIGHT_TIER' => [0.15, 'Scheduling priority score: weight of the customer tier (0 to 1).'],
        'PPIC_SIMILAR_WINDOW_HOURS' => [8, 'Scheduling: jobs whose slack differs by less than this many hours are ordered by the setup-aware score.'],
        'PPIC_HORIZON_HOURS' => [72, 'Scheduling: hours ahead in which the engine groups jobs to save setup.'],
        'PPIC_HORIZON_DAYS' => [60, 'Scheduling: days ahead a plan run looks (holidays, capacity).'],
        'PPIC_BOTTLENECK_LOAD_PCT' => [90, 'Scheduling: a process is a bottleneck when its machine load is at least this percent and the highest of the horizon.'],
        'PPIC_DEFAULT_SHIFTS' => [2, 'Scheduling: shifts per day for a machine that has no shift count of its own.'],
        'PPIC_HOURS_PER_SHIFT' => [8, 'Scheduling: hours per shift for a machine that has no shift hours of its own.'],
        'PPIC_BASE_SETUP_MIN' => [15, 'Scheduling: base setup minutes between two jobs on a machine (changeover and tooling are added).'],
        'PPIC_LEARN_MIN_SAMPLES' => [5, 'Learning: a value is adjusted only after at least this many finished jobs.'],
        'PPIC_LEARN_MAX_STEP_PCT' => [20, 'Learning: one adjustment moves a value by at most this percent.'],
        'PPIC_LEARN_BLEND' => [0.3, 'Learning: share of the typical actual that is blended into the old value (0 to 1).'],
    ];

    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $menu = fn () => $sys->table('sys_app_menu');
        $parent = $menu()->where('code', 'PROD_MASTER')->first();
        if ($parent) {
            foreach (self::MENU as $code => [$label, $icon, $slug, $sort]) {
                if ($menu()->where('code', $code)->exists()) {
                    continue;
                }
                $menu()->insert([
                    'parent_id' => $parent->id, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'item', 'object_type' => 'grid',
                    'target' => 'v2:'.$slug, 'sort_order' => $sort, 'activate' => 1, 'required_object' => $parent->required_object, 'status_code' => 'READY_FOR_QC',
                    'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now(),
                ]);
            }
        }
        foreach (self::CONSTANTS as $key => [$value, $description]) {
            if ($sys->table('sys_constant')->where('constant_key', $key)->exists()) {
                continue;
            }
            $sys->table('sys_constant')->insert([
                'constant_key' => $key, 'category_code' => 'PRODUCTION', 'constant_group' => 'PPIC', 'constant_number' => $value, 'description' => $description,
                'activate' => 1, 'created_at' => now(), 'created_by' => 'system',
            ]);
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $sys->table('sys_app_menu')->whereIn('code', array_keys(self::MENU))->delete();
        $sys->table('sys_constant')->whereIn('constant_key', array_keys(self::CONSTANTS))->delete();
    }
};
