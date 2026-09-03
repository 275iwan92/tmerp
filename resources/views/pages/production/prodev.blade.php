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

    public string $search = '';

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
    public function parts()
    {
        return ProdPart::with('product')
            ->when($this->search !== '', fn ($q) => $q->where('part_name', 'like', "%{$this->search}%")
                ->orWhere('part_code', 'like', "%{$this->search}%")
                ->orWhereHas('product', fn ($q2) => $q2->where('product_name', 'like', "%{$this->search}%")))
            ->orderByDesc('id_part')
            ->limit(60)
            ->get();
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

    public function switchPart(int $id): void
    {
        $this->partId = $id;
        $this->sweepCandidates = [];
        $this->combineWithPartId = null;
        $this->combineResult = null;
        unset($this->part, $this->activeLayoutGroup, $this->combinableParts);
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
            gap: 3.0, gripper: 12.0, margin: 8.0, topN: 5
        );

        foreach ($results as $i => $r) {
            $cells = $engine->cells($r, 8.0, 12.0, 3.0);
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
            shiftStep: 5.0, gap: 3.0, gripper: 12.0, margin: 8.0, topN: 5
        );

        foreach ($results as $i => $r) {
            $cells = $engine->cells($r, 8.0, 12.0, 3.0);
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
            gap: 3.0, gripper: 12.0, margin: 8.0, targetQty: null, topN: 5
        );

        foreach ($results as $i => $r) {
            $plano = MatPlanoSize::find($r['plano_size_id']);
            $cells = $engine->cells($r, 8.0, 12.0, 3.0);
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
            (float) ($part->cm_width ?? 0) * 10, (float) ($part->cm_length ?? 0) * 10, 3.0
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
            gap: 3.0, gripper: 12.0, margin: 8.0, splitStep: 10.0
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

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">🛠️ TELASIH MAKMUR — Product Development</b>
        @if ($this->part)
            <span class="text-xs text-white/75">{{ $this->part->displayMastercardCode() }} · {{ $this->part->part_name }}</span>
            <a href="{{ route('production.prodev.print', ['part' => $this->partId]) }}" target="_blank"
                class="ml-auto bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">🖨️ Cetak Master Card</a>
        @endif
    </header>

    <div class="max-w-[1320px] mx-auto my-5 px-4">

        @if (session('error'))
            <div class="bg-[#fdecea] border border-[#f3b8b0] text-[#a1362b] text-xs px-3.5 py-2.5 rounded-md mb-3.5">{{ session('error') }}</div>
        @endif

        {{-- ============ PART PICKER ============ --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md p-3 mb-4">
            <div class="flex items-center gap-2 mb-2">
                <span class="text-[10.5px] text-muted uppercase font-semibold">Cari Part:</span>
                <input type="text" wire:model.live.debounce.400ms="search" placeholder="Nama/kode part atau produk..."
                    class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px] flex-1 max-w-[320px]">
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                @forelse ($this->parts as $p)
                    <button type="button" wire:click="switchPart({{ $p->id_part }})"
                        class="text-xs font-semibold px-3 py-1.5 rounded-full border cursor-pointer {{ $this->partId === $p->id_part ? 'bg-navy text-white border-navy' : 'bg-[#f4f6fa] text-navy border-[#d7dbe2]' }}">
                        {{ $p->part_name }} <span class="opacity-70">· {{ $p->product?->product_name }}</span>
                    </button>
                @empty
                    <span class="text-xs text-muted">Belum ada Part di Master Produk.</span>
                @endforelse
            </div>
        </div>

        @if ($this->part)
        <div class="flex flex-col gap-3.5">

            {{-- ============ REFERENSI INQUIRY ASAL (Mode #1, read-only) ============
            Cuma tampil kalau ADA iqp_part yang link ke prod_part ini DAN Inquiry-nya
            CONFIRMED (bukan draft/cancelled/lost, lihat originInquiryParts()). Murni
            referensi biar ProDev tak perlu tanya-tanya Sales -- TIDAK bisa diedit dari
            sini (snapshot/beku, CLAUDE.md §4), field teknis ProDev sendiri di bawah
            tetap fully-editable seperti biasa. --}}
            @if ($this->originInquiryParts->isNotEmpty())
                <div class="bg-[#eef3fb] border border-[#c3d8f5] rounded-md overflow-hidden">
                    <h3 class="text-xs text-[#1560a8] uppercase font-bold px-3.5 py-2 bg-[#e0ecfa] border-b border-[#c3d8f5]">
                        📥 Referensi Inquiry Asal (read-only, sudah CONFIRMED)
                    </h3>
                    <div class="p-3.5 flex flex-col gap-2.5">
                        @foreach ($this->originInquiryParts as $oi)
                            <div class="text-[11.5px] text-[#1560a8] {{ ! $loop->last ? 'pb-2.5 border-b border-[#c3d8f5]' : '' }}">
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
            <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden">
                <h3 class="text-xs text-navy uppercase font-bold px-3.5 py-2 bg-[#f4f6fa] border-b border-[#d7dbe2]">📋 Spesifikasi Teknis (Master Card)</h3>
                <div class="p-3.5 grid three gap-2.5">
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Kode Item</label>
                        <input type="text" value="{{ $this->part->mastercard_code }}" placeholder="{{ $this->part->displayMastercardCode() }}"
                            wire:change="updateField('mastercard_code', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px]">
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Kode Pisau (Die Code)</label>
                        <input type="text" value="{{ $this->part->die_code }}" wire:change="updateField('die_code', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px]">
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Bentuk Sambungan (Join Type)</label>
                        <input type="text" value="{{ $this->part->joint_type }}" wire:change="updateField('joint_type', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px]">
                    </div>

                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Ukuran Pisau W x H (cm)</label>
                        <div class="flex gap-1.5">
                            <input type="number" step="0.1" value="{{ $this->part->die_w_cm }}" wire:change="updateField('die_w_cm', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px] w-1/2">
                            <input type="number" step="0.1" value="{{ $this->part->die_h_cm }}" wire:change="updateField('die_h_cm', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px] w-1/2">
                        </div>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Ukuran Bahan Mentah W x H (cm)</label>
                        <div class="flex gap-1.5">
                            <input type="number" step="0.1" value="{{ $this->part->sheet_raw_w_cm }}" wire:change="updateField('sheet_raw_w_cm', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px] w-1/2">
                            <input type="number" step="0.1" value="{{ $this->part->sheet_raw_h_cm }}" wire:change="updateField('sheet_raw_h_cm', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px] w-1/2">
                        </div>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Ukuran Cetak P x L (cm)</label>
                        <div class="flex gap-1.5">
                            <input type="number" step="0.1" value="{{ $this->part->printsize_p }}" wire:change="updateField('printsize_p', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px] w-1/2">
                            <input type="number" step="0.1" value="{{ $this->part->printsize_l }}" wire:change="updateField('printsize_l', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px] w-1/2">
                        </div>
                    </div>

                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Dimensi Badan (Body) P x L (cm) — dasar Engine</label>
                        <div class="flex gap-1.5">
                            <input type="number" step="0.1" value="{{ $this->part->cm_length }}" wire:change="updateField('cm_length', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px] w-1/2">
                            <input type="number" step="0.1" value="{{ $this->part->cm_width }}" wire:change="updateField('cm_width', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px] w-1/2">
                        </div>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Pack Qty (pcs/pack)</label>
                        <input type="number" value="{{ $this->part->pack_qty }}" wire:change="updateField('pack_qty', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px]">
                    </div>
                    <div class="flex flex-col gap-0.5"></div>

                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Warna Unit 1-4 Sisi Pertama (pisah koma)</label>
                        <input type="text" value="{{ implode(', ', $this->part->color_set_1 ?? []) }}" placeholder="Black, Cyan, Magenta, Yellow"
                            wire:change="updateColorSet('color_set_1', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px]">
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Warna Unit 1-4 Sisi Kedua (pisah koma)</label>
                        <input type="text" value="{{ implode(', ', $this->part->color_set_2 ?? []) }}"
                            wire:change="updateColorSet('color_set_2', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px]">
                    </div>
                    <div class="flex flex-col gap-0.5"></div>
                </div>

                <h4 class="text-[11px] text-navy uppercase font-bold px-3.5 py-1.5 bg-[#f8fafc] border-y border-[#eef1f5]">🔖 Lidah / Flap (Engine §4.3a)</h4>
                <div class="p-3.5 grid three gap-2.5">
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Sisi Lidah</label>
                        <select wire:change="updateField('flap_side', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px]">
                            @foreach (['TOP', 'BOTTOM', 'LEFT', 'RIGHT'] as $side)
                                <option value="{{ $side }}" @selected($this->part->flap_side === $side)>{{ $side }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Panjang Lidah Saat Ini (mm)</label>
                        <input type="number" step="0.1" value="{{ $this->part->flap_length_mm }}" wire:change="updateField('flap_length_mm', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px]">
                    </div>
                    <div class="flex flex-col gap-0.5"></div>
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Rentang Valid Min-Max (mm)</label>
                        <div class="flex gap-1.5">
                            <input type="number" step="0.1" value="{{ $this->part->flap_min_mm }}" placeholder="default {{ number_format(\App\Models\Sys\SysConstant::number('PRODEV_FLAP_MIN_MM', 20), 0) }}"
                                wire:change="updateField('flap_min_mm', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px] w-1/2">
                            <input type="number" step="0.1" value="{{ $this->part->flap_max_mm }}" wire:change="updateField('flap_max_mm', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px] w-1/2">
                        </div>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Lebar Lidah (mm, Tingkat 2)</label>
                        <input type="number" step="0.1" value="{{ $this->part->flap_width_mm }}" wire:change="updateField('flap_width_mm', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px]">
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] text-muted font-semibold">Offset Lidah (mm, Tingkat 2)</label>
                        <input type="number" step="0.1" value="{{ $this->part->flap_offset_mm }}" wire:change="updateField('flap_offset_mm', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px]">
                    </div>
                </div>
            </div>

            {{-- ============ ENGINE TINGKAT 1 + PREVIEW ============ --}}
            <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden">
                <h3 class="text-xs text-navy uppercase font-bold px-3.5 py-2 bg-[#f4f6fa] border-b border-[#d7dbe2] flex items-center justify-between">
                    <span>🎯 Engine Imposition (Tingkat 1: Flap Sweep · Tingkat 2: Interlock)</span>
                    @if ($this->activeLayoutGroup)
                        <span class="text-[10.5px] font-bold {{ $this->activeLayoutGroup->status === 'CONFIRMED' ? 'text-[#0e8a4f]' : 'text-[#8a5a00]' }}">
                            {{ $this->activeLayoutGroup->status }} · {{ $this->activeLayoutGroup->up_count }} UP
                        </span>
                    @endif
                </h3>
                <div class="p-3.5">
                    <div class="flex items-end gap-2.5 mb-3">
                        <div class="flex flex-col gap-0.5">
                            <label class="text-[11px] text-muted font-semibold">Ukuran Plano</label>
                            <select wire:model="selectedPlanoId" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px]">
                                @foreach ($this->planoSizes as $p)
                                    <option value="{{ $p->id_plano_size }}">{{ $p->plano_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="button" wire:click="runEngineTingkat1"
                            class="px-3.5 py-1.5 rounded text-[11px] font-bold bg-[#2c7be5] text-white cursor-pointer">▶ Tingkat 1 (Flap Sweep)</button>
                        <button type="button" wire:click="runEngineTingkat2"
                            class="px-3.5 py-1.5 rounded text-[11px] font-bold bg-[#7c5cd6] text-white cursor-pointer">▶ Tingkat 2 (Interlock)</button>
                        <button type="button" wire:click="runEngineBestSheet" title="Tool #2 -- coba SEMUA plano aktif, bukan cuma yg dipilih di dropdown di atas"
                            class="px-3.5 py-1.5 rounded text-[11px] font-bold bg-[#b6852a] text-white cursor-pointer">▶ Cari Plano Termurah</button>

                        @if ($this->activeLayoutGroup)
                            @if ($this->activeLayoutGroup->status === 'CONFIRMED')
                                <button type="button" wire:click="unlockLayout" class="px-3.5 py-1.5 rounded text-[11px] font-bold bg-[#f4f6fa] border border-[#d7dbe2] text-navy cursor-pointer">🔓 Buka Kunci</button>
                            @else
                                <button type="button" wire:click="confirmLayout" class="px-3.5 py-1.5 rounded text-[11px] font-bold bg-[#22a06b] text-white cursor-pointer">✓ Konfirmasi Layout</button>
                            @endif
                        @endif
                    </div>

                    @if (count($sweepCandidates) > 0)
                        <div class="text-[11px] text-muted mb-2">Top {{ count($sweepCandidates) }} hasil (ranking UP terbanyak, lalu efisiensi tertinggi) — pilih salah satu:</div>
                        <div class="flex gap-2.5 flex-wrap">
                            @foreach ($sweepCandidates as $i => $c)
                                <div class="border {{ $i === 0 ? 'border-[#22a06b]' : 'border-[#dde3ec]' }} rounded-md p-2.5 w-[280px]">
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="text-[11px] font-bold text-navy">
                                            @isset($c['plano_name']){{ $c['plano_name'] }} · @endisset
                                            Lidah {{ $c['flap_length_mm'] }}mm
                                            @isset($c['shift_offset_mm'])· Shift {{ $c['shift_offset_mm'] }}mm @endisset
                                            · {{ $c['method'] }}
                                        </span>
                                        @if ($i === 0) <span class="text-[9.5px] bg-[#eaf7ee] text-[#0e8a4f] px-1.5 py-0.5 rounded font-bold">TERBAIK</span> @endif
                                    </div>
                                    <div class="bg-[#fbfcfd] border border-[#dde3ec] rounded flex items-center justify-center" style="height:180px">
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
                    @elseif ($this->activeLayoutGroup)
                        <div class="text-[11.5px] px-2.5 py-2 rounded-md leading-relaxed bg-[#f0f7f4] border border-[#c3e6d1] text-[#0e5c38]">
                            Layout aktif: <b>{{ $this->activeLayoutGroup->method }} = {{ $this->activeLayoutGroup->up_count }} up</b>
                            (efisiensi {{ number_format($this->activeLayoutGroup->efficiency_pct, 0) }}%, lidah {{ $this->part->flap_length_mm }}mm).
                            @if ($this->activeLayoutGroup->die_rule_meter)
                                Estimasi meter lari pisau (Tool #4): <b>{{ number_format($this->activeLayoutGroup->die_rule_meter, 2) }} m</b>.
                            @endif
                            Jalankan Engine lagi kalau mau coba opsi lain.
                        </div>
                    @else
                        <div class="text-[11.5px] px-2.5 py-2 rounded-md leading-relaxed bg-[#e8f4ff] border border-[#b8d9f5] text-[#1560a8]">
                            Belum ada layout tersimpan. Isi dimensi badan (cm_length/cm_width) &amp; rentang lidah di atas, lalu klik Jalankan Engine.
                        </div>
                    @endif
                </div>
            </div>

            {{-- ============ TOOL #10: INTRA-PRODUCT PART COMBINER ============ --}}
            <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden">
                <h3 class="text-xs text-navy uppercase font-bold px-3.5 py-2 bg-[#f4f6fa] border-b border-[#d7dbe2]">
                    🧩 Tool #10 — Gabung Part dalam Produk Sama
                </h3>
                <div class="p-3.5">
                    @if ($this->combinableParts->isEmpty())
                        <div class="text-[11.5px] text-muted">Produk ini cuma punya 1 part terdaftar — tidak ada pasangan utk digabung.</div>
                    @else
                        <div class="flex items-end gap-2.5 mb-3">
                            <div class="flex flex-col gap-0.5">
                                <label class="text-[11px] text-muted font-semibold">Gabung dengan part</label>
                                <select wire:model="combineWithPartId" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px] min-w-[220px]">
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
            <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden">
                <h3 class="text-xs text-navy uppercase font-bold px-3.5 py-2 bg-[#f4f6fa] border-b border-[#d7dbe2]">🔀 Urutan Proses (Routing)</h3>
                <div class="p-3.5">
                    <table class="w-full text-xs mb-2.5">
                        <thead>
                            <tr>
                                <th class="text-left px-2 py-1.5 text-[9.5px] text-muted uppercase font-bold border-b border-[#e2e7ee]">Seq</th>
                                <th class="text-left px-2 py-1.5 text-[9.5px] text-muted uppercase font-bold border-b border-[#e2e7ee]">Proses</th>
                                <th class="text-left px-2 py-1.5 text-[9.5px] text-muted uppercase font-bold border-b border-[#e2e7ee]">Mesin</th>
                                <th class="text-left px-2 py-1.5 text-[9.5px] text-muted uppercase font-bold border-b border-[#e2e7ee]">Catatan</th>
                                <th class="border-b border-[#e2e7ee]"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->part->processes as $pp)
                                <tr wire:key="proc-{{ $pp->id_part_process }}">
                                    <td class="px-2 py-1.5 border-b border-[#eef1f5]">{{ $pp->step_seq }}</td>
                                    <td class="px-2 py-1.5 border-b border-[#eef1f5] font-semibold">{{ $pp->process?->process_name }}</td>
                                    <td class="px-2 py-1.5 border-b border-[#eef1f5]">{{ $pp->machine?->machine_name ?? '—' }}</td>
                                    <td class="px-2 py-1.5 border-b border-[#eef1f5] text-muted">{{ $pp->note }}</td>
                                    <td class="px-2 py-1.5 border-b border-[#eef1f5] text-right">
                                        <button type="button" wire:click="removeProcess({{ $pp->id_part_process }})" class="text-[11px] text-[#c0392b] cursor-pointer">hapus</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-2 py-3 text-center text-muted">Belum ada routing.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="flex items-end gap-2 flex-wrap">
                        <select wire:model="newProcessId" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px]">
                            <option value="">— Proses —</option>
                            @foreach ($this->processOptions as $po)
                                <option value="{{ $po->id_process }}">{{ $po->process_name }}</option>
                            @endforeach
                        </select>
                        <select wire:model="newMachineId" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px]">
                            <option value="">— Mesin (opsional) —</option>
                            @foreach ($this->machineOptions as $mo)
                                <option value="{{ $mo->machine_id }}">{{ $mo->machine_name }}</option>
                            @endforeach
                        </select>
                        <input type="text" wire:model="newProcessNotes" placeholder="Catatan (opsional)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px] flex-1 min-w-[140px]">
                        <button type="button" wire:click="addProcess" class="px-3 py-1.5 rounded text-[11px] font-bold bg-navy text-white cursor-pointer">+ Tambah</button>
                    </div>
                </div>
            </div>

            {{-- ============ FINISHING ============ --}}
            <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden">
                <h3 class="text-xs text-navy uppercase font-bold px-3.5 py-2 bg-[#f4f6fa] border-b border-[#d7dbe2]">✨ Finishing</h3>
                <div class="p-3.5">
                    <table class="w-full text-xs mb-2.5">
                        <thead>
                            <tr>
                                <th class="text-left px-2 py-1.5 text-[9.5px] text-muted uppercase font-bold border-b border-[#e2e7ee]">Jenis</th>
                                <th class="text-left px-2 py-1.5 text-[9.5px] text-muted uppercase font-bold border-b border-[#e2e7ee]">Catatan</th>
                                <th class="border-b border-[#e2e7ee]"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->part->finishings as $f)
                                <tr wire:key="fin-{{ $f->id_part_finishing }}">
                                    <td class="px-2 py-1.5 border-b border-[#eef1f5] font-semibold">{{ $f->finishingType?->finishing_name }}</td>
                                    <td class="px-2 py-1.5 border-b border-[#eef1f5] text-muted">{{ $f->notes }}</td>
                                    <td class="px-2 py-1.5 border-b border-[#eef1f5] text-right">
                                        <button type="button" wire:click="removeFinishing({{ $f->id_part_finishing }})" class="text-[11px] text-[#c0392b] cursor-pointer">hapus</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-2 py-3 text-center text-muted">Belum ada finishing.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="flex items-end gap-2 flex-wrap">
                        <select wire:model="newFinishingTypeId" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px]">
                            <option value="">— Jenis Finishing —</option>
                            @foreach ($this->finishingOptions as $fo)
                                <option value="{{ $fo->id_finishing_type }}">{{ $fo->finishing_name }}</option>
                            @endforeach
                        </select>
                        <input type="text" wire:model="newFinishingNotes" placeholder="mis. Gold Puyeh (opsional)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px] flex-1 min-w-[140px]">
                        <button type="button" wire:click="addFinishing" class="px-3 py-1.5 rounded text-[11px] font-bold bg-navy text-white cursor-pointer">+ Tambah</button>
                    </div>
                </div>
            </div>

            {{-- ============ ATTACHMENT ============ --}}
            <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden">
                <h3 class="text-xs text-navy uppercase font-bold px-3.5 py-2 bg-[#f4f6fa] border-b border-[#d7dbe2]">📎 Referensi (JPEG wajib utk preview, PDF/AI opsional)</h3>
                <div class="p-3.5">
                    <div class="flex gap-2.5 flex-wrap mb-3">
                        @forelse ($this->part->attachments as $att)
                            <div class="border border-[#dde3ec] rounded-md p-2 w-[140px]" wire:key="att-{{ $att->id_attachment }}">
                                @if ($att->file_type === 'JPEG')
                                    <img src="{{ asset('storage/'.$att->file_path) }}" class="w-full h-[90px] object-cover rounded mb-1.5" alt="preview">
                                @else
                                    <div class="w-full h-[90px] bg-[#f4f6fa] rounded mb-1.5 flex items-center justify-center text-2xl">📄</div>
                                @endif
                                <div class="text-[10px] font-bold text-navy truncate">{{ $att->original_filename }}</div>
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
                        <select wire:model="newAttachmentType" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px]">
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
