<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TIER_3A_FINANCE.sql §5 — header voucher/journal entry. Snapshot; REVISE
// pada POSTED = copy-record (revision_no+1), lama is_cancelled=1
// cancel_reason='SUPERSEDED' — financeaccounting_tier2.md §B5.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2acc')->create('fin_voucher', function (Blueprint $table) {
            $table->id('voucher_id');
            $table->string('voucher_number', 50)->unique();
            $table->unsignedBigInteger('journal_id');
            $table->date('entry_date');
            $table->date('posting_date');
            $table->enum('doc_type', ['NEW', 'RECALC', 'REVISE'])->default('NEW');
            $table->integer('revision_no')->default(0);
            $table->string('source_type', 30)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference_no', 100)->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['DRAFT', 'POSTED', 'CANCELLED'])->default('DRAFT');
            $table->boolean('is_cancelled')->default(false);
            $table->string('cancel_reason', 100)->nullable();
            $table->string('posted_by', 50)->nullable();
            $table->dateTime('posted_at')->nullable();
            $table->string('created_by', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('updated_by', 50)->nullable();
            $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

            $table->foreign('journal_id', 'fk_vch_journal')->references('journal_id')->on('fin_journal')->restrictOnDelete();
            $table->index('posting_date', 'idx_vch_posting');
            $table->index(['source_type', 'source_id'], 'idx_vch_source');
            $table->index('status', 'idx_vch_status');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2acc')->dropIfExists('fin_voucher');
    }
};
