<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// BACKLOG §12.3 / docs/financeaccounting_delta_partner_category_coa_v2.md §2.2
// (Grup A, "KEPUTUSAN TERKUNCI" -- boleh jalan sekarang). Fallback lapis
// TERAKHIR utk resolve akun Persediaan FG/COGS (prod_part -> prod_product ->
// prod_category), menutup gap "SFG tanpa fallback kategori" yang sebelumnya
// TANPA fallback sama sekali (lihat komentar StockPostingService::postUsageJournal()).
// SENGAJA TIDAK di-seed di sini (beda dari draf spec §2.3 yang minta UPDATE
// massal) -- diverifikasi ulang ke fin_account aktual (2026-09-11), account_code
// '51x' ternyata per JENIS BIAYA (Bahan Baku/Tinta/Tenaga Kerja/dst), bukan per
// kategori produk seperti diasumsikan draf, jadi tidak ada 1 akun COGS yang
// benar utk semua kategori tanpa dipilih manual per baris -- kolom dibiarkan
// NULL, diisi via UI nanti (pola sama seperti gap 12 kategori mat_category).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->table('prod_category', function (Blueprint $table) {
            $table->unsignedBigInteger('default_stock_account_id')->nullable()->after('description')
                ->comment('LOGICAL -> tm2acc.fin_account -- Persediaan Barang Jadi, fallback terakhir');
            $table->unsignedBigInteger('default_cogs_account_id')->nullable()->after('default_stock_account_id')
                ->comment('LOGICAL -> tm2acc.fin_account -- akun HPP, fallback terakhir');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('prod_category', function (Blueprint $table) {
            $table->dropColumn(['default_stock_account_id', 'default_cogs_account_id']);
        });
    }
};
