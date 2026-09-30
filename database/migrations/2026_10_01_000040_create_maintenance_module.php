<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Modul Maintenance v2 (docs/maintenance_tier1.md, disetujui user 2026-10-01 lewat mockup maintenance_v2.jpg):
// tabel mnt_* di tm2prd + modul akses MAINT, grup MAINT_TECH/MAINT_HEAD, menu v2, konstanta tarif teknisi, katalog kode English.
// Relasi ke tm2mst (mesin, pegawai, bahan) = logis, bukan FK (beda database).
return new class extends Migration
{
    private function audit(Blueprint $t): void
    {
        $t->timestamp('created_at')->nullable();
        $t->string('created_by', 50)->nullable();
        $t->timestamp('updated_at')->nullable();
        $t->string('updated_by', 50)->nullable();
    }

    public function up(): void
    {
        $s = Schema::connection('tm2prd');

        $s->create('mnt_code', function (Blueprint $t) {
            $t->id('id_code');
            $t->string('code_type', 20);           // COMPONENT | PROBLEM | CAUSE | ACTION
            $t->string('code', 40);
            $t->string('name', 120);
            $t->string('machine_type', 60)->nullable(); // null = berlaku semua jenis mesin
            $t->integer('sort_order')->default(0);
            $t->boolean('activate')->default(true);
            $this->audit($t);
            $t->unique(['code_type', 'code']);
        });

        $s->create('mnt_plan', function (Blueprint $t) {
            $t->id('id_plan');
            $t->string('plan_code', 40)->unique();
            $t->unsignedBigInteger('machine_id')->index();
            $t->string('plan_name', 150);
            $t->string('wo_type', 20)->default('PREVENTIVE');      // PREVENTIVE | INSPECTION
            $t->string('trigger_mode', 20)->default('TIME');       // TIME | METER | TIME_OR_METER
            $t->unsignedInteger('interval_value')->nullable();
            $t->string('interval_unit', 10)->nullable();          // DAY | WEEK | MONTH | YEAR
            $t->string('meter_type', 20)->nullable();             // RUN_HOURS | IMPRESSIONS
            $t->decimal('meter_interval', 14, 2)->nullable();
            $t->string('schedule_mode', 10)->default('FLOATING'); // FLOATING | FIXED
            $t->unsignedInteger('lead_days')->default(3);
            $t->unsignedInteger('est_minutes')->nullable();
            $t->boolean('needs_machine_stop')->default(true);
            $t->unsignedBigInteger('vendor_id')->nullable();
            $t->date('certificate_valid_until')->nullable();       // inspeksi K3 berkala (K10)
            $t->date('last_done_date')->nullable();
            $t->decimal('last_done_meter', 14, 2)->nullable();
            $t->date('next_due_date')->nullable();
            $t->decimal('next_due_meter', 14, 2)->nullable();
            $t->text('notes')->nullable();
            $t->boolean('activate')->default(true);
            $this->audit($t);
        });

        $s->create('mnt_plan_task', function (Blueprint $t) {
            $t->id('id_plan_task');
            $t->foreignId('plan_id')->constrained('mnt_plan', 'id_plan')->cascadeOnDelete();
            $t->unsignedInteger('seq_no')->default(1);
            $t->string('task_text', 255);
            $t->string('task_kind', 20)->default('INSPECT');       // INSPECT | CLEAN | LUBRICATE | ADJUST | REPLACE | MEASURE
            $t->string('measure_uom', 20)->nullable();
            $t->decimal('min_value', 12, 3)->nullable();
            $t->decimal('max_value', 12, 3)->nullable();
            $t->boolean('is_required')->default(true);
            $this->audit($t);
        });

        $s->create('mnt_plan_part', function (Blueprint $t) {
            $t->id('id_plan_part');
            $t->foreignId('plan_id')->constrained('mnt_plan', 'id_plan')->cascadeOnDelete();
            $t->unsignedBigInteger('material_id');
            $t->decimal('qty', 12, 3)->default(1);
            $t->string('uom_code', 20)->nullable();
            $this->audit($t);
        });

        $s->create('mnt_request', function (Blueprint $t) {
            $t->id('id_request');
            $t->string('doc_no', 30)->unique();
            $t->dateTime('request_date');
            $t->unsignedBigInteger('machine_id')->index();
            $t->string('request_kind', 20)->default('BREAKDOWN');   // BREAKDOWN | CORRECTIVE | SAFETY | IMPROVEMENT
            $t->string('priority', 2)->default('P3');               // P1..P4
            $t->boolean('machine_stopped')->default(false);
            $t->dateTime('stopped_at')->nullable();
            $t->text('symptom');
            $t->string('photo_path', 255)->nullable();
            $t->unsignedBigInteger('reported_by')->nullable();       // emp_employee
            $t->unsignedBigInteger('breakdown_event_id')->nullable(); // prd_breakdown_event (Worklist WO)
            $t->unsignedBigInteger('downtime_id')->nullable();       // prod_machine_downtime dibuka saat mesin berhenti
            $t->string('status', 20)->default('OPEN');              // OPEN | ACCEPTED | REJECTED
            $t->string('reject_reason', 255)->nullable();
            $t->unsignedBigInteger('wo_id')->nullable();
            $this->audit($t);
        });

        $s->create('mnt_wo', function (Blueprint $t) {
            $t->id('id_wo');
            $t->string('doc_no', 30)->nullable()->unique();         // lahir saat Confirm
            $t->string('wo_type', 20);                              // PREVENTIVE | BREAKDOWN | CORRECTIVE | INSPECTION
            $t->string('source', 10)->default('MANUAL');            // PLAN | REQUEST | MANUAL
            $t->unsignedBigInteger('plan_id')->nullable()->index();
            $t->unsignedBigInteger('request_id')->nullable();
            $t->unsignedBigInteger('machine_id')->index();
            $t->string('component_code', 40)->nullable();
            $t->string('priority', 2)->default('P3');
            $t->string('description', 255);
            $t->boolean('needs_machine_stop')->default(true);
            $t->dateTime('planned_start')->nullable();
            $t->dateTime('planned_end')->nullable();
            $t->unsignedInteger('est_minutes')->nullable();
            $t->dateTime('actual_start')->nullable();
            $t->dateTime('actual_end')->nullable();
            $t->unsignedBigInteger('downtime_id')->nullable();
            $t->decimal('meter_at_done', 14, 2)->nullable();
            $t->unsignedBigInteger('assigned_to')->nullable();       // emp_employee (teknisi)
            $t->unsignedBigInteger('vendor_id')->nullable();         // sup_supplier (jasa luar)
            $t->string('problem_code', 40)->nullable();
            $t->string('cause_code', 40)->nullable();
            $t->string('action_code', 40)->nullable();
            $t->text('findings')->nullable();
            $t->string('work_status', 20)->default('DRAFT');        // DRAFT | SCHEDULED | IN_PROGRESS | WAITING_PART | DONE
            $t->boolean('stat_confirm')->default(false);
            $t->boolean('stat_cancel')->default(false);
            $t->boolean('stat_close')->default(false);
            $t->string('cancel_reason', 255)->nullable();
            $t->string('closed_by', 50)->nullable();
            $t->dateTime('closed_at')->nullable();
            $t->decimal('cost_part', 16, 2)->default(0);
            $t->decimal('cost_labor', 16, 2)->default(0);
            $t->decimal('cost_vendor', 16, 2)->default(0);
            $this->audit($t);
        });

        $s->create('mnt_wo_task', function (Blueprint $t) {
            $t->id('id_wo_task');
            $t->foreignId('wo_id')->constrained('mnt_wo', 'id_wo')->cascadeOnDelete();
            $t->unsignedInteger('seq_no')->default(1);
            $t->string('task_text', 255);
            $t->string('task_kind', 20)->default('INSPECT');
            $t->string('measure_uom', 20)->nullable();
            $t->decimal('min_value', 12, 3)->nullable();
            $t->decimal('max_value', 12, 3)->nullable();
            $t->boolean('is_required')->default(true);
            $t->string('result', 10)->nullable();                   // OK | NOT_OK | NA
            $t->decimal('measured_value', 12, 3)->nullable();
            $t->string('note', 255)->nullable();
            $t->string('done_by', 50)->nullable();
            $t->dateTime('done_at')->nullable();
            $this->audit($t);
        });

        $s->create('mnt_wo_part', function (Blueprint $t) {
            $t->id('id_wo_part');
            $t->foreignId('wo_id')->constrained('mnt_wo', 'id_wo')->cascadeOnDelete();
            $t->unsignedBigInteger('material_id');
            $t->decimal('qty_plan', 12, 3)->default(0);
            $t->decimal('qty_used', 12, 3)->nullable();
            $t->string('uom_code', 20)->nullable();
            $t->decimal('unit_cost', 16, 2)->default(0);            // snapshot harga rata-rata saat baris dibuat
            $t->unsignedBigInteger('stock_doc_id')->nullable();      // dokumen keluar gudang (tahap M2)
            $t->unsignedBigInteger('pr_line_id')->nullable();        // pr_line bila stok kurang
            $this->audit($t);
        });

        $s->create('mnt_wo_labor', function (Blueprint $t) {
            $t->id('id_wo_labor');
            $t->foreignId('wo_id')->constrained('mnt_wo', 'id_wo')->cascadeOnDelete();
            $t->unsignedBigInteger('employee_id');
            $t->date('work_date');
            $t->unsignedInteger('minutes')->default(0);
            $t->decimal('rate_snapshot', 14, 2)->default(0);        // per jam, dari konstanta MNT_LABOR_RATE_HOUR (bukan gaji)
            $t->string('note', 255)->nullable();
            $this->audit($t);
        });

        $s->create('mnt_meter_reading', function (Blueprint $t) {
            $t->id('id_meter_reading');
            $t->unsignedBigInteger('machine_id');
            $t->string('meter_type', 20);                           // RUN_HOURS | IMPRESSIONS
            $t->dateTime('reading_at');
            $t->decimal('reading_value', 14, 2);
            $t->string('source', 20)->default('MANUAL');           // MANUAL | PRODUCTION
            $this->audit($t);
            $t->index(['machine_id', 'meter_type', 'reading_at']);
        });

        // Riwayat dokumen (tab History): Created, Confirmed, Started, Hold, Resumed, Done, Closed, Cancelled, Accepted, Rejected.
        $s->create('mnt_log', function (Blueprint $t) {
            $t->id('id_log');
            $t->string('doc_type', 10);                             // WO | REQUEST | PLAN
            $t->unsignedBigInteger('doc_id');
            $t->string('action', 30);
            $t->string('note', 255)->nullable();
            $t->timestamp('created_at')->nullable();
            $t->string('created_by', 50)->nullable();
            $t->index(['doc_type', 'doc_id']);
        });

        $this->seedSystem();
        $this->seedCodes();
    }

    private function seedSystem(): void
    {
        $sys = DB::connection('tm2sys');
        $now = now();

        if (! $sys->table('sec_module')->where('module_code', 'MAINT')->exists()) {
            $sys->table('sec_module')->insert(['module_code' => 'MAINT', 'module_name' => 'Maintenance', 'icon' => 'fa-solid fa-screwdriver-wrench',
                'route_prefix' => 'maintenance', 'min_mgmt_level' => 1, 'is_reportable' => 1, 'sort_order' => 42, 'activate' => 1,
                'created_at' => $now, 'updated_at' => $now, 'created_by' => 'migration']);
        }
        $mod = $sys->table('sec_module')->where('module_code', 'MAINT')->value('id_module');
        $prod = $sys->table('sec_module')->where('module_code', 'PROD')->value('id_module');

        // Siapa pun yang boleh masuk Production boleh melapor kerusakan (Request); keputusan (Accept/Close) butuh can_approve.
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
        // Kepala produksi / operasional ikut memutuskan (Accept request, Close WO).
        foreach (['PROD_MGR', 'OPS_MGR', 'DIREKSI'] as $code) {
            $gid = $sys->table('sec_group')->where('group_code', $code)->value('id_group');
            if ($gid) {
                $sys->table('sec_group_permission')->where('group_id', $gid)->where('module_id', $mod)->update(['can_approve' => 1]);
            }
        }

        if (! $sys->table('sys_constant')->where('constant_key', 'MNT_LABOR_RATE_HOUR')->exists()) {
            $sys->table('sys_constant')->insert(['constant_key' => 'MNT_LABOR_RATE_HOUR', 'category_code' => 'PRODUCTION', 'constant_group' => 'MAINTENANCE',
                'constant_number' => 50000, 'description' => 'Maintenance: technician labor rate per hour (IDR) for WO cost — a standard rate, not payroll data',
                'activate' => 1, 'created_at' => $now, 'updated_at' => $now, 'created_by' => 'migration']);
        }

        $menu = fn (array $r) => $sys->table('sys_app_menu')->where('code', $r['code'])->exists() ? null
            : $sys->table('sys_app_menu')->insert($r + ['target_kind' => 'app', 'activate' => 1, 'created_at' => $now,
                'status_code' => isset($r['target']) ? 'READY_FOR_QC' : 'NOT_BUILT', 'status_updated_by' => 'claude (v2 build)']);
        $id = fn (string $code) => $sys->table('sys_app_menu')->where('code', $code)->value('id');

        $menu(['code' => 'MAINT', 'label' => 'Maintenance', 'short_label' => 'MT', 'icon' => 'fa-solid fa-screwdriver-wrench', 'menu_type' => 'module',
            'sort_order' => 42, 'required_object' => 'MAINT', 'doc_slug' => 'maintenance']);
        $menu(['code' => 'MAINT_GRP_WORK', 'parent_id' => $id('MAINT'), 'label' => 'Work', 'icon' => 'fa-solid fa-wrench', 'menu_type' => 'group', 'sort_order' => 10]);
        $menu(['code' => 'MAINT_REQ', 'parent_id' => $id('MAINT_GRP_WORK'), 'label' => 'Maintenance Request', 'icon' => 'fa-solid fa-triangle-exclamation', 'menu_type' => 'item', 'object_type' => 'form', 'target' => 'v2:maintenance-request', 'sort_order' => 10]);
        $menu(['code' => 'MAINT_WO', 'parent_id' => $id('MAINT_GRP_WORK'), 'label' => 'Maintenance Work Order', 'icon' => 'fa-solid fa-screwdriver-wrench', 'menu_type' => 'item', 'object_type' => 'form', 'target' => 'v2:maintenance-wo', 'sort_order' => 20]);
        $menu(['code' => 'MAINT_MYWORK', 'parent_id' => $id('MAINT_GRP_WORK'), 'label' => 'My Work', 'icon' => 'fa-solid fa-user-gear', 'menu_type' => 'item', 'object_type' => 'form', 'target' => 'v2:maintenance-my-work', 'sort_order' => 30]);
        $menu(['code' => 'MAINT_WORK_RPT', 'parent_id' => $id('MAINT_GRP_WORK'), 'label' => 'Report', 'icon' => 'fa-solid fa-chart-column', 'menu_type' => 'group', 'sort_order' => 90]);
        $menu(['code' => 'MAINT_WO_RPT', 'parent_id' => $id('MAINT_WORK_RPT'), 'label' => 'Maintenance WO Report', 'icon' => 'fa-solid fa-table-list', 'menu_type' => 'item', 'object_type' => 'report', 'target' => 'v2:maintenance-wo-report', 'sort_order' => 10]);
        $menu(['code' => 'MAINT_GRP_PLAN', 'parent_id' => $id('MAINT'), 'label' => 'Planning', 'icon' => 'fa-solid fa-calendar-check', 'menu_type' => 'group', 'sort_order' => 20]);
        $menu(['code' => 'MAINT_PLAN', 'parent_id' => $id('MAINT_GRP_PLAN'), 'label' => 'Preventive Plan', 'icon' => 'fa-solid fa-calendar-check', 'menu_type' => 'item', 'object_type' => 'form', 'target' => 'v2:maintenance-plan', 'sort_order' => 10]);
        $menu(['code' => 'MAINT_GRP_MASTER', 'parent_id' => $id('MAINT'), 'label' => 'Master', 'icon' => 'fa-solid fa-book', 'menu_type' => 'group', 'sort_order' => 30]);
        $menu(['code' => 'MAINT_CODES', 'parent_id' => $id('MAINT_GRP_MASTER'), 'label' => 'Maintenance Codes', 'icon' => 'fa-solid fa-tags', 'menu_type' => 'item', 'object_type' => 'form', 'target' => 'v2:maintenance-codes', 'sort_order' => 10]);
        $menu(['code' => 'MAINT_MACHINE', 'parent_id' => $id('MAINT_GRP_MASTER'), 'label' => 'Master Mesin', 'icon' => 'fa-solid fa-gears', 'menu_type' => 'item', 'object_type' => 'form', 'target' => 'v2:prod-machine', 'sort_order' => 20]);

        // Menu lama Production > Support diarahkan ke layar v2 (tidak dibangun di v1).
        $sys->table('sys_app_menu')->where('code', 'PROD_MTN_SCH')->update(['target' => 'v2:maintenance-wo', 'label' => 'Maintenance Work Order', 'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => $now]);
        $sys->table('sys_app_menu')->where('code', 'PROD_MTN_PREV')->update(['target' => 'v2:maintenance-plan', 'label' => 'Preventive Plan', 'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => $now]);
    }

    // Katalog kode umum mesin cetak & kemasan (gaya SAP PM catalog); English = bahasa dasar, tampilan lewat kamus.
    private function seedCodes(): void
    {
        $codes = [
            'COMPONENT' => ['FEEDER' => 'Feeder', 'DELIVERY' => 'Delivery unit', 'PRINT_UNIT' => 'Printing unit', 'INKING' => 'Inking system',
                'DAMPENING' => 'Dampening system', 'GRIPPER' => 'Gripper', 'CYLINDER' => 'Cylinder', 'ROLLER' => 'Roller',
                'VACUUM_PUMP' => 'Vacuum pump', 'AIR_SYSTEM' => 'Compressed air system', 'DRIVE_MOTOR' => 'Drive motor', 'BELT_CHAIN' => 'Belt / chain',
                'BEARING' => 'Bearing', 'BLADE_KNIFE' => 'Blade / knife / die', 'PNEUMATIC' => 'Pneumatic', 'HYDRAULIC' => 'Hydraulic',
                'ELECTRICAL' => 'Electrical', 'SENSOR' => 'Sensor', 'CONTROL_PANEL' => 'Control panel / PLC', 'HEATER_DRYER' => 'Heater / dryer / UV lamp',
                'GLUE_UNIT' => 'Glue unit', 'LUBRICATION' => 'Lubrication system', 'ENGINE' => 'Engine', 'BRAKE' => 'Brake',
                'TIRE_WHEEL' => 'Tire / wheel', 'MAST_FORK' => 'Mast / fork', 'BATTERY' => 'Battery', 'OTHER' => 'Other'],
            'PROBLEM' => ['NOT_START' => 'Does not start', 'STOPPED' => 'Stopped while running', 'DOUBLE_SHEET' => 'Double sheet',
                'MISFEED' => 'Misfeed', 'PAPER_JAM' => 'Paper jam', 'REGISTER_OFF' => 'Registration off', 'COLOR_VARIATION' => 'Color variation',
                'NOISE' => 'Abnormal noise', 'VIBRATION' => 'Vibration', 'OVERHEAT' => 'Overheating', 'OIL_LEAK' => 'Oil leak',
                'AIR_LEAK' => 'Air leak', 'LOW_PRESSURE' => 'Low pressure', 'ERROR_CODE' => 'Error code on panel', 'WORN' => 'Worn',
                'BROKEN' => 'Broken', 'DULL' => 'Dull (blade/die)', 'OTHER' => 'Other'],
            'CAUSE' => ['WEAR' => 'Normal wear', 'LACK_LUBRICATION' => 'Lack of lubrication', 'CONTAMINATION' => 'Dirt / contamination',
                'MISADJUSTMENT' => 'Misadjustment', 'OPERATOR_ERROR' => 'Operator error', 'ELECTRICAL_FAULT' => 'Electrical fault',
                'MATERIAL_DEFECT' => 'Part / material defect', 'OVERLOAD' => 'Overload', 'AGE_FATIGUE' => 'Age / fatigue',
                'POWER_SUPPLY' => 'Power supply problem', 'UNKNOWN' => 'Unknown', 'OTHER' => 'Other'],
            'ACTION' => ['REPLACE' => 'Replace part', 'REPAIR' => 'Repair', 'ADJUST' => 'Adjust / set', 'CLEAN' => 'Clean',
                'LUBRICATE' => 'Lubricate', 'TIGHTEN' => 'Tighten', 'CALIBRATE' => 'Calibrate', 'RESET' => 'Reset',
                'REWIRE' => 'Rewire', 'SHARPEN' => 'Sharpen', 'TEMP_FIX' => 'Temporary fix', 'OTHER' => 'Other'],
        ];
        $db = DB::connection('tm2prd');
        foreach ($codes as $type => $list) {
            $i = 0;
            foreach ($list as $code => $name) {
                $db->table('mnt_code')->insertOrIgnore(['code_type' => $type, 'code' => $code, 'name' => $name, 'sort_order' => $i += 10,
                    'activate' => 1, 'created_at' => now(), 'created_by' => 'migration']);
            }
        }
    }

    public function down(): void
    {
        $s = Schema::connection('tm2prd');
        foreach (['mnt_log', 'mnt_meter_reading', 'mnt_wo_labor', 'mnt_wo_part', 'mnt_wo_task', 'mnt_wo', 'mnt_request', 'mnt_plan_part', 'mnt_plan_task', 'mnt_plan', 'mnt_code'] as $t) {
            $s->dropIfExists($t);
        }
        $sys = DB::connection('tm2sys');
        $sys->table('sys_app_menu')->whereIn('code', ['MAINT_MACHINE', 'MAINT_CODES', 'MAINT_GRP_MASTER', 'MAINT_PLAN', 'MAINT_GRP_PLAN', 'MAINT_WO_RPT', 'MAINT_WORK_RPT', 'MAINT_MYWORK', 'MAINT_WO', 'MAINT_REQ', 'MAINT_GRP_WORK', 'MAINT'])->delete();
        $sys->table('sys_app_menu')->where('code', 'PROD_MTN_SCH')->update(['target' => 'form_maintenance_schedule.php', 'label' => 'Schedule Maintenance', 'status_code' => 'NOT_BUILT']);
        $sys->table('sys_app_menu')->where('code', 'PROD_MTN_PREV')->update(['target' => 'form_maintenance_preventive.php', 'label' => 'Preventive Maintenance', 'status_code' => 'NOT_BUILT']);
        $sys->table('sys_constant')->where('constant_key', 'MNT_LABOR_RATE_HOUR')->delete();
        $mod = $sys->table('sec_module')->where('module_code', 'MAINT')->value('id_module');
        $sys->table('sec_group_permission')->where('module_id', $mod)->delete();
        $groups = $sys->table('sec_group')->whereIn('group_code', ['MAINT_TECH', 'MAINT_HEAD'])->pluck('id_group');
        $sys->table('sec_group_permission')->whereIn('group_id', $groups)->delete();
        $sys->table('sec_group')->whereIn('id_group', $groups)->delete();
        $sys->table('sec_module')->where('id_module', $mod)->delete();
    }
};
