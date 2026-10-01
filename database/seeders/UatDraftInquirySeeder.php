<?php

namespace Database\Seeders;

use App\Models\Master\ProdProduct;
use App\Services\Sales\InquiryDerivedWriter;
use App\Support\Dieline\TmDieline;
use App\Support\V2\Data\Sales;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Dummy Inquiry berstatus DRAFT (perintah user 2026-10-01: "rebuild data dummy for all inquiry data but status draft,
// so I could see how dieline mockup works"). Draft = form masih bisa diedit, jadi preview dieline & 3D bisa dicoba langsung.
//   - NEW: satu inquiry per tipe dieline aktif (prod_type yang punya dieline_spec.elements) -> semua bentuk dieline terwakili.
//     Ukuran = nilai bawaan parameter tipe, ditimpa Ukuran Pasaran pertama tipe itu (bukan angka karangan).
//   - RECALC / REVISE: lewat InquiryDerivedWriter dari Master Product dummy yang punya inquiry asal, supaya membuka form
//     (dummy lama RECALC/REVISE tidak punya product -> jatuh ke daftar, tidak bisa dibuka). Tiap product: satu DRAFT +
//     satu Confirmed (SUBMITTED) dari jenis sebaliknya. RECALC/REVISE lama yang tidak terhubung dibereskan di legacy().
// Draft dummy lama yang digantikan DIHAPUS (aturan B.12); yang tidak disentuh: sampel desain customer IQ99FBX*
// (UatFoodBoxInquirySeeder, punya artwork asli), draft LIVE buatan user, dan inquiry NEW non-draft (rantai Quotation/CO).
// Hanya jalan di ddt*. Diulang = reset (IQ99DRF* lama dihapus).
//   php artisan db:seed --class=UatDraftInquirySeeder --env=mockup
class UatDraftInquirySeeder extends Seeder
{
    private const PREFIX = 'IQ99DRF';

    private const KEEP = 'IQ99FBX';

    private const BY = 'uat';

    private const TIERS = [1000, 3000, 5000];

    private const SOURCES = ['EMAIL', 'WA', 'PHONE', 'VISIT', 'MARKETPLACE'];

    // Tipe yang biasa 2 part Body + Lid (InquiryForm::TEMPLATES); tutup = alas + 5 mm kelonggaran, tinggi = tinggi tutup.
    private const LIDDED = ['BOX_WITH_LID', 'ROUND_BOX', 'SHALLOW_BOX'];

    // Karton 2-piece: dua lembar sama besar (Part A + Part B) yang disambung -> bisa dicoba dijadikan Rakitan di form.
    private const TWO_PIECE = ['RSC_2PC', 'LOCK_BOTTOM_2PC'];

    // tipe => [notasi warna, bahan [Kategori, Jenis, Gramatur/Spec] dari master mat_category]. Tipe lain = DEFAULT.
    private const DEFAULT = ['4/0', [['Paper', 'Ivory', '300gr']]];

    private const SPEC = [
        'CRASH_LOCK' => ['4/0', [['Paper', 'Duplex', '350gr']]],
        'SLEEVE' => ['4/0', [['Paper', 'Art carton', '260gr']]],
        'GIFT_BOX' => ['4/0', [['Paper', 'Art carton', '310gr']]],
        'MAILER' => ['1/0', [['Paper', 'Kraft', '300gr'], ['Sheet', 'SFEF', 'M125/K125']]],
        'SHALLOW_BOX' => ['4/0', [['Paper', 'Duplex', '350gr']]],
        'BOX_WITH_LID' => ['4/0', [['Paper', 'Duplex', '400gr']]],
        'ROUND_BOX' => ['4/0', [['Paper', 'Art carton', '260gr']]],
        'CYLINDER' => ['4/0', [['Paper', 'Art carton', '260gr']]],
        'COUNTER_DISPLAY' => ['4/0', [['Paper', 'Duplex', '350gr'], ['Sheet', 'SFEF', 'M125/K125']]],
        'HANG_TAG' => ['4/4', [['Paper', 'Art carton', '260gr']]],
        'KALENDER_DINDING' => ['4/0', [['Paper', 'Art Papper', '150gr']]],
        'KALENDER_DUDUK' => ['4/4', [['Paper', 'Art carton', '260gr']]],
        'BROSUR' => ['4/4', [['Paper', 'Art Papper', '150gr']]],
        'FLYER' => ['4/4', [['Paper', 'Art Papper', '120gr']]],
        'POSTER' => ['4/0', [['Paper', 'Art Papper', '150gr']]],
        'BUKU' => ['4/4', [['Paper', 'Art Papper', '120gr']]],
        'LABEL_STIKER' => ['4/0', [['Paper', 'Stiker vinyl', '0gr']]],
        'KARTU_NAMA' => ['4/4', [['Paper', 'Art carton', '260gr']]],
        'AMPLOP_KOP' => ['1/0', [['Paper', 'HVS', '90 GR']]],
        'RICE_BOX' => ['4/0', [['Paper', 'Kraft', '290gr']]],
        'PAPER_LUNCH_BOX' => ['4/0', [['Paper', 'Kraft', '290gr']]],
        'RSC_2PC' => ['4/0', [['Paper', 'Duplex', '250gr'], ['Sheet', 'BCF', 'K125/M125/K125']]],
        'LOCK_BOTTOM_2PC' => ['4/0', [['Paper', 'Duplex', '250gr'], ['Sheet', 'SFEF', 'M125/K125']]],
        'TRAY_LID_DW' => ['4/0', [['Paper', 'Duplex', '350gr']]],
    ];

    // Draft REVISE: scope + alasan + perubahan (supaya tabel sebelum -> sesudah terisi).
    private const REVISES = [
        [['DIMENSION'], 'Customer asks for a taller box (+10 mm)', 10],
        [['COLOR'], 'Customer adds one spot colour', 0],
        [['ARTWORK'], 'New artwork for the 2027 design', 0],
    ];

    private $db;

    private $mst;

    public function run(): void
    {
        $this->db = DB::connection('tm2sdi');
        $this->mst = DB::connection('tm2mst');

        if (! str_starts_with($this->db->getDatabaseName(), 'ddt') || ! str_starts_with($this->mst->getDatabaseName(), 'ddt')) {
            throw new RuntimeException('UatDraftInquirySeeder hanya boleh di database dummy ddt* (--env=mockup).');
        }

        $types = $this->types();
        $sales = $this->mst->table('sales_person')->where('activate', 1)->orderBy('id_sales_person')->pluck('sales_name')->all() ?: [null];
        // Customer yang memang sudah dipakai inquiry dummy lain (bukan customer acak dari 881 master).
        $customers = $this->db->table('iqp_inquiry')->where('data_origin', 'DUMMY')->whereNotNull('customer_id')->distinct()->orderBy('customer_id')->pluck('customer_id')->all();
        if (! $customers) {
            throw new RuntimeException('Tidak ada customer dummy untuk dipakai.');
        }

        $n = 0;
        $this->db->transaction(function () use ($types, $sales, $customers, &$n) {
            $removed = $this->reset();
            [$deleted, $converted] = $this->legacy();
            $this->command?->info("Draft dummy lama dihapus: $removed. RECALC/REVISE lama tanpa Master Product: $deleted dihapus, $converted dijadikan NEW.");

            foreach ($types as $code => $t) {
                $n++;
                [$color, $mats] = self::SPEC[$code] ?? self::DEFAULT;
                $now = now();
                $inquiryId = $this->db->table('iqp_inquiry')->insertGetId([
                    'inquiry_no' => $this->no($n),
                    'inquiry_date' => $now->copy()->subDays(count($types) - $n)->toDateString(),
                    'source' => self::SOURCES[$n % count(self::SOURCES)],
                    'pic_sales' => $sales[$n % count($sales)],
                    'customer_id' => $customers[$n % count($customers)],
                    'is_prospect' => 0,
                    'deadline_quotation' => $now->copy()->addDays(14)->toDateString(),
                    'product_draft_name' => mb_substr($t['name'].($t['size'] ? ' '.$t['size'] : ''), 0, 150),
                    'sales_note' => 'Dummy draft for dieline preview. Type '.$code.'; dimensions = '.($t['size'] ? 'standard size "'.$t['size'].'"' : 'type defaults').'.',
                    'inquiry_type' => 'NEW', 'revision_no' => 0, 'material_source' => 'OWN', 'job_nature' => 'PRODUCT',
                    'tax_scheme' => 'PPN', 'pph_applicable' => 0, 'ppn_percent' => 11, 'pph_percent' => 2,
                    'sub_state' => 'DRAFT', 'stat_confirm' => 0, 'stat_cancel' => 0, 'is_approved' => 0,
                    'created_at' => $now, 'updated_at' => $now, 'created_by' => self::BY, 'updated_by' => self::BY, 'data_origin' => 'DUMMY',
                ]);

                foreach (self::TIERS as $i => $qty) {
                    $this->db->table('iqp_tier')->insert(['inquiry_id' => $inquiryId, 'tier_seq' => $i + 1, 'qty' => $qty, 'is_primary' => $i === 0 ? 1 : 0,
                        'created_at' => $now, 'updated_at' => $now, 'created_by' => self::BY, 'updated_by' => self::BY, 'data_origin' => 'DUMMY']);
                }

                foreach ($this->parts($code, $t['dims']) as $seq => [$partName, $dims]) {
                    $spec = $t['spec'];
                    $spec['dims'] = $dims;
                    $spec['type_code'] = $code;
                    $flat = TmDieline::flat($spec, $dims);
                    $partId = $this->db->table('iqp_part')->insertGetId([
                        'inquiry_id' => $inquiryId, 'part_seq' => $seq + 1, 'part_code' => 'P'.($seq + 1), 'part_name' => $partName, 'part_type' => $t['part_type'],
                        'qty_per_sku' => 1, 'cfg_color' => $color, 'dieline_spec' => json_encode($spec),
                        'flat_w' => $flat['w'], 'flat_h' => $flat['h'], 'flat_area' => $flat['area'],
                        'created_at' => $now, 'updated_at' => $now, 'created_by' => self::BY, 'updated_by' => self::BY, 'data_origin' => 'DUMMY',
                    ]);
                    foreach ($mats as $m => $path) {
                        $this->db->table('iqp_part_material')->insert([
                            'part_id' => $partId, 'layer_seq' => $m + 1, 'rawmat_id' => null, 'category_id' => $this->category($path), 'rawmat_name' => implode(' › ', $path),
                            'gram_weight' => preg_match('/^(\d+)\s*gr/i', $path[2], $g) ? $g[1] : null, 'flute_type' => $path[0] === 'Sheet' ? $path[1] : null,
                            'is_primary' => $m === 0 ? 1 : 0, 'material_source' => 'OWN', 'source' => 'MANUAL',
                            'created_at' => $now, 'updated_at' => $now, 'created_by' => self::BY, 'data_origin' => 'DUMMY',
                        ]);
                    }
                }
            }

            $n = $this->derived($n);
        });

        $this->command?->info($n.' inquiry '.self::PREFIX.'* siap di '.$this->db->getDatabaseName().': '.count($types).' NEW DRAFT (satu per tipe dieline) + '.($n - count($types)).' RECALC/REVISE (separuh DRAFT, separuh Confirmed).');
    }

    // Tipe aktif yang punya dieline (sama dengan Sales::dielineTypes()): spec, ukuran = bawaan parameter + Ukuran Pasaran pertama.
    private function types(): array
    {
        $partTypes = $this->mst->table('prod_type')->pluck('part_type_default', 'type_code');

        $out = [];
        foreach (Sales::dielineTypes() as $code => $t) {
            $out[$code] = ['name' => $t['label'], 'spec' => $t['spec'], 'size' => $t['sizes'][0]['label'] ?? null, 'part_type' => $partTypes[$code] ?: 'DC',
                'dims' => array_merge(array_map(fn ($p) => $p['default'], $t['params']), $t['sizes'][0]['dims'] ?? [])];
        }

        return $out;
    }

    private function parts(string $code, array $dims): array
    {
        if (in_array($code, self::TWO_PIECE, true)) {
            return [['Part A', $dims], ['Part B', $dims]];
        }
        if (! in_array($code, self::LIDDED, true)) {
            return [['MonoForm', $dims]];
        }
        $lid = $dims;
        foreach (['product_length', 'product_width', 'diameter'] as $k) {
            if (isset($lid[$k])) {
                $lid[$k] += 5;
            }
        }
        $lid['product_height'] = $dims['lid_height'] ?? $dims['product_height'];

        return [['Body', $dims], ['Lid', $lid]];
    }

    // Draft RECALC + REVISE dari Master Product dummy yang lahir dari inquiry (logika = layar: InquiryDerivedWriter).
    private function derived(int $n): int
    {
        $writer = new InquiryDerivedWriter(self::BY);
        $products = ProdProduct::where('data_origin', 'DUMMY')->whereNotNull('source_inquiry_id')
            ->whereIn('source_inquiry_id', $this->db->table('iqp_inquiry')->pluck('id_inquiry'))->orderBy('id_product')->get();

        // Tiap product: satu dokumen DRAFT + satu dokumen Confirmed (SUBMITTED) dari jenis sebaliknya, supaya kedua tab
        // (Recalculate / Change Specification) punya contoh yang masih bisa diedit dan yang sudah terkunci.
        foreach ([false, true] as $confirmed) {
            foreach ($products as $i => $product) {
                $source = InquiryDerivedWriter::sourceOf($product);
                if (($i % 2 === 0) !== $confirmed) {
                    $qty = (float) ($this->db->table('iqp_tier')->where('inquiry_id', $source->id_inquiry)->where('is_primary', 1)->value('qty') ?: 10000);
                    $doc = $writer->recalc($product, null, ['qty' => $qty * ($confirmed ? 3 : 2), 'deadline_quotation' => now()->addDays(10)->toDateString()]);
                } else {
                    [$scope, $reason, $taller] = self::REVISES[(intdiv($i, 2) + (int) $confirmed) % count(self::REVISES)];
                    $parts = [];
                    foreach ($source->parts as $p) {
                        $h = (float) ($p->dieline_spec['dims']['product_height'] ?? 0);
                        $parts[$p->part_seq] = ['dims' => ['product_height' => $h + $taller], 'cfg_color' => '5/0', 'cfg_ink' => $p->cfg_ink];
                    }
                    $doc = $writer->revise($product, null, ['scope' => $scope, 'reason' => $reason, 'design_number' => in_array('ARTWORK', $scope, true) ? 'DS-2027-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT) : '', 'parts' => $parts]);
                }
                if ($confirmed) {
                    $writer->confirm($doc);
                }
                $n++;
                $this->db->table('iqp_inquiry')->where('id_inquiry', $doc->id_inquiry)->update(['inquiry_no' => $this->no($n), 'data_origin' => 'DUMMY', 'created_by' => self::BY, 'updated_by' => self::BY]);
                foreach (['iqp_part', 'iqp_tier'] as $t) {
                    $this->db->table($t)->where('inquiry_id', $doc->id_inquiry)->update(['data_origin' => 'DUMMY']);
                }
            }
        }

        return $n;
    }

    // RECALC / REVISE dummy lama yang tidak lahir dari Master Product (product_id & parent_inquiry_id kosong): layar v2 tidak
    // bisa membukanya (jatuh ke daftar). User 2026-10-01 mengizinkan perbaikan:
    //   - tanpa tier qty dan tidak dipakai dokumen lain = dummy generik rusak -> DIHAPUS (costing-nya ikut lewat FK cascade;
    //     prod_part/product ProDev yang pernah dibuat darinya tidak disentuh);
    //   - bertier / dipakai Quotation (salinan berharga UatQuotationInquirySeeder) -> dijadikan NEW: datanya utuh & konsisten,
    //     hanya jenisnya yang salah (Recalc/Revise sungguhan harus dari Master Product, docs/flow_product_master.md §4).
    private function legacy(): array
    {
        $ids = $this->db->table('iqp_inquiry')->where('data_origin', 'DUMMY')->whereIn('inquiry_type', ['RECALC', 'REVISE'])
            ->whereNull('product_id')->whereNull('parent_inquiry_id')->where('inquiry_no', 'not like', self::PREFIX.'%')->pluck('id_inquiry');
        $partIds = $this->db->table('iqp_part')->whereIn('inquiry_id', $ids)->pluck('inquiry_id', 'id_part');
        $keep = collect()
            ->merge($this->db->table('iqp_tier')->whereIn('inquiry_id', $ids)->pluck('inquiry_id'))
            ->merge($this->db->table('quo_quotation_item')->whereIn('inquiry_id', $ids)->pluck('inquiry_id'))
            ->merge($this->db->table('co_order_item')->whereIn('inquiry_id', $ids)->pluck('inquiry_id'))
            ->merge($this->db->table('iqp_inquiry')->whereIn('parent_inquiry_id', $ids)->pluck('parent_inquiry_id'))
            ->merge($this->mst->table('prod_product')->whereIn('source_inquiry_id', $ids)->pluck('source_inquiry_id'))
            ->merge($this->mst->table('prod_master_card')->whereIn('inquiry_id', $ids)->pluck('inquiry_id'))
            ->merge($this->mst->table('prod_part')->whereIn('source_iqp_part_id', $partIds->keys())->pluck('source_iqp_part_id')->map(fn ($p) => $partIds[$p]))
            ->merge(DB::connection('tm2prd')->table('prd_wo')->whereIn('source_iqp_part_id', $partIds->keys())->pluck('source_iqp_part_id')->map(fn ($p) => $partIds[$p]))
            ->unique();
        $convert = $ids->intersect($keep)->values();
        $delete = $ids->diff($keep)->values();

        $this->db->table('iqp_inquiry')->whereIn('id_inquiry', $convert)->update(['inquiry_type' => 'NEW', 'revise_scope' => null, 'revise_reason' => null, 'updated_by' => self::BY, 'updated_at' => now()]);
        $this->db->table('iqp_part')->whereIn('inquiry_id', $delete)->update(['parent_part_id' => null]);
        $this->db->table('iqp_inquiry')->whereIn('id_inquiry', $delete)->delete();

        return [$delete->count(), $convert->count()];
    }

    private function no(int $n): string
    {
        return self::PREFIX.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }

    // [Kategori, Jenis, Gramatur] -> id_category node terdalam; gagal keras bila master mat_category tidak punya rantai itu.
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

    // Hapus IQ99DRF* lama + draft dummy lama yang digantikan. Draft yang sudah dirujuk dokumen lain tidak dihapus.
    // File artwork tidak dihapus: draft RECALC/REVISE berbagi path dengan inquiry asalnya.
    private function reset(): int
    {
        $ids = $this->db->table('iqp_inquiry')->where('data_origin', 'DUMMY')->where('inquiry_no', 'not like', self::KEEP.'%')
            ->where(fn ($q) => $q->where('inquiry_no', 'like', self::PREFIX.'%')->orWhere(fn ($d) => $d->where('sub_state', 'DRAFT')->where('stat_confirm', 0)))
            ->pluck('id_inquiry');
        $parts = $this->db->table('iqp_part')->whereIn('inquiry_id', $ids)->get(['id_part', 'inquiry_id', 'prod_part_id']);
        $partIds = $parts->pluck('id_part');
        $used = collect()
            ->merge($this->db->table('iqp_inquiry')->whereIn('parent_inquiry_id', $ids)->whereNotIn('id_inquiry', $ids)->pluck('parent_inquiry_id'))
            ->merge($this->db->table('iqp_costing')->whereIn('inquiry_id', $ids)->pluck('inquiry_id'))
            ->merge($this->db->table('quo_quotation_item')->whereIn('inquiry_id', $ids)->pluck('inquiry_id'))
            ->merge($this->db->table('co_order_item')->whereIn('inquiry_id', $ids)->pluck('inquiry_id'))
            ->merge($this->mst->table('prod_product')->whereIn('source_inquiry_id', $ids)->pluck('source_inquiry_id'))
            ->merge($this->mst->table('prod_master_card')->whereIn('inquiry_id', $ids)->pluck('inquiry_id'))
            ->merge($parts->whereIn('id_part', $this->mst->table('prod_part')->whereIn('source_iqp_part_id', $partIds)->pluck('source_iqp_part_id'))->pluck('inquiry_id'))
            ->merge($parts->whereIn('id_part', DB::connection('tm2prd')->table('prd_wo')->whereIn('source_iqp_part_id', $partIds)->pluck('source_iqp_part_id'))->pluck('inquiry_id'))
            // RECALC memakai prod_part milik product (bukan miliknya sendiri) -> bukan tanda "sudah dikirim ke ProDev" untuk IQ99DRF*.
            ->merge($parts->whereNotNull('prod_part_id')->whereNotIn('inquiry_id', $this->db->table('iqp_inquiry')->where('inquiry_no', 'like', self::PREFIX.'%')->pluck('id_inquiry'))->pluck('inquiry_id'))
            ->unique();
        $ids = $ids->diff($used)->values();
        if ($used->isNotEmpty()) {
            $this->command?->warn('Draft dummy dilewati (sudah dirujuk dokumen lain): '.$used->implode(', '));
        }

        $this->db->table('iqp_tier')->whereIn('inquiry_id', $ids)->delete();
        $this->db->table('iqp_part')->whereIn('inquiry_id', $ids)->update(['parent_part_id' => null]);
        $this->db->table('iqp_inquiry')->whereIn('id_inquiry', $ids)->delete();

        return $ids->count();
    }
}
