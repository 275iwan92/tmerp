<?php

namespace Database\Seeders;

use App\Models\Sdi\IqpInquiry;
use App\Models\Sys\SecUser;
use Database\Seeders\Concerns\PricedDummyChain;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

// Sampel PERMANEN produk rakitan DOUBLE JOIN (user 2026-09-29, pedoman docs/flow_product_master.md §3):
// KS Power N120 MF, PT. Selatan Jadi Jaya, DS003637 -- data dari master card + Proof Digital asli
// (docs/attachments/mastercard/, docs/attachments/Proof Digital ... .pdf). Harga & qty order = contoh.
// Alur sungguhan: Inquiry (Body rakitan + Part A + Part B) -> Quotation PUBLISHED -> CO lahir (= WIN)
// -> ProductMasterSync menjadikannya Master Product. Hanya ddt*; diulang = reset. Simpan baseline sesudahnya
// supaya kembali setelah Reset:
//   php artisan db:seed --class=UatKsPowerSeeder --env=mockup --force
//   php artisan ddt:baseline save --env=mockup --no-interaction --force
class UatKsPowerSeeder extends Seeder
{
    use PricedDummyChain;

    private const INQ_NO = 'IQ99KSP001';

    private const MARK = 'UAT-KSPOWER';

    private const ASSETS = __DIR__.'/../../docs/attachments/mastercard/';

    // Urutan pekerjaan master card: [nama, process_id prod_process, catatan].
    private const BOP_PART = [['POTONG', 5, 'Bahan 79 x 109 cm -> 47,5 x 74,5 cm'], ['CETAK', 1, 'Lithrone G40 · CMYK · ukuran cetak 75,5 x 49 cm'],
        ['QC', 13, null], ['LAMINATING GLOSSY', 7, null], ['LAMINASI', 2, 'Duplex 310 gsm dilem ke SH B/C Flute'],
        ['PLONG BELAKANG', 3, 'Pisau SJJ N120 MF (50,7 x 18,7 x 26,7 cm)_SEMARANG · 72,9 x 46,4 cm'], ['KUPAS', 10, null]];

    private const BOP_BODY = [['LEM SAMPING', 4, 'Part A + Part B direkatkan 2 sisi (Double Join)'], ['HITUNG', 15, null], ['PACK', 11, '10 pcs / pack']];

    private $sdi;

    private $mst;

    public function run(): void
    {
        $this->sdi = DB::connection('tm2sdi');
        $this->mst = DB::connection('tm2mst');
        if (! str_starts_with($this->sdi->getDatabaseName(), 'ddt') || ! str_starts_with($this->mst->getDatabaseName(), 'ddt')) {
            throw new RuntimeException('UatKsPowerSeeder hanya boleh di database dummy ddt* (--env=mockup).');
        }

        $customer = $this->mst->table('cust_customer')->where('customer_name', 'PT. SELATAN JADI JAYA')->value('id_customer')
            ?? throw new RuntimeException('Customer PT. SELATAN JADI JAYA tidak ada di master.');
        $join = $this->mst->table('mat_join_type')->where('code', 'GLUE_DOUBLE')->value('id_join_type');
        $duplex = $this->category(['Paper', 'Duplex', '310gr']);
        $flute = $this->category(['Sheet', 'BCF', 'M125/M125X3/K125']);
        $lamGlossy = $this->mst->table('ref_finishing_type')->where('finishing_name', 'Laminating Glossy')->value('id_finishing_type');
        Auth::setUser(SecUser::findOrFail(1));

        $this->reset();
        $now = now();
        $row = fn (array $x) => $x + ['created_at' => $now, 'updated_at' => $now, 'created_by' => 'uat', 'updated_by' => 'uat', 'data_origin' => 'DUMMY'];

        $inquiryId = $this->sdi->table('iqp_inquiry')->insertGetId($row([
            'inquiry_no' => self::INQ_NO, 'inquiry_date' => '2026-09-24', 'source' => 'EMAIL', 'pic_sales' => 'Firman',
            'customer_id' => $customer, 'is_prospect' => 0, 'deadline_quotation' => '2026-09-26',
            'product_draft_name' => 'KS Power N120 MF', 'design_number' => 'DS003637',
            'sales_note' => 'Kemasan aki truk KS Power N120 MF. Dibuat 2 part (A & B, masing-masing 1/2 UP) lalu direkatkan 2 sisi (Double Join). Keterangan: GERMAN FLAG-PRECAUTIONS-CCA-RC. ACC E-mail 23 Sep 2026 oleh Pak Habibi.',
            'inquiry_type' => 'NEW', 'revision_no' => 0, 'material_source' => 'OWN', 'job_nature' => 'PRODUCT', 'tax_scheme' => 'PPN',
            'pph_applicable' => 0, 'ppn_percent' => 11, 'pph_percent' => 2, 'sub_state' => 'SUBMITTED', 'stat_confirm' => 1, 'stat_cancel' => 0, 'is_approved' => 1,
        ]));
        $tierId = $this->sdi->table('iqp_tier')->insertGetId($row(['inquiry_id' => $inquiryId, 'tier_seq' => 1, 'qty' => 30000, 'is_primary' => 1]));

        // Body = rakitan (tanpa dieline sendiri); Part A & B = komponen, tiap part = setengah RSC (RSC_2PC),
        // bentangan = ukuran pisau 729 x 464 mm (35 lem + 507 + 187; 267 + 2 x (187/2 + 5)), lubang pegangan 120 mm.
        $dims = ['product_length' => 507, 'product_width' => 187, 'product_height' => 267, 'glue_flap' => 35, 'flap_extra' => 5,
            'hand_hole_width' => 120, 'overflow_margin' => 0];
        $spec = ['type_code' => 'RSC_2PC', 'dims' => $dims];
        $body = $this->sdi->table('iqp_part')->insertGetId($row([
            'inquiry_id' => $inquiryId, 'part_seq' => 1, 'part_code' => 'BODY', 'part_name' => 'Body', 'part_type' => 'DC+SHEET',
            'part_kind' => 'ASSEMBLY', 'join_type_id' => $join, 'qty_in_parent' => 1, 'qty_per_sku' => 1, 'pack_qty' => 10, 'dieline_spec' => json_encode($spec),
        ]));
        $this->bop($body, self::BOP_BODY, $row);

        foreach (['A' => 2, 'B' => 3] as $k => $seq) {
            $part = $this->sdi->table('iqp_part')->insertGetId($row([
                'inquiry_id' => $inquiryId, 'parent_part_id' => $body, 'part_seq' => $seq, 'part_code' => 'DS3637 '.$k, 'part_name' => 'Part '.$k,
                'part_type' => 'DC+SHEET', 'part_kind' => 'COMPONENT', 'qty_in_parent' => 1, 'qty_per_sku' => 1, 'cfg_color' => '4/0', 'cfg_ink' => 'CMYK',
                'dieline_spec' => json_encode($spec), 'flat_w' => 729, 'flat_h' => 464, 'flat_area' => 729 * 464,
            ]));
            foreach ([[$duplex, 'Paper › Duplex › 310gr', 310, null, 1], [$flute, 'Sheet › BCF › M125/M125X3/K125', null, 'BCF', 0]] as $m => [$cat, $name, $gsm, $fl, $primary]) {
                $this->sdi->table('iqp_part_material')->insert($row(['part_id' => $part, 'layer_seq' => $m + 1, 'category_id' => $cat, 'rawmat_name' => $name,
                    'gram_weight' => $gsm, 'flute_type' => $fl, 'size_p' => 475, 'size_l' => 745, 'is_primary' => $primary, 'material_source' => 'OWN', 'source' => 'MANUAL']));
            }
            foreach (['BLACK', 'CYAN', 'MAGENTA', 'YELLOW'] as $u => $ink) {
                $this->sdi->table('iqp_part_ink')->insert($row(['part_id' => $part, 'pass_seq' => 1, 'unit_no' => $u + 1, 'ink_name' => $ink, 'source' => 'MANUAL']));
            }
            $this->sdi->table('iqp_part_finishing')->insert(['part_id' => $part, 'seq' => 10, 'finishing_type_id' => $lamGlossy, 'finishing_name' => 'Laminating Glossy',
                'created_at' => $now, 'updated_at' => $now, 'created_by' => 'uat', 'updated_by' => 'uat', 'data_origin' => 'DUMMY']);
            $this->bop($part, self::BOP_PART, $row);

            $file = 'dieline-ks-power-n120-'.strtolower($k).'.jpg';
            $path = "inquiry/{$part}/artwork/{$file}";
            Storage::disk('public')->put($path, file_get_contents(self::ASSETS.$file));
            $this->sdi->table('iqp_part')->where('id_part', $part)->update(['artwork_path' => $path, 'artwork_original_filename' => $file]);
        }

        // Aturan A.1: ProCost (publish) -> Quotation dari harga costing -> CO lahir = WIN -> Master Product.
        $this->priceAndPublish($inquiryId);
        $order = $this->winViaQuotation($inquiryId, ['attn_contact' => 'Pak Habibi', 'sales_pic' => 'Firman', 'issued_date' => '2026-09-26',
            'valid_until' => '2026-10-26', 'payment_term' => '30 hari', 'notes' => self::MARK, 'data_origin' => 'DUMMY']);
        $this->sdi->table('co_order')->where('id_order', $order->id_order)->update(['data_origin' => 'DUMMY']);
        $this->sdi->table('co_order_item')->where('order_id', $order->id_order)->update(['data_origin' => 'DUMMY', 'due_date' => '2026-10-19']);
        $product = IqpInquiry::find($inquiryId)->product_id;
        $this->mst->table('prod_product')->where('id_product', $product)->update(['product_code' => 'SJJ-KS PWR N120 MF', 'drawing_by' => 'RINGGA', 'design_date' => '2026-09-24', 'data_origin' => 'DUMMY']);
        // Master card: "ACC E-mail: 23 September 2026 · Di ACC oleh: Pak Habibi"; ACC Digital & Box Polos masih kosong.
        $this->mst->table('prod_product_acc')->insert(['product_id' => $product, 'acc_type' => 'EMAIL', 'acc_at' => '2026-09-23 09:00:00', 'approved_by' => 'Pak Habibi',
            'channel' => 'EMAIL', 'note' => 'Dari master card DS003637', 'created_at' => $now, 'updated_at' => $now, 'created_by' => 'uat', 'updated_by' => 'uat', 'data_origin' => 'DUMMY']);
        $this->mst->table('prod_part')->where('product_id', $product)->update(['data_origin' => 'DUMMY']);

        $this->command?->info(self::INQ_NO." -> {$order->co_no} -> Master Product #{$product} (KS Power N120 MF, Body Double Join + Part A/B) di ".$this->sdi->getDatabaseName().'.');
    }

    private function bop(int $partId, array $steps, callable $row): void
    {
        foreach ($steps as $i => [$name, $process, $note]) {
            $this->sdi->table('iqp_part_process')->insert($row(['part_id' => $partId, 'branch_code' => 'MAIN', 'step_seq' => ($i + 1) * 10, 'flow_type' => 'LINEAR',
                'process_id' => $process, 'process_name' => $name, 'qty_factor' => 1, 'note' => $note, 'source' => 'MANUAL']));
        }
    }

    private function category(array $path): int
    {
        $id = null;
        foreach ($path as $level => $name) {
            $id = $this->mst->table('mat_category')->where('level', $level + 1)->where('category_name', $name)
                ->when($id, fn ($q) => $q->where('parent_id', $id), fn ($q) => $q->whereNull('parent_id'))->value('id_category');
            if (! $id) {
                throw new RuntimeException('mat_category tidak ada: '.implode(' › ', $path));
            }
        }

        return $id;
    }

    private function reset(): void
    {
        $inq = $this->sdi->table('iqp_inquiry')->where('inquiry_no', self::INQ_NO)->first();
        $quo = $this->sdi->table('quo_quotation')->where('notes', self::MARK)->pluck('id_quotation');
        $orders = $this->sdi->table('co_order')->whereIn('quotation_id', $quo)->pluck('id_order');
        $this->sdi->table('co_order_delivery')->whereIn('order_id', $orders)->delete();
        $this->sdi->table('co_order_item')->whereIn('order_id', $orders)->delete();
        $this->sdi->table('co_order')->whereIn('id_order', $orders)->delete();
        $this->sdi->table('quo_quotation_item')->whereIn('quotation_id', $quo)->delete();
        $this->sdi->table('quo_quotation')->whereIn('id_quotation', $quo)->delete();
        if (! $inq) {
            return;
        }
        $this->dropCosting($inq->id_inquiry);

        if ($inq->product_id) {
            $parts = $this->mst->table('prod_part')->where('product_id', $inq->product_id)->pluck('id_part');
            foreach ($parts as $p) {
                Storage::disk('public')->deleteDirectory("product/{$p}");
            }
            $this->mst->table('prod_product_acc')->where('product_id', $inq->product_id)->delete();
            foreach (['prod_part_material', 'prod_part_ink', 'prod_part_process', 'prod_part_finishing', 'prod_part_artwork', 'prod_part_attachment'] as $t) {
                $this->mst->table($t)->whereIn('part_id', $parts)->delete();
            }
            $this->mst->table('prod_part')->whereIn('id_part', $parts)->update(['parent_part_id' => null]);
            $this->mst->table('prod_part')->whereIn('id_part', $parts)->delete();
            $this->mst->table('prod_product')->where('id_product', $inq->product_id)->delete();
        }
        $parts = $this->sdi->table('iqp_part')->where('inquiry_id', $inq->id_inquiry)->pluck('id_part');
        foreach ($parts as $p) {
            Storage::disk('public')->deleteDirectory("inquiry/{$p}");
        }
        foreach (['iqp_part_material', 'iqp_part_finishing', 'iqp_part_process', 'iqp_part_ink'] as $t) {
            $this->sdi->table($t)->whereIn('part_id', $parts)->delete();
        }
        $this->sdi->table('iqp_part')->whereIn('id_part', $parts)->update(['parent_part_id' => null]);
        $this->sdi->table('iqp_part')->where('inquiry_id', $inq->id_inquiry)->delete();
        $this->sdi->table('iqp_tier')->where('inquiry_id', $inq->id_inquiry)->delete();
        $this->sdi->table('iqp_inquiry')->where('id_inquiry', $inq->id_inquiry)->delete();
    }
}
