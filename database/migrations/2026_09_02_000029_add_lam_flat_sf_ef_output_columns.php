<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kolom output (c_*) utk J36 (Laminasi Flat) & J37 (SF EF) -- CostingEngine::
// calculateDcSheetPart(), tipe DC+SHEET/DC+SF. Dipisah dari c_sheet_cost_pc
// (dipakai ulang utk geometri, lihat computeSheetGeometry()) supaya nilai
// "kontribusi ke HPP DC part ini" tetap kebaca jelas terpisah dari makna
// aslinya di SHEET_DIGITAL (harga material sheet itu sendiri).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('iqp_costing_part', function (Blueprint $table) {
            $table->decimal('c_lam_flat_cost_pc', 18, 4)->nullable()->after('c_sheet_cost_pc');
            $table->decimal('c_sf_ef_cost_pc', 18, 4)->nullable()->after('c_lam_flat_cost_pc');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('iqp_costing_part', function (Blueprint $table) {
            $table->dropColumn(['c_lam_flat_cost_pc', 'c_sf_ef_cost_pc']);
        });
    }
};
