<?php

use App\Models\Master\MatCoatingRate;
use App\Models\Master\MatGrammage;
use App\Models\Master\MatSheetRate;
use App\Models\Master\ProdLayoutGroup;
use App\Models\Master\ProdPartProcess;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpCostingPart;
use App\Models\Sdi\IqpCostingPartCoating;
use App\Models\Sdi\IqpCostingPriceTier;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpLayout;
use App\Models\Sdi\IqpPart;
use App\Services\Costing\CostingEngine;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Costing (HPP) — TELASIH MAKMUR ERP')] class extends Component
{
    public ?int $inquiryId = null;

    public int $costingId;

    public string $engineError = '';

    // Cross-nav read-only (wizard-bar, user 2026-09-04): "harus bisa klik
    // Costing dari Inquiry/ProDev, lihat status/harga, TIDAK bisa mengubah;
    // kalau belum diproses tulis 'belum diproses', kalau sudah confirm
    // (published) baru kelihatan harganya." Beda dari entry primer via grid
    // Costing (inquiry.costing.list) yang tetap full engine spt biasa.
    #[Url]
    public bool $view = false;

    // 2 rute -> 1 komponen (keputusan user 2026-09-18): {inquiry} = mode
    // INTEGRATED (perilaku lama, tak berubah), {costing} = mode INSTANT
    // (kalkulator standalone, langsung by costing_id tanpa inquiry). Template
    // bercabang total sesuai $this->costing->isIntegrated()/isInstant() --
    // lihat docs/inquiry_product_costing/procost_formula_library.md §I.
    public function mount(?int $inquiry = null, ?int $costing = null): void
    {
        if ($costing !== null) {
            $row = IqpCosting::where('mode', IqpCosting::MODE_INSTANT)->findOrFail($costing);
            $this->costingId = $row->id_costing;
            $this->inquiryId = null;

            // Instant tak punya iqp_tier sumber -- kasih 1 tier default supaya
            // Ladder Harga langsung ada baris utk diisi, bukan kosong melompong.
            if ($row->priceTiers()->count() === 0) {
                IqpCostingPriceTier::create([
                    'costing_id' => $row->id_costing,
                    'qty_pcs' => 1000,
                    'is_primary' => true,
                    'created_by' => Auth::user()->login,
                    'updated_by' => Auth::user()->login,
                ]);
            }

            return;
        }

        $this->inquiryId = $inquiry;

        if ($this->view) {
            // JANGAN firstOrCreate -- cuma numpang lihat, tidak boleh bikin
            // draft costing baru sbg side-effect dari navigasi pasif.
            $this->costingId = (int) (IqpCosting::where('inquiry_id', $inquiry)->value('id_costing') ?? 0);

            return;
        }

        $costing = IqpCosting::firstOrCreate(
            ['inquiry_id' => $inquiry],
            [
                'calc_type' => 'NEW',
                'doc_status' => 'DRAFT',
                'created_by' => Auth::user()->login,
                'updated_by' => Auth::user()->login,
            ]
        );

        // Auto-fill dimensi dari inquiry (sekali, saat header masih kosong).
        if (! $costing->i_dim_panjang_mm) {
            $firstPart = IqpInquiry::find($inquiry)?->parts()->orderBy('part_seq')->first();
            if ($firstPart) {
                $costing->fill([
                    'i_dim_panjang_mm' => $firstPart->flat_w,
                    'i_dim_lebar_mm' => $firstPart->flat_h,
                ])->save();
            }
        }

        // Price tier auto-generate dari iqp_tier (1:1 per qty tier), kalau belum ada.
        if ($costing->priceTiers()->count() === 0) {
            foreach (IqpInquiry::find($inquiry)?->tiers ?? [] as $tier) {
                IqpCostingPriceTier::create([
                    'costing_id' => $costing->id_costing,
                    'tier_id' => $tier->id_tier,
                    'qty_pcs' => $tier->qty,
                    'is_primary' => $tier->is_primary,
                    'created_by' => Auth::user()->login,
                    'updated_by' => Auth::user()->login,
                ]);
            }
        }

        $this->costingId = $costing->id_costing;
    }

    #[Computed]
    public function costing(): ?IqpCosting
    {
        if (! $this->costingId) {
            return null; // hanya kejadian di mode cross-nav read-only ($view=true) tanpa costing sama sekali
        }

        return IqpCosting::with(['parts.coatings', 'priceTiers', 'inquiry'])->findOrFail($this->costingId);
    }

    #[Computed]
    public function grammages()
    {
        return MatGrammage::where('activate', true)->orderBy('grammage_name')->get();
    }

    #[Computed]
    public function coatingRates()
    {
        return MatCoatingRate::where('activate', true)->orderBy('coating_type')->get();
    }

    #[Computed]
    public function sheetRates()
    {
        return MatSheetRate::where('activate', true)->orderBy('substance_name')->orderBy('flute_type')->get();
    }

    public function addPart(string $partType = 'DC'): void
    {
        $costing = $this->costing;

        $data = [
            'costing_id' => $costing->id_costing,
            'no_urut' => $costing->parts()->count() + 1,
            'part_name' => '',
            'part_type_code' => in_array($partType, ['DC', 'SHEET_DIGITAL'], true) ? $partType : 'DC',
            'qty_per_set' => 1,
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ];

        if ($data['part_type_code'] === 'DC') {
            $data['i_cut_per_plano'] = 1;
            $data['i_isi_per_lb'] = 1;
            $data['i_jumlah_warna'] = 4;
        } else {
            $data['i_isi_per_lb_kerja'] = 1;
            $data['supplier_sheet_code'] = 'UJK';
        }

        IqpCostingPart::create($data);

        unset($this->costing);
    }

    // Tambah part dari iqp_part (Inquiry) — auto-fill plano/cut dari Imposition
    // yang sudah confirmed, kalau ada (lihat inquiry/imposition.blade.php confirm()).
    public function addPartFromInquiry(int $inquiryPartId): void
    {
        $costing = $this->costing;
        $iqpPart = IqpPart::where('inquiry_id', $this->inquiryId)->findOrFail($inquiryPartId);
        $layout = IqpLayout::where('part_id', $iqpPart->id_part)->where('stat_confirm', true)->first();

        $data = [
            'costing_id' => $costing->id_costing,
            'part_id' => $iqpPart->id_part,
            'no_urut' => $costing->parts()->count() + 1,
            'part_name' => $iqpPart->part_name,
            'part_type_code' => 'DC',
            'qty_per_set' => $iqpPart->qty_per_sku ?: 1,
            'i_cut_per_plano' => $layout->up_count ?? 1,
            'i_isi_per_lb' => 1,
            'i_jumlah_warna' => 4,
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ];

        if ($layout) {
            $data['i_panjang_plano_cm'] = round($layout->plano_w_mm / 10, 4);
            $data['i_lebar_plano_cm'] = round($layout->plano_h_mm / 10, 4);
        }

        IqpCostingPart::create($data);

        unset($this->costing);
    }

    #[Computed]
    public function inquiryParts()
    {
        if (! $this->inquiryId) {
            return collect(); // mode INSTANT -- tidak ada inquiry sumber part
        }

        return IqpPart::where('inquiry_id', $this->inquiryId)->orderBy('part_seq')->get();
    }

    // ============ MODE INSTANT (standalone, keputusan user 2026-09-18) ============
    #[Computed]
    public function customers()
    {
        return \App\Models\Master\CustCustomer::where('activate', true)->orderBy('customer_name')->limit(300)->get();
    }

    public function updateHeaderField(string $field, $value): void
    {
        if (! $this->costing->isInstant()) {
            return; // header Integrated read-only, snapshot dari inquiry
        }

        $allowed = ['customer_id', 'product_name_draft', 'i_dim_panjang_mm', 'i_dim_lebar_mm', 'i_dim_tinggi_mm', 'ppn_mode', 'ppn_pct'];
        if (! in_array($field, $allowed, true)) {
            return;
        }

        IqpCosting::whereKey($this->costingId)->update([$field => $value === '' ? null : $value]);
        unset($this->costing);
    }

    // Instant tak punya iqp_tier sumber -- tier qty diisi manual officer.
    public function addTier(): void
    {
        if (! $this->costing->isInstant()) {
            return;
        }

        IqpCostingPriceTier::create([
            'costing_id' => $this->costingId,
            'qty_pcs' => 1000,
            'is_primary' => $this->costing->priceTiers()->count() === 0,
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ]);

        unset($this->costing);
    }

    public function removeTier(int $tierId): void
    {
        if (! $this->costing->isInstant()) {
            return;
        }

        IqpCostingPriceTier::where('costing_id', $this->costingId)->whereKey($tierId)->delete();
        unset($this->costing);
    }

    // STUB (2026-09-18): tombol Reverse butuh keputusan desain lebih lanjut
    // (prod_part/prod_layout_group wajib py product_id NOT NULL -- Reverse juga
    // harus bikin Master Product baru, lihat procost_formula_library.md §V &
    // diskusi sesi ini) -- BELUM diimplementasikan, sengaja cuma flash pesan.
    public function reverseToIntegrated(): void
    {
        session()->flash('error', 'Fitur Reverse belum aktif -- menunggu konfirmasi desain (Master Product auto-generate).');
    }

    // docs/inquiry_product_costing/productdevelopment_tier1.md §5.1 -- daftar
    // Master Card/layout yang SUDAH dikonfirmasi ProDev, siap ditarik officer
    // Costing (mode "Ambil Data"). Lintas-DB (tm2mst), query terpisah -- CLAUDE.md §2 pola 1.
    #[Computed]
    public function availableLayoutGroups()
    {
        return ProdLayoutGroup::with(['product', 'parts'])->where('status', 'CONFIRMED')->orderByDesc('id_layout_group')->limit(100)->get();
    }

    // BACKLOG.md §3 "ProCost belum konsumsi urutan proses" -- urutan proses
    // (prod_part_process, tm2mst, diisi officer ProDev lewat prodev.blade.php
    // ::addProcess(), SUDAH hidup sejak 2026-09-02) kini jadi masukan parameter
    // Costing, TAPI bukan auto-hitung angka: prod_process/prod_part_process
    // TIDAK punya kolom rate Rupiah sama sekali (satu-satunya "rate" yg ada,
    // process-rate.blade.php::target_qty_per_hour, itu KECEPATAN produksi utk
    // WO Generation, besaran beda total, bukan biaya) -- menebak angka HPP dari
    // situ salah & berbahaya (CLAUDE.md §4 "Costing rahasia"). Konsumsi yang
    // jujur & aman: validasi silang 2 arah terhadap 3 field ongkos manual DC
    // (cetak/plong/lem) -- "engine suggests (warning), officer confirms (isi)",
    // pola yang sama dipakai di seluruh fitur baru sesi ini. Scope DC saja:
    // proses SHEET_DIGITAL/KRAFT (diecut/slitter/slotter/join/partisi/layer/
    // packing/kirim) TIDAK punya padanan nama di master prod_process saat ini
    // (isinya migrasi offset lama: CETAK/PLONG/LEM MESIN/POTONG/UV/dst) --
    // jangan dipaksa map, itu cuma tebakan.
    protected const DC_PROCESS_FIELD_MAP = [
        'CETAK' => ['field' => 'i_ongkos_cetak_rim', 'label' => 'Ongkos Cetak /rim'],
        'PLONG' => ['field' => 'i_ongkos_plong_lb', 'label' => 'Ongkos Plong /lb'],
        'LEM MESIN' => ['field' => 'i_ongkos_lem_pc', 'label' => 'Ongkos Lem /pc'],
    ];

    // Routing per prodev_part_id, di-load sekali per request (bukan per-part
    // N+1) -- dikelompokkan supaya partRoutingLabel()/routingWarningsFor() di
    // Blade tinggal ambil dari cache computed ini.
    #[Computed]
    public function partRouting()
    {
        $prodevPartIds = $this->costing?->parts->pluck('prodev_part_id')->filter()->unique()->values() ?? collect();

        if ($prodevPartIds->isEmpty()) {
            return collect();
        }

        return ProdPartProcess::whereIn('part_id', $prodevPartIds)
            ->with('process')
            ->orderBy('step_seq')
            ->get()
            ->groupBy('part_id');
    }

    public function routingNamesFor(IqpCostingPart $part)
    {
        if (! $part->prodev_part_id) {
            return collect();
        }

        return ($this->partRouting[$part->prodev_part_id] ?? collect())
            ->pluck('process.process_name')
            ->filter()
            ->map(fn ($name) => strtoupper(trim($name)))
            ->unique()
            ->values();
    }

    public function routingWarningsFor(IqpCostingPart $part): array
    {
        if ($part->part_type_code !== 'DC' || ! $part->prodev_part_id) {
            return [];
        }

        $routingNames = $this->routingNamesFor($part);

        if ($routingNames->isEmpty()) {
            return [];
        }

        $warnings = [];

        foreach (self::DC_PROCESS_FIELD_MAP as $processName => $meta) {
            $inRouting = $routingNames->contains($processName);
            $filled = (float) $part->{$meta['field']} > 0;

            if ($inRouting && ! $filled) {
                $warnings[] = "Proses {$processName} ada di routing ProDev tapi \"{$meta['label']}\" masih 0 — isi dulu.";
            } elseif (! $inRouting && $filled) {
                $warnings[] = "\"{$meta['label']}\" terisi tapi proses {$processName} tidak ada di routing ProDev — cek kembali, mungkin sisa data lama.";
            }
        }

        return $warnings;
    }

    // Instruksi user 2026-09-03: sembunyikan FORMULA/breakdown HPP (bukan field
    // input, bukan harga jual final -- itu tetap kelihatan spy staf Costing tetap
    // bisa kerja normal atur margin/toleransi) dari siapa pun KECUALI login
    // literal 'johiwan'. LEBIH KETAT dari aturan lama CLAUDE.md §4 "Costing
    // rahasia Sisi A" (grup Costing+Direksi umum) -- ini 1 login spesifik,
    // sesuai instruksi user, JANGAN dilonggarkan sendiri ke is_admin/grup.
    #[Computed]
    public function canSeeFormula(): bool
    {
        return Auth::user()->login === 'johiwan';
    }

    // Toggle Manual <-> Ambil Data per part (keputusan user: per-part, bukan
    // per-costing -- tier1 §7 poin 4). Balik ke MANUAL cuma lepas link, TIDAK
    // menghapus nilai i_panjang_plano_cm/dst yang sudah ke-auto-fill (officer
    // tetap bisa lanjut edit manual dari titik itu).
    public function toggleDataSource(int $partId): void
    {
        $part = IqpCostingPart::where('costing_id', $this->costingId)->findOrFail($partId);

        if ($part->data_source === 'PRODEV') {
            $part->update(['data_source' => 'MANUAL', 'prod_layout_group_id' => null]);
        } else {
            $part->update(['data_source' => 'PRODEV']);
        }

        unset($this->costing);
    }

    // Tarik plano/UP dari prod_layout_group terpilih -> auto-fill + langsung
    // hitung ulang HPP (tujuan eksplisit user: 1 officer, tanpa langkah "Hitung"
    // terpisah lagi setelah pilih layout -- tier1 §5.1). $prodPartId WAJIB diisi
    // kalau group menaungi >1 part (Tool #10, BACKLOG.md §0) -- dipakai
    // ProdLayoutGroup::effectiveCutFor() utk alokasi biaya proporsional by luas
    // (§4.2b), BUKAN qty_up_in_group mentah (itu akan melipatgandakan biaya
    // kertas keliru kalau tiap part yg berbagi 1 plano dihitung seolah dpt
    // plano sendiri-sendiri).
    public function pickLayoutGroup(int $partId, int $groupId, ?int $prodPartId = null): void
    {
        $part = IqpCostingPart::where('costing_id', $this->costingId)->findOrFail($partId);
        $group = ProdLayoutGroup::with('parts')->find($groupId);

        if (! $group) {
            return;
        }

        $isMulti = $group->parts->count() > 1;
        // Group SOLO: aman auto-pakai satu-satunya part (perilaku lama, tak ada
        // ambiguitas). Group MULTI: JANGAN fallback ke first() -- kalau
        // prodPartId tak diisi eksplisit, biarkan null supaya guard di bawah
        // menolak (alokasi proporsional wajib tau part mana, tak boleh nebak).
        $prodPart = $prodPartId
            ? $group->parts->firstWhere('id_part', $prodPartId)
            : ($isMulti ? null : $group->parts->first());

        if ($isMulti && ! $prodPart) {
            session()->flash('error', 'Layout ini menaungi lebih dari 1 part (Tool #10) -- pilih part yang direpresentasikan baris costing ini dulu.');

            return;
        }

        $effectiveCut = $prodPart ? $group->effectiveCutFor($prodPart) : ($group->up_count ?: 1);

        // Tool #4 (die_rule_meter) -- SOLO group: langsung dipakai penuh (cuma 1
        // part pemilik plano). Group MULTI-part: dialokasi proporsional by
        // share luas yang SAMA dgn effectiveCutFor() (null kalau belum pernah
        // dihitung, mis. hasil Tool #10 COMBINED_SPLIT -- lihat catatan di
        // prodev.blade.php::confirmCombine(), TIDAK ditebak/dipaksa ada).
        $pisauFaktor = $part->i_pisau_faktor;
        if ($group->die_rule_meter !== null) {
            $ownUpInGroup = $prodPart?->pivot->qty_up_in_group ?? $group->up_count;
            $pisauFaktor = $isMulti && $effectiveCut > 0
                ? round((float) $group->die_rule_meter * $ownUpInGroup / $effectiveCut, 4)
                : (float) $group->die_rule_meter;
        }

        $part->update([
            'data_source' => 'PRODEV',
            'prod_layout_group_id' => $group->id_layout_group,
            'prodev_part_id' => $prodPart?->id_part,
            'i_panjang_plano_cm' => round($group->plano_w_mm / 10, 4),
            'i_lebar_plano_cm' => round($group->plano_h_mm / 10, 4),
            'i_cut_per_plano' => $effectiveCut ?: 1,
            'i_pisau_faktor' => $pisauFaktor,
        ]);

        unset($this->costing);
        $this->hitung();
    }

    public function removePart(int $partId): void
    {
        IqpCostingPart::whereKey($partId)->where('costing_id', $this->costingId)->delete();
        unset($this->costing);
    }

    public function addCoating(int $partId): void
    {
        $part = IqpCostingPart::where('costing_id', $this->costingId)->findOrFail($partId);

        IqpCostingPartCoating::create([
            'costing_part_id' => $part->id_costing_part,
            'seq' => $part->coatings()->count() + 1,
            'coating_type' => 'WB',
            'index_coating' => MatCoatingRate::where('coating_type', 'WB')->where('activate', true)->value('index_coating'),
        ]);

        unset($this->costing);
    }

    public function removeCoating(int $coatingId): void
    {
        IqpCostingPartCoating::whereKey($coatingId)
            ->whereHas('costingPart', fn ($q) => $q->where('costing_id', $this->costingId))
            ->delete();
        unset($this->costing);
    }

    public function updatePart(int $partId, string $field, $value): void
    {
        $allowed = [
            'part_name', 'grammage_id', 'i_dc_persen_override', 'i_panjang_plano_cm', 'i_lebar_plano_cm',
            'i_cut_per_plano', 'i_isi_per_lb', 'i_jumlah_warna', 'i_ongkos_cetak_rim', 'i_ongkos_plong_lb',
            'i_ongkos_lem_pc', 'i_film_faktor', 'i_film_rate', 'i_pisau_faktor', 'i_pisau_rate_rp', 'i_ongkos_plate_warna',
            'i_dim_panjang_mm', 'i_dim_lebar_mm', 'i_dim_tinggi_mm', 'i_lidah_lem_mm', 'flute_type', 'sheet_rate_id',
            'supplier_sheet_code', 'i_isi_per_lb_kerja', 'i_ongkos_print_pc', 'i_ongkos_diecut_pc', 'i_ongkos_slitter_pc',
            'i_ongkos_slotter_pc', 'i_ongkos_join_pc', 'i_ongkos_partisi_pc', 'i_ongkos_layer_pc', 'i_ongkos_qc_pack_pc',
            'i_ongkos_kupas_pc', 'i_ongkos_packing_pc', 'jenis_packing',
            'i_ongkos_kirim_pc',
        ];

        if (! in_array($field, $allowed, true)) {
            return;
        }

        // Mode Ambil Data (§5.1): plano/cut sumbernya prod_layout_group, bukan input
        // manual -- toleransi guard di sini, UI sudah disable field-nya juga.
        $lockedWhenProdev = ['i_panjang_plano_cm', 'i_lebar_plano_cm', 'i_cut_per_plano'];
        if (in_array($field, $lockedWhenProdev, true)) {
            $isProdev = IqpCostingPart::where('costing_id', $this->costingId)->whereKey($partId)->value('data_source') === 'PRODEV';
            if ($isProdev) {
                return;
            }
        }

        IqpCostingPart::where('costing_id', $this->costingId)->whereKey($partId)->update([$field => $value === '' ? null : $value]);
        unset($this->costing);
    }

    public function updateCoating(int $coatingId, string $field, $value): void
    {
        if (! in_array($field, ['coating_type', 'index_coating'], true)) {
            return;
        }

        $coating = IqpCostingPartCoating::whereKey($coatingId)
            ->whereHas('costingPart', fn ($q) => $q->where('costing_id', $this->costingId))
            ->first();

        if (! $coating) {
            return;
        }

        if ($field === 'coating_type') {
            $rate = MatCoatingRate::where('coating_type', $value)->where('activate', true)->value('index_coating');
            $coating->update(['coating_type' => $value, 'index_coating' => $rate]);
        } else {
            $coating->update([$field => $value]);
        }

        unset($this->costing);
    }

    public function updateTier(int $tierId, string $field, $value): void
    {
        $allowed = ['tolerance_pct', 'margin_pct', 'rounding_mode', 'rounding_step'];
        // qty_pcs Integrated sumbernya iqp_tier (immutable di sini) -- Instant
        // tak punya sumber itu, jadi qty boleh diedit langsung.
        if ($field === 'qty_pcs' && $this->costing->isInstant()) {
            $allowed[] = 'qty_pcs';
        }

        if (! in_array($field, $allowed, true)) {
            return;
        }

        IqpCostingPriceTier::where('costing_id', $this->costingId)->whereKey($tierId)->update([$field => $value]);
        unset($this->costing);
    }

    public function hitung(): void
    {
        $this->engineError = '';

        try {
            (new CostingEngine)->run($this->costing);
        } catch (\Throwable $e) {
            $this->engineError = $e->getMessage();
        }

        unset($this->costing);
    }

    public function submitToDireksi(): void
    {
        $costing = $this->costing;

        if ($costing->doc_status !== 'CALCULATED') {
            return;
        }

        $costing->update(['doc_status' => 'PENDING_APPROVAL']);
        unset($this->costing);
    }

    // "+ Tambahkan ke Quotation" -- tempel inquiry ini (kalau sudah published) ke
    // draft Quotation customer yang sedang berjalan (bikin baru kalau belum ada),
    // lalu pindah ke Quotation Entry. Lihat App\Services\Sales\QuotationBuilder.
    public function addToQuotation()
    {
        $inquiry = $this->costing->inquiry;

        if (! $inquiry || ! $inquiry->customer_id) {
            return;
        }

        $quotation = \App\Services\Sales\QuotationBuilder::findOrCreateDraft($inquiry->customer_id);
        \App\Services\Sales\QuotationBuilder::addInquiry($quotation, $inquiry);

        return $this->redirect(route('quotation.entry', ['quotation' => $quotation->id_quotation]));
    }

    // Wizard step-bar lintas modul, pola sama dgn inquiry/form.blade.php &
    // production/prodev.blade.php::wizardSteps() (user 2026-09-03).
    #[Computed]
    public function wizardSteps(): array
    {
        if (! $this->inquiryId) {
            return []; // mode INSTANT -- tak ada rute Inquiry/ProDev utk dinavigasi
        }

        $prodPartId = IqpPart::where('inquiry_id', $this->inquiryId)
            ->whereNotNull('prod_part_id')
            ->value('prod_part_id');

        return [
            'inquiry' => [
                'label' => '1. Inquiry',
                'url' => route('inquiry.form', ['id' => $this->inquiryId, 'view' => 1]),
            ],
            'development' => [
                'label' => '2. Development',
                'url' => $prodPartId ? route('production.prodev', ['part' => $prodPartId]) : null,
                'reason' => 'Belum ada part yang dikirim ke ProDev.',
            ],
            'costing' => [
                'label' => '3. Costing',
                'url' => route('inquiry.costing', ['inquiry' => $this->inquiryId]),
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
};
?>

<div>
    @if ($this->costing?->isIntegrated() ?? true)
        <x-prodev.wizard-bar current="costing" :steps="$this->wizardSteps" :back-url="route('inquiry.costing.list')" />
    @endif

    @if ($view)
        {{-- ============ CROSS-NAV READ-ONLY (wizard-bar, user 2026-09-04) ============
        Bukan entry primer (grid Costing) -- cuma numpang lihat status/harga dari
        Inquiry/ProDev, TIDAK BISA mengubah apa pun, tidak ada tombol edit sama
        sekali. Sisi A rahasia (CLAUDE.md §4) tetap dijaga: HPP/breakdown TIDAK
        ditampilkan di sini (beda dari $this->canSeeFormula di mode edit yg cuma
        gate utk johiwan) -- yg boleh kelihatan di ringkasan ini cuma status +
        harga jual final per tier (price_per_pcs), SETELAH published. --}}
        <div class="max-w-[720px] mx-auto my-10 px-4">
            <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden">
                <h3 class="text-xs text-navy uppercase font-bold px-3.5 py-2 bg-[#f4f6fa] border-b border-[#d7dbe2]">🧮 Product Costing (lihat saja)</h3>
                <div class="p-4">
                    @if (! $this->costing)
                        <div class="text-[12.5px] px-3 py-3 rounded-md leading-relaxed bg-[#eef0f3] text-[#5a6272]">
                            ⏳ <b>Belum Diproses</b> — Costing utk Inquiry ini belum pernah dibuka/dihitung Tim Costing.
                        </div>
                    @elseif (! $this->costing->published_flag)
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full bg-[#fff3d6] text-[#8a5a00]">{{ $this->costing->doc_status }}</span>
                        </div>
                        <div class="text-[12.5px] px-3 py-3 rounded-md leading-relaxed bg-[#eef0f3] text-[#5a6272]">
                            ⏳ <b>Belum Diproses</b> — Costing masih dikerjakan Tim Costing, belum di-publish Direksi. Harga baru kelihatan di sini setelah publish.
                        </div>
                    @else
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full bg-[#eaf7ee] text-[#1f8a4c]">{{ $this->costing->doc_status }} · PUBLISHED</span>
                        </div>
                        <table class="w-full text-[12.5px]">
                            <thead>
                                <tr>
                                    <th class="text-left px-2 py-1.5 text-[9.5px] text-muted uppercase font-bold border-b border-[#eef1f5]">Qty</th>
                                    <th class="text-right px-2 py-1.5 text-[9.5px] text-muted uppercase font-bold border-b border-[#eef1f5]">Harga / pcs</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->costing->priceTiers as $tier)
                                    <tr class="{{ $tier->is_primary ? 'bg-[#eef5ff]' : '' }}">
                                        <td class="px-2 py-1.5 border-b border-[#eef1f5] font-semibold">{{ number_format($tier->qty_pcs) }}{{ $tier->is_primary ? ' ★' : '' }}</td>
                                        <td class="px-2 py-1.5 border-b border-[#eef1f5] text-right font-bold text-navy">{{ $tier->price_per_pcs !== null ? 'Rp '.number_format($tier->price_per_pcs, 2) : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="text-center py-4 text-muted">Belum ada tier qty.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    @else
    @if ($this->costing->isIntegrated())
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">🧮 TELASIH MAKMUR — Costing (HPP)</b>
        <span class="text-xs text-white/75">{{ $this->costing->inquiry?->inquiry_no ?? '—' }} · {{ $this->costing->inquiry?->product_draft_name }}</span>
        <button type="button" wire:click="addToQuotation" class="ml-auto bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md cursor-pointer hover:brightness-110">＋ Tambahkan ke Quotation</button>
        <a href="{{ route('inquiry.nota', ['inquiry' => $this->inquiryId]) }}" class="bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">→ Nota</a>
        <a href="{{ route('inquiry.imposition', ['inquiry' => $this->inquiryId]) }}" class="bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">→ Imposition</a>
        <a href="{{ route('inquiry.hub') }}" title="Kembali ke Inquiry Hub"
            class="inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full no-underline hover:brightness-110">
            <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-[13px]"><i class="fa-solid fa-arrow-left"></i></span>
        </a>
    </header>
    @else
    {{-- ============ HEADER MODE INSTANT (standalone, keputusan user 2026-09-18) ============ --}}
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">🧮 Kalkulator HPP — Mode Instant</b>
        <span class="text-xs text-white/75">{{ $this->costing->doc_no ?? 'Draft #'.$this->costingId }}</span>
        <button type="button" wire:click="reverseToIntegrated"
            title="Bikin Inquiry Product + ProDev otomatis dari data di sini, lalu tautkan (mode dokumen ini berubah jadi Integrated)"
            class="ml-auto bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md cursor-pointer hover:brightness-110">⇄ Reverse</button>
        <a href="{{ route('inquiry.costing.instant.list') }}" wire:navigate title="Kembali ke grid Instant"
            class="inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full no-underline hover:brightness-110">
            <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-[13px]"><i class="fa-solid fa-arrow-left"></i></span>
        </a>
    </header>
    @endif

    <div class="max-w-[1180px] mx-auto my-5 px-4">

        @if (session('error'))
            <div class="bg-red-50 border border-red-300 text-red-700 text-xs px-3.5 py-2.5 rounded-md mb-3">⚠ {{ session('error') }}</div>
        @endif

        @if ($engineError)
            <div class="bg-red-50 border border-red-300 text-red-700 text-xs px-3.5 py-2.5 rounded-md mb-3">⚠ {{ $engineError }}</div>
        @endif

        @if ($this->costing->isInstant())
            {{-- ============ IDENTITAS MODE INSTANT (tak ada Inquiry sbg sumber) ============ --}}
            <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4 grid grid-cols-3 gap-3">
                <x-form.select label="Customer" wire:change="updateHeaderField('customer_id', $event.target.value)" class="!py-1.5"
                    :selected="$this->costing->customer_id" :options="$this->customers->pluck('customer_name', 'id_customer')->all()" />
                <x-form.input label="Nama Produk (bebas)" value="{{ $this->costing->product_name_draft }}" placeholder="mis. Dus Sepatu Model X"
                    wire:change="updateHeaderField('product_name_draft', $event.target.value)" class="!py-1.5" />
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] text-muted uppercase font-semibold">Dimensi Jadi P×L×T (mm)</label>
                    <div class="flex gap-1">
                        <x-form.input type="number" step="0.01" value="{{ $this->costing->i_dim_panjang_mm }}" wire:change="updateHeaderField('i_dim_panjang_mm', $event.target.value)" class="w-1/3 !py-1.5" />
                        <x-form.input type="number" step="0.01" value="{{ $this->costing->i_dim_lebar_mm }}" wire:change="updateHeaderField('i_dim_lebar_mm', $event.target.value)" class="w-1/3 !py-1.5" />
                        <x-form.input type="number" step="0.01" value="{{ $this->costing->i_dim_tinggi_mm }}" wire:change="updateHeaderField('i_dim_tinggi_mm', $event.target.value)" class="w-1/3 !py-1.5" />
                    </div>
                </div>
            </div>
        @endif

        {{-- ============ HEADER ============ --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4 flex items-center gap-4 flex-wrap">
            <div>
                <div class="text-[10.5px] text-muted uppercase font-semibold">Tipe</div>
                <div class="text-sm font-bold text-navy">{{ $this->costing->calc_type }}</div>
            </div>
            <div>
                <div class="text-[10.5px] text-muted uppercase font-semibold">Status</div>
                <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full inline-block bg-[#eaf7ee] text-[#1f8a4c]">{{ $this->costing->doc_status }}</span>
            </div>
            @if ($this->costing->isIntegrated())
            <div>
                <div class="text-[10.5px] text-muted uppercase font-semibold">Dimensi Jadi (mm)</div>
                <div class="text-sm">{{ $this->costing->i_dim_panjang_mm }} × {{ $this->costing->i_dim_lebar_mm }} × {{ $this->costing->i_dim_tinggi_mm }}</div>
            </div>
            @endif
            <div>
                <div class="text-[10.5px] text-muted uppercase font-semibold">PPN</div>
                <div class="text-sm">{{ $this->costing->ppn_mode }} ({{ $this->costing->ppn_pct }}%)</div>
            </div>
            <div class="ml-auto flex gap-2">
                <button type="button" wire:click="hitung" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">⚙ Hitung</button>
                <button type="button" wire:click="submitToDireksi" @disabled($this->costing->doc_status !== 'CALCULATED')
                    class="px-4 py-2 rounded text-xs font-semibold bg-[#22a06b] text-white cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">Submit ke Direksi</button>
            </div>
        </div>

        {{-- ============ PART REPEATER (DC only) ============ --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2]">
                <h3 class="text-xs text-navy uppercase font-bold">Part</h3>
                <div class="flex gap-1.5 items-center">
                    <button type="button" wire:click="addPart('DC')" class="text-[11px] font-bold text-accent bg-[#eef3fb] border border-dashed border-[#9cc0f0] rounded px-2.5 py-1 cursor-pointer">＋ Part DC</button>
                    <button type="button" wire:click="addPart('SHEET_DIGITAL')" class="text-[11px] font-bold text-accent bg-[#eef3fb] border border-dashed border-[#9cc0f0] rounded px-2.5 py-1 cursor-pointer">＋ Part Sheet</button>
                    @if ($this->inquiryParts->isNotEmpty())
                        <select id="add-from-part-{{ $this->costingId }}" class="text-[11px] px-2 py-1 border border-dashed border-[#9cc0f0] rounded text-navy">
                            <option value="">— dari part inquiry —</option>
                            @foreach ($this->inquiryParts as $ip)
                                <option value="{{ $ip->id_part }}">{{ $ip->part_name ?: 'Part #'.$ip->part_seq }}{{ $ip->layout?->stat_confirm ? ' ✓' : '' }}</option>
                            @endforeach
                        </select>
                        <button type="button"
                            @click="const s=document.getElementById('add-from-part-{{ $this->costingId }}'); if(s.value){ $wire.addPartFromInquiry(parseInt(s.value)); s.value=''; }"
                            class="text-[11px] font-bold text-accent bg-[#eef3fb] border border-dashed border-[#9cc0f0] rounded px-2.5 py-1 cursor-pointer">＋</button>
                    @endif
                </div>
            </div>

            @forelse ($this->costing->parts as $part)
                <div class="border-b border-[#d7dbe2] last:border-b-0 p-4" wire:key="part-{{ $part->id_costing_part }}" x-data="{ zoomArtwork: false }">
                    <div class="flex items-center gap-2 mb-3">
                        <x-form.input value="{{ $part->part_name }}" placeholder="Nama part (mis. Bag Atas)"
                            wire:change="updatePart({{ $part->id_costing_part }}, 'part_name', $event.target.value)"
                            class="!py-1.5 font-semibold" />
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-[#d8ecff] text-[#1560a8]">{{ $part->part_type_code }}</span>

                        {{-- Mode Instant selalu MANUAL (blm py ProDev sblm Reverse) -- toggle
                        cuma relevan utk Integrated. --}}
                        @if ($this->costing->isIntegrated() && ! in_array($part->part_type_code, ['SHEET', 'SHEET_DIGITAL'], true))
                            <button type="button" wire:click="toggleDataSource({{ $part->id_costing_part }})"
                                class="text-[10px] font-bold px-2 py-0.5 rounded cursor-pointer {{ $part->data_source === 'PRODEV' ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#f4f6fa] text-muted border border-[#d7dbe2]' }}">
                                {{ $part->data_source === 'PRODEV' ? '🔗 Ambil Data (ProDev)' : '✎ Manual' }}
                            </button>
                        @endif

                        <button type="button" wire:click="removePart({{ $part->id_costing_part }})" class="text-red-500 text-xs cursor-pointer px-2 ml-auto">✕ Hapus</button>
                    </div>

                    {{-- Artwork/desain dari Inquiry (permintaan user 2026-09-04: "info
                    artwork harus mengalir ke ProDev dan ProCost") -- baca langsung dari
                    iqp_part.artwork_path via relasi inquiryPart(), sama file yg diupload
                    sales di Inquiry, tidak disalin ulang. Null kalau part costing ini
                    dibuat manual (bukan dari addPartFromInquiry()) atau inquiry-nya
                    memang belum ada artwork. --}}
                    @php($artworkUrl = $part->inquiryPart?->artwork_path ? asset('storage/'.$part->inquiryPart->artwork_path) : null)
                    @if ($artworkUrl)
                        <div class="flex items-center gap-2.5 bg-[#f4f6fa] border border-[#d7dbe2] rounded-md p-2 mb-3">
                            <img src="{{ $artworkUrl }}" @click="zoomArtwork = true"
                                class="w-12 h-12 object-cover rounded border border-[#d7dbe2] cursor-zoom-in flex-shrink-0" alt="artwork">
                            <div class="min-w-0">
                                <div class="text-[10px] font-bold text-navy uppercase tracking-wide">📎 Artwork dari Inquiry</div>
                                <div class="text-[11px] text-muted truncate" title="{{ $part->inquiryPart->artwork_original_filename }}">{{ $part->inquiryPart->artwork_original_filename }}</div>
                            </div>
                        </div>
                        <div x-show="zoomArtwork" x-cloak @click.self="zoomArtwork = false" @keydown.escape.window="zoomArtwork = false"
                             class="fixed inset-0 z-[250] bg-black/70 flex items-center justify-center p-6">
                            <img src="{{ $artworkUrl }}" class="max-w-[90vw] max-h-[88vh] rounded-lg shadow-2xl" alt="artwork ukuran penuh">
                        </div>
                    @endif

                    @if ($part->data_source === 'PRODEV')
                        <div class="bg-[#f0f7f4] border border-[#c6e5d5] rounded-md p-2.5 mb-3 flex items-center gap-2">
                            <span class="text-[11px] text-[#177a4b] font-semibold">Sumber Layout:</span>
                            <select onchange="if(this.value) $wire.pickLayoutGroup({{ $part->id_costing_part }}, parseInt(this.value))"
                                class="text-[11px] px-2 py-1 border border-[#c3d8f5] rounded flex-1 max-w-[360px]">
                                <option value="">— pilih Master Card/Layout terkonfirmasi —</option>
                                @foreach ($this->availableLayoutGroups as $g)
                                    <option value="{{ $g->id_layout_group }}" @selected($part->prod_layout_group_id === $g->id_layout_group)>
                                        {{ $g->mastercard_code }} · {{ $g->product?->product_name }} · {{ $g->up_count }} UP
                                    </option>
                                @endforeach
                            </select>
                            @if (! $part->prod_layout_group_id)
                                <span class="text-[10.5px] text-[#8a5a00]">Pilih layout dulu, plano/cut &amp; HPP terisi otomatis.</span>
                            @endif
                        </div>

                        @php($selectedGroup = $part->prod_layout_group_id ? $this->availableLayoutGroups->firstWhere('id_layout_group', $part->prod_layout_group_id) : null)
                        @if ($selectedGroup && $selectedGroup->parts->count() > 1)
                            {{-- Layout Tool #10 (COMBINED_SPLIT) menaungi >1 part -- officer WAJIB
                            pilih part mana yg direpresentasikan baris costing ini, supaya biaya
                            kertas/plate teralokasi proporsional by luas (§4.2b), bukan dobel-hitung. --}}
                            <div class="bg-[#fff8ec] border border-[#f0dcae] rounded-md p-2.5 mb-3 flex items-center gap-2">
                                <span class="text-[11px] text-[#8a5a00] font-semibold">Part dalam Layout Gabungan:</span>
                                <select onchange="if(this.value) $wire.pickLayoutGroup({{ $part->id_costing_part }}, {{ $selectedGroup->id_layout_group }}, parseInt(this.value))"
                                    class="text-[11px] px-2 py-1 border border-[#e6cd8f] rounded flex-1 max-w-[320px]">
                                    <option value="">— pilih part —</option>
                                    @foreach ($selectedGroup->parts as $gp)
                                        <option value="{{ $gp->id_part }}" @selected($part->prodev_part_id === $gp->id_part)>
                                            {{ $gp->part_name }} ({{ $gp->pivot->qty_up_in_group }} up)
                                        </option>
                                    @endforeach
                                </select>
                                @if (! $part->prodev_part_id)
                                    <span class="text-[10.5px] text-[#9a3324]">Belum dipilih — biaya BELUM teralokasi proporsional.</span>
                                @endif
                            </div>
                        @endif
                    @endif

                    @if ($part->part_type_code === 'DC')
                    <div class="grid grid-cols-4 gap-2.5 mb-3">
                        <x-form.select label="Gramatur" wire:change="updatePart({{ $part->id_costing_part }}, 'grammage_id', $event.target.value)" class="!py-1.5"
                            :selected="$part->grammage_id" :options="$this->grammages->pluck('grammage_name', 'id_grammage')->all()" />
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Plano P×L (cm)</label>
                            <div class="flex gap-1">
                                <x-form.input type="number" step="0.01" value="{{ $part->i_panjang_plano_cm }}" @disabled($part->data_source === 'PRODEV') wire:change="updatePart({{ $part->id_costing_part }}, 'i_panjang_plano_cm', $event.target.value)" class="w-1/2 !py-1.5 disabled:bg-[#eef0f3]" />
                                <x-form.input type="number" step="0.01" value="{{ $part->i_lebar_plano_cm }}" @disabled($part->data_source === 'PRODEV') wire:change="updatePart({{ $part->id_costing_part }}, 'i_lebar_plano_cm', $event.target.value)" class="w-1/2 !py-1.5 disabled:bg-[#eef0f3]" />
                            </div>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Cut / Isi</label>
                            <div class="flex gap-1">
                                <x-form.input type="number" step="0.01" value="{{ $part->i_cut_per_plano }}" @disabled($part->data_source === 'PRODEV') wire:change="updatePart({{ $part->id_costing_part }}, 'i_cut_per_plano', $event.target.value)" class="w-1/2 !py-1.5 disabled:bg-[#eef0f3]" />
                                <x-form.input type="number" step="0.01" value="{{ $part->i_isi_per_lb }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_isi_per_lb', $event.target.value)" class="w-1/2 !py-1.5" />
                            </div>
                        </div>
                        <x-form.input label="Jumlah Warna" type="number" value="{{ $part->i_jumlah_warna }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_jumlah_warna', $event.target.value)" class="!py-1.5" />
                    </div>

                    @if ($part->prodev_part_id)
                        @php($routingNames = $this->routingNamesFor($part))
                        @php($routingWarnings = $this->routingWarningsFor($part))
                        <div class="flex items-center gap-1.5 flex-wrap text-[10.5px] mb-1.5">
                            <span class="font-bold text-muted uppercase">Urutan Proses (ProDev):</span>
                            @forelse ($routingNames as $name)
                                <span class="px-1.5 py-0.5 rounded bg-[#eef3fb] text-[#2c3e69] font-semibold">{{ $name }}</span>
                            @empty
                                <span class="text-[#8a5a00]">belum ada routing tercatat di ProDev untuk part ini</span>
                            @endforelse
                        </div>
                        @if ($routingWarnings)
                            <div class="bg-[#fff8ec] border border-[#f0dcae] rounded-md p-2 mb-1.5 space-y-0.5">
                                @foreach ($routingWarnings as $w)
                                    <div class="text-[10.5px] text-[#8a5a00]">⚠ {{ $w }}</div>
                                @endforeach
                            </div>
                        @endif
                    @endif
                    <div class="grid grid-cols-3 gap-2.5 mb-3">
                        <x-form.input label="Ongkos Cetak /rim" type="number" step="0.01" value="{{ $part->i_ongkos_cetak_rim }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_cetak_rim', $event.target.value)" class="!py-1.5" />
                        <x-form.input label="Ongkos Plong /lb" type="number" step="0.01" value="{{ $part->i_ongkos_plong_lb }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_plong_lb', $event.target.value)" class="!py-1.5" />
                        <x-form.input label="Ongkos Lem /pc" type="number" step="0.01" value="{{ $part->i_ongkos_lem_pc }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_lem_pc', $event.target.value)" class="!py-1.5" />
                    </div>

                    <div class="grid grid-cols-4 gap-2.5 mb-3">
                        <x-form.input label="Film Faktor" type="number" step="0.01" value="{{ $part->i_film_faktor }}" placeholder="0.25" wire:change="updatePart({{ $part->id_costing_part }}, 'i_film_faktor', $event.target.value)" class="!py-1.5" />
                        <x-form.input label="Pisau Faktor" type="number" step="0.01" value="{{ $part->i_pisau_faktor }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_pisau_faktor', $event.target.value)" class="!py-1.5" />
                        <x-form.input label="Pisau Rate (Rp)" type="number" step="0.01" value="{{ $part->i_pisau_rate_rp }}" placeholder="default part type" wire:change="updatePart({{ $part->id_costing_part }}, 'i_pisau_rate_rp', $event.target.value)" class="!py-1.5" />
                        <x-form.input label="Plate/warna (Rp)" type="number" step="0.01" value="{{ $part->i_ongkos_plate_warna }}" placeholder="default 90.000" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_plate_warna', $event.target.value)" class="!py-1.5" />
                    </div>

                    {{-- COATING GRID (nested) — hidden utk SHEET_DIGITAL (TOOL_SKIP, §B.1) --}}
                    <div class="border border-dashed border-[#cfd7e3] rounded-md p-2.5">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10.5px] font-bold text-navy uppercase">Coating (berurutan)</span>
                            <button type="button" wire:click="addCoating({{ $part->id_costing_part }})" class="text-[10.5px] font-bold text-accent cursor-pointer">＋ Layer</button>
                        </div>
                        @forelse ($part->coatings as $coating)
                            <div class="flex items-center gap-2 mb-1.5" wire:key="coating-{{ $coating->id_coating }}">
                                <span class="text-[10px] text-muted w-4">{{ $coating->seq }}.</span>
                                <x-form.select wire:change="updateCoating({{ $coating->id_coating }}, 'coating_type', $event.target.value)" class="!py-1 text-[11px]" :placeholder="false"
                                    :selected="$coating->coating_type" :options="$this->coatingRates->pluck('coating_type', 'coating_type')->all()" />
                                <span class="text-[11px] text-muted">index: {{ $coating->index_coating }}</span>
                                <span class="text-[11px] ml-auto">{{ $coating->c_cost_per_pc !== null ? number_format($coating->c_cost_per_pc, 2) : '—' }}/pc</span>
                                <button type="button" wire:click="removeCoating({{ $coating->id_coating }})" class="text-red-500 text-[11px] cursor-pointer">✕</button>
                            </div>
                        @empty
                            <div class="text-[10.5px] text-muted">Tidak ada coating.</div>
                        @endforelse
                    </div>

                    @elseif ($part->part_type_code === 'SHEET_DIGITAL')
                    <div class="grid grid-cols-4 gap-2.5 mb-3">
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Panjang×Lebar×Tinggi (mm)</label>
                            <div class="flex gap-1">
                                <x-form.input type="number" step="0.01" value="{{ $part->i_dim_panjang_mm }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_dim_panjang_mm', $event.target.value)" class="w-1/3 !py-1.5" />
                                <x-form.input type="number" step="0.01" value="{{ $part->i_dim_lebar_mm }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_dim_lebar_mm', $event.target.value)" class="w-1/3 !py-1.5" />
                                <x-form.input type="number" step="0.01" value="{{ $part->i_dim_tinggi_mm }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_dim_tinggi_mm', $event.target.value)" class="w-1/3 !py-1.5" />
                            </div>
                        </div>
                        <x-form.input label="Lidah Lem (mm)" type="number" step="0.01" value="{{ $part->i_lidah_lem_mm }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_lidah_lem_mm', $event.target.value)" class="!py-1.5" />
                        <x-form.select label="Jenis Flute" wire:change="updatePart({{ $part->id_costing_part }}, 'flute_type', $event.target.value)" class="!py-1.5"
                            :selected="$part->flute_type" :options="['B/F' => 'B/F', 'C/F' => 'C/F', 'BC/F' => 'BC/F']" />
                        <x-form.select label="Substance" wire:change="updatePart({{ $part->id_costing_part }}, 'sheet_rate_id', $event.target.value)" class="!py-1.5"
                            :selected="$part->sheet_rate_id" :options="$this->sheetRates->mapWithKeys(fn ($sr) => [$sr->id_sheet_rate => $sr->substance_name.' ('.$sr->flute_type.')'])->all()" />
                    </div>

                    <div class="grid grid-cols-4 gap-2.5 mb-3">
                        <x-form.input label="Supplier" value="{{ $part->supplier_sheet_code }}" wire:change="updatePart({{ $part->id_costing_part }}, 'supplier_sheet_code', $event.target.value)" class="!py-1.5" />
                        <x-form.input label="Isi/Lb Kerja" type="number" step="0.01" value="{{ $part->i_isi_per_lb_kerja }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_isi_per_lb_kerja', $event.target.value)" class="!py-1.5" />
                        <x-form.input label="Ongkos Print /pc" type="number" step="0.01" value="{{ $part->i_ongkos_print_pc }}" placeholder="dikali 2 di engine" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_print_pc', $event.target.value)" class="!py-1.5" />
                        <x-form.input label="Ongkos Diecut /pc" type="number" step="0.01" value="{{ $part->i_ongkos_diecut_pc }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_diecut_pc', $event.target.value)" class="!py-1.5" />
                    </div>

                    <div class="grid grid-cols-4 gap-2.5 mb-3">
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Slitter/Slotter /pc</label>
                            <div class="flex gap-1">
                                <x-form.input type="number" step="0.01" value="{{ $part->i_ongkos_slitter_pc }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_slitter_pc', $event.target.value)" class="w-1/2 !py-1.5" />
                                <x-form.input type="number" step="0.01" value="{{ $part->i_ongkos_slotter_pc }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_slotter_pc', $event.target.value)" class="w-1/2 !py-1.5" />
                            </div>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Join/Partisi/Layer /pc</label>
                            <div class="flex gap-1">
                                <x-form.input type="number" step="0.01" value="{{ $part->i_ongkos_join_pc }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_join_pc', $event.target.value)" class="w-1/3 !py-1.5" />
                                <x-form.input type="number" step="0.01" value="{{ $part->i_ongkos_partisi_pc }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_partisi_pc', $event.target.value)" class="w-1/3 !py-1.5" />
                                <x-form.input type="number" step="0.01" value="{{ $part->i_ongkos_layer_pc }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_layer_pc', $event.target.value)" class="w-1/3 !py-1.5" />
                            </div>
                        </div>
                        <x-form.input label="QC /pc" type="number" step="0.01" value="{{ $part->i_ongkos_qc_pack_pc }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_qc_pack_pc', $event.target.value)" class="!py-1.5" />
                        <x-form.input label="Kirim /pc" type="number" step="0.01" value="{{ $part->i_ongkos_kirim_pc }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_kirim_pc', $event.target.value)" class="!py-1.5" />
                    </div>

                    {{-- Gap analysis 2026-09-03 (perbandingan vs legacy ScriptCase "Kalkulasih HPP"):
                    Ongkos Kupas belum ada sama sekali, QC+Packing tadinya 1 kolom (di atas, sekarang
                    QC saja). Jenis Packing free-text (bukan dropdown) -- belum ada daftar baku jenis
                    packing yang dikonfirmasi user, jangan menebak opsinya. --}}
                    <div class="grid grid-cols-3 gap-2.5 mb-3">
                        <x-form.input label="Kupas /pc" type="number" step="0.01" value="{{ $part->i_ongkos_kupas_pc }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_kupas_pc', $event.target.value)" class="!py-1.5" />
                        <x-form.input label="Packing /pc" type="number" step="0.01" value="{{ $part->i_ongkos_packing_pc }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_packing_pc', $event.target.value)" class="!py-1.5" />
                        <x-form.input label="Jenis Packing" value="{{ $part->jenis_packing }}" placeholder="mis. Karton/Plastik" wire:change="updatePart({{ $part->id_costing_part }}, 'jenis_packing', $event.target.value)" class="!py-1.5" />
                    </div>
                    @endif

                    @if ($part->c_total_part_cost_pc !== null)
                        @if ($this->canSeeFormula)
                            @if ($part->part_type_code === 'SHEET_DIGITAL')
                                <div class="bg-[#eef3fb] border border-[#c3d8f5] rounded-md px-3 py-2 text-[11.5px] text-[#1560a8] flex gap-4 flex-wrap mb-2">
                                    <span>Sheet {{ $part->c_panjang_sheet_cm }}×{{ $part->c_lebar_sheet_cm }}cm</span>
                                    <span>Isi trim: <b>{{ $part->c_isi_trim }}</b></span>
                                    <span>Lebar trim: <b>{{ $part->c_lebar_trim_cm }}cm</b></span>
                                    <span>Running meter: <b>{{ number_format($part->c_running_meter, 2) }}</b></span>
                                    <span>Diskon/surcharge: <b>{{ $part->c_discount_rm }}</b></span>
                                </div>
                            @endif
                            <div class="mt-1 bg-[#f0f7f4] border border-[#c6e5d5] rounded-md px-3 py-2 text-[11.5px] text-[#177a4b] flex gap-4 flex-wrap">
                                <span>Bahan: <b>{{ number_format($part->c_paper_cost_pc, 2) }}</b></span>
                                <span>Proses: <b>{{ number_format($part->c_cetak_cost_pc + $part->c_plong_cost_pc + $part->c_lem_cost_pc, 2) }}</b></span>
                                <span>Coating: <b>{{ number_format($part->c_total_coating_pc, 2) }}</b></span>
                                <span>Tooling (order): <b>{{ number_format($part->c_tooling_cost_order, 0) }}</b></span>
                                <span>Total/pc (tanpa tooling): <b>{{ number_format($part->c_total_part_cost_pc, 2) }}</b></span>
                            </div>
                        @else
                            {{-- Instruksi user 2026-09-03: breakdown formula HPP cuma login johiwan --}}
                            <div class="mt-1 bg-[#f4f6fa] border border-[#d7dbe2] rounded-md px-3 py-2 text-[11.5px] text-muted flex items-center gap-1.5">
                                🔒 Detail perhitungan disembunyikan — sudah dihitung, hasil akhir ada di Ladder Harga di bawah.
                            </div>
                        @endif
                    @endif
                </div>
            @empty
                <div class="text-center py-8 text-muted text-sm">Belum ada part. Klik "Tambah Part" untuk mulai.</div>
            @endforelse
        </div>

        {{-- ============ PRICE TIER PANEL ============ --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2]">
                <h3 class="text-xs text-navy uppercase font-bold">Ladder Harga per Tier</h3>
                @if ($this->costing->isInstant())
                    <button type="button" wire:click="addTier" class="text-[11px] font-bold text-accent bg-[#eef3fb] border border-dashed border-[#9cc0f0] rounded px-2.5 py-1 cursor-pointer">＋ Tambah Tier</button>
                @endif
            </div>
            <table class="w-full border-collapse text-xs">
                <thead>
                    <tr>
                        <th class="text-left px-3 py-2 bg-[#f4f5f8] text-muted text-[10.5px] uppercase border-b border-[#d7dbe2]">Qty</th>
                        <th class="text-left px-3 py-2 bg-[#f4f5f8] text-muted text-[10.5px] uppercase border-b border-[#d7dbe2]">Toleransi %</th>
                        <th class="text-left px-3 py-2 bg-[#f4f5f8] text-muted text-[10.5px] uppercase border-b border-[#d7dbe2]">Margin %</th>
                        <th class="text-left px-3 py-2 bg-[#f4f5f8] text-muted text-[10.5px] uppercase border-b border-[#d7dbe2]">Rounding</th>
                        <th class="text-left px-3 py-2 bg-[#f4f5f8] text-muted text-[10.5px] uppercase border-b border-[#d7dbe2]">HPP/pc</th>
                        <th class="text-left px-3 py-2 bg-[#f4f5f8] text-muted text-[10.5px] uppercase border-b border-[#d7dbe2]">Harga Jual/pc (excl PPN)</th>
                        <th class="text-left px-3 py-2 bg-[#f4f5f8] text-muted text-[10.5px] uppercase border-b border-[#d7dbe2]">Incl PPN</th>
                        @if ($this->costing->isInstant())
                            <th class="w-8 border-b border-[#d7dbe2]"></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->costing->priceTiers as $tier)
                        <tr wire:key="tier-{{ $tier->id_price_tier }}" class="{{ $tier->is_primary ? 'bg-[#eef5ff]' : '' }}">
                            <td class="px-3 py-2 border-b border-[#eef1f5] font-semibold">
                                @if ($this->costing->isInstant())
                                    <x-form.input type="number" step="1" value="{{ $tier->qty_pcs }}" wire:change="updateTier({{ $tier->id_price_tier }}, 'qty_pcs', $event.target.value)" class="w-24 !py-1" />
                                @else
                                    {{ number_format($tier->qty_pcs) }}{{ $tier->is_primary ? ' ★' : '' }}
                                @endif
                            </td>
                            <td class="px-3 py-2 border-b border-[#eef1f5]"><x-form.input type="number" step="0.1" value="{{ $tier->tolerance_pct }}" wire:change="updateTier({{ $tier->id_price_tier }}, 'tolerance_pct', $event.target.value)" class="w-16 !py-1" /></td>
                            <td class="px-3 py-2 border-b border-[#eef1f5]"><x-form.input type="number" step="0.1" value="{{ $tier->margin_pct }}" wire:change="updateTier({{ $tier->id_price_tier }}, 'margin_pct', $event.target.value)" class="w-16 !py-1" /></td>
                            <td class="px-3 py-2 border-b border-[#eef1f5]">
                                <x-form.select wire:change="updateTier({{ $tier->id_price_tier }}, 'rounding_mode', $event.target.value)" class="!py-1" :placeholder="false"
                                    :selected="$tier->rounding_mode" :options="['NEAREST' => 'NEAREST', 'UP' => 'UP', 'DOWN' => 'DOWN']" />
                            </td>
                            <td class="px-3 py-2 border-b border-[#eef1f5]">
                                @if ($this->canSeeFormula)
                                    {{ $tier->c_hpp_pc !== null ? number_format($tier->c_hpp_pc, 2) : '—' }}
                                @else
                                    🔒
                                @endif
                            </td>
                            <td class="px-3 py-2 border-b border-[#eef1f5] font-bold text-navy">{{ $tier->price_per_pcs !== null ? number_format($tier->price_per_pcs, 2) : '—' }}</td>
                            <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $tier->c_after_ppn_pc !== null ? number_format($tier->c_after_ppn_pc, 2) : '—' }}</td>
                            @if ($this->costing->isInstant())
                                <td class="px-2 py-2 border-b border-[#eef1f5] text-right">
                                    <button type="button" wire:click="removeTier({{ $tier->id_price_tier }})" class="text-red-500 text-[11px] cursor-pointer">✕</button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-6 text-muted">Belum ada tier qty.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
    @endif
</div>
