<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

// Sampel Inquiry dari desain nyata customer (user 2026-09-26, diperbarui 2026-09-29 dari docs/attachments/sample-*).
// Ukuran = ukuran customer yang tercetak di desain; bila tidak tercetak = ukuran standar pasar (riset 2026-09-29):
//   Mbak Ida  PPL M tercetak 15,8 x 9 x 4,85 cm   | Mama Sakhi PPL M standar (bawah 15,8 x 9, atas 17,7 x 11, tinggi 5 cm)
//   Dowiq     R8 tercetak 18 x 18 x 6,7 cm         | Martabak   dus martabak manis standar 21 x 13,5 x 5,5 cm
//   Stoic Box mailer e-flute standar 31 x 20 x 7 cm, cetak 2 sisi (luar = artwork, dalam = artwork_inner)
// Bentangan (flat) = hasil TMDieline.flat untuk dims tsb (sama dengan layar). Dieline prod_type dibekukan + dims.
// Prospek baru (bukan customer master), status DRAFT: siap dibuka/dilanjutkan dari layar Inquiry.
// Hanya jalan di ddt*. Diulang = reset (IQ99FBX* lama dihapus).
//   php artisan db:seed --class=UatFoodBoxInquirySeeder --env=mockup
class UatFoodBoxInquirySeeder extends Seeder
{
    private const PREFIX = 'IQ99FBX';

    private const ASSETS = __DIR__.'/assets/uat-foodbox/';

    // [prospek, nama produk, tipe, catatan, parts[]]; part = [nama, part_type, dims (mm), [flat W, H], warna, [artwork luar, dalam|null], bahan[]].
    // dims PPL: alas P, L, T, atas P, L. Bahan = rantai mat_category [Kategori, Jenis, Gramatur] tanpa merek/ukuran plano
    // (Sales pilih Jenis+Gramatur, keputusan user 2026-09-29) -> iqp_part_material.category_id, rawmat_id kosong.
    private const PLAN = [
        ['Warung Mbak Ida', 'Paper Lunch Box PPL M Kraft - Warung Mbak Ida', 'PAPER_LUNCH_BOX',
            'PPL M (alas 15,8 x 9 cm, tinggi 4,85 cm - tercetak di desain). Cetak 1 warna hitam di kraft food grade. Bakso, pangsit, rujak cingur; dijual juga lewat GrabFood/ShopeeFood/GoFood.',
            [['MonoForm', 'DC', [158, 90, 48.5, 177, 110], [256.84, 337.84], '1/0', ['warung-mbak-ida-ppl-m.jpg', null], [['Paper', 'Kraft', '290gr']]]]],
        ['Mama Sakhi Kitchen', 'Paper Lunch Box PPL M Kraft - Mama Sakhi Kitchen', 'PAPER_LUNCH_BOX',
            'Die sama dengan PPL M; ukuran tidak tercetak -> ukuran standar PPL M (bawah 15,8 x 9, atas 17,7 x 11, tinggi 5 cm). Full color + logo Halal.',
            [['MonoForm', 'DC', [158, 90, 50, 177, 110], [259.79, 341.98], '4/0', ['mama-sakhi-kitchen-ppl-m.jpg', null], [['Paper', 'Kraft', '290gr']]]]],
        ['Warung Lancar Bu Dewi', 'Dus Nasi Kotak R8 Kraft - Warung Lancar Bu Dewi', 'RICE_BOX',
            'Dus nasi R8 (18 x 18 x 6,7 cm). Full color + logo Halal. Untuk nasi & lauk, perlu kraft food grade.',
            [['MonoForm', 'DC', [180, 180, 67], [314, 607.9], '4/0', ['warung-lancar-bu-dewi-r8.png', null], [['Paper', 'Kraft', '290gr']]]]],
        ['Dowiq Pispa Bakery', 'Box Kue R8 Kraft - Dowiq Pispa Bakery', 'RICE_BOX',
            'Box kue/roti R8 (18 x 18 x 6,7 cm - tercetak di desain, ukuran customer). Full color. Roti, kue kering, kue basah, pizza.',
            [['MonoForm', 'DC', [180, 180, 67], [314, 607.9], '4/0', ['dowiq-pispa-bakery-r8.jpg', null], [['Paper', 'Kraft', '300gr']]]]],
        ['Martabak & Terang Bulan A-ye Bandung', 'Dus Martabak Kraft - Martabak A-ye Bandung', 'RICE_BOX',
            'Dus martabak; ukuran tidak tercetak -> standar dus martabak manis 21 x 13,5 x 5,5 cm. Full color + logo Halal, daftar varian terang bulan/martabak telor.',
            [['MonoForm', 'DC', [210, 135, 55], [320, 473.5], '4/0', ['martabak-a-ye-bandung.jpg', null], [['Paper', 'Kraft', '290gr']]]]],
        ['Stoic Box', 'Mailer Box Stoic Box (cetak 2 sisi)', 'MAILER',
            'Mailer box e-flute; ukuran tidak tercetak -> standar 31 x 20 x 7 cm. Cetak 2 sisi: luar kuning (logo Stoic Box), dalam biru (sambutan + QR).',
            [['MonoForm', 'DC', [310, 200, 70], [456, 346], '4/4', ['stoic-box-mailer-luar.jpg', 'stoic-box-mailer-dalam.jpg'], [['Paper', 'Kraft', '300gr']]]]],
        // Gambar referensi user 2026-09-29 (dieline tray + tutup): alas L 350 x W 280 x H 115 mm (tercetak). Tutup "L+, W+, H-" tanpa angka:
        // tinggi tutup diukur dari proporsi gambar dieline tutup (sirip ~0,155 x W) = 45 mm; L/W tutup = alas + 5 mm
        // (2 x tebal kraft+E-flute ~1,8 mm + kelonggaran 1 mm) supaya tutup masuk. Bentangan = A + 2C + 2 bleed 3 mm.
        ['Contoh Box Tutup-Alas (gambar referensi)', 'Box Tutup-Alas Kraft 35 x 28 x 11,5 cm', 'BOX_WITH_LID',
            'Box tutup-alas (tray + tutup lepas), kraft polos coklat sesuai gambar referensi. Alas 350 x 280 x 115 mm (tercetak di gambar); tutup tidak bernomor ("L+, W+, H-") -> 355 x 285 x 45 mm (alas + 5 mm kelonggaran, tinggi dari proporsi gambar). Ukuran besar -> kraft dilaminasi single face E-flute.',
            [['Body', 'DC+SF', [350, 280, 115], [586, 516], '1/0', null, [['Paper', 'Kraft', '300gr'], ['Sheet', 'SFEF', 'M125/K125']]],
             ['Lid', 'DC+SF', [355, 285, 45], [451, 381], '1/0', null, [['Paper', 'Kraft', '300gr'], ['Sheet', 'SFEF', 'M125/K125']]]]],
    ];

    private const TIERS = [1000, 3000, 5000];

    private $db;

    public function run(): void
    {
        $this->db = DB::connection('tm2sdi');

        if (! str_starts_with($this->db->getDatabaseName(), 'ddt') || ! str_starts_with(DB::connection('tm2mst')->getDatabaseName(), 'ddt')) {
            throw new RuntimeException('UatFoodBoxInquirySeeder hanya boleh di database dummy ddt* (--env=mockup).');
        }

        $types = DB::connection('tm2mst')->table('prod_type')->whereIn('type_code', array_column(self::PLAN, 2))->pluck('dieline_spec', 'type_code');
        $sales = DB::connection('tm2mst')->table('sales_person')->where('activate', 1)->orderBy('id_sales_person')->pluck('sales_name')->all() ?: [null];

        $this->db->transaction(function () use ($types, $sales) {
            $this->reset();

            foreach (self::PLAN as $i => [$prospect, $product, $typeCode, $note, $parts]) {
                $now = now();
                $inquiryId = $this->db->table('iqp_inquiry')->insertGetId([
                    'inquiry_no' => self::PREFIX.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                    'inquiry_date' => $now->copy()->subDays(count(self::PLAN) - 1 - $i)->toDateString(),
                    'source' => 'Referral',
                    'pic_sales' => $sales[$i % count($sales)],
                    'customer_id' => null,
                    'is_prospect' => 1,
                    'prospect_name' => $prospect,
                    'deadline_quotation' => $now->copy()->addDays(7)->toDateString(),
                    'product_draft_name' => $product,
                    'sales_note' => $note,
                    'inquiry_type' => 'NEW',
                    'revision_no' => 0,
                    'material_source' => 'OWN',
                    'job_nature' => 'PRODUCT',
                    'tax_scheme' => 'PPN',
                    'pph_applicable' => 0,
                    'ppn_percent' => 11,
                    'pph_percent' => 2,
                    'sub_state' => 'DRAFT',
                    'stat_confirm' => 0,
                    'stat_cancel' => 0,
                    'is_approved' => 0,
                    'created_at' => $now, 'updated_at' => $now, 'created_by' => 'uat', 'updated_by' => 'uat',
                    'data_origin' => 'DUMMY',
                ]);

                foreach (self::TIERS as $t => $qty) {
                    $this->db->table('iqp_tier')->insert(['inquiry_id' => $inquiryId, 'tier_seq' => $t + 1, 'qty' => $qty, 'is_primary' => $t === 0 ? 1 : 0,
                        'created_at' => $now, 'updated_at' => $now, 'created_by' => 'uat', 'updated_by' => 'uat', 'data_origin' => 'DUMMY']);
                }

                foreach ($parts as $n => [$partName, $partType, $d, [$flatW, $flatH], $color, $art, $mats]) {
                    $spec = json_decode($types[$typeCode], true);
                    $spec['dims'] = ['product_length' => $d[0], 'product_width' => $d[1], 'product_height' => $d[2]]
                        + (isset($d[3]) ? ['top_length' => $d[3], 'top_width' => $d[4]] : [])
                        + ($typeCode === 'BOX_WITH_LID' ? ['lid_height' => $parts[1][2][2], 'overflow_margin' => 3] : []);
                    $spec['type_code'] = $typeCode;

                    $partId = $this->db->table('iqp_part')->insertGetId([
                        'inquiry_id' => $inquiryId, 'part_seq' => $n + 1, 'part_code' => 'P'.($n + 1), 'part_name' => $partName, 'part_type' => $partType,
                        'qty_per_sku' => 1, 'cfg_color' => $color, 'dieline_spec' => json_encode($spec),
                        'flat_w' => $flatW, 'flat_h' => $flatH, 'flat_area' => round($flatW * $flatH, 2),
                        'created_at' => $now, 'updated_at' => $now, 'created_by' => 'uat', 'updated_by' => 'uat', 'data_origin' => 'DUMMY',
                    ]);

                    foreach ($mats as $m => $path) {
                        $cat = $this->category($path);
                        $gsm = preg_match('/^(\d+)\s*gr/i', $path[2], $g) ? $g[1] : null;
                        $this->db->table('iqp_part_material')->insert([
                            'part_id' => $partId, 'layer_seq' => $m + 1, 'rawmat_id' => null, 'category_id' => $cat, 'rawmat_name' => implode(' › ', $path),
                            'gram_weight' => $gsm, 'flute_type' => $path[0] === 'Sheet' ? $path[1] : null, 'is_primary' => $m === 0 ? 1 : 0,
                            'material_source' => 'OWN', 'source' => 'MANUAL',
                            'created_at' => $now, 'updated_at' => $now, 'created_by' => 'uat', 'data_origin' => 'DUMMY',
                        ]);
                    }

                    if ($art) {
                        [$file, $inner] = $art;
                        $path = "inquiry/{$partId}/artwork/{$file}";
                        Storage::disk('public')->put($path, file_get_contents(self::ASSETS.$file));
                        $upd = ['artwork_path' => $path, 'artwork_original_filename' => $file];
                        if ($inner) {
                            $ipath = "inquiry/{$partId}/artwork-inner/{$inner}";
                            Storage::disk('public')->put($ipath, file_get_contents(self::ASSETS.$inner));
                            $upd += ['artwork_inner_path' => $ipath, 'artwork_inner_original_filename' => $inner];
                        }
                        $this->db->table('iqp_part')->where('id_part', $partId)->update($upd);
                    }
                }
            }
        });

        $this->command?->info(count(self::PLAN).' inquiry '.self::PREFIX.'* (sampel desain customer, DRAFT) siap di '.$this->db->getDatabaseName().'.');
    }

    // [Kategori, Jenis, Gramatur] -> id_category node terdalam; gagal keras bila master mat_category tidak punya rantai itu.
    private function category(array $path): int
    {
        $id = null;
        foreach ($path as $level => $name) {
            $id = DB::connection('tm2mst')->table('mat_category')->where('level', $level + 1)->where('category_name', $name)
                ->when($id, fn ($q) => $q->where('parent_id', $id), fn ($q) => $q->whereNull('parent_id'))->value('id_category');
            if (! $id) {
                throw new RuntimeException('mat_category tidak ada: '.implode(' › ', $path));
            }
        }

        return $id;
    }

    private function reset(): void
    {
        $inq = $this->db->table('iqp_inquiry')->where('inquiry_no', 'like', self::PREFIX.'%')->pluck('id_inquiry');

        if ($inq->isEmpty()) {
            return;
        }

        $parts = $this->db->table('iqp_part')->whereIn('inquiry_id', $inq)->pluck('id_part');
        foreach ($parts as $partId) {
            Storage::disk('public')->deleteDirectory("inquiry/{$partId}");
        }
        foreach (['iqp_part_material', 'iqp_part_finishing', 'iqp_part_process'] as $t) {
            $this->db->table($t)->whereIn('part_id', $parts)->delete();
        }
        $this->db->table('iqp_part')->whereIn('inquiry_id', $inq)->delete();
        $this->db->table('iqp_tier')->whereIn('inquiry_id', $inq)->delete();
        $this->db->table('iqp_inquiry')->whereIn('id_inquiry', $inq)->delete();
    }
}
