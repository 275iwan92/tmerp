<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kurang kirim lanjutan (Q-13..Q-16, Inventory v2.01): permintaan mencatat asal deteksi (MANUAL / AUTO), rujukan PR tambahan (tm2pur.pr_requisition)
// dan draft debit note (tm2pur.dn_debit_note, dokumen draft sederhana: belum ada fitur debit note pembelian, retur barang prt_return bukan padanannya).
// Relasi lintas database = logis (tanpa FK). Jalankan di ddt* dulu: php artisan migrate --env=mockup --path=<berkas> --force
return new class extends Migration
{
    public function up(): void
    {
        $inv = Schema::connection('tm2inv');
        foreach (['detected_source' => fn (Blueprint $t) => $t->string('detected_source', 8)->default('MANUAL')->comment('MANUAL (typed) / AUTO (inv:detect-shortage)'),
            'pr_id' => fn (Blueprint $t) => $t->unsignedBigInteger('pr_id')->nullable()->comment('tm2pur.pr_requisition (logis): additional purchase request'),
            'debit_note_id' => fn (Blueprint $t) => $t->unsignedBigInteger('debit_note_id')->nullable()->comment('tm2pur.dn_debit_note (logis): draft debit note')] as $col => $add) {
            if (! $inv->hasColumn('inv_shortage_request', $col)) {
                $inv->table('inv_shortage_request', fn (Blueprint $t) => $add($t));
            }
        }

        $pur = Schema::connection('tm2pur');
        if (! $pur->hasTable('dn_debit_note')) {
            $pur->create('dn_debit_note', function (Blueprint $t) {
                $t->id('dn_id');
                $t->string('doc_no', 20)->unique()->comment('DN + yy + 5 digits');
                $t->date('i_doc_date');
                $t->unsignedBigInteger('i_supplier_id')->nullable()->index()->comment('tm2mst.sup_supplier (logis)');
                $t->unsignedBigInteger('pv_id')->nullable()->index();
                $t->unsignedBigInteger('po_id')->nullable();
                $t->unsignedBigInteger('shortage_id')->nullable()->index()->comment('tm2inv.inv_shortage_request (logis)');
                $t->string('i_reason', 255);
                $t->decimal('i_qty', 20, 4)->comment('purchase unit');
                $t->string('i_uom', 50)->nullable();
                $t->decimal('i_unit_price', 18, 4)->default(0);
                $t->decimal('c_amount', 18, 4)->default(0)->comment('i_qty * i_unit_price');
                $t->string('status', 10)->default('DRAFT')->comment('DRAFT / SENT / SETTLED / CANCELLED');
                $t->string('notes', 255)->nullable();
                $t->string('created_by', 100)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->string('updated_by', 100)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('tm2pur')->dropIfExists('dn_debit_note');
        Schema::connection('tm2inv')->table('inv_shortage_request', fn (Blueprint $t) => $t->dropColumn(['detected_source', 'pr_id', 'debit_note_id']));
    }
};
