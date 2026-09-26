<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

// Sampel Inquiry dus makanan (user 2026-09-26): 3 desain nyata dari user jadi artwork,
// ukuran = standar pasar yang juga tercetak di desainnya:
//   PPL M (paper lunch box medium) alas 15,8 x 9 cm, tinggi 4,85 cm
//   R8 (dus nasi kotak kraft)      18 x 18 cm, tinggi 6,7 cm
// Tipe Produk MAILER (dieline prod_type dibekukan + dims, sama seperti layar Inquiry menyimpan).
// Prospek baru (bukan customer master), status DRAFT: siap dibuka/dilanjutkan dari layar Inquiry.
// Hanya jalan di ddt*. Diulang = reset (IQ99FBX* lama dihapus).
//   php artisan db:seed --class=UatFoodBoxInquirySeeder --env=mockup
class UatFoodBoxInquirySeeder extends Seeder
{
    private const PREFIX = 'IQ99FBX';

    private const ASSETS = __DIR__.'/assets/uat-foodbox/';

    // [prospek, nama produk, file artwork, P x L x T (mm), warna, bahan (material_code), catatan]
    private const PLAN = [
        ['Warung Mbak Ida', 'Paper Lunch Box PPL M Kraft - Warung Mbak Ida', 'warung-mbak-ida-ppl-m.webp', [158, 90, 48.5], '1/0', 'PKM23023',
            'PPL M (alas 15,8 x 9 cm, tinggi 4,85 cm). Cetak 1 warna hitam di kraft food grade. Bakso, pangsit, rujak cingur; dijual juga lewat GrabFood/ShopeeFood/GoFood.'],
        ['Warung Lancar Bu Dewi', 'Dus Nasi Kotak R8 Kraft - Warung Lancar Bu Dewi', 'warung-lancar-bu-dewi-r8.png', [180, 180, 67], '4/0', 'PKM23023',
            'Dus nasi R8 (18 x 18 x 6,7 cm). Full color + logo Halal. Untuk nasi & lauk, perlu kraft food grade.'],
        ['Dowiq Pispa Bakery', 'Box Kue R8 Kraft - Dowiq Pispa Bakery', 'dowiq-pispa-bakery-r8.webp', [180, 180, 67], '4/0', 'PKD24024',
            'Box kue/roti R8 (18 x 18 x 6,7 cm). Full color. Roti, kue kering, kue basah, pizza.'],
    ];

    private const TIERS = [1000, 3000, 5000];

    private const OVERFLOW = 3;

    private $db;

    public function run(): void
    {
        $this->db = DB::connection('tm2sdi');

        if (! str_starts_with($this->db->getDatabaseName(), 'ddt') || ! str_starts_with(DB::connection('tm2mst')->getDatabaseName(), 'ddt')) {
            throw new RuntimeException('UatFoodBoxInquirySeeder hanya boleh di database dummy ddt* (--env=mockup).');
        }

        $type = DB::connection('tm2mst')->table('prod_type')->where('type_code', 'MAILER')->value('dieline_spec');
        $sales = DB::connection('tm2mst')->table('sales_person')->where('activate', 1)->orderBy('id_sales_person')->pluck('sales_name')->all() ?: [null];
        $materials = DB::connection('tm2mst')->table('mat_raw')->whereIn('material_code', array_column(self::PLAN, 5))->get()->keyBy('material_code');

        $this->db->transaction(function () use ($type, $sales, $materials) {
            $this->reset();

            foreach (self::PLAN as $i => [$prospect, $product, $file, [$a, $b, $c], $color, $matCode, $note]) {
                $now = now();
                $inquiryId = $this->db->table('iqp_inquiry')->insertGetId([
                    'inquiry_no' => self::PREFIX.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                    'inquiry_date' => $now->copy()->subDays(2 - $i)->toDateString(),
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

                $spec = json_decode($type, true);
                $spec['dims'] = ['product_length' => $a, 'product_width' => $b, 'product_height' => $c, 'overflow_margin' => self::OVERFLOW];
                $spec['type_code'] = 'MAILER';
                $flatW = $a + 2 * $c + 2 * self::OVERFLOW;
                $flatH = $b + 2 * $c + 2 * self::OVERFLOW;

                $partId = $this->db->table('iqp_part')->insertGetId([
                    'inquiry_id' => $inquiryId, 'part_seq' => 1, 'part_code' => 'P1', 'part_name' => 'MonoForm', 'part_type' => 'DC',
                    'qty_per_sku' => 1, 'cfg_color' => $color, 'dieline_spec' => json_encode($spec),
                    'flat_w' => $flatW, 'flat_h' => $flatH, 'flat_area' => $flatW * $flatH,
                    'created_at' => $now, 'updated_at' => $now, 'created_by' => 'uat', 'updated_by' => 'uat', 'data_origin' => 'DUMMY',
                ]);

                $mat = $materials[$matCode] ?? null;
                preg_match('/(\d+)\s*GR/i', (string) $mat?->material_name, $gsm);
                $this->db->table('iqp_part_material')->insert([
                    'part_id' => $partId, 'layer_seq' => 1, 'rawmat_id' => $mat?->material_id, 'rawmat_name' => $mat?->material_name ?? 'Brown Kraft',
                    'gram_weight' => $gsm[1] ?? null, 'is_primary' => 1, 'material_source' => 'OWN', 'source' => 'MANUAL',
                    'created_at' => $now, 'updated_at' => $now, 'created_by' => 'uat', 'data_origin' => 'DUMMY',
                ]);

                $path = "inquiry/{$partId}/artwork/{$file}";
                Storage::disk('public')->put($path, file_get_contents(self::ASSETS.$file));
                $this->db->table('iqp_part')->where('id_part', $partId)->update(['artwork_path' => $path, 'artwork_original_filename' => $file]);
            }
        });

        $this->command?->info(count(self::PLAN).' inquiry '.self::PREFIX.'* (dus makanan, DRAFT) siap di '.$this->db->getDatabaseName().'.');
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
