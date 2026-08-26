<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// inventory_tier3.md §12 langkah 4 ("Bersihkan data legacy") -- WAJIB sebelum unique
// index §3/§5 dipasang. Data ini dump produksi asli (tminv_20260722_2018_sd.sql,
// 66 rm_stock/48 rm_stock_d/36 rm_stockcard), bukan data yang dikarang -- tapi
// spec sendiri mendiagnosis 3 cacat nyata dari bug trigger lama:
// 1. doc_no dobel (mis. 26BB00005 x6, 26OU00014 x5) -- generator nomor lama
//    tidak benar2 increment. Baris TIDAK dihapus, cuma doc_no yang lebih baru
//    ditambah suffix -DUPn supaya unique index bisa dipasang tanpa kehilangan
//    riwayat.
// 2. rm_stock_d.location_id NULL/0 (12 dari 48 baris) -- dibackfill ke satu
//    lokasi fallback "Lokasi Tidak Diketahui" (dibuat di sini) supaya MODIFY
//    location_id NOT NULL (Tier 3 §4) bisa jalan. Baris asli tidak bisa
//    ditelusuri ke lokasi fisik yang benar -- ditandai eksplisit, bukan ditebak.
// 3. rm_stockcard.reference_detail_id dobel (35,49,62,63,64) -- INI bug
//    posting-ganda yang didiagnosis Tier 3 §5 sendiri (merusak saldo
//    180->50->-80). Baris duplikat (doc_id lebih besar) DIHAPUS, hanya
//    baris pertama per reference_detail_id yang disimpan -- bukan
//    kehilangan data, tapi membuang hasil bug.
return new class extends Migration
{
    public function up(): void
    {
        $inv = DB::connection('tm2inv');
        $mst = DB::connection('tm2mst');

        // -- 1. doc_no dobel di rm_stock --
        $dupGroups = $inv->table('rm_stock')
            ->select('doc_no')
            ->whereNotNull('doc_no')
            ->groupBy('doc_no')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('doc_no');

        foreach ($dupGroups as $docNo) {
            $rows = $inv->table('rm_stock')->where('doc_no', $docNo)->orderBy('doc_id')->get(['doc_id']);
            $n = 1;
            foreach ($rows as $row) {
                if ($n > 1) {
                    $inv->table('rm_stock')->where('doc_id', $row->doc_id)->update(['doc_no' => "{$docNo}-DUP{$n}"]);
                }
                $n++;
            }
        }

        // -- 2. location_id NULL/0 di rm_stock_d -> fallback location --
        $whRaw = $mst->table('m_warehouse')->where('warehouse_code', 'WH-RAW-01')->value('id_warehouse');

        if ($whRaw) {
            $fallbackLocId = $mst->table('m_wh_loc')->where('location_code', 'UNKNOWN-LEGACY')->value('location_id');

            if (! $fallbackLocId) {
                $fallbackLocId = $mst->table('m_wh_loc')->insertGetId([
                    'warehouse_id' => $whRaw,
                    'row_code' => 'R00',
                    'col_code' => 'C00',
                    'stack_code' => 'S00',
                    'location_code' => 'UNKNOWN-LEGACY',
                    'location_name' => 'Lokasi Tidak Diketahui (backfill data legacy tminv)',
                    'location_type' => 'STORAGE',
                    'is_usable' => true,
                    'is_active' => true,
                    'created_by' => 'system',
                    'updated_by' => 'system',
                ]);
            }

            $inv->table('rm_stock_d')->where(function ($q) {
                $q->whereNull('location_id')->orWhere('location_id', 0);
            })->update(['location_id' => $fallbackLocId]);
        }

        // -- 3. reference_detail_id dobel di rm_stockcard (bug posting-ganda) --
        $dupRefs = $inv->table('rm_stockcard')
            ->select('reference_detail_id')
            ->whereNotNull('reference_detail_id')
            ->groupBy('reference_detail_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('reference_detail_id');

        foreach ($dupRefs as $refId) {
            $rows = $inv->table('rm_stockcard')->where('reference_detail_id', $refId)->orderBy('doc_id')->get(['doc_id']);
            $keep = true;
            foreach ($rows as $row) {
                if (! $keep) {
                    $inv->table('rm_stockcard')->where('doc_id', $row->doc_id)->delete();
                }
                $keep = false;
            }
        }
    }

    public function down(): void
    {
        // Tidak reversibel dengan aman (data asli sudah diubah/dihapus) --
        // dump mentah tetap ada di database/legacy/tminv_20260722_2018_sd.sql
        // untuk restore manual bila benar2 dibutuhkan.
    }
};
