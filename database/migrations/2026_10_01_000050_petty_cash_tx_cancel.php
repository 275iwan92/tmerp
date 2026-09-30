<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pembatalan transaksi kas kecil yang salah input (user 2026-10-01): transaksi tidak dihapus -- jurnalnya dibalik,
// status CANCELLED, jejak siapa/kapan/alasan disimpan di sini.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2acc')->table('fin_petty_cash_tx', function (Blueprint $t) {
            $t->string('cancel_reason', 255)->nullable()->after('status');
            $t->string('cancelled_by', 100)->nullable()->after('cancel_reason');
            $t->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            $t->unsignedBigInteger('reversal_voucher_id')->nullable()->after('voucher_id')->comment('jurnal pembalik saat dibatalkan');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2acc')->table('fin_petty_cash_tx', fn (Blueprint $t) => $t->dropColumn(['cancel_reason', 'cancelled_by', 'cancelled_at', 'reversal_voucher_id']));
    }
};
