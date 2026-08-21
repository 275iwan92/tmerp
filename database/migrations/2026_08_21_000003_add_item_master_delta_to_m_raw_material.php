<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// ITEM_MASTER_DELTA.md -- perluasan m_raw_material utk layar Master Item
// (mockup_master_rawmaterial.html). Penyesuaian dari draf dokumen (dicatat
// di ITEM_MASTER_DELTA.md via keputusan 2026-08-21):
// - old_material_code/old_material_name TIDAK dibuat -- sudah ada
//   old_item_code/old_item_name (dari dump legacy asli, makna sama).
// - safety_stock/reorder_point/lead_time_days/purchase_tax_code ditambahkan
//   juga meski tak disebut di delta doc §2/§3 -- field ini ADA di mockup
//   (tab Stok & tab UOM/Pengadaan), delta doc-nya sendiri tak lengkap.
// - fin_profit_center dibuat di migration terpisah (lihat 000004) krn perlu
//   verifikasi pola fin_cost_center dulu (PK cc_id, bukan code/name polos).
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2mst');

        if (! $db->hasColumn('m_raw_material', 'hs_code')) {
            $db->table('m_raw_material', function (Blueprint $table) {
                $table->string('hs_code', 20)->nullable()->after('material_code')->comment('Kode HS/tarif bea cukai, mis. 4810.13.00');

                $table->boolean('qc_required')->default(false)->after('specification')->comment('Wajib inspeksi QC saat penerimaan (nyambung ke pv_line.qc_quality_status)');
                $table->unsignedSmallInteger('shelf_life_days')->nullable()->after('qc_required')->comment('Masa simpan (hari), NULL = tak terbatas');
                $table->decimal('moisture_pct', 5, 2)->nullable()->after('shelf_life_days')->comment('Batas kelembaban standar (%)');

                $table->unsignedSmallInteger('lead_time_days')->nullable()->after('tol_purchase')->comment('Estimasi lead time pengadaan (hari)');
                $table->string('purchase_tax_code', 10)->nullable()->default('PPN')->after('lead_time_days')->comment('PPN atau NON_PPN -- pajak masukan default item ini');

                $table->decimal('safety_stock', 20, 4)->unsigned()->nullable()->after('min_stock');
                $table->decimal('reorder_point', 20, 4)->unsigned()->nullable()->after('safety_stock');

                $table->string('valuation_class', 30)->nullable()->after('costing_method')->comment('Label klasifikasi laporan SAJA (bukan penentu akun), mis. "Raw Material - Kertas"');
                $table->unsignedBigInteger('stock_asset_account_id')->nullable()->comment('LOGICAL -> tm2acc.fin_account (is_postable=1) - akun aset persediaan default item ini');
                $table->unsignedBigInteger('cogs_account_id')->nullable()->comment('LOGICAL -> tm2acc.fin_account - akun HPP/beban saat pemakaian');
                $table->unsignedBigInteger('gr_ir_account_id')->nullable()->comment('LOGICAL -> tm2acc.fin_account - akun kliring GR/IR (barang diterima, faktur belum masuk)');
                $table->unsignedBigInteger('cost_center_id')->nullable()->comment('LOGICAL -> tm2acc.fin_cost_center.cc_id - default cost center pemakaian');
                $table->unsignedBigInteger('profit_center_id')->nullable()->comment('LOGICAL -> tm2acc.fin_profit_center.pc_id - dimensi profit center');
            });
        }

        // costing_method: PROC_TIER_3 §7.1 bikin ENUM('AVG','FIFO','LIFO'), ITEM_MASTER_DELTA
        // §1 minta diperluas +STANDARD (harga tetap, gabung konsep price_control V/S SAP
        // supaya tak ada 2 kolom tumpang tindih).
        $current = collect(DB::connection('tm2mst')->select("SHOW COLUMNS FROM m_raw_material WHERE Field = 'costing_method'"))->first();

        if ($current && ! str_contains($current->Type, 'STANDARD')) {
            DB::connection('tm2mst')->statement(
                "ALTER TABLE m_raw_material MODIFY COLUMN costing_method ENUM('AVG','FIFO','LIFO','STANDARD') NOT NULL DEFAULT 'AVG' COMMENT 'Metode harga perolehan PER ITEM - STANDARD = harga tetap'"
            );
        }
    }

    public function down(): void
    {
        $db = Schema::connection('tm2mst');

        if ($db->hasColumn('m_raw_material', 'hs_code')) {
            $db->table('m_raw_material', function (Blueprint $table) {
                $table->dropColumn([
                    'hs_code', 'qc_required', 'shelf_life_days', 'moisture_pct',
                    'lead_time_days', 'purchase_tax_code', 'safety_stock', 'reorder_point',
                    'valuation_class', 'stock_asset_account_id', 'cogs_account_id',
                    'gr_ir_account_id', 'cost_center_id', 'profit_center_id',
                ]);
            });
        }
    }
};
