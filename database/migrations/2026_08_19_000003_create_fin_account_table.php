<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TIER_3A_FINANCE.sql §2 — Chart of Accounts, self-parent (pohon 3 level).
// account_id MANUAL (bukan AUTO_INCREMENT) — id stabil dipakai sebagai
// parent_id oleh akun anak; nilainya di-generate app-layer (MAX+1), bukan
// diinput user, lihat Livewire pages::finance.coa::nextAccountId().
// default_cc_id & tax_scheme_code SENGAJA tanpa FK (DDL asal juga begitu —
// default_cc_id logical walau satu-DB, tax_scheme_code logical ke tm2mst).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2acc')->create('fin_account', function (Blueprint $table) {
            $table->unsignedInteger('account_id')->primary();
            $table->string('account_code', 20)->unique();
            $table->string('account_name', 150);
            $table->enum('account_type', ['ASSET', 'LIABILITY', 'EQUITY', 'REVENUE', 'COGS', 'EXPENSE', 'OTHER_INCOME', 'OTHER_EXPENSE']);
            $table->enum('normal_balance', ['DEBIT', 'CREDIT']);
            $table->unsignedInteger('parent_id')->nullable();
            $table->boolean('is_recon')->default(false);
            $table->boolean('is_postable')->default(true);
            $table->unsignedInteger('default_cc_id')->nullable();
            $table->string('tax_scheme_code', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('updated_by', 50)->nullable();
            $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

            $table->foreign('parent_id', 'fk_acc_parent')->references('account_id')->on('fin_account')->restrictOnDelete();
            $table->index('account_code', 'idx_acc_code');
            $table->index('account_type', 'idx_acc_type');
            $table->index('parent_id', 'idx_acc_parent');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2acc')->dropIfExists('fin_account');
    }
};
