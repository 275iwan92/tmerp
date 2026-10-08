<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Maintenance Fase 2 dan 3: layar Dashboard, Request, My Work, BMO, Machine Reliability dan Maintenance Order Report masuk menu; target KPI menjadi konstanta
// (Settings > Constants). Jalankan dengan --env=mockup sampai rilis ke tm2*.
return new class extends Migration
{
    private const TARGETS = [
        ['MNT_TARGET_AVAILABILITY', 97, 'Maintenance KPI target: machine availability, percent (higher is better).'],
        ['MNT_TARGET_MTBF_HOURS', 120, 'Maintenance KPI target: mean time between failures, hours (higher is better).'],
        ['MNT_TARGET_MTTR_HOURS', 4, 'Maintenance KPI target: mean time to repair, hours (lower is better).'],
        ['MNT_TARGET_PM_COMPLIANCE', 90, 'Maintenance KPI target: preventive orders done on time, percent (higher is better).'],
        ['MNT_TARGET_PLANNED_WORK', 85, 'Maintenance KPI target: planned work share of all orders, percent (higher is better).'],
        ['MNT_TARGET_BACKLOG_WEEKS_MAX', 5, 'Maintenance KPI target: largest acceptable backlog in weeks of technician time (lower is better).'],
        ['MNT_TECHNICIAN_HOURS_WEEK', 40, 'Maintenance: working hours one technician has per week, used to turn open order hours into backlog weeks.'],
    ];

    private const TARGET_SCREENS = ['MAINT_REQ' => 'maintenance-request', 'MAINT_BMO' => 'maintenance-bmo', 'MAINT_MYWORK' => 'maintenance-my-work',
        'MAINT_ORDER_RPT' => 'maintenance-order-report', 'MAINT_RELIAB' => 'maintenance-reliability'];

    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $now = now();
        foreach (self::TARGETS as [$key, $value, $desc]) {
            if (! $sys->table('sys_constant')->where('constant_key', $key)->exists()) {
                $sys->table('sys_constant')->insert(['constant_key' => $key, 'category_code' => 'PRODUCTION', 'constant_group' => 'MAINTENANCE', 'constant_number' => $value,
                    'description' => $desc, 'activate' => 1, 'created_at' => $now, 'updated_at' => $now, 'created_by' => 'migration']);
            }
        }

        foreach (self::TARGET_SCREENS as $code => $slug) {
            $sys->table('sys_app_menu')->where('code', $code)->update(['target' => 'v2:'.$slug, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (maintenance phase 3)']);
        }

        if (! $sys->table('sys_app_menu')->where('code', 'MAINT_DASH')->exists()) {
            $module = $sys->table('sys_app_menu')->where('code', 'MAINT')->value('id');
            $base = ['target_kind' => 'app', 'activate' => 1, 'created_at' => $now, 'status_updated_by' => 'claude (maintenance phase 3)'];
            $sys->table('sys_app_menu')->insert(['code' => 'MAINT_GRP_DASH', 'parent_id' => $module, 'label' => 'Dashboard', 'icon' => 'fa-solid fa-gauge-high', 'menu_type' => 'group',
                'sort_order' => 5, 'status_code' => 'NOT_BUILT'] + $base);
            $sys->table('sys_app_menu')->insert(['code' => 'MAINT_DASH', 'parent_id' => $sys->table('sys_app_menu')->where('code', 'MAINT_GRP_DASH')->value('id'), 'label' => 'Maintenance Dashboard',
                'icon' => 'fa-solid fa-gauge-high', 'menu_type' => 'item', 'object_type' => 'report', 'sort_order' => 10, 'target' => 'v2:maintenance-dashboard', 'status_code' => 'READY_FOR_QC'] + $base);
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $sys->table('sys_constant')->whereIn('constant_key', array_column(self::TARGETS, 0))->delete();
        $sys->table('sys_app_menu')->whereIn('code', ['MAINT_DASH', 'MAINT_GRP_DASH'])->delete();
        $sys->table('sys_app_menu')->whereIn('code', array_keys(self::TARGET_SCREENS))->update(['target' => null, 'status_code' => 'NOT_BUILT']);
    }
};
