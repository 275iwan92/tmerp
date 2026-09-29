<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Siklus Quotation wajib punya status batal (aturan: tiap modul punya confirmed/cancelled/closed).
// PUBLISHED yang belum WIN/LOST bisa dibatalkan: doc_status = CANCELLED + stat_cancel + cancel_reason (pelaku = updated_by);
// inquiry-nya kembali tersedia untuk quotation lain (QuotationBuilder::availableInquiries).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('quo_quotation', function (Blueprint $table) {
            $table->boolean('stat_cancel')->default(false)->after('doc_status');
            $table->string('cancel_reason', 255)->nullable()->after('stat_cancel');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('quo_quotation', function (Blueprint $table) {
            $table->dropColumn(['stat_cancel', 'cancel_reason']);
        });
    }
};
