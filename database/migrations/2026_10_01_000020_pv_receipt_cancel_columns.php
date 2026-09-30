<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cancel SPB (user 2026-10-01): pengganti trigger legacy pur_prcv_detail AFTER DELETE. SPB POSTED dibatalkan lewat
// dokumen stok balik (Stock Out RM ber-ref PV_CANCEL) + jurnal pembalik, bukan dihapus -- jejaknya disimpan di sini.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2pur')->table('pv_receipt', function (Blueprint $t) {
            $t->string('cancel_reason', 255)->nullable()->after('rm_stock_doc_id');
            $t->timestamp('cancelled_at')->nullable()->after('cancel_reason');
            $t->string('cancelled_by', 100)->nullable()->after('cancelled_at');
            $t->unsignedBigInteger('rm_stock_rev_doc_id')->nullable()->after('cancelled_by')->comment('dokumen stok balik (tm2inv.rm_stock, ref PV_CANCEL)');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2pur')->table('pv_receipt', function (Blueprint $t) {
            $t->dropColumn(['cancel_reason', 'cancelled_at', 'cancelled_by', 'rm_stock_rev_doc_id']);
        });
    }
};
