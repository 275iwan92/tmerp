<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// TIER_3A_FINANCE.sql §6 — baris double-entry. debit XOR credit dijaga CHECK
// DB (chk_dr_cr) + validasi aplikasi. partner_id wajib bila account.is_recon=1
// (enforce di Livewire, bukan DB — financeaccounting_tier2.md §B2).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2acc')->create('fin_voucher_line', function (Blueprint $table) {
            $table->id('line_id');
            $table->unsignedBigInteger('voucher_id');
            $table->unsignedInteger('account_id');
            $table->enum('partner_type', ['CUSTOMER', 'SUPPLIER'])->nullable();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->unsignedBigInteger('cc_id')->nullable();
            $table->unsignedBigInteger('co_id')->nullable();
            $table->decimal('debit', 18, 4)->default(0);
            $table->decimal('credit', 18, 4)->default(0);
            $table->string('narration', 255)->nullable();

            $table->foreign('voucher_id', 'fk_vl_voucher')->references('voucher_id')->on('fin_voucher')->cascadeOnDelete();
            $table->foreign('account_id', 'fk_vl_account')->references('account_id')->on('fin_account')->restrictOnDelete();
            $table->foreign('cc_id', 'fk_vl_cc')->references('cc_id')->on('fin_cost_center')->restrictOnDelete();
            $table->index('voucher_id', 'idx_vl_voucher');
            $table->index('account_id', 'idx_vl_account');
            $table->index(['partner_type', 'partner_id'], 'idx_vl_partner');
            $table->index('co_id', 'idx_vl_co');
        });

        DB::connection('tm2acc')->statement(
            'ALTER TABLE fin_voucher_line ADD CONSTRAINT chk_dr_cr CHECK '
            .'((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0) OR (debit = 0 AND credit = 0))'
        );
    }

    public function down(): void
    {
        Schema::connection('tm2acc')->dropIfExists('fin_voucher_line');
    }
};
