<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Auto-jurnal Purchase Receive + Stock Out. doc_rmprefix1.default_coa_account_id
// yang sudah ada (migration 2026_08_20_000012) dipakai apa adanya sbg default
// akun COGS/pemakaian per kategori (makna aslinya "akun beban kategori ini"
// utk PR/PO non-stock, konsisten dipakai lagi di sini). Tambah 2 kolom baru
// yang belum ada: default akun Persediaan & GR/IR per kategori -- item RM
// individual (m_raw_material) TETAP bisa override 3-3nya sendiri (kolom sudah
// ada dari 2026_08_21_000003), kategori cuma fallback kalau item-nya kosong.
//
// Data yg diisi sengaja HANYA yang cocok jelas dgn akun yg sudah diseed
// FinanceMasterSeeder ("Kertas & Board" / "Tinta & Chemical"). 13 kategori
// sisanya (Gasoline/Solasi/Plastik/CTP Plate/Pack Material/ATK/Spare Part x2/
// Tools/Laptop-IT/Jasa/Peralatan Kebersihan) DIBIARKAN NULL -- sudah ada
// catatan terbuka PROC-STOCKCLASS-SEED (PROC_STATE.yaml) yang eksplisit minta
// review Jo per kategori, bukan tebakan; GR/IR clearing tetap generik utk
// SEMUA kategori (akun teknis, bukan keputusan bisnis per kategori).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->table('doc_rmprefix1', function (Blueprint $table) {
            $table->unsignedBigInteger('default_stock_asset_account_id')->nullable()->after('default_coa_account_id')
                ->comment('LOGICAL -> tm2acc.fin_account (Persediaan) -- fallback kategori utk m_raw_material.stock_asset_account_id');
            $table->unsignedBigInteger('default_gr_ir_account_id')->nullable()->after('default_stock_asset_account_id')
                ->comment('LOGICAL -> tm2acc.fin_account (GR/IR Clearing) -- fallback kategori utk m_raw_material.gr_ir_account_id');
        });

        $mst = DB::connection('tm2mst');
        $acc = DB::connection('tm2acc');

        $grIrId = (int) $acc->table('fin_account')->where('account_code', '211900')->value('account_id');
        $paperStock = (int) $acc->table('fin_account')->where('account_code', '113100')->value('account_id');
        $paperCogs = (int) $acc->table('fin_account')->where('account_code', '511000')->value('account_id');
        $chemStock = (int) $acc->table('fin_account')->where('account_code', '113200')->value('account_id');
        $chemCogs = (int) $acc->table('fin_account')->where('account_code', '512000')->value('account_id');

        if ($grIrId) {
            $mst->table('doc_rmprefix1')->update(['default_gr_ir_account_id' => $grIrId]);
        }

        if ($paperStock && $paperCogs) {
            $mst->table('doc_rmprefix1')->whereIn('category1', ['Paper', 'Sheet'])->update([
                'default_stock_asset_account_id' => $paperStock,
                'default_coa_account_id' => $paperCogs,
            ]);
        }

        if ($chemStock && $chemCogs) {
            $mst->table('doc_rmprefix1')->whereIn('category1', ['Ink', 'Glue', 'Chemical Liquid', 'Chemical Powder'])->update([
                'default_stock_asset_account_id' => $chemStock,
                'default_coa_account_id' => $chemCogs,
            ]);
        }
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('doc_rmprefix1', function (Blueprint $table) {
            $table->dropColumn(['default_stock_asset_account_id', 'default_gr_ir_account_id']);
        });
    }
};
