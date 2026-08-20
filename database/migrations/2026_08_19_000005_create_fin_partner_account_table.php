<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TIER_3A_FINANCE.sql §4 — mapping akun recon per partner. partner = ref
// LOGICAL ke tm2mst.cust_customer / tm2mst.sup_supplier (business_partners
// DIHAPUS, CLAUDE.md §2 aturan lintas-DB: tanpa FK MySQL lintas-DB).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2acc')->create('fin_partner_account', function (Blueprint $table) {
            $table->id('map_id');
            $table->enum('partner_type', ['CUSTOMER', 'SUPPLIER']);
            $table->unsignedBigInteger('partner_id');
            $table->unsignedInteger('ar_account_id')->nullable();
            $table->unsignedInteger('ap_account_id')->nullable();
            $table->string('payment_term', 50)->default('NET30');
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('updated_by', 50)->nullable();
            $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

            $table->foreign('ar_account_id', 'fk_pa_ar')->references('account_id')->on('fin_account')->restrictOnDelete();
            $table->foreign('ap_account_id', 'fk_pa_ap')->references('account_id')->on('fin_account')->restrictOnDelete();
            $table->unique(['partner_type', 'partner_id'], 'uq_partner');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2acc')->dropIfExists('fin_partner_account');
    }
};
