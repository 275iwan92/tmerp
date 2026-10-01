<?php

namespace Database\Seeders;

use App\Support\Dieline\TmDieline;
use App\Support\V2\Data\Sales;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Dummy Inquiry: SEMUA jenis NEW dan berstatus DRAFT (perintah user 2026-10-01 "rebuild data dummy for all inquiry data but
// status draft, so I could see how dieline mockup works"; dipertegas 2026-10-02 "buat semua dalam kondisi new dan draft").
// Draft = form masih bisa diedit, jadi preview dieline & 3D bisa dicoba langsung.
//   - Satu inquiry per tipe dieline aktif (prod_type yang punya dieline_spec.elements) -> semua bentuk dieline terwakili.
//     Ukuran = nilai bawaan parameter tipe, ditimpa Ukuran Pasaran pertama tipe itu (bukan angka karangan).
//   - Tiga contoh pesanan buku (BOOKS): buku tulis, buku pelajaran sekolah, buku cerita anak -- masing-masing 2 part
//     (Cover + Inner Pages) dengan bahan / warna / jumlah halaman sendiri dan Isi per Pack.
// Yang DIHAPUS tiap jalan (aturan B.12): IQ99DRF* lama (termasuk contoh RECALC / REVISE yang dulu dibuat di sini), draft dummy
// lama yang digantikan, dan dummy generik IQ269* ("... #n", SUBMITTED tanpa kelanjutan). Yang tidak disentuh: sampel desain
// customer IQ99FBX* (UatFoodBoxInquirySeeder, punya artwork asli), inquiry LIVE buatan user, dan rantai order (inquiry yang
// sudah dipakai Quotation / CO / Master Product / WO -- kalau dijadikan draft, dokumen di belakangnya kehilangan sumber).
// Hanya jalan di ddt*. Diulang = reset.
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

    // Contoh pesanan buku (tipe BUKU): [nama, catatan, isi per pack, panjang, lebar, jilid, part [nama, halaman, warna, bahan]].
    // Ukuran & bahan = yang lazim di pasaran: buku tulis 16 x 21 cm isi HVS 58 gr; buku pelajaran B5 17,6 x 25 cm;
    // buku cerita anak 21 x 21 cm isi art paper. Halaman cover = 4 (depan-belakang, luar-dalam).
    private const BOOKS = [
        ['Buku Tulis 38 Lembar', 'Exercise book, 38 ruled sheets (76 pages), saddle stitched. Packed per 10 books.', 10, 210, 160, 'SADDLE', [
            ['Cover', 4, '4/0', ['Paper', 'Art carton', '210gr']],
            ['Inner Pages', 76, '1/1', ['Paper', 'HVS', '58 GR']],
        ]],
        ['Buku Pelajaran Sekolah B5 160 Hal', 'School textbook B5, 160 pages full colour, perfect binding. Packed per 20 books.', 20, 250, 176, 'PERFECT', [
            ['Cover', 4, '4/0', ['Paper', 'Art carton', '230gr']],
            ['Inner Pages', 160, '4/4', ['Paper', 'HVS', '85 GR']],
        ]],
        ['Buku Cerita Anak 21x21 24 Hal', 'Children story book 21 x 21 cm, 24 pages full colour, saddle stitched. Packed per 50 books.', 50, 210, 210, 'SADDLE', [
            ['Cover', 4, '4/0', ['Paper', 'Art carton', '260gr']],
            ['Inner Pages', 24, '4/4', ['Paper', 'Art Papper', '150gr']],
        ]],
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
            $generic = $this->generic();
            $this->command?->info("Draft dummy lama dihapus: $removed. Dummy generik IQ269* dihapus: $generic. RECALC/REVISE lama tanpa Master Product: $deleted dihapus, $converted dijadikan NEW.");

            foreach ($types as $code => $t) {
                $n++;
                [$color, $mats] = self::SPEC[$code] ?? self::DEFAULT;
                $this->create($n, count($types) - $n, $sales, $customers, $t['name'].($t['size'] ? ' '.$t['size'] : ''),
                    'Dummy draft for dieline preview. Type '.$code.'; dimensions = '.($t['size'] ? 'standard size "'.$t['size'].'"' : 'type defaults').'.',
                    $code, $t, array_map(fn ($p) => [$p[0], $p[1], $color, $mats, null], $this->parts($code, $t['dims'])));
            }

            $book = $types['BUKU'] ?? throw new RuntimeException('Tipe BUKU tidak aktif / tidak punya dieline.');
            foreach (self::BOOKS as [$name, $note, $pack, $length, $width, $binding, $parts]) {
                $n++;
                $this->create($n, 0, $sales, $customers, $name, 'Dummy draft: book order. '.$note, 'BUKU', $book, array_map(fn ($p) => [$p[0],
                    ['product_length' => $length, 'product_width' => $width, 'page_count' => $p[1], 'binding_type' => $binding] + $book['dims'], $p[2], [$p[3]], $pack], $parts));
            }
        });

        $this->command?->info($n.' inquiry '.self::PREFIX.'* siap di '.$this->db->getDatabaseName().': semuanya NEW + DRAFT ('.count($types).' satu per tipe dieline + '.count(self::BOOKS).' contoh buku).');
    }

    // Satu inquiry NEW DRAFT. $parts = [[nama part, dims, notasi warna, bahan [[Kategori, Jenis, Gramatur]..], isi per pack|null]..].
    private function create(int $n, int $daysAgo, array $sales, array $customers, string $name, string $note, string $code, array $t, array $parts): void
    {
        $now = now();
        $inquiryId = $this->db->table('iqp_inquiry')->insertGetId([
            'inquiry_no' => $this->no($n),
            'inquiry_date' => $now->copy()->subDays($daysAgo)->toDateString(),
            'source' => self::SOURCES[$n % count(self::SOURCES)],
            'pic_sales' => $sales[$n % count($sales)],
            'customer_id' => $customers[$n % count($customers)],
            'is_prospect' => 0,
            'deadline_quotation' => $now->copy()->addDays(14)->toDateString(),
            'product_draft_name' => mb_substr($name, 0, 150),
            'sales_note' => $note,
            'inquiry_type' => 'NEW', 'revision_no' => 0, 'material_source' => 'OWN', 'job_nature' => 'PRODUCT',
            'tax_scheme' => 'PPN', 'pph_applicable' => 0, 'ppn_percent' => 11, 'pph_percent' => 2,
            'sub_state' => 'DRAFT', 'stat_confirm' => 0, 'stat_cancel' => 0, 'is_approved' => 0,
            'created_at' => $now, 'updated_at' => $now, 'created_by' => self::BY, 'updated_by' => self::BY, 'data_origin' => 'DUMMY',
        ]);

        foreach (self::TIERS as $i => $qty) {
            $this->db->table('iqp_tier')->insert(['inquiry_id' => $inquiryId, 'tier_seq' => $i + 1, 'qty' => $qty, 'is_primary' => $i === 0 ? 1 : 0,
                'created_at' => $now, 'updated_at' => $now, 'created_by' => self::BY, 'updated_by' => self::BY, 'data_origin' => 'DUMMY']);
        }

        foreach ($parts as $seq => [$partName, $dims, $color, $mats, $pack]) {
            $spec = $t['spec'];
            $spec['dims'] = $dims;
            $spec['type_code'] = $code;
            $flat = TmDieline::flat($spec, $dims);
            $partId = $this->db->table('iqp_part')->insertGetId([
                'inquiry_id' => $inquiryId, 'part_seq' => $seq + 1, 'part_code' => 'P'.($seq + 1), 'part_name' => $partName, 'part_type' => $t['part_type'],
                'qty_per_sku' => 1, 'pack_qty' => $pack, 'cfg_color' => $color, 'dieline_spec' => json_encode($spec),
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
        $convert = $ids->intersect($this->referenced($ids)->merge($this->db->table('iqp_tier')->whereIn('inquiry_id', $ids)->pluck('inquiry_id')))->values();
        $delete = $ids->diff($convert)->values();

        $this->db->table('iqp_inquiry')->whereIn('id_inquiry', $convert)->update(['inquiry_type' => 'NEW', 'revise_scope' => null, 'revise_reason' => null, 'updated_by' => self::BY, 'updated_at' => now()]);
        $this->db->table('iqp_part')->whereIn('inquiry_id', $delete)->update(['parent_part_id' => null]);
        $this->db->table('iqp_inquiry')->whereIn('id_inquiry', $delete)->delete();

        return [$delete->count(), $convert->count()];
    }

    // Dummy generik IQ269* (migration 2026_09_03 "prodev inqprod dummy workload": "... #n", SUBMITTED tanpa kelanjutan) dihapus
    // atas perintah user 2026-10-02 ("data dummy ga jelas dihapus saja"). Costing-nya ikut terhapus (FK cascade); part ProDev
    // (prod_part) yang pernah dibuat darinya TIDAK disentuh. Yang sudah dipakai dokumen lain dilewati.
    private function generic(): int
    {
        $ids = $this->db->table('iqp_inquiry')->where('data_origin', 'DUMMY')->where('inquiry_no', 'like', 'IQ269%')->pluck('id_inquiry');
        $ids = $ids->diff($this->referenced($ids))->values();

        $this->db->table('iqp_tier')->whereIn('inquiry_id', $ids)->delete();
        $this->db->table('iqp_part')->whereIn('inquiry_id', $ids)->update(['parent_part_id' => null]);
        $this->db->table('iqp_inquiry')->whereIn('id_inquiry', $ids)->delete();

        return $ids->count();
    }

    // Inquiry (dari $ids) yang sudah dipakai dokumen lain: Quotation, CO, inquiry turunan, Master Product, Master Card, ProDev, WO.
    private function referenced($ids)
    {
        $partIds = $this->db->table('iqp_part')->whereIn('inquiry_id', $ids)->pluck('inquiry_id', 'id_part');

        return collect()
            ->merge($this->db->table('quo_quotation_item')->whereIn('inquiry_id', $ids)->pluck('inquiry_id'))
            ->merge($this->db->table('co_order_item')->whereIn('inquiry_id', $ids)->pluck('inquiry_id'))
            ->merge($this->db->table('iqp_inquiry')->whereIn('parent_inquiry_id', $ids)->pluck('parent_inquiry_id'))
            ->merge($this->mst->table('prod_product')->whereIn('source_inquiry_id', $ids)->pluck('source_inquiry_id'))
            ->merge($this->mst->table('prod_master_card')->whereIn('inquiry_id', $ids)->pluck('inquiry_id'))
            ->merge($this->mst->table('prod_part')->whereIn('source_iqp_part_id', $partIds->keys())->pluck('source_iqp_part_id')->map(fn ($p) => $partIds[$p]))
            ->merge(DB::connection('tm2prd')->table('prd_wo')->whereIn('source_iqp_part_id', $partIds->keys())->pluck('source_iqp_part_id')->map(fn ($p) => $partIds[$p]))
            ->unique();
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
    // File artwork tidak dihapus: contoh RECALC/REVISE lama berbagi path dengan inquiry asalnya.
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
