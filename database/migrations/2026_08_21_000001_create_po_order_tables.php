<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PO - Purchase Order (procurement_tier3.md §4). FK lintas-DB (department, coa_account,
// item, co_line) = LOGICAL (CLAUDE.md §2). alloc_id UNIQUE menjaga 1 alokasi =
// 1 po_line (anti many-to-many, PROC_TIER_3 §3.1).
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2pur');

        if (! $db->hasTable('po_order')) {
            $db->create('po_order', function (Blueprint $table) {
                $table->id('po_id');
                $table->string('doc_no', 30)->nullable()->unique()->comment('PO/YY/##### - digenerate saat SUBMIT');
                $table->date('i_doc_date');
                $table->unsignedBigInteger('i_supplier_id')->comment('LOGICAL -> tm2mst.sup_supplier');
                $table->unsignedBigInteger('i_pq_id')->nullable()->comment('penawaran acuan - PQ belum dibangun, selalu NULL fase ini');

                $table->enum('i_po_type', ['LOCAL', 'IMPORT'])->default('LOCAL');
                $table->enum('i_procure_type', ['REPEAT', 'ONE_TIME', 'CAPEX', 'CASH'])->default('REPEAT');

                $table->unsignedBigInteger('i_currency_id')->nullable()->comment('LOGICAL -> tm2mst.fin_currency; NULL fase ini (default IDR, PQ belum ada)');
                $table->decimal('i_fx_rate', 18, 6)->default(1);
                $table->date('i_quotation_valid_until')->nullable();

                $table->string('i_payment_term', 50)->nullable();
                $table->date('i_delivery_date')->nullable();
                $table->string('i_ship_to', 255)->nullable();
                $table->string('i_notes', 500)->nullable();

                $table->decimal('c_dpp', 18, 4)->nullable();
                $table->decimal('c_ppn', 18, 4)->nullable();
                $table->decimal('c_pph', 18, 4)->nullable();
                $table->decimal('c_grand_total', 18, 4)->nullable();
                $table->decimal('c_net_payable', 18, 4)->nullable();

                $table->string('status', 20)->default('DRAFT')
                    ->comment('DRAFT|SUBMITTED|APPROVED|REJECTED|SENT|PARTIALLY_RECEIVED|RECEIVED|CLOSED|CANCELLED');
                $table->dateTime('sent_at')->nullable();

                $table->boolean('is_auto_generated')->default(false)->comment('1 = dari Pembelian Kontan');
                $table->string('source_note', 100)->nullable();

                $table->unsignedInteger('revision_no')->default(0);
                $table->unsignedBigInteger('parent_po_id')->nullable();
                $table->boolean('is_cancelled')->default(false);
                $table->string('cancel_reason', 100)->nullable();

                $table->string('created_by', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->string('updated_by', 100)->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->index('status');
                $table->index('i_supplier_id');
                $table->index('i_doc_date');
                $table->index('i_procure_type');
            });
        }

        if (! $db->hasTable('po_line')) {
            $db->create('po_line', function (Blueprint $table) {
                $table->id('po_line_id');
                $table->unsignedBigInteger('po_id');
                $table->unsignedSmallInteger('line_no');

                $table->unsignedBigInteger('alloc_id')->nullable()->comment('FK 1:1 ke pr_line_alloc; NULL bila PO tanpa PR (kontan/darurat)');
                $table->unsignedBigInteger('item_id')->nullable()->comment('LOGICAL -> tm2mst.m_raw_material');
                $table->string('c_item_code', 60)->nullable();
                $table->string('i_description', 255);
                $table->enum('i_line_class', ['RAW', 'INDIRECT', 'SERVICE'])->default('RAW')->comment('diturunkan dari doc_rmprefix1.stock_class; SERVICE kena PPh23');

                $table->decimal('i_qty', 20, 4);
                $table->string('i_uom', 50)->comment('satuan beli');
                $table->string('c_uom_stock', 50)->nullable();
                $table->decimal('c_conv_to_stock', 20, 6)->nullable();

                $table->decimal('i_price', 18, 4)->comment('harga satuan dalam i_currency_id header');
                $table->decimal('i_disc_pct', 5, 2)->nullable();
                $table->decimal('i_disc_amt', 18, 4)->nullable();
                $table->decimal('c_line_net', 18, 4)->nullable();

                $table->unsignedBigInteger('i_department_id');
                $table->unsignedBigInteger('i_coa_account_id');
                $table->unsignedBigInteger('i_co_line_id')->nullable();
                $table->boolean('is_stock')->default(true);

                $table->decimal('c_tol_pct', 5, 2)->nullable();
                $table->decimal('c_qty_received', 20, 4)->default(0);
                $table->decimal('c_qty_outstanding', 20, 4)->nullable();
                $table->string('c_line_status', 20)->nullable()->comment('OPEN|PARTIALLY_RECEIVED|RECEIVED|CLOSED_BY_TOLERANCE');

                $table->string('created_by', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->string('updated_by', 100)->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->unique(['po_id', 'line_no']);
                $table->unique('alloc_id', 'uq_po_alloc');
                $table->index('item_id');
                $table->index('i_co_line_id');
                $table->foreign('po_id')->references('po_id')->on('po_order')->cascadeOnDelete();
                $table->foreign('alloc_id')->references('alloc_id')->on('pr_line_alloc')->nullOnDelete();
            });
        }

        if (! $db->hasTable('po_import_doc')) {
            $db->create('po_import_doc', function (Blueprint $table) {
                $table->id('import_doc_id');
                $table->unsignedBigInteger('po_id');
                $table->string('i_doc_code', 20)->comment('PIB|INV|PL|BL|COO|INS|NIB|HS|PI_LARTAS|LS|SNI|SPPB|DO_FF|PAY_BM|INCO');
                $table->string('i_doc_name', 100);
                $table->boolean('is_mandatory')->default(false);
                $table->boolean('is_received')->default(false);
                $table->string('i_doc_no', 100)->nullable();
                $table->date('i_doc_date')->nullable();
                $table->string('i_file_path', 255)->nullable();
                $table->string('i_notes', 255)->nullable();
                $table->string('created_by', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->string('updated_by', 100)->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->unique(['po_id', 'i_doc_code']);
                $table->foreign('po_id')->references('po_id')->on('po_order')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $db = Schema::connection('tm2pur');
        $db->dropIfExists('po_import_doc');
        $db->dropIfExists('po_line');
        $db->dropIfExists('po_order');
    }
};
