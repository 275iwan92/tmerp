<?php

use App\Models\Master\CustCustomer;
use App\Models\Master\MatRaw;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Models\Master\ProdType;
use App\Models\Master\ProdTypeSize;
use App\Models\Sdi\IqpExternalCost;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpPart;
use App\Models\Sdi\IqpPartMaterial;
use App\Models\Sdi\IqpTier;
use App\Models\Sdi\IqpTooling;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('TELASIH MAKMUR — Inkuiri')] class extends Component
{
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

    public string $custQuery = '';

    public ?int $customer_id = null;

    public string $customer_name = '';

    public bool $is_prospect = false;

    public string $prospect_name = '';

    public ?string $deadline_quotation = null;

    public string $product_draft_name = '';

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

    /** @var array<int, array{id: ?int, part_name: string, part_type: string, type_code: string, qty_per_sku: string, dims: array, flat_manual: bool, flat_w: string, flat_h: string, flat_area: string, cfg_color: string, cfg_ink: string, materials: array}> */
    public array $parts = [];

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
                    'url' => $this->fromPart ? route('production.prodev.print', ['part' => $this->fromPart]) : null,
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
                'url' => $prodPartId ? route('production.prodev.print', ['part' => $prodPartId]) : null,
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
    public function theme(): \App\Models\Sys\SysTheme
    {
        return \App\Support\ActiveTheme::resolve();
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
            $this->addTier();
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
        $this->stat_confirm = (bool) $inq->stat_confirm;
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
            'materials' => $p->materials->map(fn (IqpPartMaterial $m) => [
                'id' => $m->id_part_material,
                'rawmat_id' => $m->rawmat_id,
                'display_name' => $m->rawmat_name ?? '',
                'brand' => $m->rawMaterial?->brand ?? '',
                'category' => $m->rawMaterial?->category1 ?? '',
                'gram_weight' => $m->gram_weight,
                'size_p' => $m->size_p,
                'size_l' => $m->size_l,
                'query' => '',
                'is_primary' => (bool) $m->is_primary,
            ])->values()->all(),
        ])->values()->all();

        if (empty($this->parts)) {
            $this->addPart();
        }

        foreach ($this->parts as $i => $part) {
            if (empty($part['materials'])) {
                $this->parts[$i]['materials'] = [$this->blankMaterialRow(true)];
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

    #[Computed]
    public function customerResults()
    {
        if ($this->is_prospect || $this->customer_id || mb_strlen($this->custQuery) < 2) {
            return collect();
        }

        return CustCustomer::query()
            ->where('activate', true)
            ->where(function ($q) {
                $q->where('customer_name', 'like', "%{$this->custQuery}%")
                    ->orWhere('customer_code', 'like', "%{$this->custQuery}%");
            })
            ->orderBy('customer_name')
            ->limit(8)
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
                    'sizes' => $t->sizes->map(fn ($s) => [
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
            'dims' => [],
            'flat_manual' => false,
            'flat_w' => '',
            'flat_h' => '',
            'flat_area' => '',
            'dims_version' => 0,
            'cfg_color' => '4/4',
            'cfg_ink' => '',
            'materials' => [$this->blankMaterialRow(true)],
        ];
    }

    protected function blankMaterialRow(bool $isPrimary = false): array
    {
        return [
            'id' => null,
            'rawmat_id' => null,
            'display_name' => '',
            'brand' => '',
            'category' => '',
            'gram_weight' => null,
            'size_p' => null,
            'size_l' => null,
            'query' => '',
            'is_primary' => $isPrimary,
        ];
    }

    public function removePart(int $index): void
    {
        if ($this->readOnly || count($this->parts) <= 1 || ! isset($this->parts[$index])) {
            return;
        }

        unset($this->parts[$index]);
        $this->parts = array_values($this->parts);
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
        $this->parts[$index]['dims_version'] = ($this->parts[$index]['dims_version'] ?? 0) + 1;

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

    public function addMaterialRow(int $partIndex): void
    {
        if ($this->readOnly || ! isset($this->parts[$partIndex])) {
            return;
        }

        $this->parts[$partIndex]['materials'][] = $this->blankMaterialRow(count($this->parts[$partIndex]['materials']) === 0);
    }

    public function removeMaterialRow(int $partIndex, int $matIndex): void
    {
        if ($this->readOnly || ! isset($this->parts[$partIndex]['materials'][$matIndex])) {
            return;
        }

        if (count($this->parts[$partIndex]['materials']) <= 1) {
            return;
        }

        $wasPrimary = $this->parts[$partIndex]['materials'][$matIndex]['is_primary'];

        unset($this->parts[$partIndex]['materials'][$matIndex]);
        $this->parts[$partIndex]['materials'] = array_values($this->parts[$partIndex]['materials']);

        if ($wasPrimary && ! empty($this->parts[$partIndex]['materials'])) {
            $this->parts[$partIndex]['materials'][0]['is_primary'] = true;
        }
    }

    public function setMaterialPrimary(int $partIndex, int $matIndex): void
    {
        if ($this->readOnly || ! isset($this->parts[$partIndex]['materials'][$matIndex])) {
            return;
        }

        foreach ($this->parts[$partIndex]['materials'] as $j => $row) {
            $this->parts[$partIndex]['materials'][$j]['is_primary'] = ($j === $matIndex);
        }
    }

    public function materialResults(int $partIndex, int $matIndex)
    {
        $row = $this->parts[$partIndex]['materials'][$matIndex] ?? null;

        if (! $row || $row['rawmat_id'] || mb_strlen($row['query']) < 2) {
            return collect();
        }

        return MatRaw::query()
            ->where('activate', true)
            ->where('stat_confirm', true)
            ->where(function ($q) use ($row) {
                $q->where('material_name', 'like', "%{$row['query']}%")
                    ->orWhere('material_code', 'like', "%{$row['query']}%");
            })
            ->orderBy('material_name')
            ->limit(8)
            ->get();
    }

    public function selectMaterial(int $partIndex, int $matIndex, int $rawmatId): void
    {
        if ($this->readOnly || ! isset($this->parts[$partIndex]['materials'][$matIndex])) {
            return;
        }

        $material = MatRaw::find($rawmatId);

        if (! $material) {
            return;
        }

        $this->parts[$partIndex]['materials'][$matIndex]['rawmat_id'] = $material->material_id;
        $this->parts[$partIndex]['materials'][$matIndex]['display_name'] = $material->material_name;
        $this->parts[$partIndex]['materials'][$matIndex]['brand'] = $material->brand ?? '';
        $this->parts[$partIndex]['materials'][$matIndex]['category'] = $material->category1 ?? '';
        $this->parts[$partIndex]['materials'][$matIndex]['gram_weight'] = $material->gram_weight;
        $this->parts[$partIndex]['materials'][$matIndex]['size_p'] = $material->cm_length;
        $this->parts[$partIndex]['materials'][$matIndex]['size_l'] = $material->cm_width;
        $this->parts[$partIndex]['materials'][$matIndex]['query'] = '';
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

            $this->persistPartMaterials($part, $index);
        }

        $this->inquiry->parts()->whereNotIn('id_part', $keepIds)->delete();
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
            if (! $row['rawmat_id'] && $row['display_name'] === '') {
                continue;
            }

            $data = [
                'layer_seq' => $matIndex + 1,
                'rawmat_id' => $row['rawmat_id'] ?: null,
                'rawmat_name' => $row['display_name'] !== '' ? $row['display_name'] : null,
                'gram_weight' => $row['gram_weight'] ?: null,
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
            'stat_confirm' => true,
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
    // (draft, kalau iqp_inquiry belum punya parent_product_id) + prod_part dari
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

        if (! $this->inquiry->parent_product_id) {
            $product = ProdProduct::create([
                'product_code' => 'DRAFT-'.$this->inquiry->id_inquiry,
                'product_name' => $this->inquiry->product_draft_name ?: $this->inquiry_no,
                'customer_id' => $this->inquiry->customer_id,
                'activate' => true,
                'created_by' => Auth::user()->login,
                'updated_by' => Auth::user()->login,
            ]);
            $this->inquiry->update(['parent_product_id' => $product->id_product]);
        } else {
            $this->inquiry->refresh();
        }

        if (! $part->prod_part_id) {
            $dims = $part->dieline_spec['dims'] ?? [];

            $prodPart = ProdPart::create([
                'product_id' => $this->inquiry->parent_product_id,
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
        <b>{{ $mode === 'NEW' ? '✨ Inkuiri Produk Baru' : ($mode === 'RECALC' ? '🔁 Hitung Ulang Harga' : '✏️ Ubah Spesifikasi') }}</b>
        <span class="pill {{ strtolower($mode) }}">{{ $mode }}</span>
        <span class="sub">{{ $inquiry_no ?: 'belum tersimpan' }} · {{ $sub_state }}</span>
        <a class="back" href="{{ route('inquiry.hub') }}">← Menu Utama</a>
    </header>

    <div class="wrap">

        @if ($flash)
            <div class="note g">✅ {{ $flash }}</div>
        @endif

        @if ($readOnly)
            <div class="note o">🔒 Inkuiri ini sudah dikonfirmasi (status <b>{{ $sub_state }}</b>) — dibuka lihat-saja, tidak bisa diedit.</div>
        @else
            <div class="note y">
                👤 <b>Sales Officer</b> — isi header, tier qty, part &amp; dieline, tooling, diskon, dan biaya
                eksternal di bawah. Costing (HPP) &amp; estimasi kecepatan akan menyusul di iterasi berikutnya.
            </div>
        @endif

        <div class="card">
            <div class="toolbar">
                <button type="button" class="btn p" wire:click="save" @if($readOnly) disabled @endif wire:loading.attr="disabled">
                    💾 Simpan
                </button>
                @unless($stat_confirm)
                    <button type="button" class="btn s" wire:click="confirm" wire:loading.attr="disabled">
                        ✅ Confirm
                    </button>
                @endunless
                @if($stat_confirm && ! $stat_cancel)
                    <button type="button" class="btn d" wire:click="openCancelForm">
                        ✖ Batalkan
                    </button>
                @endif
                @if($stat_confirm && ! in_array($sub_state, ['CANCELLED', 'CONVERTED', 'LOST'], true))
                    <button type="button" class="btn d" wire:click="openLostForm">
                        ❌ Tandai Kalah
                    </button>
                @endif
                <div style="margin-left:auto"></div>
                @if ($id)
                    <a href="{{ route('inquiry.imposition', ['inquiry' => $id]) }}" class="btn o xs" style="text-decoration:none">🔀 Imposition</a>
                    <a href="{{ route('inquiry.nota', ['inquiry' => $id]) }}" class="btn o xs" style="text-decoration:none">🖨 Cetak Nota</a>
                @endif
                <span class="pill {{ $this->statusPillClass() }}">{{ $sub_state }}</span>
            </div>

            @if ($showCancelForm)
                <div class="body" style="background:#fff4e5;border-bottom:1px solid #ffd9a0">
                    <div class="f" style="margin-bottom:8px">
                        <label>Alasan Pembatalan *</label>
                        <textarea wire:model="cancel_reason" rows="2" placeholder="Wajib diisi — tercatat di riwayat"></textarea>
                        @error('cancel_reason') <span class="hint" style="color:#e55353">{{ $message }}</span> @enderror
                    </div>
                    <div style="display:flex;gap:8px">
                        <button type="button" class="btn d xs" wire:click="cancel">Ya, batalkan inkuiri</button>
                        <button type="button" class="btn g xs" wire:click="dismissCancelForm">Batal</button>
                    </div>
                </div>
            @endif

            @if ($showLostForm)
                <div class="body" style="background:#fff4e5;border-bottom:1px solid #ffd9a0">
                    <div class="f" style="margin-bottom:8px">
                        <label>Alasan Kalah *</label>
                        <textarea wire:model="lost_reason" rows="2" placeholder="mis. Harga kompetitor lebih rendah, customer batal order — wajib diisi, tercatat di riwayat &amp; laporan win-rate"></textarea>
                        @error('lost_reason') <span class="hint" style="color:#e55353">{{ $message }}</span> @enderror
                    </div>
                    <div style="display:flex;gap:8px">
                        <button type="button" class="btn d xs" wire:click="markLost">Ya, tandai kalah</button>
                        <button type="button" class="btn g xs" wire:click="dismissLostForm">Batal</button>
                    </div>
                </div>
            @endif

            <div class="body">
                <div class="sec-title">Header Inkuiri</div>
                <div class="grid">
                    <x-form.input legacy label="No. Inkuiri (auto)" value="{{ $inquiry_no ?: 'belum tersimpan' }}" readonly />
                    <x-form.date-picker legacy label="Tanggal" wire:model="inquiry_date" :disabled="$readOnly" name="inquiry_date" />
                    <x-form.select legacy label="Sumber" wire:model="source" x-data x-effect="$el.value = @js($source)" :disabled="$readOnly" :placeholder="false"
                        :selected="$source" :options="['EMAIL' => 'EMAIL', 'WA' => 'WA', 'PHONE' => 'PHONE', 'VISIT' => 'VISIT', 'MARKETPLACE' => 'MARKETPLACE']" />
                    <x-form.input legacy label="PIC Sales" wire:model="pic_sales" placeholder="Nama PIC Sales" :disabled="$readOnly" />

                    <x-form.toggle-group legacy col2 label="Prospek?">
                        <x-form.toggle-option legacy :active="! $is_prospect" style="flex:1" wire:click="setProspect(false)" :disabled="$readOnly">Tidak — customer terdaftar</x-form.toggle-option>
                        <x-form.toggle-option legacy :active="$is_prospect" style="flex:1" wire:click="setProspect(true)" :disabled="$readOnly">Ya — belum terdaftar</x-form.toggle-option>
                    </x-form.toggle-group>
                    <x-form.date-picker legacy col2 wire:model="deadline_quotation" :disabled="$readOnly" name="deadline_quotation">
                        <x-slot:label>Deadline Quotation <span class="hint" style="font-weight:400">(min. +14 hari kerja)</span></x-slot:label>
                    </x-form.date-picker>

                    @if ($is_prospect)
                        <x-form.input legacy col2 label="Nama Calon Customer" wire:model="prospect_name" placeholder="Nama prospek…" :disabled="$readOnly" name="prospect_name" />
                    @elseif ($customer_id)
                        <div class="f col2">
                            <label>Customer</label>
                            <div style="display:flex;gap:6px;align-items:center">
                                <input type="text" value="{{ $customer_name }}" readonly class="cust-ok">
                                @unless($readOnly)
                                    <button type="button" class="btn g xs" wire:click="clearCustomer">Ganti</button>
                                @endunless
                            </div>
                        </div>
                    @else
                        <div class="f col2 ac-wrap">
                            <label>Customer / Calon Customer</label>
                            <input type="text" wire:model.live.debounce.400ms="custQuery" autocomplete="off"
                                   placeholder="Ketik nama / kode customer…" @if($readOnly) disabled @endif>
                            @error('customer_id') <span class="hint" style="color:#e55353">{{ $message }}</span> @enderror
                            @if ($custQuery && mb_strlen($custQuery) >= 2)
                                <div class="ac-box on">
                                    @forelse ($this->customerResults as $c)
                                        <div class="ac-i" wire:click="selectCustomer({{ $c->id_customer }})">
                                            <b>{!! $this->highlightMatch($c->customer_name, $custQuery) !!}</b>
                                            <span class="cd">{{ $c->customer_code }}</span>
                                        </div>
                                    @empty
                                        <div class="ac-none">Tidak ditemukan.</div>
                                    @endforelse
                                </div>
                            @endif
                        </div>
                    @endif

                    @php $productDraftHint = 'Nama sementara selama quotation. Karakter khusus (/@#%"\';) tidak diperbolehkan.'; @endphp
                    <x-form.input legacy col2 label="Nama Produk (draft)" wire:model="product_draft_name" maxlength="150"
                        placeholder="mis. Box Kosmetik Serum 30ml" :disabled="$readOnly" name="product_draft_name"
                        :hint="$productDraftHint" />
                    <x-form.textarea legacy col2 label="Catatan Sales" wire:model="sales_note" rows="2" placeholder="opsional" :disabled="$readOnly" />
                </div>
            </div>
        </div>

        <div class="card">
            <h3>Sifat Order &amp; Pajak <span style="font-weight:400;color:#889">(menentukan struktur biaya &amp; pajak)</span></h3>
            <div class="body">
                <div class="grid">
                    <x-form.toggle-group legacy label="Sumber Bahan" :hint="$material_source === 'OWN' ? 'Bahan dari perusahaan → order bersifat PRODUCT.' : 'Bahan dari customer → order bersifat JASA.'">
                        <x-form.toggle-option legacy :active="$material_source === 'OWN'" style="flex:1" wire:click="setMaterialSource('OWN')" :disabled="$readOnly">Perusahaan (OWN)</x-form.toggle-option>
                        <x-form.toggle-option legacy :active="$material_source === 'CUSTOMER'" style="flex:1" wire:click="setMaterialSource('CUSTOMER')" :disabled="$readOnly">Customer sediakan</x-form.toggle-option>
                    </x-form.toggle-group>
                    <x-form.input legacy label="Jenis Pekerjaan" value="{{ $this->jobNature() }}" readonly hint="Otomatis dari sumber bahan (PRODUCT / JASA)." />
                    <x-form.toggle-group legacy label="Skema PPN">
                        <x-form.toggle-option legacy :active="$tax_scheme === 'PPN'" style="flex:1" wire:click="setTaxScheme('PPN')" :disabled="$readOnly">PPN</x-form.toggle-option>
                        <x-form.toggle-option legacy :active="$tax_scheme === 'NON_PPN'" style="flex:1" wire:click="setTaxScheme('NON_PPN')" :disabled="$readOnly">NON_PPN</x-form.toggle-option>
                    </x-form.toggle-group>
                    <x-form.toggle-group legacy label="PPh23 (jasa)" hint="Hanya relevan bila Jenis Pekerjaan = JASA.">
                        <x-form.toggle-option legacy :active="! $pph_applicable" style="flex:1" wire:click="setPph(false)" :disabled="$readOnly || $this->jobNature() !== 'JASA'">Tidak</x-form.toggle-option>
                        <x-form.toggle-option legacy :active="$pph_applicable" style="flex:1" wire:click="setPph(true)" :disabled="$readOnly || $this->jobNature() !== 'JASA'">Ya</x-form.toggle-option>
                    </x-form.toggle-group>
                </div>

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

        <div class="card">
            <h3>Qty Order Berjenjang <span style="font-weight:400;color:#889">(Costing hitung HPP per tier — tepat 1 UTAMA)</span></h3>
            <div class="body">
                @error('tiers')
                    <div class="note o">{{ $message }}</div>
                @enderror
                <table>
                    <thead>
                        <tr>
                            <th style="width:44px">Seq</th>
                            <th>Qty (pcs)</th>
                            <th style="width:120px">Utama</th>
                            <th style="width:190px">Diskon Item</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tiers as $i => $tier)
                            <tr wire:key="tier-row-{{ $tier['id'] ?? 'new-'.$i }}">
                                <td>{{ $i + 1 }}</td>
                                <td>
                                    <input type="number" step="1" min="0" wire:model.blur="tiers.{{ $i }}.qty"
                                           placeholder="mis. 10000" @if($readOnly) disabled @endif>
                                </td>
                                <td style="text-align:center">
                                    <button type="button" class="btn xs {{ $tier['is_primary'] ? 'p' : 'g' }}"
                                            wire:click="setPrimaryTier({{ $i }})" @if($readOnly) disabled @endif>
                                        {{ $tier['is_primary'] ? '✓ Utama' : 'Jadikan Utama' }}
                                    </button>
                                </td>
                                <td>
                                    <div style="display:flex;gap:3px;align-items:center;flex-wrap:wrap">
                                        <button type="button" class="btn xs {{ $tier['discount_item_type'] === '' ? 'p' : 'g' }}"
                                                wire:click="setTierDiscountType({{ $i }}, '')" @if($readOnly) disabled @endif>—</button>
                                        <button type="button" class="btn xs {{ $tier['discount_item_type'] === 'PERCENT' ? 'p' : 'g' }}"
                                                wire:click="setTierDiscountType({{ $i }}, 'PERCENT')" @if($readOnly) disabled @endif>%</button>
                                        <button type="button" class="btn xs {{ $tier['discount_item_type'] === 'AMOUNT' ? 'p' : 'g' }}"
                                                wire:click="setTierDiscountType({{ $i }}, 'AMOUNT')" @if($readOnly) disabled @endif>Rp</button>
                                        @if ($tier['discount_item_type'])
                                            <input type="number" step="0.01" min="0" wire:model.blur="tiers.{{ $i }}.discount_item_value"
                                                   placeholder="0" style="width:80px" @if($readOnly) disabled @endif>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <button type="button" class="btn d xs" wire:click="removeTier({{ $i }})"
                                            @if($readOnly || count($tiers) <= 1) disabled @endif>✕</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
                                    <input type="text" wire:model.blur="parts.{{ $i }}.part_name" placeholder="mis. Body" @if($readOnly) disabled @endif>
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
                            </div>

                            @php($typeOptions = $this->productTypeOptions)
                            @php($selectedType = $typeOptions[$part['type_code']] ?? null)
                            <div class="flx"
                                 wire:key="dieline-{{ $i }}-{{ $part['dims_version'] ?? 0 }}"
                                 x-data="dielinePart(@js($typeOptions), @js($this->partAlpineInit($i)))"
                                 data-index="{{ $i }}">
                                <div style="flex:1">
                                    <div class="ptype-box">
                                        <div style="flex:1">
                                            <label>Tipe Produk</label>
                                            <div style="display:flex;gap:6px;align-items:center">
                                                <select x-ref="typeSelect" wire:change="selectPartType({{ $i }}, $event.target.value)" @if($readOnly) disabled @endif style="flex:1">
                                                    <option value="">— pilih tipe produk —</option>
                                                    @foreach ($typeOptions as $code => $t)
                                                        <option value="{{ $code }}" @selected($part['type_code'] === $code)>{{ $t['name'] }}</option>
                                                    @endforeach
                                                </select>
                                                {{-- Popup picker visual (TAMBAHAN, bukan pengganti dropdown di atas --
                                                permintaan user 2026-09-03: sales baru gampang kenali tipe produk dari
                                                gambar, ala Pacdora. Reuse TMDieline.svg() yang sudah ada (dieline-part.js),
                                                bukan aset gambar baru -- thumbnail = dieline asli tipe itu, bukan generik. --}}
                                                @unless($readOnly)
                                                <button type="button" @click="pickerOpen = true" title="Pilih visual (gambar)"
                                                    style="flex-shrink:0;width:34px;height:34px;border:1px solid #c7cfda;border-radius:6px;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:15px">🖼️</button>
                                                @endunless
                                            </div>
                                        </div>
                                        @if ($selectedType && ! empty($selectedType['sizes']))
                                            <div class="f size-pick" style="width:220px">
                                                <label>⭐ Ukuran Pasaran</label>
                                                <select wire:change="applySizePreset({{ $i }}, $event.target.value)" @if($readOnly) disabled @endif>
                                                    <option value="">— pilih ukuran umum —</option>
                                                    @foreach ($selectedType['sizes'] as $size)
                                                        <option value="{{ $size['id'] }}">{{ $size['label'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Modal picker visual (gambar dieline per tipe) -- overlay, tidak
                                    mengubah layout field manapun di bawah. Pilih kartu = SAMA PERSIS
                                    hasilnya dgn pilih dari <select> teks di atas (pickType() cuma
                                    trigger event 'change' di select yg sama), field dimensi tetap
                                    full-editable sesudahnya, tidak ada yg terkunci. --}}
                                    <div x-show="pickerOpen" x-cloak @keydown.escape.window="pickerOpen = false"
                                        style="position:fixed;inset:0;z-index:200;background:rgba(15,22,45,.45);display:flex;align-items:center;justify-content:center;padding:24px"
                                        @click.self="pickerOpen = false">
                                        <div style="background:#fff;border-radius:12px;width:100%;max-width:820px;max-height:80vh;display:flex;flex-direction:column;overflow:hidden">
                                            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #e3e7f0">
                                                <b style="font-size:14px;color:#2c3e69">Pilih Tipe Produk (Visual)</b>
                                                <button type="button" @click="pickerOpen = false" style="background:none;border:none;font-size:20px;cursor:pointer;color:#8a94a6;line-height:1">&times;</button>
                                            </div>
                                            <div style="overflow-y:auto;padding:16px;display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px">
                                                <template x-for="item in typeList" :key="item.code">
                                                    <button type="button" @click="pickType(item.code)"
                                                        :style="typeCode === item.code ? 'border-color:#2c7be5;background:#eef3fb' : 'border-color:#e3e7f0;background:#fff'"
                                                        style="border-width:2px;border-style:solid;border-radius:8px;padding:8px;cursor:pointer;text-align:center;display:flex;flex-direction:column;gap:6px;align-items:center">
                                                        <div style="width:100%;height:90px;background:#fbfcfd;border:1px solid #eef0f3;border-radius:6px;display:flex;align-items:center;justify-content:center;overflow:hidden;padding:4px" x-html="item.svg || '<span style=\'font-size:22px;color:#c7cfda\'>&#128196;</span>'"></div>
                                                        <span style="font-size:11px;color:#2c3e69;font-weight:600;line-height:1.25" x-text="item.name"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </div>

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
                                    <div class="hint" style="margin-bottom:6px">Pilih dari master bahan baku — brand &amp; jenis bahan otomatis terisi.</div>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th style="width:36px">Lyr</th>
                                                <th>Bahan (ketik untuk cari)</th>
                                                <th style="width:100px">Brand</th>
                                                <th style="width:120px">Jenis Bahan</th>
                                                <th style="width:80px">Utama</th>
                                                <th style="width:36px"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($part['materials'] as $j => $mat)
                                                <tr wire:key="mat-{{ $i }}-{{ $mat['id'] ?? 'new-'.$j }}">
                                                    <td>{{ $j + 1 }}</td>
                                                    <td>
                                                        @if ($mat['rawmat_id'])
                                                            <div style="display:flex;gap:6px;align-items:center">
                                                                <input type="text" value="{{ $mat['display_name'] }}" readonly class="cust-ok">
                                                                @unless($readOnly)
                                                                    <button type="button" class="btn g xs" wire:click="clearMaterialRow({{ $i }}, {{ $j }})">Ganti</button>
                                                                @endunless
                                                            </div>
                                                        @else
                                                            <div class="ac-wrap">
                                                                <input type="text" wire:model.live.debounce.400ms="parts.{{ $i }}.materials.{{ $j }}.query"
                                                                       autocomplete="off" placeholder="ketik kode/nama bahan…" @if($readOnly) disabled @endif>
                                                                @if (mb_strlen($mat['query'] ?? '') >= 2)
                                                                    <div class="ac-box on">
                                                                        @forelse ($this->materialResults($i, $j) as $m)
                                                                            <div class="ac-i" wire:click="selectMaterial({{ $i }}, {{ $j }}, {{ $m->material_id }})">
                                                                                <b>{!! $this->highlightMatch($m->material_name, $mat['query']) !!}</b>
                                                                                <span class="cd">{{ $m->material_code }}</span>
                                                                            </div>
                                                                        @empty
                                                                            <div class="ac-none">Bahan tak ditemukan.</div>
                                                                        @endforelse
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td><input type="text" value="{{ $mat['brand'] }}" readonly placeholder="—" style="background:#f7f8fa" title="Brand dari master bahan"></td>
                                                    <td><input type="text" value="{{ $mat['category'] }}" readonly placeholder="—" style="background:#f7f8fa" title="Jenis bahan dari master"></td>
                                                    <td style="text-align:center">
                                                        <button type="button" class="btn xs {{ $mat['is_primary'] ? 'p' : 'g' }}"
                                                                wire:click="setMaterialPrimary({{ $i }}, {{ $j }})" @if($readOnly) disabled @endif>
                                                            {{ $mat['is_primary'] ? '✓' : '—' }}
                                                        </button>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn d xs" wire:click="removeMaterialRow({{ $i }}, {{ $j }})"
                                                                @if($readOnly || count($part['materials']) <= 1) disabled @endif>✕</button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    <button type="button" class="btn g xs" style="margin-top:6px" wire:click="addMaterialRow({{ $i }})" @if($readOnly) disabled @endif>
                                        ＋ Tambah Bahan
                                    </button>

                                    <div class="sec-title">Cetak / Warna</div>
                                    <div class="grid">
                                        <div class="f">
                                            <label>Notasi Warna</label>
                                            <select wire:model="parts.{{ $i }}.cfg_color" x-data x-effect="$el.value = @js($part['cfg_color'])" @if($readOnly) disabled @endif>
                                                @foreach (['4/4', '4/1', '4/0', '2/0', '1/1', '1/0'] as $opt)
                                                    <option value="{{ $opt }}" @selected($part['cfg_color'] === $opt)>{{ $opt }}</option>
                                                @endforeach
                                            </select>
                                            <div class="hint">Format sisiA/sisiB (4/4, 4/0, 4/1)</div>
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
                                        <div class="f col2">
                                            <label>Catatan Warna (Pantone)</label>
                                            <input type="text" wire:model.blur="parts.{{ $i }}.cfg_ink"
                                                   placeholder="mis. Pantone 485 C" @if($readOnly) disabled @endif>
                                        </div>
                                    </div>
                                </div>

                                <div style="width:360px;flex-shrink:0">
                                    <label style="font-size:11px;color:#667;font-weight:600">Preview Dieline (live)</label>
                                    <div class="dcut" x-ref="preview"></div>
                                    <div class="legend">🔴 potong &nbsp; 🟢 lipat &nbsp; ⚪ overflow &nbsp; 🔵 lubang/jilid</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                <button type="button" class="btn g" wire:click="addPart" @if($readOnly) disabled @endif>
                    ＋ Tambah Part
                </button>
            </div>
        </div>

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

        <div class="card">
            <h3>Diskon Dokumen <span style="font-weight:400;color:#889">(level dokumen — dibawa ke Customer Order)</span></h3>
            <div class="body">
                <div class="grid">
                    <div class="f">
                        <label>Diskon Dokumen</label>
                        <div style="display:flex;gap:6px">
                            <button type="button" class="btn xs {{ $discount_doc_type === '' ? 'p' : 'g' }}" style="flex:1"
                                    wire:click="setDiscountDocType('')" @if($readOnly) disabled @endif>—</button>
                            <button type="button" class="btn xs {{ $discount_doc_type === 'PERCENT' ? 'p' : 'g' }}" style="flex:1"
                                    wire:click="setDiscountDocType('PERCENT')" @if($readOnly) disabled @endif>%</button>
                            <button type="button" class="btn xs {{ $discount_doc_type === 'AMOUNT' ? 'p' : 'g' }}" style="flex:1"
                                    wire:click="setDiscountDocType('AMOUNT')" @if($readOnly) disabled @endif>Rp</button>
                        </div>
                    </div>
                    @if ($discount_doc_type)
                        <div class="f">
                            <label>Nilai Diskon {{ $discount_doc_type === 'PERCENT' ? '(%)' : '(Rp)' }}</label>
                            <input type="number" step="0.01" min="0" wire:model.blur="discount_doc_value" @if($readOnly) disabled @endif>
                            @error('discount_doc_value') <span class="hint" style="color:#e55353">{{ $message }}</span> @enderror
                        </div>
                    @endif
                    <div class="f">
                        <label>Diskon Qty (%) <span style="font-weight:400;color:#889">(occasional)</span></label>
                        <input type="number" step="0.01" min="0" wire:model.blur="discount_qty_value" placeholder="mis. 3" @if($readOnly) disabled @endif>
                    </div>
                    <div class="f">
                        <label>Ambang Tonase (ton)</label>
                        <input type="number" step="0.01" min="0" wire:model.blur="discount_qty_min_ton" placeholder="mis. 5" @if($readOnly) disabled @endif>
                        <div class="hint">Diskon qty aktif otomatis saat tonase total ≥ ambang ini (aturan detail menyusul).</div>
                    </div>
                </div>
            </div>
        </div>

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
