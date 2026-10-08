<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PPIC Scheduling (docs: Help "Technical Plan: PPIC ..."): Work Order operations learn their process, planned minutes, draft plan
// and gates; candidate machines per operation; plan runs; decision log; operator time log; tooling master; wait rules; customer tier.
// Relations across databases are logical only. Run on the dummy databases first: php artisan migrate --env=mockup --path=database/migrations/<file>
return new class extends Migration
{
    private function addColumns(string $conn, string $table, array $columns): void
    {
        $s = Schema::connection($conn);
        foreach ($columns as $name => $define) {
            if (! $s->hasColumn($table, $name)) {
                $s->table($table, fn (Blueprint $t) => $define($t, $name));
            }
        }
    }

    public function up(): void
    {
        $prd = Schema::connection('tm2prd');
        $mst = Schema::connection('tm2mst');

        // Work Order: frozen copy of what scheduling must know (from ProDev layout / part). NULL = unknown, eligibility then skips that check.
        $this->addColumns('tm2prd', 'prd_wo', [
            'ups_per_sheet' => fn ($t, $n) => $t->unsignedSmallInteger($n)->nullable()->after('qty'),
            'sheet_length_mm' => fn ($t, $n) => $t->decimal($n, 8, 2)->nullable()->after('ups_per_sheet'),
            'sheet_width_mm' => fn ($t, $n) => $t->decimal($n, 8, 2)->nullable()->after('sheet_length_mm'),
            'board_gsm' => fn ($t, $n) => $t->unsignedSmallInteger($n)->nullable()->after('sheet_width_mm'),
            'colour_count' => fn ($t, $n) => $t->unsignedTinyInteger($n)->nullable()->after('board_gsm'),
            'dieline_ref' => fn ($t, $n) => $t->string($n, 60)->nullable()->after('colour_count'),
            'customer_tier' => fn ($t, $n) => $t->unsignedTinyInteger($n)->nullable()->after('dieline_ref'),
            'layout_json' => fn ($t, $n) => $t->json($n)->nullable()->after('customer_tier'),
        ]);

        $this->addColumns('tm2prd', 'prd_wo_operation', [
            'process_id' => fn ($t, $n) => $t->unsignedBigInteger($n)->nullable()->after('process_name'),
            'qty_uom' => fn ($t, $n) => $t->string($n, 8)->nullable()->after('qty'),
            'qty_out' => fn ($t, $n) => $t->decimal($n, 14, 2)->nullable()->after('qty_uom'),
            'setup_base_min' => fn ($t, $n) => $t->unsignedSmallInteger($n)->nullable(),
            'setup_change_min' => fn ($t, $n) => $t->unsignedSmallInteger($n)->nullable(),
            'setup_tool_min' => fn ($t, $n) => $t->unsignedSmallInteger($n)->nullable(),
            'run_minutes' => fn ($t, $n) => $t->unsignedInteger($n)->nullable(),
            'plan_status' => fn ($t, $n) => $t->string($n, 12)->default('NONE'),     // NONE | DRAFT | CONFIRMED
            'draft_machine_id' => fn ($t, $n) => $t->unsignedBigInteger($n)->nullable(),
            'draft_start' => fn ($t, $n) => $t->dateTime($n)->nullable(),
            'draft_finish' => fn ($t, $n) => $t->dateTime($n)->nullable(),
            'draft_run_id' => fn ($t, $n) => $t->unsignedBigInteger($n)->nullable(),
            'is_locked' => fn ($t, $n) => $t->boolean($n)->default(false),
            'lock_reason' => fn ($t, $n) => $t->string($n, 80)->nullable(),
            'planned_by' => fn ($t, $n) => $t->string($n, 10)->nullable(),            // ENGINE | OFFICER
            'priority_note' => fn ($t, $n) => $t->string($n, 120)->nullable(),
            'gate_material' => fn ($t, $n) => $t->string($n, 8)->nullable(),          // READY | WAIT | BLOCK
            'gate_tooling' => fn ($t, $n) => $t->string($n, 8)->nullable(),
            'gate_artwork' => fn ($t, $n) => $t->string($n, 8)->nullable(),
            'gate_note' => fn ($t, $n) => $t->string($n, 190)->nullable(),
            'material_ready_at' => fn ($t, $n) => $t->dateTime($n)->nullable(),
            'actual_setup_min' => fn ($t, $n) => $t->unsignedInteger($n)->nullable(),
        ]);
        try {
            $prd->table('prd_wo_operation', fn (Blueprint $t) => $t->index(['plan_status', 'draft_machine_id'], 'idx_op_plan'));
        } catch (Throwable) {
        }

        if (! $prd->hasTable('prd_wo_operation_machine')) {
            $prd->create('prd_wo_operation_machine', function (Blueprint $t) {
                $t->id('id_op_machine');
                $t->unsignedBigInteger('operation_id')->index();
                $t->unsignedBigInteger('machine_id');                      // logical -> tm2mst.prod_machine
                $t->boolean('eligible')->default(true);
                $t->string('reason', 190)->nullable();                     // why not eligible
                $t->unsignedSmallInteger('preference_rank')->nullable();
                $t->decimal('target_qty_per_hour', 12, 2)->nullable();
                $t->unsignedSmallInteger('setup_minutes')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->unique(['operation_id', 'machine_id'], 'uq_op_machine');
            });
        }

        if (! $prd->hasTable('prd_plan_run')) {
            $prd->create('prd_plan_run', function (Blueprint $t) {
                $t->id('run_id');
                $t->string('trigger_kind', 20);                            // DAILY | SHIFT | EVENT | MANUAL
                $t->string('status', 12)->default('DRAFT');                // DRAFT | CONFIRMED | DISCARDED
                $t->dateTime('plan_from');
                $t->unsignedInteger('operations_planned')->default(0);
                $t->unsignedInteger('exceptions_found')->default(0);
                $t->unsignedInteger('duration_ms')->default(0);
                $t->json('summary')->nullable();
                $t->string('started_by', 50)->nullable();                  // login or ENGINE
                $t->dateTime('confirmed_at')->nullable();
                $t->string('confirmed_by', 50)->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        if (! $prd->hasTable('prd_plan_decision')) {
            $prd->create('prd_plan_decision', function (Blueprint $t) {
                $t->id('decision_id');
                $t->unsignedBigInteger('run_id')->nullable()->index();
                $t->unsignedBigInteger('operation_id')->nullable()->index();
                $t->string('actor', 50);                                   // login or ENGINE
                $t->string('event', 30);                                   // AUTO_PLAN | CONFIRM | PRIORITY | MOVE | LOCK_BREAK | DISCARD
                $t->string('old_value', 190)->nullable();
                $t->string('new_value', 190)->nullable();
                $t->string('reason', 190)->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        if (! $prd->hasTable('prd_operation_time_log')) {
            $prd->create('prd_operation_time_log', function (Blueprint $t) {
                $t->id('log_id');
                $t->unsignedBigInteger('operation_id')->index();
                $t->unsignedBigInteger('machine_id')->nullable();
                $t->string('operator', 50)->nullable();
                $t->string('kind', 10);                                    // SETUP | RUN | PAUSE
                $t->string('pause_reason', 30)->nullable();
                $t->string('change_kind', 20)->nullable();
                $t->dateTime('started_at');
                $t->dateTime('ended_at')->nullable();
                $t->unsignedInteger('minutes')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        // Master side
        $this->addColumns('tm2mst', 'cust_customer', [
            'customer_tier' => fn ($t, $n) => $t->unsignedTinyInteger($n)->default(2),   // 1 key, 2 normal, 3 low; kept by Sales
        ]);

        if (! $mst->hasTable('prod_tooling_asset')) {
            $mst->create('prod_tooling_asset', function (Blueprint $t) {
                $t->id('id_tooling_asset');
                $t->string('tooling_code', 40)->unique();
                $t->string('tooling_type', 20);                            // PLATE_SET | CUTTING_DIE | EMBOSS_DIE | FOIL_DIE
                $t->unsignedBigInteger('product_id')->nullable();          // logical -> prod_product
                $t->string('dieline_ref', 60)->nullable();
                $t->string('size_note', 80)->nullable();
                $t->string('location', 60)->nullable();
                $t->string('status', 14)->default('NOT_MADE');             // NOT_MADE | IN_MAKING | READY | IN_USE | NEEDS_REPAIR | RETIRED
                $t->unsignedInteger('impressions_planned')->nullable();
                $t->unsignedInteger('impressions_used')->default(0);
                $t->unsignedSmallInteger('setup_first_min')->nullable();
                $t->unsignedSmallInteger('setup_repeat_min')->nullable();
                $t->boolean('activate')->default(true);
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 50)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 50)->nullable();
            });
        }

        if (! $mst->hasTable('prod_wait_rule')) {
            $mst->create('prod_wait_rule', function (Blueprint $t) {
                $t->id('id_wait_rule');
                $t->unsignedBigInteger('after_process_id')->nullable();   // logical -> prod_process
                $t->string('after_process_name', 60)->nullable();
                $t->string('kind', 40);                                    // e.g. CONVENTIONAL_INK, UV_INK, AQUEOUS, SPOT_UV
                $t->unsignedInteger('wait_minutes')->default(0);
                $t->boolean('activate')->default(true);
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 50)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 50)->nullable();
            });
        }

        // Status of learnable values (Assumed -> Learning -> Calibrated) on the machine changeover table.
        $this->addColumns('tm2mst', 'prod_machine_changeover', [
            'value_status' => fn ($t, $n) => $t->string($n, 12)->default('ASSUMED'),
            'sample_count' => fn ($t, $n) => $t->unsignedInteger($n)->default(0),
            'frozen' => fn ($t, $n) => $t->boolean($n)->default(false),
        ]);
    }

    public function down(): void
    {
        // Intentionally additive only: dropping would lose Work Order planning history.
    }
};
