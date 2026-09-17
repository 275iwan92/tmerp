<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Dua hal, keduanya instruksi user 2026-09-17.
//
// A. SAMPEL UAT jadi data NYATA. Sebelumnya AL SAMSI & OSIMA cuma job demo
//    hardcode di JavaScript, sehingga tidak pernah muncul di grid dan tidak
//    bisa dipakai UAT ("Kedua sample itu HARUS ada buat UAT, agar saya bisa
//    melihat mulai artwork dan lainnya"). Sekarang dibuatkan rantai lengkap
//    Inquiry -> prod_product -> prod_part supaya muncul di grid, membawa nama
//    pelanggan & no inquiry, dan tombol upload artwork-nya hidup.
//    Angka dimensinya dari mockup_prodev_imposition.html -- itu 3 job cetak
//    riil (AL SAMSI Sarung Tenun & PT. Selatan Jadi Jaya), ukuran blank-nya
//    hasil ukur dari gambar dieline, bukan rumus.
//
// B. GEOMETRI part 2 dibetulkan mengikuti PRODUK INDUKNYA (user: "ikuti produk
//    induk nya 12 x 8 x 4"). Part 2 "Body Box" tertulis 30 x 20 tanpa tinggi,
//    padahal produk induknya "Folding Box Duplex 350gr Kosmetik" berukuran
//    12 x 8 x 4 cm -- angka part-nya bertentangan dengan induknya dan tidak
//    mungkin benar untuk body box produk sebesar itu.
//    Bentangannya dihitung pakai rumus dieline SUNGGUHAN milik tipe folding
//    carton di sistem ini (prod_type CARD_BOX/RTE_BOX/STE_BOX):
//        flat_w = G + 2A + 2B + 2P      (lidah lem + 2 x panjang + 2 x lebar)
//        flat_h = max(TT,DF) + C + max(BT,DF) + 2P
//    Nilai lidah memakai default yang sudah dipakai data dieline lain di DB
//    (glue 1,5 cm; tuck atas/bawah 1,5 cm; dust flap 1,0 cm; margin 0,5 cm).
//    Ini MENGGANTIKAN angka dari migration 2026_09_17_000001 yang memakai
//    pendekatan kasar "sisi box + 2 cm" -- rumus itu bukan bentangan karton.
return new class extends Migration
{
    // cm
    private const GLUE = 1.5;
    private const TUCK = 1.5;
    private const DUST = 1.0;
    private const MARGIN = 0.5;
    private const BLEED = 0.3;

    private const SAMPLES = [
        [
            'code' => 'UAT-ALSAMSI-TOP',
            'customer' => 'AL SAMSI - SARUNG TENUN',
            'product' => 'AL SAMSI Exclusive Top - Sarung Tenun',
            'design_no' => 'DS002047',
            'part' => 'Top Lid (Tray Lid 4 Corner Lock)',
            'box' => [26.5, 20.0, 5.5],     // panjang, lebar, tinggi jadi
            'blank' => [42.0, 35.5],        // hasil ukur dari gambar dieline
            'joint' => 'Tray Lid 4 Corner Lock',
            'color' => '4/0',
            'finishing' => 'Gold Foil + UV Spot, Art Carton 310 gsm',
            'inquiry_no' => 'IQUAT260901',
        ],
        [
            'code' => 'UAT-OSIMA',
            'customer' => 'PT. SELATAN JADI JAYA',
            'product' => 'OSIMA MS N100 CV - Battery Carton',
            'design_no' => 'DS003146',
            'part' => 'Wrap Panel A+B (Hand Hole)',
            'box' => [41.3, 18.0, 26.0],
            'blank' => [50.0, 40.5],
            'joint' => '2-Piece Wrap + Hand Hole',
            'color' => '4/0',
            'finishing' => 'Duplex 400 gsm / E-flute Laminated',
            'inquiry_no' => 'IQUAT260902',
        ],
        [
            'code' => 'UAT-ALSAMSI-SIG',
            'customer' => 'AL SAMSI - SARUNG TENUN',
            'product' => 'AL SAMSI Signature - Lock Bottom 10 Pcs',
            'design_no' => 'TM 25-11.25',
            'part' => 'Inner Box (Lock Bottom Blank A)',
            'box' => [37.5, 19.7, 25.5],
            'blank' => [59.2, 39.5],
            'joint' => 'Lock Bottom (Blank A+B)',
            'color' => '4/0',
            'finishing' => 'Gold Foil, Ivory / Duplex 350 gsm',
            'inquiry_no' => 'IQUAT260903',
        ],
    ];

    public function up(): void
    {
        $mst = DB::connection('tm2mst');
        $sdi = DB::connection('tm2sdi');
        $now = now();

        foreach (self::SAMPLES as $s) {
            $customerId = $mst->table('cust_customer')->where('customer_name', $s['customer'])->value('id_customer');

            if (! $customerId) {
                $customerId = $mst->table('cust_customer')->insertGetId([
                    'customer_code' => 'UAT-'.substr(md5($s['customer']), 0, 6),
                    'customer_name' => $s['customer'],
                    'activate' => 1,
                    'created_at' => $now, 'updated_at' => $now,
                    'created_by' => 'claude_code', 'updated_by' => 'claude_code',
                ], 'id_customer');
            }

            [$boxL, $boxW, $boxH] = $s['box'];
            [$blankL, $blankW] = $s['blank'];

            $productId = $mst->table('prod_product')->where('product_code', $s['code'])->value('id_product');

            if (! $productId) {
                $productId = $mst->table('prod_product')->insertGetId([
                    'product_code' => $s['code'],
                    'product_name' => $s['product'],
                    'design_no' => $s['design_no'],
                    'customer_id' => $customerId,
                    'cm_length' => $boxL, 'cm_width' => $boxW, 'cm_height' => $boxH,
                    'card_status' => 'DRAFT',
                    'activate' => 1,
                    'created_at' => $now, 'updated_at' => $now,
                    'created_by' => 'claude_code', 'updated_by' => 'claude_code',
                ], 'id_product');
            }

            $partId = $mst->table('prod_part')->where('mastercard_code', $s['code'])->value('id_part');

            if (! $partId) {
                $partId = $mst->table('prod_part')->insertGetId([
                    'product_id' => $productId,
                    'part_code' => $s['code'],
                    'part_name' => $s['part'],
                    'part_type' => 'DC',
                    'mastercard_code' => $s['code'],
                    'cm_length' => $boxL, 'cm_width' => $boxW, 'cm_height' => $boxH,
                    'die_w_cm' => $blankL, 'die_h_cm' => $blankW,
                    'sheet_raw_w_cm' => round($blankL + 2 * self::BLEED, 2),
                    'sheet_raw_h_cm' => round($blankW + 2 * self::BLEED, 2),
                    'joint_type' => $s['joint'],
                    'cfg_color' => $s['color'],
                    'finishing' => $s['finishing'],
                    'activate' => 1,
                    'created_at' => $now, 'updated_at' => $now,
                    'created_by' => 'claude_code', 'updated_by' => 'claude_code',
                ], 'id_part');
            }

            // Rantai Inquiry supaya nama pelanggan & no inquiry tampil di layar
            // kerja, dan upload artwork (iqp_part.artwork_path) bisa dipakai.
            $inquiryId = $sdi->table('iqp_inquiry')->where('inquiry_no', $s['inquiry_no'])->value('id_inquiry');

            if (! $inquiryId) {
                $inquiryId = $sdi->table('iqp_inquiry')->insertGetId([
                    'inquiry_no' => $s['inquiry_no'],
                    'inquiry_date' => $now->toDateString(),
                    'product_draft_name' => $s['product'],
                    'customer_id' => $customerId,
                    'inquiry_type' => 'NEW',
                    'stat_confirm' => 1,
                    'created_at' => $now, 'updated_at' => $now,
                    'created_by' => 'claude_code', 'updated_by' => 'claude_code',
                ], 'id_inquiry');
            }

            if (! $sdi->table('iqp_part')->where('prod_part_id', $partId)->exists()) {
                $sdi->table('iqp_part')->insert([
                    'inquiry_id' => $inquiryId,
                    'prod_part_id' => $partId,
                    'part_seq' => 1,
                    'part_code' => $s['code'],
                    'part_name' => $s['part'],
                    'part_type' => 'DC',
                    'cfg_color' => $s['color'],
                    'flat_w' => $blankL, 'flat_h' => $blankW,
                    'flat_area' => round($blankL * $blankW, 2),
                    'created_at' => $now, 'updated_at' => $now,
                    'created_by' => 'claude_code', 'updated_by' => 'claude_code',
                ]);
            }
        }

        $this->fixPart2($mst);
    }

    // Bentangan folding carton sungguhan, bukan "sisi box + 2 cm".
    private function fixPart2($mst): void
    {
        $part = $mst->table('prod_part')->where('id_part', 2)->first();

        if (! $part) {
            return;
        }

        $product = $mst->table('prod_product')->where('id_product', $part->product_id)->first();

        if (! $product || ! $product->cm_length || ! $product->cm_width || ! $product->cm_height) {
            return;
        }

        $a = (float) $product->cm_length;
        $b = (float) $product->cm_width;
        $c = (float) $product->cm_height;

        $flatW = self::GLUE + 2 * $a + 2 * $b + 2 * self::MARGIN;
        $flatH = max(self::TUCK, self::DUST) + $c + max(self::TUCK, self::DUST) + 2 * self::MARGIN;

        $mst->table('prod_part')->where('id_part', 2)->update([
            'cm_length' => $a, 'cm_width' => $b, 'cm_height' => $c,
            'die_w_cm' => round($flatW, 2), 'die_h_cm' => round($flatH, 2),
            'sheet_raw_w_cm' => round($flatW + 2 * self::BLEED, 2),
            'sheet_raw_h_cm' => round($flatH + 2 * self::BLEED, 2),
            'joint_type' => 'Glue Flap (Tuck End)',
            'updated_by' => 'claude_code',
        ]);
    }

    public function down(): void
    {
        $mst = DB::connection('tm2mst');
        $sdi = DB::connection('tm2sdi');
        $codes = array_column(self::SAMPLES, 'code');

        $partIds = $mst->table('prod_part')->whereIn('mastercard_code', $codes)->pluck('id_part');
        $sdi->table('iqp_part')->whereIn('prod_part_id', $partIds)->delete();
        $sdi->table('iqp_inquiry')->whereIn('inquiry_no', array_column(self::SAMPLES, 'inquiry_no'))->delete();
        $mst->table('prod_part')->whereIn('mastercard_code', $codes)->delete();
        $mst->table('prod_product')->whereIn('product_code', $codes)->delete();

        // Customer & part 2 TIDAK disentuh saat rollback: customer bisa keburu
        // dipakai transaksi lain, dan angka part 2 yang benar lebih baik
        // dipertahankan daripada dikembalikan ke data yang bertentangan.
    }
};
