<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Master Produk (FG) & Master Card (SFG) belum punya kolom akun akuntansi
// sama sekali (beda dgn m_raw_material yang sudah ada sejak 2026_08_21_000003)
// -- ditambah sekarang atas permintaan user supaya siap saat Delivery/Invoice
// auto-jurnal dikerjakan (fase berikutnya, BELUM diwire ke posting apapun di
// putaran ini -- putaran ini cuma Purchase Receive + Stock Out utk RM).
// Pola sama persis dgn m_raw_material: FK logical ke tm2acc (beda DB, tak ada
// constraint fisik), nullable, officer boleh override per item.
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2mst');

        $db->table('prod_product', function (Blueprint $table) {
            $table->unsignedBigInteger('stock_asset_account_id')->nullable()->comment('LOGICAL -> tm2acc.fin_account (Persediaan Barang Jadi)');
            $table->unsignedBigInteger('cogs_account_id')->nullable()->comment('LOGICAL -> tm2acc.fin_account (HPP saat kirim/DO)');
            $table->unsignedBigInteger('cost_center_id')->nullable()->comment('LOGICAL -> tm2acc.fin_cost_center');
            $table->unsignedBigInteger('profit_center_id')->nullable()->comment('LOGICAL -> tm2acc.fin_cost_center (dipakai sbg profit center)');
        });

        $db->table('prod_part', function (Blueprint $table) {
            $table->unsignedBigInteger('stock_asset_account_id')->nullable()->comment('LOGICAL -> tm2acc.fin_account (Persediaan WIP/SFG)');
            $table->unsignedBigInteger('cogs_account_id')->nullable()->comment('LOGICAL -> tm2acc.fin_account (beban saat SFG di-stock-out)');
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('profit_center_id')->nullable();
        });
    }

    public function down(): void
    {
        $db = Schema::connection('tm2mst');

        $db->table('prod_product', function (Blueprint $table) {
            $table->dropColumn(['stock_asset_account_id', 'cogs_account_id', 'cost_center_id', 'profit_center_id']);
        });
        $db->table('prod_part', function (Blueprint $table) {
            $table->dropColumn(['stock_asset_account_id', 'cogs_account_id', 'cost_center_id', 'profit_center_id']);
        });
    }
};
