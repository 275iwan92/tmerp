<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TIER_3A_FINANCE.sql §3 — cost center TERKONTROL (Keputusan C, CLAUDE.md §4):
// referensi FK, bukan free-text. org_dept_id logical -> tm2mst.org_department.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2acc')->create('fin_cost_center', function (Blueprint $table) {
            $table->id('cc_id');
            $table->string('cc_code', 30)->unique();
            $table->string('cc_name', 100);
            $table->enum('cc_group', ['PRODUCTION', 'SALES', 'PURCHASING', 'PREPRESS', 'QC', 'WAREHOUSE', 'FINANCE', 'TAX', 'GENERAL']);
            $table->unsignedBigInteger('org_dept_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('updated_by', 50)->nullable();
            $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2acc')->dropIfExists('fin_cost_center');
    }
};
