<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Maintenance dibangun ulang (user 2026-10-08): modul MAINT, grup akses, dan menu empat kelompok (Preventive, Breakdown,
// Improvement, Master Data) + Report. Layar yang belum dibangun = baris menu tanpa target (NOT_BUILT); target diisi saat
// layarnya selesai. Sisa Maintenance lama dibersihkan dulu. Jalankan hanya dengan --env=mockup sampai rilis ke tm2*.
return new class extends Migration
{
    private const OLD_TABLES = ['mnt_log', 'mnt_meter_reading', 'mnt_wo_labor', 'mnt_wo_part', 'mnt_wo_task', 'mnt_wo', 'mnt_request', 'mnt_plan_part', 'mnt_plan_task', 'mnt_plan'];

    public function up(): void
    {
        $this->dropOldTables();
        $sys = DB::connection('tm2sys');
        $now = now();

        // Sisa menu dan Help layar lama (target menunjuk layar yang sudah dihapus).
        $old = $sys->table('sys_app_menu')->where('code', 'like', 'MAINT%')->orWhereIn('code', ['PROD_MTN_SCH', 'PROD_MTN_PREV'])->pluck('id');
        if ($old->isNotEmpty()) {
            $sys->statement('SET FOREIGN_KEY_CHECKS=0');
            $sys->table('sys_app_menu')->whereIn('id', $old)->delete();
            $sys->statement('SET FOREIGN_KEY_CHECKS=1');
        }
        $topics = $sys->table('sys_help_topic')->where('topic_key', 'like', 'maintenance-%')->pluck('id_help_topic');
        if ($topics->isNotEmpty()) {
            $sys->table('sys_help_line')->whereIn('help_topic_id', $topics)->delete();
            $sys->table('sys_help_topic')->whereIn('id_help_topic', $topics)->delete();
        }

        if (! $sys->table('sec_module')->where('module_code', 'MAINT')->exists()) {
            $sys->table('sec_module')->insert(['module_code' => 'MAINT', 'module_name' => 'Maintenance', 'icon' => 'fa-solid fa-screwdriver-wrench',
                'route_prefix' => 'maintenance', 'min_mgmt_level' => 1, 'is_reportable' => 1, 'sort_order' => 42, 'activate' => 1,
                'created_at' => $now, 'updated_at' => $now, 'created_by' => 'migration']);
        }
        $mod = $sys->table('sec_module')->where('module_code', 'MAINT')->value('id_module');
        $prod = $sys->table('sec_module')->where('module_code', 'PROD')->value('id_module');

        // Siapa pun yang boleh masuk Production boleh melapor (Request, Daily Check); keputusan butuh can_approve.
        foreach ($sys->table('sec_group_permission')->where('module_id', $prod)->where('can_access', 1)->get() as $p) {
            if (! $sys->table('sec_group_permission')->where('group_id', $p->group_id)->where('module_id', $mod)->exists()) {
                $sys->table('sec_group_permission')->insert(['group_id' => $p->group_id, 'module_id' => $mod, 'can_access' => 1,
                    'can_input' => $p->can_input, 'can_approve' => 0, 'can_export' => $p->can_export, 'created_at' => $now, 'updated_at' => $now, 'created_by' => 'migration']);
            }
        }
        foreach ([['MAINT_TECH', 'Maintenance Technician', 1, 0], ['MAINT_HEAD', 'Head of Maintenance', 3, 1]] as [$code, $name, $level, $approve]) {
            if (! $sys->table('sec_group')->where('group_code', $code)->exists()) {
                $sys->table('sec_group')->insert(['group_code' => $code, 'group_name' => $name, 'mgmt_level' => $level, 'activate' => 1,
                    'created_at' => $now, 'updated_at' => $now, 'created_by' => 'migration']);
            }
            $gid = $sys->table('sec_group')->where('group_code', $code)->value('id_group');
            foreach ([$mod => $approve, $prod => 0] as $mid => $appr) {
                if (! $sys->table('sec_group_permission')->where('group_id', $gid)->where('module_id', $mid)->exists()) {
                    $sys->table('sec_group_permission')->insert(['group_id' => $gid, 'module_id' => $mid, 'can_access' => 1, 'can_input' => 1,
                        'can_approve' => $appr, 'can_export' => 1, 'created_at' => $now, 'updated_at' => $now, 'created_by' => 'migration']);
                }
            }
        }
        foreach (['PROD_MGR', 'OPS_MGR', 'DIREKSI'] as $code) {
            $gid = $sys->table('sec_group')->where('group_code', $code)->value('id_group');
            if ($gid) {
                $sys->table('sec_group_permission')->where('group_id', $gid)->where('module_id', $mod)->update(['can_approve' => 1]);
            }
        }

        $this->menu($sys, $now);
    }

    // Tabel mnt_* lama di tm2prd hanya dibuang bila kosong (kode tabel master boleh berisi data awal); database ddt* sudah bersih.
    private function dropOldTables(): void
    {
        $s = Schema::connection('tm2prd');
        $db = DB::connection('tm2prd');
        foreach (self::OLD_TABLES as $t) {
            if ($s->hasTable($t) && $db->table($t)->count() > 0) {
                return;
            }
        }
        $db->statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ([...self::OLD_TABLES, 'mnt_code'] as $t) {
            $s->dropIfExists($t);
        }
        $db->statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function menu($sys, $now): void
    {
        $add = function (string $code, ?string $parent, string $label, string $icon, string $type, array $extra = []) use ($sys, $now) {
            $sys->table('sys_app_menu')->insert([
                'code' => $code, 'parent_id' => $parent ? $sys->table('sys_app_menu')->where('code', $parent)->value('id') : null,
                'label' => $label, 'icon' => $icon, 'menu_type' => $type, 'target_kind' => 'app', 'activate' => 1, 'created_at' => $now,
                'status_code' => isset($extra['target']) ? 'READY_FOR_QC' : 'NOT_BUILT', 'status_updated_by' => 'claude (maintenance rebuild)',
            ] + $extra);
        };
        $item = fn (string $code, string $parent, string $label, string $icon, int $sort, string $object = 'form', array $extra = []) => $add($code, $parent, $label, $icon, 'item', ['object_type' => $object, 'sort_order' => $sort] + $extra);

        $add('MAINT', null, 'Maintenance', 'fa-solid fa-screwdriver-wrench', 'module', ['short_label' => 'MT', 'sort_order' => 42, 'required_object' => 'MAINT', 'doc_slug' => 'maintenance']);

        $add('MAINT_GRP_PM', 'MAINT', 'Preventive Maintenance', 'fa-solid fa-calendar-check', 'group', ['sort_order' => 10]);
        $item('MAINT_PLAN', 'MAINT_GRP_PM', 'Preventive Plan', 'fa-solid fa-calendar-check', 10, 'form', ['target' => 'v2:maintenance-plan']);
        $item('MAINT_PM_CAL', 'MAINT_GRP_PM', 'PM Calendar', 'fa-solid fa-calendar-days', 20, 'form', ['target' => 'v2:maintenance-pm-calendar']);
        $item('MAINT_PMO', 'MAINT_GRP_PM', 'Preventive Maintenance Order', 'fa-solid fa-screwdriver-wrench', 30, 'form', ['target' => 'v2:maintenance-pmo']);
        $item('MAINT_DAILY', 'MAINT_GRP_PM', 'Daily Check', 'fa-solid fa-clipboard-check', 40, 'form', ['target' => 'v2:maintenance-daily-check']);
        $item('MAINT_METER', 'MAINT_GRP_PM', 'Meter Readings', 'fa-solid fa-gauge-high', 50, 'form', ['target' => 'v2:maintenance-meter']);

        $add('MAINT_GRP_BD', 'MAINT', 'Breakdown Maintenance', 'fa-solid fa-triangle-exclamation', 'group', ['sort_order' => 20]);
        $item('MAINT_REQ', 'MAINT_GRP_BD', 'Maintenance Request', 'fa-solid fa-triangle-exclamation', 10);
        $item('MAINT_BMO', 'MAINT_GRP_BD', 'Breakdown Maintenance Order', 'fa-solid fa-screwdriver-wrench', 20);
        $item('MAINT_MYWORK', 'MAINT_GRP_BD', 'My Work', 'fa-solid fa-user-gear', 30);

        $add('MAINT_GRP_IMP', 'MAINT', 'Improvement', 'fa-solid fa-lightbulb', 'group', ['sort_order' => 30]);
        $item('MAINT_IMP', 'MAINT_GRP_IMP', 'Improvement Proposal', 'fa-solid fa-lightbulb', 10);
        $item('MAINT_IMP_REV', 'MAINT_GRP_IMP', 'Improvement Review', 'fa-solid fa-chart-line', 20);

        $add('MAINT_GRP_MASTER', 'MAINT', 'Master Data', 'fa-solid fa-book', 'group', ['sort_order' => 40]);
        $item('MAINT_PROFILE', 'MAINT_GRP_MASTER', 'Asset Maintenance Profile', 'fa-solid fa-gears', 10, 'form', ['target' => 'v2:maintenance-profile']);
        $item('MAINT_READY', 'MAINT_GRP_MASTER', 'Asset Readiness', 'fa-solid fa-list-check', 20, 'report', ['target' => 'v2:maintenance-readiness']);
        $item('MAINT_DOCS', 'MAINT_GRP_MASTER', 'SOP and Work Instructions', 'fa-solid fa-file-lines', 30, 'form', ['target' => 'v2:maintenance-documents']);
        $item('MAINT_CODES', 'MAINT_GRP_MASTER', 'Maintenance Codes', 'fa-solid fa-tags', 40, 'form', ['target' => 'v2:maintenance-codes']);
        $item('MAINT_MACHINE', 'MAINT_GRP_MASTER', 'Master Machine', 'fa-solid fa-gears', 50, 'form', ['target' => 'v2:prod-machine']);

        $add('MAINT_GRP_RPT', 'MAINT', 'Report', 'fa-solid fa-chart-column', 'group', ['sort_order' => 50]);
        $item('MAINT_ORDER_RPT', 'MAINT_GRP_RPT', 'Maintenance Order Report', 'fa-solid fa-table-list', 10, 'report');
        $item('MAINT_RELIAB', 'MAINT_GRP_RPT', 'Machine Reliability', 'fa-solid fa-chart-column', 20, 'report');
        $item('MAINT_PMCOMP', 'MAINT_GRP_RPT', 'PM Compliance', 'fa-solid fa-percent', 30, 'report', ['target' => 'v2:maintenance-pm-compliance']);
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $sys->table('sys_app_menu')->where('code', 'like', 'MAINT%')->delete();
    }
};
