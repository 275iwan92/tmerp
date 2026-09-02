<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// docs/inquiry_product_costing/gemini_spark_productcosting.md §IV -- SHEET,
// KRAFT diimplementasikan penuh (formula literal dari dokumen, aman).
// DC+SHEET/DC+SF JUGA ditandai is_engine_ready=1 (dipakai selektor UI utk
// tahu part_type mana yang bisa di-"Hitung HPP") TAPI CostingEngine tetap
// menandai output-nya "BELUM DIVERIFIKASI Excel" di description_calc --
// is_engine_ready di sini artinya "tidak lagi melempar exception", BUKAN
// "formula sudah pasti benar" utk 2 tipe itu (lihat class docblock
// CostingEngine.php).
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2mst')->table('prod_part_type')
            ->whereIn('part_type_code', ['SHEET', 'KRAFT', 'DC+SHEET', 'DC+SF'])
            ->update(['is_engine_ready' => true]);
    }

    public function down(): void
    {
        DB::connection('tm2mst')->table('prod_part_type')
            ->whereIn('part_type_code', ['SHEET', 'KRAFT', 'DC+SHEET', 'DC+SF'])
            ->update(['is_engine_ready' => false]);
    }
};
