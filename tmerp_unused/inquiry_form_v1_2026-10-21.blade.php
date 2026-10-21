<?php

use App\Models\Master\CustCustomer;
use App\Models\Master\MatCategory;
use App\Models\Master\ProdMarket;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdPartAttachment;
use App\Models\Master\ProdPartFinishing;
use App\Models\Master\ProdProduct;
use App\Models\Master\ProdType;
use App\Models\Master\ProdTypeSize;
use App\Models\Master\RefFinishingType;
use App\Models\Master\SalesPerson;
use App\Models\Sdi\IqpExternalCost;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpPart;
use App\Models\Sdi\IqpPartFinishing;
use App\Models\Sdi\IqpPartMaterial;
use App\Models\Sdi\IqpTier;
use App\Models\Sdi\IqpTooling;
use App\Models\Sys\SysConstant;
use App\Models\Sys\SysTheme;
use App\Support\ActiveTheme;
use App\Support\Localization\Translator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('TELASIH MAKMUR — Inkuiri')] class extends Component
{
    use WithFileUploads;

    #[Url]
    public ?int $id = null;

    #[Url]
    public string $mode = 'NEW';

    // Cross-nav read-only (wizard-bar dari ProDev/Costing/Master Card, user
    // 2026-09-04: "SELALU bisa klik ke Inquiry Product, TAPI view only saat
    // di ProDev"). Beda dari readOnly biasa (yg dipicu stat_confirm) --
    // $view=true MEMAKSA readOnly=true APAPUN status stat_confirm-nya, dan
    // kalau tidak ada $id sama sekali (job Mode #2, murni manual di ProDev,
    // tidak pernah lewat Inquiry) TIDAK memulai draft baru spt biasa --
    // tampil pesan "tidak ada Inquiry" (lihat noInquiryForView di bawah).
    // NAMA PROPERTI SENGAJA BUKAN "$view" -- ditemukan bug nyata 2026-09-04:
    // Livewire/Blade rendering pipeline internal punya variabel bernama $view
    // sendiri (View instance) yang di-extract() ke scope template SEBELUM
    // properti publik component (extract() default EXTR_SKIP -- tidak
    // menimpa variabel yang sudah ada), jadi property public $view diam-diam
    // TERTUTUPI, @if($view)/@if(!$view) di Blade jadi baca objek View
    // (selalu truthy) bukan nilai boolean kita -- KEDUA cabang if/else
    // ke-render bersamaan (dikonfirmasi via marker debug manual). costing.
    // blade.php kebetulan lolos dgn nama sama (mungkin beda urutan properti/
    // extract) tapi JANGAN pernah pakai "view" sbg nama properti Livewire.
    #[Url(as: 'view')]
    public bool $crossView = false;

    // Dibawa dari ProDev/Master Card via wizard-bar khusus utk job Mode #2
    // (tanpa Inquiry) -- satu-satunya cara balik ke Development/Master Card
    // dari sini kalau $inquiry null (lihat wizardSteps() & noInquiryForView).
    #[Url]
    public ?int $fromPart = null;

    public bool $noInquiryForView = false;

    public ?IqpInquiry $inquiry = null;

    public bool $readOnly = false;

    public string $inquiry_no = '';

    public string $inquiry_date = '';

    public string $source = 'EMAIL';

    public string $pic_sales = '';

    // Picker visual Peran Part (permintaan user 2026-09-04: "kasih gambar di
    // samping nama biar mudah dipahami sales junior") -- null = tertutup,
    // isinya index part yg lagi diisi. Dropdown murni tidak bisa nampilkan
    // icon (keterbatasan <select> native), makanya popup kartu spt picker
    // Tipe Produk yg sudah ada.
    public ?int $rolePickerPartIndex = null;

    // Picker PIC Sales (permintaan user 2026-09-04: auto dari login + bisa
    // diubah dari daftar sales internal/external, master baru `sales_person`).
    public bool $showSalesPicker = false;

    public string $salesQuery = '';

    public string $custQuery = '';

    public ?int $customer_id = null;

    public string $customer_name = '';

    public bool $is_prospect = false;

    public string $prospect_name = '';

    // Quick-add Customer Master dari dropdown pencarian (permintaan user
    // 2026-09-04: "+ New Customer... popup isi nama customer dan hal penting
    // lainnya"). Field wajib Customer Master di luar ini (approval_status,
    // tax_profile, credit_rating, kyc_status) di-default konservatif saat
    // simpan, lihat saveNewCustomer().
    public bool $showNewCustomerModal = false;

    public string $newCustName = '';

    public string $newCustType = 'local';

    public string $newCustPhone = '';

    public string $newCustEmail = '';

    public string $newCustAddress = '';

    public ?string $deadline_quotation = null;

    public string $product_draft_name = '';

    // Product Market terpilih di "Kategori Produk" (tidak disimpan) -- membuka Ukuran Pasaran milik market itu.
    public ?int $productMarketId = null;

    public string $sales_note = '';

    public string $material_source = 'OWN';

    public string $tax_scheme = 'PPN';

    public bool $pph_applicable = false;

    public string $cust_material_spec = '';

    public ?string $cust_material_eta = null;

    public string $discount_doc_type = '';

    public string $discount_doc_value = '';

    public string $discount_qty_value = '';

    public string $discount_qty_min_ton = '';

    public string $sub_state = 'DRAFT';

    public bool $stat_confirm = false;

    public bool $stat_cancel = false;

    public bool $showCancelForm = false;

    public string $cancel_reason = '';

    // Win-rate Sales (docs/BACKLOG.md §3) -- sub_state=LOST, terpisah dari
    // CANCELLED (batal sepihak internal) krn LOST = kalah bersaing/ditolak
    // customer, dipakai laporan win-rate (sales.win-rate).
    public bool $showLostForm = false;

    public string $lost_reason = '';

    /** @var array<int, array{id: ?int, qty: string, is_primary: bool, discount_item_type: string, discount_item_value: string}> */
    public array $tiers = [];

    /** @var array<int, array{id: ?int, part_name: string, part_type: string, type_code: string, qty_per_sku: string, dims: array, flat_manual: bool, flat_w: string, flat_h: string, flat_area: string, cfg_color: string, cfg_ink: string, artwork_path: ?string, artwork_original_filename: ?string, suggestions: ?array, materials: array}> */
    public array $parts = [];

    // Temp upload artwork per index part (Livewire WithFileUploads) -- lihat
    // uploadArtwork() & suggestion engine kertas/finishing/tinta di bawah.
    public array $artworkUploads = [];

    /** @var array<int, array{id: ?int, tooling_kind: string, tooling_status: string, is_new_charge: bool, cost: string, remark: string}> */
    public array $toolings = [];

    /** @var array<int, array{id: ?int, cost_kind: string, description: string, amount: string, tax_treatment: string}> */
    public array $externalCosts = [];

    public ?string $flash = null;

    // Wizard step-bar lintas modul (mockup_FORM_prodev_header.png, user
    // 2026-09-03 "ya, bikin step-bar lintas modul juga") -- pola SAMA dgn
    // prodev.blade.php::wizardSteps(), job aktif = inquiry ini. Development/
    // Master Card cuma reachable kalau minimal 1 part sudah "Kirim ke ProDev"
    // (prod_part_id terisi) -- ambil part PERTAMA yg sudah terkirim.
    #[Computed]
    public function wizardSteps(): array
    {
        // Job Mode #2 (noInquiryForView): tidak ada inquiry sama sekali, tapi
        // wizard-bar TETAP tampil (user 2026-09-04: "SELALU bisa klik") --
        // Development/Master Card balik ke $fromPart yg dibawa ProDev, Inquiry
        // step-nya sendiri ('1. Inquiry') tetap disorot aktif tanpa url baru
        // (kita memang sedang di sini, murni nampilin "tidak ada data").
        if (! $this->inquiry) {
            if (! $this->noInquiryForView) {
                return [];
            }

            return [
                'inquiry' => ['label' => '1. Inquiry', 'url' => null, 'reason' => 'Sedang di sini -- part ini Mode #2 (manual), tidak ada Inquiry.'],
                'development' => [
                    'label' => '2. Development',
                    'url' => $this->fromPart ? route('production.prodev', ['part' => $this->fromPart]) : null,
                ],
                'costing' => ['label' => '3. Costing', 'url' => null, 'reason' => 'Part ini Mode #2 (manual) -- tidak ada Inquiry utk dihitung Costing-nya.'],
                'mastercard' => [
                    'label' => 'Master Card',
                    'url' => $this->fromPart ? route('production.old-prodev.print', ['part' => $this->fromPart]) : null,
                ],
                'analytics' => ['label' => 'Analytics', 'url' => null, 'reason' => 'Modul Analytics belum dibangun.'],
            ];
        }

        $prodPartId = IqpPart::where('inquiry_id', $this->inquiry->id_inquiry)
            ->whereNotNull('prod_part_id')
            ->value('prod_part_id');

        return [
            'inquiry' => [
                'label' => '1. Inquiry',
                'url' => route('inquiry.form', ['id' => $this->inquiry->id_inquiry]),
            ],
            'development' => [
                'label' => '2. Development',
                'url' => $prodPartId ? route('production.prodev', ['part' => $prodPartId]) : null,
                'reason' => 'Belum ada part yang dikirim ke ProDev (tombol "Kirim ke ProDev" di baris part, perlu Confirm dulu).',
            ],
            'costing' => [
                'label' => '3. Costing',
                'url' => route('inquiry.costing', ['inquiry' => $this->inquiry->id_inquiry, 'view' => 1]),
            ],
            'mastercard' => [
                'label' => 'Master Card',
                'url' => $prodPartId ? route('production.old-prodev.print', ['part' => $prodPartId]) : null,
                'reason' => 'Belum ada part yang dikirim ke ProDev.',
            ],
            'analytics' => [
                'label' => 'Analytics',
                'url' => null,
                'reason' => 'Modul Analytics belum dibangun.',
            ],
        ];
    }

    // Tema (sys_theme) buat ringkasan cross-nav read-only (pola sama persis
    // production/prodev.blade.php::theme()) -- form input ASLI di bawah
    // (mode edit) SENGAJA tidak disentuh, tetap warna literal spt sebelumnya
    // (CLAUDE.md §4 "JANGAN DIUBAH").
    #[Computed]
    public function theme(): SysTheme
    {
        return ActiveTheme::resolve();
    }

    // Ringkasan Box Style + Dieline per part (user 2026-09-04: "boleh
    // improve dari attachment #1 [mockup Esko-style]" -- BUKAN reuse form
    // input asli). Sama persis logika originInquiryParts() di
    // production/prodev.blade.php (type_code+dims -> label param ProdType),
    // ditambah geometri kotak flat utk preview 2D (flat_w/flat_h SUDAH
    // tersimpan riil di iqp_part, bukan dihitung ulang/dikarang).
    //
    // Follow-up 2026-09-04 (user: "tidak ada gambar layout... tidak diecut
    // nya yang sudah di define... sertakan lebih banyak informasi"):
    // - dieline_spec/dieline_dims dikirim mentah ke component supaya JS bisa
    //   panggil window.TMDieline.svg() (resources/js/tmdieline.js) -- diecut
    //   ASLI (garis potong/lipat/lubang) yang dibekukan saat part ini dibuat,
    //   BUKAN kotak polos flat_w x flat_h seperti sebelumnya.
    // - color/materials/processes ditambahkan -- cek komentar di
    //   components/inquiry/readonly-summary.blade.php soal processes yang
    //   (jujur) memang selalu kosong utk NEW (tidak ada UI isi di form ini).
    public function partSummary(IqpPart $part): array
    {
        $spec = $part->dieline_spec ?? [];
        $typeCode = $spec['type_code'] ?? null;
        $type = $typeCode ? ProdType::with('params')->find($typeCode) : null;
        $dims = $spec['dims'] ?? [];

        $dimRows = collect($type?->params ?? [])
            ->filter(fn ($p) => array_key_exists($p->param_key, $dims) && $dims[$p->param_key] !== '' && $dims[$p->param_key] !== null)
            ->map(fn ($p) => ['label' => $p->param_label, 'value' => $dims[$p->param_key], 'uom' => $p->unit])
            ->values();

        $flatW = (float) ($part->flat_w ?? 0);
        $flatH = (float) ($part->flat_h ?? 0);
        $maxPx = 260;
        $scale = ($flatW > 0 && $flatH > 0) ? min($maxPx / $flatW, $maxPx / $flatH) : 0;

        return [
            'type_name' => $type?->type_name,
            'dims' => $dimRows,
            'flat_w' => $flatW,
            'flat_h' => $flatH,
            'flat_area' => $part->flat_area,
            'qty_per_sku' => $part->qty_per_sku,
            'flat_w_px' => $scale > 0 ? round($flatW * $scale, 1) : 0,
            'flat_h_px' => $scale > 0 ? round($flatH * $scale, 1) : 0,
            // Diecut asli (DSL prod_type.dieline_spec dibekukan + dims part ini)
            // -- null kalau tipe tak punya elements (mis. Mode manual/flat_manual).
            'dieline_spec' => ! empty($spec['elements']) ? $spec : null,
            'dieline_dims' => ! empty($dims) ? $dims : (object) [],
            'color' => $this->colorBreakdown($part->cfg_color),
            'cfg_color' => $part->cfg_color,
            'cfg_ink' => $part->cfg_ink,
            'materials' => $part->materials->map(fn (IqpPartMaterial $m) => [
                'layer_seq' => $m->layer_seq,
                'name' => $m->rawmat_name,
                'gram_weight' => $m->gram_weight,
                'flute_type' => $m->flute_type,
                'size_p' => $m->size_p,
                'size_l' => $m->size_l,
                'is_primary' => (bool) $m->is_primary,
                'material_source' => $m->material_source,
            ])->values()->all(),
            'processes' => $part->processes->map(fn ($p) => [
                'step_seq' => $p->step_seq,
                'process_name' => $p->process_name,
                'flow_type' => $p->flow_type,
                'qty_factor' => $p->qty_factor,
            ])->values()->all(),
            'finishings' => $part->finishings->map(fn ($f) => [
                'name' => $f->finishing_name,
                'attribute' => $f->attribute,
            ])->values()->all(),
            'pack_qty' => $part->pack_qty,
        ];
    }

    // Dipakai <x-inquiry.readonly-summary :parts="$this->partSummaries" /> --
    // anonymous component tak bisa panggil method Livewire ($this->partSummary()
    // langsung), jadi dihitung di sini & dikirim sbg array biasa.
    #[Computed]
    public function partSummaries(): array
    {
        if (! $this->inquiry) {
            return [];
        }

        return $this->inquiry->parts->map(fn (IqpPart $part) => [
            'part' => $part,
            'summary' => $this->partSummary($part),
        ])->all();
    }

    public function mount(): void
    {
        $this->mode = strtoupper($this->mode ?: 'NEW');

        if ($this->id) {
            $this->inquiry = IqpInquiry::with([
                'customer',
                'tiers' => fn ($q) => $q->orderBy('tier_seq'),
                'parts' => fn ($q) => $q->orderBy('part_seq'),
                'parts.materials' => fn ($q) => $q->orderBy('layer_seq'),
                'parts.materials.rawMaterial',
                'parts.processes' => fn ($q) => $q->orderBy('step_seq'),
                'parts.finishings',
                'toolings',
                'externalCosts',
            ])->findOrFail($this->id);

            if ($this->crossView) {
                // Cross-nav read-only (user 2026-09-04, koreksi eksplisit: "JANGAN
                // pakai layar inquiry product yang original karena itu untuk
                // inputan... boleh improve dari attachment #1", mockup Esko-style
                // Step 1) -- JANGAN isi state form editable sama sekali, dirender
                // via ringkasan terpisah (lihat blok @if($crossView) di template) yang
                // baca $this->inquiry (relasi Eloquent) langsung, bukan array
                // $parts/$tiers/dst yang dipakai form input.
                return;
            }

            $this->fillFromInquiry();
        } elseif ($this->crossView) {
            // Cross-nav tapi part-nya Mode #2 (murni manual, TIDAK PERNAH lewat
            // Inquiry) -- tidak ada apa pun utk diringkas, tandai saja.
            $this->noInquiryForView = true;
        } else {
            $this->inquiry_date = now()->toDateString();
            // Default +14 hari kerja kalender (permintaan user 2026-09-04: "tgl
            // kebutuhan customer default 14 hari dari today, bisa diedit") -- tetap
            // field date-picker biasa, cuma pre-fill, sales bebas ubah.
            $this->deadline_quotation = now()->addDays(14)->toDateString();
            $this->pic_sales = Auth::user()->name;

            // 3 preset qty umum (permintaan user 2026-09-04) supaya sales tak mulai
            // dari kosong -- tetap bebas ditambah/dikurang/diedit spt biasa, baris
            // pertama (10rb) otomatis Utama (default addTier() yg sudah ada).
            foreach (['10000', '50000', '200000'] as $presetQty) {
                $this->addTier();
                $this->tiers[count($this->tiers) - 1]['qty'] = $presetQty;
            }

            $this->addPart();
        }
    }

    protected function fillFromInquiry(): void
    {
        $inq = $this->inquiry;

        $this->mode = $inq->inquiry_type;
        $this->inquiry_no = $inq->inquiry_no;
        $this->inquiry_date = optional($inq->inquiry_date)->toDateString() ?? '';
        $this->source = $inq->source ?? 'EMAIL';
        $this->pic_sales = $inq->pic_sales ?? '';
        $this->customer_id = $inq->customer_id;
        $this->customer_name = $inq->customer?->customer_name ?? '';
        $this->is_prospect = (bool) $inq->is_prospect;
        $this->prospect_name = $inq->prospect_name ?? '';
        $this->deadline_quotation = optional($inq->deadline_quotation)->toDateString();
        $this->product_draft_name = $inq->product_draft_name;
        $this->sales_note = $inq->sales_note ?? '';
        $this->material_source = $inq->material_source;
        $this->tax_scheme = $inq->tax_scheme;
        $this->pph_applicable = (bool) $inq->pph_applicable;
        $this->cust_material_spec = $inq->cust_material_spec ?? '';
        $this->cust_material_eta = optional($inq->cust_material_eta)->toDateString();
        $this->discount_doc_type = $inq->discount_doc_type ?? '';
        $this->discount_doc_value = $inq->discount_doc_value !== null ? rtrim(rtrim((string) $inq->discount_doc_value, '0'), '.') : '';
        $this->discount_qty_value = $inq->discount_qty_value !== null ? rtrim(rtrim((string) $inq->discount_qty_value, '0'), '.') : '';
        $this->discount_qty_min_ton = $inq->discount_qty_min_ton !== null ? rtrim(rtrim((string) $inq->discount_qty_min_ton, '0'), '.') : '';
        $this->sub_state = $inq->sub_state;
        $this->stat_confirm = (bool) $inq->stat_confirm_sales;
        $this->stat_cancel = (bool) $inq->stat_cancel;

        // Tier 2 §11.2: begitu stat_confirm=1 form terkunci total (CONFIRMED/DONE/CANCELLED
        // semuanya turunan dari stat_confirm), bukan berdasar teks sub_state (itu label monitoring).
        $this->readOnly = $this->stat_confirm;

        $this->tiers = $inq->tiers->map(fn (IqpTier $t) => [
            'id' => $t->id_tier,
            'qty' => $t->qty !== null ? rtrim(rtrim((string) $t->qty, '0'), '.') : '',
            'is_primary' => (bool) $t->is_primary,
            'discount_item_type' => $t->discount_item_type ?? '',
            'discount_item_value' => $t->discount_item_value !== null ? rtrim(rtrim((string) $t->discount_item_value, '0'), '.') : '',
        ])->values()->all();

        if (empty($this->tiers)) {
            $this->addTier();
        }

        $this->parts = $inq->parts->map(fn (IqpPart $p) => [
            'id' => $p->id_part,
            'part_name' => $p->part_name,
            'part_type' => $p->part_type ?? 'DC',
            'type_code' => $p->dieline_spec['type_code'] ?? '',
            'qty_per_sku' => $p->qty_per_sku !== null ? rtrim(rtrim((string) $p->qty_per_sku, '0'), '.') : '1',
            'pack_qty' => $p->pack_qty ?? '',
            'dims' => $p->dieline_spec['dims'] ?? [],
            // Tidak ada kolom flat_manual di DDL (Tier 3A LOCKED) — heuristik: tipe tanpa
            // dieline_spec dianggap manual (sama seperti mockup memaksa manual saat !t.spec).
            'flat_manual' => empty($p->dieline_spec['elements'] ?? null),
            'flat_w' => $p->flat_w !== null ? rtrim(rtrim((string) $p->flat_w, '0'), '.') : '',
            'flat_h' => $p->flat_h !== null ? rtrim(rtrim((string) $p->flat_h, '0'), '.') : '',
            'flat_area' => $p->flat_area !== null ? rtrim(rtrim((string) $p->flat_area, '0'), '.') : '',
            'dims_version' => 0,
            'cfg_color' => $p->cfg_color ?? '4/4',
            'cfg_ink' => $p->cfg_ink ?? '',
            'part_role' => '',
            'artwork_path' => $p->artwork_path,
            'artwork_original_filename' => $p->artwork_original_filename,
            'suggestions' => null,
            'materials' => $this->hydrateMaterialSlots($p->materials),
            'finishings' => $p->finishings->map(fn (IqpPartFinishing $fin) => [
                'id' => $fin->id_part_finishing,
                'finishing_type_id' => $fin->finishing_type_id,
                'attribute' => $fin->attribute ?? '',
            ])->values()->all(),
        ])->values()->all();

        if (empty($this->parts)) {
            $this->addPart();
        }

        foreach ($this->parts as $i => $part) {
            if (empty($part['materials'])) {
                $this->parts[$i]['materials'] = $this->blankMaterialsPair();
            }

            if (! empty($part['type_code'])) {
                $this->refreshArtworkSuggestions($i);
            }
        }

        $this->toolings = $inq->toolings->map(fn (IqpTooling $t) => [
            'id' => $t->id_tooling,
            'tooling_kind' => $t->tooling_kind,
            'tooling_status' => $t->tooling_status,
            'is_new_charge' => (bool) $t->is_new_charge,
            'cost' => $t->cost !== null ? rtrim(rtrim((string) $t->cost, '0'), '.') : '0',
            'remark' => $t->remark ?? '',
        ])->values()->all();

        $this->externalCosts = $inq->externalCosts->map(fn (IqpExternalCost $c) => [
            'id' => $c->id_external_cost,
            'cost_kind' => $c->cost_kind,
            'description' => $c->description ?? '',
            'amount' => $c->amount !== null ? rtrim(rtrim((string) $c->amount, '0'), '.') : '0',
            'tax_treatment' => $c->tax_treatment,
        ])->values()->all();
    }

    // Tanpa gerbang panjang query lagi (dulu min. 2 karakter) -- permintaan user
    // 2026-09-04: "dropdown yang langsung bisa discroll tapi bisa diketik juga".
    // Query kosong = browse (list default, alfabetis); diketik = filter nama/kode.
    #[Computed]
    public function customerResults()
    {
        if ($this->is_prospect || $this->customer_id) {
            return collect();
        }

        return CustCustomer::query()
            ->where('activate', true)
            ->when($this->custQuery !== '', fn ($q) => $q->where(function ($q2) {
                $q2->where('customer_name', 'like', "%{$this->custQuery}%")
                    ->orWhere('customer_code', 'like', "%{$this->custQuery}%");
            }))
            ->orderBy('customer_name')
            ->limit(30)
            ->get();
    }

    // Escape dulu baru bungkus potongan yang cocok dengan <mark> — jangan pernah
    // interpolasi $query mentah ke output HTML (celah XSS).
    public function highlightMatch(string $text, string $query): string
    {
        if ($query === '') {
            return e($text);
        }

        $pos = mb_stripos($text, $query);

        if ($pos === false) {
            return e($text);
        }

        $before = mb_substr($text, 0, $pos);
        $match = mb_substr($text, $pos, mb_strlen($query));
        $after = mb_substr($text, $pos + mb_strlen($query));

        return e($before).'<mark>'.e($match).'</mark>'.e($after);
    }

    public function selectCustomer(int $customerId): void
    {
        $customer = CustCustomer::find($customerId);

        if (! $customer) {
            return;
        }

        $this->customer_id = $customer->id_customer;
        $this->customer_name = $customer->customer_name;
        $this->custQuery = '';
    }

    public function clearCustomer(): void
    {
        $this->customer_id = null;
        $this->customer_name = '';
        $this->custQuery = '';
    }

    // ================= QUICK-ADD CUSTOMER (dari dropdown pencarian, 2026-09-04) =================

    public function openNewCustomerModal(): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->newCustName = $this->custQuery;
        $this->newCustType = 'local';
        $this->newCustPhone = '';
        $this->newCustEmail = '';
        $this->newCustAddress = '';
        $this->showNewCustomerModal = true;
    }

    public function closeNewCustomerModal(): void
    {
        $this->showNewCustomerModal = false;
    }

    public function setNewCustType(string $value): void
    {
        $this->newCustType = $value;
    }

    public function saveNewCustomer(): void
    {
        if ($this->readOnly) {
            return;
        }

        $data = $this->validate([
            'newCustName' => ['required', 'string', 'max:200'],
            'newCustType' => ['required', 'in:local,import'],
            'newCustPhone' => ['nullable', 'string', 'max:50'],
            'newCustEmail' => ['nullable', 'email', 'max:150'],
            'newCustAddress' => ['nullable', 'string'],
        ], [], [
            'newCustName' => 'Nama Customer',
            'newCustType' => 'Tipe Customer',
            'newCustPhone' => 'No. Telp',
            'newCustEmail' => 'Email',
            'newCustAddress' => 'Alamat',
        ]);

        // Field wajib Customer Master di luar yg diisi di popup (approval_status/
        // tax_profile/credit_rating/kyc_status) DI-DEFAULT KONSERVATIF -- customer
        // ini masih "DRAFT/belum divalidasi", BUKAN otomatis boleh transaksi penuh
        // (credit_rating='C' = risiko tertinggi, kyc_status='PENDING'). Finance/
        // Credit Control tetap review manual via layar Master Customer (alur
        // approval_status DRAFT->CREDIT_CHECK->APPROVED yg sudah ada, tidak
        // di-bypass oleh quick-add ini).
        $customer = CustCustomer::create([
            'customer_code' => $this->generateQuickCustomerCode(),
            'customer_name' => $data['newCustName'],
            'customer_type' => $data['newCustType'],
            'phone' => $data['newCustPhone'] ?: null,
            'email' => $data['newCustEmail'] ?: null,
            'default_address' => $data['newCustAddress'] ?: null,
            'approval_status' => 'DRAFT',
            'tax_profile' => 'PPN_11',
            'credit_rating' => 'C',
            'kyc_status' => 'PENDING',
            'is_prospect' => false,
            'activate' => true,
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ]);

        $this->selectCustomer($customer->id_customer);
        $this->showNewCustomerModal = false;
        $this->flash = "Customer baru \"{$customer->customer_name}\" ({$customer->customer_code}) dibuat -- status DRAFT, perlu direview Finance/Credit di Master Customer sebelum transaksi penuh.";
    }

    protected function generateQuickCustomerCode(): string
    {
        $prefix = 'QC'.now()->format('ym');

        $last = CustCustomer::where('customer_code', 'like', "{$prefix}%")
            ->orderByDesc('customer_code')
            ->value('customer_code');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    // ================= PICKER PIC SALES (master sales_person, 2026-09-04) =================

    public function openSalesPicker(): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->salesQuery = '';
        $this->showSalesPicker = true;
    }

    public function closeSalesPicker(): void
    {
        $this->showSalesPicker = false;
    }

    #[Computed]
    public function salesPersonResults()
    {
        return SalesPerson::query()
            ->where('activate', true)
            ->when($this->salesQuery !== '', fn ($q) => $q->where('sales_name', 'like', "%{$this->salesQuery}%"))
            ->orderBy('sales_type')
            ->orderBy('sales_name')
            ->limit(20)
            ->get();
    }

    public function selectSalesPerson(int $id): void
    {
        $sp = SalesPerson::find($id);

        if (! $sp) {
            return;
        }

        $this->pic_sales = $sp->sales_name;
        $this->showSalesPicker = false;
    }

    // Tombol toggle (bukan <select>) sengaja dipakai untuk properti dua/tiga-pilihan ini:
    // Livewire v4 tidak konsisten mensinkronkan `selected`/nilai <select> saat elemen itu
    // ikut ter-morph dalam re-render komponen full-page — class CSS pada <button> selalu benar.
    public function setProspect(bool $value): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->is_prospect = $value;

        if ($value) {
            $this->clearCustomer();
        } else {
            $this->prospect_name = '';
        }
    }

    // job_nature diturunkan dari material_source (Tier1 §5.2) — bukan pilihan bebas user.
    public function jobNature(): string
    {
        return $this->material_source === 'CUSTOMER' ? 'JASA' : 'PRODUCT';
    }

    public function statusPillClass(): string
    {
        return match ($this->sub_state) {
            'DRAFT' => 'draft',
            'WAITING_COST', 'SUBMITTED', 'PRICE_CALCULATED' => 'submit',
            'PRICE_APPROVED' => 'priceok',
            'QUOTED' => 'quoted',
            'PROVING', 'PROVE_APPROVED' => 'proving',
            'CONVERTED' => 'ok',
            'CANCELLED', 'LOST' => 'cancelled',
            default => 'draft',
        };
    }

    public function setMaterialSource(string $value): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->material_source = $value;

        // Default sensible: JASA (bahan dari customer) biasanya kena PPh23 — tetap bisa diubah manual.
        $this->pph_applicable = $value === 'CUSTOMER';
    }

    public function setTaxScheme(string $value): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->tax_scheme = $value;
    }

    public function setPph(bool $value): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->pph_applicable = $value;
    }

    // ================= DISKON DOKUMEN (iqp_inquiry.discount_doc_*, Tier1 F10) =================

    public function setDiscountDocType(string $type): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->discount_doc_type = $type;

        if ($type === '') {
            $this->discount_doc_value = '';
        }
    }

    // ================= QTY TIER (Tier1 §2.4/§3.4.2 — "tier qty, pilih UTAMA") =================

    public function addTier(): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->tiers[] = [
            'id' => null,
            'qty' => '',
            'is_primary' => count($this->tiers) === 0,
            'discount_item_type' => '',
            'discount_item_value' => '',
        ];
    }

    public function removeTier(int $index): void
    {
        if ($this->readOnly || count($this->tiers) <= 1 || ! isset($this->tiers[$index])) {
            return;
        }

        $wasPrimary = $this->tiers[$index]['is_primary'];

        unset($this->tiers[$index]);
        $this->tiers = array_values($this->tiers);

        // Invarian "tepat 1 UTAMA" dijaga di client juga, bukan cuma dicek saat simpan.
        if ($wasPrimary) {
            $this->tiers[0]['is_primary'] = true;
        }
    }

    public function setPrimaryTier(int $index): void
    {
        if ($this->readOnly || ! isset($this->tiers[$index])) {
            return;
        }

        foreach ($this->tiers as $i => $tier) {
            $this->tiers[$i]['is_primary'] = ($i === $index);
        }
    }

    public function setTierDiscountType(int $index, string $type): void
    {
        if ($this->readOnly || ! isset($this->tiers[$index])) {
            return;
        }

        $this->tiers[$index]['discount_item_type'] = $type;

        if ($type === '') {
            $this->tiers[$index]['discount_item_value'] = '';
        }
    }

    // ================= PART & DIELINE (Tier1 §5.3/§5.11 — F19 dieline_spec DSL) =================

    #[Computed]
    public function productTypeOptions(): array
    {
        return ProdType::query()
            ->where('activate', true)
            ->orderBy('sort_order')
            ->with([
                'params' => fn ($q) => $q->where('activate', true)->orderBy('seq'),
                'sizes' => fn ($q) => $q->where('activate', true)->orderBy('sort_order')->with('values'),
            ])
            ->get()
            ->mapWithKeys(fn (ProdType $t) => [
                $t->type_code => [
                    'name' => $t->type_name,
                    'spec' => $t->dieline_spec,
                    'params' => $t->params->map(fn ($p) => [
                        'key' => $p->param_key,
                        'label' => $p->param_label,
                        'uom' => $p->unit,
                        'type' => $p->param_type,
                        'options' => $p->option_json ?? [],
                        'default' => $p->param_type === 'ENUM' ? ($p->default_str ?? '') : (float) $p->default_value,
                    ])->values()->all(),
                    // Ukuran umum Tipe Produk + ukuran milik Product Market yang sedang dipilih.
                    'sizes' => $t->sizes->filter(fn ($s) => $s->market_id === null || (int) $s->market_id === $this->productMarketId)->map(fn ($s) => [
                        'id' => $s->id_type_size,
                        'label' => $s->size_label,
                        'values' => $s->values->mapWithKeys(fn ($v) => [
                            $v->param_key => $v->value_num !== null ? (float) $v->value_num : $v->value_str,
                        ])->all(),
                    ])->values()->all(),
                ],
            ])
            ->all();
    }

    // State awal Alpine (dielinePart) per baris part — dims dipaksa jadi object walau
    // kosong supaya @js() tidak encode jadi [] (array) di JS, melainkan {}.
    public function partAlpineInit(int $i): array
    {
        $p = $this->parts[$i];

        return [
            'typeCode' => $p['type_code'] ?? '',
            'dims' => empty($p['dims']) ? (object) [] : $p['dims'],
            'flatManual' => (bool) ($p['flat_manual'] ?? false),
        ];
    }

    public function addPart(): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->parts[] = [
            'id' => null,
            'part_name' => '',
            'part_type' => 'DC',
            'type_code' => '',
            'qty_per_sku' => '1',
            'pack_qty' => '',
            'dims' => [],
            'flat_manual' => false,
            'flat_w' => '',
            'flat_h' => '',
            'flat_area' => '',
            'size_preset_id' => '',
            'dims_version' => 0,
            'cfg_color' => '4/4',
            'cfg_ink' => '',
            'artwork_path' => null,
            'artwork_original_filename' => null,
            'suggestions' => null,
            'part_role' => '',
            'materials' => $this->blankMaterialsPair(),
            'finishings' => [],
        ];

        // Tipe Produk level Inquiry (2026-09-04) -- part baru ikut tipe yg
        // sudah dipilih part lain (konsisten dgn setSharedProductType()),
        // supaya "+ Tambah Part" manual tidak nyasar balik ke gerbang "pilih
        // tipe dulu" padahal Inquiry-nya sudah punya tipe. Kosong kalau
        // memang belum ada tipe sama sekali (Inquiry baru).
        $sharedTypeCode = $this->parts[0]['type_code'] ?? '';

        if ($sharedTypeCode !== '') {
            $newIndex = count($this->parts) - 1;
            $this->selectPartType($newIndex, $sharedTypeCode);

            if (! $this->typeNeedsRoleChoice($sharedTypeCode)) {
                $this->applyRoleToPart($newIndex, 'monoform');
            }
        }
    }

    // Bahan (user 2026-09-29): sales tidak tahu ukuran plano & tidak berwenang
    // pilih brand -- cuma pilih Jenis + Gramatur/Spec dari hirarki mat_category
    // (pola sama dgn "Lookup Product Market" -> Nilai Awal Inquiry New).
    // rawmat_id/size_p/size_l TIDAK diisi sales, dibiarkan null (resolve brand
    // & ukuran plano spesifik = kerjaan Purchasing/ProDev belakangan).
    protected function blankMaterialRow(bool $isPrimary = false): array
    {
        return [
            'id' => null,
            'rawmat_id' => null,
            'category_id' => null,
            'jenis_id' => null,
            'gramatur_id' => null,
            'display_name' => '',
            'gram_weight' => null,
            'flute_type' => null,
            'size_p' => null,
            'size_l' => null,
            'is_primary' => $isPrimary,
        ];
    }

    // Slot #1 = Paper, slot #2 = Sheet/SF -- 2 hal besar bahan baku utama
    // (keputusan user 2026-09-29), tetap ada meski salah satu belum diisi.
    protected function blankMaterialsPair(): array
    {
        return [$this->blankMaterialRow(true), $this->blankMaterialRow(false)];
    }

    // Bangun ulang 2 slot dari baris iqp_part_material tersimpan (layer_seq
    // 1=Paper, 2=Sheet) -- turunkan balik jenis_id/gramatur_id dari
    // category_id via ancestorChain() supaya dropdown ke-restore saat edit.
    protected function hydrateMaterialSlots($materials): array
    {
        $slots = $this->blankMaterialsPair();

        foreach ($materials as $m) {
            $slot = (int) $m->layer_seq - 1;

            if ($slot !== 0 && $slot !== 1) {
                continue;
            }

            $chain = $m->category_id ? MatCategory::find($m->category_id)?->ancestorChain() : null;

            $slots[$slot] = [
                'id' => $m->id_part_material,
                'rawmat_id' => $m->rawmat_id,
                'category_id' => $m->category_id,
                'jenis_id' => $chain[1]->id_category ?? null,
                'gramatur_id' => $chain[2]->id_category ?? null,
                'display_name' => $m->rawmat_name ?? '',
                'gram_weight' => $m->gram_weight,
                'flute_type' => $m->flute_type,
                'size_p' => $m->size_p,
                'size_l' => $m->size_l,
                'is_primary' => (bool) $m->is_primary,
            ];
        }

        return $slots;
    }

    // id_category root 'Paper' (slot 0) / 'Sheet' (slot 1), sama persis dgn
    // ProdMarket::CHAIN_ROOT di layar Lookup Product Market.
    #[Computed]
    public function materialChainRoots(): array
    {
        return [
            MatCategory::roots()->where('category_name', 'Paper')->value('id_category'),
            MatCategory::roots()->where('category_name', 'Sheet')->value('id_category'),
        ];
    }

    public function materialSlotLabel(int $slot): string
    {
        return $slot === 0 ? 'Paper' : 'Sheet / SF';
    }

    public function materialJenisOptions(int $slot): array
    {
        $rootId = $this->materialChainRoots[$slot] ?? null;

        if (! $rootId) {
            return [];
        }

        return MatCategory::where('parent_id', $rootId)->where('level', 2)->where('activate', true)
            ->orderBy('category_name')->pluck('category_name', 'id_category')->all();
    }

    public function materialGramaturOptions(int $partIndex, int $slot): array
    {
        $jenisId = $this->parts[$partIndex]['materials'][$slot]['jenis_id'] ?? null;

        if (! $jenisId) {
            return [];
        }

        return MatCategory::where('parent_id', $jenisId)->where('level', 3)->where('activate', true)
            ->orderBy('category_name')->pluck('category_name', 'id_category')->all();
    }

    public function setMaterialJenis(int $partIndex, int $slot, ?string $jenisId): void
    {
        if ($this->readOnly || ! isset($this->parts[$partIndex]['materials'][$slot])) {
            return;
        }

        $this->parts[$partIndex]['materials'][$slot]['jenis_id'] = $jenisId !== '' && $jenisId !== null ? (int) $jenisId : null;
        $this->parts[$partIndex]['materials'][$slot]['gramatur_id'] = null;
        $this->resolveMaterialRow($partIndex, $slot);
    }

    public function setMaterialGramatur(int $partIndex, int $slot, ?string $gramaturId): void
    {
        if ($this->readOnly || ! isset($this->parts[$partIndex]['materials'][$slot])) {
            return;
        }

        $this->parts[$partIndex]['materials'][$slot]['gramatur_id'] = $gramaturId !== '' && $gramaturId !== null ? (int) $gramaturId : null;
        $this->resolveMaterialRow($partIndex, $slot);
    }

    // Node terdalam yang terpilih (Gramatur kalau ada, kalau tidak Jenis) jadi
    // category_id yang disimpan -- turunkan display_name (chain label "Paper
    // › Kraft › 300gr"), gram_weight (parse angka depan nama Gramatur), dan
    // flute_type (nama Jenis, khusus slot Sheet) dari situ.
    protected function resolveMaterialRow(int $partIndex, int $slot): void
    {
        $row = $this->parts[$partIndex]['materials'][$slot];
        $deepestId = $row['gramatur_id'] ?: $row['jenis_id'];

        if (! $deepestId) {
            $this->parts[$partIndex]['materials'][$slot]['category_id'] = null;
            $this->parts[$partIndex]['materials'][$slot]['display_name'] = '';
            $this->parts[$partIndex]['materials'][$slot]['gram_weight'] = null;
            $this->parts[$partIndex]['materials'][$slot]['flute_type'] = null;

            return;
        }

        $node = MatCategory::find($deepestId);

        if (! $node) {
            return;
        }

        $chain = $node->ancestorChain();
        $gramaturNode = $row['gramatur_id'] ? $node : null;
        $jenisNode = $chain[1] ?? null;

        $this->parts[$partIndex]['materials'][$slot]['category_id'] = $deepestId;
        $this->parts[$partIndex]['materials'][$slot]['display_name'] = implode(' › ', array_map(fn ($n) => $n->category_name, $chain));
        $this->parts[$partIndex]['materials'][$slot]['gram_weight'] = $gramaturNode && preg_match('/(\d+)/', (string) $gramaturNode->category_name, $m) ? (float) $m[1] : null;
        $this->parts[$partIndex]['materials'][$slot]['flute_type'] = $slot === 1 ? $jenisNode?->category_name : null;
    }

    public function removePart(int $index): void
    {
        if ($this->readOnly || count($this->parts) <= 1 || ! isset($this->parts[$index])) {
            return;
        }

        unset($this->parts[$index]);
        $this->parts = array_values($this->parts);
    }

    // ================= PERAN PART -> AUTO NAMA (2026-09-04) =================
    // Istilah dari riset glosarium packaging (Esko ArtiosCAD, corrugated box
    // glossary) -- Body dipakai jg utk produk 1-part yg berbentuk container,
    // Utama khusus utk produk NON-container (poster/label/hang tag) yg "Body"
    // terasa janggal. Left/Right selaras branch_code (L/R) yg sudah ada di
    // iqp_part_process. Nama hasil generate BEKU (tidak auto-update lagi kalau
    // Nama Produk diedit belakangan) -- keputusan eksplisit user, biar tidak
    // berubah tak terduga. Duplikat role di part lain otomatis diberi angka.
    protected function partRoleOptions(): array
    {
        return [
            'body' => ['label' => 'Body', 'icon' => '📦', 'desc' => 'Bagian struktural utama'],
            'lid' => ['label' => 'Lid', 'icon' => '🔝', 'desc' => 'Tutup terpisah'],
            'base' => ['label' => 'Base', 'icon' => '⬇️', 'desc' => 'Alas terpisah'],
            'sleeve' => ['label' => 'Sleeve', 'icon' => '🧦', 'desc' => 'Selongsong luar'],
            'tray' => ['label' => 'Tray', 'icon' => '🍱', 'desc' => 'Baki dalam'],
            'insert' => ['label' => 'Insert', 'icon' => '🧩', 'desc' => 'Fitment/pengganjal dalam'],
            'divider' => ['label' => 'Divider', 'icon' => '🗂️', 'desc' => 'Sekat antar-kompartemen'],
            'left' => ['label' => 'Left', 'icon' => '◀️', 'desc' => 'Sisi kiri'],
            'right' => ['label' => 'Right', 'icon' => '▶️', 'desc' => 'Sisi kanan'],
            'monoform' => ['label' => 'MonoForm', 'icon' => '1️⃣', 'desc' => 'Bukan container, 1 lembar (poster/label/hang tag) — istilah dari "Mono Carton"'],
        ];
    }

    public function openRolePicker(int $index): void
    {
        if ($this->readOnly || ! isset($this->parts[$index])) {
            return;
        }

        $this->rolePickerPartIndex = $index;
    }

    public function closeRolePicker(): void
    {
        $this->rolePickerPartIndex = null;
    }

    public function selectPartRole(string $role): void
    {
        $index = $this->rolePickerPartIndex;

        if ($this->readOnly || $index === null || ! isset($this->parts[$index]) || ! isset($this->partRoleOptions()[$role])) {
            return;
        }

        $this->applyRoleToPart($index, $role);
        $this->rolePickerPartIndex = null;
    }

    protected function applyRoleToPart(int $index, string $role): void
    {
        $productSlug = Str::slug($this->product_draft_name, '') ?: 'produk';
        $base = $productSlug.'-'.$role;

        $existingNames = collect($this->parts)
            ->reject(fn ($p, $i) => $i === $index)
            ->pluck('part_name')
            ->filter();

        $candidate = $base;
        $n = 2;

        while ($existingNames->contains($candidate)) {
            $candidate = $base.'-'.$n;
            $n++;
        }

        $this->parts[$index]['part_role'] = $role;
        $this->parts[$index]['part_name'] = $candidate;
    }

    // ================= TEMPLATE MULTI-PART per Tipe Produk (2026-09-04) =================
    // Permintaan user: "part-part ini harusnya sudah diketahui saat Tipe Produk
    // dipilih -- misal box sepatu [Box with Lid] sudah tahu part-partnya".
    // Revisi (sesi sama, setelah ditemukan prod_type.has_multi_part SUDAH ADA &
    // terisi data kanonik): map di bawah cuma didaftar utk tipe yg (a) flag
    // has_multi_part=true DI DB *dan* (b) dieline_spec-nya sungguhan sudah
    // dibangun (gen+elements nyata, dicek manual satu-satu) *dan* (c) makna
    // "2 bagian"-nya cocok pola Body+Lid (BUKAN tipe cetak commercial spt Buku/
    // Kalender/Majalah yg has_multi_part=true-nya berarti multi-HALAMAN, beda
    // konsep sama sekali). SLEEVE SENGAJA DIHAPUS dari map ini -- data kanonik
    // bilang has_multi_part=false utk SLEEVE (beda dari asumsi awal saya),
    // partTemplateSuggestion() di bawah cross-check ke DB jadi keputusan akhir
    // ada di situ, bukan di map lokal ini. MATCH_BOX/POLYGONAL_BOX_LID dibuang
    // krn dieline_spec-nya kosong (0 elements, belum pernah dibangun) --
    // percuma disarankan kalau preview-nya bakal blank. Pola "engine suggests,
    // officer confirms" (keputusan user): tampil sbg banner + tombol, TIDAK
    // otomatis langsung nambah part tanpa diklik.
    protected function partTemplateMap(): array
    {
        $bodyLid = [['role' => 'body', 'label' => 'Body'], ['role' => 'lid', 'label' => 'Lid']];

        return [
            'BOX_WITH_LID' => $bodyLid,
            'ROUND_BOX' => $bodyLid,
            'SHALLOW_BOX' => $bodyLid,
        ];
    }

    public function partTemplateSuggestion(int $index): ?array
    {
        $row = $this->parts[$index] ?? null;

        if (! $row || empty($row['type_code'])) {
            return null;
        }

        $template = $this->partTemplateMap()[$row['type_code']] ?? null;

        if (! $template) {
            return null;
        }

        // Cross-check ke data kanonik has_multi_part -- kalau master data
        // diubah nanti (mis. tipe ini dikoreksi jadi bukan multi-part), saran
        // otomatis berhenti sendiri tanpa perlu ubah kode di sini.
        if (! ProdType::where('type_code', $row['type_code'])->value('has_multi_part')) {
            return null;
        }

        $existingRoles = collect($this->parts)->pluck('part_role')->filter()->all();
        $missing = collect($template)->reject(fn ($t) => in_array($t['role'], $existingRoles, true));

        if ($missing->isEmpty()) {
            return null;
        }

        return [
            'labels' => $missing->pluck('label')->implode(' + '),
            'roles' => $missing->pluck('role')->values()->all(),
            'total' => count($template),
        ];
    }

    public function applyPartTemplate(int $index): void
    {
        if ($this->readOnly || ! isset($this->parts[$index])) {
            return;
        }

        $suggestion = $this->partTemplateSuggestion($index);

        if (! $suggestion) {
            return;
        }

        $roles = $suggestion['roles'];

        // Part yg lagi diedit belum punya role -> isi part INI dulu pakai role
        // pertama yg hilang, drpd bikin part baru sementara part yg dipilih
        // sendiri masih tanpa nama/role.
        if (empty($this->parts[$index]['part_role'])) {
            $firstRole = array_shift($roles);
            $this->applyRoleToPart($index, $firstRole);
        }

        // Part tambahan (mis. Lid) ikut Tipe Produk yg SAMA dgn part sumber --
        // BOX_WITH_LID/ROUND_BOX/SHALLOW_BOX generatornya (TRAY/CYLINDER) memang
        // dipakai bareng utk Body maupun Lid, cuma beda dimensi (bukan tipe
        // berbeda). Dimensi TIDAK di-otak-atik otomatis di sini -- ditemukan
        // param `lid_height` sudah ada di skema BOX_WITH_LID/ROUND_BOX tapi
        // belum jelas apakah generator dieline-nya sungguhan memakainya;
        // officer isi manual dulu sampai itu dikonfirmasi (drpd nebak formula
        // yg bisa menghasilkan dieline salah).
        foreach ($roles as $role) {
            // addPart() otomatis mewarisi Tipe Produk part[0] (lihat catatannya),
            // tidak perlu dipanggil ulang manual di sini.
            $this->addPart();
            $this->applyRoleToPart(count($this->parts) - 1, $role);
        }
    }

    // ================= KATEGORI PRODUK PASAR (2026-09-04) =================
    // Permintaan user: "pindah ke atas, pilihan lebih detail di pasaran umum
    // spt box kosmetik" -- lapisan BARU di atas Part, bahasa yg dikenal sales
    // (bukan istilah teknis dieline spt "Box with Lid"/"Shallow Box"). Pola
    // interaksi disamakan dgn "Ukuran Pasaran" yg sudah ada (dropdown ringkas
    // setelah pilih -> isi nilai default, bukan modal baru). Daftar TETAP di
    // kode dulu (keputusan user, upgrade ke Master screen kalau daftarnya
    // sering berubah). 1 kategori = 1 struktur default (keputusan user) --
    // pilih kategori -> set Tipe Produk Part 1 + langsung terapkan template
    // Body/Lid kalau tipe itu multi-part (reuse applyPartTemplate() yg sama).
    // Officer tetap bebas edit part/tipe manual sesudahnya, ini cuma starting
    // point, bukan kunci mati.
    protected function productCategoryMap(): array
    {
        // Sejak 2026-09-26 dari master Lookup Product Market (tm2mst.prod_market, diisi officer ProDev),
        // bukan lagi daftar tetap di kode.
        // Hanya Detail Kategori (punya Tipe Produk); Kategori induk jadi awalan label.
        return ProdMarket::with(['parent', 'paperCategory', 'sheetCategory'])->where('activate', true)->whereNotNull('type_code')->orderBy('sort_order')->orderBy('market_label')->get()
            ->mapWithKeys(fn ($m) => [$m->market_code => ['label' => ($m->parent ? $m->parent->market_label.' › ' : '').$m->market_label, 'type_code' => $m->type_code, 'id' => $m->id_market,
                // Slot #0 = Paper, slot #1 = Sheet/SF (nama chain, id_category node terdalam default).
                'materials' => [$m->paper_category_id, $m->sheet_category_id],
                'color' => $m->default_color, 'tiers' => $m->tierList()]])->all();
    }

    public function applyProductCategory(string $category): void
    {
        if ($this->readOnly || $category === '' || ! isset($this->productCategoryMap()[$category])) {
            return;
        }

        if (! isset($this->parts[0])) {
            $this->addPart();
        }

        $market = $this->productCategoryMap()[$category];
        $this->productMarketId = $market['id'];
        unset($this->productTypeOptions);
        $this->setSharedProductType($market['type_code']);

        // Nilai awal dari Product Market -- tetap bisa diedit sesudahnya.
        foreach (array_keys($this->parts) as $i) {
            if ($market['color']) {
                $this->parts[$i]['cfg_color'] = $market['color'];
            }
            // Bahan slot #1 Paper / #2 Sheet-SF: default dari Product Market kalau
            // slot itu belum diisi sales (rantai lengkap sampai Jenis/Gramatur).
            foreach ($market['materials'] as $slot => $categoryId) {
                if (! $categoryId || ($this->parts[$i]['materials'][$slot]['category_id'] ?? null)) {
                    continue;
                }

                $chain = MatCategory::find($categoryId)?->ancestorChain();

                if (! $chain) {
                    continue;
                }

                $this->parts[$i]['materials'][$slot]['jenis_id'] = $chain[1]->id_category ?? null;
                $this->parts[$i]['materials'][$slot]['gramatur_id'] = $chain[2]->id_category ?? null;
                $this->resolveMaterialRow($i, $slot);
            }
        }

        // Jenjang qty hanya diganti selama belum ada tier yang tersimpan.
        if ($market['tiers'] && collect($this->tiers)->every(fn ($t) => empty($t['id']))) {
            $this->tiers = [];
            foreach ($market['tiers'] as $qty) {
                $this->addTier();
                $this->tiers[count($this->tiers) - 1]['qty'] = (string) $qty;
            }
        }
    }

    // ================= TIPE PRODUK level Inquiry, bukan per-part (2026-09-04) =================
    // Permintaan user: "tipe produk harusnya di luar part... diecut baru jalan
    // ketika part dipilih spt body atau lid. Tapi jika MonoForm maka auto
    // diecut muncul dan pilihan part juga MonoForm." Klarifikasi eksplisit
    // user: yang TIDAK BOLEH diubah adalah kemampuan render diecut (TMDieline
    // DSL) -- ini murni relokasi alur pemilihan UI, TMDieline.svg()/flat() &
    // dieline_spec sama sekali tidak disentuh. 1 Tipe Produk berlaku utk semua
    // Part dalam 1 Inquiry (koheren krn satu2nya template multi-part yg
    // didukung sekarang -- Box with Lid/Round Box/Shallow Box -- generatornya
    // memang dipakai bareng Body+Lid, bukan tipe berbeda per part).
    public function setSharedProductType(string $typeCode): void
    {
        if ($this->readOnly) {
            return;
        }

        $autoRole = $typeCode !== '' && ! $this->typeNeedsRoleChoice($typeCode);

        foreach ($this->parts as $i => $part) {
            $this->selectPartType($i, $typeCode);

            if ($autoRole) {
                // Tipe 1-bentuk (non multi-part) -- tak perlu tanya Peran Part,
                // dieline langsung aktif begitu tipe dipilih.
                $this->applyRoleToPart($i, 'monoform');
            } else {
                $this->parts[$i]['part_role'] = '';
            }
        }
    }

    protected function typeNeedsRoleChoice(string $typeCode): bool
    {
        if (! isset($this->partTemplateMap()[$typeCode])) {
            return false;
        }

        return (bool) ProdType::where('type_code', $typeCode)->value('has_multi_part');
    }

    // Ganti tipe produk part: reset dims ke default prod_type_param milik tipe baru.
    // dims_version dinaikkan supaya wire:key blok dieline berubah -> Livewire mem-
    // buat ulang elemennya -> Alpine dielinePart re-init dari data server terbaru
    // (bukan cuma di-morph, yang akan menyisakan state Alpine lama/basi).
    public function selectPartType(int $index, string $typeCode): void
    {
        if ($this->readOnly || ! isset($this->parts[$index])) {
            return;
        }

        $this->parts[$index]['type_code'] = $typeCode;
        $this->parts[$index]['dims'] = [];
        $this->parts[$index]['flat_w'] = '';
        $this->parts[$index]['flat_h'] = '';
        $this->parts[$index]['flat_area'] = '';
        $this->parts[$index]['size_preset_id'] = '';
        $this->parts[$index]['dims_version'] = ($this->parts[$index]['dims_version'] ?? 0) + 1;

        $this->refreshArtworkSuggestions($index);

        if ($typeCode === '') {
            return;
        }

        $type = ProdType::with(['params' => fn ($q) => $q->where('activate', true)])->find($typeCode);

        if (! $type) {
            return;
        }

        $this->parts[$index]['flat_manual'] = empty($type->dieline_spec['elements'] ?? null);

        foreach ($type->params as $param) {
            $this->parts[$index]['dims'][$param->param_key] = $param->param_type === 'ENUM'
                ? (string) ($param->default_str ?? '')
                : (string) ($param->default_value ?? '');
        }
    }

    public function applySizePreset(int $index, string $sizeId): void
    {
        if ($this->readOnly || ! isset($this->parts[$index]) || $sizeId === '') {
            return;
        }

        $size = ProdTypeSize::with('values')->find($sizeId);

        if (! $size || $size->type_code !== $this->parts[$index]['type_code']) {
            return;
        }

        foreach ($size->values as $val) {
            $this->parts[$index]['dims'][$val->param_key] = $val->value_num !== null
                ? (string) $val->value_num
                : (string) ($val->value_str ?? '');
        }

        $this->parts[$index]['size_preset_id'] = $sizeId;
        $this->parts[$index]['dims_version'] = ($this->parts[$index]['dims_version'] ?? 0) + 1;
    }

    // ================= WARNA / CETAK (iqp_part.cfg_color/cfg_ink) =================

    // Turunan tampilan saja (front/back/total/side) dari notasi "sisiA/sisiB" — tidak
    // ada kolom terpisah di DDL, murni bantu baca untuk user (selaras mockup E8).
    public function colorBreakdown(?string $code): ?array
    {
        if (! $code || ! preg_match('/^(\d+)\/(\d+)$/', trim($code), $m)) {
            return null;
        }

        $front = (int) $m[1];
        $back = (int) $m[2];

        return [
            'front' => $front,
            'back' => $back,
            'total' => $front + $back,
            'side' => $back > 0 ? '2SIDE' : '1SIDE',
        ];
    }

    // ================= BAHAN / MATERIAL per part (iqp_part_material, Tier1 §5.11) =================
    // 2 slot tetap (bukan add/remove bebas lagi, user 2026-09-29): #1 Paper,
    // #2 Sheet/SF. Lihat blankMaterialRow()/resolveMaterialRow() di atas.

    public function setMaterialPrimary(int $partIndex, int $matIndex): void
    {
        if ($this->readOnly || ! isset($this->parts[$partIndex]['materials'][$matIndex])) {
            return;
        }

        foreach ($this->parts[$partIndex]['materials'] as $j => $row) {
            $this->parts[$partIndex]['materials'][$j]['is_primary'] = ($j === $matIndex);
        }
    }

    // ================= FINISHING TAMBAHAN per part (iqp_part_finishing) =================
    // Master-detail: tiap part boleh punya berapa pun baris finishing (hot print,
    // UV spot, emboss, dst) -- permintaan user "master detail bisa ditambah apa
    // saja, buat dropdown nya". Dropdown sumber ref_finishing_type (tm2mst, master
    // yg SAMA dipakai ProDev). Sejak 2026-09-17 disimpan terstruktur (id +
    // atribut, mis. warna foil) karena jadi input engine BOP/BOM -- dulu teks
    // bebas di iqp_part_process.

    #[Computed]
    public function finishingTypeOptions()
    {
        return RefFinishingType::where('activate', true)->orderBy('finishing_name')->get();
    }

    public function addFinishingRow(int $partIndex): void
    {
        if ($this->readOnly || ! isset($this->parts[$partIndex])) {
            return;
        }

        $this->parts[$partIndex]['finishings'][] = ['id' => null, 'finishing_type_id' => '', 'attribute' => ''];
    }

    public function removeFinishingRow(int $partIndex, int $rowIndex): void
    {
        if ($this->readOnly || ! isset($this->parts[$partIndex]['finishings'][$rowIndex])) {
            return;
        }

        unset($this->parts[$partIndex]['finishings'][$rowIndex]);
        $this->parts[$partIndex]['finishings'] = array_values($this->parts[$partIndex]['finishings']);
    }

    public function applySuggestedFinishing(int $partIndex, int $finishingTypeId): void
    {
        if ($this->readOnly || ! isset($this->parts[$partIndex])) {
            return;
        }

        foreach ($this->parts[$partIndex]['finishings'] as $row) {
            if ((int) $row['finishing_type_id'] === $finishingTypeId) {
                return;
            }
        }

        $this->parts[$partIndex]['finishings'][] = ['id' => null, 'finishing_type_id' => $finishingTypeId, 'attribute' => ''];
    }

    public function clearMaterialRow(int $partIndex, int $matIndex): void
    {
        if ($this->readOnly || ! isset($this->parts[$partIndex]['materials'][$matIndex])) {
            return;
        }

        $id = $this->parts[$partIndex]['materials'][$matIndex]['id'];
        $isPrimary = $this->parts[$partIndex]['materials'][$matIndex]['is_primary'];
        $this->parts[$partIndex]['materials'][$matIndex] = $this->blankMaterialRow($isPrimary);
        $this->parts[$partIndex]['materials'][$matIndex]['id'] = $id;
    }

    // ================= ARTWORK per part (iqp_part.artwork_path) =================
    // Upload gambar desain/artwork customer (permintaan user 2026-09-04) --
    // referensi visual sales & dasar engine saran default di bawah. BISA
    // dipakai SEBELUM part/inquiry pernah disimpan sama sekali (revisi
    // 2026-09-04 ke-2: user keberatan harus Simpan dulu) -- file ditahan
    // sbg temp upload Livewire (preview via temporaryUrl(), analisis warna
    // baca langsung dari file temp), baru dipindah ke storage permanen +
    // ditulis ke iqp_part.artwork_path saat part beneran disimpan
    // (persistPendingArtwork(), dipanggil dari persistParts()).

    #[Computed]
    public function artworkMaxMb(): float
    {
        return SysConstant::number('INQUIRY_ARTWORK_MAX_MB', 10);
    }

    // Livewire lifecycle hook -- otomatis terpanggil begitu file dipilih di
    // wire:model="artworkUploads.{index}" (tidak perlu tombol Upload terpisah).
    public function updatedArtworkUploads($value, $key): void
    {
        $index = (int) $key;

        if ($this->readOnly || ! isset($this->parts[$index]) || ! $value) {
            return;
        }

        $this->validateOnly("artworkUploads.{$key}", [
            "artworkUploads.{$key}" => 'image|mimes:jpg,jpeg,png|max:'.($this->artworkMaxMb * 1024),
        ]);

        $this->parts[$index]['artwork_original_filename'] = $value->getClientOriginalName();

        $this->refreshArtworkSuggestions($index);
    }

    public function removeArtwork(int $index): void
    {
        if ($this->readOnly || ! isset($this->parts[$index])) {
            return;
        }

        unset($this->artworkUploads[$index]);

        $path = $this->parts[$index]['artwork_path'] ?? null;

        if ($path) {
            Storage::disk('public')->delete($path);

            if ($this->parts[$index]['id']) {
                IqpPart::whereKey($this->parts[$index]['id'])->update([
                    'artwork_path' => null,
                    'artwork_original_filename' => null,
                ]);
            }
        }

        $this->parts[$index]['artwork_path'] = null;
        $this->parts[$index]['artwork_original_filename'] = null;

        $this->refreshArtworkSuggestions($index);
    }

    // Dipanggil dari persistParts() sesudah $part punya id_part pasti (part baru
    // maupun lama) -- pindahkan temp upload (kalau ada) ke storage permanen.
    protected function persistPendingArtwork(IqpPart $part, int $index): void
    {
        $file = $this->artworkUploads[$index] ?? null;

        if (! $file) {
            return;
        }

        $oldPath = $part->artwork_path;
        $path = $file->store("inquiry/{$part->id_part}/artwork", 'public');

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        $part->update([
            'artwork_path' => $path,
            'artwork_original_filename' => $file->getClientOriginalName(),
        ]);

        $this->parts[$index]['artwork_path'] = $path;
        unset($this->artworkUploads[$index]);
    }

    // ================= SARAN OTOMATIS: kertas/finishing/tinta (permintaan user 2026-09-04) =================
    // Engine "suggests, officer confirms" (pola sama scheduling/imposition/routing,
    // CLAUDE.md §4) -- BUKAN auto-terapkan. Jenis kertas & finishing TIDAK bisa
    // disimpulkan dari pixel gambar (itu keputusan produksi, bukan konten visual),
    // jadi sumbernya histori Inquiry lain dgn Tipe Produk sama (dieline_spec->type_code).
    // Notasi warna/tinta didekati dari analisis warna artwork -- heuristik kasar
    // (jumlah warna dominan & grayscale-atau-tidak), selalu tampil sbg saran yg
    // wajib dicek ulang officer, bukan deteksi presisi cetak.

    protected function refreshArtworkSuggestions(int $index): void
    {
        $row = $this->parts[$index] ?? null;

        if (! $row) {
            return;
        }

        $typeCode = $row['type_code'] ?? '';
        $pendingFile = $this->artworkUploads[$index] ?? null;

        $colorSourcePath = $pendingFile
            ? $pendingFile->getRealPath()
            : $this->storedArtworkAbsolutePath($row['artwork_path'] ?? null);

        $this->parts[$index]['suggestions'] = [
            'color' => $colorSourcePath ? $this->analyzeImageColors($colorSourcePath) : null,
            'size' => $colorSourcePath ? $this->suggestSizeFromArtwork($colorSourcePath) : null,
            'material' => $typeCode !== '' ? $this->suggestMaterialFromHistory($typeCode) : null,
            'finishing' => $typeCode !== '' ? $this->suggestFinishingFromHistory($typeCode) : null,
        ];
    }

    // Ukuran fisik HANYA disarankan kalau file punya metadata DPI asli (JFIF/pHYs)
    // -- keputusan user 2026-09-04: drpd nebak dari resolusi pixel mentah (bisa
    // meleset >4x antara file 72dpi vs 300dpi), TIDAK tampilkan saran sama sekali
    // kalau metadata-nya tidak ada. Diasumsikan artwork = bentangan/flat (yg
    // dicetak), bukan dimensi luar box jadi -- makanya target-nya Flat W/H, bukan
    // field Panjang/Lebar produk.
    protected function suggestSizeFromArtwork(?string $absolutePath): ?array
    {
        if (! $absolutePath || ! is_file($absolutePath)) {
            return null;
        }

        $info = @getimagesize($absolutePath);

        if (! $info) {
            return null;
        }

        $dpi = match ($info[2]) {
            IMAGETYPE_JPEG => $this->readJpegDpi($absolutePath),
            IMAGETYPE_PNG => $this->readPngDpi($absolutePath),
            default => null,
        };

        if (! $dpi || $dpi['dpi_x'] <= 0 || $dpi['dpi_y'] <= 0) {
            return null;
        }

        return [
            'width_mm' => round(($info[0] / $dpi['dpi_x']) * 25.4, 1),
            'height_mm' => round(($info[1] / $dpi['dpi_y']) * 25.4, 1),
            'dpi_x' => (int) round($dpi['dpi_x']),
            'dpi_y' => (int) round($dpi['dpi_y']),
        ];
    }

    // JFIF APP0 segment (bukan EXIF -- kebanyakan export Illustrator/Photoshop
    // simpan density di sini): units 1=dots/inch, 2=dots/cm.
    protected function readJpegDpi(string $path): ?array
    {
        $fh = @fopen($path, 'rb');

        if (! $fh) {
            return null;
        }

        try {
            if (fread($fh, 2) !== "\xFF\xD8") {
                return null;
            }

            while (! feof($fh)) {
                $marker = fread($fh, 2);

                if (strlen($marker) < 2 || $marker[0] !== "\xFF" || $marker === "\xFF\xDA") {
                    break;
                }

                $lenRaw = fread($fh, 2);

                if (strlen($lenRaw) < 2) {
                    break;
                }

                $segLen = unpack('n', $lenRaw)[1];

                if ($marker === "\xFF\xE0" && $segLen >= 14) {
                    $payload = fread($fh, $segLen - 2);

                    if (substr($payload, 0, 5) === "JFIF\0") {
                        $units = ord($payload[7]);
                        $x = unpack('n', substr($payload, 8, 2))[1];
                        $y = unpack('n', substr($payload, 10, 2))[1];

                        return match ($units) {
                            1 => ['dpi_x' => $x, 'dpi_y' => $y],
                            2 => ['dpi_x' => $x * 2.54, 'dpi_y' => $y * 2.54],
                            default => null,
                        };
                    }

                    continue;
                }

                fseek($fh, $segLen - 2, SEEK_CUR);
            }

            return null;
        } finally {
            fclose($fh);
        }
    }

    // Chunk pHYs (pixels-per-unit) -- unit specifier 1=meter, selain itu "unknown"
    // (rasio doang, bukan skala dunia nyata) jadi diabaikan.
    protected function readPngDpi(string $path): ?array
    {
        $fh = @fopen($path, 'rb');

        if (! $fh) {
            return null;
        }

        try {
            if (fread($fh, 8) !== "\x89PNG\r\n\x1a\n") {
                return null;
            }

            while (! feof($fh)) {
                $lenRaw = fread($fh, 4);

                if (strlen($lenRaw) < 4) {
                    break;
                }

                $len = unpack('N', $lenRaw)[1];
                $type = fread($fh, 4);

                if ($type === 'pHYs' && $len === 9) {
                    $chunk = fread($fh, 9);
                    $ppuX = unpack('N', substr($chunk, 0, 4))[1];
                    $ppuY = unpack('N', substr($chunk, 4, 4))[1];
                    $unit = ord($chunk[8]);

                    if ($unit === 1 && $ppuX > 0 && $ppuY > 0) {
                        return ['dpi_x' => $ppuX * 0.0254, 'dpi_y' => $ppuY * 0.0254];
                    }

                    return null;
                }

                if ($type === 'IDAT') {
                    break;
                }

                fseek($fh, $len + 4, SEEK_CUR);
            }

            return null;
        } finally {
            fclose($fh);
        }
    }

    protected function storedArtworkAbsolutePath(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->path($path);
    }

    protected function analyzeImageColors(?string $full): ?array
    {
        if (! $full || ! is_file($full)) {
            return null;
        }

        $info = @getimagesize($full);

        if (! $info) {
            return null;
        }

        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($full),
            IMAGETYPE_PNG => @imagecreatefrompng($full),
            default => null,
        };

        if (! $img) {
            return null;
        }

        // Downsample ke grid kecil -- cukup utk gambaran warna, jauh lebih cepat
        // & noise kompresi JPEG teredam dibanding scan pixel penuh.
        $sw = 40;
        $sh = 40;
        $sample = imagecreatetruecolor($sw, $sh);
        imagecopyresampled($sample, $img, 0, 0, 0, 0, $sw, $sh, imagesx($img), imagesy($img));
        imagedestroy($img);

        $colors = [];
        $isGrayscale = true;

        for ($y = 0; $y < $sh; $y++) {
            for ($x = 0; $x < $sw; $x++) {
                $rgb = imagecolorat($sample, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                if (abs($r - $g) > 12 || abs($g - $b) > 12 || abs($r - $b) > 12) {
                    $isGrayscale = false;
                }

                // Kuantisasi tiap channel ke kelipatan 32 -- variasi gradasi/kompresi
                // kecil tidak dihitung sbg warna baru terus-terusan.
                $colors[intdiv($r, 32).'-'.intdiv($g, 32).'-'.intdiv($b, 32)] = true;
            }
        }

        imagedestroy($sample);

        $distinct = count($colors);

        $code = match (true) {
            $isGrayscale && $distinct <= 3 => '1/0',
            ! $isGrayscale && $distinct <= 4 => '2/0',
            ! $isGrayscale && $distinct <= 10 => '4/0',
            default => '4/4',
        };

        return [
            'code' => $code,
            'distinct_colors' => $distinct,
            'is_grayscale' => $isGrayscale,
        ];
    }

    protected function suggestMaterialFromHistory(string $typeCode): ?array
    {
        $top = IqpPartMaterial::query()
            ->join('iqp_part', 'iqp_part.id_part', '=', 'iqp_part_material.part_id')
            ->where('iqp_part.dieline_spec->type_code', $typeCode)
            ->whereNotNull('iqp_part_material.category_id')
            ->selectRaw('iqp_part_material.category_id, iqp_part_material.rawmat_name, count(*) as cnt')
            ->groupBy('iqp_part_material.category_id', 'iqp_part_material.rawmat_name')
            ->orderByDesc('cnt')
            ->first();

        if (! $top) {
            return null;
        }

        $total = IqpPartMaterial::query()
            ->join('iqp_part', 'iqp_part.id_part', '=', 'iqp_part_material.part_id')
            ->where('iqp_part.dieline_spec->type_code', $typeCode)
            ->whereNotNull('iqp_part_material.category_id')
            ->count();

        return [
            'category_id' => $top->category_id,
            'rawmat_name' => $top->rawmat_name,
            'pct' => $total > 0 ? (int) round($top->cnt / $total * 100) : 0,
        ];
    }

    protected function suggestFinishingFromHistory(string $typeCode): ?array
    {
        $top = IqpPartFinishing::query()
            ->join('iqp_part', 'iqp_part.id_part', '=', 'iqp_part_finishing.part_id')
            ->where('iqp_part.dieline_spec->type_code', $typeCode)
            ->selectRaw('iqp_part_finishing.finishing_type_id, iqp_part_finishing.finishing_name, count(*) as cnt')
            ->groupBy('iqp_part_finishing.finishing_type_id', 'iqp_part_finishing.finishing_name')
            ->orderByDesc('cnt')
            ->first();

        if (! $top) {
            return null;
        }

        $total = IqpPartFinishing::query()
            ->join('iqp_part', 'iqp_part.id_part', '=', 'iqp_part_finishing.part_id')
            ->where('iqp_part.dieline_spec->type_code', $typeCode)
            ->count();

        return [
            'finishing_type_id' => (int) $top->finishing_type_id,
            'process_name' => $top->finishing_name,
            'pct' => $total > 0 ? (int) round($top->cnt / $total * 100) : 0,
        ];
    }

    public function applySuggestedColor(int $index, string $code): void
    {
        if ($this->readOnly || ! isset($this->parts[$index])) {
            return;
        }

        $this->parts[$index]['cfg_color'] = $code;
    }

    public function applySuggestedMaterial(int $index, int $categoryId): void
    {
        if ($this->readOnly || ! isset($this->parts[$index])) {
            return;
        }

        $chain = MatCategory::find($categoryId)?->ancestorChain();

        if (! $chain) {
            return;
        }

        $slot = ($chain[0]->category_name ?? null) === 'Sheet' ? 1 : 0;

        $this->parts[$index]['materials'][$slot]['jenis_id'] = $chain[1]->id_category ?? null;
        $this->parts[$index]['materials'][$slot]['gramatur_id'] = $chain[2]->id_category ?? null;
        $this->resolveMaterialRow($index, $slot);
    }

    // ================= TOOLING (iqp_tooling, Tier1 F6) =================
    // tooling_asset_id (reuse m_tooling_asset) tak diisi UI — master belum digarap
    // Tier 3A (lihat CLAUDE.md §5 GAP #2); baris tooling diinput manual dulu.

    protected function blankToolingRow(): array
    {
        return [
            'id' => null,
            'tooling_kind' => 'PLATE',
            'tooling_status' => 'ADA',
            'is_new_charge' => false,
            'cost' => '0',
            'remark' => '',
        ];
    }

    public function addTooling(): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->toolings[] = $this->blankToolingRow();
    }

    public function removeTooling(int $index): void
    {
        if ($this->readOnly || ! isset($this->toolings[$index])) {
            return;
        }

        unset($this->toolings[$index]);
        $this->toolings = array_values($this->toolings);
    }

    public function setToolingNewCharge(int $index, bool $value): void
    {
        if ($this->readOnly || ! isset($this->toolings[$index])) {
            return;
        }

        $this->toolings[$index]['is_new_charge'] = $value;

        if (! $value) {
            $this->toolings[$index]['cost'] = '0';
        }
    }

    // ================= BIAYA EKSTERNAL (iqp_external_cost, Tier1 F13) =================

    protected function blankExternalCostRow(): array
    {
        return [
            'id' => null,
            'cost_kind' => 'TRUCKING',
            'description' => '',
            'amount' => '0',
            'tax_treatment' => 'TAXABLE',
        ];
    }

    public function addExternalCost(): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->externalCosts[] = $this->blankExternalCostRow();
    }

    public function removeExternalCost(int $index): void
    {
        if ($this->readOnly || ! isset($this->externalCosts[$index])) {
            return;
        }

        unset($this->externalCosts[$index]);
        $this->externalCosts = array_values($this->externalCosts);
    }

    // ================= SIMPAN / STATE (Tier2 §11 Form Standard & State) =================

    protected function headerRules(): array
    {
        $rules = [
            'inquiry_date' => ['required', 'date'],
            'source' => ['required', 'in:EMAIL,WA,PHONE,VISIT,MARKETPLACE'],
            'pic_sales' => ['nullable', 'string', 'max:100'],
            'deadline_quotation' => ['nullable', 'date', 'after_or_equal:inquiry_date'],
            'product_draft_name' => ['required', 'string', 'max:150', 'regex:~^[^/@#%"\']+$~'],
            'sales_note' => ['nullable', 'string'],
            'material_source' => ['required', 'in:OWN,CUSTOMER'],
            'tax_scheme' => ['required', 'in:PPN,NON_PPN'],
            'cust_material_spec' => ['nullable', 'string', 'required_if:material_source,CUSTOMER'],
            'cust_material_eta' => ['nullable', 'date'],
            'discount_doc_type' => ['nullable', 'in:PERCENT,AMOUNT'],
            'discount_doc_value' => ['nullable', 'numeric', 'min:0', 'required_if:discount_doc_type,PERCENT,AMOUNT'],
            'discount_qty_value' => ['nullable', 'numeric', 'min:0'],
            'discount_qty_min_ton' => ['nullable', 'numeric', 'min:0'],
        ];

        // CLAUDE.md §2: FK lintas-DB divalidasi di aplikasi (exists:conn.table,col), bukan constraint DB.
        $rules[$this->is_prospect ? 'prospect_name' : 'customer_id'] = $this->is_prospect
            ? ['required', 'string', 'max:150']
            : ['required', 'integer', 'exists:tm2mst.cust_customer,id_customer'];

        return $rules;
    }

    protected function assertTierInvariant(): void
    {
        if (count($this->tiers) === 0) {
            throw ValidationException::withMessages(['tiers' => 'Minimal 1 baris tier qty diperlukan.']);
        }

        $primaryCount = collect($this->tiers)->where('is_primary', true)->count();

        if ($primaryCount !== 1) {
            throw ValidationException::withMessages([
                'tiers' => 'Harus tepat 1 tier UTAMA (saat ini: '.$primaryCount.').',
            ]);
        }

        foreach ($this->tiers as $i => $tier) {
            if ($tier['qty'] === '' || ! is_numeric($tier['qty']) || (float) $tier['qty'] <= 0) {
                throw ValidationException::withMessages([
                    'tiers' => 'Qty tier baris '.($i + 1).' wajib diisi angka lebih besar dari 0.',
                ]);
            }
        }
    }

    protected function headerAttributes(): array
    {
        $isCustomerMaterial = $this->material_source === 'CUSTOMER';

        return [
            'inquiry_date' => $this->inquiry_date,
            'source' => $this->source,
            'pic_sales' => $this->pic_sales ?: null,
            'customer_id' => $this->is_prospect ? null : $this->customer_id,
            'is_prospect' => $this->is_prospect,
            'prospect_name' => $this->is_prospect ? $this->prospect_name : null,
            'deadline_quotation' => $this->deadline_quotation ?: null,
            'product_draft_name' => $this->product_draft_name,
            'sales_note' => $this->sales_note ?: null,
            'material_source' => $this->material_source,
            'job_nature' => $this->jobNature(),
            'tax_scheme' => $this->tax_scheme,
            'pph_applicable' => $this->pph_applicable,
            'cust_material_spec' => $isCustomerMaterial ? ($this->cust_material_spec ?: null) : null,
            'cust_material_eta' => $isCustomerMaterial ? ($this->cust_material_eta ?: null) : null,
            'discount_doc_type' => $this->discount_doc_type ?: null,
            'discount_doc_value' => $this->discount_doc_type && $this->discount_doc_value !== '' ? $this->discount_doc_value : null,
            'discount_qty_value' => $this->discount_qty_value !== '' ? $this->discount_qty_value : null,
            'discount_qty_min_ton' => $this->discount_qty_min_ton !== '' ? $this->discount_qty_min_ton : null,
            'updated_by' => Auth::user()->login,
        ];
    }

    protected function persistTiers(): void
    {
        $keepIds = [];

        foreach ($this->tiers as $index => $row) {
            $data = [
                'tier_seq' => $index + 1,
                'qty' => $row['qty'],
                'is_primary' => $row['is_primary'],
                'discount_item_type' => $row['discount_item_type'] ?: null,
                'discount_item_value' => $row['discount_item_value'] !== '' ? $row['discount_item_value'] : null,
                'updated_by' => Auth::user()->login,
            ];

            if ($row['id']) {
                IqpTier::whereKey($row['id'])->update($data);
                $keepIds[] = $row['id'];
            } else {
                $data['created_by'] = Auth::user()->login;
                $tier = $this->inquiry->tiers()->create($data);
                $this->tiers[$index]['id'] = $tier->id_tier;
                $keepIds[] = $tier->id_tier;
            }
        }

        $this->inquiry->tiers()->whereNotIn('id_tier', $keepIds)->delete();
    }

    protected function persistParts(): void
    {
        $keepIds = [];

        foreach ($this->parts as $index => $row) {
            // Snapshot dieline (Tier1 §5.3): DSL asli prod_type.dieline_spec + dims yang
            // dipakai user, dibekukan ke iqp_part.dieline_spec — bukan re-join ke prod_type.
            $dielineSpec = null;

            if (! empty($row['type_code'])) {
                $type = ProdType::find($row['type_code']);

                if ($type && $type->dieline_spec) {
                    $dielineSpec = $type->dieline_spec;
                    $dielineSpec['dims'] = $row['dims'] ?? [];
                    $dielineSpec['type_code'] = $row['type_code'];
                }
            }

            $data = [
                'part_seq' => $index + 1,
                'part_name' => $row['part_name'] !== '' ? $row['part_name'] : 'Part '.($index + 1),
                'part_type' => $row['part_type'] ?: null,
                'qty_per_sku' => $row['qty_per_sku'] !== '' ? $row['qty_per_sku'] : 1,
                'pack_qty' => ($row['pack_qty'] ?? '') !== '' ? (int) $row['pack_qty'] : null,
                'cfg_color' => $row['cfg_color'] !== '' ? $row['cfg_color'] : null,
                'cfg_ink' => $row['cfg_ink'] !== '' ? $row['cfg_ink'] : null,
                'dieline_spec' => $dielineSpec,
                'flat_w' => $row['flat_w'] !== '' ? $row['flat_w'] : null,
                'flat_h' => $row['flat_h'] !== '' ? $row['flat_h'] : null,
                'flat_area' => $row['flat_area'] !== '' ? $row['flat_area'] : null,
                'updated_by' => Auth::user()->login,
            ];

            if ($row['id']) {
                $part = IqpPart::findOrFail($row['id']);
                $part->update($data);
                $keepIds[] = $row['id'];
            } else {
                $data['created_by'] = Auth::user()->login;
                $part = $this->inquiry->parts()->create($data);
                $this->parts[$index]['id'] = $part->id_part;
                $keepIds[] = $part->id_part;
            }

            $this->persistPendingArtwork($part, $index);
            $this->persistPartMaterials($part, $index);
            $this->persistPartFinishings($part, $index);
        }

        $this->inquiry->parts()->whereNotIn('id_part', $keepIds)->delete();
    }

    // Finishing disimpan TERSTRUKTUR ke iqp_part_finishing (BACKLOG §18.2).
    // iqp_part_process TIDAK disentuh lagi dari layar ini -- tabel itu milik
    // hasil engine BOP (routing), yang dihitung dari finishing ini.
    protected function persistPartFinishings(IqpPart $part, int $partIndex): void
    {
        $part->finishings()->delete();
        $names = $this->finishingTypeOptions->pluck('finishing_name', 'id_finishing_type');

        foreach (array_values(array_filter(
            $this->parts[$partIndex]['finishings'] ?? [],
            fn ($r) => ! empty($r['finishing_type_id']) && isset($names[$r['finishing_type_id']])
        )) as $seq => $row) {
            $part->finishings()->create([
                'seq' => ($seq + 1) * 10,
                'finishing_type_id' => (int) $row['finishing_type_id'],
                'finishing_name' => $names[$row['finishing_type_id']],
                'attribute' => trim($row['attribute'] ?? '') ?: null,
                'created_by' => Auth::user()->login,
                'updated_by' => Auth::user()->login,
            ]);
        }
    }

    protected function persistToolings(): void
    {
        $keepIds = [];

        foreach ($this->toolings as $index => $row) {
            $data = [
                'tooling_kind' => $row['tooling_kind'],
                'tooling_status' => $row['tooling_status'],
                'is_new_charge' => $row['is_new_charge'],
                'cost' => $row['is_new_charge'] && $row['cost'] !== '' ? $row['cost'] : 0,
                'remark' => $row['remark'] !== '' ? $row['remark'] : null,
                'updated_by' => Auth::user()->login,
            ];

            if ($row['id']) {
                IqpTooling::whereKey($row['id'])->update($data);
                $keepIds[] = $row['id'];
            } else {
                $data['created_by'] = Auth::user()->login;
                $tooling = $this->inquiry->toolings()->create($data);
                $this->toolings[$index]['id'] = $tooling->id_tooling;
                $keepIds[] = $tooling->id_tooling;
            }
        }

        $this->inquiry->toolings()->whereNotIn('id_tooling', $keepIds)->delete();
    }

    protected function persistExternalCosts(): void
    {
        $keepIds = [];

        foreach ($this->externalCosts as $index => $row) {
            $data = [
                'cost_kind' => $row['cost_kind'],
                'description' => $row['description'] !== '' ? $row['description'] : null,
                'amount' => $row['amount'] !== '' ? $row['amount'] : 0,
                'tax_treatment' => $row['tax_treatment'],
                'updated_by' => Auth::user()->login,
            ];

            if ($row['id']) {
                IqpExternalCost::whereKey($row['id'])->update($data);
                $keepIds[] = $row['id'];
            } else {
                $data['created_by'] = Auth::user()->login;
                $cost = $this->inquiry->externalCosts()->create($data);
                $this->externalCosts[$index]['id'] = $cost->id_external_cost;
                $keepIds[] = $cost->id_external_cost;
            }
        }

        $this->inquiry->externalCosts()->whereNotIn('id_external_cost', $keepIds)->delete();
    }

    protected function persistPartMaterials(IqpPart $part, int $partIndex): void
    {
        $keepIds = [];

        foreach ($this->parts[$partIndex]['materials'] as $matIndex => $row) {
            if (! $row['category_id'] && ! $row['rawmat_id'] && $row['display_name'] === '') {
                continue;
            }

            $data = [
                'layer_seq' => $matIndex + 1,
                'rawmat_id' => $row['rawmat_id'] ?: null,
                'category_id' => $row['category_id'] ?: null,
                'rawmat_name' => $row['display_name'] !== '' ? $row['display_name'] : null,
                'gram_weight' => $row['gram_weight'] ?: null,
                'flute_type' => $row['flute_type'] ?: null,
                'size_p' => $row['size_p'] ?: null,
                'size_l' => $row['size_l'] ?: null,
                'is_primary' => $row['is_primary'],
                // B1 (Tier1 §6.2): granularitas per-layer belum diputuskan, ikut flag header dulu.
                'material_source' => $this->material_source,
                'updated_by' => Auth::user()->login,
            ];

            if ($row['id']) {
                IqpPartMaterial::whereKey($row['id'])->update($data);
                $keepIds[] = $row['id'];
            } else {
                $data['created_by'] = Auth::user()->login;
                $material = $part->materials()->create($data);
                $this->parts[$partIndex]['materials'][$matIndex]['id'] = $material->id_part_material;
                $keepIds[] = $material->id_part_material;
            }
        }

        $part->materials()->whereNotIn('id_part_material', $keepIds)->delete();
    }

    protected function persist(): void
    {
        $this->validate($this->headerRules());
        $this->assertTierInvariant();

        DB::connection('tm2sdi')->transaction(function () {
            $attributes = $this->headerAttributes();

            if ($this->inquiry) {
                $this->inquiry->update($attributes);
            } else {
                $attributes['inquiry_type'] = $this->mode;
                $attributes['created_by'] = Auth::user()->login;

                // Penomoran LOCKED per Tier1 §2.4: IQ + YY + NNNNN, counter reset tiap tahun.
                $prefix = 'IQ'.now()->format('y');

                $last = IqpInquiry::where('inquiry_no', 'like', "{$prefix}%")
                    ->lockForUpdate()
                    ->orderByDesc('inquiry_no')
                    ->value('inquiry_no');

                $seq = $last ? ((int) substr($last, 4)) + 1 : 1;
                $attributes['inquiry_no'] = $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);

                // refresh() wajib: kolom ber-DEFAULT DB (sub_state, revision_no, dst) belum
                // terisi di instance in-memory setelah create() tanpa query ulang.
                $this->inquiry = IqpInquiry::create($attributes)->refresh();

                $this->id = $this->inquiry->id_inquiry;
                $this->inquiry_no = $this->inquiry->inquiry_no;
            }

            $this->persistTiers();
            $this->persistParts();
            $this->persistToolings();
            $this->persistExternalCosts();
        });

        $this->sub_state = $this->inquiry->sub_state;
    }

    public function save(): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->persist();

        $this->flash = 'Tersimpan sebagai '.$this->inquiry_no.' ('.$this->sub_state.').';
    }

    public function confirm(): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->persist();

        $this->inquiry->update([
            'stat_confirm_sales' => true,
            'sub_state' => 'SUBMITTED',
            'updated_by' => Auth::user()->login,
        ]);

        $this->stat_confirm = true;
        $this->readOnly = true;
        $this->sub_state = 'SUBMITTED';
        $this->flash = 'Inkuiri '.$this->inquiry_no.' dikonfirmasi — status SUBMITTED, form terkunci.';
    }

    // Mode #1 -- link Inquiry(BARU) -> ProDev yg SEBELUMNYA TIDAK ADA SAMA
    // SEKALI (CLAUDE.md §4 "VITAL", gap ditemukan 2026-09-03). Seed prod_product
    // (draft, kalau iqp_inquiry belum punya product_id -- IqpInquiry::ensureDraftProduct()) + prod_part dari
    // dims kasar iqp_part.dieline_spec['dims'] (product_length/width/height,
    // mm->cm), lalu bawa officer langsung ke ProDev part itu. ProDev TETAP
    // yang isi detail teknis lengkap (die_w_cm, flap, dst) -- di sini cuma
    // titik masuk, bukan auto-lengkapi semuanya.
    public function sendToProDev(int $index): void
    {
        $row = $this->parts[$index] ?? null;

        if (! $row || ! $row['id']) {
            $this->flash = 'Simpan inquiry dulu (tombol Simpan) sebelum kirim part ke ProDev.';

            return;
        }

        if (! $this->stat_confirm) {
            $this->flash = 'Confirm inquiry ini dulu sebelum kirim ke ProDev (spec harus FIX).';

            return;
        }

        $part = IqpPart::findOrFail($row['id']);

        $this->inquiry->ensureDraftProduct(Auth::user()->login);

        if (! $part->prod_part_id) {
            $dims = $part->dieline_spec['dims'] ?? [];

            $prodPart = ProdPart::create([
                'product_id' => $this->inquiry->product_id,
                'part_code' => 'P'.$part->id_part,
                'part_name' => $part->part_name,
                'cm_length' => isset($dims['product_length']) ? round(((float) $dims['product_length']) / 10, 2) : null,
                'cm_width' => isset($dims['product_width']) ? round(((float) $dims['product_width']) / 10, 2) : null,
                'cm_height' => isset($dims['product_height']) ? round(((float) $dims['product_height']) / 10, 2) : null,
                'activate' => true,
                'created_by' => Auth::user()->login,
                'updated_by' => Auth::user()->login,
            ]);
            $part->update(['prod_part_id' => $prodPart->id_part]);
        }

        // Spesifikasi yang jadi input engine BOP/BOM/Ink ikut ke master part
        // setiap kali dikirim, supaya ProDev tidak bekerja dari data basi.
        ProdPart::whereKey($part->prod_part_id)->update(array_filter([
            'pack_qty' => $part->pack_qty,
            'cfg_color' => $part->cfg_color,
            'updated_by' => Auth::user()->login,
        ], fn ($v) => $v !== null));

        ProdPartFinishing::where('part_id', $part->prod_part_id)->delete();
        foreach ($part->finishings as $fin) {
            ProdPartFinishing::create([
                'part_id' => $part->prod_part_id,
                'seq' => $fin->seq,
                'finishing_type_id' => $fin->finishing_type_id,
                'attribute' => $fin->attribute,
                'notes' => $fin->notes,
                'created_by' => Auth::user()->login,
                'updated_by' => Auth::user()->login,
            ]);
        }

        // Artwork yg diupload di Inquiry ikut jadi Referensi ProDev otomatis --
        // 1 file yg sama (disk 'public'), bukan disalin fisik. Permintaan user
        // 2026-09-04: "sangat membantu ProDev dan ProCost".
        if ($part->artwork_path
            && ! ProdPartAttachment::where('part_id', $part->prod_part_id)->where('file_path', $part->artwork_path)->exists()) {
            ProdPartAttachment::create([
                'part_id' => $part->prod_part_id,
                'file_type' => 'JPEG',
                'file_path' => $part->artwork_path,
                'original_filename' => $part->artwork_original_filename,
                'uploaded_at' => now(),
                'uploaded_by' => Auth::user()->login,
            ]);
        }

        $this->redirect(route('production.prodev', ['part' => $part->prod_part_id]));
    }

    public function openCancelForm(): void
    {
        $this->showCancelForm = true;
    }

    public function dismissCancelForm(): void
    {
        $this->showCancelForm = false;
        $this->cancel_reason = '';
    }

    public function cancel(): void
    {
        if (! $this->stat_confirm || $this->stat_cancel) {
            return;
        }

        $this->validate(['cancel_reason' => ['required', 'string', 'max:255']]);

        $this->inquiry->update([
            'stat_cancel' => true,
            'cancel_reason' => $this->cancel_reason,
            'sub_state' => 'CANCELLED',
            'updated_by' => Auth::user()->login,
        ]);

        $this->stat_cancel = true;
        $this->sub_state = 'CANCELLED';
        $this->showCancelForm = false;
        $this->flash = 'Inkuiri '.$this->inquiry_no.' dibatalkan.';
    }

    public function openLostForm(): void
    {
        $this->showLostForm = true;
    }

    public function dismissLostForm(): void
    {
        $this->showLostForm = false;
        $this->lost_reason = '';
    }

    // sub_state='LOST' (win-rate Sales) -- beda dari cancel(): boleh dari sub_state
    // manapun selama sudah stat_confirm & belum terminal (CANCELLED/CONVERTED/LOST),
    // krn "kalah" bisa terjadi kapan saja sepanjang pipeline (quoted, proving, dst).
    public function markLost(): void
    {
        if (! $this->stat_confirm || in_array($this->sub_state, ['CANCELLED', 'CONVERTED', 'LOST'], true)) {
            return;
        }

        $this->validate(['lost_reason' => ['required', 'string', 'max:255']]);

        $this->inquiry->update([
            'cancel_reason' => $this->lost_reason,
            'sub_state' => 'LOST',
            'updated_by' => Auth::user()->login,
        ]);

        $this->sub_state = 'LOST';
        $this->showLostForm = false;
        $this->flash = 'Inkuiri '.$this->inquiry_no.' ditandai kalah (LOST).';
    }
};
?>

<div>
    @if ($this->crossView)
        {{-- User 2026-09-04, koreksi eksplisit: "JANGAN pakai layar inquiry
        product yang original karena itu untuk inputan... boleh improve dari
        attachment #1" (mockup_FORM_prodev_!.png, Esko-style Step 1). Markup
        BESAR dipindah ke component terpisah <x-inquiry.readonly-summary>
        (bukan inline di sini) -- ditemukan bug nyata: cabang if/else besar
        di file 1900+ baris ini sempat merender KEDUA cabang sekaligus,
        pindah ke component kecil terbukti menghilangkan gejalanya. --}}
        <x-prodev.wizard-bar current="inquiry" :steps="$this->wizardSteps" :back-url="route('inquiry.hub')" />
        @php $__theme = $this->theme; @endphp
        <div class="pd-wb min-h-screen bg-[var(--pd-bg)] text-[var(--pd-ink)]" style="
            --pd-bg: {{ $__theme->mode === 'dark' ? ($__theme->content_bg_color ?: '#12162a') : ($__theme->content_bg_color ?: '#eef1f6') }};
            --pd-surface: {{ $__theme->mode === 'dark' ? '#1b2140' : '#ffffff' }};
            --pd-surface-alt: {{ $__theme->mode === 'dark' ? '#20264a' : '#f4f6fa' }};
            --pd-border: {{ $__theme->mode === 'dark' ? '#2a3157' : '#d7dbe2' }};
            --pd-ink: {{ $__theme->mode === 'dark' ? '#c7cee8' : '#3a4256' }};
            --pd-ink-strong: {{ $__theme->mode === 'dark' ? '#eef1ff' : '#1c2440' }};
            --pd-muted: {{ $__theme->mode === 'dark' ? '#8089ac' : '#8a92ad' }};
        ">
            <x-inquiry.readonly-summary :inquiry="$this->inquiry" :parts="$this->partSummaries" />
        </div>
    @endif
    @if (! $this->crossView)
    <header class="app-header">
        <b>{{ $mode === 'NEW' ? '✨ '.Translator::t('sales_form_title_new', 'Inkuiri Produk Baru') : ($mode === 'RECALC' ? '🔁 '.Translator::t('sales_form_title_recalc', 'Hitung Ulang Harga') : '✏️ '.Translator::t('sales_form_title_revise', 'Ubah Spesifikasi')) }}</b>
        <span class="pill {{ strtolower($mode) }}">{{ $mode }}</span>
        <span class="sub">{{ $inquiry_no ?: Translator::t('sales_form_not_saved_yet', 'belum tersimpan') }} · {{ $sub_state }}</span>
        <a class="back" href="{{ route('inquiry.hub') }}">← {{ Translator::t('sales_form_back_to_menu', 'Menu Utama') }}</a>
    </header>

    <div class="wrap">

        @if ($flash)
            <div class="note g">✅ {{ $flash }}</div>
        @endif

        @if ($readOnly)
            <div class="note o">🔒 {{ Translator::t('sales_form_readonly_note_prefix', 'Inkuiri ini sudah dikonfirmasi (status') }} <b>{{ $sub_state }}</b>{{ Translator::t('sales_form_readonly_note_suffix', ') — dibuka lihat-saja, tidak bisa diedit.') }}</div>
        @else
            <div class="note y">
                👤 <b>{{ Translator::t('sales_form_sales_officer_label', 'Sales Officer') }}</b> — {!! Translator::t('sales_form_officer_hint', 'isi header, tier qty, part &amp; dieline, tooling, diskon, dan biaya eksternal di bawah. Costing (HPP) &amp; estimasi kecepatan akan menyusul di iterasi berikutnya.') !!}
            </div>
        @endif

        <div class="card">
            <div class="toolbar">
                <button type="button" class="btn p" wire:click="save" @if($readOnly) disabled @endif wire:loading.attr="disabled">
                    💾 {{ Translator::t('shell_btn_save', 'Simpan') }}
                </button>
                @unless($stat_confirm)
                    <button type="button" class="btn s" wire:click="confirm" wire:loading.attr="disabled">
                        ✅ {{ Translator::t('sales_form_btn_confirm', 'Confirm') }}
                    </button>
                @endunless
                @if($stat_confirm && ! $stat_cancel)
                    <button type="button" class="btn d" wire:click="openCancelForm">
                        ✖ {{ Translator::t('sales_form_btn_cancel_doc', 'Batalkan') }}
                    </button>
                @endif
                @if($stat_confirm && ! in_array($sub_state, ['CANCELLED', 'CONVERTED', 'LOST'], true))
                    <button type="button" class="btn d" wire:click="openLostForm">
                        ❌ {{ Translator::t('sales_form_btn_mark_lost', 'Tandai Kalah') }}
                    </button>
                @endif
                <div style="margin-left:auto"></div>
                @if ($id)
                    <a href="{{ route('inquiry.imposition', ['inquiry' => $id]) }}" class="btn o xs" style="text-decoration:none">🔀 {{ Translator::t('sales_form_link_imposition', 'Imposition') }}</a>
                    <a href="{{ route('inquiry.nota', ['inquiry' => $id]) }}" class="btn o xs" style="text-decoration:none">🖨 {{ Translator::t('sales_form_link_print_nota', 'Cetak Nota') }}</a>
                @endif
                <span class="pill {{ $this->statusPillClass() }}">{{ $sub_state }}</span>
            </div>

            @if ($showCancelForm)
                <div class="body" style="background:#fff4e5;border-bottom:1px solid #ffd9a0">
                    <div style="margin-bottom:8px">
                        <x-form.textarea :legacy="true" name="cancel_reason"
                            :label="Translator::t('sales_form_cancel_reason_label', 'Alasan Pembatalan *')"
                            wire:model="cancel_reason" rows="2"
                            :placeholder="Translator::t('sales_form_cancel_reason_placeholder', 'Wajib diisi — tercatat di riwayat')" />
                    </div>
                    <div style="display:flex;gap:8px">
                        <button type="button" class="btn d xs" wire:click="cancel">{{ Translator::t('sales_form_confirm_cancel_doc', 'Ya, batalkan inkuiri') }}</button>
                        <button type="button" class="btn g xs" wire:click="dismissCancelForm">{{ Translator::t('shared_btn_cancel', 'Batal') }}</button>
                    </div>
                </div>
            @endif

            @if ($showLostForm)
                <div class="body" style="background:#fff4e5;border-bottom:1px solid #ffd9a0">
                    <div style="margin-bottom:8px">
                        <x-form.textarea :legacy="true" name="lost_reason"
                            :label="Translator::t('sales_form_lost_reason_label', 'Alasan Kalah *')"
                            wire:model="lost_reason" rows="2"
                            :placeholder="Translator::t('sales_form_lost_reason_placeholder', 'mis. Harga kompetitor lebih rendah, customer batal order — wajib diisi, tercatat di riwayat & laporan win-rate')" />
                    </div>
                    <div style="display:flex;gap:8px">
                        <button type="button" class="btn d xs" wire:click="markLost">{{ Translator::t('sales_form_confirm_mark_lost', 'Ya, tandai kalah') }}</button>
                        <button type="button" class="btn g xs" wire:click="dismissLostForm">{{ Translator::t('shared_btn_cancel', 'Batal') }}</button>
                    </div>
                </div>
            @endif

            <div class="body">
                <div class="sec-title">{{ Translator::t('sales_form_section_header', 'Header Inkuiri') }}</div>

                {{-- Baris 1 (permintaan user 2026-09-04): No.Inkuiri, Tgl Dokumen, Tgl
                Kebutuhan Pelanggan, Sales, Sumber -- 5 kolom (.grid.five, sudah ada di
                app.css, dipakai persis utk kasus 5-field-1-baris spt ini). --}}
                <div class="grid five">
                    <div class="f">
                        <label>No. Inkuiri</label>
                        <div style="padding:6px 0;font-size:12.5px;font-weight:700;color:{{ $inquiry_no ? '#1c2440' : '#9aa3b5' }}">
                            {{ $inquiry_no ?: 'Otomatis saat disimpan' }}
                        </div>
                    </div>
                    <x-form.date-picker legacy label="Tanggal" wire:model="inquiry_date" :disabled="$readOnly" name="inquiry_date" />
                    <x-form.date-picker legacy wire:model="deadline_quotation" :disabled="$readOnly" name="deadline_quotation">
                        <x-slot:label>Tgl Kebutuhan Pelanggan</x-slot:label>
                    </x-form.date-picker>
                    <div class="f">
                        <label>PIC Sales</label>
                        <div style="display:flex;gap:6px;align-items:center">
                            <input type="text" value="{{ $pic_sales }}" readonly style="flex:1">
                            @unless($readOnly)
                                <button type="button" class="btn g xs" wire:click="openSalesPicker" title="Ubah PIC Sales">✎</button>
                            @endunless
                        </div>
                    </div>
                    <x-form.select legacy label="Sumber" wire:model="source" x-data x-effect="$el.value = @js($source)" :disabled="$readOnly" :placeholder="false"
                        :selected="$source" :options="['EMAIL' => 'EMAIL', 'WA' => 'WA', 'PHONE' => 'PHONE', 'VISIT' => 'VISIT', 'MARKETPLACE' => 'MARKETPLACE']" />
                </div>

                {{-- Baris 2: Customer + Nama Produk (draft) + Kategori Produk, sejajar. --}}
                <div class="grid three" style="margin-top:10px">
                    {{-- Prospek? dihapus (permintaan user 2026-09-04: "sudah diwakili oleh
                    New Customer") -- kasus "customer belum terdaftar" sekarang lewat quick-add
                    Customer Master sungguhan (openNewCustomerModal(), lihat combobox di bawah),
                    bukan lagi placeholder nama tanpa record. is_prospect/prospect_name TETAP
                    ada di properti/kolom DB (backward-compat baca inquiry lama yg masih
                    is_prospect=true) tapi tidak lagi bisa di-set true dari layar ini. --}}
                    @if ($is_prospect)
                        <x-form.input legacy label="Nama Calon Customer (data lama)" wire:model="prospect_name" placeholder="Nama prospek…" :disabled="$readOnly" name="prospect_name" />
                    @elseif ($customer_id)
                        <div class="f">
                            <label>Customer</label>
                            <div style="display:flex;gap:6px;align-items:center">
                                <input type="text" value="{{ $customer_name }}" readonly class="cust-ok">
                                @unless($readOnly)
                                    <button type="button" class="btn g xs" wire:click="clearCustomer">Ganti</button>
                                @endunless
                            </div>
                        </div>
                    @else
                        {{-- Combobox scroll+search (permintaan user 2026-09-04: "dropdown yang
                        langsung bisa discroll tapi bisa diketik juga, New Customer paling atas").
                        Buka/tutup murni Alpine (x-data lokal, tanpa roundtrip Livewire) supaya
                        instan; isi list (customerResults) tetap dari Livewire, termasuk saat
                        query kosong (browse) maupun diketik (filter). --}}
                        <div class="f ac-wrap" x-data="{ open: false }" @click.outside="open = false">
                            <label>Customer</label>
                            <input type="text" wire:model.live.debounce.300ms="custQuery" autocomplete="off"
                                   @focus="open = true" @click="open = true"
                                   placeholder="Ketik utk cari, atau scroll pilih…" @if($readOnly) disabled @endif>
                            @error('customer_id') <span class="hint" style="color:#e55353">{{ $message }}</span> @enderror
                            <div class="ac-box" :class="open ? 'on' : ''">
                                @unless($readOnly)
                                    <div class="ac-i" style="color:#1560a8;font-weight:700" wire:click="openNewCustomerModal" @click="open = false">
                                        ＋ Buat Customer Baru{{ mb_strlen($custQuery) >= 2 ? ': "'.$custQuery.'"' : '' }}
                                    </div>
                                @endunless
                                @forelse ($this->customerResults as $c)
                                    <div class="ac-i" wire:click="selectCustomer({{ $c->id_customer }})" @click="open = false">
                                        <b>{!! $this->highlightMatch($c->customer_name, $custQuery) !!}</b>
                                        <span class="cd">{{ $c->customer_code }}</span>
                                    </div>
                                @empty
                                    <div class="ac-none">Tidak ditemukan.</div>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    {{-- Nama Produk autogrow: mulai 1 baris, tinggi nambah sendiri kalau
                    isinya melebihi lebar kotak (bukan <input> krn <input> tidak pernah
                    bisa wrap/tambah tinggi -- keterbatasan HTML, bukan pilihan desain). --}}
                    @php $productDraftHint = 'Nama sementara selama quotation. Karakter khusus (/@#%"\';) tidak diperbolehkan.'; @endphp
                    <x-form.textarea legacy autogrow label="Nama Produk (draft)" wire:model="product_draft_name" maxlength="150"
                        placeholder="mis. Box Kosmetik Serum 30ml" :disabled="$readOnly" name="product_draft_name"
                        :hint="$productDraftHint" />

                    {{-- Kategori Produk Pasar (permintaan user 2026-09-04: "pindah ke atas,
                    pilihan lebih detail di pasaran umum spt box kosmetik") -- dropdown ringkas
                    (pola sama "Ukuran Pasaran"), pilih -> Tipe Produk Part 1 + template Body/
                    Lid (kalau relevan) langsung terisi. Cuma starting point, bisa diedit
                    manual per part sesudahnya -- lihat applyProductCategory(). --}}
                    <div class="f">
                        <label>Kategori Produk <span class="hint" style="font-weight:400">(opsional, isi cepat Part 1)</span></label>
                        <select wire:change="applyProductCategory($event.target.value)" @if($readOnly) disabled @endif>
                            <option value="">— pilih kategori pasar —</option>
                            @foreach ($this->productCategoryMap() as $catCode => $cat)
                                <option value="{{ $catCode }}">{{ $cat['label'] }}</option>
                            @endforeach
                        </select>
                        <div class="hint">Isi cepat Tipe Produk &amp; part umum — tetap bisa diedit manual di bawah.</div>
                    </div>
                </div>

                {{-- Tipe Produk level Inquiry (permintaan user 2026-09-04: "tipe produk
                harusnya di luar part, bukan di dalam"). Berlaku utk SEMUA Part sekaligus
                (setSharedProductType()) -- koheren krn satu2nya template multi-part yg
                didukung (Box with Lid/Round Box/Shallow Box) generatornya memang dipakai
                bareng Body+Lid, bukan tipe berbeda per part. Kategori Produk di atas cuma
                jalan pintas ke field yg sama ini. --}}
                <div class="grid" style="margin-top:10px">
                    <div class="f col2">
                        <label>Tipe Produk <span class="hint" style="font-weight:400">(berlaku utk semua Part)</span></label>
                        <select wire:change="setSharedProductType($event.target.value)"
                                x-data x-effect="$el.value = @js($parts[0]['type_code'] ?? '')"
                                @if($readOnly) disabled @endif>
                            <option value="">— pilih tipe produk —</option>
                            @foreach ($this->productTypeOptions as $code => $t)
                                <option value="{{ $code }}">{{ $t['name'] }}</option>
                            @endforeach
                        </select>
                        <div class="hint">Kategori Produk di atas cuma jalan pintas — bisa juga pilih langsung di sini.</div>
                    </div>
                    @php($headerPartTpl = $this->partTemplateSuggestion(0))
                    @if ($headerPartTpl)
                        <div class="f col2">
                            <label>&nbsp;</label>
                            <div class="note b" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:0">
                                <span>🧩 Tipe ini biasanya {{ $headerPartTpl['total'] }} part: <b>{{ $headerPartTpl['labels'] }}</b>.</span>
                                @unless($readOnly)
                                    <button type="button" class="btn g xs" wire:click="applyPartTemplate(0)">Tambahkan Otomatis</button>
                                @endunless
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Baris 3: Catatan Sales + tickmark Sumber Bahan (permintaan user
                2026-09-04: "tickmark default un-tick utk customer bawa raw material
                sendiri" -- checkbox tunggal gantikan radio 2 pilihan, lebih ringkas lagi
                krn cuma 2 kemungkinan (OWN/CUSTOMER). "Jenis Pekerjaan" TETAP dihapus dari
                UI (sudah diwakili checkbox ini), jobNature() tetap jalan di background
                (dipakai save() nulis job_nature). Checkbox pakai wire:click ke
                setMaterialSource() yg sama (bukan wire:model checkbox langsung) --
                konsisten sama toggle/radio lain di file ini, jaga2 morph-sync spt
                memory feedback_livewire_select_morph_bug. --}}
                <div class="grid two" style="margin-top:10px">
                    <x-form.textarea legacy autogrow label="Catatan Sales" wire:model="sales_note" placeholder="opsional" :disabled="$readOnly" />
                    <div class="f">
                        <label>Sumber Bahan</label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:7px 0">
                            <input type="checkbox" @checked($material_source === 'CUSTOMER')
                                   wire:click="setMaterialSource('{{ $material_source === 'CUSTOMER' ? 'OWN' : 'CUSTOMER' }}')"
                                   @if($readOnly) disabled @endif>
                            <span style="font-size:12.5px;color:#233">Customer bawa bahan baku sendiri</span>
                        </label>
                        <div class="hint">{{ $material_source === 'OWN' ? 'Tidak dicentang → bahan dari perusahaan, order bersifat PRODUCT.' : 'Dicentang → bahan dari customer, order bersifat JASA (cuma ongkos cetak/finishing).' }}</div>
                    </div>
                </div>
                {{-- Skema PPN & PPh23 disembunyikan dari layar Inquiry (permintaan user
                2026-09-04: "belum perlu saat input inquiry, pindah nanti di CO seperti
                halnya discount... ini hal yang pasti") -- properti/validasi/kolom DB
                TETAP ada, cuma UI-nya dilepas (pola sama persis dgn Diskon Dokumen,
                commit 4990a82). Default tax_scheme='PPN' & pph_applicable otomatis dari
                Sumber Bahan (setMaterialSource()) tetap jalan di background. --}}

                @if ($material_source === 'CUSTOMER')
                    <div style="margin-top:6px">
                        <div class="sec-title">Spesifikasi Bahan Customer <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#8a94a6">(wajib bila bahan dari customer)</span></div>
                        <div class="grid two">
                            <x-form.input legacy col2 label="Deskripsi bahan customer *" wire:model="cust_material_spec"
                                placeholder="mis. Art Carton 260gsm, 65×100cm, brand APP, 50 rim" :disabled="$readOnly" name="cust_material_spec" />
                            <x-form.date-picker legacy label="ETA bahan tiba" wire:model="cust_material_eta" :disabled="$readOnly" />
                        </div>
                        <div class="note b" style="margin-top:6px">Bahan customer → HPP material = 0; masuk via Receiving non-costed (consignment). Handling &amp; spoilage dihitung Costing.</div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Popup pilih PIC Sales (permintaan user 2026-09-04) --}}
        @if ($showSalesPicker)
            <div style="position:fixed;inset:0;z-index:200;background:rgba(15,22,45,.45);display:flex;align-items:center;justify-content:center;padding:24px"
                 wire:click.self="closeSalesPicker">
                <div style="background:#fff;border-radius:12px;width:100%;max-width:420px;max-height:70vh;display:flex;flex-direction:column;overflow:hidden">
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #e3e7f0">
                        <b style="font-size:14px;color:#2c3e69">Pilih PIC Sales</b>
                        <button type="button" wire:click="closeSalesPicker" style="border:none;background:none;font-size:18px;cursor:pointer;line-height:1">✕</button>
                    </div>
                    <div style="padding:12px 18px 0">
                        <input type="text" wire:model.live.debounce.300ms="salesQuery" placeholder="Cari nama sales…" autocomplete="off">
                    </div>
                    <div style="overflow-y:auto;padding:10px 18px 18px">
                        @forelse ($this->salesPersonResults as $sp)
                            <div wire:click="selectSalesPerson({{ $sp->id_sales_person }})"
                                 style="padding:8px 10px;border-radius:6px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:8px"
                                 onmouseover="this.style.background='#f4f6fa'" onmouseout="this.style.background='transparent'">
                                <span style="font-size:12.5px;color:#2c3e69">{{ $sp->sales_name }}</span>
                                <span style="font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:10px;flex-shrink:0;{{ $sp->sales_type === 'INTERNAL' ? 'background:#eaf7ee;color:#1f8a4c' : 'background:#fff4e5;color:#8a4b00' }}">{{ $sp->sales_type }}</span>
                            </div>
                        @empty
                            <div class="hint">Tidak ada hasil.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        {{-- Popup quick-add Customer Master (permintaan user 2026-09-04) --}}
        @if ($showNewCustomerModal)
            <div style="position:fixed;inset:0;z-index:200;background:rgba(15,22,45,.45);display:flex;align-items:center;justify-content:center;padding:24px"
                 wire:click.self="closeNewCustomerModal">
                <div style="background:#fff;border-radius:12px;width:100%;max-width:460px;max-height:85vh;display:flex;flex-direction:column;overflow:hidden">
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #e3e7f0">
                        <b style="font-size:14px;color:#2c3e69">Buat Customer Baru</b>
                        <button type="button" wire:click="closeNewCustomerModal" style="border:none;background:none;font-size:18px;cursor:pointer;line-height:1">✕</button>
                    </div>
                    <div style="padding:16px 18px;overflow-y:auto">
                        <div class="note b" style="margin-bottom:10px">Customer dibuat status <b>DRAFT</b> — Finance/Credit tetap perlu review lengkap di Master Customer sebelum bisa transaksi penuh.</div>
                        <div class="grid" style="grid-template-columns:1fr">
                            <x-form.input legacy label="Nama Customer *" wire:model="newCustName" placeholder="Nama perusahaan/customer" name="newCustName" />
                            <x-form.toggle-group legacy label="Tipe">
                                <x-form.toggle-option legacy :active="$newCustType === 'local'" style="flex:1" wire:click="setNewCustType('local')">Lokal</x-form.toggle-option>
                                <x-form.toggle-option legacy :active="$newCustType === 'import'" style="flex:1" wire:click="setNewCustType('import')">Import</x-form.toggle-option>
                            </x-form.toggle-group>
                            <x-form.input legacy label="No. Telp" wire:model="newCustPhone" placeholder="0812xxxxxxxx" name="newCustPhone" />
                            <x-form.input legacy label="Email" wire:model="newCustEmail" placeholder="opsional" name="newCustEmail" />
                            <x-form.textarea legacy label="Alamat" wire:model="newCustAddress" rows="2" placeholder="opsional" />
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;justify-content:flex-end;padding:12px 18px;border-top:1px solid #e3e7f0;background:#f9fafb">
                        <button type="button" class="btn g" wire:click="closeNewCustomerModal">Batal</button>
                        <button type="button" class="btn p" wire:click="saveNewCustomer">Simpan &amp; Pakai</button>
                    </div>
                </div>
            </div>
        @endif

        <div class="card">
            <h3>Qty Order Berjenjang <span style="font-weight:400;color:#889">(Costing hitung HPP per tier — tepat 1 UTAMA)</span></h3>
            <div class="body">
                @error('tiers')
                    <div class="note o">{{ $message }}</div>
                @enderror
                {{-- Compact (permintaan user 2026-09-04): qty order maks 8-9 digit (puluhan
                juta), input selebar tabel penuh cuma buang ruang -- dibungkus max-width
                supaya tabel & input-nya tetap ringkas. Preview artwork yg sempat dipasang
                di ruang kanan DICOPOT LAGI (user: "ternyata jelek, hapus saja" -- keputusan
                soal isi ruang kanan ditunda dulu, "nanti diatur lagi"). --}}
                <div style="max-width:400px">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:36px">Seq</th>
                                <th style="width:140px">Qty (pcs)</th>
                                <th style="width:90px">Utama</th>
                                <th style="width:34px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tiers as $i => $tier)
                                <tr wire:key="tier-row-{{ $tier['id'] ?? 'new-'.$i }}">
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <input type="number" step="1" min="0" max="999999999" wire:model.blur="tiers.{{ $i }}.qty"
                                               placeholder="mis. 10000" @if($readOnly) disabled @endif>
                                    </td>
                                    <td style="text-align:center">
                                        <button type="button" class="btn xs {{ $tier['is_primary'] ? 'p' : 'g' }}" style="white-space:nowrap"
                                                wire:click="setPrimaryTier({{ $i }})" @if($readOnly) disabled @endif>
                                            {{ $tier['is_primary'] ? '✓ Utama' : 'Pilih' }}
                                        </button>
                                    </td>
                                    <td>
                                        <button type="button" class="btn d xs" wire:click="removeTier({{ $i }})"
                                                @if($readOnly || count($tiers) <= 1) disabled @endif>✕</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn g" style="margin-top:8px" wire:click="addTier" @if($readOnly) disabled @endif>
                    ＋ Tambah Tier
                </button>
            </div>
        </div>

        <div class="card">
            <h3>Part &amp; Dieline <span style="font-weight:400;color:#889">(dimensi produk &amp; preview dieline live)</span></h3>
            <div class="body">
                @foreach ($parts as $i => $part)
                    <div class="partcard" wire:key="part-{{ $part['id'] ?? 'new-'.$i }}">
                        <div class="parthd">
                            <span class="pnm">{{ $part['part_name'] !== '' ? $part['part_name'] : 'Part '.($i + 1) }}</span>
                            {{-- Mode #1 -- kirim part ini ke ProDev (CLAUDE.md §4 VITAL, link yg
                            sebelumnya tidak ada). WAJIB Inquiry sudah CONFIRMED (stat_confirm),
                            bukan cuma tersimpan -- selaras "Confirm Inquiry = spec sudah FIX, boleh
                            diproses" (inquiryproduct_flow.md §3) & panel referensi read-only di
                            ProDev yg cuma nampilin Inquiry CONFIRMED (user 2026-09-03, biar tidak
                            kirim spec yg masih bisa berubah). --}}
                            @if ($part['id'] && $stat_confirm)
                                <button type="button" class="btn xs" style="margin-left:auto" wire:click="sendToProDev({{ $i }})">🏭 Kirim ke ProDev</button>
                            @endif
                            @if (count($parts) > 1)
                                <button type="button" class="btn d xs" @if(! $part['id']) style="margin-left:auto" @endif wire:click="removePart({{ $i }})" @if($readOnly) disabled @endif>✕ hapus part</button>
                            @endif
                        </div>
                        <div class="partbody">
                            <div class="grid" style="margin-bottom:8px">
                                <div class="f">
                                    <label>Nama Part</label>
                                    <div style="display:flex;gap:6px;align-items:center">
                                        <x-form.input :legacy="'flex-1 min-w-0'" wire:model.blur="parts.{{ $i }}.part_name" placeholder="mis. Body" :disabled="$readOnly" />
                                        @unless($readOnly)
                                            <button type="button" wire:click="openRolePicker({{ $i }})" title="Pilih peran part (nama terisi otomatis)"
                                                style="flex-shrink:0;width:34px;height:34px;border:1px solid #c7cfda;border-radius:6px;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px">🏷️</button>
                                        @endunless
                                    </div>
                                </div>
                                <div class="f">
                                    <label>Tipe Part</label>
                                    <select wire:model="parts.{{ $i }}.part_type" x-data x-effect="$el.value = @js($part['part_type'])" @if($readOnly) disabled @endif>
                                        @foreach ([['DC', 'Paper'], ['DC+SHEET', 'Paper + Sheet'], ['DC+SF', 'Paper + SF'], ['SHEET', 'Sheet'], ['KRAFT', 'Kraft']] as [$code, $label])
                                            <option value="{{ $code }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="f">
                                    <label>Qty per Set</label>
                                    <input type="number" min="0" step="0.01" wire:model.blur="parts.{{ $i }}.qty_per_sku" @if($readOnly) disabled @endif>
                                </div>
                                <div class="f">
                                    <label>Isi per Pack (pcs)</label>
                                    <input type="number" min="1" step="1" wire:model.blur="parts.{{ $i }}.pack_qty" placeholder="mis. 400" @if($readOnly) disabled @endif>
                                </div>
                                <div class="f" x-data="{ zoom: false }">
                                    <label>Artwork / Desain</label>
                                    @php($artworkPreviewUrl = isset($artworkUploads[$i]) ? $artworkUploads[$i]->temporaryUrl() : ($part['artwork_path'] ? asset('storage/'.$part['artwork_path']) : null))
                                    @if ($artworkPreviewUrl)
                                        <div style="display:flex;gap:6px;align-items:center">
                                            <img src="{{ $artworkPreviewUrl }}" @click="zoom = true" style="width:48px;height:48px;object-fit:cover;border-radius:4px;border:1px solid #c7cfda;flex-shrink:0;cursor:zoom-in" alt="artwork">
                                            <div style="flex:1;min-width:0">
                                                <div style="font-size:10.5px;font-weight:600;color:#334;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="{{ $part['artwork_original_filename'] }}">{{ $part['artwork_original_filename'] }}</div>
                                                @unless($readOnly)
                                                    <button type="button" class="btn d xs" wire:click="removeArtwork({{ $i }})" style="margin-top:2px">✕ hapus</button>
                                                @endunless
                                            </div>
                                        </div>
                                        <div x-show="zoom" x-cloak @click.self="zoom = false" @keydown.escape.window="zoom = false"
                                             style="position:fixed;inset:0;z-index:250;background:rgba(15,22,45,.72);display:flex;align-items:center;justify-content:center;padding:24px">
                                            <img src="{{ $artworkPreviewUrl }}" style="max-width:90vw;max-height:88vh;border-radius:8px;box-shadow:0 12px 40px rgba(0,0,0,.4)" alt="artwork ukuran penuh">
                                        </div>
                                    @elseif(! $readOnly)
                                        <input type="file" x-ref="artworkInput{{ $i }}" wire:model="artworkUploads.{{ $i }}" accept=".jpg,.jpeg,.png" style="display:none">
                                        <button type="button" class="btn g" style="display:inline-flex;align-items:center;gap:6px;width:fit-content"
                                                @click="$refs['artworkInput{{ $i }}'].click()">
                                            📎 Upload Artwork
                                        </button>
                                        <div wire:loading wire:target="artworkUploads.{{ $i }}" class="hint" style="color:#1560a8">⏳ mengunggah &amp; menganalisis…</div>
                                        <div wire:loading.remove wire:target="artworkUploads.{{ $i }}" class="hint">JPG/PNG, maks {{ $this->artworkMaxMb }}MB — preview &amp; saran otomatis langsung jalan</div>
                                        @error('artworkUploads.'.$i) <div style="font-size:10px;color:#c0392b">{{ $message }}</div> @enderror
                                    @endif
                                </div>
                            </div>

                            @php($typeOptions = $this->productTypeOptions)
                            @php($selectedType = $typeOptions[$part['type_code']] ?? null)
                            <div class="flx"
                                 wire:key="dieline-{{ $i }}-{{ $part['dims_version'] ?? 0 }}"
                                 x-data="dielinePart(@js($typeOptions), @js($this->partAlpineInit($i)))"
                                 data-index="{{ $i }}">
                                @if (empty($part['type_code']))
                                    {{-- Tipe Produk sekarang dipilih di Header (permintaan user 2026-09-04:
                                    "tipe produk harusnya di luar part") -- berlaku utk semua Part, bukan
                                    per-part lagi. Kemampuan render dieline (TMDieline) TIDAK disentuh, ini
                                    murni relokasi alur pemilihan (klarifikasi eksplisit user). --}}
                                    <div class="note o" style="flex:1">📋 Pilih <b>Tipe Produk</b> dulu di bagian atas (dekat Kategori Produk) sebelum mengisi part ini.</div>
                                @elseif (empty($part['part_role']) && ! $part['id'])
                                    {{-- Diecut baru aktif setelah Peran Part dipilih (permintaan user:
                                    "diecut baru jalan ketika part dipilih spt body atau lid"). Gerbang
                                    ini SENGAJA dilewati kalau part SUDAH PERNAH TERSIMPAN ($part['id']
                                    ada) -- data lama dari sblm fitur Peran Part ada TETAP TAMPIL apa
                                    adanya, drpd nyembunyiin data lengkap cuma krn belum pernah diberi
                                    role secara retroaktif. Cek "dims kosong" TIDAK bisa dipakai di sini
                                    (selectPartType() selalu isi dims dgn default begitu tipe dipilih,
                                    jadi part BARU pun langsung py dims non-kosong -- id_part null/ada
                                    itu sinyal yg benar2 bedain "belum pernah disimpan" vs "sudah").
                                    Tipe
                                    non-multi-part auto-isi MonoForm dari setSharedProductType(), jadi
                                    cabang ini praktis cuma kejadian utk tipe multi-part (Box with
                                    Lid/Round Box/Shallow Box) yg belum ditentukan bagiannya. --}}
                                    <div class="note b" style="flex:1;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                        <span>🏷️ Pilih <b>Peran Part</b> ini dulu (ikon 🏷️ di sebelah Nama Part) — dimensi &amp; preview dieline aktif setelah peran ditentukan.</span>
                                        @unless($readOnly)
                                            <button type="button" class="btn g xs" wire:click="openRolePicker({{ $i }})">Pilih Peran Part</button>
                                        @endunless
                                    </div>
                                @else
                                <div style="flex:1">
                                    <div class="sec-title">Dimensi</div>
                                    <div class="grid">
                                        @forelse ($selectedType['params'] ?? [] as $param)
                                            <div class="f">
                                                <label>{{ $param['label'] }}{{ $param['uom'] ? ' ('.$param['uom'].')' : '' }}</label>
                                                @if ($param['type'] === 'ENUM')
                                                    <select wire:model.blur="parts.{{ $i }}.dims.{{ $param['key'] }}"
                                                            x-data x-effect="$el.value = @js($part['dims'][$param['key']] ?? '')"
                                                            x-on:change="onDim('{{ $param['key'] }}', $event.target.value)" @if($readOnly) disabled @endif>
                                                        @foreach ($param['options'] as $opt)
                                                            <option value="{{ $opt['c'] }}">{{ $opt['l'] }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input type="number" step="0.01" wire:model.blur="parts.{{ $i }}.dims.{{ $param['key'] }}"
                                                           x-on:input="onDim('{{ $param['key'] }}', $event.target.value)" @if($readOnly) disabled @endif>
                                                @endif
                                            </div>
                                        @empty
                                            <div class="hint" style="grid-column:span 4">Pilih tipe produk dulu.</div>
                                        @endforelse
                                    </div>
                                    @if ($selectedType && empty($selectedType['spec']))
                                        <div class="nospec">⚠️ Tipe ini belum punya <code>dieline_spec</code>. Isi Flat manual.</div>
                                    @endif

                                    <div class="sec-title">Bentangan (Flat) — dipakai Costing</div>
                                    @if ($part['suggestions']['size'] ?? null)
                                        @php($sugSize = $part['suggestions']['size'])
                                        <div class="note o" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                            <span>📐 Ukuran terbaca dari metadata DPI artwork ({{ $sugSize['dpi_x'] }}×{{ $sugSize['dpi_y'] }} dpi): <b>{{ $sugSize['width_mm'] }} × {{ $sugSize['height_mm'] }} mm</b> — asumsi ini bentangan/flat yg dicetak, bukan dimensi luar box.
                                            @if ($sugSize['dpi_x'] == 72 || $sugSize['dpi_x'] == 96)
                                                ⚠️ {{ $sugSize['dpi_x'] }} dpi biasanya cuma default layar/software, BUKAN skala cetak asli — kemungkinan besar tidak akurat, jangan langsung dipakai.
                                            @else
                                                Tetap cek ulang manual sebelum konfirmasi ke customer.
                                            @endif
                                            </span>
                                            @unless($readOnly)
                                                <button type="button" class="btn g xs" @click="applyArtworkSize({{ $sugSize['width_mm'] }}, {{ $sugSize['height_mm'] }})">Pakai ke Flat W/H</button>
                                            @endunless
                                        </div>
                                    @endif
                                    <div class="grid">
                                        <div class="f">
                                            <label>Flat Width (mm)</label>
                                            <input type="number" step="0.01" wire:model="parts.{{ $i }}.flat_w" x-ref="fw"
                                                   :readonly="!flatManual" x-on:input="onFlatManualInput()" @if($readOnly) disabled @endif>
                                        </div>
                                        <div class="f">
                                            <label>Flat Height (mm)</label>
                                            <input type="number" step="0.01" wire:model="parts.{{ $i }}.flat_h" x-ref="fh"
                                                   :readonly="!flatManual" x-on:input="onFlatManualInput()" @if($readOnly) disabled @endif>
                                        </div>
                                        <div class="f">
                                            <label>Flat Area (mm²)</label>
                                            <input type="text" wire:model="parts.{{ $i }}.flat_area" x-ref="fa" readonly>
                                        </div>
                                        <div class="f">
                                            <label>Manual?</label>
                                            <div style="display:flex;gap:6px">
                                                <button type="button" class="btn xs" :class="!flatManual ? 'p' : 'g'" style="flex:1" @click="toggleManual(false)" @if($readOnly) disabled @endif>Auto</button>
                                                <button type="button" class="btn xs" :class="flatManual ? 'p' : 'g'" style="flex:1" @click="toggleManual(true)" @if($readOnly) disabled @endif>Manual</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="sec-title">Bahan (Material)</div>
                                    @if (($part['suggestions']['material'] ?? null) && empty($part['materials'][0]['category_id'] ?? null))
                                        @php($sugMat = $part['suggestions']['material'])
                                        <div class="note b" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                            <span>💡 Saran bahan dari histori: <b>{{ $sugMat['rawmat_name'] }}</b> (dipakai {{ $sugMat['pct'] }}% Inquiry Tipe Produk ini sebelumnya)</span>
                                            @unless($readOnly)
                                                <button type="button" class="btn g xs" wire:click="applySuggestedMaterial({{ $i }}, {{ $sugMat['category_id'] }})">Pakai</button>
                                            @endunless
                                        </div>
                                    @endif
                                    <div class="hint" style="margin-bottom:6px">Sales pilih Jenis &amp; Gramatur/Spec saja — brand &amp; ukuran plano ditentukan Purchasing/ProDev kemudian.</div>
                                    @foreach ($part['materials'] as $j => $mat)
                                        <div wire:key="mat-{{ $i }}-{{ $j }}" style="margin-bottom:10px">
                                            <div class="grid">
                                                <div class="f">
                                                    <label>Bahan #{{ $j + 1 }} — {{ $this->materialSlotLabel($j) }}</label>
                                                    <select wire:change="setMaterialJenis({{ $i }}, {{ $j }}, $event.target.value)"
                                                            x-data x-effect="$el.value = @js((string) ($mat['jenis_id'] ?? ''))" @if($readOnly) disabled @endif>
                                                        <option value="">— pilih Jenis —</option>
                                                        @foreach ($this->materialJenisOptions($j) as $catId => $name)
                                                            <option value="{{ $catId }}">{{ $name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="f">
                                                    <label>Gramatur / Spec <span class="hint" style="font-weight:400">(opsional)</span></label>
                                                    <select wire:change="setMaterialGramatur({{ $i }}, {{ $j }}, $event.target.value)"
                                                            x-data x-effect="$el.value = @js((string) ($mat['gramatur_id'] ?? ''))"
                                                            @if($readOnly || ! $mat['jenis_id']) disabled @endif>
                                                        <option value="">— semua —</option>
                                                        @foreach ($this->materialGramaturOptions($i, $j) as $catId => $name)
                                                            <option value="{{ $catId }}">{{ $name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="f" style="max-width:100px">
                                                    <label>Utama</label>
                                                    <button type="button" class="btn xs {{ $mat['is_primary'] ? 'p' : 'g' }}" style="width:100%"
                                                            wire:click="setMaterialPrimary({{ $i }}, {{ $j }})" @if($readOnly) disabled @endif>
                                                        {{ $mat['is_primary'] ? '✓ Utama' : '—' }}
                                                    </button>
                                                </div>
                                            </div>
                                            @if ($mat['display_name'])
                                                <div class="hint">{{ $mat['display_name'] }}</div>
                                            @endif
                                        </div>
                                    @endforeach

                                    <div class="sec-title">Cetak / Warna</div>
                                    @if ($part['suggestions']['color'] ?? null)
                                        @php($sugColor = $part['suggestions']['color'])
                                        <div class="note b" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                            <span>💡 Saran notasi warna dari analisis artwork: <b>{{ $sugColor['code'] }}</b> ({{ $sugColor['distinct_colors'] }} warna terdeteksi{{ $sugColor['is_grayscale'] ? ', hitam-putih' : '' }}) — perkiraan kasar, cek ulang sebelum dipakai.</span>
                                            @unless($readOnly)
                                                <button type="button" class="btn g xs" wire:click="applySuggestedColor({{ $i }}, '{{ $sugColor['code'] }}')">Pakai</button>
                                            @endunless
                                        </div>
                                    @endif
                                    @if ($part['suggestions']['finishing'] ?? null)
                                        @php($sugFin = $part['suggestions']['finishing'])
                                        <div class="note y" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                            <span>📋 Saran finishing dari histori: <b>{{ $sugFin['process_name'] }}</b> (dipakai {{ $sugFin['pct'] }}% Inquiry Tipe Produk ini sebelumnya).</span>
                                            @unless($readOnly)
                                                <button type="button" class="btn g xs" wire:click="applySuggestedFinishing({{ $i }}, {{ (int) $sugFin['finishing_type_id'] }})">Tambah ke Finishing</button>
                                            @endunless
                                        </div>
                                    @endif
                                    <div class="grid">
                                        <div class="f">
                                            <label>Notasi Warna</label>
                                            <select wire:model="parts.{{ $i }}.cfg_color" x-data x-effect="$el.value = @js($part['cfg_color'])" @if($readOnly) disabled @endif>
                                                @foreach (range(1, 6) as $front)
                                                    @foreach (range(0, 6) as $back)
                                                        @php($opt = "{$front}/{$back}")
                                                        <option value="{{ $opt }}" @selected($part['cfg_color'] === $opt)>{{ $opt }}</option>
                                                    @endforeach
                                                @endforeach
                                            </select>
                                            <div class="hint">Format sisiA/sisiB (1/0 s.d. 6/6)</div>
                                        </div>
                                        <div class="f col3">
                                            <label>Turunan (otomatis)</label>
                                            @php($cb = $this->colorBreakdown($part['cfg_color']))
                                            <div class="derived">
                                                @if ($cb)
                                                    <span>front: <b>{{ $cb['front'] }}</b></span>
                                                    <span>back: <b>{{ $cb['back'] }}</b></span>
                                                    <span>total: <b>{{ $cb['total'] }}</b></span>
                                                    <span>side: <b>{{ $cb['side'] }}</b></span>
                                                @else
                                                    <span class="hint">&nbsp;</span>
                                                @endif
                                            </div>
                                        </div>
                                        <x-form.input :legacy="true" :col2="true" label="Catatan Warna (Pantone)"
                                            wire:model.blur="parts.{{ $i }}.cfg_ink"
                                            placeholder="mis. Pantone 485 C" :disabled="$readOnly" />
                                    </div>

                                    {{-- Finishing Tambahan (permintaan user 2026-09-04: "master detail bisa
                                    ditambah apa saja, misal hot spot dan lain finishing, buat dropdown nya")
                                    -- master-detail: berapa pun baris, tiap baris pilih dari dropdown
                                    ref_finishing_type (master yg sama dipakai ProDev). Mengisi gap yg
                                    sebelumnya dicatat di readonly-summary.blade.php ("form Inquiry BARU
                                    belum punya UI proses/finishing"). --}}
                                    <div class="sec-title">Finishing Tambahan</div>
                                    <div class="hint" style="margin-bottom:6px">Opsional — proses dekoratif tambahan (hot print, UV spot, emboss, laminating, dll), boleh lebih dari satu.</div>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th style="width:32px">#</th>
                                                <th>Jenis Finishing</th>
                                                <th>Atribut / Varian</th>
                                                <th style="width:36px"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($part['finishings'] as $j => $fin)
                                                <tr wire:key="fin-{{ $i }}-{{ $fin['id'] ?? 'new-'.$j }}">
                                                    <td>{{ $j + 1 }}</td>
                                                    <td>
                                                        <select wire:model="parts.{{ $i }}.finishings.{{ $j }}.finishing_type_id"
                                                                x-data x-effect="$el.value = @js((string) $fin['finishing_type_id'])" @if($readOnly) disabled @endif>
                                                            <option value="">— pilih jenis finishing —</option>
                                                            @foreach ($this->finishingTypeOptions as $ft)
                                                                <option value="{{ $ft->id_finishing_type }}">{{ $ft->finishing_name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="text" maxlength="60" wire:model.blur="parts.{{ $i }}.finishings.{{ $j }}.attribute"
                                                               placeholder="mis. Gold Puyeh, area logo" @if($readOnly) disabled @endif>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn d xs" wire:click="removeFinishingRow({{ $i }}, {{ $j }})" @if($readOnly) disabled @endif>✕</button>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" style="text-align:center;color:#8a94a6;font-size:11.5px">Belum ada finishing tambahan.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                    @unless($readOnly)
                                        <button type="button" class="btn g xs" style="margin-top:6px" wire:click="addFinishingRow({{ $i }})">
                                            ＋ Tambah Finishing
                                        </button>
                                    @endunless
                                </div>

                                <div style="width:360px;flex-shrink:0">
                                    <label style="font-size:11px;color:#667;font-weight:600">Preview Dieline (live)</label>
                                    <div class="dcut" x-ref="preview"></div>
                                    <div class="legend">🔴 potong &nbsp; 🟢 lipat &nbsp; ⚪ overflow &nbsp; 🔵 lubang/jilid</div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach

                <button type="button" class="btn g" wire:click="addPart" @if($readOnly) disabled @endif>
                    ＋ Tambah Part
                </button>
            </div>
        </div>

        {{-- Popup Peran Part (permintaan user 2026-09-04: "kasih gambar di samping
        nama biar mudah dipahami sales junior, icon tidak terlalu kecil tapi jelas")
        -- <select> native tidak bisa nampilkan icon, jadi popup kartu (pola sama
        dgn "Pilih Tipe Produk (Visual)" di atas). Klik kartu -> selectPartRole()
        generate nama part = {slug nama produk}-{role}, beku setelah digenerate
        (tidak auto-update lagi kalau Nama Produk diedit belakangan). --}}
        @if ($rolePickerPartIndex !== null)
            <div style="position:fixed;inset:0;z-index:200;background:rgba(15,22,45,.45);display:flex;align-items:center;justify-content:center;padding:24px"
                 wire:click.self="closeRolePicker">
                <div style="background:#fff;border-radius:12px;width:100%;max-width:620px;max-height:80vh;display:flex;flex-direction:column;overflow:hidden">
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #e3e7f0">
                        <div>
                            <b style="font-size:14px;color:#2c3e69">Pilih Peran Part</b>
                            <div class="hint" style="margin:2px 0 0">Nama part terisi otomatis: nama-produk-{peran}</div>
                        </div>
                        <button type="button" wire:click="closeRolePicker" style="background:none;border:none;font-size:20px;cursor:pointer;color:#8a94a6;line-height:1">&times;</button>
                    </div>
                    <div style="overflow-y:auto;padding:16px;display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px">
                        @foreach ($this->partRoleOptions() as $roleKey => $role)
                            <button type="button" wire:click="selectPartRole('{{ $roleKey }}')"
                                style="border:2px solid #e3e7f0;border-radius:8px;padding:12px 8px;cursor:pointer;background:#fff;text-align:center;display:flex;flex-direction:column;gap:6px;align-items:center">
                                <span style="font-size:40px;line-height:1">{{ $role['icon'] }}</span>
                                <span style="font-size:12.5px;color:#2c3e69;font-weight:700">{{ $role['label'] }}</span>
                                <span style="font-size:10px;color:#8a94a6;line-height:1.3">{{ $role['desc'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="card">
            <h3>Status Tooling <span style="font-weight:400;color:#889">(plat/pisau/film — cek aus saat repeat order)</span></h3>
            <div class="body">
                <div class="note b" style="margin-bottom:10px">
                    🔧 Tooling baru (plat cetak, pisau/matras, film) → biaya masuk sebagai line terpisah. Master aset
                    tooling (cek otomatis aus) menyusul di iterasi berikutnya — isi manual dulu.
                </div>
                @if (empty($toolings))
                    <div class="hint">Belum ada baris tooling.</div>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th style="width:140px">Jenis</th>
                                <th style="width:150px">Status</th>
                                <th style="width:110px">Biaya Baru?</th>
                                <th style="width:140px">Biaya (Rp)</th>
                                <th>Catatan</th>
                                <th style="width:36px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($toolings as $i => $t)
                                <tr wire:key="tool-{{ $t['id'] ?? 'new-'.$i }}">
                                    <td>
                                        <select wire:model="toolings.{{ $i }}.tooling_kind" x-data x-effect="$el.value = @js($t['tooling_kind'])" @if($readOnly) disabled @endif>
                                            @foreach (['PLATE' => 'Plat cetak', 'PISAU' => 'Pisau/matras', 'MATRAS' => 'Matras', 'FILM' => 'Film'] as $code => $label)
                                                <option value="{{ $code }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select wire:model="toolings.{{ $i }}.tooling_status" x-data x-effect="$el.value = @js($t['tooling_status'])" @if($readOnly) disabled @endif>
                                            @foreach (['ADA' => 'Ada', 'ADA_WARNING' => 'Ada (warning)', 'AUS' => 'Aus', 'TIDAK_ADA' => 'Tidak ada'] as $code => $label)
                                                <option value="{{ $code }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td style="text-align:center">
                                        <button type="button" class="btn xs {{ $t['is_new_charge'] ? 'p' : 'g' }}"
                                                wire:click="setToolingNewCharge({{ $i }}, {{ $t['is_new_charge'] ? 'false' : 'true' }})" @if($readOnly) disabled @endif>
                                            {{ $t['is_new_charge'] ? 'Ya' : 'Tidak' }}
                                        </button>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" wire:model.blur="toolings.{{ $i }}.cost"
                                               @if(! $t['is_new_charge']) readonly @endif @if($readOnly) disabled @endif>
                                    </td>
                                    <td>
                                        <input type="text" wire:model.blur="toolings.{{ $i }}.remark" placeholder="opsional" @if($readOnly) disabled @endif>
                                    </td>
                                    <td>
                                        <button type="button" class="btn d xs" wire:click="removeTooling({{ $i }})" @if($readOnly) disabled @endif>✕</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                <button type="button" class="btn g xs" style="margin-top:6px" wire:click="addTooling" @if($readOnly) disabled @endif>
                    ＋ Tambah Tooling
                </button>
            </div>
        </div>

        {{-- Diskon Dokumen disembunyikan dari layar Inquiry (permintaan user 2026-09-04:
        "belum perlu discount" tahap ini) -- properti/logika/kolom DB TETAP ada
        (tidak dihapus, cuma UI-nya), krn ini bagian desain LOCKED CLAUDE.md §4
        ("Diskon jarang: item di iqp_tier, dokumen kolom-kembar header↔co_order")
        yg dipakai Quotation/CO nanti. Nilainya tetap NULL default spt biasa. --}}

        <div class="card">
            <h3>Biaya Eksternal / Pihak Ketiga <span style="font-weight:400;color:#889">(bila sudah ditentukan sejak awal)</span></h3>
            <div class="body">
                <div class="note b" style="margin-bottom:10px">
                    🚚 Biaya pihak ketiga yang sudah pasti (trucking, ekspedisi, jasa luar) dicatat sejak inkuiri →
                    mengalir ke quotation &amp; CO.
                </div>
                @if (empty($externalCosts))
                    <div class="hint">Belum ada baris biaya eksternal.</div>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Deskripsi</th>
                                <th style="width:140px">Jenis</th>
                                <th style="width:140px">Jumlah (Rp)</th>
                                <th style="width:170px">Perlakuan Pajak</th>
                                <th style="width:36px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($externalCosts as $i => $c)
                                <tr wire:key="ext-{{ $c['id'] ?? 'new-'.$i }}">
                                    <td>
                                        <input type="text" wire:model.blur="externalCosts.{{ $i }}.description"
                                               placeholder="mis. trucking ke gudang customer" @if($readOnly) disabled @endif>
                                    </td>
                                    <td>
                                        <select wire:model="externalCosts.{{ $i }}.cost_kind" x-data x-effect="$el.value = @js($c['cost_kind'])" @if($readOnly) disabled @endif>
                                            @foreach (['TRUCKING' => 'Trucking', 'EKSPEDISI' => 'Ekspedisi', 'JASA_LUAR' => 'Jasa Luar', 'LAINNYA' => 'Lainnya'] as $code => $label)
                                                <option value="{{ $code }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" wire:model.blur="externalCosts.{{ $i }}.amount" @if($readOnly) disabled @endif>
                                    </td>
                                    <td>
                                        <select wire:model="externalCosts.{{ $i }}.tax_treatment" x-data x-effect="$el.value = @js($c['tax_treatment'])" @if($readOnly) disabled @endif>
                                            <option value="TAXABLE">Taxable (masuk DPP)</option>
                                            <option value="REIMBURSEMENT">Reimbursement</option>
                                        </select>
                                    </td>
                                    <td>
                                        <button type="button" class="btn d xs" wire:click="removeExternalCost({{ $i }})" @if($readOnly) disabled @endif>✕</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                <button type="button" class="btn g xs" style="margin-top:6px" wire:click="addExternalCost" @if($readOnly) disabled @endif>
                    ＋ Tambah Biaya
                </button>
            </div>
        </div>

    </div>
    @endif
</div>
