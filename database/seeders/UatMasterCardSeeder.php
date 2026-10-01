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

// 5 sampel dummy dari master card Excel asli (user 2026-09-30): docs/attachments/MC-*.xlsx sheet "MC" (peta sel di
// docs/attachments_index.md). Alur sama dengan UatKsPowerSeeder: Inquiry -> ProCost (publish) -> Quotation PUBLISHED -> CO (= WIN) -> Master Product.
// Artwork = gambar proof di Excel, dipotong per part (docs/attachments/mastercard/dieline-*.jpg). Qty order = contoh; harga = hasil CostingEngine (aturan A.1, bukan harga sisipan).
// Ukuran bentangan per part = ukuran pisau / jumlah UP; flap_extra/glue_flap/lock_tab diukur dari proof. Hanya ddt*.
//   php artisan db:seed --class=UatMasterCardSeeder --env=mockup --force
//   php artisan ddt:baseline save --env=mockup --no-interaction --force
class UatMasterCardSeeder extends Seeder
{
    use PricedDummyChain;

    private const ASSETS = __DIR__.'/../../docs/attachments/mastercard/';

    private const BCF = ['Sheet', 'BCF', 'M125/M125X3/K125'];

    private const SFEF = ['Sheet', 'SFEF', 'M125/K125'];

    private $sdi;

    private $mst;

    // [nama step, process_id prod_process, catatan] — urutan pekerjaan master card.
    private function products(): array
    {
        $bodyBattery = [['LEM SAMPING', 4, 'Part A + Part B direkatkan 2 sisi (Double Join)'], ['HITUNG', 15, null], ['PACK', 11, '10 pcs / pack']];

        return [
            [
                'inq' => 'IQ99MC0001', 'mark' => 'UAT-MC-OSIMA', 'customer' => 'PT. SELATAN JADI JAYA', 'name' => 'Osima MS N100 CV', 'design' => 'DS003146',
                'code' => 'SJJ-OSM N100 CV DS3146', 'date' => '2026-03-14', 'qty' => 20000, 'price' => 4150, 'attn' => 'Pak Habibi',
                'note' => 'Kemasan aki Osima MS N100 CV. Revisi 02: penambahan "MS" pada logo Osima. Pisau SJJ N100 CV (plong dari belakang) 62,3 x 45,2 cm, 1 UP per part.',
                'type' => 'RSC_2PC', 'dims' => [413, 180, 260], 'extra' => ['glue_flap' => 30, 'flap_extra' => 6, 'hand_hole_width' => 0, 'overflow_margin' => 0],
                'flat' => [623, 452], 'part_type' => 'DC+SHEET', 'pack' => 10, 'join' => 'GLUE_DOUBLE',
                'mats' => [[['Paper', 'Duplex', '310gr'], 310, 1000, 700], [self::BCF, null, 470, 635]],
                'finishing' => [['Laminating Glossy', null]],
                'bop' => [['POTONG', 5, 'Potong bahan'], ['CETAK', 1, 'Lithrone G40 · CMYK'], ['POTONG', 5, 'Potong uk. 64,5 x 48 cm'], ['QC', 13, null],
                    ['LAMINATING GLOSSY', 7, null], ['LAMINASI', 2, 'Duplex 310 gsm dilem ke SH B/C Flute'], ['PLONG BELAKANG', 3, 'Pisau SJJ N100 CV · 62,3 x 45,2 cm'], ['KUPAS', 10, null]],
                'body_bop' => $bodyBattery,
                'parts' => ['A' => 'dieline-osima-n100-a.jpg', 'B' => 'dieline-osima-n100-b.jpg'],
                'acc' => [['EMAIL', '2026-03-07', 'EMAIL'], ['DIGITAL', '2026-03-14', 'EMAIL'], ['SAMPLE', '2021-03-18', 'LANGSUNG']], 'acc_by' => 'Pak Habibi',
            ],
            [
                'inq' => 'IQ99MC0002', 'mark' => 'UAT-MC-YAMA', 'customer' => 'PT. SELATAN JADI JAYA', 'name' => 'YAMA Din170 MF', 'design' => 'DS003026',
                'code' => 'SJJ-YMA DIN170 MF', 'date' => '2026-01-22', 'qty' => 15000, 'price' => 4480, 'attn' => 'Pak Habibi',
                'note' => 'Kemasan aki YAMA Din170 MF. Part A & B masing-masing 1/2 UP, direkatkan 2 sisi (Double Join). Master card yang ada = Part B (artwork Part A menyusul).',
                'type' => 'RSC_2PC', 'dims' => [515, 220, 257], 'extra' => ['glue_flap' => 30, 'flap_extra' => 8.5, 'hand_hole_width' => 0, 'overflow_margin' => 0],
                'flat' => [765, 494], 'part_type' => 'DC+SHEET', 'pack' => 10, 'join' => 'GLUE_DOUBLE',
                'mats' => [[['Paper', 'Duplex', '310gr'], 310, 790, 1090], [self::BCF, null, 520, 780]],
                'finishing' => [['Laminating Glossy', null]],
                'bop' => [['POTONG', 5, null], ['CETAK', 1, 'Lithrone G40 · CMYK · ukuran cetak 79 x 53 cm'], ['QC', 13, null], ['LAMINATING GLOSSY', 7, null],
                    ['LAMINASI', 2, 'Duplex 310 gsm dilem ke SH B/C Flute'], ['PLONG BELAKANG', 3, 'Pisau SJJ Din170 MF · 76,5 x 49,4 cm'], ['KUPAS', 10, null]],
                'body_bop' => $bodyBattery,
                'parts' => ['A' => null, 'B' => 'dieline-yama-din170-b.jpg'],
                'acc' => [['SAMPLE', '2021-03-18', 'LANGSUNG']], 'acc_by' => 'Pak Habibi',
            ],
            [
                'inq' => 'IQ99MC0003', 'mark' => 'UAT-MC-ALSOUTER', 'customer' => 'PAK MUKRI', 'name' => 'Outer Alsamsi Cream Lock', 'design' => null,
                'code' => 'YLAS-ALSM OB CRM CTK', 'date' => '2025-11-26', 'qty' => 5000, 'price' => 6250, 'attn' => 'Pak Mukri',
                'note' => 'Outer box sarung Al Samsi Cream, dasar kunci (lock bottom). Pisau OUTER ALSAMSI CREAM LOCK (375x197x255mm)_SEMARANG 96,9 x 60,2 cm = 1 set (Part A + B).',
                'type' => 'LOCK_BOTTOM_2PC', 'dims' => [375, 197, 255], 'extra' => ['glue_flap' => 30, 'flap_extra' => -2, 'lock_depth' => 133, 'overflow_margin' => 0],
                'flat' => [602, 484.5], 'part_type' => 'DC+SF', 'pack' => 25, 'join' => 'GLUE_DOUBLE',
                'mats' => [[['Paper', 'Duplex', '250gr'], 250, 1000, 650], [self::SFEF, null, 980, 615]],
                'finishing' => [['Varnish', 'WB']],
                'bop' => [['POTONG', 5, null], ['CETAK', 1, 'Lithrone G40 · CMYK · ukuran cetak 98,5 x 62,5 cm'], ['QC', 13, null], ['VARNISH', 20, 'WB'],
                    ['LAMINASI', 2, 'Duplex 250 gsm dilem ke Single Face E/Flute'], ['PLONG BELAKANG', 3, 'Pisau Outer Alsamsi Cream Lock · 96,9 x 60,2 cm (1 set)'], ['KUPAS', 10, null]],
                'body_bop' => [['LEM SAMPING', 4, 'Part A + Part B direkatkan (Double Join)'], ['HITUNG', 15, null], ['PACK', 11, 'Stripping 25 biji / pack']],
                'parts' => ['A' => 'dieline-alsamsi-outer-a.jpg', 'B' => 'dieline-alsamsi-outer-b.jpg'],
                'acc' => [], 'acc_by' => null,
            ],
            [
                'inq' => 'IQ99MC0004', 'mark' => 'UAT-MC-ALSTOP', 'customer' => 'PAK MUKRI', 'name' => 'Alsamsi Exclusive Top', 'design' => 'DS003047',
                'code' => 'MKR-TTP ALS ECLS', 'date' => '2026-01-30', 'qty' => 10000, 'price' => 2350, 'attn' => 'Pak Mukri',
                'note' => 'Tutup box sarung Al Samsi Exclusive (tray lid dinding ganda). Pisau ALSAMSI TOP isi 4 mata 83,4 x 70,1 cm = 4 UP.',
                'type' => 'TRAY_LID_DW', 'dims' => [248, 190, 34], 'extra' => ['lock_tab' => 16.5, 'overflow_margin' => 0],
                'flat' => [417, 350.5], 'part_type' => 'DC', 'pack' => 400, 'join' => null,
                'mats' => [[['Paper', 'Duplex', '350gr'], 350, 845, 720]],
                'finishing' => [['Laminating Doff', null], ['UV Spot', null], ['Hot Print', 'Gold Puyeh'], ['Emboss', null]],
                'bop' => [['CETAK', 1, 'Lithrone G40 · CMYK · ukuran cetak 84,5 x 72 cm'], ['QC', 13, null], ['LAMINATING DOFF', 19, 'Plastik 85 cm'], ['UV', 6, 'UV Spot'],
                    ['HOT PRINT', 21, 'Gold Puyeh'], ['PLONG', 3, 'Plong depan + Emboss · pisau 4 mata 83,4 x 70,1 cm'], ['KUPAS', 10, null], ['HITUNG', 15, null], ['WRAPPING', 17, '400 pcs / pack']],
                'body_bop' => null,
                'parts' => ['' => 'dieline-alsamsi-exclusive-top.jpg'],
                'acc' => [], 'acc_by' => null,
            ],
            [
                'inq' => 'IQ99MC0005', 'mark' => 'UAT-MC-YAHYA', 'customer' => 'PT. UPAYA GUNA SEJAHTERA', 'name' => 'Yahya 250g (24pcs) C22', 'design' => 'DS002159',
                'code' => 'UGS-IB YHY C22 250GR DS2159', 'date' => '2026-05-19', 'qty' => 48000, 'price' => 385, 'attn' => 'Pak Rahman',
                'note' => 'Inner box arang Yahya 250 g (24 pcs) C22. Tidak ada revisi desain, hanya perubahan bahan dari 65 x 100 ke 102 x 65 cm. Pisau PAK RAHMAN YAHYA 250 GR 64,5 x 50,2 cm = 8 UP.',
                'type' => 'RTE_BOX', 'dims' => [72, 46, 95], 'extra' => ['glue_flap' => 14, 'top_tuck_flap' => 33, 'dust_flap' => 25, 'bottom_tuck_flap' => 33, 'overflow_margin' => 0],
                'flat' => [250, 161], 'part_type' => 'DC', 'pack' => 600, 'join' => 'GLUE_SINGLE',
                'mats' => [[['Paper', 'Duplex', '310gr'], 310, 1020, 650]],
                'finishing' => [],
                'bop' => [['POTONG', 5, 'Potong bahan'], ['CETAK', 1, 'Lithrone G40 · CMYK · ukuran cetak 65 x 51 cm'], ['QC', 13, null], ['UV', 6, 'UV full'],
                    ['PLONG', 3, 'Plong depan · pisau 8 UP 64,5 x 50,2 cm'], ['KUPAS', 10, null], ['LEM MESIN', 4, 'Lem samping FG4 (Single Join)'], ['HITUNG', 15, null], ['PACK', 11, '600 pcs / pack']],
                'body_bop' => null,
                'parts' => ['' => 'dieline-yahya-250g-c22.jpg'],
                'acc' => [['SAMPLE', '2020-04-01', 'LANGSUNG'], ['DIGITAL', '2024-11-19', 'LANGSUNG']], 'acc_by' => 'Pak Rahman',
            ],
        ];
    }

    public function run(): void
    {
        $this->sdi = DB::connection('tm2sdi');
        $this->mst = DB::connection('tm2mst');
        if (! str_starts_with($this->sdi->getDatabaseName(), 'ddt') || ! str_starts_with($this->mst->getDatabaseName(), 'ddt')) {
            throw new RuntimeException('UatMasterCardSeeder hanya boleh di database dummy ddt* (--env=mockup).');
        }
        Auth::setUser(SecUser::findOrFail(1));
        foreach ($this->products() as $p) {
            $this->reset($p);
            $this->seedProduct($p);
        }
    }

    private function seedProduct(array $p): void
    {
        $customer = $this->mst->table('cust_customer')->whereRaw('TRIM(customer_name) = ?', [$p['customer']])->orderBy('id_customer')->value('id_customer')
            ?? throw new RuntimeException("Customer {$p['customer']} tidak ada di master.");
        $now = now();
        $row = fn (array $x) => $x + ['created_at' => $now, 'updated_at' => $now, 'created_by' => 'uat', 'updated_by' => 'uat', 'data_origin' => 'DUMMY'];
        $join = $p['join'] ? $this->mst->table('mat_join_type')->where('code', $p['join'])->value('id_join_type') : null;

        $inquiryId = $this->sdi->table('iqp_inquiry')->insertGetId($row([
            'inquiry_no' => $p['inq'], 'inquiry_date' => $p['date'], 'source' => 'EMAIL', 'pic_sales' => 'Firman', 'customer_id' => $customer, 'is_prospect' => 0,
            'deadline_quotation' => $p['date'], 'product_draft_name' => $p['name'], 'design_number' => $p['design'], 'sales_note' => $p['note'],
            'inquiry_type' => 'NEW', 'revision_no' => 0, 'material_source' => 'OWN', 'job_nature' => 'PRODUCT', 'tax_scheme' => 'PPN',
            'pph_applicable' => 0, 'ppn_percent' => 11, 'pph_percent' => 2, 'sub_state' => 'SUBMITTED', 'stat_confirm_sales' => 1, 'stat_cancel' => 0, 'is_approved' => 1,
        ]));
        $tierId = $this->sdi->table('iqp_tier')->insertGetId($row(['inquiry_id' => $inquiryId, 'tier_seq' => 1, 'qty' => $p['qty'], 'is_primary' => 1]));

        [$l, $w, $h] = $p['dims'];
        $spec = ['type_code' => $p['type'], 'dims' => ['product_length' => $l, 'product_width' => $w, 'product_height' => $h] + $p['extra']];
        $multi = count($p['parts']) > 1;
        $body = null;
        if ($multi) {
            $body = $this->sdi->table('iqp_part')->insertGetId($row([
                'inquiry_id' => $inquiryId, 'part_seq' => 1, 'part_code' => 'BODY', 'part_name' => 'Body', 'part_type' => $p['part_type'], 'part_kind' => 'ASSEMBLY',
                'join_type_id' => $join, 'qty_in_parent' => 1, 'qty_per_sku' => 1, 'pack_qty' => $p['pack'], 'dieline_spec' => json_encode($spec),
            ]));
            $this->bop($body, $p['body_bop'], $row);
        }

        $seq = $multi ? 1 : 0;
        foreach ($p['parts'] as $k => $file) {
            [$fw, $fh] = $p['flat'];
            $part = $this->sdi->table('iqp_part')->insertGetId($row([
                'inquiry_id' => $inquiryId, 'parent_part_id' => $body, 'part_seq' => ++$seq, 'part_code' => $k === '' ? 'P1' : 'PART '.$k,
                'part_name' => $k === '' ? $p['name'] : 'Part '.$k, 'part_type' => $p['part_type'], 'part_kind' => 'COMPONENT', 'join_type_id' => $multi ? null : $join,
                'qty_in_parent' => 1, 'qty_per_sku' => 1, 'pack_qty' => $multi ? null : $p['pack'], 'cfg_color' => '4/0', 'cfg_ink' => 'CMYK',
                'dieline_spec' => json_encode($spec), 'flat_w' => $fw, 'flat_h' => $fh, 'flat_area' => $fw * $fh,
            ]));
            foreach ($p['mats'] as $m => [$path, $gsm, $sp, $sl]) {
                $this->sdi->table('iqp_part_material')->insert($row(['part_id' => $part, 'layer_seq' => $m + 1, 'category_id' => $this->category($path),
                    'rawmat_name' => implode(' › ', $path), 'gram_weight' => $gsm, 'flute_type' => $path[0] === 'Sheet' ? $path[1] : null,
                    'size_p' => $sp, 'size_l' => $sl, 'is_primary' => $m === 0 ? 1 : 0, 'material_source' => 'OWN', 'source' => 'MANUAL']));
            }
            foreach (['BLACK', 'CYAN', 'MAGENTA', 'YELLOW'] as $u => $ink) {
                $this->sdi->table('iqp_part_ink')->insert($row(['part_id' => $part, 'pass_seq' => 1, 'unit_no' => $u + 1, 'ink_name' => $ink, 'source' => 'MANUAL']));
            }
            foreach ($p['finishing'] as $f => [$name, $attr]) {
                $type = $this->mst->table('ref_finishing_type')->where('finishing_name', $name)->value('id_finishing_type')
                    ?? throw new RuntimeException("ref_finishing_type tidak ada: {$name}");
                $this->sdi->table('iqp_part_finishing')->insert(['part_id' => $part, 'seq' => ($f + 1) * 10, 'finishing_type_id' => $type, 'finishing_name' => $name, 'attribute' => $attr,
                    'created_at' => $now, 'updated_at' => $now, 'created_by' => 'uat', 'updated_by' => 'uat', 'data_origin' => 'DUMMY']);
            }
            $this->bop($part, $p['bop'], $row);
            if ($file) {
                $path = "inquiry/{$part}/artwork/{$file}";
                Storage::disk('public')->put($path, file_get_contents(self::ASSETS.$file));
                $this->sdi->table('iqp_part')->where('id_part', $part)->update(['artwork_path' => $path, 'artwork_original_filename' => $file]);
            }
        }

        // Aturan A.1: quotation selalu dari costing yang di-publish (ProCost Mode #2 + CostingEngine), bukan harga sisipan.
        $this->priceAndPublish($inquiryId);
        $order = $this->winViaQuotation($inquiryId, ['attn_contact' => $p['attn'], 'sales_pic' => 'Firman', 'issued_date' => $p['date'],
            'valid_until' => date('Y-m-d', strtotime($p['date'].' +30 days')), 'payment_term' => '30 hari', 'notes' => $p['mark'], 'data_origin' => 'DUMMY']);
        $this->sdi->table('co_order')->where('id_order', $order->id_order)->update(['data_origin' => 'DUMMY']);
        $this->sdi->table('co_order_item')->where('order_id', $order->id_order)->update(['data_origin' => 'DUMMY', 'due_date' => date('Y-m-d', strtotime($p['date'].' +20 days'))]);
        $product = IqpInquiry::find($inquiryId)->product_id;
        $this->mst->table('prod_product')->where('id_product', $product)->update(['product_code' => $p['code'], 'drawing_by' => 'RINGGA', 'design_date' => $p['date'], 'data_origin' => 'DUMMY']);
        foreach ($p['acc'] as [$type, $at, $channel]) {
            $this->mst->table('prod_product_acc')->insert(['product_id' => $product, 'acc_type' => $type, 'acc_at' => $at.' 09:00:00', 'approved_by' => $p['acc_by'],
                'channel' => $channel, 'note' => 'Dari master card '.($p['design'] ?? $p['code']), 'created_at' => $now, 'updated_at' => $now, 'created_by' => 'uat', 'updated_by' => 'uat', 'data_origin' => 'DUMMY']);
        }
        $this->mst->table('prod_part')->where('product_id', $product)->update(['data_origin' => 'DUMMY']);

        $this->command?->info("{$p['inq']} -> {$order->co_no} -> Master Product #{$product} ({$p['name']}, {$p['type']}) di ".$this->sdi->getDatabaseName().'.');
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
                ->when($id, fn ($q) => $q->where('parent_id', $id), fn ($q) => $q->whereNull('parent_id'))->orderBy('id_category')->value('id_category');
            if (! $id) {
                throw new RuntimeException('mat_category tidak ada: '.implode(' › ', $path));
            }
        }

        return $id;
    }

    // Diulang = reset: hapus jejak sampel ini (CO, quotation, master product, inquiry) lalu dibuat ulang.
    private function reset(array $p): void
    {
        $inq = $this->sdi->table('iqp_inquiry')->where('inquiry_no', $p['inq'])->first();
        $quo = $this->sdi->table('quo_quotation')->where('notes', $p['mark'])->pluck('id_quotation');
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
            foreach ($parts as $id) {
                Storage::disk('public')->deleteDirectory("product/{$id}");
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
        foreach ($parts as $id) {
            Storage::disk('public')->deleteDirectory("inquiry/{$id}");
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
