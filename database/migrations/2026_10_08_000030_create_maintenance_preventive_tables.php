<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Maintenance Fase 2 (Preventive) di tm2mnt / ddtmnt: plan, Maintenance Order (PMO, BMO, IMO berbagi satu tabel), meter, Daily Check,
// dan Request (dibuat oleh Daily Check; layar Request menyusul di Fase 3). Relasi ke tm2mst / tm2sys logis. Jalankan dengan --env=mockup.
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
        $s = Schema::connection('tm2mnt');

        $s->create('mnt_plan', function (Blueprint $t) {
            $t->id('id_plan');
            $t->string('plan_code', 40)->unique();
            $t->foreignId('asset_id')->constrained('mnt_asset', 'id_asset');
            $t->string('plan_name', 150);
            $t->string('order_type', 20)->default('PREVENTIVE');   // PREVENTIVE | INSPECTION
            $t->string('trigger_mode', 20)->default('TIME');       // TIME | METER | TIME_OR_METER
            $t->unsignedInteger('interval_value')->nullable();
            $t->string('interval_unit', 10)->nullable();            // DAY | WEEK | MONTH | YEAR
            $t->string('meter_type', 20)->nullable();               // RUN_HOURS | IMPRESSIONS | ODOMETER_KM
            $t->decimal('meter_interval', 14, 2)->nullable();
            $t->string('schedule_mode', 10)->default('FLOATING');   // FLOATING | FIXED
            $t->unsignedInteger('lead_days')->default(3);
            $t->unsignedInteger('est_minutes')->nullable();
            $t->boolean('needs_stop')->default(true);
            $t->date('certificate_valid_until')->nullable();        // inspeksi wajib (kompresor, forklift, kendaraan)
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
            $t->unsignedInteger('seq_no');
            $t->string('task_text', 255);
            $t->string('task_kind', 12)->default('INSPECT');        // INSPECT | CLEAN | LUBRICATE | ADJUST | REPLACE | MEASURE
            $t->string('measure_uom', 20)->nullable();
            $t->decimal('min_value', 14, 3)->nullable();
            $t->decimal('max_value', 14, 3)->nullable();
            $t->boolean('is_required')->default(true);
            $this->audit($t);
        });

        $s->create('mnt_plan_part', function (Blueprint $t) {
            $t->id('id_plan_part');
            $t->foreignId('plan_id')->constrained('mnt_plan', 'id_plan')->cascadeOnDelete();
            $t->unsignedBigInteger('material_id');                  // logis tm2mst.mat_raw
            $t->decimal('qty', 14, 3)->default(1);
            $t->string('uom_code', 20)->nullable();
            $this->audit($t);
        });

        $s->create('mnt_meter_reading', function (Blueprint $t) {
            $t->id('id_meter_reading');
            $t->foreignId('asset_id')->constrained('mnt_asset', 'id_asset');
            $t->string('meter_type', 20);
            $t->dateTime('reading_at');
            $t->decimal('reading_value', 14, 2);
            $t->string('source', 12)->default('MANUAL');            // MANUAL | PRODUCTION
            $t->string('note', 150)->nullable();
            $this->audit($t);
            $t->index(['asset_id', 'meter_type', 'reading_at']);
        });

        $s->create('mnt_request', function (Blueprint $t) {
            $t->id('id_request');
            $t->string('doc_no', 40)->unique();                     // MR-yymm-nnnn
            $t->dateTime('request_date');
            $t->foreignId('asset_id')->constrained('mnt_asset', 'id_asset');
            $t->string('request_kind', 15)->default('CORRECTIVE');  // BREAKDOWN | CORRECTIVE | SAFETY | IMPROVEMENT
            $t->string('priority', 2)->default('P3');               // P1 | P2 | P3 | P4
            $t->boolean('asset_stopped')->default(false);
            $t->dateTime('stopped_at')->nullable();
            $t->text('symptom');
            $t->string('source_type', 12)->default('MANUAL');       // MANUAL | DAILY_CHECK
            $t->unsignedBigInteger('daily_check_line_id')->nullable();
            $t->unsignedBigInteger('breakdown_event_id')->nullable(); // logis tm2prd.prd_breakdown_event
            $t->string('status', 10)->default('OPEN');              // OPEN | ACCEPTED | REJECTED
            $t->string('reject_reason', 255)->nullable();
            $t->unsignedBigInteger('order_id')->nullable();
            $this->audit($t);
            $t->index(['status', 'asset_id']);
        });

        $s->create('mnt_order', function (Blueprint $t) {
            $t->id('id_order');
            $t->string('doc_no', 40)->nullable()->unique();         // PMO- / BMO- / IMO-yymm-nnnn, lahir saat Confirm
            $t->string('order_type', 15);                           // PREVENTIVE | INSPECTION | BREAKDOWN | CORRECTIVE | IMPROVEMENT
            $t->string('source', 10)->default('MANUAL');            // PLAN | REQUEST | MANUAL
            $t->unsignedBigInteger('plan_id')->nullable()->index();
            $t->unsignedBigInteger('request_id')->nullable()->index();
            $t->foreignId('asset_id')->constrained('mnt_asset', 'id_asset');
            $t->string('component_code', 40)->nullable();
            $t->string('priority', 2)->default('P3');
            $t->string('description', 255);
            $t->boolean('needs_stop')->default(true);
            $t->dateTime('planned_start')->nullable();
            $t->dateTime('planned_end')->nullable();
            $t->unsignedInteger('est_minutes')->nullable();
            $t->dateTime('actual_start')->nullable();
            $t->dateTime('actual_end')->nullable();
            $t->unsignedBigInteger('downtime_id')->nullable();      // logis tm2mst.prod_machine_downtime (aset mesin saja)
            $t->decimal('meter_at_done', 14, 2)->nullable();
            $t->unsignedBigInteger('assigned_to')->nullable();      // logis tm2mst.emp_employee
            $t->unsignedBigInteger('vendor_id')->nullable();        // logis tm2mst.sup_supplier
            $t->string('problem_code', 40)->nullable();
            $t->string('cause_code', 40)->nullable();
            $t->string('action_code', 40)->nullable();
            $t->text('findings')->nullable();
            $t->string('work_status', 14)->default('DRAFT');        // DRAFT | SCHEDULED | IN_PROGRESS | WAITING_PART | DONE
            $t->boolean('stat_confirm')->default(false);
            $t->boolean('stat_cancel')->default(false);
            $t->boolean('stat_close')->default(false);
            $t->string('cancel_reason', 255)->nullable();
            $t->string('closed_by', 50)->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->decimal('cost_part', 16, 2)->default(0);
            $t->decimal('cost_labor', 16, 2)->default(0);
            $t->decimal('cost_vendor', 16, 2)->default(0);
            $this->audit($t);
            $t->index(['order_type', 'work_status']);
        });

        $s->create('mnt_order_task', function (Blueprint $t) {
            $t->id('id_order_task');
            $t->foreignId('order_id')->constrained('mnt_order', 'id_order')->cascadeOnDelete();
            $t->unsignedInteger('seq_no');
            $t->string('task_text', 255);
            $t->string('task_kind', 12)->default('INSPECT');
            $t->string('measure_uom', 20)->nullable();
            $t->decimal('min_value', 14, 3)->nullable();
            $t->decimal('max_value', 14, 3)->nullable();
            $t->boolean('is_required')->default(true);
            $t->string('result', 8)->nullable();                    // OK | NOT_OK | NA
            $t->decimal('measured_value', 14, 3)->nullable();
            $t->string('note', 255)->nullable();
            $t->string('done_by', 50)->nullable();
            $t->timestamp('done_at')->nullable();
            $this->audit($t);
        });

        $s->create('mnt_order_part', function (Blueprint $t) {
            $t->id('id_order_part');
            $t->foreignId('order_id')->constrained('mnt_order', 'id_order')->cascadeOnDelete();
            $t->unsignedBigInteger('material_id');
            $t->decimal('qty_plan', 14, 3)->default(1);
            $t->decimal('qty_used', 14, 3)->nullable();
            $t->string('uom_code', 20)->nullable();
            $t->decimal('unit_cost', 16, 4)->default(0);            // snapshot harga rata-rata saat part ditambah
            $this->audit($t);
        });

        $s->create('mnt_order_labor', function (Blueprint $t) {
            $t->id('id_order_labor');
            $t->foreignId('order_id')->constrained('mnt_order', 'id_order')->cascadeOnDelete();
            $t->unsignedBigInteger('employee_id');                  // logis tm2mst.emp_employee
            $t->date('work_date');
            $t->unsignedInteger('minutes');
            $t->decimal('rate_snapshot', 14, 2)->default(0);        // tarif per jam saat dicatat (konstanta MNT_LABOR_RATE_HOUR)
            $t->string('note', 255)->nullable();
            $this->audit($t);
        });

        $s->create('mnt_daily_check', function (Blueprint $t) {
            $t->id('id_daily_check');
            $t->string('doc_no', 40)->unique();                     // DC-yymm-nnnn
            $t->foreignId('asset_id')->constrained('mnt_asset', 'id_asset');
            $t->date('check_date');
            $t->string('shift_code', 10);
            $t->string('checked_by', 50)->nullable();
            $t->string('status', 10)->default('OPEN');              // OPEN | SUBMITTED
            $t->timestamp('submitted_at')->nullable();
            $this->audit($t);
            $t->unique(['asset_id', 'check_date', 'shift_code']);
        });

        $s->create('mnt_daily_check_line', function (Blueprint $t) {
            $t->id('id_daily_check_line');
            $t->foreignId('daily_check_id')->constrained('mnt_daily_check', 'id_daily_check')->cascadeOnDelete();
            $t->unsignedBigInteger('watch_point_id')->nullable();
            $t->string('component_code', 40)->nullable();
            $t->string('check_text', 200);                          // salinan, supaya riwayat tetap bila watch point diubah
            $t->decimal('normal_min', 14, 3)->nullable();
            $t->decimal('normal_max', 14, 3)->nullable();
            $t->string('measure_uom', 20)->nullable();
            $t->string('result', 8)->nullable();                    // OK | NOT_OK | NA
            $t->decimal('measured_value', 14, 3)->nullable();
            $t->string('note', 255)->nullable();
            $t->unsignedBigInteger('request_id')->nullable();
            $this->audit($t);
        });
    }

    public function down(): void
    {
        $s = Schema::connection('tm2mnt');
        foreach (['mnt_daily_check_line', 'mnt_daily_check', 'mnt_order_labor', 'mnt_order_part', 'mnt_order_task', 'mnt_order', 'mnt_request', 'mnt_meter_reading', 'mnt_plan_part', 'mnt_plan_task', 'mnt_plan'] as $t) {
            $s->dropIfExists($t);
        }
    }
};
