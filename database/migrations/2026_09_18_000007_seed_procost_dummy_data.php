<?php

use App\Models\Sdi\IqpCosting;
use App\Services\Costing\CostingEngine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// feedback_keep_dummy_data: grid Product Costing (Integrated & Instant) baru
// dibangun 2026-09-18, kosong/nyaris kosong -- tidak bisa didemokan. Seed data
// dummy REALISTIS (10 produk beda utk Integrated + 6 utk Instant), dihitung
// SUNGGUHAN lewat CostingEngine (bukan angka tebakan) supaya grid & ladder
// harga langsung kelihatan hidup. Ditandai inquiry_no prefix 'IQ99DUM' &
// data_origin='DUMMY' di semua tabel tersentuh (iqp_inquiry/part/tier/costing/
// costing_part/costing_price_tier) supaya down() presisi & bisa direset lewat
// Setup > Dummy & Legacy Data (DataOriginService) tanpa nyentuh data real.
return new class extends Migration
{
    public function up(): void
    {
        $sdi = DB::connection('tm2sdi');
        $mst = DB::connection('tm2mst');
        $now = now();

        $customerIds = $mst->table('cust_customer')->where('activate', true)->orderBy('id_customer')->limit(20)->pluck('id_customer')->all();
        $grammageIds = $mst->table('mat_grammage')->where('activate', true)->pluck('id_grammage')->all();

        if (empty($customerIds) || empty($grammageIds)) {
            return; // master belum ada -- jangan gagal migrate, cukup lewati seed dummy ini
        }

        // ============ 10 PRODUK BEDA -- MODE INTEGRATED (lewat Inquiry) ============
        $integratedProducts = [
            ['Folding Box Kosmetik Duplex', 135, 108, 140],
            ['Rigid Box Parfum Premium', 90, 90, 160],
            ['Karton Box Susu Bubuk 400gr', 110, 70, 180],
            ['Dus Sepatu Anak', 260, 160, 110],
            ['Kotak Obat Farmasi Strip', 80, 45, 120],
            ['Kemasan Snack Duplex', 150, 100, 60],
            ['Box Mainan Anak Edukasi', 300, 200, 150],
            ['Kemasan Kue Kering Lebaran', 200, 200, 80],
            ['Dus Elektronik Charger', 120, 80, 50],
            ['Packaging Skincare Set', 175, 120, 90],
        ];

        foreach ($integratedProducts as $seq => [$name, $p, $l, $t]) {
            $n = $seq + 1;
            $custId = $customerIds[$seq % count($customerIds)];
            $grammageId = $grammageIds[$seq % count($grammageIds)];

            $inquiryId = $sdi->table('iqp_inquiry')->insertGetId([
                'inquiry_no' => 'IQ99DUM'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'inquiry_date' => $now->copy()->subDays(random_int(1, 30))->toDateString(),
                'source' => 'DUMMY',
                'customer_id' => $custId,
                'is_prospect' => false,
                'product_draft_name' => $name,
                'inquiry_type' => 'NEW',
                'sub_state' => 'PRICE_CALCULATED',
                'stat_confirm' => true,
                'stat_cancel' => false,
                'is_approved' => false,
                'created_by' => 'system', 'updated_by' => 'system',
                'created_at' => $now, 'updated_at' => $now,
                'data_origin' => 'DUMMY',
            ], 'id_inquiry');

            $partId = $sdi->table('iqp_part')->insertGetId([
                'inquiry_id' => $inquiryId,
                'part_seq' => 1, 'part_code' => 'P1', 'part_name' => 'Body',
                'qty_per_sku' => 1,
                'flat_w' => $p, 'flat_h' => $l, 'flat_area' => $p * $l,
                'created_by' => 'system', 'updated_by' => 'system',
                'created_at' => $now, 'updated_at' => $now,
                'data_origin' => 'DUMMY',
            ], 'id_part');

            $tierQtys = [500, 2000];
            $tierIds = [];
            foreach ($tierQtys as $ti => $qty) {
                $tierIds[] = $sdi->table('iqp_tier')->insertGetId([
                    'inquiry_id' => $inquiryId, 'tier_seq' => $ti + 1, 'qty' => $qty,
                    'is_primary' => $ti === 0,
                    'created_by' => 'system', 'updated_by' => 'system',
                    'created_at' => $now, 'updated_at' => $now,
                    'data_origin' => 'DUMMY',
                ], 'id_tier');
            }

            $costingId = $sdi->table('iqp_costing')->insertGetId([
                'inquiry_id' => $inquiryId, 'mode' => IqpCosting::MODE_INTEGRATED,
                'customer_id' => $custId, 'product_name_draft' => $name,
                'calc_type' => 'NEW', 'doc_status' => 'DRAFT',
                'i_dim_panjang_mm' => $p, 'i_dim_lebar_mm' => $l, 'i_dim_tinggi_mm' => $t,
                'ppn_mode' => 'PPN', 'ppn_pct' => 11,
                'created_by' => 'system', 'updated_by' => 'system',
                'created_at' => $now, 'updated_at' => $now,
                'data_origin' => 'DUMMY',
            ], 'id_costing');

            foreach ($tierIds as $idx => $tierId) {
                $sdi->table('iqp_costing_price_tier')->insert([
                    'costing_id' => $costingId, 'tier_id' => $tierId,
                    'qty_pcs' => $tierQtys[$idx], 'is_primary' => $idx === 0,
                    'tolerance_pct' => 5, 'margin_pct' => 15, 'rounding_mode' => 'NEAREST', 'rounding_step' => 1,
                    'created_by' => 'system', 'updated_by' => 'system',
                    'created_at' => $now, 'updated_at' => $now,
                    'data_origin' => 'DUMMY',
                ]);
            }

            $sdi->table('iqp_costing_part')->insert([
                'costing_id' => $costingId, 'part_id' => $partId, 'no_urut' => 1,
                'part_name' => 'Body', 'part_type_code' => 'DC', 'data_source' => 'MANUAL',
                'qty_per_set' => 1,
                'grammage_id' => $grammageId,
                'i_panjang_plano_cm' => 65, 'i_lebar_plano_cm' => 90,
                'i_cut_per_plano' => random_int(6, 12), 'i_isi_per_lb' => 1, 'i_jumlah_warna' => 4,
                'i_ongkos_cetak_rim' => 450000 + ($seq * 10000),
                'i_ongkos_plong_lb' => 70, 'i_ongkos_lem_pc' => 25,
                'i_film_faktor' => 0.25, 'i_pisau_faktor' => 0.05, 'i_pisau_rate_rp' => 550000,
                'created_by' => 'system', 'updated_by' => 'system',
                'created_at' => $now, 'updated_at' => $now,
                'data_origin' => 'DUMMY',
            ]);

            $costing = IqpCosting::find($costingId);
            (new CostingEngine)->run($costing);
        }

        // ============ 6 DUMMY -- MODE INSTANT (standalone, tanpa Inquiry) ============
        $instantProducts = [
            ['Simulasi Dus Sepatu Ekspor', 250, 150, 100],
            ['Kalkulasi Cepat Box Parfum B', 85, 85, 150],
            ['Estimasi Kemasan Snack Baru', 140, 95, 55],
            ['Cek Harga Box Obat Generik', 75, 40, 110],
            ['Simulasi Kotak Souvenir', 120, 120, 60],
            ['Draft Kemasan Elektronik Mini', 100, 70, 40],
        ];

        foreach ($instantProducts as $seq => [$name, $p, $l, $t]) {
            $custId = $customerIds[($seq + 3) % count($customerIds)];
            $grammageId = $grammageIds[$seq % count($grammageIds)];

            $costingId = $sdi->table('iqp_costing')->insertGetId([
                'inquiry_id' => null, 'mode' => IqpCosting::MODE_INSTANT,
                'customer_id' => $custId, 'product_name_draft' => $name,
                'calc_type' => 'NEW', 'doc_status' => 'DRAFT',
                'i_dim_panjang_mm' => $p, 'i_dim_lebar_mm' => $l, 'i_dim_tinggi_mm' => $t,
                'ppn_mode' => 'PPN', 'ppn_pct' => 11,
                'created_by' => 'system', 'updated_by' => 'system',
                'created_at' => $now, 'updated_at' => $now,
                'data_origin' => 'DUMMY',
            ], 'id_costing');

            $sdi->table('iqp_costing_price_tier')->insert([
                'costing_id' => $costingId, 'tier_id' => null,
                'qty_pcs' => 1000, 'is_primary' => true,
                'tolerance_pct' => 5, 'margin_pct' => 12, 'rounding_mode' => 'NEAREST', 'rounding_step' => 1,
                'created_by' => 'system', 'updated_by' => 'system',
                'created_at' => $now, 'updated_at' => $now,
                'data_origin' => 'DUMMY',
            ]);

            $sdi->table('iqp_costing_part')->insert([
                'costing_id' => $costingId, 'part_id' => null, 'no_urut' => 1,
                'part_name' => 'Body', 'part_type_code' => 'DC', 'data_source' => 'MANUAL',
                'qty_per_set' => 1,
                'grammage_id' => $grammageId,
                'i_panjang_plano_cm' => 60, 'i_lebar_plano_cm' => 85,
                'i_cut_per_plano' => random_int(6, 10), 'i_isi_per_lb' => 1, 'i_jumlah_warna' => 4,
                'i_ongkos_cetak_rim' => 420000 + ($seq * 8000),
                'i_ongkos_plong_lb' => 65, 'i_ongkos_lem_pc' => 20,
                'i_film_faktor' => 0.25, 'i_pisau_faktor' => 0.05, 'i_pisau_rate_rp' => 500000,
                'created_by' => 'system', 'updated_by' => 'system',
                'created_at' => $now, 'updated_at' => $now,
                'data_origin' => 'DUMMY',
            ]);

            $costing = IqpCosting::find($costingId);
            (new CostingEngine)->run($costing);
        }
    }

    public function down(): void
    {
        $sdi = DB::connection('tm2sdi');

        // Instant: identifikasi via mode+data_origin+created_by (tak ada inquiry_no).
        $instantIds = $sdi->table('iqp_costing')->where('mode', 'INSTANT')->where('data_origin', 'DUMMY')->where('created_by', 'system')->pluck('id_costing');
        $sdi->table('iqp_costing_part')->whereIn('costing_id', $instantIds)->delete();
        $sdi->table('iqp_costing_price_tier')->whereIn('costing_id', $instantIds)->delete();
        $sdi->table('iqp_costing')->whereIn('id_costing', $instantIds)->delete();

        // Integrated: presisi via inquiry_no prefix (sama pola dgn seeder ProDev dummy).
        $inquiryIds = $sdi->table('iqp_inquiry')->where('inquiry_no', 'like', 'IQ99DUM%')->pluck('id_inquiry');
        $costingIds = $sdi->table('iqp_costing')->whereIn('inquiry_id', $inquiryIds)->pluck('id_costing');
        $sdi->table('iqp_costing_part')->whereIn('costing_id', $costingIds)->delete();
        $sdi->table('iqp_costing_price_tier')->whereIn('costing_id', $costingIds)->delete();
        $sdi->table('iqp_costing')->whereIn('id_costing', $costingIds)->delete();
        $sdi->table('iqp_tier')->whereIn('inquiry_id', $inquiryIds)->delete();
        $sdi->table('iqp_part')->whereIn('inquiry_id', $inquiryIds)->delete();
        $sdi->table('iqp_inquiry')->whereIn('id_inquiry', $inquiryIds)->delete();
    }
};
