<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Status pipeline Master Customer (redesign UI 2026-08-19, referensi mockup
// "gemini-code" gaya SAP-Hana — gayanya dipakai, istilah SAP dibuang):
// DRAFT -> CREDIT_CHECK -> APPROVED -> BLOCKED. Kolom flat sederhana,
// konsisten dengan pola sec_user.approval_status & prod_product.card_status
// yang sudah ada di codebase ini — BUKAN doc_state_log (prinsip
// doc_state_log di CLAUDE.md §3 dipakai utk dokumen TRANSAKSI seperti
// inquiry/quotation, bukan status approval master data).
// Terpisah dari is_active (lifecycle record) & is_blacklisted (flag
// bermasalah, fitur lain) — approval_status murni indikator alur kredit.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->table('cust_customer', function (Blueprint $table) {
            $table->enum('approval_status', ['DRAFT', 'CREDIT_CHECK', 'APPROVED', 'BLOCKED'])
                ->default('DRAFT')->after('is_blacklisted');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('cust_customer', function (Blueprint $table) {
            $table->dropColumn('approval_status');
        });
    }
};
