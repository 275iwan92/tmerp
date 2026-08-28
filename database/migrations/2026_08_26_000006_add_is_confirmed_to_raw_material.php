<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Keputusan user 2026-08-26: kategori Item Bahan Baku boleh tetap diedit
// selama item masih DRAFT (belum di-Confirm) -- dikunci beneran cuma setelah
// Confirm, bukan lagi begitu record pertama kali disimpan. Confirm mewajibkan
// 3 akun (stock_asset/cogs/gr_ir) sudah terisi (form entry.blade.php yang
// validasi ini, bukan DB constraint -- boleh beda kombinasi per kasus nanti).
// Nama kolom `stat_confirm` (bukan is_confirmed) ikut DEC-FIELDNAME-STATUS
// (migration 2026_08_26_000007, dijalankan lebih dulu -- is_active di tabel
// ini juga sudah jadi `activate`).
//
// Backfill 5.895 item lama (migration ini, SEKALI jalan):
// 1. Isi akun kosong dari default mat_category level 1 (kalau kategorinya
//    sudah punya default -- 6 dari 18 kategori yg sudah di-seed, migration
//    2026_08_22_000021). TIDAK menimpa akun yang sudah diisi manual per item.
// 2. stat_confirm = true kalau (a) item PERNAH dipakai di transaksi manapun
//    (pr_line/po_line/pq_line/prt_line di tm2pur, ATAU rm_stock/rm_stockcard
//    di tm2inv -- integritas lintas-DB = tanggung jawab Laravel, CLAUDE.md §2,
//    jadi dicek per-koneksi lalu digabung di PHP, bukan JOIN lintas-DB) --
//    supaya tidak diam-diam memblokir item yang sudah berjalan di lapangan.
//    ATAU (b) ketiga akunnya sudah lengkap terisi (baik dari awal maupun
//    hasil backfill #1). Sisanya (item baru yg belum pernah dipakai & akunnya
//    masih kosong) jadi DRAFT -- sesuai instruksi user, bukan tebakan.
return new class extends Migration
{
    public function up(): void
    {
        $mst = Schema::connection('tm2mst');

        if (! $mst->hasColumn('m_raw_material', 'stat_confirm')) {
            $mst->table('m_raw_material', function (Blueprint $table) {
                $table->boolean('stat_confirm')->default(false)
                    ->comment('Kategori & material_code cuma terkunci setelah true -- syarat: 3 akun (stock_asset/cogs/gr_ir) terisi. Set via layar Master Bahan Baku, bukan migration lanjutan.');
                $table->timestamp('confirmed_at')->nullable()->after('stat_confirm');
                $table->string('confirmed_by', 100)->nullable()->after('confirmed_at');
            });
        }

        $this->backfillAccountsFromCategory();
        $this->backfillConfirmedFlag();
    }

    private function backfillAccountsFromCategory(): void
    {
        $mst = DB::connection('tm2mst');

        $categoryDefaults = $mst->table('mat_category')
            ->where('level', 1)
            ->get(['category_name', 'default_stock_asset_account_id', 'default_coa_account_id', 'default_gr_ir_account_id'])
            ->keyBy('category_name');

        foreach ($categoryDefaults as $categoryName => $defaults) {
            $update = [];

            if ($defaults->default_stock_asset_account_id) {
                $update['stock_asset_account_id'] = DB::raw('COALESCE(stock_asset_account_id, '.(int) $defaults->default_stock_asset_account_id.')');
            }
            if ($defaults->default_coa_account_id) {
                $update['cogs_account_id'] = DB::raw('COALESCE(cogs_account_id, '.(int) $defaults->default_coa_account_id.')');
            }
            if ($defaults->default_gr_ir_account_id) {
                $update['gr_ir_account_id'] = DB::raw('COALESCE(gr_ir_account_id, '.(int) $defaults->default_gr_ir_account_id.')');
            }

            if (! empty($update)) {
                $mst->table('m_raw_material')->where('category1', $categoryName)->update($update);
            }
        }
    }

    private function backfillConfirmedFlag(): void
    {
        $mst = DB::connection('tm2mst');
        $pur = DB::connection('tm2pur');
        $inv = DB::connection('tm2inv');

        $transactedIds = collect();

        foreach (['pr_line', 'po_line', 'pq_line', 'prt_line', 'pv_line'] as $table) {
            if (Schema::connection('tm2pur')->hasTable($table)) {
                $transactedIds = $transactedIds->merge($pur->table($table)->whereNotNull('item_id')->distinct()->pluck('item_id'));
            }
        }

        foreach (['rm_stock', 'rm_stockcard'] as $table) {
            if (Schema::connection('tm2inv')->hasTable($table) && Schema::connection('tm2inv')->hasColumn($table, 'material_id')) {
                $transactedIds = $transactedIds->merge($inv->table($table)->whereNotNull('material_id')->distinct()->pluck('material_id'));
            }
        }

        $transactedIds = $transactedIds->unique()->filter()->values();

        $now = now();

        if ($transactedIds->isNotEmpty()) {
            $mst->table('m_raw_material')->whereIn('material_id', $transactedIds)->update([
                'stat_confirm' => true,
                'confirmed_at' => $now,
                'confirmed_by' => 'legacy_backfill_has_transaction',
            ]);
        }

        $mst->table('m_raw_material')
            ->where('stat_confirm', false)
            ->whereNotNull('stock_asset_account_id')
            ->whereNotNull('cogs_account_id')
            ->whereNotNull('gr_ir_account_id')
            ->update([
                'stat_confirm' => true,
                'confirmed_at' => $now,
                'confirmed_by' => 'legacy_backfill_accounts_complete',
            ]);
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('m_raw_material', function (Blueprint $table) {
            $table->dropColumn(['stat_confirm', 'confirmed_at', 'confirmed_by']);
        });
    }
};
