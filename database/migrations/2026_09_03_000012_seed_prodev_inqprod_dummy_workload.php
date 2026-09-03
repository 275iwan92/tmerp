<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// feedback_keep_dummy_data: prodev-list.blade.php (grid Pro-Dev baru) & Inquiry
// Product hub kosong/nyaris kosong -- tidak bisa didemokan/dites nyata. Isi
// beban kerja dummy REALISTIS, diminta persis oleh user 2026-09-03:
//   InqProd 66 inkuiri: NEW 17 confirm/11 draft, RECALC 9 confirm/11 draft,
//   REVISE 10 confirm/8 draft (1 iqp_part per inkuiri, biar nongol di grid
//   imposition-list/costing-list yg syaratnya whereHas('parts')).
//   ProDev 33 prod_part: 25 Mode #1 (link ke iqp_part CONFIRMED, ambil dari
//   36 inkuiri confirmed di atas) + 8 Mode #2 (standalone, tanpa iqp_part) --
//   dari 25 Mode#1: 16 draft layout + 9 confirmed layout; dari 8 Mode#2:
//   5 draft + 3 confirmed -- total 21 draft + 12 confirmed (persis diminta).
// Semua baris ditandai product_code/mastercard_code prefix 'PDDUM-' &
// inquiry_no prefix 'IQ2690' (jauh di atas sequence real yg baru sampai
// ratusan) supaya down() bisa hapus presisi tanpa nyentuh data lain.
return new class extends Migration
{
    public function up(): void
    {
        $sdi = DB::connection('tm2sdi');
        $mst = DB::connection('tm2mst');
        $now = now();

        $customerIds = $mst->table('cust_customer')->orderBy('id_customer')->limit(80)->pluck('id_customer')->all();
        if (empty($customerIds)) {
            $customerIds = [null];
        }

        $productNames = [
            'Folding Box Duplex Kosmetik', 'Folding Box Karton Makanan', 'Corrugated Box Elektronik',
            'Kraft Box Souvenir', 'Sheet Label Kemasan', 'Rigid Box Parfum', 'Duplex Box Farmasi',
            'Karton Box Mainan', 'Paper Bag Belanja', 'Hang Tag Produk', 'Inner Box Sepatu',
            'Master Box Ekspor', 'Sleeve Box Kado', 'Display Box Retail', 'Divider Karton',
        ];

        // [type, confirmed, count] -- total 66 sesuai permintaan user.
        $specs = [
            ['NEW', true, 17],
            ['NEW', false, 11],
            ['RECALC', false, 11],
            ['RECALC', true, 9],
            ['REVISE', false, 8],
            ['REVISE', true, 10],
        ];

        $created = []; // ['inquiry_id','part_id','confirmed','product_draft_name']
        $seq = 0;

        foreach ($specs as [$type, $confirmed, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $seq++;
                $custId = $customerIds[$seq % count($customerIds)];
                $prodName = $productNames[$seq % count($productNames)].' #'.$seq;
                $inquiryNo = 'IQ2690'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);

                $inquiryId = $sdi->table('iqp_inquiry')->insertGetId([
                    'inquiry_no' => $inquiryNo,
                    'inquiry_date' => $now->copy()->subDays(random_int(1, 75))->toDateString(),
                    'source' => 'DUMMY',
                    'customer_id' => $custId,
                    'is_prospect' => false,
                    'product_draft_name' => $prodName,
                    'inquiry_type' => $type,
                    'sub_state' => $confirmed ? 'SUBMITTED' : 'DRAFT',
                    'stat_confirm' => $confirmed,
                    'stat_cancel' => false,
                    'is_approved' => false,
                    'created_by' => 'system',
                    'updated_by' => 'system',
                    'created_at' => $now,
                    'updated_at' => $now,
                ], 'id_inquiry');

                $flatW = random_int(150, 450);
                $flatH = random_int(150, 450);

                $partId = $sdi->table('iqp_part')->insertGetId([
                    'inquiry_id' => $inquiryId,
                    'part_seq' => 1,
                    'part_code' => 'P1',
                    'part_name' => 'Body',
                    'qty_per_sku' => 1,
                    'flat_w' => $flatW,
                    'flat_h' => $flatH,
                    'flat_area' => $flatW * $flatH,
                    'dieline_spec' => json_encode([
                        'dims' => [
                            'product_length' => $flatW,
                            'product_width' => $flatH,
                            'product_height' => random_int(30, 150),
                        ],
                    ]),
                    'created_by' => 'system',
                    'updated_by' => 'system',
                    'created_at' => $now,
                    'updated_at' => $now,
                ], 'id_part');

                $created[] = [
                    'inquiry_id' => $inquiryId,
                    'part_id' => $partId,
                    'type' => $type,
                    'confirmed' => $confirmed,
                    'product_draft_name' => $prodName,
                    'customer_id' => $custId,
                ];
            }
        }

        // ProDev Mode #1: HANYA boleh bersumber dari inkuiri tipe NEW/REVISE yang
        // CONFIRMED (CLAUDE.md §4 LOCKED -- Mode #1 = "utk Inquiry BARU"; REVISE
        // masuk akal juga krn spec-nya BERUBAH, jadi Master Card lama tak lagi
        // valid, perlu ProDev ulang). RECALC (spec SAMA, cuma harga bahan
        // berubah) SENGAJA DIKECUALIKAN -- tidak ada alasan re-run ProDev kalau
        // desain fisiknya tidak berubah sama sekali, layout lama masih valid.
        // Pool: 17 NEW-confirmed + 10 REVISE-confirmed = 27, cukup utk 25 --
        // 9 RECALC-confirmed & sisa 2 REVISE-confirmed sengaja dibiarkan
        // "confirmed tapi belum dikirim ke ProDev" (backlog realistis).
        $confirmedPool = array_values(array_filter($created, fn ($r) => $r['confirmed'] && in_array($r['type'], ['NEW', 'REVISE'], true)));
        $mode1 = array_slice($confirmedPool, 0, 25);

        foreach ($mode1 as $idx => $row) {
            $layoutStatus = $idx < 16 ? 'DRAFT' : 'CONFIRMED';
            $this->createProdevPart($mst, $now, 'PDDUM1-'.($idx + 1), $row['product_draft_name'], $row['customer_id'], $layoutStatus, $row['part_id'], $sdi);
        }

        // ProDev Mode #2 (standalone, tanpa iqp_part): 5 draft + 3 confirmed.
        for ($i = 0; $i < 8; $i++) {
            $layoutStatus = $i < 5 ? 'DRAFT' : 'CONFIRMED';
            $prodName = $productNames[($i + 30) % count($productNames)].' (Manual) #'.($i + 1);
            $this->createProdevPart($mst, $now, 'PDDUM2-'.($i + 1), $prodName, null, $layoutStatus, null, $sdi);
        }
    }

    private function createProdevPart($mst, $now, string $code, string $productName, ?int $customerId, string $layoutStatus, ?int $iqpPartId, $sdi): void
    {
        $productId = $mst->table('prod_product')->insertGetId([
            'product_code' => $code,
            'product_name' => $productName,
            'customer_id' => $customerId,
            'activate' => true,
            'created_by' => 'system',
            'updated_by' => 'system',
            'created_at' => $now,
            'updated_at' => $now,
        ], 'id_product');

        $cmLength = random_int(15, 45);
        $cmWidth = random_int(15, 45);

        $partId = $mst->table('prod_part')->insertGetId([
            'product_id' => $productId,
            'part_code' => $code,
            'part_name' => $productName,
            'cm_length' => $cmLength,
            'cm_width' => $cmWidth,
            'activate' => true,
            'created_by' => 'system',
            'updated_by' => 'system',
            'created_at' => $now,
            'updated_at' => $now,
        ], 'id_part');

        if ($iqpPartId) {
            $sdi->table('iqp_part')->where('id_part', $iqpPartId)->update(['prod_part_id' => $partId]);
        }

        $layoutGroupId = $mst->table('prod_layout_group')->insertGetId([
            'product_id' => $productId,
            'mastercard_code' => $code,
            'up_count' => random_int(4, 16),
            'method' => 'TEGAK',
            'status' => $layoutStatus,
            'confirmed_at' => $layoutStatus === 'CONFIRMED' ? $now : null,
            'confirmed_by' => $layoutStatus === 'CONFIRMED' ? 'system' : null,
            'created_by' => 'system',
            'updated_by' => 'system',
            'created_at' => $now,
            'updated_at' => $now,
        ], 'id_layout_group');

        $mst->table('prod_layout_group_part')->insert([
            'id_layout_group' => $layoutGroupId,
            'part_id' => $partId,
            'qty_up_in_group' => 1,
            'created_at' => $now,
        ]);
    }

    public function down(): void
    {
        $sdi = DB::connection('tm2sdi');
        $mst = DB::connection('tm2mst');

        $partIds = $mst->table('prod_part')->where('part_code', 'like', 'PDDUM%')->pluck('id_part');
        $productIds = $mst->table('prod_product')->where('product_code', 'like', 'PDDUM%')->pluck('id_product');
        $groupIds = $mst->table('prod_layout_group')->where('mastercard_code', 'like', 'PDDUM%')->pluck('id_layout_group');

        $sdi->table('iqp_part')->whereIn('prod_part_id', $partIds)->update(['prod_part_id' => null]);

        $mst->table('prod_layout_group_part')->whereIn('id_layout_group', $groupIds)->delete();
        $mst->table('prod_layout_group')->whereIn('id_layout_group', $groupIds)->delete();
        $mst->table('prod_part')->whereIn('id_part', $partIds)->delete();
        $mst->table('prod_product')->whereIn('id_product', $productIds)->delete();

        $inquiryIds = $sdi->table('iqp_inquiry')->where('inquiry_no', 'like', 'IQ2690%')->pluck('id_inquiry');
        $sdi->table('iqp_part')->whereIn('inquiry_id', $inquiryIds)->delete();
        $sdi->table('iqp_inquiry')->whereIn('id_inquiry', $inquiryIds)->delete();
    }
};
