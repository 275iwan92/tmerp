<?php

use App\Models\Master\MatPlanoSize;
use App\Models\Master\ProdLayoutGroup;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdPartAttachment;
use App\Models\Master\ProdPartFinishing;
use App\Models\Master\ProdPartProcess;
use App\Models\Master\ProdProcess;
use App\Models\Master\RefFinishingType;
use App\Models\Sdi\IqpPart;
use App\Models\Sys\SysConstant;
use App\Services\Imposition\ImpositionEngine;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

// docs/inquiry_product_costing/productdevelopment_tier1.md §4.1/§4.2/§4.3a --
// layar KERJA ProDev (bukan Master Card itu sendiri -- Master Card = tombol
// cetak dari data yang sama, lihat prodev-print.blade.php). Officer isi
// spesifikasi teknis prod_part, jalankan ENGINE Tingkat 1 (parametric flap
// sweep), pilih hasil terbaik -> tersimpan ke prod_layout_group. Scope saat
// ini: 1 group = 1 part (Tool #10 Intra-Product Combiner, gabung >1 part,
// BELUM diimplementasi -- urutan kerja resmi tier1 §7 poin 1).
new #[Title('Product Development — TELASIH MAKMUR ERP')] class extends Component
{
    use WithFileUploads;

    public ?int $partId = null;

    public ?int $selectedPlanoId = null;

    public array $sweepCandidates = [];

    // Tool #10 (Intra-Product Part Combiner, BACKLOG.md §0/§4.2b).
    public ?int $combineWithPartId = null;

    public ?array $combineResult = null;

    public ?int $newProcessId = null;

    public ?int $newMachineId = null;

    public string $newProcessNotes = '';

    public ?int $newFinishingTypeId = null;

    public string $newFinishingNotes = '';

    public $newAttachmentFile = null;

    public string $newAttachmentType = 'JPEG';

    public function mount(?int $part = null): void
    {
        $this->partId = $part ?? ProdPart::orderByDesc('id_part')->value('id_part');
        $this->selectedPlanoId = MatPlanoSize::where('activate', true)->orderBy('sort_order')->value('id_plano_size');
    }

    #[Computed]
    public function part(): ?ProdPart
    {
        if (! $this->partId) {
            return null;
        }

        return ProdPart::with([
            'product',
            'processes.process',
            'processes.machine',
            'finishings.finishingType',
            'attachments',
            'layoutGroups',
        ])->find($this->partId);
    }

    #[Computed]
    public function activeLayoutGroup(): ?ProdLayoutGroup
    {
        return $this->part?->layoutGroups->sortByDesc('id_layout_group')->first();
    }

    // Status ringkas buat badge di header (selalu kelihatan tanpa scroll,
    // user 2026-09-03 minta -- sebelumnya cuma ada di dalam section Engine
    // Imposition, harus scroll dulu). Sama persis 3 nilai yg dipakai grid
    // production.prodev.list (prodev-list.blade.php::layoutStatus()).
    public function layoutStatusLabel(): string
    {
        return match ($this->activeLayoutGroup?->status) {
            'CONFIRMED' => 'CONFIRMED',
            'DRAFT' => 'DRAFT',
            default => 'BELUM MULAI',
        };
    }

    public function layoutStatusBadgeClass(): string
    {
        return match ($this->layoutStatusLabel()) {
            'CONFIRMED' => 'bg-[#1c3a2a] text-[#5fe3a0]',
            'DRAFT' => 'bg-[#4a3a12] text-[#f0c46a]',
            default => 'bg-white/10 text-white/70',
        };
    }

    // Redesign form (mockup_FORM_prodev_2/header.png, user 2026-09-03) --
    // seluruh isi konten (kartu/border/teks) ikut tema tm2sys.sys_theme yang
    // dipilih user (bukan cuma chrome header yg sudah otomatis ikut lewat
    // --color-navy/--color-accent global, lihat themestandard.md §6 "halaman
    // iframe belum digarap" -- layar ini jadi INSTANCE PERTAMA yg digarap).
    #[Computed]
    public function theme(): \App\Models\Sys\SysTheme
    {
        return \App\Support\ActiveTheme::resolve();
    }

    // "AI Waste Reduction" (mockup panel Efficiency Metrics) -- BUKAN angka
    // dikarang: baseline = ImpositionEngine::run() TANPA shift/interlock pada
    // flap+plano YANG SAMA dgn layout aktif (persis apa yg Tingkat 1 hasilkan),
    // dibandingkan ke waste_pct SUNGGUHAN yg tersimpan di layout aktif (bisa
    // dari Tingkat 1/2/Best Sheet). delta negatif = layout aktif lebih hemat
    // drpd grid standar polos, exact makna "AI Waste Reduction -12.6%" mockup.
    #[Computed]
    public function wasteReductionVsStandard(): ?array
    {
        $group = $this->activeLayoutGroup;
        $part = $this->part;

        if (! $group || ! $part || ! $group->plano_w_mm || ! $group->plano_h_mm) {
            return null;
        }

        $bodyW = (float) ($part->cm_width ?? 0) * 10;
        $bodyH = (float) ($part->cm_length ?? 0) * 10;

        if ($bodyW <= 0 || $bodyH <= 0) {
            return null;
        }

        $flapLen = (float) (data_get($group->layout_detail, 'flap_length_mm') ?? $part->flap_length_mm ?? 0);
        $side = $part->flap_side ?? 'TOP';

        $engine = new ImpositionEngine;
        [$flatW, $flatH] = $engine->applyFlap($bodyW, $bodyH, $side, $flapLen);
        $baseline = $engine->run((float) $group->plano_w_mm, (float) $group->plano_h_mm, $flatW, $flatH, $this->gapMm(), $this->gripperMm(), $this->marginMm());

        $activeWaste = (float) $group->waste_pct;
        $baselineWaste = (float) $baseline['waste_pct'];
        $deltaPct = $baselineWaste > 0 ? round((($activeWaste - $baselineWaste) / $baselineWaste) * 100, 1) : 0.0;

        return [
            'baseline_waste_pct' => $baselineWaste,
            'active_waste_pct' => $activeWaste,
            'delta_pct' => $deltaPct,
            'is_improvement' => $deltaPct < -0.05,
        ];
    }

    // Tab "1-Up Dieline" (viewer, mockup) -- geometri 1 unit (badan+lidah),
    // REAL dari field part yg sama dipakai Engine, bukan gambar statis.
    #[Computed]
    public function dielineOneUp(): ?array
    {
        $part = $this->part;

        if (! $part) {
            return null;
        }

        $bodyWmm = (float) ($part->cm_width ?? 0) * 10;
        $bodyHmm = (float) ($part->cm_length ?? 0) * 10;

        if ($bodyWmm <= 0 || $bodyHmm <= 0) {
            return null;
        }

        $side = $part->flap_side ?? 'TOP';
        $flap = (float) ($part->flap_length_mm ?? 0);

        $engine = new ImpositionEngine;
        [$flatW, $flatH] = $engine->applyFlap($bodyWmm, $bodyHmm, $side, $flap);

        $maxPx = 300;
        $scale = min($maxPx / max($flatW, 1), $maxPx / max($flatH, 1));

        return [
            'flat_w_mm' => round($flatW, 1),
            'flat_h_mm' => round($flatH, 1),
            'body_w_mm' => round($bodyWmm, 1),
            'body_h_mm' => round($bodyHmm, 1),
            'flap_mm' => round($flap, 1),
            'side' => $side,
            'flat_w_px' => round($flatW * $scale, 1),
            'flat_h_px' => round($flatH * $scale, 1),
            'body_w_px' => round($bodyWmm * $scale, 1),
            'body_h_px' => round($bodyHmm * $scale, 1),
            'flap_px' => round($flap * $scale, 1),
        ];
    }

    // Wizard step-bar lintas modul (mockup_FORM_prodev_header.png, user
    // 2026-09-03 "ya, bikin step-bar lintas modul juga") -- dihitung per job
    // AKTIF (part yg sedang dibuka). Inquiry/Costing cuma reachable kalau job
    // ini Mode #1 (originInquiryParts non-empty, ambil inquiry_id pertama --
    // 1 part reusable bisa >1 inquiry, dipakai yg PERTAMA/terbaru sbg wakil).
    #[Computed]
    public function wizardSteps(): array
    {
        if (! $this->part) {
            return [];
        }

        $origin = $this->originInquiryParts->first();
        $inquiryId = $origin['inquiry_id'] ?? null;

        return [
            'inquiry' => [
                // SELALU ada url (user 2026-09-04: "SELALU bisa klik ke Inquiry
                // Product") -- Mode #2 pun tetap diarahkan ke inquiry.form,
                // yang menampilkan pesan "tidak ada Inquiry" (noInquiryForView)
                // alih-alih tombol mati. view=1 + from_part = wajib supaya
                // form.blade.php tahu ini cross-nav (readOnly paksa) & tahu
                // jalan balik ke Development/Master Card part ini.
                'label' => '1. Inquiry',
                'url' => $inquiryId
                    ? route('inquiry.form', ['id' => $inquiryId, 'view' => 1])
                    : route('inquiry.form', ['view' => 1, 'from_part' => $this->partId]),
            ],
            'development' => [
                'label' => '2. Development',
                'url' => route('production.prodev', ['part' => $this->partId]),
            ],
            'costing' => [
                'label' => '3. Costing',
                'url' => $inquiryId ? route('inquiry.costing', ['inquiry' => $inquiryId, 'view' => 1]) : null,
                'reason' => 'Part ini Mode #2 (manual/standalone) -- belum ada Inquiry utk dihitung Costing-nya.',
            ],
            'mastercard' => [
                'label' => 'Master Card',
                'url' => route('production.prodev.print', ['part' => $this->partId]),
            ],
            'analytics' => [
                'label' => 'Analytics',
                'url' => null,
                'reason' => 'Modul Analytics belum dibangun.',
            ],
        ];
    }

    #[Computed]
    public function jobOptions()
    {
        return ProdPart::with('product')
            ->orderByDesc('id_part')
            ->limit(150)
            ->get()
            ->map(fn (ProdPart $p) => [
                'id' => $p->id_part,
                'label' => ($p->product?->product_name ?: $p->part_name).' · '.$p->displayMastercardCode(),
            ]);
    }

    public function switchJob(int $partId): void
    {
        $this->redirect(route('production.prodev', ['part' => $partId]));
    }

    // Highlight kartu Nesting Strategy mana yg lagi aktif (mockup) -- 'interlock'
    // kalau layout tersimpan punya shift_offset_mm > 0 (hasil Tingkat 2), selain
    // itu 'standard' (Tingkat 1, termasuk hasil Cari Plano Termurah/Tool #2 yg
    // internal-nya juga sweepFlap tanpa shift).
    public function activeNestingMethod(): ?string
    {
        $group = $this->activeLayoutGroup;

        if (! $group) {
            return null;
        }

        $shift = (float) (data_get($group->layout_detail, 'shift_offset_mm') ?? 0);

        return $shift > 0 ? 'interlock' : 'standard';
    }

    // "Sisa Bahan per Lembar" (Stock Specifications, mockup) -- luas plano
    // dikurangi luas terpakai layout aktif, REAL dari up_count x flat area.
    public function planoWasteAreaCm2(): ?float
    {
        $group = $this->activeLayoutGroup;
        $part = $this->part;

        if (! $group || ! $part || ! $group->plano_w_mm || ! $group->plano_h_mm) {
            return null;
        }

        $bodyW = (float) ($part->cm_width ?? 0) * 10;
        $bodyH = (float) ($part->cm_length ?? 0) * 10;
        $flap = (float) (data_get($group->layout_detail, 'flap_length_mm') ?? $part->flap_length_mm ?? 0);
        $side = $part->flap_side ?? 'TOP';

        $engine = new ImpositionEngine;
        [$flatW, $flatH] = $engine->applyFlap($bodyW, $bodyH, $side, $flap);

        $planoAreaMm2 = (float) $group->plano_w_mm * (float) $group->plano_h_mm;
        $usedAreaMm2 = $flatW * $flatH * (int) $group->up_count;

        return round(max(0, $planoAreaMm2 - $usedAreaMm2) / 100, 1); // mm2 -> cm2
    }

    // Viewer "2D Sheet" (mockup) saat TIDAK sedang milih kandidat baru --
    // regenerasi cell dari layout_detail YANG SUDAH TERSIMPAN (bukan gambar
    // statis) via ImpositionEngine::cells() yang SAMA dipakai runEngine*().
    // Garis crease (badan<->lidah) dihitung dari rasio flap/(body+flap) pada
    // sumbu tempat lidah nempel -- utk layout 'grid' semua cell rot-nya sama
    // (lihat ImpositionEngine::cells()), utk 'mix' tiap cell bisa beda, jadi
    // rasio dihitung PER CELL berdasar cell['rot']-nya sendiri (akurat utk 2 kasus).
    #[Computed]
    public function activeLayoutCells(): ?array
    {
        $group = $this->activeLayoutGroup;
        $part = $this->part;

        if (! $group || ! $part || ! $group->layout_detail || ! $group->plano_w_mm || ! $group->plano_h_mm) {
            return null;
        }

        $layout = $group->layout_detail['layout'] ?? null;

        if (! $layout) {
            return null;
        }

        $engine = new ImpositionEngine;
        $cells = $engine->cells(['layout' => $layout], $this->marginMm(), $this->gripperMm(), $this->gapMm());

        $bodyW = (float) ($part->cm_width ?? 0) * 10;
        $bodyH = (float) ($part->cm_length ?? 0) * 10;
        $flap = (float) (data_get($group->layout_detail, 'flap_length_mm') ?? 0);
        $side = $part->flap_side ?? 'TOP';
        $flapOnStartEdge = in_array($side, ['TOP', 'LEFT'], true); // flap digambar di edge x=0/y=0 cell
        $flapAlongWidth = in_array($side, ['LEFT', 'RIGHT'], true); // sumbu lidah = W (LEFT/RIGHT) atau H (TOP/BOTTOM)

        $maxW = 640;
        $maxH = 380;
        $scale = min($maxW / (float) $group->plano_w_mm, $maxH / (float) $group->plano_h_mm);

        return [
            'boxW' => round((float) $group->plano_w_mm * $scale, 1),
            'boxH' => round((float) $group->plano_h_mm * $scale, 1),
            'gripperPx' => round($this->gripperMm() * $scale, 1),
            'cells' => collect($cells)->map(function ($c) use ($scale, $flap, $flapAlongWidth, $flapOnStartEdge) {
                // rot=false: sumbu asli. rot=true (dirotasi 90deg oleh engine): sumbu W/H tertukar.
                $creaseIsVertical = $c['rot'] ? ! $flapAlongWidth : $flapAlongWidth; // garis crease vertikal (memisah kiri/kanan) kalau sumbu lidah = W
                $dim = $creaseIsVertical ? $c['w'] : $c['h'];
                $frac = $dim > 0 ? min(1, max(0, $flap / $dim)) : 0;

                return [
                    'left' => round($c['x'] * $scale, 1),
                    'top' => round($c['y'] * $scale, 1),
                    'width' => max(0, round($c['w'] * $scale, 1) - 1),
                    'height' => max(0, round($c['h'] * $scale, 1) - 1),
                    'rot' => $c['rot'],
                    'crease_vertical' => $creaseIsVertical,
                    'crease_pct' => round(($flapOnStartEdge ? $frac : 1 - $frac) * 100, 2),
                ];
            })->all(),
        ];
    }

    // GAP ditemukan user 2026-09-03 (mode #1 hanya bikin LINK, tak pernah
    // TAMPILKAN data asalnya) -- reverse-lookup semua iqp_part (bisa >1, part
    // reusable lintas-order) yg nunjuk ke prod_part ini via prod_part_id, lalu
    // ambil label param dari ProdType (dieline_spec['dims'] cuma simpan
    // key=>value mentah, labelnya ada di prod_type_param). READ-ONLY, murni
    // referensi -- ProDev tetap yg isi field teknisnya sendiri (§4.4 data flow).
    #[Computed]
    public function originInquiryParts()
    {
        if (! $this->part) {
            return collect();
        }

        return IqpPart::with('inquiry.customer')
            ->where('prod_part_id', $this->part->id_part)
            // HANYA Inquiry yang CONFIRMED & masih HIDUP -- pola PERSIS sama
            // dgn costing-list.blade.php/imposition-list.blade.php
            // (whereNotIn sub_state DRAFT/CANCELLED/LOST), bukan ditemukan
            // sendiri. GOTCHA yg sempat kelewat: stat_confirm TETAP true
            // SELAMANYA walau Inquiry belakangan di-CANCEL/LOST (yg berubah
            // cuma sub_state, lihat cancel()/markLost() di inquiry/form.blade.php)
            // -- cek stat_confirm SAJA TIDAK CUKUP, harus digabung sub_state.
            ->whereHas('inquiry', fn ($q) => $q->where('stat_confirm', true)
                ->whereNotIn('sub_state', ['DRAFT', 'CANCELLED', 'LOST']))
            ->orderByDesc('id_part')
            ->get()
            ->map(function (IqpPart $ip) {
                $spec = $ip->dieline_spec ?? [];
                $typeCode = $spec['type_code'] ?? null;
                $type = $typeCode ? \App\Models\Master\ProdType::with('params')->find($typeCode) : null;
                $dims = $spec['dims'] ?? [];

                $dimRows = collect($type?->params ?? [])
                    ->filter(fn ($p) => array_key_exists($p->param_key, $dims) && $dims[$p->param_key] !== '' && $dims[$p->param_key] !== null)
                    ->map(fn ($p) => ['label' => $p->param_label, 'value' => $dims[$p->param_key], 'uom' => $p->unit])
                    ->values();

                return [
                    'inquiry_id' => $ip->inquiry_id,
                    'inquiry_no' => $ip->inquiry?->inquiry_no,
                    'customer_name' => $ip->inquiry?->customer?->customer_name,
                    'part_name' => $ip->part_name,
                    'type_name' => $type?->type_name,
                    'dims' => $dimRows,
                    'flat_w' => $ip->flat_w,
                    'flat_h' => $ip->flat_h,
                    'flat_area' => $ip->flat_area,
                    'qty_per_sku' => $ip->qty_per_sku,
                ];
            });
    }

    #[Computed]
    public function planoSizes()
    {
        return MatPlanoSize::where('activate', true)->orderBy('sort_order')->get();
    }

    // Tool #10 -- part LAIN dalam produk yg SAMA (§4.2b: gabung/pisah cuma
    // relevan intra-product, beda dari Tool #5 gang-run lintas-produk).
    #[Computed]
    public function combinableParts()
    {
        if (! $this->part) {
            return collect();
        }

        return ProdPart::where('product_id', $this->part->product_id)
            ->where('id_part', '!=', $this->part->id_part)
            ->orderBy('part_name')
            ->get();
    }

    #[Computed]
    public function processOptions()
    {
        return ProdProcess::where('activate', true)->orderBy('process_name')->get();
    }

    #[Computed]
    public function machineOptions()
    {
        return ProdMachine::where('activate', true)->orderBy('machine_name')->get();
    }

    #[Computed]
    public function finishingOptions()
    {
        return RefFinishingType::where('activate', true)->orderBy('finishing_name')->get();
    }

    #[Computed]
    public function uploadMaxMb(): float
    {
        return SysConstant::number('PRODEV_UPLOAD_MAX_MB', 25);
    }

    // Parameter fisik mesin cetak (gap antar-unit, gripper margin, margin tepi
    // plano) -- SEBELUMNYA hardcode 3.0/12.0/8.0 tersebar di 4 method beda,
    // dipindah ke sys_constant (migration 2026_09_03_000014, pola sama
    // PRODEV_FLAP_MIN_MM) supaya 1 sumber kebenaran & bisa di-adjust admin
    // tanpa deploy. Ditampilkan sbg "Machine Specifications" (redesign
    // mockup_FORM_prodev_2.png) -- angka NYATA yg dipakai Engine, bukan dekorasi.
    #[Computed]
    public function gapMm(): float
    {
        return (float) SysConstant::number('PRODEV_GAP_MM', 3.0);
    }

    #[Computed]
    public function gripperMm(): float
    {
        return (float) SysConstant::number('PRODEV_GRIPPER_MM', 12.0);
    }

    #[Computed]
    public function marginMm(): float
    {
        return (float) SysConstant::number('PRODEV_MARGIN_MM', 8.0);
    }

    public function updateField(string $field, $value): void
    {
        $allowed = [
            'mastercard_code', 'die_code', 'die_w_cm', 'die_h_cm',
            'sheet_raw_w_cm', 'sheet_raw_h_cm', 'printsize_p', 'printsize_l',
            'joint_type', 'pack_qty', 'cm_length', 'cm_width',
            'flap_side', 'flap_length_mm', 'flap_min_mm', 'flap_max_mm',
            'flap_width_mm', 'flap_offset_mm',
        ];

        $part = $this->part;

        if (! in_array($field, $allowed, true) || ! $part) {
            return;
        }

        $part->update([$field => $value === '' ? null : $value, 'updated_by' => Auth::user()->login]);
        unset($this->part);
    }

    public function updateColorSet(string $field, string $csv): void
    {
        $part = $this->part;

        if (! in_array($field, ['color_set_1', 'color_set_2'], true) || ! $part) {
            return;
        }

        $colors = array_values(array_filter(array_map('trim', explode(',', $csv))));
        $part->update([$field => $colors, 'updated_by' => Auth::user()->login]);
        unset($this->part);
    }

    // ENGINE Tingkat 1 (§4.3a) -- parametric flap sweep. Badan (sebelum lidah)
    // diambil dari cm_width/cm_length part (dikonversi cm->mm), rentang lidah
    // dari flap_min_mm/flap_max_mm part (fallback PRODEV_FLAP_MIN_MM kalau
    // belum diisi, +30mm sbg batas atas sementara kalau flap_max juga kosong).
    public function runEngineTingkat1(): void
    {
        $part = $this->part;
        $plano = MatPlanoSize::find($this->selectedPlanoId);

        if (! $part || ! $plano) {
            return;
        }

        $bodyW = (float) ($part->cm_width ?? 0) * 10;
        $bodyH = (float) ($part->cm_length ?? 0) * 10;

        if ($bodyW <= 0 || $bodyH <= 0) {
            session()->flash('error', 'Isi dimensi badan part (cm_width/cm_length) dulu sebelum jalankan Engine.');

            return;
        }

        $flapMin = (float) ($part->flap_min_mm ?? SysConstant::number('PRODEV_FLAP_MIN_MM', 20));
        $flapMax = (float) ($part->flap_max_mm ?? ($flapMin + 30));
        $side = $part->flap_side ?? 'TOP';

        $engine = new ImpositionEngine;
        $results = $engine->sweepFlap(
            (float) $plano->width_mm, (float) $plano->height_mm,
            $bodyW, $bodyH, $side, $flapMin, $flapMax, 5.0,
            gap: $this->gapMm(), gripper: $this->gripperMm(), margin: $this->marginMm(), topN: 5
        );

        foreach ($results as $i => $r) {
            $cells = $engine->cells($r, $this->marginMm(), $this->gripperMm(), $this->gapMm());
            $maxW = 260;
            $maxH = 170;
            $scale = min($maxW / (float) $plano->width_mm, $maxH / (float) $plano->height_mm);
            $results[$i]['boxW'] = round((float) $plano->width_mm * $scale, 1);
            $results[$i]['boxH'] = round((float) $plano->height_mm * $scale, 1);
            $results[$i]['cellsVisual'] = collect($cells)->map(fn ($c) => [
                'left' => round($c['x'] * $scale, 1),
                'top' => round($c['y'] * $scale, 1),
                'width' => max(0, round($c['w'] * $scale, 1) - 1),
                'height' => max(0, round($c['h'] * $scale, 1) - 1),
                'rot' => $c['rot'],
            ])->all();
        }

        $this->sweepCandidates = $results;
    }

    // ENGINE Tingkat 2 (§4.3a REVISI) -- perluasan Tingkat 1 dgn variabel shift_offset_mm
    // (lever utama, interlock "69"). Reuse ImpositionEngine::sweepInterlock() -- lihat
    // disclaimer "belum divalidasi dataset riil" di docblock method itu.
    public function runEngineTingkat2(): void
    {
        $part = $this->part;
        $plano = MatPlanoSize::find($this->selectedPlanoId);

        if (! $part || ! $plano) {
            return;
        }

        $bodyW = (float) ($part->cm_width ?? 0) * 10;
        $bodyH = (float) ($part->cm_length ?? 0) * 10;

        if ($bodyW <= 0 || $bodyH <= 0) {
            session()->flash('error', 'Isi dimensi badan part (cm_width/cm_length) dulu sebelum jalankan Engine.');

            return;
        }

        $flapMin = (float) ($part->flap_min_mm ?? SysConstant::number('PRODEV_FLAP_MIN_MM', 20));
        $flapMax = (float) ($part->flap_max_mm ?? ($flapMin + 30));
        $side = $part->flap_side ?? 'TOP';

        $engine = new ImpositionEngine;
        $results = $engine->sweepInterlock(
            (float) $plano->width_mm, (float) $plano->height_mm,
            $bodyW, $bodyH, $side, $flapMin, $flapMax, 5.0,
            shiftStep: 5.0, gap: $this->gapMm(), gripper: $this->gripperMm(), margin: $this->marginMm(), topN: 5
        );

        foreach ($results as $i => $r) {
            $cells = $engine->cells($r, $this->marginMm(), $this->gripperMm(), $this->gapMm());
            $maxW = 260;
            $maxH = 170;
            $scale = min($maxW / (float) $plano->width_mm, $maxH / (float) $plano->height_mm);
            $results[$i]['boxW'] = round((float) $plano->width_mm * $scale, 1);
            $results[$i]['boxH'] = round((float) $plano->height_mm * $scale, 1);
            $results[$i]['cellsVisual'] = collect($cells)->map(fn ($c) => [
                'left' => round($c['x'] * $scale, 1),
                'top' => round($c['y'] * $scale, 1),
                'width' => max(0, round($c['w'] * $scale, 1) - 1),
                'height' => max(0, round($c['h'] * $scale, 1) - 1),
                'rot' => $c['rot'],
            ])->all();
        }

        $this->sweepCandidates = $results;
    }

    // Tool #2 (Pcs->Plano / Best Sheet Finder, BACKLOG.md §0) -- BEDA dari
    // Tingkat1/2 yg mengunci 1 plano dulu (selectedPlanoId), di sini LOOP semua
    // plano aktif & ranking lintas-plano by biaya (ImpositionEngine::bestSheet()).
    // Hasil disimpan ke sweepCandidates yang SAMA (bukan property baru) supaya
    // pickCandidate()/render candidate card reuse penuh -- 1 elemen bestSheet()
    // shape-nya identik sweepFlap() + extra key plano_size_id/plano_name/cost_metric.
    public function runEngineBestSheet(): void
    {
        $part = $this->part;

        if (! $part) {
            return;
        }

        $bodyW = (float) ($part->cm_width ?? 0) * 10;
        $bodyH = (float) ($part->cm_length ?? 0) * 10;

        if ($bodyW <= 0 || $bodyH <= 0) {
            session()->flash('error', 'Isi dimensi badan part (cm_width/cm_length) dulu sebelum jalankan Engine.');

            return;
        }

        $flapMin = (float) ($part->flap_min_mm ?? SysConstant::number('PRODEV_FLAP_MIN_MM', 20));
        $flapMax = (float) ($part->flap_max_mm ?? ($flapMin + 30));
        $side = $part->flap_side ?? 'TOP';

        $engine = new ImpositionEngine;
        $results = $engine->bestSheet(
            $this->planoSizes, $bodyW, $bodyH, $side, $flapMin, $flapMax, 5.0,
            gap: $this->gapMm(), gripper: $this->gripperMm(), margin: $this->marginMm(), targetQty: null, topN: 5
        );

        foreach ($results as $i => $r) {
            $plano = MatPlanoSize::find($r['plano_size_id']);
            $cells = $engine->cells($r, $this->marginMm(), $this->gripperMm(), $this->gapMm());
            $maxW = 260;
            $maxH = 170;
            $scale = min($maxW / (float) $plano->width_mm, $maxH / (float) $plano->height_mm);
            $results[$i]['boxW'] = round((float) $plano->width_mm * $scale, 1);
            $results[$i]['boxH'] = round((float) $plano->height_mm * $scale, 1);
            $results[$i]['cellsVisual'] = collect($cells)->map(fn ($c) => [
                'left' => round($c['x'] * $scale, 1),
                'top' => round($c['y'] * $scale, 1),
                'width' => max(0, round($c['w'] * $scale, 1) - 1),
                'height' => max(0, round($c['h'] * $scale, 1) - 1),
                'rot' => $c['rot'],
            ])->all();
        }

        $this->sweepCandidates = $results;
    }

    public function pickCandidate(int $idx): void
    {
        $part = $this->part;
        $cand = $this->sweepCandidates[$idx] ?? null;
        // Kandidat dari Tool #2 (bestSheet) bawa plano_size_id sendiri (bisa beda
        // dari selectedPlanoId officer) -- kandidat Tingkat1/2 tidak, fallback ke
        // selectedPlanoId spt semula.
        $plano = MatPlanoSize::find($cand['plano_size_id'] ?? $this->selectedPlanoId);

        if (! $part || ! $cand || ! $plano) {
            return;
        }

        $existing = $part->layoutGroups->sortByDesc('id_layout_group')->first();
        $login = Auth::user()->login;

        // Tool #4 (Die-Rule Estimator, BACKLOG.md §0) -- otomatis dihitung begitu
        // officer pilih kandidat, bukan langkah manual terpisah. Common Knife
        // (cutting) + crease per-unit, lihat docblock dieRuleMeterLari().
        $engine = new ImpositionEngine;
        $dieRuleMeter = $engine->dieRuleMeterLari(
            $cand['layout'], $cand['up'], $cand['flap_length_mm'], $part->flap_side ?? 'TOP',
            (float) ($part->cm_width ?? 0) * 10, (float) ($part->cm_length ?? 0) * 10, $this->gapMm()
        );

        $payload = [
            'product_id' => $part->product_id,
            'plano_size_id' => $plano->id_plano_size,
            'plano_w_mm' => $plano->width_mm,
            'plano_h_mm' => $plano->height_mm,
            'method' => $cand['method'],
            'up_count' => $cand['up'],
            'efficiency_pct' => $cand['efficiency_pct'],
            'waste_pct' => $cand['waste_pct'],
            'die_rule_meter' => $dieRuleMeter,
            'layout_detail' => [
                'layout' => $cand['layout'],
                'flap_length_mm' => $cand['flap_length_mm'],
                'shift_offset_mm' => $cand['shift_offset_mm'] ?? null,
            ],
            'updated_by' => $login,
        ];

        if ($existing && $existing->status !== 'CONFIRMED') {
            $existing->update($payload);
            $group = $existing;
        } else {
            $payload['created_by'] = $login;
            $payload['status'] = 'DRAFT';
            $group = ProdLayoutGroup::create($payload);
            $group->parts()->attach($part->id_part, ['qty_up_in_group' => 1]);
        }

        if (! $group->mastercard_code) {
            $group->update(['mastercard_code' => "MC-{$group->id_layout_group}"]);
        }

        $part->update(['flap_length_mm' => $cand['flap_length_mm'], 'updated_by' => $login]);

        // Sinkronkan dropdown plano ke plano yg BENAR-BENAR dipakai kandidat --
        // penting utk hasil Tool #2 (bestSheet) yg bisa datang dari plano BEDA
        // dari yg sebelumnya terpilih di dropdown.
        $this->selectedPlanoId = $plano->id_plano_size;
        $this->sweepCandidates = [];
        unset($this->part, $this->activeLayoutGroup);
    }

    // Tool #10 -- MVP 2-part (BACKLOG.md §0, "prioritas tertinggi", lihat
    // disclaimer & formula lengkap di ImpositionEngine::combinePair()). Dimensi
    // flat tiap part pakai lidah TERAKHIR yg sudah pernah dipilih (flap_length_mm
    // tersimpan dari sweep solo sebelumnya via pickCandidate()) -- kalau part
    // belum pernah di-sweep solo, flap_length_mm masih null = dipakai sbg 0
    // (body mentah tanpa lidah, applyFlap(...,0) = body tak berubah).
    public function runToolCombine(): void
    {
        $partA = $this->part;
        $partB = $this->combineWithPartId ? ProdPart::find($this->combineWithPartId) : null;
        $plano = MatPlanoSize::find($this->selectedPlanoId);

        if (! $partA || ! $partB || ! $plano) {
            session()->flash('error', 'Pilih part pasangan (produk yang sama) dulu sebelum jalankan Tool #10.');

            return;
        }

        $bodyWA = (float) ($partA->cm_width ?? 0) * 10;
        $bodyHA = (float) ($partA->cm_length ?? 0) * 10;
        $bodyWB = (float) ($partB->cm_width ?? 0) * 10;
        $bodyHB = (float) ($partB->cm_length ?? 0) * 10;

        if ($bodyWA <= 0 || $bodyHA <= 0 || $bodyWB <= 0 || $bodyHB <= 0) {
            session()->flash('error', 'Isi dimensi badan KEDUA part (cm_width/cm_length) dulu sebelum jalankan Tool #10.');

            return;
        }

        $engine = new ImpositionEngine;
        [$flatWA, $flatHA] = $engine->applyFlap($bodyWA, $bodyHA, $partA->flap_side ?? 'TOP', (float) ($partA->flap_length_mm ?? 0));
        [$flatWB, $flatHB] = $engine->applyFlap($bodyWB, $bodyHB, $partB->flap_side ?? 'TOP', (float) ($partB->flap_length_mm ?? 0));

        $qtyA = (float) ($partA->qty_per_sku ?: 1);
        $qtyB = (float) ($partB->qty_per_sku ?: 1);

        $this->combineResult = $engine->combinePair(
            (float) $plano->width_mm, (float) $plano->height_mm,
            $flatWA, $flatHA, $qtyA, $flatWB, $flatHB, $qtyB,
            gap: $this->gapMm(), gripper: $this->gripperMm(), margin: $this->marginMm(), splitStep: 10.0
        );
        $this->combineResult['part_a_name'] = $partA->part_name;
        $this->combineResult['part_b_name'] = $partB->part_name;
    }

    // Terapkan rekomendasi COMBINED -- bikin 1 prod_layout_group BARU (bukan
    // timpa group solo existing masing² part, biar riwayat solo tetap ada)
    // menaungi KEDUA part sekaligus, qty_up_in_group dari hasil split.
    public function confirmCombine(): void
    {
        $partA = $this->part;
        $partB = $this->combineWithPartId ? ProdPart::find($this->combineWithPartId) : null;
        $plano = MatPlanoSize::find($this->selectedPlanoId);
        $result = $this->combineResult;

        if (! $partA || ! $partB || ! $plano || ! $result || $result['recommend'] !== 'COMBINED' || ! $result['combined']) {
            return;
        }

        $login = Auth::user()->login;
        $c = $result['combined'];

        $bodyWA = (float) ($partA->cm_width ?? 0) * 10;
        $bodyHA = (float) ($partA->cm_length ?? 0) * 10;
        $bodyWB = (float) ($partB->cm_width ?? 0) * 10;
        $bodyHB = (float) ($partB->cm_length ?? 0) * 10;
        $engine = new ImpositionEngine;
        [$flatWA, $flatHA] = $engine->applyFlap($bodyWA, $bodyHA, $partA->flap_side ?? 'TOP', (float) ($partA->flap_length_mm ?? 0));
        [$flatWB, $flatHB] = $engine->applyFlap($bodyWB, $bodyHB, $partB->flap_side ?? 'TOP', (float) ($partB->flap_length_mm ?? 0));
        $usedAreaMm2 = $c['up_a'] * $flatWA * $flatHA + $c['up_b'] * $flatWB * $flatHB;
        $planoAreaMm2 = (float) $plano->width_mm * (float) $plano->height_mm;
        $efficiencyPct = $planoAreaMm2 > 0 ? round(min(100, max(0, $usedAreaMm2 / $planoAreaMm2 * 100)), 2) : 0;

        $group = ProdLayoutGroup::create([
            'product_id' => $partA->product_id,
            'plano_size_id' => $plano->id_plano_size,
            'plano_w_mm' => $plano->width_mm,
            'plano_h_mm' => $plano->height_mm,
            'method' => 'COMBINED_SPLIT',
            'up_count' => $c['up_a'] + $c['up_b'],
            'efficiency_pct' => $efficiencyPct,
            'waste_pct' => round(100 - $efficiencyPct, 2),
            // die_rule_meter TIDAK dihitung otomatis di sini -- Common Knife
            // formula Tool #4 (dieRuleMeterLari()) butuh struktur `layout`
            // grid/mix tunggal dari run(), belum ada varian utk 2-region split
            // gabungan. Officer isi manual di Costing kalau perlu, atau susulan.
            'layout_detail' => [
                'type' => 'combined_split',
                'split_x_mm' => $c['split_x_mm'],
                'part_a' => ['id' => $partA->id_part, 'up' => $c['up_a']],
                'part_b' => ['id' => $partB->id_part, 'up' => $c['up_b']],
                'sets_per_sheet' => $c['sets_per_sheet'],
                'saving_pct_vs_separate' => $result['saving_pct'],
            ],
            'status' => 'DRAFT',
            'created_by' => $login,
            'updated_by' => $login,
        ]);
        $group->update(['mastercard_code' => "MC-{$group->id_layout_group}"]);
        $group->parts()->attach([
            $partA->id_part => ['qty_up_in_group' => $c['up_a']],
            $partB->id_part => ['qty_up_in_group' => $c['up_b']],
        ]);

        $this->combineResult = null;
        $this->combineWithPartId = null;
        unset($this->part, $this->activeLayoutGroup);
    }

    public function confirmLayout(): void
    {
        $group = $this->activeLayoutGroup;

        if (! $group || $group->up_count < 1) {
            return;
        }

        $group->update(['status' => 'CONFIRMED', 'confirmed_by' => Auth::user()->login, 'confirmed_at' => now()]);
        unset($this->activeLayoutGroup, $this->part);
    }

    public function unlockLayout(): void
    {
        $group = $this->activeLayoutGroup;

        if (! $group) {
            return;
        }

        // GUARD ditemukan user 2026-09-03 -- kalau Costing SUDAH menarik layout
        // ini (Ambil Data, pickLayoutGroup() di costing.blade.php), unlock+ubah
        // di sini bikin HPP yang sudah dihitung (bahkan mungkin sudah masuk
        // Quotation ke customer) diam-diam basi TANPA peringatan siapa pun.
        // Cross-DB tm2mst->tm2sdi, query terpisah (CLAUDE.md §2 pola 1).
        $usedByCosting = \App\Models\Sdi\IqpCostingPart::where('prod_layout_group_id', $group->id_layout_group)->exists();

        if ($usedByCosting) {
            session()->flash('error', 'Layout ini SUDAH ditarik Costing (Ambil Data) -- buka kunci akan bikin HPP yang sudah dihitung jadi basi tanpa peringatan. Koordinasi dgn Costing dulu, atau jalankan Engine lagi (otomatis bikin layout BARU, bukan timpa yang lama).');

            return;
        }

        $group->update(['status' => 'DRAFT', 'confirmed_by' => null, 'confirmed_at' => null]);
        unset($this->activeLayoutGroup, $this->part);
    }

    public function addProcess(): void
    {
        $part = $this->part;

        if (! $part || ! $this->newProcessId) {
            return;
        }

        $nextSeq = ((int) $part->processes->max('step_seq')) + 10;

        ProdPartProcess::create([
            'part_id' => $part->id_part,
            'branch_code' => 'MAIN',
            'step_seq' => $nextSeq,
            'flow_type' => 'LINEAR',
            'process_id' => $this->newProcessId,
            'machine_id' => $this->newMachineId ?: null,
            'note' => $this->newProcessNotes ?: null,
            'created_by' => Auth::user()->login,
        ]);

        $this->reset(['newProcessId', 'newMachineId', 'newProcessNotes']);
        unset($this->part);
    }

    public function removeProcess(int $id): void
    {
        ProdPartProcess::where('part_id', $this->partId)->where('id_part_process', $id)->delete();
        unset($this->part);
    }

    public function addFinishing(): void
    {
        $part = $this->part;

        if (! $part || ! $this->newFinishingTypeId) {
            return;
        }

        $nextSeq = ((int) $part->finishings->max('seq')) + 10;

        ProdPartFinishing::create([
            'part_id' => $part->id_part,
            'seq' => $nextSeq,
            'finishing_type_id' => $this->newFinishingTypeId,
            'notes' => $this->newFinishingNotes ?: null,
            'created_by' => Auth::user()->login,
        ]);

        $this->reset(['newFinishingTypeId', 'newFinishingNotes']);
        unset($this->part);
    }

    public function removeFinishing(int $id): void
    {
        ProdPartFinishing::where('part_id', $this->partId)->where('id_part_finishing', $id)->delete();
        unset($this->part);
    }

    public function uploadAttachment(): void
    {
        $part = $this->part;

        if (! $part || ! $this->newAttachmentFile) {
            return;
        }

        $this->validate([
            'newAttachmentFile' => 'file|max:'.($this->uploadMaxMb * 1024),
        ]);

        $path = $this->newAttachmentFile->store("prodev/{$part->id_part}/attachment", 'public');

        ProdPartAttachment::create([
            'part_id' => $part->id_part,
            'file_type' => $this->newAttachmentType,
            'file_path' => $path,
            'original_filename' => $this->newAttachmentFile->getClientOriginalName(),
            'uploaded_by' => Auth::user()->login,
        ]);

        $this->reset(['newAttachmentFile']);
        unset($this->part);
    }

    public function deleteAttachment(int $id): void
    {
        ProdPartAttachment::where('part_id', $this->partId)->where('id_attachment', $id)->delete();
        unset($this->part);
    }
};
?>

@php $__pdTheme = $this->theme; @endphp
<div class="pd-wb min-h-screen bg-[var(--pd-bg)] text-[var(--pd-ink)]" style="
    --pd-bg: {{ $__pdTheme->mode === 'dark' ? ($__pdTheme->content_bg_color ?: '#12162a') : ($__pdTheme->content_bg_color ?: '#eef1f6') }};
    --pd-surface: {{ $__pdTheme->mode === 'dark' ? '#1b2140' : '#ffffff' }};
    --pd-surface-alt: {{ $__pdTheme->mode === 'dark' ? '#20264a' : '#f4f6fa' }};
    --pd-border: {{ $__pdTheme->mode === 'dark' ? '#2a3157' : '#d7dbe2' }};
    --pd-ink: {{ $__pdTheme->mode === 'dark' ? '#c7cee8' : '#3a4256' }};
    --pd-ink-strong: {{ $__pdTheme->mode === 'dark' ? '#eef1ff' : '#1c2440' }};
    --pd-muted: {{ $__pdTheme->mode === 'dark' ? '#8089ac' : '#8a92ad' }};
    --pd-input-bg: {{ $__pdTheme->mode === 'dark' ? '#161b36' : '#ffffff' }};
    --pd-info-bg: {{ $__pdTheme->mode === 'dark' ? '#132540' : '#eef3fb' }};
    --pd-info-bg-strong: {{ $__pdTheme->mode === 'dark' ? '#183055' : '#e0ecfa' }};
    --pd-info-border: {{ $__pdTheme->mode === 'dark' ? '#25406b' : '#c3d8f5' }};
    --pd-info-ink: {{ $__pdTheme->mode === 'dark' ? '#8fc1ff' : '#1560a8' }};
">
    @if ($this->part)
        <x-prodev.wizard-bar current="development" :steps="$this->wizardSteps" :jobs="$this->jobOptions" :selected-job-id="$this->partId" :back-url="route('production.prodev.list')" />
    @endif

    <div class="bg-[var(--pd-surface)] border-b border-[var(--pd-border)] px-5 py-2 flex items-center gap-3.5">
        @if ($this->part)
            <span class="text-xs text-[var(--pd-ink)] font-semibold">{{ $this->part->displayMastercardCode() }} · {{ $this->part->part_name }}</span>
            <span class="text-[10.5px] font-bold px-2.5 py-1 rounded-full {{ $this->layoutStatusBadgeClass() }}">{{ $this->layoutStatusLabel() }}</span>
            <a href="{{ route('production.prodev.print', ['part' => $this->partId]) }}" target="_blank"
                class="ml-auto bg-accent text-white text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">🖨️ Cetak Master Card</a>
        @endif
    </div>

    <div class="max-w-[1320px] mx-auto my-5 px-4">

        @if (session('error'))
            <div class="bg-[#fdecea] border border-[#f3b8b0] text-[#a1362b] text-xs px-3.5 py-2.5 rounded-md mb-3.5">{{ session('error') }}</div>
        @endif

        @if ($this->part)
        <div class="flex flex-col gap-3.5">

            {{-- ============ REFERENSI INQUIRY ASAL (Mode #1, read-only) ============
            Cuma tampil kalau ADA iqp_part yang link ke prod_part ini DAN Inquiry-nya
            CONFIRMED (bukan draft/cancelled/lost, lihat originInquiryParts()). Murni
            referensi biar ProDev tak perlu tanya-tanya Sales -- TIDAK bisa diedit dari
            sini (snapshot/beku, CLAUDE.md §4), field teknis ProDev sendiri di bawah
            tetap fully-editable seperti biasa. --}}
            @if ($this->originInquiryParts->isNotEmpty())
                <div class="bg-[var(--pd-info-bg)] border border-[var(--pd-info-border)] rounded-md overflow-hidden">
                    <h3 class="text-xs text-[var(--pd-info-ink)] uppercase font-bold px-3.5 py-2 bg-[var(--pd-info-bg-strong)] border-b border-[var(--pd-info-border)]">
                        📥 Referensi Inquiry Asal (read-only, sudah CONFIRMED)
                    </h3>
                    <div class="p-3.5 flex flex-col gap-2.5">
                        @foreach ($this->originInquiryParts as $oi)
                            <div class="text-[11.5px] text-[var(--pd-info-ink)] {{ ! $loop->last ? 'pb-2.5 border-b border-[var(--pd-info-border)]' : '' }}">
                                <div class="font-bold mb-1">
                                    {{ $oi['inquiry_no'] }} · {{ $oi['customer_name'] }} · {{ $oi['part_name'] }}
                                    @if ($oi['type_name'])<span class="opacity-70">({{ $oi['type_name'] }})</span>@endif
                                </div>
                                <div class="flex flex-wrap gap-x-4 gap-y-1">
                                    @foreach ($oi['dims'] as $d)
                                        <span>{{ $d['label'] }}: <b>{{ $d['value'] }}{{ $d['uom'] ? ' '.$d['uom'] : '' }}</b></span>
                                    @endforeach
                                    @if ($oi['flat_w'])<span>Flat W: <b>{{ $oi['flat_w'] }}mm</b></span>@endif
                                    @if ($oi['flat_h'])<span>Flat H: <b>{{ $oi['flat_h'] }}mm</b></span>@endif
                                    @if ($oi['qty_per_sku'])<span>Qty/Set: <b>{{ $oi['qty_per_sku'] }}</b></span>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ============ SPESIFIKASI TEKNIS ============ --}}
            <div class="bg-[var(--pd-surface)] border border-[var(--pd-border)] rounded-md overflow-hidden">
                <h3 class="text-xs text-[var(--pd-ink-strong)] uppercase font-bold px-3.5 py-2 bg-[var(--pd-surface-alt)] border-b border-[var(--pd-border)]">📋 Spesifikasi Teknis (Master Card)</h3>
                <div class="p-3.5 grid three gap-2.5">
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Kode Item</label>
                        <input type="text" value="{{ $this->part->mastercard_code }}" placeholder="{{ $this->part->displayMastercardCode() }}"
                            wire:change="updateField('mastercard_code', $event.target.value)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px]">
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Kode Pisau (Die Code)</label>
                        <input type="text" value="{{ $this->part->die_code }}" wire:change="updateField('die_code', $event.target.value)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px]">
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Bentuk Sambungan (Join Type)</label>
                        <input type="text" value="{{ $this->part->joint_type }}" wire:change="updateField('joint_type', $event.target.value)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px]">
                    </div>

                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Ukuran Pisau W x H (cm)</label>
                        <div class="flex gap-1.5">
                            <input type="number" step="0.1" value="{{ $this->part->die_w_cm }}" wire:change="updateField('die_w_cm', $event.target.value)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px] w-1/2">
                            <input type="number" step="0.1" value="{{ $this->part->die_h_cm }}" wire:change="updateField('die_h_cm', $event.target.value)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px] w-1/2">
                        </div>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Ukuran Bahan Mentah W x H (cm)</label>
                        <div class="flex gap-1.5">
                            <input type="number" step="0.1" value="{{ $this->part->sheet_raw_w_cm }}" wire:change="updateField('sheet_raw_w_cm', $event.target.value)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px] w-1/2">
                            <input type="number" step="0.1" value="{{ $this->part->sheet_raw_h_cm }}" wire:change="updateField('sheet_raw_h_cm', $event.target.value)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px] w-1/2">
                        </div>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Ukuran Cetak P x L (cm)</label>
                        <div class="flex gap-1.5">
                            <input type="number" step="0.1" value="{{ $this->part->printsize_p }}" wire:change="updateField('printsize_p', $event.target.value)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px] w-1/2">
                            <input type="number" step="0.1" value="{{ $this->part->printsize_l }}" wire:change="updateField('printsize_l', $event.target.value)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px] w-1/2">
                        </div>
                    </div>

                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Dimensi Badan (Body) P x L (cm) — dasar Engine</label>
                        <div class="flex gap-1.5">
                            <input type="number" step="0.1" value="{{ $this->part->cm_length }}" wire:change="updateField('cm_length', $event.target.value)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px] w-1/2">
                            <input type="number" step="0.1" value="{{ $this->part->cm_width }}" wire:change="updateField('cm_width', $event.target.value)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px] w-1/2">
                        </div>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Pack Qty (pcs/pack)</label>
                        <input type="number" value="{{ $this->part->pack_qty }}" wire:change="updateField('pack_qty', $event.target.value)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px]">
                    </div>
                    <div class="flex flex-col gap-0.5"></div>

                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Warna Unit 1-4 Sisi Pertama (pisah koma)</label>
                        <input type="text" value="{{ implode(', ', $this->part->color_set_1 ?? []) }}" placeholder="Black, Cyan, Magenta, Yellow"
                            wire:change="updateColorSet('color_set_1', $event.target.value)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px]">
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Warna Unit 1-4 Sisi Kedua (pisah koma)</label>
                        <input type="text" value="{{ implode(', ', $this->part->color_set_2 ?? []) }}"
                            wire:change="updateColorSet('color_set_2', $event.target.value)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px]">
                    </div>
                    <div class="flex flex-col gap-0.5"></div>
                </div>

                <h4 class="text-[11px] text-[var(--pd-ink-strong)] uppercase font-bold px-3.5 py-1.5 bg-[var(--pd-surface-alt)] border-y border-[var(--pd-border)]">🔖 Lidah / Flap (Engine §4.3a)</h4>
                <div class="p-3.5 grid three gap-2.5">
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Sisi Lidah</label>
                        <select wire:change="updateField('flap_side', $event.target.value)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px]">
                            @foreach (['TOP', 'BOTTOM', 'LEFT', 'RIGHT'] as $side)
                                <option value="{{ $side }}" @selected($this->part->flap_side === $side)>{{ $side }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Panjang Lidah Saat Ini (mm)</label>
                        <input type="number" step="0.1" value="{{ $this->part->flap_length_mm }}" wire:change="updateField('flap_length_mm', $event.target.value)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px]">
                    </div>
                    <div class="flex flex-col gap-0.5"></div>
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Rentang Valid Min-Max (mm)</label>
                        <div class="flex gap-1.5">
                            <input type="number" step="0.1" value="{{ $this->part->flap_min_mm }}" placeholder="default {{ number_format(\App\Models\Sys\SysConstant::number('PRODEV_FLAP_MIN_MM', 20), 0) }}"
                                wire:change="updateField('flap_min_mm', $event.target.value)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px] w-1/2">
                            <input type="number" step="0.1" value="{{ $this->part->flap_max_mm }}" wire:change="updateField('flap_max_mm', $event.target.value)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px] w-1/2">
                        </div>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Lebar Lidah (mm, Tingkat 2)</label>
                        <input type="number" step="0.1" value="{{ $this->part->flap_width_mm }}" wire:change="updateField('flap_width_mm', $event.target.value)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px]">
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Offset Lidah (mm, Tingkat 2)</label>
                        <input type="number" step="0.1" value="{{ $this->part->flap_offset_mm }}" wire:change="updateField('flap_offset_mm', $event.target.value)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px]">
                    </div>
                </div>
            </div>

            {{-- ============ IMPOSITION (§2 Master Card "Isi Dalam Satu Cetakan") ============
            Redesign 2026-09-03 (user, ikut mockup_FORM_prodev_2.png): 3 kolom --
            Nesting Strategy+Machine/Stock Specs (kiri) | Viewer 2D/1-Up/3D (tengah) |
            Efficiency Metrics (kanan). Semua wire:click METHOD SAMA PERSIS dgn
            sebelumnya (runEngineTingkat1/2/BestSheet, pickCandidate, confirmLayout,
            unlockLayout) -- cuma tatanan UI yg berubah. --}}
            <div class="flex flex-col lg:flex-row gap-3.5 items-start" x-data="{ tab: 'sheet', zoom: 100, showCut: true, showCrease: true, showGripper: true }">

                {{-- ---- KIRI: Nesting Strategy + Machine Specs + Stock Specs ---- --}}
                <div class="w-full lg:w-[280px] shrink-0 flex flex-col gap-3.5">
                    <div class="bg-[var(--pd-surface)] border border-[var(--pd-border)] rounded-md overflow-hidden">
                        <h3 class="text-xs text-[var(--pd-ink-strong)] uppercase font-bold px-3.5 py-2 bg-[var(--pd-surface-alt)] border-b border-[var(--pd-border)]">⚡ Nesting Strategy</h3>
                        <div class="p-2.5 flex flex-col gap-1.5">
                            <button type="button" wire:click="runEngineTingkat1" title="Standard Step-and-Repeat -- grid orthogonal tanpa rotasi/interlock (Tingkat 1)"
                                class="text-left px-2.5 py-2 rounded-md border cursor-pointer {{ $this->activeNestingMethod() === 'standard' ? 'border-accent bg-[var(--pd-info-bg)]' : 'border-[var(--pd-border)] bg-[var(--pd-surface)] hover:bg-[var(--pd-surface-alt)]' }}">
                                <div class="flex items-center justify-between">
                                    <span class="text-[11.5px] font-bold text-[var(--pd-ink-strong)]">Standard Step-and-Repeat</span>
                                    @if ($this->activeNestingMethod() === 'standard')<span class="text-[9px] font-bold text-accent">AKTIF</span>@endif
                                </div>
                                <div class="text-[10.5px] text-muted mt-0.5 leading-snug">Matrix bounding-box orthogonal, tanpa rotasi/interlock. Baseline.</div>
                            </button>
                            <button type="button" wire:click="runEngineTingkat2" title="AI Interlocking -- sweep interlock 180°, geser lidah supaya saling mengunci (Tingkat 2)"
                                class="text-left px-2.5 py-2 rounded-md border cursor-pointer {{ $this->activeNestingMethod() === 'interlock' ? 'border-accent bg-[var(--pd-info-bg)]' : 'border-[var(--pd-border)] bg-[var(--pd-surface)] hover:bg-[var(--pd-surface-alt)]' }}">
                                <div class="flex items-center justify-between">
                                    <span class="text-[11.5px] font-bold text-[var(--pd-ink-strong)]">AI Interlocking</span>
                                    @if ($this->activeNestingMethod() === 'interlock')<span class="text-[9px] font-bold text-accent">AKTIF</span>@endif
                                </div>
                                <div class="text-[10.5px] text-muted mt-0.5 leading-snug">Interlock 180°, kunci lidah ke kontur negatif tetangga. Perkiraan hemat {{ number_format(\App\Models\Sys\SysConstant::number('PRODEV_INTERLOCK_SAVING_MIN_PCT', 12), 0) }}–{{ number_format(\App\Models\Sys\SysConstant::number('PRODEV_INTERLOCK_SAVING_MAX_PCT', 22), 0) }}%.</div>
                            </button>
                            <div class="text-left px-2.5 py-2 rounded-md border border-[var(--pd-border)] opacity-50" title="Segera hadir -- pola brick-offset belum ada di ImpositionEngine">
                                <div class="flex items-center justify-between">
                                    <span class="text-[11.5px] font-bold text-[var(--pd-ink-strong)]">Staggered Brick Offset</span>
                                    <span class="text-[9px] font-bold text-muted">SEGERA HADIR</span>
                                </div>
                                <div class="text-[10.5px] text-muted mt-0.5 leading-snug">Pola geser bata per-baris utk kontur asimetris. Belum diimplementasi.</div>
                            </div>
                            <button type="button" wire:click="runEngineBestSheet" title="Tool #2 -- coba SEMUA plano aktif, ranking lintas-plano by biaya"
                                class="text-left px-2.5 py-1.5 rounded-md text-[10.5px] font-semibold text-accent hover:bg-[var(--pd-surface-alt)] cursor-pointer mt-0.5">🔍 Cari Plano Termurah (semua ukuran)</button>
                        </div>
                    </div>

                    <div class="bg-[var(--pd-surface)] border border-[var(--pd-border)] rounded-md overflow-hidden">
                        <h3 class="text-xs text-[var(--pd-ink-strong)] uppercase font-bold px-3.5 py-2 bg-[var(--pd-surface-alt)] border-b border-[var(--pd-border)]">🖨️ Machine Specifications</h3>
                        <div class="p-3 flex flex-col gap-1.5 text-[11.5px]">
                            <div class="flex items-center justify-between"><span class="text-muted">Gap Antar-Unit</span><b class="text-[var(--pd-ink-strong)]">{{ number_format($this->gapMm(), 1) }} mm</b></div>
                            <div class="flex items-center justify-between"><span class="text-muted">Gripper Margin</span><b class="text-[var(--pd-ink-strong)]">{{ number_format($this->gripperMm(), 1) }} mm</b></div>
                            <div class="flex items-center justify-between"><span class="text-muted">Margin Tepi Plano</span><b class="text-[var(--pd-ink-strong)]">{{ number_format($this->marginMm(), 1) }} mm</b></div>
                            <div class="text-[10px] text-muted mt-1 leading-snug">Parameter fisik mesin cetak sheetfed, dipakai semua kalkulasi Engine di panel ini (sys_constant PRODEV_*, admin bisa adjust dari Configuration).</div>
                        </div>
                    </div>

                    <div class="bg-[var(--pd-surface)] border border-[var(--pd-border)] rounded-md overflow-hidden">
                        <h3 class="text-xs text-[var(--pd-ink-strong)] uppercase font-bold px-3.5 py-2 bg-[var(--pd-surface-alt)] border-b border-[var(--pd-border)]">📦 Stock Specifications</h3>
                        <div class="p-3 flex flex-col gap-2">
                            <div class="flex flex-col gap-0.5">
                                <label class="text-[10.5px] text-muted font-semibold">Ukuran Plano (Sheet Size)</label>
                                <select wire:model="selectedPlanoId" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[12.5px]">
                                    @foreach ($this->planoSizes as $p)
                                        <option value="{{ $p->id_plano_size }}">{{ $p->plano_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex flex-col gap-0.5">
                                <label class="text-[10.5px] text-muted font-semibold">Ukuran Bahan Mentah W × H (cm)</label>
                                <div class="flex gap-1.5">
                                    <input type="number" step="0.1" value="{{ $this->part->sheet_raw_w_cm }}" wire:change="updateField('sheet_raw_w_cm', $event.target.value)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[12.5px] w-1/2">
                                    <input type="number" step="0.1" value="{{ $this->part->sheet_raw_h_cm }}" wire:change="updateField('sheet_raw_h_cm', $event.target.value)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[12.5px] w-1/2">
                                </div>
                            </div>
                            @if ($this->planoWasteAreaCm2() !== null)
                                <div class="flex items-center justify-between text-[11px] pt-1 border-t border-[var(--pd-border)]">
                                    <span class="text-muted">Sisa Bahan / Lembar</span>
                                    <b class="text-[var(--pd-ink-strong)]">{{ number_format($this->planoWasteAreaCm2(), 0) }} cm²</b>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ---- TENGAH: Viewer 2D Sheet / 1-Up Dieline / 3D Mockup ---- --}}
                <div class="flex-1 min-w-0 flex flex-col gap-3.5 w-full">
                    <div class="bg-[var(--pd-surface)] border border-[var(--pd-border)] rounded-md overflow-hidden">
                        <div class="flex items-center justify-between px-3.5 py-2 bg-[var(--pd-surface-alt)] border-b border-[var(--pd-border)]">
                            <div class="flex items-center gap-1">
                                <button type="button" @click="tab = 'sheet'" :class="tab === 'sheet' ? 'bg-accent text-white' : 'text-muted hover:bg-[var(--pd-surface)]'" class="px-2.5 py-1 rounded text-[10.5px] font-bold cursor-pointer">2D Sheet</button>
                                <button type="button" @click="tab = 'oneup'" :class="tab === 'oneup' ? 'bg-accent text-white' : 'text-muted hover:bg-[var(--pd-surface)]'" class="px-2.5 py-1 rounded text-[10.5px] font-bold cursor-pointer">1-Up Dieline</button>
                                <button type="button" disabled title="Segera hadir -- render 3D mockup belum ada mesinnya" class="px-2.5 py-1 rounded text-[10.5px] font-bold text-muted opacity-50 cursor-not-allowed">3D Mockup</button>
                            </div>
                            <div class="flex items-center gap-1.5" x-show="tab === 'sheet'">
                                <button type="button" @click="zoom = Math.max(40, zoom - 20)" class="w-6 h-6 rounded border border-[var(--pd-border)] text-[var(--pd-ink)] text-xs cursor-pointer">−</button>
                                <span class="text-[10.5px] text-muted w-9 text-center" x-text="zoom + '%'"></span>
                                <button type="button" @click="zoom = Math.min(200, zoom + 20)" class="w-6 h-6 rounded border border-[var(--pd-border)] text-[var(--pd-ink)] text-xs cursor-pointer">+</button>
                            </div>
                        </div>

                        <div class="p-3.5" x-show="tab === 'sheet'">
                            @if (count($sweepCandidates) > 0)
                                <div class="text-[11px] text-muted mb-2">Top {{ count($sweepCandidates) }} hasil (ranking UP terbanyak, lalu efisiensi tertinggi) — pilih salah satu:</div>
                                <div class="flex gap-2.5 flex-wrap">
                                    @foreach ($sweepCandidates as $i => $c)
                                        <div class="border {{ $i === 0 ? 'border-[#22a06b]' : 'border-[var(--pd-border)]' }} rounded-md p-2.5 w-[280px]">
                                            <div class="flex items-center justify-between mb-1.5">
                                                <span class="text-[11px] font-bold text-[var(--pd-ink-strong)]">
                                                    @isset($c['plano_name']){{ $c['plano_name'] }} · @endisset
                                                    Lidah {{ $c['flap_length_mm'] }}mm
                                                    @isset($c['shift_offset_mm'])· Shift {{ $c['shift_offset_mm'] }}mm @endisset
                                                    · {{ $c['method'] }}
                                                </span>
                                                @if ($i === 0) <span class="text-[9.5px] bg-[#eaf7ee] text-[#0e8a4f] px-1.5 py-0.5 rounded font-bold">TERBAIK</span> @endif
                                            </div>
                                            <div class="bg-[#fbfcfd] border border-[var(--pd-border)] rounded flex items-center justify-center" style="height:180px">
                                                <div class="relative bg-white border-2 border-navy" style="width:{{ $c['boxW'] }}px;height:{{ $c['boxH'] }}px">
                                                    @foreach ($c['cellsVisual'] as $cell)
                                                        <div class="absolute {{ $cell['rot'] ? 'bg-[#7c5cd6]/15 border border-[#7c5cd6]' : 'bg-[#2c7be5]/15 border border-[#2c7be5]' }}"
                                                            style="left:{{ $cell['left'] }}px;top:{{ $cell['top'] }}px;width:{{ $cell['width'] }}px;height:{{ $cell['height'] }}px"></div>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <div class="flex items-center justify-between mt-1.5 text-[11px]">
                                                <span><b>{{ $c['up'] }}</b> up · {{ number_format($c['efficiency_pct'], 0) }}% efisien
                                                    @isset($c['gross_sheets'])· {{ $c['gross_sheets'] }} lembar dibutuhkan @endisset
                                                </span>
                                                <button type="button" wire:click="pickCandidate({{ $i }})" class="text-[11px] font-bold text-accent cursor-pointer">Pilih →</button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @elseif ($this->activeLayoutCells())
                                @php $__al = $this->activeLayoutCells(); @endphp
                                <div class="flex items-center gap-3 mb-2 text-[10.5px]">
                                    <label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" x-model="showCut"> <span class="text-muted">Cut Knife</span></label>
                                    <label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" x-model="showCrease"> <span class="text-muted">Crease</span></label>
                                    <label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" x-model="showGripper"> <span class="text-muted">Gripper</span></label>
                                </div>
                                <div class="bg-[#fbfcfd] border border-[var(--pd-border)] rounded overflow-auto p-4 flex items-center justify-center" style="min-height:340px">
                                    <div class="relative bg-white border-2 border-navy origin-center" :style="`width:{{ $__al['boxW'] }}px;height:{{ $__al['boxH'] }}px;transform:scale(${zoom / 100})`">
                                        <div class="absolute inset-x-0 top-0 bg-[#f2c94c]/15 border-b border-dashed border-[#c99a1f]" x-show="showGripper" style="height:{{ $__al['gripperPx'] }}px"></div>
                                        @foreach ($__al['cells'] as $cell)
                                            <div class="absolute border border-accent bg-accent/10" x-show="showCut"
                                                style="left:{{ $cell['left'] }}px;top:{{ $cell['top'] }}px;width:{{ $cell['width'] }}px;height:{{ $cell['height'] }}px">
                                                <div class="absolute border-dashed border-[#c0392b]" x-show="showCrease"
                                                    style="{{ $cell['crease_vertical'] ? "left:{$cell['crease_pct']}%;top:0;bottom:0;border-left-width:1.5px" : "top:{$cell['crease_pct']}%;left:0;right:0;border-top-width:1.5px" }}"></div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="text-[11px] text-muted mt-2">🔵 Cut Knife (biru) · 🔴 Crease (merah putus-putus) · 🟡 Gripper margin (kuning, area tak-tercetak).</div>
                            @else
                                <div class="text-[11.5px] px-2.5 py-2 rounded-md leading-relaxed bg-[var(--pd-info-bg)] border border-[var(--pd-info-border)] text-[var(--pd-info-ink)]">
                                    Belum ada layout tersimpan. Pilih strategi nesting di kiri, lalu klik salah satu kartu utk jalankan Engine.
                                </div>
                            @endif
                        </div>

                        <div class="p-3.5" x-show="tab === 'oneup'">
                            @if ($this->dielineOneUp())
                                @php $__du = $this->dielineOneUp(); @endphp
                                <div class="bg-[#fbfcfd] border border-[var(--pd-border)] rounded flex items-center justify-center p-6" style="min-height:340px">
                                    <div class="relative bg-white border-2 border-navy" style="width:{{ $__du['flat_w_px'] }}px;height:{{ $__du['flat_h_px'] }}px">
                                        @php $__isVert = in_array($__du['side'], ['LEFT', 'RIGHT']); @endphp
                                        <div class="absolute bg-accent/10 border border-accent"
                                            style="{{ $__du['side'] === 'TOP' ? "left:0;right:0;top:0;height:{$__du['flap_px']}px" : ($__du['side'] === 'BOTTOM' ? "left:0;right:0;bottom:0;height:{$__du['flap_px']}px" : ($__du['side'] === 'LEFT' ? "top:0;bottom:0;left:0;width:{$__du['flap_px']}px" : "top:0;bottom:0;right:0;width:{$__du['flap_px']}px")) }}"></div>
                                        <div class="absolute {{ $__isVert ? 'top-0 bottom-0' : 'left-0 right-0' }} border-dashed border-[#c0392b]"
                                            style="{{ $__isVert
                                                ? ($__du['side'] === 'LEFT' ? "left:{$__du['flap_px']}px;border-left-width:1.5px" : "right:{$__du['flap_px']}px;border-right-width:1.5px")
                                                : ($__du['side'] === 'TOP' ? "top:{$__du['flap_px']}px;border-top-width:1.5px" : "bottom:{$__du['flap_px']}px;border-bottom-width:1.5px") }}"></div>
                                        <div class="absolute inset-0 flex items-center justify-center text-[10.5px] text-muted font-semibold">Badan {{ $__du['body_w_mm'] }} × {{ $__du['body_h_mm'] }} mm</div>
                                    </div>
                                </div>
                                <div class="text-[11px] text-muted mt-2">Lidah ({{ $__du['side'] }}): <b class="text-[var(--pd-ink-strong)]">{{ $__du['flap_mm'] }}mm</b> · Flat total: <b class="text-[var(--pd-ink-strong)]">{{ $__du['flat_w_mm'] }} × {{ $__du['flat_h_mm'] }} mm</b></div>
                            @else
                                <div class="text-[11.5px] px-2.5 py-2 rounded-md leading-relaxed bg-[var(--pd-info-bg)] border border-[var(--pd-info-border)] text-[var(--pd-info-ink)]">
                                    Isi dimensi badan (cm_length/cm_width) di atas dulu.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ---- KANAN: Efficiency Metrics ---- --}}
                <div class="w-full lg:w-[270px] shrink-0 flex flex-col gap-3.5">
                    <div class="bg-[var(--pd-surface)] border border-[var(--pd-border)] rounded-md overflow-hidden">
                        <h3 class="text-xs text-[var(--pd-ink-strong)] uppercase font-bold px-3.5 py-2 bg-[var(--pd-surface-alt)] border-b border-[var(--pd-border)] flex items-center justify-between">
                            <span>📊 Efficiency Metrics</span>
                            @if ($this->activeLayoutGroup)
                                <span class="text-[9.5px] font-bold {{ $this->activeLayoutGroup->status === 'CONFIRMED' ? 'text-[#0e8a4f]' : 'text-[#8a5a00]' }}">{{ $this->activeLayoutGroup->status }}</span>
                            @endif
                        </h3>
                        <div class="p-3.5 flex flex-col gap-3">
                            @if ($this->activeLayoutGroup)
                                <div class="[display:grid] [grid-template-columns:1fr_1fr] gap-2.5">
                                    <div class="bg-[var(--pd-surface-alt)] rounded-md p-2.5">
                                        <div class="text-[9px] text-muted uppercase font-bold">UPS / Mata</div>
                                        <div class="text-xl font-black text-[var(--pd-ink-strong)]">{{ $this->activeLayoutGroup->up_count }}</div>
                                        <div class="text-[9px] text-muted">per Plano</div>
                                    </div>
                                    <div class="bg-[var(--pd-surface-alt)] rounded-md p-2.5">
                                        <div class="text-[9px] text-muted uppercase font-bold">Sheet Yield</div>
                                        <div class="text-xl font-black text-[#0e8a4f]">{{ number_format($this->activeLayoutGroup->efficiency_pct, 1) }}%</div>
                                        <div class="text-[9px] text-muted">Efisiensi Area</div>
                                    </div>
                                </div>

                                @if ($this->wasteReductionVsStandard())
                                    @php $__wr = $this->wasteReductionVsStandard(); @endphp
                                    <div class="rounded-md p-2.5 {{ $__wr['is_improvement'] ? 'bg-[#0f2a1e] text-[#5fe3a0]' : 'bg-[var(--pd-surface-alt)] text-[var(--pd-ink)]' }}">
                                        <div class="text-[9px] uppercase font-bold opacity-80">AI Waste Reduction</div>
                                        <div class="text-lg font-black">{{ $__wr['delta_pct'] > 0 ? '+' : '' }}{{ $__wr['delta_pct'] }}%</div>
                                        <div class="text-[9.5px] opacity-80 leading-snug">
                                            @if ($__wr['is_improvement'])
                                                Dibanding grid standar ({{ $__wr['baseline_waste_pct'] }}% waste), strategi aktif hemat waste jadi {{ $__wr['active_waste_pct'] }}%.
                                            @else
                                                Sama dengan/di atas baseline grid standar ({{ $__wr['baseline_waste_pct'] }}% waste).
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                <div class="flex flex-col gap-1.5 text-[11px] pt-1 border-t border-[var(--pd-border)]">
                                    <div class="flex items-center justify-between"><span class="text-muted">Pisau Pond</span><b class="text-[var(--pd-ink-strong)]">{{ $this->part->die_w_cm ?? '—' }} × {{ $this->part->die_h_cm ?? '—' }} cm</b></div>
                                    <div class="flex items-center justify-between"><span class="text-muted">Plano Press Sheet</span><b class="text-[var(--pd-ink-strong)]">{{ $this->activeLayoutGroup->plano_w_mm / 10 }} × {{ $this->activeLayoutGroup->plano_h_mm / 10 }} cm</b></div>
                                    <div class="flex items-center justify-between"><span class="text-muted">Metode</span><b class="text-[var(--pd-ink-strong)]">{{ $this->activeLayoutGroup->method }}</b></div>
                                    @if ($this->activeLayoutGroup->die_rule_meter)
                                        <div class="flex items-center justify-between"><span class="text-muted">Meter Lari Pisau</span><b class="text-[var(--pd-ink-strong)]">{{ number_format($this->activeLayoutGroup->die_rule_meter, 2) }} m</b></div>
                                    @endif
                                </div>

                                @if ($this->activeLayoutGroup->status === 'CONFIRMED')
                                    <button type="button" wire:click="unlockLayout" class="px-3 py-1.5 rounded text-[11px] font-bold bg-[var(--pd-surface-alt)] border border-[var(--pd-border)] text-[var(--pd-ink-strong)] cursor-pointer">🔓 Buka Kunci</button>
                                @else
                                    <button type="button" wire:click="confirmLayout" class="px-3 py-1.5 rounded text-[11px] font-bold bg-[#22a06b] text-white cursor-pointer">✓ Konfirmasi Layout</button>
                                @endif

                                @php $__costingOriginId = $this->originInquiryParts->first()['inquiry_id'] ?? null; @endphp
                                <a href="{{ $__costingOriginId ? route('inquiry.costing', ['inquiry' => $__costingOriginId]) : route('inquiry.costing.list') }}" wire:navigate
                                    class="text-center px-3 py-1.5 rounded text-[11px] font-bold bg-accent text-white no-underline hover:brightness-110">Hitung Product Costing →</a>
                            @else
                                <div class="text-[11.5px] px-2.5 py-2 rounded-md leading-relaxed bg-[var(--pd-info-bg)] border border-[var(--pd-info-border)] text-[var(--pd-info-ink)]">
                                    Belum ada layout tersimpan. Isi dimensi badan (cm_length/cm_width) &amp; rentang lidah di kartu Spesifikasi Teknis, lalu jalankan Nesting Strategy di kiri.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============ TOOL #10: INTRA-PRODUCT PART COMBINER ============ --}}
            <div class="bg-[var(--pd-surface)] border border-[var(--pd-border)] rounded-md overflow-hidden">
                <h3 class="text-xs text-[var(--pd-ink-strong)] uppercase font-bold px-3.5 py-2 bg-[var(--pd-surface-alt)] border-b border-[var(--pd-border)]">
                    🧩 Tool #10 — Gabung Part dalam Produk Sama
                </h3>
                <div class="p-3.5">
                    @if ($this->combinableParts->isEmpty())
                        <div class="text-[11.5px] text-muted">Produk ini cuma punya 1 part terdaftar — tidak ada pasangan utk digabung.</div>
                    @else
                        <div class="flex items-end gap-2.5 mb-3">
                            <div class="flex flex-col gap-0.5">
                                <label class="text-[11px] text-muted font-semibold">Gabung dengan part</label>
                                <select wire:model="combineWithPartId" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px] min-w-[220px]">
                                    <option value="">— pilih part pasangan —</option>
                                    @foreach ($this->combinableParts as $cp)
                                        <option value="{{ $cp->id_part }}">{{ $cp->part_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="button" wire:click="runToolCombine" @disabled(! $combineWithPartId)
                                class="px-3.5 py-1.5 rounded text-[11px] font-bold bg-[#0e8a6b] text-white cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">▶ Bandingkan Gabung vs Pisah</button>
                        </div>

                        @if ($combineResult)
                            @if ($combineResult['recommend'] === 'NONE')
                                <div class="text-[11.5px] px-2.5 py-2 rounded-md leading-relaxed bg-[#fdecea] border border-[#f3c4bd] text-[#9a3324]">
                                    Kedua part tidak muat digabung sama sekali di plano {{ $this->planoSizes->firstWhere('id_plano_size', $selectedPlanoId)?->plano_name }} (dicoba 2 orientasi). Coba plano lain di dropdown Engine Imposition di atas.
                                </div>
                            @else
                                <div class="text-[11.5px] px-2.5 py-2.5 rounded-md leading-relaxed {{ $combineResult['recommend'] === 'COMBINED' ? 'bg-[#f0f7f4] border border-[#c3e6d1] text-[#0e5c38]' : 'bg-[#fff8ec] border border-[#f0dcae] text-[#8a5a00]' }}">
                                    <div class="font-bold mb-1">
                                        Rekomendasi: {{ $combineResult['recommend'] === 'COMBINED' ? '✓ GABUNG' : '✕ PISAH (tetap sendiri²)' }}
                                        @if ($combineResult['recommend'] === 'COMBINED')
                                            — hemat {{ $combineResult['saving_pct'] }}% luas dibanding cetak terpisah
                                        @endif
                                    </div>
                                    @if ($combineResult['combined'])
                                        <div>Split di X={{ $combineResult['combined']['split_x_mm'] }}mm: <b>{{ $combineResult['part_a_name'] }}</b> {{ $combineResult['combined']['up_a'] }} up, <b>{{ $combineResult['part_b_name'] }}</b> {{ $combineResult['combined']['up_b'] }} up ({{ $combineResult['combined']['sets_per_sheet'] }} set produk jadi/lembar).</div>
                                    @endif
                                    <div class="text-[10.5px] opacity-80 mt-1">Pisah: {{ $combineResult['part_a_name'] }} {{ $combineResult['separate']['up_a'] }} up solo, {{ $combineResult['part_b_name'] }} {{ $combineResult['separate']['up_b'] }} up solo (masing² plano sendiri).</div>
                                    @if ($combineResult['recommend'] === 'COMBINED')
                                        <button type="button" wire:click="confirmCombine" class="mt-2 px-3 py-1.5 rounded text-[11px] font-bold bg-[#22a06b] text-white cursor-pointer">✓ Terapkan — Buat Layout Gabungan</button>
                                    @endif
                                </div>
                            @endif
                        @endif
                    @endif
                </div>
            </div>

            {{-- ============ ROUTING (URUTAN PROSES) ============ --}}
            <div class="bg-[var(--pd-surface)] border border-[var(--pd-border)] rounded-md overflow-hidden">
                <h3 class="text-xs text-[var(--pd-ink-strong)] uppercase font-bold px-3.5 py-2 bg-[var(--pd-surface-alt)] border-b border-[var(--pd-border)]">🔀 Urutan Proses (Routing)</h3>
                <div class="p-3.5">
                    <table class="w-full text-xs mb-2.5">
                        <thead>
                            <tr>
                                <th class="text-left px-2 py-1.5 text-[9.5px] text-muted uppercase font-bold border-b border-[var(--pd-border)]">Seq</th>
                                <th class="text-left px-2 py-1.5 text-[9.5px] text-muted uppercase font-bold border-b border-[var(--pd-border)]">Proses</th>
                                <th class="text-left px-2 py-1.5 text-[9.5px] text-muted uppercase font-bold border-b border-[var(--pd-border)]">Mesin</th>
                                <th class="text-left px-2 py-1.5 text-[9.5px] text-muted uppercase font-bold border-b border-[var(--pd-border)]">Catatan</th>
                                <th class="border-b border-[var(--pd-border)]"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->part->processes as $pp)
                                <tr wire:key="proc-{{ $pp->id_part_process }}">
                                    <td class="px-2 py-1.5 border-b border-[var(--pd-border)]">{{ $pp->step_seq }}</td>
                                    <td class="px-2 py-1.5 border-b border-[var(--pd-border)] font-semibold">{{ $pp->process?->process_name }}</td>
                                    <td class="px-2 py-1.5 border-b border-[var(--pd-border)]">{{ $pp->machine?->machine_name ?? '—' }}</td>
                                    <td class="px-2 py-1.5 border-b border-[var(--pd-border)] text-muted">{{ $pp->note }}</td>
                                    <td class="px-2 py-1.5 border-b border-[var(--pd-border)] text-right">
                                        <button type="button" wire:click="removeProcess({{ $pp->id_part_process }})" class="text-[11px] text-[#c0392b] cursor-pointer">hapus</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-2 py-3 text-center text-muted">Belum ada routing.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="flex items-end gap-2 flex-wrap">
                        <select wire:model="newProcessId" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px]">
                            <option value="">— Proses —</option>
                            @foreach ($this->processOptions as $po)
                                <option value="{{ $po->id_process }}">{{ $po->process_name }}</option>
                            @endforeach
                        </select>
                        <select wire:model="newMachineId" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px]">
                            <option value="">— Mesin (opsional) —</option>
                            @foreach ($this->machineOptions as $mo)
                                <option value="{{ $mo->machine_id }}">{{ $mo->machine_name }}</option>
                            @endforeach
                        </select>
                        <input type="text" wire:model="newProcessNotes" placeholder="Catatan (opsional)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px] flex-1 min-w-[140px]">
                        <button type="button" wire:click="addProcess" class="px-3 py-1.5 rounded text-[11px] font-bold bg-navy text-white cursor-pointer">+ Tambah</button>
                    </div>
                </div>
            </div>

            {{-- ============ FINISHING ============ --}}
            <div class="bg-[var(--pd-surface)] border border-[var(--pd-border)] rounded-md overflow-hidden">
                <h3 class="text-xs text-[var(--pd-ink-strong)] uppercase font-bold px-3.5 py-2 bg-[var(--pd-surface-alt)] border-b border-[var(--pd-border)]">✨ Finishing</h3>
                <div class="p-3.5">
                    <table class="w-full text-xs mb-2.5">
                        <thead>
                            <tr>
                                <th class="text-left px-2 py-1.5 text-[9.5px] text-muted uppercase font-bold border-b border-[var(--pd-border)]">Jenis</th>
                                <th class="text-left px-2 py-1.5 text-[9.5px] text-muted uppercase font-bold border-b border-[var(--pd-border)]">Catatan</th>
                                <th class="border-b border-[var(--pd-border)]"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->part->finishings as $f)
                                <tr wire:key="fin-{{ $f->id_part_finishing }}">
                                    <td class="px-2 py-1.5 border-b border-[var(--pd-border)] font-semibold">{{ $f->finishingType?->finishing_name }}</td>
                                    <td class="px-2 py-1.5 border-b border-[var(--pd-border)] text-muted">{{ $f->notes }}</td>
                                    <td class="px-2 py-1.5 border-b border-[var(--pd-border)] text-right">
                                        <button type="button" wire:click="removeFinishing({{ $f->id_part_finishing }})" class="text-[11px] text-[#c0392b] cursor-pointer">hapus</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-2 py-3 text-center text-muted">Belum ada finishing.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="flex items-end gap-2 flex-wrap">
                        <select wire:model="newFinishingTypeId" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px]">
                            <option value="">— Jenis Finishing —</option>
                            @foreach ($this->finishingOptions as $fo)
                                <option value="{{ $fo->id_finishing_type }}">{{ $fo->finishing_name }}</option>
                            @endforeach
                        </select>
                        <input type="text" wire:model="newFinishingNotes" placeholder="mis. Gold Puyeh (opsional)" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px] flex-1 min-w-[140px]">
                        <button type="button" wire:click="addFinishing" class="px-3 py-1.5 rounded text-[11px] font-bold bg-navy text-white cursor-pointer">+ Tambah</button>
                    </div>
                </div>
            </div>

            {{-- ============ ATTACHMENT ============ --}}
            <div class="bg-[var(--pd-surface)] border border-[var(--pd-border)] rounded-md overflow-hidden">
                <h3 class="text-xs text-[var(--pd-ink-strong)] uppercase font-bold px-3.5 py-2 bg-[var(--pd-surface-alt)] border-b border-[var(--pd-border)]">📎 Referensi (JPEG wajib utk preview, PDF/AI opsional)</h3>
                <div class="p-3.5">
                    <div class="flex gap-2.5 flex-wrap mb-3">
                        @forelse ($this->part->attachments as $att)
                            <div class="border border-[var(--pd-border)] rounded-md p-2 w-[140px]" wire:key="att-{{ $att->id_attachment }}">
                                @if ($att->file_type === 'JPEG')
                                    <img src="{{ asset('storage/'.$att->file_path) }}" class="w-full h-[90px] object-cover rounded mb-1.5" alt="preview">
                                @else
                                    <div class="w-full h-[90px] bg-[var(--pd-surface-alt)] rounded mb-1.5 flex items-center justify-center text-2xl">📄</div>
                                @endif
                                <div class="text-[10px] font-bold text-[var(--pd-ink-strong)] truncate">{{ $att->original_filename }}</div>
                                <div class="flex items-center justify-between mt-1">
                                    <span class="text-[9px] bg-[#eef1f5] px-1.5 py-0.5 rounded font-bold">{{ $att->file_type }}</span>
                                    <button type="button" wire:click="deleteAttachment({{ $att->id_attachment }})" class="text-[10px] text-[#c0392b] cursor-pointer">hapus</button>
                                </div>
                            </div>
                        @empty
                            <span class="text-xs text-muted">Belum ada file.</span>
                        @endforelse
                    </div>
                    <div class="flex items-end gap-2 flex-wrap">
                        <select wire:model="newAttachmentType" class="px-2 py-1.5 border !border-[var(--pd-border)] !bg-[var(--pd-input-bg)] !text-[var(--pd-ink-strong)] placeholder:!text-[var(--pd-muted)] rounded text-[13px]">
                            <option value="JPEG">JPEG (preview)</option>
                            <option value="PDF">PDF</option>
                            <option value="AI">AI</option>
                        </select>
                        <input type="file" wire:model="newAttachmentFile" class="text-[13px]">
                        <button type="button" wire:click="uploadAttachment" class="px-3 py-1.5 rounded text-[11px] font-bold bg-navy text-white cursor-pointer">↑ Upload</button>
                        <span class="text-[10.5px] text-muted">maks {{ $this->uploadMaxMb }}MB</span>
                    </div>
                    @error('newAttachmentFile') <div class="text-[11px] text-[#c0392b] mt-1.5">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
