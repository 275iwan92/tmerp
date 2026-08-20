<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TIER_3A_FINANCE.sql §1 — buku jurnal kecil (CSH/BNK/SAL/PUR/GEN), acuan
// header voucher (fin_voucher.journal_id). Mirror DDL persis, lihat CLAUDE.md §7.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2acc')->create('fin_journal', function (Blueprint $table) {
            $table->id('journal_id');
            $table->string('journal_code', 10)->unique();
            $table->string('journal_name', 100);
            $table->enum('journal_type', ['SALES', 'PURCHASE', 'CASH_BANK', 'GENERAL']);
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('updated_by', 50)->nullable();
            $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2acc')->dropIfExists('fin_journal');
    }
};
