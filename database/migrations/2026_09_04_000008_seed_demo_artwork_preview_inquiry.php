<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Demo khusus (permintaan user 2026-09-04: "coba beri data dummy buat saya
// lihat preview sebesar apa") -- inquiry BARU/DRAFT terpisah dari data
// pengujian lain, isinya 1 part CARD_BOX + artwork contoh (di-generate PHP
// GD, bukan file eksternal) supaya user bisa langsung buka & lihat thumbnail
// preview (48x48, klik utk zoom) tanpa perlu upload manual dulu. Tetap
// DRAFT/belum confirm -- boleh dites upload/ganti artwork juga.
return new class extends Migration
{
    public function up(): void
    {
        $sdi = DB::connection('tm2sdi');

        if ($sdi->table('iqp_inquiry')->where('sales_note', 'DEMO_ARTWORK_PREVIEW')->exists()) {
            return;
        }

        $customerId = DB::connection('tm2mst')->table('cust_customer')->where('activate', true)->value('id_customer');

        $prefix = 'IQ'.now()->format('y');
        $lastNo = $sdi->table('iqp_inquiry')->where('inquiry_no', 'like', "{$prefix}%")->orderByDesc('inquiry_no')->value('inquiry_no');
        $seq = $lastNo ? ((int) substr($lastNo, 4)) + 1 : 1;
        $inquiryNo = $prefix.str_pad((string) $seq, 6, '0', STR_PAD_LEFT);

        $inquiryId = $sdi->table('iqp_inquiry')->insertGetId([
            'inquiry_no' => $inquiryNo,
            'inquiry_date' => now()->toDateString(),
            'source' => 'EMAIL',
            'pic_sales' => 'Demo Preview',
            'customer_id' => $customerId,
            'is_prospect' => false,
            'product_draft_name' => 'DEMO - Preview Upload Artwork',
            'sales_note' => 'DEMO_ARTWORK_PREVIEW',
            'inquiry_type' => 'NEW',
            'material_source' => 'OWN',
            'job_nature' => 'PRODUCT',
            'tax_scheme' => 'PPN',
            'sub_state' => 'DRAFT',
            'stat_confirm' => false,
            'stat_cancel' => false,
            'created_by' => 'system',
            'updated_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sdi->table('iqp_tier')->insert([
            'inquiry_id' => $inquiryId,
            'tier_seq' => 1,
            'qty' => 10000,
            'is_primary' => true,
            'created_by' => 'system',
            'updated_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $type = DB::connection('tm2mst')->table('prod_type')->where('type_code', 'CARD_BOX')->first();
        $dielineSpec = null;

        if ($type && $type->dieline_spec) {
            $dielineSpec = json_decode($type->dieline_spec, true);
            $dielineSpec['dims'] = [
                'product_length' => '250', 'product_width' => '180', 'product_height' => '80',
                'glue_flap' => '13', 'top_tuck_flap' => '15', 'dust_flap' => '12',
                'bottom_tuck_flap' => '15', 'overflow_margin' => '3',
            ];
            $dielineSpec['type_code'] = 'CARD_BOX';
        }

        $partId = $sdi->table('iqp_part')->insertGetId([
            'inquiry_id' => $inquiryId,
            'part_seq' => 1,
            'part_name' => 'Body',
            'part_type' => 'DC',
            'qty_per_sku' => 1,
            'cfg_color' => '4/0',
            'dieline_spec' => $dielineSpec ? json_encode($dielineSpec) : null,
            'flat_w' => 545.5,
            'flat_h' => 380,
            'flat_area' => 207290,
            'created_by' => 'system',
            'updated_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Artwork contoh -- generate lewat GD (bukan aset eksternal), simulasi
        // label box warna-warni + teks, biar preview terasa "nyata" bukan
        // kotak polos. Disk 'public' spy sama persis dgn upload sungguhan.
        $img = imagecreatetruecolor(600, 600);
        $bg = imagecolorallocate($img, 255, 247, 230);
        $accent = imagecolorallocate($img, 226, 130, 40);
        $navy = imagecolorallocate($img, 44, 62, 105);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 0, 0, 599, 599, $bg);
        imagefilledrectangle($img, 0, 0, 599, 140, $navy);
        imagefilledellipse($img, 300, 340, 380, 380, $accent);
        imagefilledellipse($img, 300, 340, 260, 260, $white);
        imagestring($img, 5, 190, 50, 'CONTOH ARTWORK', $white);
        imagestring($img, 3, 190, 90, 'DEMO PREVIEW UPLOAD', $white);
        imagestring($img, 5, 205, 320, 'BOX KOSMETIK', $navy);
        imagestring($img, 3, 235, 345, 'Serum 30ml', $navy);
        imagerectangle($img, 10, 10, 589, 589, $accent);

        $relativePath = "inquiry/{$partId}/artwork/demo-sample.jpg";
        $absoluteDir = Storage::disk('public')->path("inquiry/{$partId}/artwork");

        if (! is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0755, true);
        }

        imagejpeg($img, Storage::disk('public')->path($relativePath), 88);
        imagedestroy($img);

        $sdi->table('iqp_part')->where('id_part', $partId)->update([
            'artwork_path' => $relativePath,
            'artwork_original_filename' => 'demo-sample.jpg',
        ]);
    }

    public function down(): void
    {
        $sdi = DB::connection('tm2sdi');
        $inquiry = $sdi->table('iqp_inquiry')->where('sales_note', 'DEMO_ARTWORK_PREVIEW')->first();

        if (! $inquiry) {
            return;
        }

        $parts = $sdi->table('iqp_part')->where('inquiry_id', $inquiry->id_inquiry)->get();

        foreach ($parts as $part) {
            if ($part->artwork_path) {
                Storage::disk('public')->deleteDirectory("inquiry/{$part->id_part}/artwork");
            }
        }

        $sdi->table('iqp_part')->where('inquiry_id', $inquiry->id_inquiry)->delete();
        $sdi->table('iqp_tier')->where('inquiry_id', $inquiry->id_inquiry)->delete();
        $sdi->table('iqp_inquiry')->where('id_inquiry', $inquiry->id_inquiry)->delete();
    }
};
