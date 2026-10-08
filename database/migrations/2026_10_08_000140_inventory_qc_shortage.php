<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Inventory & Warehouse v2.01, jalur "QC, URGENT, kurang kirim" (docs/md_for_systems/inventorywarehouse_v201_scope.md bagian 7):
//   tm2inv: rencana sampling (inv_qc_sampling_plan + inv_qc_intensity_rule), kelas risiko (inv_qc_risk_class), keputusan QC (inv_qc_decision),
//           first article (inv_qc_first_article), penahanan WO (inv_qc_hold), klaim supplier (inv_supplier_claim), permintaan kirim kekurangan (inv_shortage_request);
//   tm2pur: pv_line mendapat persetujuan URGENT bahan FSC / food grade + sertifikasi batch; po_line tanpa kolom baru (status CLOSED_SHORT = nilai baru c_line_status).
// Nilai awal pengaturan (tabel sampel ANSI/ASQ Z1.4 Level II yang disederhanakan, aturan perpindahan intensitas) hanya nilai awal: semuanya
// diubah user lewat layar Sampling Plan / Sampling Rules / Risk Class. Relasi lintas database = logis (tanpa FK). Jalankan di ddt* dulu.
return new class extends Migration
{
    private const BY = 'claude (inventory v2.01 qc)';

    // [batas bawah lot, batas atas lot, ukuran sampel normal, ringan (Z1.4 reduced)]; ketat = 1,5 x normal dibulatkan ke atas (usulan, bisa diubah).
    private const PLAN = [
        [1, 8, 2, 2], [9, 15, 3, 2], [16, 25, 5, 2], [26, 50, 8, 3], [51, 90, 13, 5], [91, 150, 20, 8], [151, 280, 32, 13], [281, 500, 50, 20],
        [501, 1200, 80, 32], [1201, 3200, 125, 50], [3201, 10000, 200, 80], [10001, 35000, 315, 125], [35001, 150000, 500, 200], [150001, 500000, 800, 315], [500001, null, 1250, 500],
    ];

    // kelas => [intensitas awal, AQL %, lulus berapa lot berturut-turut jadi REDUCED, lulus berapa lot sesudah reject kembali dari TIGHTENED, catatan]
    private const RULES = [
        'A' => ['TIGHTENED', 1.00, 10, 5, 'Food grade, FSC / PEFC claim, hazardous material.'],
        'B' => ['NORMAL', 2.50, 10, 5, 'Paper, board, film, corrugated, glue from a known supplier.'],
        'C' => ['SKIP', 4.00, 10, 5, 'Small auxiliary materials; the supplier certificate is enough.'],
    ];

    public function up(): void
    {
        $inv = Schema::connection('tm2inv');

        if (! $inv->hasTable('inv_qc_sampling_plan')) {
            $inv->create('inv_qc_sampling_plan', function (Blueprint $t) {
                $t->id('id_plan');
                $t->string('intensity', 10)->comment('TIGHTENED / NORMAL / REDUCED');
                $t->decimal('lot_from', 20, 4)->comment('lot quantity from (inclusive)');
                $t->decimal('lot_to', 20, 4)->nullable()->comment('lot quantity to (inclusive); empty = no upper limit');
                $t->unsignedInteger('sample_size');
                $t->string('note', 255)->nullable();
                $t->boolean('activate')->default(true);
                $this->audit($t);
                $t->unique(['intensity', 'lot_from'], 'uq_inv_qc_plan_range');
            });
            foreach (self::PLAN as [$from, $to, $normal, $reduced]) {
                foreach (['NORMAL' => $normal, 'REDUCED' => $reduced, 'TIGHTENED' => (int) ceil($normal * 1.5)] as $intensity => $size) {
                    DB::connection('tm2inv')->table('inv_qc_sampling_plan')->insert(['intensity' => $intensity, 'lot_from' => $from, 'lot_to' => $to, 'sample_size' => $size,
                        'note' => 'Initial value (ANSI/ASQ Z1.4 level II, simplified).', 'created_at' => now(), 'created_by' => self::BY, 'updated_at' => now(), 'updated_by' => self::BY]);
                }
            }
        }

        if (! $inv->hasTable('inv_qc_intensity_rule')) {
            $inv->create('inv_qc_intensity_rule', function (Blueprint $t) {
                $t->id('id_rule');
                $t->string('risk_class', 1)->unique()->comment('A / B / C');
                $t->string('start_intensity', 10)->comment('TIGHTENED / NORMAL / REDUCED / SKIP');
                $t->decimal('aql_pct', 5, 2)->nullable();
                $t->unsignedSmallInteger('pass_to_reduced')->default(10)->comment('consecutive passed lots after which the intensity becomes REDUCED');
                $t->unsignedSmallInteger('recover_pass')->default(5)->comment('consecutive passed lots after a reject before TIGHTENED ends');
                $t->string('note', 255)->nullable();
                $t->boolean('activate')->default(true);
                $this->audit($t);
            });
            foreach (self::RULES as $class => [$start, $aql, $reduced, $recover, $note]) {
                DB::connection('tm2inv')->table('inv_qc_intensity_rule')->insert(['risk_class' => $class, 'start_intensity' => $start, 'aql_pct' => $aql, 'pass_to_reduced' => $reduced,
                    'recover_pass' => $recover, 'note' => $note, 'created_at' => now(), 'created_by' => self::BY, 'updated_at' => now(), 'updated_by' => self::BY]);
            }
        }

        if (! $inv->hasTable('inv_qc_risk_class')) {
            $inv->create('inv_qc_risk_class', function (Blueprint $t) {
                $t->id('id_risk');
                $t->string('scope_key', 40)->unique()->comment('C:<category id> or I:<material id>');
                $t->string('scope_type', 10)->comment('CATEGORY / ITEM');
                $t->unsignedBigInteger('category_id')->nullable()->comment('tm2mst.mat_category (logis)');
                $t->unsignedBigInteger('material_id')->nullable()->comment('tm2mst.mat_raw (logis)');
                $t->string('material_code', 60)->nullable()->comment('typed by the user; material_id is filled from it');
                $t->string('risk_class', 1)->comment('A / B / C');
                $t->string('note', 255)->nullable();
                $t->boolean('activate')->default(true);
                $this->audit($t);
                $t->index('category_id');
                $t->index('material_id');
            });
        }

        // kode bahan yang diketik user untuk aturan per item (id bahan diisi layar dari kode ini)
        if (! $inv->hasColumn('inv_qc_risk_class', 'material_code')) {
            $inv->table('inv_qc_risk_class', fn (Blueprint $t) => $t->string('material_code', 60)->nullable()->after('material_id'));
        }

        if (! $inv->hasTable('inv_qc_decision')) {
            $inv->create('inv_qc_decision', function (Blueprint $t) {
                $t->id('id_decision');
                $t->unsignedBigInteger('batch_id')->index();
                $t->string('decision_type', 15)->comment('SAMPLING_DOCK / FIRST_ARTICLE / USAGE_DECISION / DEVIATION');
                $t->string('decision', 15)->comment('RELEASED / REJECTED / BLOCKED / CONDITIONAL_USE');
                $t->string('status_before', 15)->nullable();
                $t->string('status_after', 15);
                $t->unsignedBigInteger('supplier_id')->nullable()->index()->comment('tm2mst.sup_supplier (logis); copy of the batch, for the supplier history');
                $t->string('risk_class', 1)->nullable();
                $t->string('intensity', 10)->nullable();
                $t->decimal('lot_qty', 20, 4)->nullable();
                $t->unsignedInteger('sample_size')->nullable();
                $t->unsignedInteger('sample_defects')->nullable();
                $t->string('test_result', 255)->nullable();
                $t->unsignedBigInteger('wo_id')->nullable()->comment('tm2prd.prd_wo (logis)');
                $t->string('reason', 255)->nullable();
                $t->string('decided_by', 100);
                $t->timestamp('decided_at')->nullable();
                $t->index(['supplier_id', 'decision_type', 'id_decision'], 'ix_inv_qc_decision_supplier');
            });
        }

        if (! $inv->hasTable('inv_qc_first_article')) {
            $inv->create('inv_qc_first_article', function (Blueprint $t) {
                $t->id('id_first_article');
                $t->unsignedBigInteger('wo_id')->index()->comment('tm2prd.prd_wo (logis)');
                $t->string('wo_no', 40)->nullable();
                $t->unsignedBigInteger('batch_id')->index();
                $t->string('result', 6)->comment('PASS / FAIL');
                $t->string('notes', 255)->nullable();
                $t->unsignedBigInteger('decision_id')->nullable()->comment('inv_qc_decision written by the same action');
                $t->string('checked_by', 100);
                $t->timestamp('checked_at')->nullable();
            });
        }

        if (! $inv->hasTable('inv_qc_hold')) {
            $inv->create('inv_qc_hold', function (Blueprint $t) {
                $t->id('id_hold');
                $t->unsignedBigInteger('batch_id');
                $t->unsignedBigInteger('wo_id')->index()->comment('tm2prd.prd_wo (logis)');
                $t->string('wo_no', 40)->nullable();
                $t->unsignedBigInteger('co_id')->nullable()->comment('tm2sdi.co_order (logis)');
                $t->unsignedBigInteger('delivery_id')->nullable()->comment('tm2sdi.do_delivery (logis)');
                $t->decimal('qty_issued', 20, 4)->default(0);
                $t->string('status', 8)->default('HELD')->comment('HELD / CLEARED');
                $t->string('reason', 255)->nullable();
                $t->string('held_by', 100)->nullable();
                $t->timestamp('held_at')->nullable();
                $t->string('cleared_by', 100)->nullable();
                $t->timestamp('cleared_at')->nullable();
                $t->unique(['batch_id', 'wo_id'], 'uq_inv_qc_hold_batch_wo');
            });
        }

        if (! $inv->hasTable('inv_supplier_claim')) {
            $inv->create('inv_supplier_claim', function (Blueprint $t) {
                $t->id('id_claim');
                $t->string('claim_no', 20)->unique();
                $t->unsignedBigInteger('batch_id')->index();
                $t->unsignedBigInteger('supplier_id')->nullable()->index()->comment('tm2mst.sup_supplier (logis)');
                $t->unsignedBigInteger('pv_id')->nullable()->comment('tm2pur.pv_receipt (logis)');
                $t->unsignedBigInteger('material_id')->nullable();
                $t->decimal('qty_claimed', 20, 4)->default(0);
                $t->string('uom_code', 20)->nullable();
                $t->string('reason', 255);
                $t->string('status', 8)->default('OPEN')->comment('OPEN / SENT / SETTLED / DROPPED');
                $t->string('notes', 255)->nullable();
                $this->audit($t);
            });
        }

        if (! $inv->hasTable('inv_shortage_request')) {
            $inv->create('inv_shortage_request', function (Blueprint $t) {
                $t->id('id_shortage');
                $t->string('request_no', 20)->unique();
                $t->string('source_type', 8)->comment('SUPPLIER / SUBCON / CUSTOMER');
                $t->unsignedBigInteger('supplier_id')->nullable()->index()->comment('tm2mst.sup_supplier (logis); supplier or subcontractor');
                $t->unsignedBigInteger('customer_id')->nullable()->comment('tm2mst.cust_customer (logis)');
                $t->unsignedBigInteger('po_id')->nullable();
                $t->unsignedBigInteger('po_line_id')->nullable()->index();
                $t->unsignedBigInteger('pv_id')->nullable()->index();
                $t->unsignedBigInteger('pv_line_id')->nullable()->index();
                $t->unsignedBigInteger('co_id')->nullable();
                $t->unsignedBigInteger('wo_id')->nullable()->index()->comment('tm2prd.prd_wo (logis)');
                $t->string('wo_no', 40)->nullable();
                $t->unsignedBigInteger('material_id')->nullable()->index();
                $t->string('material_code', 60)->nullable();
                $t->string('material_name', 255)->nullable();
                $t->string('uom_code', 20)->nullable();
                $t->decimal('qty_issued_net', 20, 4)->default(0)->comment('material issued to the WO, net of returns (stock unit)');
                $t->decimal('qty_output_good', 20, 4)->default(0)->comment('good output expressed in material unit');
                $t->decimal('qty_waste_std', 20, 4)->default(0)->comment('STANDARD waste in material unit (not the actual waste)');
                $t->decimal('qty_candidate', 20, 4)->default(0)->comment('issued - (output + standard waste), the suspected shortfall');
                $t->decimal('tol_pct', 8, 4)->default(0);
                $t->decimal('qty_short', 20, 4)->default(0)->comment('shortfall confirmed after the recount');
                $t->decimal('recount_qty', 20, 4)->nullable();
                $t->decimal('recount_weight', 20, 4)->nullable();
                $t->string('recount_note', 255)->nullable();
                $t->string('recount_by', 100)->nullable();
                $t->timestamp('recount_at')->nullable();
                $t->date('need_date')->nullable();
                $t->date('window_until')->nullable();
                $t->string('status', 10)->default('DRAFT')->comment('DRAFT / CONFIRMED / SENT / RECEIVED / REFUSED / CLOSED');
                $t->boolean('debit_note_required')->default(false);
                $t->string('outcome', 20)->nullable()->comment('USAGE_VARIANCE / DELIVERED / REFUSED / WINDOW_LAPSED');
                $t->string('outcome_note', 255)->nullable();
                $t->string('stock_warning', 255)->nullable();
                $t->string('confirmed_by', 100)->nullable();
                $t->timestamp('confirmed_at')->nullable();
                $t->timestamp('sent_at')->nullable();
                $t->string('sent_by', 100)->nullable();
                $t->timestamp('closed_at')->nullable();
                $t->string('closed_by', 100)->nullable();
                $t->string('notes', 255)->nullable();
                $this->audit($t);
                $t->index('status');
            });
        }

        // penerimaan: persetujuan URGENT bahan FSC / food grade dan sertifikasi yang dibawa ke batch
        $pur = Schema::connection('tm2pur');
        $pur->table('pv_line', function (Blueprint $t) use ($pur) {
            foreach (['urgent_approved_by' => fn () => $t->string('urgent_approved_by', 100)->nullable(), 'urgent_approved_at' => fn () => $t->dateTime('urgent_approved_at')->nullable(),
                'urgent_approval_note' => fn () => $t->string('urgent_approval_note', 255)->nullable(),
                'i_certification' => fn () => $t->string('i_certification', 20)->default('NONE')->comment('NONE / FSC / PEFC / OTHER, copied to the batch'),
                'i_food_grade' => fn () => $t->boolean('i_food_grade')->default(false)] as $col => $add) {
                if (! $pur->hasColumn('pv_line', $col)) {
                    $add();
                }
            }
        });

        // pengaturan baru (Settings > Constants)
        $sys = DB::connection('tm2sys');
        if (! $sys->table('sys_constant')->where('constant_key', 'INV_QC_NEW_SUPPLIER_LOTS')->exists()) {
            $sys->table('sys_constant')->insert(['constant_key' => 'INV_QC_NEW_SUPPLIER_LOTS', 'category_code' => 'INVENTORY', 'constant_group' => 'INV', 'constant_number' => 5, 'constant_text' => null,
                'description' => 'The first lots of a new supplier that are sampled with TIGHTENED intensity, whatever the risk class of the item.', 'activate' => 1,
                'created_at' => now(), 'updated_at' => now(), 'created_by' => self::BY, 'updated_by' => self::BY]);
        }
    }

    private function audit(Blueprint $t): void
    {
        $t->timestamp('created_at')->nullable();
        $t->string('created_by', 100)->nullable();
        $t->timestamp('updated_at')->nullable();
        $t->string('updated_by', 100)->nullable();
    }

    public function down(): void
    {
        $inv = Schema::connection('tm2inv');
        foreach (['inv_shortage_request', 'inv_supplier_claim', 'inv_qc_hold', 'inv_qc_first_article', 'inv_qc_decision', 'inv_qc_risk_class', 'inv_qc_intensity_rule', 'inv_qc_sampling_plan'] as $table) {
            $inv->dropIfExists($table);
        }
        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'INV_QC_NEW_SUPPLIER_LOTS')->delete();
    }
};
