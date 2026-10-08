<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// PPIC access flags (not menu modules): PPIC_SCHEDULING changes queues and the plan, PPIC_INVENTORY changes bookings, shortage follow-up and ready dates.
// Starting grants: Production Planning (both), Production Manager and Operations Manager (scheduling). Admins assign more in Security. Dummy sys database first (--env=mockup).
return new class extends Migration
{
    private const FLAGS = [
        'PPIC_SCHEDULING' => ['PPIC Scheduling', 41, ['PROD_PLANNING', 'PROD_MGR', 'OPS_MGR']],
        'PPIC_INVENTORY' => ['PPIC Inventory Control', 42, ['PROD_PLANNING', 'MGR_INV']],
    ];

    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $parent = $sys->table('sec_module')->where('module_code', 'PROD')->first();
        foreach (self::FLAGS as $code => [$name, $sort, $groups]) {
            if (! $sys->table('sec_module')->where('module_code', $code)->exists()) {
                $sys->table('sec_module')->insert(['parent_id' => $parent?->id_module, 'module_code' => $code, 'module_name' => $name, 'icon' => 'fa-solid fa-flag', 'route_prefix' => null,
                    'min_mgmt_level' => 1, 'is_reportable' => 0, 'sort_order' => $sort, 'activate' => 1, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'migration']);
            }
            $moduleId = $sys->table('sec_module')->where('module_code', $code)->value('id_module');
            foreach ($sys->table('sec_group')->whereIn('group_code', $groups)->pluck('id_group') as $groupId) {
                $has = $sys->table('sec_group_permission')->where('group_id', $groupId)->where('module_id', $moduleId)->exists();
                if (! $has) {
                    $sys->table('sec_group_permission')->insert(['group_id' => $groupId, 'module_id' => $moduleId, 'can_access' => 1, 'can_input' => 1, 'can_approve' => 1, 'can_export' => 1,
                        'created_at' => now(), 'updated_at' => now(), 'created_by' => 'migration']);
                }
            }
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $ids = $sys->table('sec_module')->whereIn('module_code', array_keys(self::FLAGS))->pluck('id_module');
        $sys->table('sec_group_permission')->whereIn('module_id', $ids)->delete();
        $sys->table('sec_module')->whereIn('module_code', array_keys(self::FLAGS))->delete();
    }
};
