<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PR - Purchase Requisition (PROC_TIER_3.md §2). FK lintas-DB (department,
// coa_account, item, co_line) = LOGICAL, tidak dibuat FK fisik (CLAUDE.md §2).
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2pur');

        if (! $db->hasTable('pr_requisition')) {
            $db->create('pr_requisition', function (Blueprint $table) {
                $table->id('pr_id');
                $table->string('doc_no', 30)->nullable()->unique()->comment('PR/YY/##### - digenerate saat SUBMIT');
                $table->date('i_doc_date');
                $table->enum('i_source', ['MANUAL', 'MRP'])->default('MANUAL');
                $table->unsignedBigInteger('mrp_run_id')->nullable()->comment('reserved - MRP belum dibangun');
                $table->unsignedBigInteger('i_department_id')->comment('LOGICAL -> tm2mst.org_department');
                $table->date('i_need_date')->nullable();
                $table->string('i_notes', 255)->nullable();

                $table->decimal('c_est_total', 18, 4)->nullable()->comment('SUM(pr_line.c_est_subtotal) - amount_snapshot approval');
                $table->string('status', 20)->default('DRAFT')
                    ->comment('DRAFT|SUBMITTED|APPROVED|REJECTED|REVISION_NEEDED|PARTIALLY_ORDERED|FULLY_ORDERED|CLOSED|CANCELLED');

                $table->unsignedInteger('revision_no')->default(0);
                $table->unsignedBigInteger('parent_pr_id')->nullable();
                $table->boolean('is_cancelled')->default(false);
                $table->string('cancel_reason', 100)->nullable();

                $table->string('created_by', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->string('updated_by', 100)->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->index('status');
                $table->index('i_doc_date');
                $table->index('i_department_id');
            });
        }

        if (! $db->hasTable('pr_line')) {
            $db->create('pr_line', function (Blueprint $table) {
                $table->id('pr_line_id');
                $table->unsignedBigInteger('pr_id');
                $table->unsignedSmallInteger('line_no');

                $table->enum('i_item_source', ['MASTER_ITEM', 'FREE_TEXT'])->default('MASTER_ITEM');
                $table->unsignedBigInteger('item_id')->nullable()->comment('LOGICAL -> tm2mst.m_raw_material.material_id');
                $table->string('c_item_code', 60)->nullable()->comment('snapshot material_code');
                $table->string('i_description', 255)->comment('snapshot nama item, atau teks bebas bila FREE_TEXT');

                $table->decimal('i_qty', 20, 4);
                $table->string('i_uom', 50);
                $table->decimal('i_est_price', 18, 4)->nullable();
                $table->decimal('c_est_subtotal', 18, 4)->nullable();

                $table->unsignedBigInteger('i_department_id')->comment('LOGICAL -> tm2mst.org_department (boleh beda per baris)');
                $table->unsignedBigInteger('i_coa_account_id')->comment('LOGICAL -> tm2acc.fin_account (is_postable=1)');
                $table->unsignedBigInteger('i_co_line_id')->nullable()->comment('LOGICAL -> tm2sdi co_order_item - penelusuran biaya');

                $table->boolean('is_stock')->default(true)->comment('0 utk SERVICE/INDIRECT/FREE_TEXT');
                $table->date('i_need_date')->nullable();
                $table->string('i_notes', 255)->nullable();

                $table->decimal('c_qty_allocated', 20, 4)->default(0);
                $table->decimal('c_qty_ordered', 20, 4)->default(0);
                $table->string('c_line_status', 20)->nullable()->comment('OPEN|PARTIALLY_ORDERED|FULLY_ORDERED|CLOSED');

                $table->string('created_by', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->string('updated_by', 100)->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->unique(['pr_id', 'line_no']);
                $table->index('item_id');
                $table->index('i_co_line_id');
                $table->foreign('pr_id')->references('pr_id')->on('pr_requisition')->cascadeOnDelete();
            });
        }

        if (! $db->hasTable('pr_line_alloc')) {
            $db->create('pr_line_alloc', function (Blueprint $table) {
                $table->id('alloc_id');
                $table->unsignedBigInteger('pr_line_id');
                $table->unsignedSmallInteger('alloc_no')->default(1);
                $table->decimal('i_qty', 20, 4)->comment('SUM per pr_line TIDAK BOLEH > pr_line.i_qty (validasi aplikasi)');
                $table->unsignedBigInteger('i_supplier_id')->nullable()->comment('LOGICAL -> tm2mst.sup_supplier');
                $table->string('i_notes', 255)->nullable();
                $table->boolean('c_is_converted')->default(false)->comment('1 bila sudah menjadi po_line');

                $table->string('created_by', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->string('updated_by', 100)->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->unique(['pr_line_id', 'alloc_no']);
                $table->index('c_is_converted');
                $table->foreign('pr_line_id')->references('pr_line_id')->on('pr_line')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $db = Schema::connection('tm2pur');
        $db->dropIfExists('pr_line_alloc');
        $db->dropIfExists('pr_line');
        $db->dropIfExists('pr_requisition');
    }
};
