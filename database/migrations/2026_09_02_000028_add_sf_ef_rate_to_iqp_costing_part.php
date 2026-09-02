<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/inquiry_product_costing/gemini_spark_productcosting.md §II event 10 /
// §IV.3 J37 (SF EF) -- dipakai CostingEngine::calculateDcSheetPart() utk tipe
// DC+SHEET/DC+SF. NULL/0 = tidak ada kontribusi SF EF (aman utk DC+SHEET
// murni yang tak pakai layer SF).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('iqp_costing_part', function (Blueprint $table) {
            $table->decimal('i_sf_ef_rate_override', 18, 4)->nullable()->after('i_ongkos_plate_warna')
                ->comment('Rate SF EF (Single Face EF) per m2 cetak. NULL/0 = tidak pakai layer SF -- §IV.3 J37');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('iqp_costing_part', function (Blueprint $table) {
            $table->dropColumn('i_sf_ef_rate_override');
        });
    }
};
