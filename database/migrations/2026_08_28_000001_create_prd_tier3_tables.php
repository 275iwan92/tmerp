<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// production_scheduling_tier3.md -- skema penuh tm2prd (8 tabel + 2 view).
// FK dalam-DB (semua tabel ini sama-sama tm2prd) dibuat FISIK sesuai CLAUDE.md §2.
// FK lintas-DB (tmmst.prod_machine, tmmst.mat_raw, tm2hrm/tm2mst.emp_employee,
// tm2sdi.co_header, tm2pur.po_header, dst) LOGICAL saja, tanpa constraint.
//
// Satu penyimpangan sadar dari teks Tier 3 §4: UNIQUE(operation_id, crew_role)
// yang didokumentasikan TIDAK diimplementasikan sebagai unique key -- literal
// begitu ia akan memblokir co-operator kedua/ketiga pada operation yang sama
// (crew_role sama-sama CO_OPERATOR), padahal Tier 3 sendiri menyatakan
// "co-operator boleh banyak, dicek di service bukan unique key ini". Yang
// dipasang di sini cuma guard yang benar-benar konsisten dgn niatnya: UNIQUE
// (operation_id, employee_id) dari §10 migration note (cegah 1 orang dobel-
// catat di operation yang sama). "Maksimal 1 OPERATOR_UTAMA per operation"
// tetap wajib dicek di service layer (PrdCrewAssignment), bukan di DB.
return new class extends Migration
{
    public function up(): void
    {
        $prd = Schema::connection('tm2prd');

        $prd->create('prd_wo', function (Blueprint $table) {
            $table->id('wo_id');
            $table->string('wo_no', 30)->unique('uk_prd_wo_no')->comment('Nomor dokumen, sequence per tahun');
            $table->enum('wo_type', ['MTO', 'MTS']);
            $table->enum('source_type', ['CO_CONFIRMED', 'MTS_FORECAST']);
            $table->unsignedBigInteger('source_co_id')->nullable()->comment('LOGICAL FK tm2sdi.co_order, wajib bila MTO');
            $table->unsignedBigInteger('source_forecast_id')->nullable()->comment('LOGICAL FK prd_mts_forecast, wajib bila MTS');
            $table->unsignedBigInteger('product_id')->comment('LOGICAL FK tm2mst.prod_product');
            $table->unsignedBigInteger('parts_id')->nullable()->comment('LOGICAL FK tm2mst.prod_part, NULL bila single-part');
            $table->decimal('qty', 18, 4);
            $table->dateTime('due_date')->comment('Dari CO (MTO) atau replenishment cycle (MTS)');
            $table->dateTime('material_needed_by')->nullable()->comment('Hasil backward schedule -- Tier2 §4.1');
            $table->enum('wo_status', ['DRAFT', 'SIMULATED', 'SCHEDULED', 'IN_PROGRESS', 'ON_HOLD', 'COMPLETED', 'CANCELLED'])->default('DRAFT');
            $table->enum('schedule_layer', ['SIMULATED', 'COMMITTED'])->default('SIMULATED');
            $table->boolean('is_hot_job')->default(false);
            $table->enum('hot_job_source', ['DIREKSI', 'CUSTOMER', 'INTERNAL_REWORK'])->nullable();
            $table->unsignedBigInteger('hot_job_customer_id')->nullable()->comment('LOGICAL FK tm2mst.cust_customer, isi bila hot_job_source=CUSTOMER');
            $table->dateTime('confirmed_at')->nullable()->comment('FIFO tie-break Composite Priority Score (Tier2 §4.2 Lapis 4)');
            $table->string('cancel_reason', 255)->nullable();
            $table->string('created_by', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('updated_by', 50)->nullable();
            $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
            $table->index(['wo_status', 'schedule_layer'], 'idx_prd_wo_status');
            $table->index('source_co_id', 'idx_prd_wo_source_co');
            $table->index('due_date', 'idx_prd_wo_due');
        });

        $prd->create('prd_wo_material', function (Blueprint $table) {
            $table->id('detail_id');
            $table->foreignId('wo_id')->constrained('prd_wo', 'wo_id');
            $table->unsignedInteger('seq_no')->default(0);
            $table->unsignedBigInteger('material_id')->comment('LOGICAL FK tm2mst.mat_raw -- kebutuhan ASLI (sebelum substitusi)');
            $table->decimal('qty_required', 18, 4)->comment('Kontrak Inventory: Stock Out tarik dari sini');
            $table->decimal('qty_standar', 18, 4)->comment('Kebutuhan teoritis dari BOM');
            $table->boolean('is_substituted')->default(false);
            $table->unsignedBigInteger('substitute_material_id')->nullable()->comment('LOGICAL FK tm2mst.mat_raw, hasil §5A.10 Tier1');
            $table->enum('substitute_reason', ['DEAD_STOCK', 'SLOW_MOVING', 'OTHER'])->nullable();
            $table->string('substitute_approved_by', 50)->nullable()->comment('Wajib minimal Manager Operasional (Tier1 §9.4)');
            $table->dateTime('substitute_approved_at')->nullable();
            $table->decimal('cutting_yield_waste_qty', 18, 4)->nullable()->comment('Sisa potong, masuk rm_stockcard via Inventory');
            $table->dateTime('material_needed_by')->nullable();
            $table->string('created_by', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('updated_by', 50)->nullable();
            $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
            $table->index('material_id', 'idx_prd_wo_material_item');
        });

        $prd->create('prd_wo_operation', function (Blueprint $table) {
            $table->id('operation_id');
            $table->foreignId('wo_id')->constrained('prd_wo', 'wo_id');
            $table->unsignedInteger('seq_no')->comment('Urutan routing, dari tm2mst.prod_parts_process');
            $table->string('process_name', 100);
            $table->enum('resource_type', ['MACHINE', 'MANUAL']);
            $table->unsignedBigInteger('machine_id')->nullable()->comment('LOGICAL FK tm2mst.prod_machine, wajib bila resource_type=MACHINE');
            $table->decimal('qty', 18, 4);
            $table->dateTime('target_start')->nullable()->comment('Hasil Backward Schedule (Tier2 §4.1)');
            $table->dateTime('target_finish')->nullable();
            $table->dateTime('actual_start')->nullable();
            $table->dateTime('actual_finish')->nullable();
            $table->enum('task_status', ['PENDING', 'ASSIGNED', 'RUNNING', 'DONE', 'BREAKDOWN', 'SUBCONTRACT', 'QC_HOLD', 'QC_PASSED', 'QC_REJECTED'])->default('PENDING');
            $table->boolean('qc_gate_required')->default(false)->comment('Titik kritis routing (Tier1 F5)');
            $table->string('rejection_reason', 255)->nullable();
            $table->boolean('is_hot_job')->default(false)->comment('Salinan dari WO, bisa beda kalau hot job cuma sebagian task');
            $table->unsignedBigInteger('batched_with_operation_id')->nullable()->comment('Self-FK -- Batching Rule (Tier1 §5A.7)');
            $table->decimal('rate_band_value', 18, 4)->nullable()->comment('Snapshot rate dipakai saat backward schedule (audit)');
            $table->decimal('critical_ratio', 8, 4)->nullable()->comment('Computed, refresh tiap re-run PDR (Tier2 §4.2)');
            $table->string('created_by', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('updated_by', 50)->nullable();
            $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
            $table->index(['wo_id', 'seq_no'], 'idx_prd_wo_op_wo');
            $table->index(['machine_id', 'target_start', 'target_finish'], 'idx_prd_wo_op_machine');
            $table->index('task_status', 'idx_prd_wo_op_status');
            $table->foreign('batched_with_operation_id', 'fk_prd_wo_op_batch')->references('operation_id')->on('prd_wo_operation');
        });

        $prd->create('prd_crew_assignment', function (Blueprint $table) {
            $table->id('assignment_id');
            $table->foreignId('operation_id')->constrained('prd_wo_operation', 'operation_id');
            $table->unsignedBigInteger('employee_id')->comment('LOGICAL FK tm2mst.emp_employee');
            $table->enum('crew_role', ['OPERATOR_UTAMA', 'CO_OPERATOR']);
            $table->string('created_by', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            // Tier3 §10 -- cegah 1 orang dobel-catat (baik sbg utama maupun co-op) di
            // operation yang sama. "Maks 1 OPERATOR_UTAMA per operation" dicek di service.
            $table->unique(['operation_id', 'employee_id'], 'uk_prd_crew_op_employee');
            $table->index('employee_id', 'idx_prd_crew_employee');
        });

        $prd->create('prd_breakdown_event', function (Blueprint $table) {
            $table->id('event_id');
            $table->foreignId('operation_id')->constrained('prd_wo_operation', 'operation_id');
            $table->unsignedBigInteger('machine_id')->comment('LOGICAL FK tm2mst.prod_machine');
            $table->string('reported_by', 50);
            $table->dateTime('reported_at');
            $table->unsignedInteger('downtime_estimate_minutes')->nullable();
            $table->enum('reroute_decision', ['MACHINE_ALT', 'SUBCONTRACT'])->nullable();
            $table->unsignedBigInteger('alt_machine_id')->nullable()->comment('Bila reroute_decision=MACHINE_ALT');
            $table->unsignedBigInteger('subcontract_vendor_id')->nullable()->comment('LOGICAL FK tm2mst.sup_supplier, bila SUBCONTRACT');
            $table->unsignedBigInteger('subcontract_po_id')->nullable()->comment('LOGICAL FK tm2pur.po_header (po_type=SUBCONTRACT)');
            $table->dateTime('subcontract_due_out')->nullable();
            $table->dateTime('subcontract_due_return')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('machine_id', 'idx_prd_breakdown_machine');
        });

        $prd->create('prd_scheduling_rule', function (Blueprint $table) {
            $table->id('rule_id');
            $table->enum('rule_type', ['BATCHING', 'SEQUENCING']);
            $table->string('rule_name', 100)->comment('Label mudah dibaca, mis. "Gabung dieline sama"');
            $table->unsignedBigInteger('applies_to_machine_id')->nullable()->comment('LOGICAL FK tm2mst.prod_machine -- NULL = semua mesin sejenis applies_to_process');
            $table->string('applies_to_process', 100)->nullable()->comment('Nama proses, mis. CETAK, PLONG');
            $table->string('condition_field', 50)->comment('Nama atribut task/produk yang dibandingkan');
            $table->enum('condition_operator', ['EQUALS', 'SORT_ASC', 'SORT_DESC']);
            $table->enum('rule_status', ['DRAFT', 'ACTIVE', 'INACTIVE'])->default('DRAFT');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('created_by', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('approved_by', 50)->nullable()->comment('Wajib diisi utk rule_status=ACTIVE');
            $table->dateTime('approved_at')->nullable();
            $table->index(['applies_to_machine_id', 'rule_status'], 'idx_prd_rule_machine');
            $table->index(['rule_type', 'rule_status'], 'idx_prd_rule_type');
        });

        $prd->create('prd_decision_feedback_log', function (Blueprint $table) {
            $table->id('log_id');
            $table->enum('event_type', ['INTERRUPTION', 'SIMULATION_ADJUST']);
            $table->foreignId('wo_id')->nullable()->constrained('prd_wo', 'wo_id');
            $table->unsignedBigInteger('context_customer_id')->nullable()->comment('LOGICAL FK tm2mst.cust_customer');
            $table->unsignedBigInteger('context_machine_id')->nullable();
            $table->json('options_presented')->nullable()->comment('Snapshot opsi yang ditawarkan sistem');
            $table->string('option_chosen', 50)->nullable();
            $table->string('chosen_by', 50);
            $table->dateTime('chosen_at');
            $table->index('context_customer_id', 'idx_prd_feedback_customer');
        });

        $prd->create('prd_mts_forecast', function (Blueprint $table) {
            $table->id('forecast_id');
            $table->unsignedBigInteger('product_id')->comment('LOGICAL FK tm2mst.prod_product');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('forecast_qty', 18, 4);
            $table->decimal('actual_qty', 18, 4)->nullable()->comment('Terisi setelah periode berjalan');
            $table->decimal('accuracy_pct', 6, 4)->nullable()->comment('1-ABS(forecast-actual)/forecast; target 85-90%');
            $table->string('input_by', 50)->comment('Direksi/Top Management (Tier1 §5A.8)');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['product_id', 'period_start'], 'idx_prd_forecast_product_period');
        });

        DB::connection('tm2prd')->statement(<<<'SQL'
            CREATE OR REPLACE VIEW `v_prd_exception_dashboard` AS
            SELECT
                o.operation_id, o.wo_id, o.process_name, o.machine_id, o.target_start, o.target_finish,
                o.critical_ratio, o.task_status,
                CASE WHEN o.task_status = 'PENDING' AND o.target_start < NOW() THEN 1 ELSE 0 END AS is_unscheduled_risk,
                CASE WHEN o.critical_ratio < 1 THEN 1 ELSE 0 END AS is_at_risk
            FROM prd_wo_operation o
            WHERE o.task_status NOT IN ('DONE','QC_PASSED')
        SQL);

        DB::connection('tm2prd')->statement(<<<'SQL'
            CREATE OR REPLACE VIEW `v_prd_customer_interruption_profile` AS
            SELECT
                context_customer_id,
                COUNT(*) AS total_interruption,
                option_chosen,
                COUNT(*) AS count_per_option
            FROM prd_decision_feedback_log
            WHERE event_type = 'INTERRUPTION' AND context_customer_id IS NOT NULL
            GROUP BY context_customer_id, option_chosen
        SQL);
    }

    public function down(): void
    {
        DB::connection('tm2prd')->statement('DROP VIEW IF EXISTS `v_prd_customer_interruption_profile`');
        DB::connection('tm2prd')->statement('DROP VIEW IF EXISTS `v_prd_exception_dashboard`');

        $prd = Schema::connection('tm2prd');
        $prd->dropIfExists('prd_mts_forecast');
        $prd->dropIfExists('prd_decision_feedback_log');
        $prd->dropIfExists('prd_scheduling_rule');
        $prd->dropIfExists('prd_breakdown_event');
        $prd->dropIfExists('prd_crew_assignment');
        $prd->dropIfExists('prd_wo_operation');
        $prd->dropIfExists('prd_wo_material');
        $prd->dropIfExists('prd_wo');
    }
};
