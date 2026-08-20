<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TIER_3A_FINANCE.sql §7 — subledger AR/AP, source polymorphic (Keputusan A).
// total_amount = subtotal+tax, snapshot beku saat posting (tak retroaktif
// walau rate pajak berubah — FIN_TIER_2.md §B7).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2acc')->create('fin_invoice', function (Blueprint $table) {
            $table->id('invoice_id');
            $table->string('invoice_number', 50)->unique();
            $table->enum('invoice_type', ['CUSTOMER_INVOICE', 'SUPPLIER_BILL']);
            $table->enum('partner_type', ['CUSTOMER', 'SUPPLIER']);
            $table->unsignedBigInteger('partner_id');
            $table->string('source_type', 30)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('co_id')->nullable();
            $table->date('invoice_date');
            $table->date('due_date');
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('total_amount', 18, 4)->default(0);
            $table->decimal('paid_amount', 18, 4)->default(0);
            $table->unsignedBigInteger('voucher_id')->nullable();
            $table->enum('payment_status', ['UNPAID', 'PARTIAL', 'PAID'])->default('UNPAID');
            $table->string('created_by', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('updated_by', 50)->nullable();
            $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

            $table->foreign('voucher_id', 'fk_inv_voucher')->references('voucher_id')->on('fin_voucher')->nullOnDelete();
            $table->index(['partner_type', 'partner_id'], 'idx_inv_partner');
            $table->index('payment_status', 'idx_inv_status');
            $table->index('co_id', 'idx_inv_co');
            $table->index(['source_type', 'source_id'], 'idx_inv_source');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2acc')->dropIfExists('fin_invoice');
    }
};
