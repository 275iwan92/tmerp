<?php

use App\Models\Master\MatPlanoSize;
use App\Models\Master\ProdLayoutGroup;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdPart;
use App\Models\Sdi\IqpLayout;
use App\Models\Sdi\IqpPart;
use App\Models\Sys\SysConstant;
use App\Services\ProDev\ArtworkAnalyzer;
use App\Services\ProDev\ProcurementBridge;
use App\Services\ProDev\SpecEngine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

// ProDev = "Imposition Plano Optimizer", port 1:1 dari
// docs/mockups/mockup_prodev_imposition.html (instruksi user 2026-09-14:
// "BUAT SESUAI MOCKUP, mulai dari tampilan sampai behaviour").
//
// Layar ProDev LAMA (workbench 3-kolom + grid + Master Card) tidak dihapus,
// tapi diarsipkan jadi production/old_prodev*.blade.php (route
// production.old-prodev*, sengaja TIDAK ditaut dari menu mana pun).
//
// Seluruh interaksi (drag/rotate/mirror nesting, kalkulator PCS<->PLANO,
// ranking plano) berjalan di CLIENT persis seperti mockup -- komponen
// Livewire ini cuma penyedia data master, dan markup-nya di-wire:ignore
// supaya morph Livewire tidak merusak state SVG yang dipegang JS.
//
// Yang SUDAH disambung ke data nyata: daftar job (prod_part + asal Inquiry),
// daftar mesin cetak (prod_machine), daftar plano (mat_plano_size), gap
// default (sys_constant PRODEV_GAP_MM).
//
// PENYIMPANAN HASIL (2026-09-14, instruksi user -- "SETIAP LAYAR ADA SAVE
// DAN CONFIRM, confirm PASTI ke Product Costing", simpan ke 2 ARAH):
// tombol "Simpan Layout" (saveLayout(), draft) & "Confirm" (saveLayout()
// dgn $confirm=true) menulis ke DUA tabel paralel yang SUDAH ADA sejak DDL
// awal TIER_3A -- BUKAN skema baru:
//   1) tm2mst.prod_layout_group (+pivot prod_layout_group_part) -- MASTER,
//      reusable lintas Inquiry, dipakai APA ADANYA oleh ProCost Mode #1
//      (inquiry/costing.blade.php::pickLayoutGroup(), baca status=CONFIRMED).
//      Sama persis pola pickCandidate()/confirmLayout() di old_prodev.blade.php
//      (workbench lama, diarsipkan) -- HANYA sumber datanya beda (nesting
//      manual mockup ini, bukan ImpositionEngine::run()).
//   2) tm2sdi.iqp_layout -- SNAPSHOT per Inquiry Part (prinsip snapshot
//      CLAUDE.md §4), ditulis KALAU DAN HANYA KALAU job ini Mode #1 (ada
//      iqp_part.prod_part_id yang balik ke part ini). Mode #2/demo cuma
//      dapat baris (1), tidak ada (2) krn memang tidak ada Inquiry sumber.
// Confirm men-set status=CONFIRMED di KEDUA tabel lalu (kalau ada Inquiry
// asal) kirim url inquiry.costing ke JS -- itulah "confirm pasti ke Product
// Costing" yg dimaksud, bukan cuma flag status tanpa arah.
new #[Title('Pro-Dev — TELASIH MAKMUR ERP')] class extends Component
{
    use WithFileUploads;

    // Sampel UAT (mastercard_code -> nama artwork vector di JS). Barisnya data
    // NYATA di prod_part (migration 2026_09_17_000002) supaya muncul di grid,
    // tapi gambarnya tetap artwork vector bawaan mockup karena strukturnya
    // (Tray Lid / 2-Piece Wrap / Lock Bottom) belum ada di pustaka dieline DSL.
    private const SAMPLE_ARTWORK = [
        'UAT-ALSAMSI-TOP' => 'alsamsi_top',
        'UAT-OSIMA' => 'osima',
        'UAT-ALSAMSI-SIG' => 'alsamsi_sig',
    ];

    public ?int $partId = null;

    // Job yang sedang dibuka di canvas -- di-set dari JS tiap ganti job, supaya
    // upload artwork tahu part mana yang dituju (sisanya layar ini client-side).
    public ?int $activeJobPartId = null;

    public $artworkUpload = null;

    public string $artworkError = '';

    // Layar ini SELALU dibuka dari grid, jadi job-nya sudah pasti sejak awal
    // (koreksi user 2026-09-16). Dibuka tanpa part = tidak ada yang bisa
    // dikerjakan -> dikembalikan ke grid, bukan menebak job pertama.
    public function mount(?int $part = null): void
    {
        if (! $part) {
            $this->redirect(route('production.prodev.list'), navigate: false);

            return;
        }

        $this->partId = $part;
        $this->activeJobPartId = $part;
    }

    public function artworkMaxMb(): float
    {
        return SysConstant::number('PRODEV_UPLOAD_MAX_MB', 25);
    }

    // Upload artwork dari ProDev ditulis ke iqp_part.artwork_path (keputusan
    // user 2026-09-14) -- SAMA persis dengan jalur upload di Inquiry Product
    // (disk 'public', folder inquiry/{id_part}/artwork), jadi satu file dipakai
    // bersama kedua layar, bukan dua salinan terpisah.
    public function updatedArtworkUpload(): void
    {
        $this->artworkError = '';

        $iqpPart = $this->activeJobPartId
            ? IqpPart::where('prod_part_id', $this->activeJobPartId)->first()
            : null;

        if (! $iqpPart) {
            $this->artworkUpload = null;
            $this->artworkError = 'Job ini tidak punya Inquiry Part (Mode #2 manual), artwork belum bisa disimpan.';

            return;
        }

        // PDF/AI/EPS ikut diterima sejak Imagick+Ghostscript dipasang
        // (2026-09-18): file vektor dirasterisasi di server untuk pratinjau
        // canvas dan analisa warna tinta.
        $this->validate([
            'artworkUpload' => 'file|max:'.($this->artworkMaxMb() * 1024),
        ], [], ['artworkUpload' => 'artwork']);

        $ext = strtolower($this->artworkUpload->getClientOriginalExtension());

        if (! in_array($ext, ['jpg', 'jpeg', 'png', ...ArtworkAnalyzer::VECTOR], true)) {
            $this->artworkUpload = null;
            $this->artworkError = 'Format artwork yang didukung: JPG, PNG, PDF, AI, EPS.';

            return;
        }

        if (in_array($ext, ArtworkAnalyzer::VECTOR, true) && ! ArtworkAnalyzer::available()) {
            $this->artworkUpload = null;
            $this->artworkError = 'File vektor butuh Imagick + Ghostscript di server -- belum aktif.';

            return;
        }

        $old = $iqpPart->artwork_path;
        $path = $this->artworkUpload->store("inquiry/{$iqpPart->id_part}/artwork", 'public');

        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
            Storage::disk('public')->delete($old.'.preview.png');
        }

        $iqpPart->update([
            'artwork_path' => $path,
            'artwork_original_filename' => $this->artworkUpload->getClientOriginalName(),
        ]);

        // Analisa warna + pratinjau langsung di server (tak perlu browser).
        $analyzer = app(ArtworkAnalyzer::class);
        $abs = Storage::disk('public')->path($path);
        $previewAbs = in_array($ext, ArtworkAnalyzer::VECTOR, true) ? $abs.'.preview.png' : null;
        $analysis = $analyzer->analyze($abs, $previewAbs);

        if ($analysis) {
            $iqpPart->update(['artwork_colors' => $analysis]);
        }

        $this->dispatch('prodev-artwork-updated',
            partId: $this->activeJobPartId,
            url: asset('storage/'.$path.($previewAbs && is_file($previewAbs) ? '.preview.png' : '')),
            name: $this->artworkUpload->getClientOriginalName(),
            colors: $analysis,
        );

        $this->artworkUpload = null;
        unset($this->jobs);
    }

    // Simpan (draft, bisa dipanggil berkali-kali) / Confirm (final, dikunci +
    // dikirim ke Product Costing) hasil nesting layar ini. $state datang dari
    // JS (lihat gatherState() di script), dalam cm/persen apa adanya dari UI --
    // konversi ke mm dilakukan di sini, bukan di client, supaya satuan yang
    // tersimpan di DB konsisten dengan kolom lama (*_mm) tanpa tergantung JS.
    public function saveLayout(array $state, bool $confirm = false): array
    {
        $part = ! empty($state['partId']) ? ProdPart::find($state['partId']) : null;

        if (! $part) {
            return ['ok' => false, 'message' => 'Job demo/tanpa Part nyata tidak bisa disimpan -- pilih job dari Master Part.'];
        }

        if (! $part->product_id) {
            return ['ok' => false, 'message' => 'Part ini belum punya product_id (produk induk) -- lengkapi dulu di Master Produk sebelum simpan layout.'];
        }

        $upCount = (int) ($state['upCount'] ?? 0);

        if ($upCount < 1) {
            return ['ok' => false, 'message' => 'Belum ada object di canvas -- atur nesting dulu sebelum simpan.'];
        }

        $login = Auth::user()->login;
        $plano = ! empty($state['planoId']) ? MatPlanoSize::find($state['planoId']) : null;
        $canvasLMm = round((float) ($state['canvasL'] ?? 0) * 10, 2);
        $canvasWMm = round((float) ($state['canvasW'] ?? 0) * 10, 2);
        $efficiencyPct = round(max(0, min(100, (float) ($state['efficiencyPct'] ?? 0))), 2);

        // Pisau = bentang seluruh susunan (bukan 1 blank). Nama mengikuti pola
        // Master Card: "PISAU <PRODUK> ISI <n> MATA".
        $knifeW = isset($state['knifeW']) ? round((float) $state['knifeW'], 2) : null;
        $knifeH = isset($state['knifeH']) ? round((float) $state['knifeH'], 2) : null;
        $knifeCode = mb_substr('PISAU '.mb_strtoupper($part->product?->product_name ?: $part->part_name)." ISI {$upCount} MATA", 0, 80);

        $layoutDetail = [
            'source' => 'imposition_plano_optimizer',
            'canvas_l_cm' => $state['canvasL'] ?? null,
            'canvas_w_cm' => $state['canvasW'] ?? null,
            'gap_cm' => $state['gapCm'] ?? null,
            'plano_name' => $state['spec']['planoName'] ?? null,
            'machine' => $state['machine'] ?? null,
            // Mesin pond ikut disimpan: dipakai Work Order saat menurunkan BOP.
            'diecut_machine_id' => $state['spec']['diecutMachineId'] ?? null,
            'objects' => $state['objects'] ?? [],
        ];

        // ===== ARAH 1: MASTER (tm2mst.prod_layout_group), reusable lintas Inquiry.
        // Draft existing (punya part ini, BELUM CONFIRMED) ditimpa, bukan bikin baris
        // baru terus-menerus -- CONFIRMED tidak pernah ditimpa (pola sama old_prodev).
        $existing = $part->layoutGroups->sortByDesc('id_layout_group')->first();

        $payload = [
            'product_id' => $part->product_id,
            'plano_size_id' => $plano?->id_plano_size,
            'plano_w_mm' => $plano ? $plano->width_mm : $canvasLMm,
            'plano_h_mm' => $plano ? $plano->height_mm : $canvasWMm,
            'method' => $state['method'] ?? 'AUTO_GRID',
            'up_count' => $upCount,
            'knife_code' => $knifeCode,
            'knife_w_cm' => $knifeW,
            'knife_h_cm' => $knifeH,
            'efficiency_pct' => $efficiencyPct,
            'waste_pct' => round(100 - $efficiencyPct, 2),
            'layout_detail' => $layoutDetail,
            'updated_by' => $login,
        ];

        if ($existing && $existing->status !== 'CONFIRMED') {
            $existing->update($payload);
            $group = $existing;
            $group->parts()->sync([$part->id_part => ['qty_up_in_group' => $upCount]]);
        } else {
            $payload['created_by'] = $login;
            $payload['status'] = 'DRAFT';
            $group = ProdLayoutGroup::create($payload);
            $group->parts()->attach($part->id_part, ['qty_up_in_group' => $upCount]);
        }

        if (! $group->mastercard_code) {
            $group->update(['mastercard_code' => "MC-{$group->id_layout_group}"]);
        }

        // ===== ARAH 2: SNAPSHOT INQUIRY (tm2sdi.iqp_layout), HANYA kalau part ini
        // Mode #1 (lahir dari/tertaut ke Inquiry) -- Mode #2/demo tidak py Inquiry
        // sumber, jadi sengaja TIDAK menulis baris paksaan di sini.
        $iqpPart = IqpPart::where('prod_part_id', $part->id_part)->first();
        $costingUrl = null;

        if ($iqpPart) {
            // firstOrNew + exists (BUKAN updateOrCreate): created_by hanya boleh
            // diisi saat baris benar-benar LAHIR, dan tidak boleh ikut ketimpa
            // tiap kali disimpan ulang -- updateOrCreate tidak bisa membedakan
            // keduanya (ketahuan saat uji: baris baru created_by-nya NULL).
            $iqpLayout = IqpLayout::firstOrNew(['part_id' => $iqpPart->id_part]);

            $iqpLayout->fill([
                // flat_w/flat_h sudah milimeter (dulu dikali 10 lagi).
                'flat_w_mm' => $iqpPart->flat_w ? round((float) $iqpPart->flat_w, 2) : $canvasLMm,
                'flat_h_mm' => $iqpPart->flat_h ? round((float) $iqpPart->flat_h, 2) : $canvasWMm,
                'gap_mm' => round((float) ($state['gapCm'] ?? 0) * 10, 2),
                'plano_size_id' => $plano?->id_plano_size,
                'plano_w_mm' => $payload['plano_w_mm'],
                'plano_h_mm' => $payload['plano_h_mm'],
                'gripper_mm' => SysConstant::number('PRODEV_GRIPPER_MM', 12.0),
                'margin_mm' => SysConstant::number('PRODEV_MARGIN_MM', 8.0),
                'method' => $payload['method'],
                'up_count' => $upCount,
                'knife_code' => $knifeCode,
                'knife_w_cm' => $knifeW,
                'knife_h_cm' => $knifeH,
                'efficiency_pct' => $efficiencyPct,
                'waste_pct' => $payload['waste_pct'],
                'layout_detail' => $layoutDetail,
                'updated_by' => $login,
            ]);

            if (! $iqpLayout->exists) {
                $iqpLayout->created_by = $login;
            }

            if ($confirm) {
                $iqpLayout->stat_confirm = true;
                $iqpLayout->confirmed_by = $login;
                $iqpLayout->confirmed_at = now();
            }

            $iqpLayout->save();

            $costingUrl = route('inquiry.costing', ['inquiry' => $iqpPart->inquiry_id]);
        }

        if ($confirm) {
            $group->update(['status' => 'CONFIRMED', 'confirmed_by' => $login, 'confirmed_at' => now()]);
        }

        // BOM / Ink / BOP (hasil engine atau override officer) ikut tersimpan
        // 2 arah pada Save yang sama.
        $engine = app(SpecEngine::class);
        $skipped = $engine->persist($part, $iqpPart,
            $engine->generate($part, $iqpPart, $this->specContext($state['spec'] ?? []), $this->specOverrides($state['specOverride'] ?? [])),
            $login,
            array_values(array_intersect((array) ($state['specReset'] ?? []), SpecEngine::SECTIONS)));

        unset($this->jobs);

        return [
            'ok' => true,
            'confirmed' => $confirm,
            'hasInquiry' => $iqpPart !== null,
            'costingUrl' => $costingUrl,
            'mastercardCode' => $group->mastercard_code,
            'knifeCode' => $knifeCode,
            'specSkipped' => $skipped,
        ];
    }

    // Pratinjau hasil engine (dan override officer yang sedang diedit) untuk
    // panel BOM / Ink / BOP -- tidak menulis apa pun.
    public function generateSpec(int $partId, array $ctx, array $overrides = []): array
    {
        $part = $partId === $this->partId ? ProdPart::find($partId) : null;

        if (! $part) {
            return ['ok' => false];
        }

        $iqp = IqpPart::where('prod_part_id', $part->id_part)->first();

        return ['ok' => true] + app(SpecEngine::class)->generate($part, $iqp, $this->specContext($ctx), $this->specOverrides($overrides));
    }

    private function specOverrides(array $o): array
    {
        return collect(SpecEngine::SECTIONS)
            ->filter(fn ($k) => isset($o[$k]) && is_array($o[$k]))
            ->mapWithKeys(fn ($k) => [$k => array_values(array_slice($o[$k], 0, 60))])
            ->all();
    }

    // Dropdown bahan BOM: cari di Master Bahan + stok tersedia gudang.
    public function searchMaterials(string $q): array
    {
        $q = trim($q);

        if (mb_strlen($q) < 2) {
            return [];
        }

        $rows = \App\Models\Master\MatRaw::where('activate', 1)
            ->where(fn ($w) => $w->where('material_name', 'like', "%{$q}%")->orWhere('material_code', 'like', "%{$q}%"))
            ->orderBy('material_name')
            ->limit(20)
            ->get(['material_id', 'material_code', 'material_name', 'category1', 'gram_weight', 'uom_stock']);

        $stock = app(SpecEngine::class)->stockFor($rows->pluck('material_id')->all());

        return $rows->map(fn ($m) => [
            'id' => $m->material_id,
            'code' => $m->material_code,
            'name' => $m->material_name,
            'category' => $m->category1,
            'gsm' => $m->gram_weight ?: null,
            'uom' => $m->uom_stock,
            'stock' => $stock[$m->material_id]['qty'] ?? 0,
        ])->all();
    }

    public function createPurchase(int $partId, int $materialId, float $qty, ?string $uom = null): array
    {
        $part = $partId === $this->partId ? ProdPart::with('product')->find($partId) : null;

        if (! $part) {
            return ['ok' => false, 'message' => 'Part tidak dikenali.'];
        }

        return app(ProcurementBridge::class)->purchaseMaterial(
            $part, IqpPart::with('inquiry')->where('prod_part_id', $partId)->first(), $materialId, $qty, Auth::user()->login,
            $uom ? mb_substr(strtoupper(trim($uom)), 0, 10) : null);
    }

    public function subcontract(int $partId, array $step, int $supplierId, ?int $coItemId = null, ?string $needDate = null, ?float $estCost = null): array
    {
        $part = $partId === $this->partId ? ProdPart::with('product')->find($partId) : null;
        $iqp = $part ? IqpPart::with('inquiry')->where('prod_part_id', $partId)->first() : null;

        if (! $iqp) {
            return ['ok' => false, 'message' => 'Subkon butuh Inquiry asal (job Mode #2 belum didukung).'];
        }

        $needDate = $needDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $needDate) ? $needDate : null;

        return app(ProcurementBridge::class)->subcontract(
            $part, $iqp, $step, $supplierId, $coItemId ?: null, $needDate, $estCost ?: null, Auth::user()->login);
    }

    #[Computed]
    public function lookups(): array
    {
        $mst = \Illuminate\Support\Facades\DB::connection('tm2mst');

        return [
            'processes' => $mst->table('prod_process')->where('activate', 1)->orderBy('process_name')
                ->get(['id_process as id', 'process_name as name', 'inhouse'])->all(),
            'machines' => ProdMachine::where('activate', true)->orderBy('machine_name')
                ->get(['machine_id as id', 'machine_name as name', 'machine_type as type'])->all(),
            'subcons' => $mst->table('sup_supplier')->where('activate', 1)->where('is_subcon', 1)->orderBy('supplier_name')
                ->get(['id_supplier as id', 'supplier_name as name'])->all(),
            'uoms' => ['LBR', 'RIM', 'M', 'ROLL', 'KG', 'G', 'PCS', 'LTR'],
            'roles' => ['KERTAS PLANO', 'SHEET', 'SF', 'SHEET / SF', 'TINTA', 'PLASTIK LAMINASI', 'LEM LAMINASI', 'LEM BOX',
                'FOIL HOT PRINT', 'VARNISH UV', 'TALI PACK', 'PLASTIK PACK', 'BAHAN LAIN'],
            'inks' => ['BLACK', 'CYAN', 'MAGENTA', 'YELLOW', 'PANTONE', 'SILVER', 'GOLD', 'WHITE', 'VARNISH'],
        ];
    }

    // Hasil analisa warna artwork (dihitung di browser dari gambar yang sama
    // dengan canvas) disimpan di Inquiry Part, supaya tidak dihitung ulang dan
    // bisa dipakai layar lain.
    public function saveArtworkColors(int $partId, array $analysis): array
    {
        $pct = fn ($v) => round(max(0, min(100, (float) $v)), 2);

        $clean = [
            'source' => mb_substr((string) ($analysis['source'] ?? ''), 0, 255),
            'analyzed_at' => now()->toDateTimeString(),
            'channels' => collect(['C', 'M', 'Y', 'K'])->mapWithKeys(fn ($c) => [$c => $pct($analysis['channels'][$c] ?? 0)])->all(),
            'colors' => collect($analysis['colors'] ?? [])->take(12)->map(fn ($c) => [
                'name' => mb_substr((string) ($c['name'] ?? ''), 0, 30),
                'hex' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($c['hex'] ?? '')) ? strtolower($c['hex']) : null,
                'pct' => $pct($c['pct'] ?? 0),
            ])->values()->all(),
        ];

        if ($partId === $this->partId) {
            IqpPart::where('prod_part_id', $partId)->update(['artwork_colors' => json_encode($clean)]);
        }

        return $clean;
    }

    private function specContext(array $ctx): array
    {
        $num = fn ($k) => isset($ctx[$k]) && is_numeric($ctx[$k]) ? (float) $ctx[$k] : null;

        return [
            'sheetL' => $num('sheetL'),
            'sheetW' => $num('sheetW'),
            'planoL' => $num('planoL'),
            'planoW' => $num('planoW'),
            'sheetsPerPlano' => (int) ($ctx['sheetsPerPlano'] ?? 1),
            'printMachineId' => isset($ctx['printMachineId']) ? (int) $ctx['printMachineId'] : null,
            'diecutMachineId' => isset($ctx['diecutMachineId']) ? (int) $ctx['diecutMachineId'] : null,
            'upCount' => (int) ($ctx['upCount'] ?? 0),
            'orderQty' => max(0, (int) ($ctx['orderQty'] ?? 0)),
            'planoName' => mb_substr((string) ($ctx['planoName'] ?? ''), 0, 60),
            'artworkColors' => is_array($ctx['artworkColors'] ?? null) ? $ctx['artworkColors'] : null,
        ];
    }

    #[Computed]
    public function gapCm(): float
    {
        return round(SysConstant::number('PRODEV_GAP_MM', 3.0) / 10, 2);
    }

    // Mesin cetak dari master. prod_machine.max_paper_size SAAT INI kosong di
    // semua baris, jadi bed dipakai dari asumsi (plano terbesar + toleransi)
    // dan ditandai est=true supaya panel info jujur bilang "belum diisi".
    #[Computed]
    public function machines(): array
    {
        $fallbackL = 102.0;
        $fallbackW = 72.0;

        $out = ProdMachine::where('activate', true)
            ->where('machine_type', 'Offset Printing')
            ->orderBy('machine_name')
            ->get()
            ->map(function ($m) use ($fallbackL, $fallbackW) {
                $bed = $this->parseBed($m->max_paper_size);

                return [
                    'id' => $m->machine_id,
                    'name' => $m->machine_name,
                    'L' => $bed['L'] ?? $fallbackL,
                    'W' => $bed['W'] ?? $fallbackW,
                    'est' => $bed['L'] === null,
                ];
            })
            ->values()
            ->all();

        return $out ?: [
            ['id' => null, 'name' => 'Komori GL440', 'L' => 102, 'W' => 72, 'est' => true],
            ['id' => null, 'name' => 'Heidelberg Speedmaster XL 75', 'L' => 74, 'W' => 53, 'est' => true],
        ];
    }

    // Mesin plong/ponds: canvas harus muat di MEJA PLONG juga, bukan cuma bed
    // mesin cetak (instruksi user 2026-09-16). max_paper_size seluruh mesin
    // Die Cutting SAAT INI kosong, jadi L/W sengaja dibiarkan null -- batas yang
    // tidak diketahui TIDAK dikarang, cukup diberitahukan supaya diisi di Master
    // Mesin. Filter dulu 'Potong' (ikut menarik guillotine), sejak daftar aset
    // 2026-09-21 jenisnya dipisah jadi 'Die Cutting' vs 'Paper Cutting'.
    #[Computed]
    public function dieCutters(): array
    {
        return ProdMachine::where('activate', true)
            ->where('machine_type', 'Die Cutting')
            ->orderBy('machine_name')
            ->get()
            ->map(function ($m) {
                $bed = $this->parseBed($m->max_paper_size);

                return [
                    'id' => $m->machine_id,
                    'name' => $m->machine_name,
                    'L' => $bed['L'],
                    'W' => $bed['W'],
                ];
            })
            // Yang ukuran mejanya sudah diketahui ditaruh di depan supaya pilihan
            // default benar-benar memberi batas, bukan mesin tanpa data. Tipe
            // 'Potong' di master masih mencampur mesin pond dgn mesin potong
            // kertas (POTONG BESAR/KECIL) -- keduanya tetap ditampilkan, tapi
            // yang punya bed nyata didahulukan.
            ->sortBy(fn ($d) => [$d['L'] === null ? 1 : 0, $d['name']])
            ->values()
            ->all();
    }

    // "102x72", "102 x 72 cm", "1020x720" (mm) -> cm
    private function parseBed(?string $raw): array
    {
        if (! $raw || ! preg_match('/(\d+(?:[.,]\d+)?)\s*[xX*]\s*(\d+(?:[.,]\d+)?)/', $raw, $m)) {
            return ['L' => null, 'W' => null];
        }

        $a = (float) str_replace(',', '.', $m[1]);
        $b = (float) str_replace(',', '.', $m[2]);

        if ($a > 300 || $b > 300) {
            $a /= 10;
            $b /= 10;
        }

        return ['L' => max($a, $b), 'W' => min($a, $b)];
    }

    #[Computed]
    public function planos(): array
    {
        return MatPlanoSize::where('activate', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id_plano_size,
                'name' => $p->plano_name.' cm',
                'L' => round(max((float) $p->width_mm, (float) $p->height_mm) / 10, 1),
                'W' => round(min((float) $p->width_mm, (float) $p->height_mm) / 10, 1),
            ])
            ->values()
            ->all();
    }

    // Job = HANYA part yang dipilih di grid. Dulu seluruh part dimuat supaya
    // bisa dipilih ulang lewat dropdown di form -- itu mubazir (user 2026-09-16:
    // "saat user pilih di grid... sudah pasti"), dan bikin layar kerja seolah
    // masih menawarkan pilihan yang sebenarnya sudah diputuskan.
    //
    // Geometri dicari berlapis: die_w/h_cm (kolom teknis ProDev) -> printsize_p/l
    // -> flat_w/h dari Inquiry. Kalau tidak satupun terisi, job TIDAK dikarang:
    // dikembalikan kosong dan alasannya disampaikan lewat jobIssue().
    #[Computed]
    public function jobs(): array
    {
        $parts = ProdPart::with('product')
            ->where('id_part', $this->partId)
            ->get();

        $origins = IqpPart::with('inquiry.customer')
            ->whereIn('prod_part_id', $parts->pluck('id_part'))
            ->get()
            ->keyBy('prod_part_id');

        $gap = $this->gapCm;
        $jobs = [];

        foreach ($parts as $p) {
            $src = $origins->get($p->id_part);

            // iqp_part.flat_w/flat_h = MILIMETER (hasil TMDieline), kolom teknis ProDev = cm. Sebelum
            // 2026-10-01 flat dipakai mentah sebagai cm -> blank "250 x 161 cm" yang tak muat di canvas mana pun.
            $l = (float) ($p->die_w_cm ?: $p->printsize_p ?: ($src?->flat_w ? $src->flat_w / 10 : 0));
            $w = (float) ($p->die_h_cm ?: $p->printsize_l ?: ($src?->flat_h ? $src->flat_h / 10 : 0));

            if ($l <= 0 || $w <= 0) {
                continue;
            }

            [$l, $w] = [max($l, $w), min($l, $w)];

            $spec = $src?->dieline_spec ?? [];

            $jobs[] = [
                'id' => 'part-'.$p->id_part,
                'kind' => 'real',
                'partId' => $p->id_part,
                'name' => $p->product && $p->product->product_name !== $p->part_name
                    ? $p->part_name.' — '.$p->product->product_name
                    : (string) $p->part_name,
                'category' => $p->part_type ?: 'Part Produksi',
                'fl' => (float) ($p->cm_length ?: 0),
                'fw' => (float) ($p->cm_width ?: 0),
                'fh' => (float) ($p->cm_height ?: 0),
                'l' => round($l, 2),
                'w' => round($w, 2),
                'bleed' => 0.3,
                'gap' => $gap,
                'grain' => 'L',
                'material' => $p->finishing ?: ($p->product?->product_name ?: '—'),
                'structure' => $p->joint_type ?: ($p->ecma_code ?: '—'),
                'art' => $p->cfg_color ?: '—',
                // Sampel UAT (AL SAMSI/OSIMA) punya artwork vector bawaan yang
                // digambar di JS -- dikenali lewat kode Master Card-nya, supaya
                // gambar aslinya tetap tampil dan bukan kotak polos.
                'type' => self::SAMPLE_ARTWORK[$p->mastercard_code] ?? null,
                'customer' => $src?->inquiry?->customer?->customer_name ?: 'Mode #2 — manual / standalone',
                'design' => $p->displayMastercardCode(),
                // Isian Master Card (cetak A4 landscape, permintaan user 2026-09-18).
                'designNo' => $src?->inquiry?->design_number ?: ($p->product?->design_no ?: '—'),
                'productName' => $p->product?->product_name ?: $p->part_name,
                'itemCode' => $p->mastercard_code ?: $p->part_code,
                'partName' => $p->part_name,
                'finishingText' => $p->finishing,
                'confirmed' => $this->layoutConfirmed($p),
                'today' => now()->translatedFormat('d M Y'),
                'reg' => $src?->inquiry?->inquiry_no ?: '—',
                // Canvas awal (juga jadi bias scoring di suggest()): pakai ukuran
                // lembar cetak yang sudah pernah dipakai part ini kalau ada.
                // Selalu [sisi panjang, sisi pendek] mengikuti konvensi L >= W.
                'history' => [
                    max($histL = (float) ($p->sheet_raw_w_cm ?: min(round($l * 2, 1), 102)), $histW = (float) ($p->sheet_raw_h_cm ?: min(round($w * 2, 1), 72))),
                    min($histL, $histW),
                ],
                'dieline' => ! empty($spec['elements']) ? $spec : null,
                'dielineDims' => $spec['dims'] ?? (object) [],
                'artworkUrl' => $src?->artwork_path ? asset('storage/'.$this->artworkDisplayPath($src->artwork_path)) : null,
                'artworkName' => $src?->artwork_original_filename,
                'canUpload' => $src !== null,
                'artColors' => $src?->artwork_colors,
                'saved' => app(SpecEngine::class)->saved($p),
                'orderQty' => app(SpecEngine::class)->tierQty($src),
                'coOptions' => app(ProcurementBridge::class)->coOptions($src),
            ];
        }

        // Tidak ada lagi job demo hardcode: AL SAMSI & OSIMA sekarang baris nyata
        // di prod_part (lihat SAMPLE_ARTWORK di atas), jadi masuk lewat grid
        // seperti job lain dan bisa dipakai UAT beneran.
        return $jobs;
    }

    // Layout part ini sudah di-CONFIRM? Master Card yang belum confirm dicap
    // "NOT CONFIRMED" (permintaan user 2026-09-18).
    private function layoutConfirmed(ProdPart $p): bool
    {
        return $p->layoutGroups()->where('status', 'CONFIRMED')->exists();
    }

    // File vektor digambar dari pratinjau PNG-nya (canvas tidak bisa
    // menggambar PDF/AI langsung).
    private function artworkDisplayPath(string $path): string
    {
        return Storage::disk('public')->exists($path.'.preview.png') ? $path.'.preview.png' : $path;
    }

    // Alasan jujur kalau part yang dipilih tidak bisa dikerjakan -- jangan diam
    // atau menampilkan job kosong tanpa keterangan.
    #[Computed]
    public function jobIssue(): ?string
    {
        if ($this->jobs !== []) {
            return null;
        }

        $part = ProdPart::find($this->partId);

        if (! $part) {
            return 'Part tidak ditemukan (id '.$this->partId.').';
        }

        return 'Part "'.$part->part_name.'" belum punya dimensi dieline. '
            .'Isi salah satu dulu: die_w_cm/die_h_cm, printsize_p/printsize_l di Master Part, '
            .'atau flat_w/flat_h di Inquiry Part asalnya.';
    }

    // Deep-link dari Inquiry ("Kirim ke ProDev") -> job part itu yang terpilih.
    #[Computed]
    public function initialJobIndex(): int
    {
        if (! $this->partId) {
            return 0;
        }

        foreach ($this->jobs as $i => $j) {
            if (($j['partId'] ?? null) === $this->partId) {
                return $i;
            }
        }

        return 0;
    }
}; ?>

<div>
    <script>
        window.__PRODEV = {
            jobs: @json($this->jobs),
            machines: @json($this->machines),
            dieCutters: @json($this->dieCutters),
            planos: @json($this->planos),
            initialJob: @json($this->initialJobIndex),
            jobIssue: @json($this->jobIssue),
            backUrl: @json(route('production.prodev.list')),
            lookups: @json($this->lookups),
            gripperMm: @json(SysConstant::number('PRODEV_GRIPPER_MM', 12.0)),
            marginMm: @json(SysConstant::number('PRODEV_MARGIN_MM', 8.0)),
        };
    </script>

    {{-- Input file NYATA dipegang Livewire, ditaruh DI LUAR wire:ignore supaya
         morph-nya tetap jalan. Tombol "Upload artwork" di panel kiri cuma
         memicu klik ke sini (label lintas-subtree lewat getElementById). --}}
    <div class="io-uploader">
        <input type="file" id="io-artFileReal" accept="image/jpeg,image/png,application/pdf,.ai,.eps" wire:model="artworkUpload">
        <div class="io-toast" style="display:none" wire:loading wire:target="artworkUpload">⏳ Mengunggah artwork…</div>
        @error('artworkUpload')
            <div class="io-toast bad">⚠ {{ $message }}</div>
        @enderror
        @if ($artworkError)
            <div class="io-toast bad">⚠ {{ $artworkError }}</div>
        @endif
    </div>

    <div wire:ignore class="io-app">
        @verbatim
        <style>
        /* CSS mockup_prodev_imposition.html -- SEMUA selector di-prefix .io-app
           / .io-* karena app.css punya rule global tak-berlapis (.grid, .toolbar,
           .pill, .note, label, input/select/textarea) yang kalau tidak diisolasi
           akan menimpa tampilan layar ini (lihat memory legacy .grid collision). */
        .io-app{--io-panel:#0d1b2d;--io-line:#294761;--io-text:#edf5ff;--io-muted:#9db0c4;--io-cyan:#35c8f5;--io-purple:#7732e2;
            background:linear-gradient(130deg,#07111f,#0b1c34 56%,#07111f);color:var(--io-text);
            font:14px/1.4 Inter,system-ui,Segoe UI,sans-serif;min-height:100vh;padding:20px}
        .io-app *{box-sizing:border-box}
        /* app.css:257-259 memberi border kotak + latar abu ke SEMUA th/td global.
           Dinetralkan dulu di sini, baru rule .io-spec di bawah yang berlaku. */
        .io-app table{width:100%;border-collapse:collapse}
        .io-app th,.io-app td{border:0;padding:0;background:none;color:inherit;text-align:left;font-weight:inherit}
        .io-shell{max-width:1780px;margin:auto}
        /* Top bar dipadatkan -- ruang vertikal dipakai canvas, bukan judul. */
        .io-top{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:11px}
        .io-top h1{font-size:18px;margin:0;color:var(--io-text)}
        .io-sub{margin:4px 0 0;color:var(--io-muted);font-size:13px}
        .io-badge{padding:7px 11px;border:1px solid #2c6c92;background:#0b2b44;color:#91e8ff;border-radius:999px;font-weight:700;font-size:12px}
        /* Tata letak diubah 2026-09-17 (permintaan user): canvas dulu terjepit di
           tengah antara dua panel, jadi sempit. Sekarang kedua panel naik ke BARIS
           ATAS berdampingan, dan canvas turun ke bawah selebar penuh. Dipetakan
           lewat grid-area supaya urutan markup tidak perlu diacak. */
        .io-layout{display:block}

        /* ===== Accordion horizontal (mockup draft user 2026-09-17) =====
           Satu bagian terbuka memakai sisa lebar; bagian lain menyempit jadi strip
           46px berjudul vertikal. Isi bagian yang terbuka mengalir KE BAWAH dalam
           beberapa kolom -- sengaja TANPA batas tinggi: versi sebelumnya membatasi
           tinggi panel sehingga CSS multi-kolom meluberkan isinya ke samping, dan
           user tidak mau panel yang di-scroll ke samping. */
        .io-acc{display:flex;gap:10px;align-items:stretch;margin-bottom:14px}
        .io-sec{display:flex;min-width:0;background:#0d1b2df2;border:1px solid var(--io-line);
            border-radius:14px;box-shadow:0 12px 28px #0005;overflow:hidden}
        .io-sec.open{flex:1 1 auto;flex-direction:column}
        .io-sec:not(.open){flex:0 0 46px}
        .io-sec-head{all:unset;box-sizing:border-box;cursor:pointer;display:flex;align-items:center;gap:8px;
            color:#c6eafb;font:700 13px/1.2 Inter,system-ui,Segoe UI,sans-serif;letter-spacing:.04em}
        .io-sec.open .io-sec-head{padding:12px 16px;border-bottom:2px solid var(--io-cyan);background:#0b2236}
        .io-sec:not(.open) .io-sec-head{width:46px;align-self:stretch;min-height:160px;justify-content:center;
            writing-mode:vertical-rl;transform:rotate(180deg);background:#0a1a2b}
        .io-sec:not(.open) .io-sec-head:hover{background:#12304a;color:#fff}
        .io-sec-head:focus-visible{outline:2px solid var(--io-cyan);outline-offset:-2px}
        .io-sec:not(.open) .io-sec-body{display:none}
        .io-sec-body{padding:14px 16px;columns:250px;column-gap:18px}
        .io-sec-body--single{columns:auto}
        .io-sec-body > .io-group{break-inside:avoid;border-top:0;padding-top:0;margin:0 0 14px}
        .io-sec-count{font-style:normal;font-size:10px;font-weight:800;padding:2px 6px;border-radius:9px;
            background:#17415f;color:#c8efff}
        .io-sec-count:empty{display:none}
        .io-empty{color:var(--io-muted);font-size:12px;padding:14px;border:1px dashed #315570;border-radius:9px}
        .io-src{display:inline-block;font-size:10px;font-weight:800;letter-spacing:.05em;padding:2px 7px;
            border-radius:5px;background:#17415f;color:#c8efff;margin-bottom:8px}
        .io-tbl{width:100%;border-collapse:collapse;font-size:12px}
        .io-tbl th{text-align:left;color:var(--io-muted);font-size:10.5px;font-weight:700;padding:5px 8px;
            border-bottom:1px solid #25445f;text-transform:uppercase;letter-spacing:.04em}
        .io-tbl td{padding:6px 8px;border-bottom:1px solid #1d3a52;color:#d9f5ff}
        .io-inputs{font-size:12px;color:#c6eafb;margin:4px 0 10px;line-height:2}
        .io-chip{display:inline-block;padding:1px 8px;border-radius:10px;background:#12304a;border:1px solid #2d5878;font-size:11px;line-height:1.7}
        .io-sw{display:inline-block;width:12px;height:12px;border-radius:3px;vertical-align:-2px;margin-right:6px;border:1px solid #ffffff40}
        .io-warns{margin:10px 0 0;padding-left:18px;color:#ffd9a1;font-size:11.5px;line-height:1.6}
        .io-subh{margin:12px 0 6px;font-size:10.5px;font-weight:800;letter-spacing:.05em;color:var(--io-muted);text-transform:uppercase}
        .io-colors{display:flex;flex-wrap:wrap;gap:6px}
        .io-colorchip{font-size:11.5px;color:#d9f5ff;padding:3px 9px;border-radius:8px;background:#0b2236;border:1px solid #1d3a52}
        .io-sectools{display:flex;flex-wrap:wrap;align-items:center;gap:6px 8px;margin-bottom:6px}
        .io-sectools .io-src{margin:0}
        .io-src.warn{background:#3a2a0c;color:#ffd08a;border-color:#7a5a1c}
        .io-note-s{font-size:10.5px;color:var(--io-muted)}
        .io-grow{flex:1}
        .io-mbtn{all:unset;box-sizing:border-box;cursor:pointer;font:700 11px/1 Inter,system-ui,sans-serif;color:#c6eafb;
            padding:6px 10px;border-radius:7px;border:1px solid #2d5878;background:#0f2a40;white-space:nowrap}
        .io-mbtn:hover{background:#15395a;color:#fff}
        .io-mbtn.on{background:#0e6b8f;border-color:#35b5e0;color:#fff}
        .io-mbtn.warn{border-color:#8a6420;color:#ffd08a;background:#2c2210}
        .io-mbtn:disabled{opacity:.5;cursor:wait}
        .io-ibtn{all:unset;cursor:pointer;width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;
            border-radius:5px;color:#9cc7de;font-size:10px}
        .io-ibtn:hover{background:#15395a;color:#fff}
        .io-ibtn.bad:hover{background:#5a1c1c;color:#ffb4b4}
        .io-sub{font-size:10.5px;color:var(--io-muted);margin-top:2px;font-weight:400}
        .io-tblwrap{overflow-x:auto}
        .io-nowrap{white-space:nowrap}
        .io-edit td{padding:4px 5px;vertical-align:top}
        .io-edit input,.io-edit select{width:100%;min-width:60px;padding:5px 6px;font-size:11.5px;margin:0}
        .io-edit input[type=color]{padding:1px;height:28px;min-width:40px}
        .io-edit + .io-mbtn{margin-top:8px}
        .io-rel{position:relative;min-width:220px}
        .io-dd{position:absolute;left:5px;right:5px;top:100%;z-index:20;max-height:260px;overflow:auto;background:#081626;
            border:1px solid #35b5e0;border-radius:8px;box-shadow:0 14px 30px #000a;padding:4px}
        .io-dd button{all:unset;box-sizing:border-box;display:block;width:100%;cursor:pointer;padding:6px 8px;border-radius:6px}
        .io-dd button:hover{background:#12304a}
        .io-dd b{display:block;font-size:11.5px;color:#e6f7ff}
        .io-dd span{font-size:10.5px;color:var(--io-muted)}
        .io-dialog{margin-top:10px;padding:12px;border:1px solid #7a5a1c;border-radius:10px;background:#1c160b;color:#f3e3c3;font-size:12px}
        .io-row3{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px;margin-top:8px}
        .io-row3 label{display:flex;flex-direction:column;gap:3px;font-size:11px;color:#e8d3a8;margin:0}
        .io-stat{display:inline-block;font-size:10px;font-weight:800;padding:2px 7px;border-radius:9px;white-space:nowrap}
        .io-stat.ok{background:#12391f;color:#8ff0b0}
        .io-stat.warn{background:#3d2c0c;color:#ffd08a}
        .io-stat.bad{background:#401616;color:#ffb0b0}
        .io-stat.mute{background:#1d2c3b;color:#a9bfd0}
        .io-panel,.io-canvas-card{background:#0d1b2df2;border:1px solid var(--io-line);border-radius:14px;box-shadow:0 12px 28px #0005}
        .io-panel{padding:16px}
        .io-panel h2{font-size:13px;text-transform:uppercase;letter-spacing:.09em;color:#c6eafb;margin:0 0 11px}
        .io-group{border-top:1px solid var(--io-line);padding-top:13px;margin-top:13px}
        .io-group:first-of-type{padding-top:0;margin-top:0;border:0}
        .io-app label{display:block;font-size:12px;color:#bed0df;margin:8px 0 4px;font-weight:400;text-transform:none;letter-spacing:0}
        .io-app select,.io-app input{width:100%;background:#081624!important;color:var(--io-text)!important;
            border:1px solid #375873!important;border-radius:8px!important;padding:9px!important;outline:none;font:inherit}
        .io-app select:focus,.io-app input:focus{border-color:var(--io-cyan)!important;box-shadow:0 0 0 3px #36c7f421}
        .io-app select option{background:#081624;color:var(--io-text)}
        .io-btn{border:1px solid #3b6c86;border-radius:8px;background:#123653;color:#effaff;padding:9px;cursor:pointer;font-weight:700;font:inherit}
        .io-btn:hover{background:#194866}
        .io-btn.green{background:#123d2f;border-color:#308b65;color:#beffda}
        .io-btn.warn{background:#4a3512;border-color:#9d6e24;color:#ffe1a4}
        .io-row{display:grid;grid-template-columns:1fr 1fr;gap:8px}
        .io-metric-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
        .io-metric{padding:8px;background:#081624;border:1px solid #25435e;border-radius:8px}
        .io-metric span{font-size:10px;color:var(--io-muted);display:block}
        .io-metric b{font-size:13px;word-break:break-word}
        .io-spec{background:#091827;border:1px solid #315570;border-radius:9px;padding:10px;margin-top:9px}
        .io-spec table{font-size:11px;width:100%;border-collapse:collapse}
        .io-spec td{padding:4px 0;border-bottom:1px solid #25445f}
        .io-spec td:last-child{text-align:right;color:#d9f5ff;font-weight:700}
        .io-notice{font-size:12px;padding:9px;border-radius:8px;margin-top:8px}
        .io-notice.info{color:#bdeaff;background:#102e47;border:1px solid #285b80}
        .io-notice.ok{color:#c3f8d9;background:#123827;border:1px solid #28794f}
        .io-notice.bad{color:#ffd4dd;background:#461825;border:1px solid #a33d54}
        .io-note{font-size:11px;color:var(--io-muted);margin:7px 0 0}
        .io-auto-box{background:#0a2439;border:1px solid #286487;border-radius:10px;padding:10px}
        .io-toggle-row{display:flex;align-items:center;justify-content:space-between;gap:10px}
        .io-toggle-row b{color:#a3eaff;font-size:13px}
        .io-toggle{position:relative;width:48px;height:27px;flex:none;margin:0}
        .io-toggle input{opacity:0;width:0!important;height:0;padding:0!important;border:0!important}
        .io-slider{position:absolute;inset:0;background:#5a2632;border:1px solid #a3465b;border-radius:30px;cursor:pointer;transition:.2s}
        .io-slider:before{content:"";position:absolute;width:19px;height:19px;left:3px;top:3px;background:#ffe9ec;border-radius:50%;transition:.2s}
        .io-toggle input:checked+.io-slider{background:#177351;border-color:#53d093}
        .io-toggle input:checked+.io-slider:before{transform:translateX(20px);background:#e8fff3}
        .io-manual-disabled{opacity:.45;pointer-events:none}
        .io-jobfix{background:#081624;border:1px solid #375873;border-radius:8px;padding:9px 10px}
        .io-jobfix b{display:block;font-size:13.5px;color:#d9f5ff;line-height:1.3}
        .io-jobfix span{display:block;margin-top:3px;font-size:11px;color:var(--io-muted)}
        .io-auto-result{margin-top:9px;padding:9px 10px;background:#08222f;border:1px solid #2b6a86;border-radius:8px}
        .io-auto-result span{display:block;font-size:10px;letter-spacing:.06em;text-transform:uppercase;color:var(--io-muted)}
        .io-auto-result b{font-size:22px;color:#9fe9ff;line-height:1.25}
        /* Tinggi ikut tinggi layar: makin tinggi area gambar, makin besar artwork
           ter-render (SVG-nya menskala mengikuti wadah), makin gampang di-drag. */
        .io-canvas-card{padding:15px;display:flex;flex-direction:column;min-height:max(600px,84vh)}
        .io-toolbar{display:flex;gap:7px;flex-wrap:wrap;align-items:center;margin-bottom:11px;background:none;border:0;padding:0}
        .io-toolbar .io-btn{padding:7px 9px}
        .io-status{margin-left:auto;color:var(--io-muted);font-size:12px}
        .io-pill{padding:3px 7px;border-radius:5px;background:#17415f;color:#c8efff;font-size:11px}
        .io-stage{position:relative;flex:1;min-height:max(520px,72vh);overflow:hidden;border:1px solid #284860;border-radius:11px;background:#071522;
            background-image:linear-gradient(#102940 1px,transparent 1px),linear-gradient(90deg,#102940 1px,transparent 1px);background-size:20px 20px}
        .io-rulerx{position:absolute;z-index:3;left:0;right:0;top:0;height:25px;background:#0b2032;border-bottom:1px solid #31536d;padding:5px 8px;font-size:10px;color:#88a7bd}
        .io-rulery{position:absolute;z-index:3;top:25px;bottom:0;width:25px;background:#0b2032;border-right:1px solid #31536d}
        .io-viewport{position:absolute;left:25px;right:0;top:25px;bottom:0;overflow:auto}
        /* SVG-nya bersistem koordinat tetap (viewBox 650x500) tapi UKURAN TAMPILnya
           mengikuti wadah -- tanpa ini melebarkan area canvas cuma menambah ruang
           kosong di kiri-kanan, gambarnya tetap sebesar itu-itu saja. Konversi
           koordinat pointer sudah memperhitungkan skala (lihat point()), jadi drag
           tetap akurat berapa pun ukuran tampilnya. */
        .io-wrap{width:100%;height:100%;display:flex;align-items:center;justify-content:center;transform-origin:center}
        .io-wrap svg{width:100%;height:100%;display:block}
        .io-sheetlabel{position:absolute;z-index:4;top:36px;left:38px;background:#0b2134de;padding:5px 8px;border-radius:6px;font-size:12px;font-weight:700}
        .io-warn{position:absolute;z-index:5;right:12px;top:36px;background:#591725e8;border:1px solid #dc4c69;color:#ffd7df;font-weight:700;padding:8px 10px;border-radius:7px;display:none}
        .io-warn.show{display:block}
        .io-print{fill:#f9fbff;stroke:#1e86b1;stroke-width:2}
        .io-safe{fill:none;stroke:#a8c7da;stroke-width:1;stroke-dasharray:5 4}
        .io-grain{stroke:#9c6b12;stroke-width:2;marker-end:url(#io-arrow)}
        .io-graintext{fill:#805700;font-size:10px;font-weight:700}
        .io-art{cursor:grab;touch-action:none}
        .io-app.dragging .io-art{cursor:grabbing}
        .io-art.selected .io-artfill{stroke:var(--io-purple)!important;stroke-width:3.4}
        .io-art.selected{filter:drop-shadow(0 0 5px #a06cffcc)}
        .io-artfill{fill:#c7f1ff;fill-opacity:.92;stroke:#056c90;stroke-width:1.4}
        /* Garis dieline WAJIB jelas terbaca berapa pun ukuran object di canvas
           (permintaan user 2026-09-14) -- karena itu strokenya ditebalkan dan
           dikunci non-scaling supaya tidak ikut mengecil saat object diperkecil. */
        .io-die{fill:none;stroke:#fc2458;stroke-width:1.9;stroke-dasharray:5 2.5}
        .io-fold{fill:none;stroke:#27a560;stroke-width:1.5;stroke-dasharray:4 2.5}
        .io-cutmark{stroke:#fc2458;stroke-width:1.4}
        .io-die,.io-fold,.io-cutmark{vector-effect:non-scaling-stroke}
        .io-uploader input{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
        .io-toast{position:fixed;right:16px;bottom:16px;z-index:60;padding:10px 14px;border-radius:9px;
            background:#123827;border:1px solid #28794f;color:#c3f8d9;font-size:12px;font-weight:700;box-shadow:0 8px 20px #0007}
        .io-toast.bad{background:#461825;border-color:#a33d54;color:#ffd4dd}
        .io-lbl{font-size:8px;fill:#06445d;font-weight:800;pointer-events:none}
        .io-out .io-artfill{fill:#ffc8d2!important;stroke:#d31f49!important}
        .io-out .io-die,.io-out .io-cutmark{stroke:#b91135}
        .io-legend{margin-top:10px;color:var(--io-muted);font-size:11px;display:flex;gap:14px;flex-wrap:wrap}
        .io-dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:4px}
        .io-upload{display:block;border:1px dashed #3d6685;border-radius:8px;background:#081929;color:#bce9ff;text-align:center;padding:9px;cursor:pointer}
        .io-upload input{display:none}
        .io-file{margin-top:4px;color:#8eb2c9;font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .io-mode-switch{display:grid;grid-template-columns:1fr 1fr;background:#071421;border:1px solid #315570;border-radius:9px;padding:3px;gap:3px}
        .io-mode-switch button{border:0;border-radius:6px;padding:8px 5px;background:transparent;color:#8eacc1;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit}
        .io-mode-switch button.active{background:#174667;color:#e8f8ff;box-shadow:0 2px 8px #0006}
        .io-compact{background:#091827;border:1px solid #315570;border-radius:11px;padding:12px;margin-top:9px}
        .io-result{padding:12px;border-radius:9px;background:#123d2f;border:1px solid #2f8f67;color:#c6ffe0;margin-top:10px}
        .io-result .caption{font-size:11px;color:#a4e7c1;text-transform:uppercase;letter-spacing:.05em}
        .io-result b{font-size:27px;line-height:1.2;color:#fff}
        .io-result small{font-size:12px;color:#c6ffe0}
        .io-mini-grid{display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-top:10px}
        .io-mini{background:#081624;border:1px solid #29465f;border-radius:7px;padding:8px}
        .io-mini span{display:block;color:var(--io-muted);font-size:10px}
        .io-mini b{font-size:14px}
        .io-bar{position:relative;height:23px;border-radius:6px;background:#081624;border:1px solid #29465f;margin-top:9px;overflow:hidden}
        .io-bar i{position:absolute;left:0;top:0;bottom:0;background:linear-gradient(90deg,#1a5f45,#4fd18a);transition:width .25s}
        .io-bar span{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:#eafff4;text-shadow:0 1px 2px #000a}
        .io-spec table.rank td{padding:3px 0}
        .io-spec table.rank td:nth-child(n+2){text-align:right}
        .io-spec table.rank tr.best td{color:#bdf5d6;font-weight:800}
        .io-spec table.rank th{font-size:10px;color:var(--io-muted);text-align:right;font-weight:700;padding-bottom:4px;border-bottom:1px solid #25445f}
        .io-spec table.rank th:first-child{text-align:left}
        .io-cut{min-height:150px;padding:6px;background:#07131f;border:1px solid #395a76;border-radius:8px;margin:9px 0;display:flex;align-items:center;justify-content:center;overflow:hidden}
        .io-instructions{font-size:12px;line-height:1.45;color:#c7e7f7;background:#102b41;border:1px solid #2d6689;border-radius:8px;padding:9px;margin-top:9px}
        .io-instructions b{color:#9eeaff}
        /* Layar sempit: kedua panel ikut menumpuk, canvas tetap paling bawah &
           selebar penuh. Urutannya dijaga lewat grid-template-areas, bukan
           mengubah urutan markup. */
        /* Layar sempit: accordion jadi vertikal (judul bagian yang tertutup
           tampil mendatar), canvas tetap paling bawah. */
        @media(max-width:900px){
            .io-acc{flex-direction:column}
            .io-sec:not(.open){flex:0 0 auto}
            .io-sec:not(.open) .io-sec-head{width:auto;height:auto;min-height:0;padding:11px 14px;
                writing-mode:horizontal-tb;transform:none}}
        @media(max-width:800px){.io-app{padding:10px}
            .io-sec-body{columns:1}
            .io-canvas-card{min-height:70vh}
            .io-top{align-items:flex-start}.io-badge{display:none}}

        /* ===== MASTER CARD: pratinjau pop-up + cetak A4 landscape 1 halaman ===== */
        .io-mc-modal{display:none;position:fixed;inset:0;z-index:9000;background:#040d18ee;backdrop-filter:blur(2px)}
        .io-mc-modal.open{display:flex;flex-direction:column}
        .io-mc-bar{display:flex;align-items:center;gap:9px;padding:10px 14px;background:#0b1f31;border-bottom:1px solid var(--io-line);
            color:#d9f5ff;font:700 13px Inter,system-ui,sans-serif}
        .io-mc-state{font-size:11px;font-weight:800;padding:2px 8px;border-radius:9px;background:#3d2c0c;color:#ffd08a}
        .io-mc-state.ok{background:#12391f;color:#8ff0b0}
        .io-mc-scroll{flex:1;overflow:auto;padding:18px;display:flex;justify-content:center;align-items:flex-start}
        /* Lembar 1 halaman A4 landscape: 297 x 210 mm dikurangi margin cetak. */
        /* Kotak cetak A4 landscape dikurangi margin 6mm: 285 x 198 mm. */
        .io-mc-sheet{position:relative;width:285mm;height:198mm;overflow:hidden;background:#fff;color:#111;
            box-shadow:0 20px 60px #0009;font:10px/1.25 Arial,Helvetica,sans-serif}
        .io-mc-sheet *{box-sizing:border-box}
        .io-mc-pad{padding:5mm 6mm;height:100%;position:relative}
        .io-mc-head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #111;padding-bottom:3px;margin-bottom:5px}
        .io-mc-head h2{margin:0;font-size:14px;letter-spacing:.02em}
        .io-mc-head .brand{font-size:14px;font-weight:700}
        .io-mc-head .brand i{font-weight:400;font-style:italic}
        .io-mc-head .item{font:700 11px "Courier New",monospace;text-align:right}
        .io-mc-body{display:grid;grid-template-columns:1fr 0.92fr;gap:7px;align-items:start}
        .io-mc-tbl{width:100%;border-collapse:collapse}
        .io-mc-tbl th,.io-mc-tbl td{border:1px solid #333;padding:2px 5px;vertical-align:middle;font-size:9.5px}
        /* Kolom label menyesuaikan isinya (bukan 30% paksa) supaya baris
           berkolom-banyak seperti Dimensi P/L/T tidak membungkus. */
        .io-mc-tbl th{background:#f1f3f7;text-align:left;font-weight:700;white-space:nowrap;width:1%}
        .io-mc-tbl td.mono{font-family:"Courier New",monospace}
        .io-mc-sub{margin-top:5px;display:grid;grid-template-columns:1.2fr 0.8fr;gap:6px;align-items:start}
        .io-mc-sub .io-mc-tbl td,.io-mc-sub .io-mc-tbl th{padding:1.5px 5px;font-size:9px}
        .io-mc-cap{border:1px solid #333;border-bottom:0;background:#f1f3f7;padding:2px 5px;font-weight:700;font-size:9.5px;text-align:center}
        /* Layout dicetak di atas PUTIH (hemat tinta, permintaan user 2026-09-18). */
        .io-mc-layout{border:1px solid #333;background:#fff;padding:3px}
        .io-mc-layout .ttl{color:#333;font:700 8.5px "Courier New",monospace;text-align:center;margin-bottom:2px}
        .io-mc-layout svg{width:100%;height:auto;max-height:96mm;display:block;background:#fff}
        .io-mc-note{color:#c0392b;font-weight:700;font-size:9.5px;text-align:center;margin-top:3px}
        .io-mc-extra{margin-top:5px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:5px;align-items:start}
        .io-mc-extra .io-mc-tbl th,.io-mc-extra .io-mc-tbl td{font-size:8.5px;padding:1.5px 4px}
        .io-mc-extra .io-mc-tbl td{white-space:nowrap}
        .io-mc-sign{margin-top:8px;display:grid;grid-template-columns:repeat(2,1fr);gap:10px 26px;text-align:center;font-size:9px;padding:0 10px}
        .io-mc-sign .who{color:#666;margin-bottom:30px}
        .io-mc-sign .line{border-top:1px solid #111;padding-top:2px;font-weight:700}
        .io-mc-stamp{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;z-index:2}
        .io-mc-stamp span{font:900 72px/1 Arial,Helvetica,sans-serif;color:#c0392b26;border:6px solid #c0392b26;
            padding:10px 26px;transform:rotate(-18deg);letter-spacing:.06em;white-space:nowrap}

        @media print{
            @page{size:A4 landscape;margin:6mm}
            html,body{background:#fff !important}
            .io-shell{display:none !important}
            .io-mc-modal{display:block !important;position:static !important;inset:auto;background:#fff;backdrop-filter:none}
            .io-mc-bar{display:none !important}
            .io-mc-scroll{overflow:visible;padding:0;display:block}
            .io-mc-sheet{width:285mm;height:198mm;box-shadow:none;overflow:hidden;page-break-inside:avoid;page-break-after:avoid}
            .io-mc-layout{background:#fff !important}
            .io-mc-stamp span{color:#c0392b40;border-color:#c0392b40;-webkit-print-color-adjust:exact;print-color-adjust:exact}
        }
        </style>

        <div class="io-shell">
            <div class="io-top">
                <div>
                    <h1>Imposition Plano Optimizer</h1>
                    <p class="io-sub">Pro-Dev — nesting manual &amp; optimasi plano per job cetak (artwork vector + garis dieline struktural)</p>
                </div>
                <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
                    <!-- Label "Back" dikunci berbahasa Inggris di kedua mode bahasa
                    (FORM_UI_STANDARD.md §1). href diisi JS dari window.__PRODEV.backUrl
                    karena blok ini ada di dalam @verbatim -- route() Blade tidak jalan. -->
                    <a class="io-btn" id="io-back" href="#">← Back</a>
                    <!-- 3 tombol aksi di top bar (permintaan user 2026-09-18):
                    Save All, Confirm, dan pratinjau/cetak Master Card. -->
                    <button class="io-btn" id="io-save">💾 Save All</button>
                    <button class="io-btn green" id="io-confirm">✓ Confirm</button>
                    <button class="io-btn" id="io-printMc">🖨 Preview &amp; Print Master Card</button>
                    <span class="io-status" id="io-status"></span>
                    <div class="io-badge" id="io-jobcount">LIVE PACKAGING JOBS</div>
                </div>
            </div>

            <div class="io-layout">
                <!-- Accordion horizontal (permintaan user 2026-09-17, dari mockup
                draft): SATU bagian terbuka lebar, sisanya menyempit jadi strip
                judul vertikal. Hemat ruang tanpa panel yang meluber ke samping,
                dan area gambar tetap di bawah selebar penuh. Semua id elemen
                dipertahankan, jadi logika JS di bawah tidak berubah. -->
                <div class="io-acc" id="io-acc">
                    <section class="io-sec" data-sec="impo">
                        <button type="button" class="io-sec-head"><span>Imposition &amp; Nesting</span></button>
                        <div class="io-sec-body">
                            <div class="io-group">
                                <h2>Job</h2>
                                <!-- Job sudah pasti sejak dipilih di grid (user 2026-09-16),
                                jadi ini identitas, BUKAN dropdown. -->
                                <div id="io-jobFixed" class="io-jobfix"></div>
                                <select id="io-product" style="display:none"></select>
                                <div id="io-metrics" class="io-metric-grid"></div>
                            </div>
                            <div class="io-group">
                                <h2>1. Jumlah Up</h2>
                                <div class="io-auto-box">
                                    <div class="io-toggle-row">
                                        <div>
                                            <b>Auto Up</b>
                                            <div class="io-note" id="io-modeText">Aktif: gunakan up maksimum yang memungkinkan</div>
                                        </div>
                                        <label class="io-toggle"><input id="io-autoUp" type="checkbox" checked><span class="io-slider"></span></label>
                                    </div>
                                    <!-- Saat Auto Up, jumlah up itu HASIL perhitungan, bukan angka
                                    yang diketik. Kotak isian baru muncul di mode Manual.
                                    Blok ini ada di dalam @verbatim: komentar Blade tidak diproses
                                    dan akan bocor jadi teks -- WAJIB komentar HTML. -->
                                    <div id="io-autoResult" class="io-auto-result">
                                        <span>Hasil optimasi</span><b id="io-autoResultValue">—</b>
                                        <div class="io-note" id="io-autoResultNote">Susunan dihitung dari ukuran canvas &amp; bentuk dieline.</div>
                                    </div>
                                    <div id="io-manualGroup" style="display:none">
                                        <label>Target up (manual)</label>
                                        <input id="io-manualUp" type="number" min="1">
                                        <p class="io-note">Canvas akan dicarikan ukuran terkecil yang memuat jumlah ini.</p>
                                    </div>
                                </div>
                                <h2 style="margin-top:14px">2. Gap</h2>
                                <label>Jarak antar artwork / dieline (cm)</label>
                                <input id="io-gap" type="number" min="0" step=".05" value=".3">
                            </div>
                            <div class="io-group">
                                <h2>3. Lembar Cetak / Canvas</h2>
                                <div class="io-row">
                                    <div><label>Length (cm)</label><input id="io-sl" type="number" min="1" step=".1"></div>
                                    <div><label>Width (cm)</label><input id="io-sw" type="number" min="1" step=".1"></div>
                                </div>
                                <button id="io-suggest" class="io-btn green" style="width:100%;margin-top:9px" title="Mengubah ukuran canvas DAN plano">✦ Suggestion</button>
                                <p id="io-suggestNote" class="io-note"></p>
                                <label>Grain direction</label>
                                <select id="io-grain">
                                    <option value="L">Along dieline length</option>
                                    <option value="W">Along dieline width</option>
                                    <option value="none">No grain constraint</option>
                                </select>
                            </div>
                            <div class="io-group">
                                <h2>Data Kalkulasi</h2>
                                <div class="io-mini-grid" style="margin-top:0">
                                    <div class="io-mini"><span>Canvas utilization</span><b id="io-yCanvas">—</b></div>
                                    <div class="io-mini"><span>Canvas waste</span><b id="io-yCanvasW">—</b></div>
                                    <div class="io-mini"><span>Plano sheet yield</span><b id="io-yPlano">—</b></div>
                                    <div class="io-mini"><span>Overall efficiency</span><b id="io-yAll">—</b></div>
                                </div>
                                <div class="io-bar"><i id="io-barFill"></i><span id="io-barTxt"></span></div>
                                <div id="io-impoDetail" class="io-spec"></div>
                            </div>
                            <div class="io-group">
                                <h2>Spesifikasi &amp; Artwork</h2>
                                <div id="io-spec" class="io-spec" style="margin-top:0"></div>
                                <label>Artwork asset</label>
                                <label class="io-upload">Upload artwork<input id="io-artFile" type="file" accept="image/*,.pdf,.ai,.svg"></label>
                                <div id="io-artName" class="io-file">Artwork bawaan job aktif</div>
                                <label>Separate dieline</label>
                                <label class="io-upload">Upload dieline<input id="io-dieFile" type="file" accept="image/*,.pdf,.ai,.svg,.dxf"></label>
                                <div id="io-dieName" class="io-file">Dieline struktural bawaan job aktif</div>
                            </div>
                        </div>
                    </section>

                    <section class="io-sec" data-sec="plano">
                        <button type="button" class="io-sec-head"><span>Plano Configuration</span></button>
                        <div class="io-sec-body">
                            <div class="io-group">
                                <h2>Mesin</h2>
                                <label>Printing machine</label>
                                <select id="io-machine"></select>
                                <div id="io-machineInfo" class="io-notice info"></div>
                                <label>Mesin plong / ponds</label>
                                <select id="io-diecut"></select>
                                <div id="io-diecutInfo" class="io-notice info"></div>
                            </div>
                            <div class="io-group">
                                <h2>Plano Optimization</h2>
                                <label>Market plano size</label>
                                <select id="io-plano"></select>
                                <button id="io-calcPlano" class="io-btn green" style="width:100%;margin-top:8px" title="Pilih plano paling hemat untuk ukuran canvas sekarang">⟳ Calculate Plano</button>
                                <div class="io-row">
                                    <div><label>Custom L</label><input id="io-cpl" type="number" step=".1" placeholder="cm"></div>
                                    <div><label>Custom W</label><input id="io-cpw" type="number" step=".1" placeholder="cm"></div>
                                </div>
                                <button id="io-custom" class="io-btn" style="width:100%;margin-top:8px">Use custom plano</button>
                                <div id="io-planoNote" class="io-notice info"></div>
                                <div id="io-cut" class="io-cut"></div>
                            </div>
                            <div class="io-group">
                                <h2>Ranking Plano</h2>
                                <div id="io-planoTable" class="io-spec" style="margin-top:0"></div>
                            </div>
                            <div class="io-group">
                                <h2>PCS ↔ PLANO Calculator</h2>
                                <div class="io-mode-switch">
                                    <button id="io-pcsMode" class="active">PCS → PLANO</button>
                                    <button id="io-planoMode">PLANO → PCS</button>
                                </div>
                                <div class="io-compact">
                                    <b id="io-inputLabel">Qty order (pcs)</b>
                                    <label for="io-converterInput" class="io-note" id="io-inputHint">Masukkan jumlah produk yang harus diproduksi</label>
                                    <input id="io-converterInput" type="number" min="0" value="100">
                                    <div class="io-result">
                                        <div class="caption" id="io-resultCaption">Plano required</div>
                                        <b id="io-resultValue">—</b> <small id="io-resultUnit">sheets</small>
                                    </div>
                                    <div class="io-mini-grid">
                                        <div class="io-mini"><span>Active up</span><b id="io-mUp">—</b></div>
                                        <div class="io-mini"><span>Yield / plano</span><b id="io-mYield">—</b></div>
                                        <div class="io-mini"><span>Products / plano</span><b id="io-mPerPlano">—</b></div>
                                        <div class="io-mini"><span>Waste / plano</span><b id="io-mWaste">—</b></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="io-sec" data-sec="bom">
                        <button type="button" class="io-sec-head"><span>Bill of Material</span><em class="io-sec-count" id="io-cnt-bom"></em></button>
                        <div class="io-sec-body io-sec-body--single"><div id="io-bom"></div></div>
                    </section>

                    <section class="io-sec" data-sec="ink">
                        <button type="button" class="io-sec-head"><span>Ink Configuration</span><em class="io-sec-count" id="io-cnt-ink"></em></button>
                        <div class="io-sec-body io-sec-body--single"><div id="io-ink"></div></div>
                    </section>

                    <section class="io-sec" data-sec="bop">
                        <button type="button" class="io-sec-head"><span>Bill of Process</span><em class="io-sec-count" id="io-cnt-bop"></em></button>
                        <div class="io-sec-body io-sec-body--single"><div id="io-bop"></div></div>
                    </section>
                </div>

                <main class="io-canvas-card">
                    <div class="io-toolbar">
                        <button class="io-btn" id="io-zout">− Zoom</button>
                        <span class="io-pill" id="io-zoom">100%</span>
                        <button class="io-btn" id="io-zin">+ Zoom</button>
                        <button class="io-btn" data-io-r="90">↻ +90°</button>
                        <button class="io-btn" data-io-r="-90">↺ −90°</button>
                        <button class="io-btn" data-io-r="180">↻ 180°</button>
                        <button class="io-btn" data-io-f="x">↔ Mirror X</button>
                        <button class="io-btn" data-io-f="y">↕ Mirror Y</button>
                        <button class="io-btn warn" id="io-resetLayout">Reset layout</button>
                        <button class="io-btn" id="io-autoNest">✦ Auto Nesting</button>
                    </div>
                    <!-- Status susunan + PERINGATAN (tabrakan garis potong, target up
                    tidak muat) sengaja ditaruh di sini, bukan di dalam accordion:
                    peringatan tidak boleh ikut tersembunyi saat bagiannya ditutup. -->
                    <div id="io-impoMode" class="io-notice info" style="margin:0 0 9px"></div>
                    <div class="io-stage">
                        <div class="io-rulerx">CANVAS / PRINT SHEET RULER — cm</div>
                        <div class="io-rulery"></div>
                        <div id="io-sheetLabel" class="io-sheetlabel"></div>
                        <div id="io-warning" class="io-warn">⚠ Artwork melewati batas canvas / print sheet</div>
                        <div class="io-viewport" id="io-viewport">
                            <div id="io-wrap" class="io-wrap">
                                <svg id="io-svg" width="650" height="500" viewBox="0 0 650 500">
                                    <defs>
                                        <marker id="io-arrow" markerWidth="6" markerHeight="6" refX="5" refY="3" orient="auto">
                                            <path d="M0 0L6 3 0 6Z" fill="#9c6b12"/>
                                        </marker>
                                    </defs>
                                    <rect id="io-sheet" class="io-print"/>
                                    <rect id="io-safeRect" class="io-safe"/>
                                    <g id="io-grainG"></g>
                                    <g id="io-arts"></g>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="io-legend">
                        <span><i class="io-dot" style="background:#c7f1ff;border:1px solid #056c90"></i>Artwork surface</span>
                        <span><i class="io-dot" style="background:#fc2458"></i>Die-cut / trim line</span>
                        <span><i class="io-dot" style="background:#27a560"></i>Fold / crease line</span>
                        <span><i class="io-dot" style="background:#9c6b12"></i>Grain</span>
                        <span><i class="io-dot" style="background:#7732e2"></i>Selected object</span>
                    </div>
                    <div class="io-instructions">
                        <b>Manual nesting:</b> pilih lalu drag artwork/dieline satu per satu. Gunakan Rotate dan Mirror untuk
                        mensimulasikan susunan berlawanan serta mengisi ruang kosong sesuai bentuk dieline. Setelah susunan
                        diatur, tombol <b>✦ Suggestion</b> tidak me-reset layout — susunan dirapatkan sesuai gap (antar-object
                        &amp; keliling), lalu ukuran canvas dan plano dipilih ulang. Tekan <b>Reset layout</b> bila ingin kembali ke grid otomatis.
                    </div>
                    <div id="io-valid" class="io-notice info">Set a valid canvas size.</div>
                </main>
            </div>
        </div>

        <!-- Pratinjau & cetak MASTER CARD (permintaan user 2026-09-18): pop-up
        besar, isinya seluruh informasi ProDev, dicetak A4 LANDSCAPE 1 halaman.
        Ditaruh DI LUAR .io-shell supaya saat mencetak cukup sembunyikan shell-nya
        (lihat @media print di style atas). Cap "NOT CONFIRMED" muncul selama
        layout belum di-Confirm. -->
        <div class="io-mc-modal" id="io-mcModal" role="dialog" aria-modal="true" aria-label="Master Card">
            <div class="io-mc-bar">
                <b>Preview Master Card</b>
                <span id="io-mcState" class="io-mc-state"></span>
                <span style="flex:1"></span>
                <button class="io-btn" id="io-mcPrint">🖨 Print (A4 landscape)</button>
                <button class="io-btn warn" id="io-mcClose">✕ Tutup</button>
            </div>
            <div class="io-mc-scroll">
                <div class="io-mc-sheet"><div class="io-mc-pad" id="io-mcSheet"></div></div>
            </div>
        </div>

        <script>
        (function () {
            const DATA = window.__PRODEV || { jobs: [], machines: [], planos: [], initialJob: 0 };
            const products = DATA.jobs;
            // Index 4 (machine id) / index 3 (plano id) ditambahkan supaya saveLayout()
            // bisa kirim plano_size_id yang benar ke server -- sebelumnya array ini
            // cuma bawa angka tampilan, id-nya hilang begitu sampai di client.
            const machines = DATA.machines.map(m => [m.name, m.L, m.W, m.est, m.id]);
            const planos = DATA.planos.map(p => [p.name, p.L, p.W, p.id]);
            // L/W bisa null = ukuran meja plong belum diisi di Master Mesin.
            const diecutters = (DATA.dieCutters || []).map(d => [d.name, d.L, d.W, d.id]);
            const GRIPPER = (DATA.gripperMm || 0) / 10;   // cm
            const MARGIN = (DATA.marginMm || 0) / 10;     // cm

            // Sistem koordinat gambar. Dulu dipatok 650x500: begitu area canvas
            // dibuat selebar layar, gambarnya TIDAK ikut membesar -- rasionya
            // terkunci, jadi yang bertambah cuma ruang kosong kiri-kanan. Sekarang
            // lebar viewBox mengikuti bentuk wadahnya, sehingga artwork benar-benar
            // memakai ruang yang ada (permintaan user: artwork besar & mudah di-drag).
            // Tingginya tetap 500 supaya skala angka di seluruh kode tidak berubah.
            const VB = { w: 650, h: 500 };
            const PAD_X = 50, PAD_Y = 70;   // sisa ruang tepi di dalam viewBox

            function usableVB() {
                return [VB.w - PAD_X, VB.h - PAD_Y];
            }

            function fitViewBox() {
                const box = $('viewport').getBoundingClientRect();
                if (!box.width || !box.height) return false;

                const lebarBaru = Math.round(Math.max(420, Math.min(2400, VB.h * box.width / box.height)));
                if (lebarBaru === VB.w) return false;

                VB.w = lebarBaru;
                $('svg').setAttribute('viewBox', '0 0 ' + VB.w + ' ' + VB.h);

                return true;
            }
            if (!products.length || !machines.length || !planos.length) {
                const box = document.getElementById('io-valid');
                box.className = 'io-notice bad';
                // Alasan spesifik dari server (mis. part belum punya dimensi dieline)
                // lebih berguna daripada pesan umum -- jangan biarkan layar diam.
                box.textContent = DATA.jobIssue
                    || 'Data master belum lengkap: butuh minimal 1 part berdimensi, 1 mesin cetak, dan 1 ukuran plano aktif.';
                return;
            }

            const $ = x => document.getElementById('io-' + x);
            const p0 = products[DATA.initialJob] || products[0];
            const m0 = machines[0];
            // Canvas awal tidak boleh lahir dalam keadaan invalid: history job bisa
            // lebih besar dari bed mesin aktif, jadi dipotong ke bed.
            const S = {
                p: p0, m: m0,
                L: Math.min(p0.history[0] || m0[1], m0[1]),
                W: Math.min(p0.history[1] || m0[2], m0[2]),
                g: p0.gap || .3,
                zoom: 1, auto: true, manualUp: 6, objs: [], selected: null, pl: planos[0], drag: null,
                conv: 'pcs', touched: false, sc0: 0, sx0: 0, sy0: 0,
                d: diecutters[0] || null,
            };
            const app = document.querySelector('.io-app');

            // Jembatan ke komponen Livewire induk: layar ini client-side, tapi
            // upload artwork tetap lewat Livewire (lihat updatedArtworkUpload()).
            function wire() {
                const root = document.querySelector('[wire\\:id]');
                return root && window.Livewire ? window.Livewire.find(root.getAttribute('wire:id')) : null;
            }
            function syncActiveJob() {
                const w = wire();
                if (w) w.set('activeJobPartId', S.p.partId, false);
            }

            // Susunan object di canvas dipegang dalam satuan PIXEL SVG (skala
            // S.sc0 + offset S.sx0/sy0 yang berubah tiap canvas di-resize) --
            // dikembalikan ke CM relatif sudut canvas sebelum dikirim ke server,
            // supaya isi layout_detail tetap bermakna walau nanti dibuka di
            // zoom/ukuran layar berbeda.
            function gatherState() {
                const sc = S.sc0 || 1;
                const cu = S.L * S.W ? upValid() * S.p.l * S.p.w / (S.L * S.W) * 100 : 0;
                const c = cut(S.pl);
                const sah = S.objs.filter(inCanvas);
                const b = (sah.length ? sah : S.objs).map(obox);

                return {
                    partId: S.p.partId,
                    canvasL: S.L,
                    canvasW: S.W,
                    gapCm: S.g,
                    upCount: upValid(),
                    method: S.touched ? 'MANUAL_NESTING' : 'AUTO_GRID',
                    planoId: S.pl[3] || null,
                    // Efisiensi yang disimpan = pemakaian material MENYELURUH
                    // (canvas utilization x plano yield), bukan cuma utilisasi
                    // canvas -- ini angka yang dipakai Costing utk waste.
                    efficiencyPct: +(cu * (c.util || 0)).toFixed(2),
                    machine: { id: S.m[4] || null, name: S.m[0], bedL: S.m[1], bedW: S.m[2] },
                    // Ukuran pisau = bentang seluruh susunan di canvas.
                    knifeW: b.length ? +((Math.max(...b.map(v => v.x + v.w)) - Math.min(...b.map(v => v.x))) / sc).toFixed(2) : null,
                    knifeH: b.length ? +((Math.max(...b.map(v => v.y + v.h)) - Math.min(...b.map(v => v.y))) / sc).toFixed(2) : null,
                    spec: specCtx(),
                    specOverride: overridesOf(S.p),
                    specReset: S.p.reset || [],
                    objects: S.objs.map(o => ({
                        x: +((o.x - S.sx0) / sc).toFixed(2),
                        y: +((o.y - S.sy0) / sc).toFixed(2),
                        w: +(o.w / sc).toFixed(2),
                        h: +(o.h / sc).toFixed(2),
                        r: o.r, fx: o.fx, fy: o.fy,
                    })),
                };
            }

            // Kanvas & spec bukan field form biasa -> dirty-guard pakai adaptor:
            // baseline per job diambil tepat sebelum interaksi user pertama
            // (hasil auto-engine saat load tidak dihitung perubahan).
            const savedState = {};
            let persistOk = false;
            const stateKey = () => JSON.stringify(gatherState());

            function registerDirtyGuard() {
                if (!window.tmerpDirty) return;
                ['pointerdown', 'keydown'].forEach(t => document.addEventListener(t, (e) => {
                    if (e.isTrusted && S.p.partId && savedState[S.p.partId] === undefined) savedState[S.p.partId] = stateKey();
                }, true));
                window.tmerpDirty.register({
                    isDirty: () => !!S.p.partId && savedState[S.p.partId] !== undefined && savedState[S.p.partId] !== stateKey(),
                    save: async () => { persistOk = false; await persist(false); return persistOk; },
                });
            }

            async function persist(confirm) {
                const w = wire();

                if (!w) { msg('Komponen belum siap, coba lagi sebentar.', 'bad'); return; }
                if (!S.p.partId) { msg('Job demo tidak bisa disimpan — pilih job nyata dari daftar Part.', 'bad'); return; }
                if (!ok()) { msg('Canvas masih di luar batas mesin — perbaiki dulu sebelum simpan.', 'bad'); return; }

                const btn = confirm ? $('confirm') : $('save');
                const label = btn.textContent;
                btn.disabled = true;
                btn.textContent = confirm ? 'Menyimpan & confirm…' : 'Menyimpan…';

                try {
                    const res = await w.call('saveLayout', gatherState(), confirm);

                    if (!res || !res.ok) {
                        msg((res && res.message) || 'Gagal menyimpan layout.', 'bad');
                        return;
                    }

                    // Yang tersimpan sekarang jadi acuan: reset sudah dieksekusi,
                    // override yang tersimpan berstatus OVERRIDE.
                    if (res.confirmed) S.p.confirmed = true;
                    if ($('mcModal').classList.contains('open')) openMasterCard();
                    S.p.reset = [];
                    SECS.forEach(k => { if (S.p.edit && S.p.edit[k]) { S.p.editSrc[k] = 'OVERRIDE'; S.p.saved = S.p.saved || {}; S.p.saved[k] = { source: 'OVERRIDE', rows: S.p.edit[k] }; } });
                    specKey = ''; specSoon();
                    persistOk = true;
                    savedState[S.p.partId] = stateKey();

                    if (!confirm) {
                        msg('Layout tersimpan sebagai DRAFT (' + res.mastercardCode + ', ' + res.knifeCode + ')'
                            + (res.hasInquiry ? ' — master + snapshot Inquiry, termasuk BOM/Ink/BOP.' : ' — master saja, job ini tidak punya Inquiry asal.')
                            + (res.specSkipped && res.specSkipped.length ? ' Tidak ditimpa: ' + res.specSkipped.join('; ') + '.' : ''),
                            res.specSkipped && res.specSkipped.length ? 'info' : 'ok');
                        return;
                    }

                    if (res.costingUrl) {
                        msg('Layout CONFIRMED (' + res.mastercardCode + ') — membuka Product Costing…', 'ok');
                        window.location.href = res.costingUrl;
                        return;
                    }

                    msg('Layout CONFIRMED (' + res.mastercardCode + '). Job ini Mode #2 (tanpa Inquiry), '
                        + 'jadi belum ada layar Product Costing yang bisa dituju.', 'ok');
                } catch (e) {
                    msg('Gagal menyimpan: ' + (e && e.message ? e.message : e), 'bad');
                } finally {
                    btn.disabled = false;
                    btn.textContent = label;
                }
            }

            // ===================== AUTO NESTING (interlock sadar-bentuk) =====================
            // Cara kerja (opsi B yang dipilih user 2026-09-16): dieline ASLI
            // (DSL dieline_spec, renderer TMDieline yang sama dgn canvas) di-
            // rasterisasi jadi peta piksel 1 px = 1 mm, lalu dua salinan didorong
            // saling mendekat sampai PERSIS bersentuhan -- meniru cara officer
            // pre-press mendorong dua blank sampai mentok. Jarak mentok dihitung
            // lewat profil tepi (skyline) per kolom/baris, jadi O(lebar) bukan
            // O(luas): eksak untuk translasi, dan cukup cepat buat dipanggil
            // berkali-kali saat menjajal banyak pola.
            //
            // Pola yang dijajal: grid biasa, selang-seling 180° antar BARIS, dan
            // selang-seling 180° antar KOLOM (ini yang menghasilkan susunan
            // berhadapan seperti contoh user), masing-masing dgn 4 nilai geseran
            // samping + 2 orientasi dasar (0°/90°). Yang menang = up terbanyak.
            //
            // Hasilnya SARAN, bukan paksaan (pola "engine suggests, officer
            // confirms") -- object tetap bisa digeser manual sesudahnya, dan
            // rotasi tiap object ikut tersimpan lewat Save/Confirm.
            const MASK_RES = 1;      // px per mm
            const MASK_MAX = 700;    // batas sisi bitmap, jaga-jaga blank raksasa
            const maskCache = new Map();

            function rasterize(svg, w, h) {
                return new Promise((resolve, reject) => {
                    const img = new Image();
                    const url = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml;charset=utf-8' }));
                    img.onload = () => {
                        const cv = document.createElement('canvas');
                        cv.width = w; cv.height = h;
                        const ctx = cv.getContext('2d', { willReadFrequently: true });
                        ctx.drawImage(img, 0, 0, w, h);
                        URL.revokeObjectURL(url);
                        resolve(ctx.getImageData(0, 0, w, h));
                    };
                    img.onerror = e => { URL.revokeObjectURL(url); reject(e); };
                    img.src = url;
                });
            }

            function solidMask(w, h) {
                return { w, h, data: new Uint8Array(w * h).fill(1), shaped: false };
            }

            // Garis dieline itu OUTLINE (stroke), bukan bidang terisi -- jadi
            // badan blank dicari dgn flood-fill dari pinggir: apa pun yang TIDAK
            // terjangkau dari luar = bagian dalam blank. Stroke ditebalkan dulu
            // supaya celah kecil antar segmen DSL tidak bikin isian bocor keluar.
            function maskFromPixels(px, w, h) {
                const ink = new Uint8Array(w * h);
                for (let i = 0; i < w * h; i++) ink[i] = px.data[i * 4 + 3] > 40 ? 1 : 0;

                const ext = new Uint8Array(w * h);
                const stack = [];
                const push = (x, y) => {
                    if (x < 0 || y < 0 || x >= w || y >= h) return;
                    const i = y * w + x;
                    if (ext[i] || ink[i]) return;
                    ext[i] = 1; stack.push(i);
                };
                for (let x = 0; x < w; x++) { push(x, 0); push(x, h - 1); }
                for (let y = 0; y < h; y++) { push(0, y); push(w - 1, y); }
                while (stack.length) {
                    const i = stack.pop(), x = i % w, y = (i - x) / w;
                    push(x + 1, y); push(x - 1, y); push(x, y + 1); push(x, y - 1);
                }

                const data = new Uint8Array(w * h);
                let filled = 0;
                for (let i = 0; i < w * h; i++) if (!ext[i]) { data[i] = 1; filled++; }

                // Isian terlalu tipis = outline bocor (DSL tidak tertutup rapat).
                // Jangan diam-diam pakai hasil salah -- jatuh balik ke kotak penuh.
                if (filled / (w * h) < 0.15) return null;

                return { w, h, data, shaped: true };
            }

            async function maskFor(job) {
                if (maskCache.has(job.id)) return maskCache.get(job.id);

                const scale = Math.min(1, MASK_MAX / Math.max(job.l * 10, job.w * 10));
                const w = Math.max(8, Math.round(job.l * 10 * MASK_RES * scale));
                const h = Math.max(8, Math.round(job.w * 10 * MASK_RES * scale));
                let mask = null;

                if (job.dieline && window.TMDieline) {
                    try {
                        // Elemen BLEED itu kotak penuh mengelilingi blank, BUKAN garis
                        // potong -- kalau ikut dirender, flood-fill langsung menganggap
                        // seluruh kotak sebagai badan dan bentuk aslinya hilang (jadi
                        // tidak pernah ada peluang interlock). Siluet hanya ditentukan
                        // garis CUT, jadi bleed dibuang dari salinan spec di sini.
                        const spec = Object.assign({}, job.dieline, {
                            elements: (job.dieline.elements || []).filter(e => e.t !== 'bleed' && e.k !== 'BLEED'),
                        });
                        let svg = window.TMDieline.svg(spec, job.dielineDims || {});

                        if (svg) {
                            // Buang margin bawaan viewBox TMDieline supaya bitmap 1:1
                            // dengan badan blank.
                            const vb = (svg.match(/viewBox="([^"]+)"/) || [])[1];
                            let dslW = w;
                            if (vb) {
                                const v = vb.trim().split(/\s+/).map(Number);
                                dslW = v[2] + 2 * v[0];
                                svg = svg.replace(/viewBox="[^"]+"/, 'viewBox="0 0 ' + dslW + ' ' + (v[3] + 2 * v[1]) + '"');
                            }
                            // Stroke dinaikkan mengikuti rasio penyusutan: DSL sering
                            // jauh lebih besar dari bitmap target, kalau stroke dibiarkan
                            // tipis garisnya putus-putus saat diperkecil -> flood-fill
                            // bocor keluar dan bentuknya batal terbaca.
                            const sw = Math.max(2.5, 2.5 * (dslW / Math.max(1, w)));
                            svg = svg
                                .replace(/stroke-width="[\d.]+"/g, 'stroke-width="' + sw.toFixed(2) + '"')
                                .replace(/width="100%" height="100%"/, 'width="' + w + '" height="' + h + '" preserveAspectRatio="none"');
                            mask = maskFromPixels(await rasterize(svg, w, h), w, h);
                        }
                    } catch (e) { mask = null; }
                }

                if (!mask) mask = solidMask(w, h);
                mask.mmPerPx = (job.l * 10) / mask.w;
                maskCache.set(job.id, mask);

                return mask;
            }

            function rotMask(m, deg) {
                if (!deg) return m;
                const w = m.w, h = m.h;
                const rw = deg === 180 ? w : h, rh = deg === 180 ? h : w;
                const out = new Uint8Array(rw * rh);
                for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) {
                    if (!m.data[y * w + x]) continue;
                    let nx, ny;
                    if (deg === 90) { nx = h - 1 - y; ny = x; }
                    else if (deg === 180) { nx = w - 1 - x; ny = h - 1 - y; }
                    else { nx = y; ny = w - 1 - x; }
                    out[ny * rw + nx] = 1;
                }
                return { w: rw, h: rh, data: out, shaped: m.shaped, mmPerPx: m.mmPerPx };
            }

            // Tabrakan diuji EKSAK, bukan lewat profil tepi (leftmost/rightmost
            // per baris). Profil hanya sah untuk bentuk cembung: pada blank
            // BERGERIGI -- justru bentuk yang bisa saling mengunci -- profil
            // membaca gigi terluar sebagai dinding penuh, sehingga menolak
            // susunan yang sebenarnya muat. Terbukti pada contoh user: dua blank
            // ini sesungguhnya bisa berjarak 8 mm (offset vertikal 30 mm), tapi
            // versi profil menuntut 71 mm dan hasilnya cuma 1 up.
            //
            // Supaya tetap murah, tiap baris mask dikemas jadi bit (32 px per
            // word), jadi satu uji tabrakan = beberapa operasi AND per baris.
            function packMask(m) {
                if (m._p) return m._p;
                const words = (m.w + 31) >> 5;
                const rows = new Uint32Array(m.h * words);
                for (let y = 0; y < m.h; y++)
                    for (let x = 0; x < m.w; x++)
                        if (m.data[y * m.w + x]) rows[y * words + (x >> 5)] |= (1 << (x & 31));
                return (m._p = { words, rows, w: m.w, h: m.h });
            }

            // Apakah A bertabrakan dengan B yang digeser dx ke kanan & sy ke bawah.
            function hits(A, B, dx, sy) {
                const PA = packMask(A), PB = packMask(B);
                const ws = PA.words, wShift = dx >> 5, bShift = dx & 31;

                for (let y = 0; y < PA.h; y++) {
                    const by = y - sy;
                    if (by < 0 || by >= PB.h) continue;
                    const aOff = y * ws, bOff = by * PB.words;
                    for (let i = 0; i < ws; i++) {
                        const j = i - wShift;
                        let bw = 0;
                        if (j >= 0 && j < PB.words) bw = PB.rows[bOff + j] << bShift;
                        if (bShift && j - 1 >= 0 && j - 1 < PB.words) bw |= PB.rows[bOff + j - 1] >>> (32 - bShift);
                        if (PA.rows[aOff + i] & bw) return true;
                    }
                }
                return false;
            }

            // Jarak horizontal minimum supaya B (pada offset vertikal sy) bebas dari A.
            function minDx(A, B, sy) {
                for (let dx = 0; dx <= A.w; dx++) if (!hits(A, B, dx, sy)) return Math.max(1, dx);
                return A.w;
            }

            // Jarak vertikal minimum supaya B (pada offset horizontal sx) bebas dari A.
            function minDy(A, B, sx) {
                for (let dy = 0; dy <= A.h; dy++) if (!hits(A, B, sx, dy)) return Math.max(1, dy);
                return A.h;
            }

            // Pitch tidak boleh dihitung dari SATU pasangan saja: di dalam kisi,
            // satu object juga bertetangga dgn object di atas/bawah kolom
            // sebelahnya (offset sx ± n×pitch). Kalau cuma offset utama yang
            // diperiksa, pasangan lain bisa saling tembus -- terbukti nyata saat
            // uji (27.662 piksel bertabrakan sebelum ini diperbaiki). Jadi ambil
            // jarak TERBESAR dari beberapa offset tetangga.
            // `span` = berapa object yang BENAR-BENAR ada sepanjang sumbu itu.
            // Penting: kalau canvas cuma muat 1 object per baris/kolom, tetangga
            // ke-2/ke-3 tidak pernah ada, jadi jaraknya tidak boleh ikut
            // membatasi. Versi sebelumnya memaksa n = -2..2 tanpa syarat ->
            // jarak jadi kebesaran dan susunan yang sebetulnya muat malah
            // ditolak (kasus nyata: canvas 9,6 x 8,4 cm cuma dapat 1 up padahal
            // 2 up muat -- ditemukan dari contoh susunan manual user).
            function needDy(A, B, sx, pitchX, span) {
                const lim = Math.min(2, Math.max(0, (span || 1) - 1));
                let need = 1;
                for (let n = -lim; n <= lim; n++) need = Math.max(need, minDy(A, B, sx + n * pitchX));
                return need;
            }

            function needDx(A, B, sy, pitchY, span) {
                const lim = Math.min(2, Math.max(0, (span || 1) - 1));
                let need = 1;
                for (let n = -lim; n <= lim; n++) need = Math.max(need, minDx(A, B, sy + n * pitchY));
                return need;
            }

            // Hitung piksel garis potong yang saling tembus. earlyExit dipakai saat
            // menyaring kandidat (cukup tahu ADA/TIDAK), tanpa earlyExit dipakai
            // untuk memberi tahu user seberapa parah tabrakan susunan manualnya.
            function overlapPixels(placements, Lmm, Wmm, earlyExit) {
                const grid = new Uint8Array(Lmm * Wmm);
                let n = 0;

                for (const p of placements) {
                    const m = p.m;
                    for (let y = 0; y < m.h; y++) {
                        const gy = p.y + y;
                        if (gy < 0 || gy >= Wmm) continue;
                        const row = y * m.w, grow = gy * Lmm;
                        for (let x = 0; x < m.w; x++) {
                            if (!m.data[row + x]) continue;
                            const gx = p.x + x;
                            if (gx < 0 || gx >= Lmm) continue;
                            if (grid[grow + gx]) { if (earlyExit) return 1; n++; }
                            else grid[grow + gx] = 1;
                        }
                    }
                }
                return n;
            }

            // Jaring pengaman: susunan yang masih saling tembus DIBUANG, tidak
            // pernah ditawarkan ke user.
            function collides(placements, Lmm, Wmm) {
                return overlapPixels(placements, Lmm, Wmm, true) > 0;
            }

            function mirrorMask(m, fx, fy) {
                if (fx > 0 && fy > 0) return m;
                const out = new Uint8Array(m.w * m.h);
                for (let y = 0; y < m.h; y++) for (let x = 0; x < m.w; x++) {
                    if (!m.data[y * m.w + x]) continue;
                    out[(fy < 0 ? m.h - 1 - y : y) * m.w + (fx < 0 ? m.w - 1 - x : x)] = 1;
                }
                return { w: m.w, h: m.h, data: out, shaped: m.shaped };
            }

            // Transform object di canvas: rotate(r) scale(fx fy) -- mirror berlaku
            // di koordinat lokal object, jadi dicerminkan dulu baru diputar.
            function variantFor(base, r, fx, fy) {
                const key = r + '|' + fx + '|' + fy;
                const cache = base._v || (base._v = {});
                return cache[key] || (cache[key] = rotMask(mirrorMask(base, fx, fy), r));
            }

            // Susunan manual TIDAK divalidasi apa pun sebelumnya: user bisa saja
            // menumpuk dua blank sampai garis potongnya saling makan, dan panel
            // yield tetap menampilkan angka yang kelihatan bagus (utilisasi bisa
            // >100% karena dihitung dari kotak pembatas, bukan bentuk asli). Di
            // sinilah susunan manual ikut diperiksa dengan alat yang sama seperti
            // auto nesting, supaya salah susun ketahuan langsung.
            async function checkManualOverlap() {
                if (!S.objs.length || !ok()) { S.overlapPx = 0; return; }

                try {
                    const base = await maskFor(S.p);
                    const sc = S.sc0 || 1;
                    const placements = [];

                    for (const o of S.objs) {
                        const r = ((Math.round(o.r) % 360) + 360) % 360;
                        if (r % 90) return;  // sudut bebas (tak mungkin dari tombol) -- lewati
                        const m = variantFor(base, r, o.fx, o.fy);
                        placements.push({
                            x: Math.round((o.x + o.w / 2 - S.sx0) / sc * 10 - m.w / 2),
                            y: Math.round((o.y + o.h / 2 - S.sy0) / sc * 10 - m.h / 2),
                            m,
                        });
                    }

                    // Diperiksa pada kotak pembatas SELURUH object, bukan pada area
                    // canvas: object yang melewati tepi canvas tetap saling menabrak,
                    // dan kalau gridnya sebatas canvas, tabrakan di luar tepi ikut
                    // terpotong dan lolos tanpa peringatan.
                    let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
                    for (const p of placements) {
                        minX = Math.min(minX, p.x); minY = Math.min(minY, p.y);
                        maxX = Math.max(maxX, p.x + p.m.w); maxY = Math.max(maxY, p.y + p.m.h);
                    }

                    const gw = maxX - minX, gh = maxY - minY;
                    if (gw <= 0 || gh <= 0 || gw * gh > 25e6) { S.overlapPx = 0; return; }

                    S.overlapPx = overlapPixels(
                        placements.map(p => ({ x: p.x - minX, y: p.y - minY, m: p.m })), gw, gh, false);
                    calculator();
                } catch (e) { S.overlapPx = 0; }
            }

            // Pitch tidak bergantung ukuran canvas -- cuma pada bentuk, geseran, gap,
            // dan berapa tetangga yang ada. Disimpan supaya penyapuan ribuan
            // kombinasi canvas x plano tidak menghitung ulang uji tabrakan yang sama.
            let maskSeq = 0;
            const pitchMemo = new Map();

            function maskId(m) {
                return m._id || (m._id = ++maskSeq);
            }

            function memoNeed(kind, A, B, off, pitch, span) {
                const lim = Math.min(3, Math.max(1, span || 1));
                const key = kind + '|' + maskId(A) + '|' + maskId(B) + '|' + off + '|' + pitch + '|' + lim;
                if (pitchMemo.has(key)) return pitchMemo.get(key);
                const val = kind === 'x' ? needDx(A, B, off, pitch, lim) : needDy(A, B, off, pitch, lim);
                pitchMemo.set(key, val);
                return val;
            }

            // collect=false -> hanya menghitung jumlah up (dipakai saat menyapu banyak
            // kombinasi); penempatan sesungguhnya baru dibangun untuk pemenangnya.
            function plainLattice(M, r, Lmm, Wmm, gap, label, collect) {
                const out = [];
                let n = 0;
                for (let y = 0; y + M.h <= Wmm + .001; y += M.h + gap)
                    for (let x = 0; x + M.w <= Lmm + .001; x += M.w + gap) {
                        n++;
                        if (collect !== false) out.push({ x: Math.round(x), y: Math.round(y), w: M.w, h: M.h, r, m: M });
                    }
                return { up: n, placements: out, label };
            }

            // Posisi tiap baris/kolom ditentukan BERTAHAP terhadap tetangga yang
            // benar-benar sudah ditempatkan (k-1 dan k-2), bukan lewat satu jarak
            // seragam + koreksi. Sebelumnya koreksi "baris k vs k+2" ditambahkan
            // tanpa syarat, padahal di canvas kecil baris ke-3 tidak pernah ada --
            // akibatnya susunan yang sebetulnya muat ikut tertolak (kasus nyata:
            // canvas 9,6 x 8,4 cm mentok 1 up padahal 2 up muat).
            function rowAltLattice(M0, M1, r, Lmm, Wmm, gap, sx, label, collect) {
                const pitchX = Math.max(M0.w, M1.w) + gap;
                const cols = Math.max(1, Math.floor((Lmm - Math.max(M0.w, M1.w)) / pitchX) + 1);
                const dAlt = [memoNeed('y', M0, M1, sx, pitchX, cols) + gap, memoNeed('y', M1, M0, -sx, pitchX, cols) + gap];
                const dSame = [memoNeed('y', M0, M0, 0, pitchX, cols) + gap, memoNeed('y', M1, M1, 0, pitchX, cols) + gap];
                const out = [], ys = [];
                let n = 0;

                for (let k = 0; k < 400; k++) {
                    let y = 0;
                    if (k) {
                        y = ys[k - 1] + dAlt[(k - 1) % 2];
                        if (k >= 2) y = Math.max(y, ys[k - 2] + dSame[k % 2]);
                    }
                    const odd = k % 2, M = odd ? M1 : M0, off = odd ? Math.max(0, sx) : 0;
                    if (y + M.h > Wmm + .001) break;
                    ys.push(y);
                    for (let x = off; x + M.w <= Lmm + .001; x += pitchX) {
                        n++;
                        if (collect !== false) out.push({ x: Math.round(x), y: Math.round(y), w: M.w, h: M.h, r: odd ? (r + 180) % 360 : r, m: M });
                    }
                }

                return { up: n, placements: out, label };
            }

            function colAltLattice(M0, M1, r, Lmm, Wmm, gap, sy, label, collect) {
                const pitchY = Math.max(M0.h, M1.h) + gap;
                const rows = Math.max(1, Math.floor((Wmm - Math.max(M0.h, M1.h)) / pitchY) + 1);
                const dAlt = [memoNeed('x', M0, M1, sy, pitchY, rows) + gap, memoNeed('x', M1, M0, -sy, pitchY, rows) + gap];
                const dSame = [memoNeed('x', M0, M0, 0, pitchY, rows) + gap, memoNeed('x', M1, M1, 0, pitchY, rows) + gap];
                const out = [], xs = [];
                let n = 0;

                for (let k = 0; k < 400; k++) {
                    let x = 0;
                    if (k) {
                        x = xs[k - 1] + dAlt[(k - 1) % 2];
                        if (k >= 2) x = Math.max(x, xs[k - 2] + dSame[k % 2]);
                    }
                    const odd = k % 2, M = odd ? M1 : M0, off = odd ? Math.max(0, sy) : 0;
                    if (x + M.w > Lmm + .001) break;
                    xs.push(x);
                    for (let y = off; y + M.h <= Wmm + .001; y += pitchY) {
                        n++;
                        if (collect !== false) out.push({ x: Math.round(x), y: Math.round(y), w: M.w, h: M.h, r: odd ? (r + 180) % 360 : r, m: M });
                    }
                }

                return { up: n, placements: out, label };
            }

            // Daftar pola + geseran yang dijajal, dihitung sekali per bentuk blank.
            function patternConfigs(base) {
                const cfgs = [];

                for (const b of [0, 90]) {
                    const M0 = rotMask(base, b), M1 = rotMask(base, (b + 180) % 360);
                    const tag = b ? ' · rotate 90°' : '';
                    cfgs.push({ kind: 'plain', M0, M1, r: b, s: 0, label: 'Grid standar' + tag });

                    const stepX = Math.max(1, Math.round(M0.w / 16)), stepY = Math.max(1, Math.round(M0.h / 16));
                    for (let sx = 0; sx < M0.w; sx += stepX)
                        cfgs.push({ kind: 'row', M0, M1, r: b, s: sx, label: 'Interlock antar-baris (180°)' + (sx ? ' geser ' + Math.round(sx / M0.w * 100) + '%' : '') + tag });
                    for (let sy = 0; sy < M0.h; sy += stepY)
                        cfgs.push({ kind: 'col', M0, M1, r: b, s: sy, label: 'Interlock antar-kolom (180°)' + (sy ? ' geser ' + Math.round(sy / M0.h * 100) + '%' : '') + tag });
                }

                return cfgs;
            }

            function buildLattice(cfg, Lmm, Wmm, gap, collect) {
                if (cfg.kind === 'plain') return plainLattice(cfg.M0, cfg.r, Lmm, Wmm, gap, cfg.label, collect);
                if (cfg.kind === 'row') return rowAltLattice(cfg.M0, cfg.M1, cfg.r, Lmm, Wmm, gap, cfg.s, cfg.label, collect);
                return colAltLattice(cfg.M0, cfg.M1, cfg.r, Lmm, Wmm, gap, cfg.s, cfg.label, collect);
            }

            // Pola terbaik untuk SATU ukuran canvas. Saat menyapu (collect=false)
            // tabrakan belum diuji demi kecepatan -- pemenangnya diuji belakangan.
            function bestLattice(cfgs, Lmm, Wmm, gap, collect) {
                let best = null, plainBest = 0, ditolak = 0;

                for (const cfg of cfgs) {
                    const c = buildLattice(cfg, Lmm, Wmm, gap, collect);
                    if (!c.up) continue;
                    if (collect !== false && collides(c.placements, Lmm, Wmm)) { ditolak++; continue; }
                    if (cfg.kind === 'plain' && c.up > plainBest) plainBest = c.up;
                    if (!best || c.up > best.up) best = c;
                }

                return best ? Object.assign(best, { plainBest, ditolak }) : null;
            }

            function applyPlacements(placements, label, offX, offY) {
                noteShortfall(Math.min(placements.length, S.auto ? placements.length : S.manualUp));
                if (!S.auto && S.manualUp < placements.length) placements = placements.slice(0, S.manualUp);

                const dx = offX || 0, dy = offY || 0;
                const sc = S.sc0, ow = S.p.l * sc, oh = S.p.w * sc;
                S.objs = placements.map((p, i) => ({
                    id: 'a' + i,
                    x: S.sx0 + (p.x + dx + p.w / 2) / 10 * sc - ow / 2,
                    y: S.sy0 + (p.y + dy + p.h / 2) / 10 * sc - oh / 2,
                    w: ow, h: oh, r: p.r, fx: 1, fy: 1,
                }));
                S.selected = null;
                S.touched = true;
                S.manualEdit = false;
                S.autoNestInfo = label;
                S.overlapPx = 0;   // sudah lolos verifikasi collides()
                // Mode Manual Up: kekurangan object dilengkapi SEBELUM digambar,
                // kalau sesudah render object tambahannya tidak ikut tampil.
                ensureManualCount();
                render(); calculator();
            }

            // Optimasi MENYELURUH: canvas & plano ikut dipilih engine (disetujui user
            // 2026-09-16). Ukuran canvas dicoba dari pembagian plano jadi nc x nr
            // bagian sama besar -- itu cara potong guillotine yang lazim dan tidak
            // menyisakan trim. Yang diperbandingkan BUKAN up per canvas (itu memihak
            // canvas besar) melainkan LUAS BAHAN PER PCS: plano besar otomatis
            // menghasilkan lebih banyak pcs tapi juga memakan bahan lebih banyak.
            // Sejalan dengan proxy biaya yang sudah dipakai engine lama
            // (ImpositionEngine::bestSheet: luas_plano / up, karena mat_plano_size
            // memang tidak menyimpan harga).
            async function optimizeSheet() {
                const btn = $('suggest'), label = btn.textContent;
                btn.disabled = true; btn.textContent = 'Menghitung…';

                try {
                    const base = await maskFor(S.p);
                    const cfgs = patternConfigs(base);
                    const gap = Math.max(0, Math.round(S.g * 10));
                    const [limL, limW] = bedLimits();
                    const bedL = limL * 10, bedW = limW * 10;
                    // Area yang benar-benar bisa dicetak = canvas dikurangi gripper
                    // (1 sisi jepitan) dan margin keliling. Tanpa ini optimizer
                    // sempat memilih canvas 54,5 x 6 cm -- strip setinggi 6 cm yang
                    // mustahil dijalankan di mesin offset.
                    const grip = Math.round(GRIPPER * 10), marg = Math.round(MARGIN * 10);
                    const usable = (l, w) => [l - 2 * marg, w - grip - marg];
                    const upCache = new Map();
                    let best = null;

                    for (let pi = 0; pi < planos.length; pi++) {
                        const P = planos[pi];
                        const area = P[1] * P[2];   // cm2

                        for (const [pl, pw] of [[P[1] * 10, P[2] * 10], [P[2] * 10, P[1] * 10]]) {
                            for (let nc = 1; nc <= 14; nc++) {
                                const cl = Math.floor(pl / nc);
                                if (cl > bedL || cl < 10) continue;

                                for (let nr = 1; nr <= 14; nr++) {
                                    const cw = Math.floor(pw / nr);
                                    if (cw > bedW || cw < 10) continue;

                                    const [ul, uw] = usable(cl, cw);
                                    if (ul <= 0 || uw <= 0) continue;

                                    // Banyak kombinasi (plano, nc, nr) menghasilkan ukuran
                                    // canvas yang sama persis -- hasil nestingnya pun sama,
                                    // jadi cukup dihitung sekali.
                                    const key = ul + 'x' + uw;
                                    let cand = upCache.get(key);
                                    if (cand === undefined) {
                                        cand = bestLattice(cfgs, ul, uw, gap, false);
                                        upCache.set(key, cand);
                                    }
                                    if (!cand) continue;

                                    const pcs = nc * nr * cand.up;
                                    const perPc = area / pcs;   // cm2 bahan per pcs -- makin kecil makin hemat
                                    if (!best || perPc < best.perPc - 1e-9) {
                                        best = { perPc, pcs, up: cand.up, planoIndex: pi, L: cl / 10, W: cw / 10, nc, nr, label: cand.label };
                                    }
                                }
                            }
                        }
                    }

                    if (!best) { msg('Tidak ada kombinasi canvas/plano yang muat di mesin ini.', 'bad'); return; }

                    const sebelum = hasilPerPlano();

                    S.pl = planos[best.planoIndex];
                    $('plano').value = String(best.planoIndex);
                    S.L = +best.L.toFixed(1); S.W = +best.W.toFixed(1);
                    $('sl').value = S.L; $('sw').value = S.W;
                    S.objs = [];
                    update(true);

                    // Pemenang baru dibangun penempatannya + diuji tabrakan di sini,
                    // lalu digeser masuk ke area cetak (gripper + margin).
                    const [ul, uw] = usable(Math.round(S.L * 10), Math.round(S.W * 10));
                    const win = bestLattice(cfgs, ul, uw, gap, true);
                    if (win) applyPlacements(win.placements, win.label, marg, grip);

                    const sesudah = hasilPerPlano();
                    $('suggestNote').textContent = 'Canvas ' + S.L + ' × ' + S.W + ' cm (plano dibagi ' + best.nc + ' × ' + best.nr + ')'
                        + ' · plano ' + S.pl[0] + ' · ' + (win ? win.up : best.up) + ' up/canvas.';
                    msg('Optimasi bahan: ' + S.pl[0] + ', canvas ' + S.L + ' × ' + S.W + ' cm, ' + (win ? win.label : best.label)
                        + ' → ' + sesudah + ' pcs/plano (' + best.perPc.toFixed(1) + ' cm² bahan per pcs)'
                        + (sebelum && sesudah > sebelum ? ' · sebelumnya ' + sebelum + ' pcs/plano' : ''), 'ok');
                } catch (e) {
                    msg('Optimasi gagal: ' + (e && e.message ? e.message : e), 'bad');
                } finally {
                    btn.disabled = false; btn.textContent = label;
                }
            }

            // Plano terbaik untuk canvas SAAT INI. Ukuran canvas sudah tetap, jadi
            // "bahan per pcs paling kecil" (aturan optimizeSheet, disetujui user
            // 2026-09-16) sama dengan sheet yield tertinggi; seri -> canvas lebih banyak.
            function bestPlanoIndex() {
                let bi = -1, bc = null;
                planos.forEach((q, i) => {
                    const c = cut(q);
                    if (c.n && (!bc || c.util > bc.util + 1e-9 || (Math.abs(c.util - bc.util) <= 1e-9 && c.n > bc.n))) { bi = i; bc = c; }
                });
                return bi;
            }

            // Pilih plano paling hemat untuk canvas sekarang. Hasilnya selalu
            // dilaporkan DIGANTI atau TETAP -- kalau plano lama sudah yang paling
            // hemat, dropdown memang tidak berubah dan officer mengira tidak jalan.
            function pickBestPlano() {
                const lama = S.pl[0], i = bestPlanoIndex();
                if (i < 0) return 'tidak ada plano standar yang muat canvas ' + S.L + ' × ' + S.W + ' cm';
                S.pl = planos[i]; $('plano').value = String(i);
                return lama === S.pl[0] ? 'plano tetap ' + S.pl[0] + ' (sudah paling hemat)' : 'plano diganti ' + lama + ' → ' + S.pl[0];
            }

            function hasilPerPlano() {
                if (!ok()) return 0;
                const c = cut(S.pl);
                return c.n * upValid();
            }

            // Susunan rapat dijalankan OTOMATIS saat job/mesin dibuka, bukan cuma
            // kalau tombolnya ditekan (koreksi user 2026-09-16: yang muncul pertama
            // masih grid renggang 9 up, padahal engine sanggup lebih rapat). Grid
            // polos tidak ada gunanya ditampilkan lebih dulu -- itu justru hasil
            // terburuk. Tidak dijalankan kalau user sudah menyusun manual, supaya
            // kerjaan tangannya tidak ditimpa diam-diam.
            let autoNestTimer = null;
            function autoNestSoon() {
                clearTimeout(autoNestTimer);
                autoNestTimer = setTimeout(() => {
                    if (!S.touched && ok()) autoNest(true).then(samakanTinggiAcc);
                }, 120);
            }

            async function autoNest(silent) {
                if (!ok()) {
                    if (!silent) msg('Canvas masih di luar batas mesin — perbaiki dulu sebelum auto nesting.', 'bad');
                    return;
                }

                const btn = $('autoNest'), label = btn.textContent;
                btn.disabled = true; btn.textContent = 'Menghitung…';

                try {
                    const base = await maskFor(S.p);
                    const gap = Math.max(0, Math.round(S.g * 10));
                    const best = bestLattice(patternConfigs(base), Math.round(S.L * 10), Math.round(S.W * 10), gap, true);

                    if (!best) {
                        if (!silent) msg('Auto nesting tidak menemukan susunan yang muat di canvas ini.', 'bad');
                        return;
                    }

                    applyPlacements(best.placements, best.label);

                    const gain = best.plainBest ? Math.round((best.up - best.plainBest) / best.plainBest * 100) : 0;
                    msg('Auto nesting: ' + best.label + ' → ' + best.up + ' up'
                        + (gain > 0 ? ' (+' + gain + '% vs grid standar, ' + (best.up - best.plainBest) + ' up lebih banyak)'
                            : ' (grid standar sudah paling padat untuk bentuk ini)')
                        + (best.ditolak ? ' · ' + best.ditolak + ' pola ditolak krn masih bertabrakan' : '')
                        + (base.shaped ? '' : ' · tipe ini belum punya dieline, dihitung sebagai kotak polos'), 'ok');
                } catch (e) {
                    if (!silent) msg('Auto nesting gagal: ' + (e && e.message ? e.message : e), 'bad');
                } finally {
                    btn.disabled = false; btn.textContent = label;
                }
            }

            function init() {
                products.forEach((x, i) => $('product').add(new Option(x.name, i)));
                machines.forEach((x, i) => $('machine').add(new Option(x[0], i)));
                diecutters.forEach((x, i) => $('diecut').add(new Option(x[0], i)));
                planos.forEach((x, i) => $('plano').add(new Option(x[0], i)));
                $('product').value = String(products.indexOf(S.p));
                $('sl').value = S.L; $('sw').value = S.W; $('gap').value = S.g; $('grain').value = S.p.grain;
                $('back').href = DATA.backUrl || '#';
                $('back').setAttribute('data-back', '');
                registerDirtyGuard();
                askSidebarMini(true);
                accInit();
                fitViewBox();
                jobIdentity();
                if (S.p.orderQty > 0) $('converterInput').value = S.p.orderQty;
                bindSpecEvents();
                renderSpecSections();
                bind();
                upModeUi();
                artworkLabel();
                syncActiveJob();
                update(true);
                samakanTinggiAcc();
                autoNestSoon();
                analyzeArtwork();
            }

            // Minta shell mengecilkan sidebar selama layar ini terbuka (permintaan
            // user 2026-09-17) -- area gambar butuh lebar sebanyak mungkin. Nilai
            // sidebar sebelumnya disimpan shell dan dikembalikan saat halaman ini
            // ditinggalkan, jadi preferensi user tidak ikut hilang.
            function askSidebarMini(mini) {
                if (window.parent === window) return;   // dibuka langsung, bukan di shell
                try { window.parent.postMessage({ tmerp: 'sidebar', mini: !!mini }, window.location.origin); } catch (e) { /* abaikan */ }
            }

            window.addEventListener('pagehide', () => askSidebarMini(false));

            // Bentuk wadah berubah (sidebar mengecil, jendela di-resize) -> lebar
            // viewBox ikut disesuaikan supaya gambar tetap memakai ruang yang ada.
            let resizeTimer = null;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => { samakanTinggiAcc(); if (fitViewBox()) render(), calculator(); }, 150);
            });

            // Accordion: satu bagian terbuka. Bagian terakhir yang dibuka diingat per
            // browser (bukan data bersama, jadi cukup localStorage).
            function accInit() {
                const secs = [...document.querySelectorAll('.io-sec')];
                let awal = 'impo';
                try { awal = localStorage.getItem('prodev.acc') || 'impo'; } catch (e) { /* abaikan */ }
                if (!secs.some(s => s.dataset.sec === awal)) awal = 'impo';

                const buka = key => {
                    secs.forEach(s => {
                        const on = s.dataset.sec === key;
                        s.classList.toggle('open', on);
                        s.querySelector('.io-sec-head').setAttribute('aria-expanded', on ? 'true' : 'false');
                    });
                    try { localStorage.setItem('prodev.acc', key); } catch (e) { /* abaikan */ }
                    // Tinggi baris accordion bisa berubah -> area gambar ikut menyesuaikan.
                    setTimeout(() => { if (fitViewBox()) { render(); calculator(); } }, 60);
                };

                secs.forEach(s => { s.querySelector('.io-sec-head').onclick = () => buka(s.dataset.sec); });
                buka(awal);
            }

            // Tinggi accordion DIKUNCI = bagian tertinggi (user 2026-09-17: tidak
            // mau naik-turun tiap ganti bagian). Tiap bagian dibuka sesaat HANYA
            // untuk diukur -- semua sinkron sebelum browser menggambar, jadi tidak
            // berkedip. Dilewati saat layar sempit (accordion tersusun ke bawah).
            function samakanTinggiAcc() {
                const acc = document.getElementById('io-acc');
                const secs = [...acc.querySelectorAll('.io-sec')];
                acc.style.minHeight = '';
                if (window.matchMedia('(max-width: 900px)').matches) return;

                const aktif = secs.find(s => s.classList.contains('open'));
                let maks = 0;
                secs.forEach(s => {
                    secs.forEach(x => x.classList.toggle('open', x === s));
                    maks = Math.max(maks, acc.offsetHeight);
                });
                secs.forEach(x => x.classList.toggle('open', x === aktif));
                acc.style.minHeight = maks + 'px';
            }

            // ===================== BOM / INK / BOP (engine, BACKLOG §18) =====================
            // Ketiganya HASIL ENGINE (App\Services\ProDev\SpecEngine) dari input
            // Inquiry + keputusan layout di layar ini, dihitung ulang tiap kali
            // lembar/plano/mesin/up/qty berubah. Officer boleh meng-override tiap
            // section (tombol Edit, pakai dropdown); override tersimpan 2 arah saat
            // Save dan tidak ditimpa engine sampai dipilih "Kembali ke hasil engine".
            const LK = DATA.lookups || { processes: [], machines: [], subcons: [], uoms: [], roles: [], inks: [] };
            const SECS = ['bop', 'bom', 'inks'];
            let specTimer = null, specKey = '', specSeq = 0;

            function specInit(p) {
                if (p.edit) return;
                p.edit = {}; p.editSrc = {}; p.editing = {}; p.reset = []; p.flash = {}; p.subconAt = null;
                SECS.forEach(k => {
                    const sv = p.saved && p.saved[k];
                    p.edit[k] = sv ? sv.rows.map(r => ({ ...r })) : null;
                    p.editSrc[k] = sv ? sv.source : null;
                });
            }

            function overridesOf(p) {
                const o = {};
                SECS.forEach(k => { if (p.edit && p.edit[k]) o[k] = p.edit[k]; });
                return o;
            }

            function specCtx() {
                const c = cut(S.pl);
                return {
                    sheetL: S.L, sheetW: S.W, planoL: S.pl[1], planoW: S.pl[2], planoName: S.pl[0], sheetsPerPlano: c.n || 1,
                    printMachineId: S.m[4] || null, diecutMachineId: S.d ? (S.d[3] || null) : null,
                    upCount: upValid(),
                    orderQty: S.conv === 'pcs' ? (+$('converterInput').value || 0) : (S.p.orderQty || 0),
                    artworkColors: S.p.artColors || null,
                };
            }

            function specSoon() {
                if (!S.p.partId) return;
                specInit(S.p);
                const ctx = specCtx(), ov = overridesOf(S.p);
                const key = S.p.partId + '|' + JSON.stringify(ctx) + '|' + JSON.stringify(ov);
                if (key === specKey) return;
                clearTimeout(specTimer);
                specTimer = setTimeout(() => refreshSpec(ctx, ov, key), 450);
            }

            async function refreshSpec(ctx, ov, key) {
                const w = wire();
                if (!w) return;
                const my = ++specSeq, job = S.p;
                try {
                    const res = await w.call('generateSpec', job.partId, ctx, ov);
                    if (my !== specSeq || S.p !== job || !res || !res.ok) return;
                    specKey = key;
                    job.spec = res;
                    renderSpecSections();
                } catch (e) { /* tampilan terakhir dibiarkan */ }
            }

            // ---- Analisa warna artwork: gambar yang SAMA dengan canvas (file upload
            // atau artwork vector sampel) dirasterisasi, lalu tiap piksel diurai ke
            // C/M/Y/K (tutupan tinta) dan dikelompokkan per nama warna (luas warna).
            // Persen dihitung terhadap SELURUH bidang blank, jadi server tinggal
            // mengalikan dengan luas blank untuk dapat cm².
            const ANALYSIS_PX = 360;

            function artSource(p) {
                if (p.artworkUrl) return 'file:' + p.artworkUrl;
                if (p.type) return 'sample:' + p.type + ':' + p.l + 'x' + p.w;
                return null;
            }

            function loadArtPixels(p) {
                return new Promise((resolve, reject) => {
                    const k = ANALYSIS_PX / Math.max(p.l, p.w);
                    const W = Math.max(1, Math.round(p.l * k)), H = Math.max(1, Math.round(p.w * k));
                    const img = new Image();
                    let url = null;
                    img.onload = () => {
                        const cv = document.createElement('canvas');
                        cv.width = W; cv.height = H;
                        const cx = cv.getContext('2d', { willReadFrequently: true });
                        cx.drawImage(img, 0, 0, W, H);
                        if (url) URL.revokeObjectURL(url);
                        try { resolve(cx.getImageData(0, 0, W, H)); } catch (e) { reject(e); }
                    };
                    img.onerror = () => { if (url) URL.revokeObjectURL(url); reject(new Error('artwork tidak bisa dibaca')); };
                    if (p.artworkUrl) { img.src = p.artworkUrl; return; }
                    // Garis potong/lipat bukan tinta -- disembunyikan saat dianalisa.
                    const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' + W + '" height="' + H + '" viewBox="0 0 ' + W + ' ' + H + '">'
                        + '<style>.io-die,.io-fold,.io-cutmark{display:none}.io-artfill{fill:#fff}'
                        + '.io-lbl{font:700 ' + Math.max(6, W * .028).toFixed(1) + 'px Inter,Arial,sans-serif}</style>'
                        + shape({ w: W, h: H }) + '</svg>';
                    url = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml;charset=utf-8' }));
                    img.src = url;
                });
            }

            function colorName(r, g, b) {
                const mx = Math.max(r, g, b), mn = Math.min(r, g, b), l = (mx + mn) / 2, d = mx - mn;
                const s = d === 0 ? 0 : d / (1 - Math.abs(2 * l - 1));
                // Kroma (d), bukan saturasi HSL, untuk putih/abu: saturasi HSL
                // membesar di warna sangat terang, jadi #fafbff terbaca "biru".
                if (l > .86 && d < .12) return 'Putih';
                if (l < .13) return 'Hitam';
                if (d < .08 || s < .12) return 'Abu-abu';
                let hue = mx === r ? ((g - b) / d) % 6 : mx === g ? (b - r) / d + 2 : (r - g) / d + 4;
                hue = (hue * 60 + 360) % 360;
                if (hue >= 30 && hue < 55 && s < .8 && l < .62) return 'Emas';
                if (hue < 15 || hue >= 345) return l < .3 ? 'Merah Tua' : 'Merah';
                if (hue < 40) return l < .4 ? 'Coklat' : 'Oranye';
                if (hue < 70) return 'Kuning';
                if (hue < 165) return 'Hijau';
                if (hue < 200) return 'Tosca';
                if (hue < 255) return l < .3 ? 'Biru Tua' : 'Biru';
                if (hue < 290) return 'Ungu';
                return 'Pink';
            }

            function analyzePixels(img) {
                const d = img.data, total = img.width * img.height, ch = { C: 0, M: 0, Y: 0, K: 0 }, groups = {};
                for (let i = 0; i < d.length; i += 4) {
                    const a = d[i + 3] / 255;
                    if (a < .04) continue;
                    // Semi-transparan ditimpa di atas kertas putih.
                    const r = (d[i] * a + 255 * (1 - a)) / 255, g = (d[i + 1] * a + 255 * (1 - a)) / 255, b = (d[i + 2] * a + 255 * (1 - a)) / 255;
                    const k = 1 - Math.max(r, g, b);
                    if (k < 1) {
                        ch.C += (1 - r - k) / (1 - k);
                        ch.M += (1 - g - k) / (1 - k);
                        ch.Y += (1 - b - k) / (1 - k);
                    }
                    ch.K += k;
                    const n = colorName(r, g, b), grp = groups[n] || (groups[n] = { n: 0, r: 0, g: 0, b: 0 });
                    grp.n++; grp.r += r; grp.g += g; grp.b += b;
                }
                const hx = v => Math.round(v * 255).toString(16).padStart(2, '0');
                return {
                    channels: Object.fromEntries(Object.entries(ch).map(([k, v]) => [k, +(v / total * 100).toFixed(2)])),
                    colors: Object.entries(groups)
                        .map(([name, g]) => ({ name, hex: '#' + hx(g.r / g.n) + hx(g.g / g.n) + hx(g.b / g.n), pct: +(g.n / total * 100).toFixed(2) }))
                        .filter(c => c.pct >= .3)
                        .sort((a, b) => b.pct - a.pct)
                        .slice(0, 12),
                };
            }

            async function analyzeArtwork(force) {
                const p = S.p, src = artSource(p);
                if (!src || !p.partId) { renderSpecSections(); return; }
                if (!force && p.artColors && (p.artColors.source === src || String(p.artColors.source || '').startsWith('server:'))) return;
                p.artBusy = true;
                renderSpecSections();
                try {
                    const res = analyzePixels(await loadArtPixels(p));
                    res.source = src;
                    const w = wire();
                    p.artColors = w ? await w.call('saveArtworkColors', p.partId, res) : res;
                    p.artError = null;
                } catch (e) {
                    p.artError = e && e.message ? e.message : String(e);
                }
                p.artBusy = false;
                specKey = '';
                specSoon();
                renderSpecSections();
            }

            // ---- Tampilan & edit
            const fmtAngka = v => (v === null || v === undefined || v === '') ? '—'
                : (+parseFloat(v).toFixed(4)).toLocaleString('id-ID', { maximumFractionDigits: 4 });
            const esc = v => String(v ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
            const notes = list => list && list.length
                ? '<ul class="io-warns">' + list.map(x => '<li>' + esc(x) + '</li>').join('') + '</ul>' : '';
            const opts = (list, val, blank) => (blank !== undefined ? '<option value="">' + esc(blank) + '</option>' : '')
                + list.map(o => '<option value="' + esc(o.id) + '"' + (String(o.id) === String(val ?? '') ? ' selected' : '') + '>' + esc(o.name) + '</option>').join('');
            const optsTxt = (list, val) => {
                const all = val && !list.includes(val) ? [val, ...list] : list;
                return all.map(o => '<option' + (o === val ? ' selected' : '') + '>' + esc(o) + '</option>').join('');
            };
            const field = (sec, i, f, val, attrs) => ' data-sec="' + sec + '" data-i="' + i + '" data-f="' + f + '" ' + (attrs || '') + ' value="' + esc(val ?? '') + '"';
            const STATUS_CLS = { 'CUKUP': 'ok', 'KURANG': 'warn', 'TIDAK ADA': 'bad', 'BELUM ADA ITEM': 'bad', 'CEK SATUAN': 'mute' };

            function rowsOf(p, k) {
                const sp = p.spec;
                if (!sp) return [];
                // Override = salinan hasil engine + perubahan officer. Selama hitungan
                // override belum kembali dari server, baris engine tetap ditampilkan.
                return (p.edit[k] && sp.override[k]) || sp[k] || [];
            }

            function tools(p, k) {
                const src = p.edit[k]
                    ? (p.editSrc[k] === 'MANUAL' && !p.edit[k].__touched ? '<span class="io-src warn">DATA MANUAL TERSIMPAN</span>' : '<span class="io-src warn">OVERRIDE OFFICER</span>')
                    : '<span class="io-src">HASIL ENGINE</span>';
                const fl = p.flash[k];
                return '<div class="io-sectools">' + src
                    + '<span class="io-note-s">tersimpan ke master &amp; Inquiry saat Save</span>'
                    + '<span class="io-grow"></span>'
                    + (p.edit[k] ? '<button type="button" class="io-mbtn" data-act="reset" data-sec="' + k + '">↺ Kembali ke hasil engine</button>' : '')
                    + '<button type="button" class="io-mbtn ' + (p.editing[k] ? 'on' : '') + '" data-act="edit" data-sec="' + k + '">'
                    + (p.editing[k] ? '✓ Selesai edit' : '✎ Edit') + '</button></div>'
                    + (fl ? '<div class="io-notice ' + (fl.ok ? 'ok' : 'bad') + '">' + esc(fl.text)
                        + (fl.url ? ' <a href="' + esc(fl.url) + '" target="_blank" rel="noopener">Buka dokumen ↗</a>' : '') + '</div>' : '');
            }

            function bopHtml(p, sp) {
                const rows = rowsOf(p, 'bop'), ed = p.editing.bop, raw = p.edit.bop || [];
                const qty = (r, v) => v === null || v === undefined ? '—' : fmtAngka(v) + ' ' + esc(r.uom || '');
                let h = tools(p, 'bop')
                    + '<div class="io-inputs"><b>Finishing dari Inquiry:</b> '
                    + (sp.inputs.finishings.length ? sp.inputs.finishings.map(f => '<span class="io-chip">' + esc(f) + '</span>').join(' ') : '—')
                    + ' <b style="margin-left:10px">Qty order:</b> ' + (sp.inputs.orderQty ? fmtAngka(sp.inputs.orderQty) + ' pcs' : '—')
                    + ' <b style="margin-left:10px">Isi / pack:</b> ' + esc(sp.inputs.packQty || '—') + '</div>';

                if (ed) {
                    h += '<div class="io-tblwrap"><table class="io-tbl io-edit"><tr><th>#</th><th>Proses</th><th>Label di routing</th><th>Mesin</th>'
                        + '<th>Target / jam</th><th>Waste %</th><th>Toleransi %</th><th>Subkon ke</th><th></th></tr>'
                        + raw.map((r, i) => '<tr><td>' + (i + 1) + '</td>'
                            + '<td><select' + field('bop', i, 'process_id', '') + '>' + opts(LK.processes, r.process_id, '— proses —') + '</select></td>'
                            + '<td><input' + field('bop', i, 'label', r.label) + '></td>'
                            + '<td><select' + field('bop', i, 'machine_id', '') + '>' + opts(LK.machines, r.machine_id, '— tanpa mesin —') + '</select></td>'
                            + '<td><input type="number" min="0" step="1"' + field('bop', i, 'target_qty_per_hour', r.target_qty_per_hour) + '></td>'
                            + '<td><input type="number" min="0" max="100" step="0.1"' + field('bop', i, 'waste_pct', r.waste_pct) + '></td>'
                            + '<td><input type="number" min="0" max="100" step="0.1"' + field('bop', i, 'tolerance_pct', r.tolerance_pct) + '></td>'
                            + '<td><select' + field('bop', i, 'subcon_supplier_id', '') + '>' + opts(LK.subcons, r.subcon_supplier_id, '— in-house —') + '</select></td>'
                            + '<td class="io-nowrap"><button type="button" class="io-ibtn" data-act="up" data-sec="bop" data-i="' + i + '" title="Naik">▲</button>'
                            + '<button type="button" class="io-ibtn" data-act="down" data-sec="bop" data-i="' + i + '" title="Turun">▼</button>'
                            + '<button type="button" class="io-ibtn bad" data-act="del" data-sec="bop" data-i="' + i + '" title="Hapus">✕</button></td></tr>').join('')
                        + '</table></div><button type="button" class="io-mbtn" data-act="add" data-sec="bop">＋ Tambah langkah</button>';
                } else if (rows.length) {
                    h += '<div class="io-tblwrap"><table class="io-tbl"><tr><th>#</th><th>Urutan Pekerjaan</th><th>Mesin</th><th>Target default</th>'
                        + '<th>Qty masuk</th><th>Waste</th><th>Toleransi</th><th>Est. jam</th><th>Subkon</th></tr>'
                        + rows.map((r, i) => '<tr><td>' + (i + 1) + '</td><td><b>' + esc(r.label) + '</b>'
                            + (r.note ? '<div class="io-sub">' + esc(r.note) + '</div>' : '') + '</td>'
                            + '<td>' + esc(r.machine || '—') + '</td>'
                            + '<td>' + (r.target_qty_per_hour ? fmtAngka(r.target_qty_per_hour) + ' ' + esc(r.uom || '') + '/jam'
                                + '<div class="io-sub">' + esc(r.target_source || '') + '</div>' : '<span class="io-sub">belum ada</span>') + '</td>'
                            + '<td>' + qty(r, r.qty_in) + '</td>'
                            + '<td>' + fmtAngka(r.waste_pct ?? 0) + '%<div class="io-sub">' + qty(r, r.qty_waste) + '</div></td>'
                            + '<td>' + fmtAngka(r.tolerance_pct ?? 0) + '%<div class="io-sub">' + qty(r, r.qty_tolerance) + '</div></td>'
                            + '<td>' + (r.est_hours ?? '—') + '</td>'
                            + '<td>' + ((!r.inhouse || r.subcon_supplier_id)
                                ? (r.subcon_supplier ? '<div class="io-sub">' + esc(r.subcon_supplier) + '</div>' : '')
                                  + (r.subcon_pr ? '<div class="io-sub">' + esc(r.subcon_pr) + '</div>' : '')
                                  + (r.subcon_do ? '<div class="io-sub">' + esc(r.subcon_do) + '</div>' : '')
                                  + '<button type="button" class="io-mbtn warn" data-act="subcon" data-i="' + i + '">⇄ Subkon</button>'
                                : '<span class="io-sub">in-house</span>') + '</td></tr>').join('')
                        + '</table></div>';
                } else {
                    h += '<div class="io-empty">Tidak ada langkah proses.</div>';
                }

                if (!ed && p.subconAt !== null && rows[p.subconAt]) h += subconDialog(p, rows[p.subconAt]);

                return h + notes(sp.warnings.bop);
            }

            function subconDialog(p, r) {
                const co = p.coOptions || [];
                return '<div class="io-dialog"><b>Subkon: ' + esc(r.label) + '</b>'
                    + '<div class="io-sub">Qty dikirim: ' + fmtAngka(r.qty_in) + ' ' + esc(r.uom || '') + ' · PR subkon dibuat DRAFT (lewat approval PR).</div>'
                    + '<div class="io-row3">'
                    + '<label>Supplier subkon<select id="io-sc-sup">' + opts(LK.subcons, r.subcon_supplier_id, '— pilih supplier —') + '</select></label>'
                    + '<label>CO (wajib untuk surat jalan)<select id="io-sc-co">' + opts(co.map(c => ({ id: c.id, name: c.label })), '', co.length ? '— pilih CO —' : '— belum ada CO untuk Inquiry ini —') + '</select></label>'
                    + '<label>Tanggal kirim<input type="date" id="io-sc-date"></label>'
                    + '<label>Estimasi biaya (Rp)<input type="number" min="0" id="io-sc-cost"></label>'
                    + '</div><div class="io-sub">Tanpa CO: PR subkon tetap dibuat, surat jalan menyusul setelah CO terbit (klik Subkon lagi).</div>'
                    + '<div style="margin-top:8px"><button type="button" class="io-mbtn on" data-act="subcon-go">Proses Subkon</button> '
                    + '<button type="button" class="io-mbtn" data-act="subcon-cancel">Batal</button></div></div>';
            }

            function bomHtml(p, sp) {
                const rows = rowsOf(p, 'bom'), ed = p.editing.bom, raw = p.edit.bom || [];
                let h = tools(p, 'bom')
                    + '<div class="io-inputs"><b>Qty order:</b> ' + (sp.inputs.orderQty ? fmtAngka(sp.inputs.orderQty) + ' pcs' : '—')
                    + ' <b style="margin-left:10px">Lembar cetak dibutuhkan:</b> ' + (sp.inputs.printSheets ? fmtAngka(sp.inputs.printSheets) + ' lembar (termasuk waste & toleransi BOP)' : '—') + '</div>';

                if (ed) {
                    h += '<div class="io-tblwrap"><table class="io-tbl io-edit"><tr><th>Peran</th><th>Bahan</th><th>Gramatur</th><th>P (cm)</th><th>L (cm)</th>'
                        + '<th>Qty / lembar</th><th>Satuan</th><th>Kebutuhan</th><th></th></tr>'
                        + raw.map((r, i) => {
                            const c = rows[i] || {};
                            return '<tr><td><select' + field('bom', i, 'role', '') + '>' + optsTxt(LK.roles, r.role) + '</select></td>'
                                + '<td class="io-rel"><input placeholder="ketik nama / kode bahan…"' + field('bom', i, 'search', r.name) + ' autocomplete="off">'
                                + (p.dd && p.dd.i === i ? ddHtml(p.dd) : '') + '</td>'
                                + '<td><input type="number" min="0"' + field('bom', i, 'gsm', r.gsm) + '></td>'
                                + '<td><input type="number" min="0" step="0.1"' + field('bom', i, 'size_p', r.size_p) + '></td>'
                                + '<td><input type="number" min="0" step="0.1"' + field('bom', i, 'size_l', r.size_l) + '></td>'
                                + '<td><input type="number" min="0" step="any"' + field('bom', i, 'qty_per_sheet', r.qty_per_sheet) + '></td>'
                                + '<td><select' + field('bom', i, 'uom', '') + '>' + optsTxt(LK.uoms, r.uom) + '</select></td>'
                                + '<td>' + (c.qty_need !== undefined && c.qty_need !== null ? fmtAngka(c.qty_need) + ' ' + esc(c.uom || '') : '—') + '</td>'
                                + '<td><button type="button" class="io-ibtn bad" data-act="del" data-sec="bom" data-i="' + i + '" title="Hapus">✕</button></td></tr>';
                        }).join('')
                        + '</table></div><button type="button" class="io-mbtn" data-act="add" data-sec="bom">＋ Tambah bahan</button>';
                } else if (rows.length) {
                    h += '<div class="io-tblwrap"><table class="io-tbl"><tr><th>Peran</th><th>Bahan</th><th>Merek</th><th>Spesifikasi</th><th>Per lembar cetak</th>'
                        + '<th>Qty kebutuhan</th><th>Stok tersedia (gudang)</th><th>Status</th><th></th></tr>'
                        + rows.map((r, i) => {
                            const spec = [r.gsm ? fmtAngka(r.gsm) + ' gsm' : '', r.size_p ? fmtAngka(r.size_p) + (r.size_l ? ' × ' + fmtAngka(r.size_l) : '') + ' cm' : ''].filter(Boolean).join(' · ');
                            const need = r.qty_need !== null ? fmtAngka(r.qty_need) + ' ' + esc(r.basis === 'PLANO' ? 'lembar plano' : (r.uom || ''))
                                + (r.qty_need_stock_uom !== null && r.stock_uom && r.stock_uom !== r.uom ? '<div class="io-sub">= ' + fmtAngka(r.qty_need_stock_uom) + ' ' + esc(r.stock_uom) + '</div>' : '')
                                : '<span class="io-sub">' + (r.name === 'None' ? '—' : 'belum bisa dihitung') + '</span>';
                            return '<tr><td>' + esc(r.role) + '</td><td><b>' + esc(r.name || '—') + '</b>'
                                + (r.note ? '<div class="io-sub">' + esc(r.note) + '</div>' : '') + '</td>'
                                + '<td>' + esc(r.brand || '—') + '</td>'
                                + '<td>' + (spec || '—') + '</td>'
                                + '<td>' + (r.qty_per_sheet !== null ? fmtAngka(r.qty_per_sheet) + ' ' + esc(r.uom || '') : '—') + '</td>'
                                + '<td>' + need + '</td>'
                                + '<td>' + (r.material_id ? fmtAngka(r.stock_available ?? 0) + ' ' + esc(r.stock_uom || '') : '—') + '</td>'
                                + '<td>' + (r.stock_status ? '<span class="io-stat ' + (STATUS_CLS[r.stock_status] || 'mute') + '">' + esc(r.stock_status) + '</span>'
                                    + (r.shortage ? '<div class="io-sub">kurang ' + fmtAngka(r.shortage) + ' ' + esc(r.stock_uom || '') + '</div>' : '') : '—') + '</td>'
                                + '<td>' + (r.material_id && ['KURANG', 'TIDAK ADA'].includes(r.stock_status) && (r.shortage > 0 || r.qty_need > 0)
                                    ? '<button type="button" class="io-mbtn warn" data-act="pr" data-i="' + i + '">＋ Buat PR</button>' : '') + '</td></tr>';
                        }).join('')
                        + '</table></div>';
                } else {
                    h += '<div class="io-empty">Belum ada bahan.</div>';
                }

                return h + notes(sp.warnings.bom);
            }

            function ddHtml(dd) {
                if (dd.busy) return '<div class="io-dd"><div class="io-sub">Mencari…</div></div>';
                if (!dd.items.length) return '<div class="io-dd"><div class="io-sub">Tidak ada bahan cocok.</div></div>';
                return '<div class="io-dd">' + dd.items.map((m, k) => '<button type="button" data-act="pick-mat" data-k="' + k + '">'
                    + '<b>' + esc(m.name) + '</b><span>' + esc(m.code) + ' · ' + esc(m.category || '') + ' · stok ' + fmtAngka(m.stock) + ' ' + esc(m.uom || '') + '</span></button>').join('') + '</div>';
            }

            function inkHtml(p, sp) {
                const rows = rowsOf(p, 'inks'), ed = p.editing.inks, raw = p.edit.inks || [];
                const art = p.artBusy ? '<div class="io-empty">Menganalisa warna artwork…</div>'
                    : p.artError ? '<div class="io-notice bad">Analisa artwork gagal: ' + esc(p.artError) + '</div>' : '';
                let h = tools(p, 'inks')
                    + '<div class="io-inputs"><b>Notasi warna:</b> ' + esc(sp.inputs.color || '—')
                    + (artSource(p) ? ' <button type="button" class="io-mbtn" data-act="reanalyze" style="margin-left:10px">↻ Analisa ulang artwork</button>' : '') + '</div>'
                    + '<datalist id="io-inknames">' + LK.inks.map(n => '<option value="' + esc(n) + '">').join('') + '</datalist>';

                if (ed) {
                    const units = [1, 2, 3, 4, 5, 6, 7, 8].map(n => ({ id: n, name: 'Unit ' + n }));
                    h += '<div class="io-tblwrap"><table class="io-tbl io-edit"><tr><th>Pass</th><th>Jenis</th><th>Unit</th><th>Tinta</th><th>Warna</th><th>Tutupan %</th><th>Luas / pcs</th><th></th></tr>'
                        + raw.map((r, i) => {
                            const c = rows[i] || {};
                            return '<tr><td><select' + field('inks', i, 'pass_seq', '') + '>' + opts([{ id: 1, name: 'Warna Pertama' }, { id: 2, name: 'Warna Kedua' }], r.pass_seq) + '</select></td>'
                                + '<td><select' + field('inks', i, 'pass_label', '') + '>' + optsTxt(['CS', 'SPOT'], r.pass_label) + '</select></td>'
                                + '<td><select' + field('inks', i, 'unit_no', '') + '>' + opts(units, r.unit_no) + '</select></td>'
                                + '<td><input list="io-inknames"' + field('inks', i, 'ink_name', r.ink_name) + '></td>'
                                + '<td><input type="color"' + field('inks', i, 'color_hex', r.color_hex || '#888888') + '></td>'
                                + '<td><input type="number" min="0" max="100" step="0.1"' + field('inks', i, 'coverage_pct', r.coverage_pct) + '></td>'
                                + '<td>' + (c.coverage_cm2 !== undefined && c.coverage_cm2 !== null ? fmtAngka(c.coverage_cm2) + ' cm²' : '—') + '</td>'
                                + '<td><button type="button" class="io-ibtn bad" data-act="del" data-sec="inks" data-i="' + i + '" title="Hapus">✕</button></td></tr>';
                        }).join('')
                        + '</table></div><button type="button" class="io-mbtn" data-act="add" data-sec="inks">＋ Tambah tinta</button>';
                } else if (rows.length) {
                    h += '<div class="io-tblwrap"><table class="io-tbl"><tr><th>Pass</th><th>Unit</th><th>Tinta</th><th>Tutupan</th><th>Luas / pcs</th><th>Luas / lembar</th></tr>'
                        + rows.map(r => '<tr><td>' + (+r.pass_seq === 1 ? 'Warna Pertama' : 'Warna Kedua') + ': ' + esc(r.pass_label) + '</td><td>Unit ' + r.unit_no + '</td><td>'
                            + '<i class="io-sw" style="background:' + esc(r.color_hex || '#888') + '"></i><b>' + esc(r.ink_name) + '</b></td><td>'
                            + (r.coverage_pct !== null ? fmtAngka(r.coverage_pct) + '%' : '—') + '</td><td>'
                            + (r.coverage_cm2 !== null ? fmtAngka(r.coverage_cm2) + ' cm²' : '—') + '</td><td>'
                            + (r.coverage_cm2_sheet !== null ? fmtAngka(r.coverage_cm2_sheet) + ' cm²' : '—') + '</td></tr>').join('') + '</table></div>';
                }

                return h + art
                    + (sp.colors.length
                        ? '<div class="io-subh">Warna di artwork (luas per pcs)</div><div class="io-colors">'
                          + sp.colors.map(c => '<span class="io-colorchip"><i class="io-sw" style="background:' + esc(c.hex || '#888') + '"></i>'
                              + esc(c.name) + ' <b>' + (c.cm2 !== null ? fmtAngka(c.cm2) + ' cm²' : fmtAngka(c.pct) + '%') + '</b></span>').join('') + '</div>'
                        : '')
                    + notes(sp.warnings.ink);
            }

            function renderSpecSections() {
                const p = S.p, sp = p.spec;
                specInit(p);
                const tunggu = '<div class="io-empty">' + (p.partId ? 'Menghitung dari data Inquiry…' : 'Job demo — engine butuh part nyata.') + '</div>';

                // Pertahankan fokus kursor saat tabel edit digambar ulang.
                const a = document.activeElement, keep = a && a.dataset && a.dataset.f
                    ? { sec: a.dataset.sec, i: a.dataset.i, f: a.dataset.f, pos: a.selectionStart } : null;

                $('cnt-bop').textContent = sp ? rowsOf(p, 'bop').length : '';
                $('cnt-bom').textContent = sp ? rowsOf(p, 'bom').length : '';
                $('cnt-ink').textContent = sp ? rowsOf(p, 'inks').length : '';
                $('bop').innerHTML = sp ? bopHtml(p, sp) : tunggu;
                $('bom').innerHTML = sp ? bomHtml(p, sp) : tunggu;
                $('ink').innerHTML = sp ? inkHtml(p, sp) : tunggu;

                if (keep) {
                    const el = document.querySelector('#io-acc [data-sec="' + keep.sec + '"][data-i="' + keep.i + '"][data-f="' + keep.f + '"]');
                    if (el) { el.focus(); try { if (keep.pos !== null && keep.pos !== undefined) el.setSelectionRange(keep.pos, keep.pos); } catch (e) { /* input number */ } }
                }
                samakanTinggiAcc();
            }

            const NUM_FIELDS = ['process_id', 'machine_id', 'target_qty_per_hour', 'waste_pct', 'tolerance_pct', 'subcon_supplier_id',
                'gsm', 'size_p', 'size_l', 'qty_per_sheet', 'pass_seq', 'unit_no', 'coverage_pct'];

            function blankRow(k, p) {
                if (k === 'bop') return { process_id: null, label: 'Langkah baru', machine_id: null, target_qty_per_hour: null, waste_pct: 0, tolerance_pct: 0, subcon_supplier_id: null, note: null };
                if (k === 'bom') return { role: 'BAHAN LAIN', material_id: null, name: 'BAHAN BARU', gsm: null, size_p: null, size_l: null, qty_per_sheet: null, uom: 'PCS', primary: false, note: null };
                const used = (p.edit.inks || []).filter(r => +r.pass_seq === 1).map(r => +r.unit_no);
                return { pass_seq: 1, pass_label: 'SPOT', unit_no: Math.min(8, (used.length ? Math.max(...used) : 0) + 1), ink_name: 'PANTONE', color_hex: null, coverage_pct: null };
            }

            // Salinan baris tampilan -> baris yang bisa diedit (hanya field input).
            function toEditable(k, rows) {
                const pick = {
                    bop: ['process_id', 'label', 'machine_id', 'target_qty_per_hour', 'waste_pct', 'tolerance_pct', 'subcon_supplier_id', 'note'],
                    bom: ['role', 'material_id', 'name', 'brand', 'gsm', 'size_p', 'size_l', 'qty_per_sheet', 'uom', 'basis', 'rate', 'primary', 'note'],
                    inks: ['pass_seq', 'pass_label', 'unit_no', 'ink_name', 'color_hex', 'coverage_pct'],
                }[k];
                return rows.map(r => Object.fromEntries(pick.map(f => [f, r[f] ?? null])));
            }

            function touch(p, k) {
                p.edit[k].__touched = true;
                p.editSrc[k] = 'OVERRIDE';
                specSoon();
            }

            let ddTimer = null;
            function bindSpecEvents() {
                const acc = document.getElementById('io-acc');

                acc.addEventListener('click', async e => {
                    const b = e.target.closest('[data-act]');
                    if (!b) return;
                    const p = S.p, k = b.dataset.sec, i = +b.dataset.i;
                    specInit(p);

                    switch (b.dataset.act) {
                        case 'edit':
                            if (!p.editing[k]) {
                                if (!p.edit[k]) p.edit[k] = toEditable(k, rowsOf(p, k));
                                p.editing[k] = true;
                                specSoon();
                            } else {
                                p.editing[k] = false;
                                // Tidak ada yang diubah -> tetap ikut engine, bukan override.
                                if (!p.edit[k].__touched && !(p.saved && p.saved[k])) p.edit[k] = null;
                                specSoon();
                            }
                            p.dd = null;
                            break;
                        case 'reset':
                            p.edit[k] = null; p.editing[k] = false; p.editSrc[k] = null;
                            if (p.saved && p.saved[k] && !p.reset.includes(k)) p.reset.push(k);
                            p.flash[k] = { ok: true, text: 'Dikembalikan ke hasil engine — tersimpan saat Save.' };
                            specSoon();
                            break;
                        case 'add':
                            p.edit[k].push(blankRow(k, p)); touch(p, k);
                            break;
                        case 'del':
                            p.edit[k].splice(i, 1); touch(p, k);
                            break;
                        case 'up':
                        case 'down': {
                            const j = b.dataset.act === 'up' ? i - 1 : i + 1, arr = p.edit[k];
                            if (j < 0 || j >= arr.length) return;
                            [arr[i], arr[j]] = [arr[j], arr[i]]; touch(p, k);
                            break;
                        }
                        case 'pick-mat': {
                            const m = p.dd && p.dd.items[+b.dataset.k], row = p.dd && p.edit.bom[p.dd.i];
                            if (!m || !row) return;
                            Object.assign(row, { material_id: m.id, name: m.name, gsm: m.gsm ?? row.gsm, uom: m.uom || row.uom });
                            p.dd = null; touch(p, 'bom');
                            break;
                        }
                        case 'reanalyze':
                            analyzeArtwork(true);
                            return;
                        case 'pr': {
                            const r = rowsOf(p, 'bom')[i], w = wire();
                            if (!r || !w) return;
                            b.disabled = true;
                            // Satuan stok tak bisa dikonversi -> minta sebanyak kebutuhan, satuan kebutuhan.
                            const byStock = r.shortage !== null && r.shortage > 0;
                            const res = (await w.call('createPurchase', p.partId, r.material_id,
                                byStock ? r.shortage : r.qty_need, byStock ? r.stock_uom : r.uom).catch(() => null))
                                || { ok: false, message: 'Server tidak merespons, coba lagi.' };
                            p.flash.bom = { ok: res.ok, text: res.message, url: res.url };
                            break;
                        }
                        case 'subcon':
                            p.subconAt = p.subconAt === i ? null : i;
                            break;
                        case 'subcon-cancel':
                            p.subconAt = null;
                            break;
                        case 'subcon-go': {
                            const r = rowsOf(p, 'bop')[p.subconAt], w = wire();
                            const sup = +document.getElementById('io-sc-sup').value;
                            if (!r || !w) return;
                            if (!sup) { p.flash.bop = { ok: false, text: 'Pilih supplier subkon dulu.' }; break; }
                            b.disabled = true;
                            const res = await w.call('subcontract', p.partId,
                                { process_id: r.process_id, label: r.label, qty_in: r.qty_in, uom: r.uom },
                                sup, +document.getElementById('io-sc-co').value || null,
                                document.getElementById('io-sc-date').value || null,
                                +document.getElementById('io-sc-cost').value || null).catch(() => null)
                                || { ok: false, message: 'Server tidak merespons, coba lagi.' };
                            p.flash.bop = { ok: res.ok, text: res.message, url: res.prUrl };
                            if (res.ok) { p.subconAt = null; specKey = ''; specSoon(); }
                            break;
                        }
                        default:
                            return;
                    }
                    renderSpecSections();
                });

                acc.addEventListener('change', e => {
                    const el = e.target, f = el.dataset && el.dataset.f;
                    if (!f) return;
                    const p = S.p, k = el.dataset.sec, row = p.edit[k] && p.edit[k][+el.dataset.i];
                    if (!row) return;
                    const v = el.value;
                    if (f === 'search') {
                        // Nama diketik bebas (tidak dipilih dari daftar) = bahan di luar master.
                        if (v.trim() !== (row.name || '')) { row.name = v.trim(); row.material_id = null; touch(p, k); }
                        return;
                    }
                    row[f] = NUM_FIELDS.includes(f) ? (v === '' ? null : +v) : v;
                    // Proses baru dipilih -> label ikut nama proses bila label masih bawaan.
                    if (f === 'process_id' && (!row.label || row.label === 'Langkah baru')) {
                        const pr = LK.processes.find(x => +x.id === +v);
                        if (pr) row.label = pr.name;
                    }
                    touch(p, k);
                });

                acc.addEventListener('input', e => {
                    const el = e.target;
                    if (!el.dataset || el.dataset.f !== 'search') return;
                    const p = S.p, i = +el.dataset.i, q = el.value.trim();
                    clearTimeout(ddTimer);
                    if (q.length < 2) { if (p.dd) { p.dd = null; renderSpecSections(); } return; }
                    ddTimer = setTimeout(async () => {
                        const w = wire();
                        if (!w) return;
                        p.dd = { i, busy: true, items: [] };
                        renderSpecSections();
                        const items = await w.call('searchMaterials', q);
                        if (p.dd && p.dd.i === i) { p.dd = { i, busy: false, items: items || [] }; renderSpecSections(); }
                    }, 300);
                });
            }

            // ===================== MASTER CARD (pratinjau & cetak) =====================
            // Semua informasi ProDev yang sedang tampil dirangkum jadi 1 lembar
            // A4 landscape mengikuti bentuk Master Card / Kartu Induk pabrik.
            // Selama layout belum di-Confirm, lembar ini dicap "NOT CONFIRMED".
            const GRAIN_LABEL = { L: 'LG', W: 'SG', none: '—' };

            // Gambar layout untuk MASTER CARD: versi HEMAT TINTA (permintaan user
            // 2026-09-18 -- "jangan ada block warna gelap di canvas"). Latar &
            // permukaan artwork dibuat putih, blok gelap diturunkan jadi abu muda,
            // garis potong/lipat tetap terbaca. Gambarnya diperbesar sebesar lembar
            // cetak lalu diberi anak panah ukuran panjang & lebar (ukuran canvas
            // tidak lagi ditulis sebagai judul).
            const MC_LIGHT = '#f0f0f0';

            function mcIsDark(color) {
                if (!color) return false;
                let r, g, b;
                const hex = color.trim().replace(/^#/, '');
                if (/^[0-9a-f]{6}$/i.test(hex)) {
                    [r, g, b] = [0, 2, 4].map(i => parseInt(hex.substr(i, 2), 16));
                } else if (/^[0-9a-f]{3}$/i.test(hex)) {
                    [r, g, b] = [0, 1, 2].map(i => parseInt(hex[i] + hex[i], 16));
                } else {
                    const m = color.match(/rgba?\(([^)]+)\)/i);
                    if (!m) return false;
                    [r, g, b] = m[1].split(',').map(v => parseFloat(v));
                }
                return (0.299 * r + 0.587 * g + 0.114 * b) / 255 < 0.72;
            }

            function mcLighten(el) {
                const tag = el.tagName.toLowerCase();

                if (['svg', 'defs', 'marker', 'g', 'clippath', 'lineargradient', 'pattern', 'stop'].includes(tag)) {
                    return;
                }

                const cs = getComputedStyle(el);
                const fill = cs.fill;
                const cls = el.getAttribute('class') || '';

                if (tag === 'text' || cls.includes('io-lbl') || cls.includes('io-graintext')) {
                    el.style.fill = '#333';
                    return;
                }
                if (cls.includes('io-artfill')) {
                    el.style.fill = '#fff';
                    el.style.fillOpacity = '1';
                    el.style.stroke = '#8a98a6';
                    el.style.strokeWidth = '.8';
                    return;
                }
                if (cls.includes('io-die') || cls.includes('io-fold') || cls.includes('io-cutmark')) {
                    // Garis potong/lipat tetap terbaca, tapi TIDAK boleh punya isian
                    // (path terbuka yang ter-fill hitam = blok tinta saat dicetak).
                    el.style.fill = 'none';
                    return;
                }
                if (tag === 'g' || tag === 'svg') {
                    return;
                }
                // Garis (line/polyline) tidak punya bidang -> isian diabaikan.
                if (['line', 'polyline'].includes(tag)) {
                    el.style.fill = 'none';
                } else if (fill && fill !== 'none' && mcIsDark(fill)) {
                    el.style.fill = MC_LIGHT;
                    if (cs.stroke === 'none') {
                        el.style.stroke = '#b9c2cb';
                        el.style.strokeWidth = '.6';
                    }
                }

                if (cs.stroke !== 'none' && mcIsDark(cs.stroke)) {
                    el.style.stroke = '#555';
                }
            }

            // Ruang untuk anak panah + labelnya, dipakai bersama oleh viewBox &
            // penggambaran panah supaya label tidak terpotong tepi gambar.
            function mcBox(sheet) {
                const x = +sheet.getAttribute('x'), y = +sheet.getAttribute('y');
                const w = +sheet.getAttribute('width'), h = +sheet.getAttribute('height');
                const off = Math.max(18, Math.min(w, h) * .06);
                const fs = Math.max(11, w * .022);

                return { x, y, w, h, off, fs, gap: off * .5, room: off + fs * 3.8 };
            }

            function mcArrows(sheet) {
                const { x, y, w, h, off, fs } = mcBox(sheet);
                const tick = off * .45;
                const L = fmtAngka(S.L) + ' cm', W = fmtAngka(S.W) + ' cm';

                return ''
                    + '<defs><marker id="io-mcArw" markerWidth="7" markerHeight="7" refX="6" refY="3.5" orient="auto">'
                    + '<path d="M0 0L7 3.5 0 7Z" fill="#333"/></marker></defs>'
                    + '<g stroke="#333" stroke-width="1" fill="none">'
                    // panah ukuran panjang (bawah)
                    + '<path d="M' + x + ' ' + (y + h + off) + 'H' + (x + w) + '" marker-start="url(#io-mcArw)" marker-end="url(#io-mcArw)"/>'
                    + '<path d="M' + x + ' ' + (y + h + off - tick) + 'v' + (tick * 2) + 'M' + (x + w) + ' ' + (y + h + off - tick) + 'v' + (tick * 2) + '"/>'
                    // panah ukuran lebar (kanan)
                    + '<path d="M' + (x + w + off) + ' ' + y + 'V' + (y + h) + '" marker-start="url(#io-mcArw)" marker-end="url(#io-mcArw)"/>'
                    + '<path d="M' + (x + w + off - tick) + ' ' + y + 'h' + (tick * 2) + 'M' + (x + w + off - tick) + ' ' + (y + h) + 'h' + (tick * 2) + '"/>'
                    + '</g>'
                    + '<g fill="#111" font-family="Arial,Helvetica,sans-serif" font-weight="700" font-size="' + fs + '">'
                    + '<text x="' + (x + w / 2) + '" y="' + (y + h + off + fs * 1.5) + '" text-anchor="middle">' + L + '</text>'
                    + '<text x="' + (x + w + off + fs * 1.4) + '" y="' + (y + h / 2) + '" text-anchor="middle" '
                    + 'transform="rotate(90 ' + (x + w + off + fs * 1.4) + ' ' + (y + h / 2) + ')">' + W + '</text>'
                    + '</g>';
            }

            function masterCardSvg() {
                const src = $('svg');
                if (!src) return '';

                const clone = src.cloneNode(true);
                clone.removeAttribute('width');
                clone.removeAttribute('height');
                clone.removeAttribute('id');
                clone.style.background = '#fff';
                clone.querySelectorAll('.selected').forEach(el => el.classList.remove('selected'));
                S.objs.filter(o => !inCanvas(o)).forEach(o => {
                    const el = clone.querySelector('[data-id="' + o.id + '"]');
                    if (el) el.remove();
                });

                const sheet = clone.querySelector('#io-sheet');
                if (sheet) {
                    sheet.removeAttribute('class');
                    sheet.setAttribute('fill', '#fff');
                    sheet.setAttribute('stroke', '#333');
                    sheet.setAttribute('stroke-width', '1.2');
                    const safe = clone.querySelector('#io-safeRect');
                    if (safe) {
                        safe.setAttribute('stroke', '#c8ced6');
                        safe.setAttribute('fill', 'none');
                    }

                    // Perbesar: bidang gambar = lembar cetak + ruang anak panah/label.
                    const b = mcBox(sheet);
                    clone.setAttribute('viewBox', (b.x - b.gap) + ' ' + (b.y - b.gap) + ' '
                        + (b.w + b.gap + b.room) + ' ' + (b.h + b.gap + b.room));
                    clone.insertAdjacentHTML('beforeend', mcArrows(sheet));
                }

                return clone.outerHTML;
            }


            function knifeInfo() {
                const st = gatherState();
                const up = st.upCount || 0;
                const nama = (S.p.productName || S.p.name || '').toUpperCase();
                return {
                    code: nama ? 'PISAU ' + nama + ' ISI ' + up + ' MATA' : '—',
                    size: st.knifeW && st.knifeH ? fmtAngka(st.knifeW) + ' × ' + fmtAngka(st.knifeH) + ' cm' : '—',
                    up: up,
                };
            }

            function mcRow(label, value, mono) {
                return '<tr><th>' + esc(label) + '</th><td' + (mono ? ' class="mono"' : '') + '>' + (value ?? '—') + '</td></tr>';
            }

            // Routing di Master Card cukup "Proses - Mesin": label engine untuk
            // cetak berbunyi "Cetak di mesin: X" padahal mesinnya juga tertulis
            // terpisah -> dulu tampil dobel dan kolomnya harus lebar.
            function mcStepName(r) {
                const name = String(r.label || '').replace(/\s*(di mesin)?\s*:.*$/i, '').trim() || r.label || '';
                return r.machine ? name + ' - ' + r.machine : name;
            }

            // Baris TINTA disebut warnanya (dari catatan engine "Unit n · CYAN"),
            // kalau tidak semua baris tinta tampil sama.
            function mcBomRow(r) {
                let role = r.role || '';
                if (role === 'TINTA') {
                    const m = String(r.note || '').match(/Unit\s*\d+\s*·\s*([^·]+)/);
                    role += ' ' + (m ? m[1].trim() : String(r.name || '').replace(/^.*\bINK\b\s*/i, '')).toUpperCase();
                }
                return mcRow(role.trim(), r.qty_need !== null && r.qty_need !== undefined ? fmtAngka(r.qty_need) + ' ' + esc(r.uom || '') : '—', true);
            }

            function masterCardHtml() {
                const p = S.p, sp = p.spec, k = knifeInfo();
                const bom = sp ? (p.edit && p.edit.bom && sp.override.bom ? sp.override.bom : sp.bom) : [];
                const bop = sp ? (p.edit && p.edit.bop && sp.override.bop ? sp.override.bop : sp.bop) : [];
                const inks = sp ? (p.edit && p.edit.inks && sp.override.inks ? sp.override.inks : sp.inks) : [];
                const kertas = bom.find(r => r.basis === 'PLANO' && r.material_id) || bom[0] || {};
                const pass1 = inks.filter(r => +r.pass_seq === 1);
                const finishing = sp && sp.inputs.finishings.length ? sp.inputs.finishings.join(', ') : (p.finishingText || '—');
                const svg = masterCardSvg();
                // Routing dicetak dalam 15 baris TETAP (permintaan user 2026-09-18):
                // sisanya dibiarkan kosong supaya tata letaknya selalu sama.
                const ROUTING_ROWS = 15;
                const cetak = fmtAngka(S.L) + ' × ' + fmtAngka(S.W) + ' cm';

                return ''
                    + '<div class="io-mc-head">'
                    + '<div><div class="brand">Telasih Makmur <i>offset printing &amp; packaging</i></div>'
                    + '<h2>MASTER CARD / KARTU INDUK</h2></div>'
                    + '<div class="item">Kode Item: ' + esc(p.itemCode || p.design || '—') + '</div></div>'

                    + '<div class="io-mc-body"><div>'
                    + '<table class="io-mc-tbl">'
                    + '<tr><th>No. Desain</th><td class="mono">' + esc(p.designNo || '—') + '</td>'
                    + '<th style="width:18%">Nama Barang</th><td><b>' + esc(p.productName || p.name) + '</b></td>'
                    + '<th style="width:12%">Tanggal</th><td class="mono">' + esc(p.today || '') + '</td></tr>'
                    + '</table>'
                    + '<table class="io-mc-tbl">'
                    + mcRow('Nama Pelanggan', '<b>' + esc(p.customer) + '</b>')
                    + '<tr><th>Dimensi P</th><td class="mono">' + fmtAngka(p.fl) + ' cm</td>'
                    + '<th>Dimensi L</th><td class="mono">' + fmtAngka(p.fw) + ' cm</td>'
                    + '<th>Dimensi T</th><td class="mono">' + fmtAngka(p.fh) + ' cm</td></tr>'
                    + '<tr><th>Kode Pisau</th><td colspan="3"><b>' + esc(k.code) + '</b></td>'
                    + '<th>Ukuran Pisau</th><td class="mono">' + k.size + '</td></tr>'
                    + '<tr><th>Material</th><td colspan="3"><b>' + esc(kertas.name || '—') + '</b>'
                    + (kertas.brand ? ' <span style="color:#555">(' + esc(kertas.brand) + ')</span>' : '') + '</td>'
                    + '<th>Kualitas</th><td class="mono">' + (kertas.gsm ? fmtAngka(kertas.gsm) + ' gsm' : '—') + '</td></tr>'
                    + '<tr><th>Ukuran Bahan</th><td colspan="3" class="mono">' + cetak + ' (' + (GRAIN_LABEL[p.grain] || '—') + ')</td>'
                    + '<th>Ukuran Cetak</th><td class="mono">' + cetak + '</td></tr>'
                    + mcRow('Jenis Finishing', esc(finishing))
                    + mcRow('Struktur / Joint', esc(p.structure || '—'))
                    + '</table>'

                    + '<div class="io-mc-sub"><div>'
                    + '<div class="io-mc-cap">Warna Pertama: CS' + (S.m ? ' (' + esc(S.m[0]) + ')' : '') + '</div>'
                    + '<table class="io-mc-tbl">'
                    + (pass1.length
                        ? pass1.map(r => '<tr><th style="width:34%">Unit ' + r.unit_no + '</th><td><b>' + esc(r.ink_name) + '</b>'
                            + (r.coverage_pct !== null && r.coverage_pct !== undefined ? ' <span style="color:#555">' + fmtAngka(r.coverage_pct) + '% · ' + fmtAngka(r.coverage_cm2) + ' cm²</span>' : '')
                            + '</td></tr>').join('')
                        : '<tr><td colspan="2" style="text-align:center;color:#777">Susunan tinta belum ada</td></tr>')
                    + '<tr><th>Isi / Lembar</th><td class="mono"><b>' + k.up + ' UP</b></td></tr>'
                    + '</table>'
                    // Mesin plong tidak diulang di sini -- sudah tertulis di Routing.
                    + '<table class="io-mc-tbl" style="margin-top:5px">'
                    + mcRow('Dieline / blank', fmtAngka(p.l) + ' × ' + fmtAngka(p.w) + ' cm · gap ' + fmtAngka(S.g) + ' cm', true)
                    + mcRow('Plano', esc(S.pl[0]) + ' · ' + cut(S.pl).n + ' lembar / plano', true)
                    + mcRow('Efisiensi material', ($('yAll') ? $('yAll').textContent : '—'))
                    + '</table></div><div>'
                    + '<div class="io-mc-cap">Urutan Pekerjaan (Routing)</div>'
                    + '<table class="io-mc-tbl">'
                    + Array.from({ length: ROUTING_ROWS }, (_, i) => {
                        const r = bop[i];
                        return '<tr><th style="width:22px;text-align:center">' + (i + 1) + '</th><td>'
                            + (r ? esc(mcStepName(r))
                                + (r.subcon_supplier ? ' <span style="color:#b45309">· subkon ' + esc(r.subcon_supplier) + '</span>' : '')
                              : '&nbsp;')
                            + '</td></tr>';
                    }).join('')
                    + '</table></div></div>'
                    // Ringkasan order & BOM di kolom kiri, tanda tangan di kolom kanan
                    // bawah layout -- tidak ada lagi baris selebar halaman yang
                    // menyisakan ruang kosong di tengah (permintaan user 2026-09-18).
                    + '<div class="io-mc-extra"><table class="io-mc-tbl">'
                    + mcRow('Qty order', sp && sp.inputs.orderQty ? fmtAngka(sp.inputs.orderQty) + ' pcs' : '—', true)
                    + mcRow('Lembar cetak', sp && sp.inputs.printSheets ? fmtAngka(sp.inputs.printSheets) + ' lbr' : '—', true)
                    + mcRow('Isi / pack', sp && sp.inputs.packQty ? sp.inputs.packQty + ' pcs' : '—', true)
                    + mcRow('No. Inquiry', esc(p.reg || '—'), true)
                    + '</table><table class="io-mc-tbl">'
                    + bom.slice(0, 6).map(mcBomRow).join('')
                    + '</table><table class="io-mc-tbl">'
                    + bom.slice(6, 12).map(mcBomRow).join('')
                    + '</table></div>'
                    + '</div>'

                    + '<div><div class="io-mc-layout">'
                    + '<div class="ttl">LAYOUT LEMBAR CETAK — ' + k.up + ' UP</div>'
                    + svg + '</div>'
                    + '<div class="io-mc-note">* ACC PROOF PRINT (DIGITAL + CETAK) HARUS DILAMPIRKAN BERSAMA MASTER CARD</div>'
                    + '<div class="io-mc-sign">'
                    + ['Mengetahui (Desainer)|Product Development', 'Marketing / Sales|Sales Officer',
                       'PPIC / QA|Quality Control', 'Plant Manager|Kepala Pabrik']
                        .map(x => { const [who, role] = x.split('|');
                            return '<div><div class="who">' + who + '</div><div class="line">' + role + '</div></div>'; }).join('')
                    + '</div>'
                    + '</div></div>'

                    + (p.confirmed ? '' : '<div class="io-mc-stamp"><span>NOT CONFIRMED</span></div>');
            }

            function openMasterCard() {
                if (!ok()) { msg('Canvas masih di luar batas mesin — perbaiki dulu sebelum cetak Master Card.', 'bad'); return; }
                $('mcSheet').innerHTML = masterCardHtml();
                // Blok gelap diturunkan SETELAH masuk DOM: warna yang diwarisi dari
                // grup induk hanya terbaca lewat getComputedStyle.
                $('mcSheet').querySelectorAll('.io-mc-layout svg *').forEach(mcLighten);
                const st = $('mcState');
                st.textContent = S.p.confirmed ? 'CONFIRMED' : 'BELUM CONFIRM';
                st.className = 'io-mc-state' + (S.p.confirmed ? ' ok' : '');
                $('mcModal').classList.add('open');
            }

            function closeMasterCard() {
                $('mcModal').classList.remove('open');
            }

            // Job dipilih di grid, jadi di sini cuma DITAMPILKAN. Dropdown hanya
            // dipakai di mode ?demo=1 (banyak sampel), bukan di alur normal.
            function jobIdentity() {
                const banyak = products.length > 1;
                $('jobFixed').style.display = banyak ? 'none' : '';
                $('product').style.display = banyak ? '' : 'none';

                if (!banyak) {
                    $('jobFixed').innerHTML = '<b>' + S.p.name + '</b><span>'
                        + S.p.design + (S.p.reg && S.p.reg !== '—' ? ' · ' + S.p.reg : '') + '</span>';
                }

                $('jobcount').textContent = banyak
                    ? products.length + ' SAMPEL DEMO'
                    : (S.p.design || 'JOB TERPILIH');
            }

            // Auto Up  -> tampilkan angka HASIL, sembunyikan kotak isian.
            // Manual Up -> tampilkan kotak isian (up jadi target), sembunyikan hasil.
            function upModeUi() {
                $('autoResult').style.display = S.auto ? '' : 'none';
                $('manualGroup').style.display = S.auto ? 'none' : '';
                $('modeText').textContent = S.auto
                    ? 'Aktif: pakai up maksimum yang muat di canvas'
                    : 'Nonaktif: tentukan sendiri jumlah up yang dikejar';
            }

            function artworkLabel() {
                $('artName').textContent = S.p.artworkName
                    || (S.p.artworkUrl ? 'Artwork tersimpan di Inquiry Part' : (S.p.canUpload
                        ? 'Belum ada artwork — klik Upload artwork'
                        : 'Job Mode #2 / demo: tidak terhubung ke Inquiry Part'));
                $('dieName').textContent = S.p.dieline
                    ? 'Dieline dari spesifikasi Tipe Produk (dieline_spec)'
                    : 'Tipe produk ini belum punya definisi dieline';
            }

            function bind() {
                ['sl', 'sw', 'gap', 'grain', 'converterInput'].forEach(id => $(id).oninput = () => {
                    S.L = +$('sl').value; S.W = +$('sw').value; S.g = +$('gap').value; update(false);
                });
                $('product').onchange = e => {
                    S.p = products[e.target.value]; S.g = S.p.gap; $('gap').value = S.g;
                    $('grain').value = S.p.grain; S.objs = [];
                    jobIdentity(); renderSpecSections(); artworkLabel(); syncActiveJob(); suggest(); samakanTinggiAcc(); autoNestSoon();
                };
                $('machine').onchange = e => { S.m = machines[e.target.value]; S.objs = []; suggest(); autoNestSoon(); };
                $('diecut').onchange = e => { S.d = diecutters[e.target.value]; S.objs = []; suggest(); autoNestSoon(); };
                $('autoUp').onchange = e => {
                    S.auto = e.target.checked;
                    upModeUi();
                    S.objs = [];
                    // Pindah ke Manual Up: canvas yang sudah diketik officer
                    // dipertahankan (suggest() akan mengecilkannya ke ukuran minimum
                    // target). Ukuran otomatis tetap lewat tombol ✦ Suggest.
                    if (S.auto) { suggest(); } else { S.touched = false; update(true); autoNestSoon(); }
                };
                $('manualUp').oninput = e => {
                    S.manualUp = Math.max(1, +e.target.value || 1);
                    // Target berubah -> susunan dibangun ulang lalu jumlah object
                    // dicocokkan ke target (termasuk yang harus di luar canvas).
                    if (!S.auto) { S.objs = []; S.touched = false; update(true); autoNestSoon(); }
                };
                // Ditekan user = optimasi menyeluruh (canvas + plano + pola).
                // Kalau susunan sudah diatur MANUAL (bukan hasil auto nesting),
                // perilaku lama dipertahankan: canvas dipaskan ke susunan itu.
                // Susunan yang sudah ada (Manual Up, atau digeser/diputar officer)
                // dipadatkan dan canvas dipaskan ke situ -- BUKAN dibuang lalu
                // dioptimasi ulang dari nol (dulu kembali ke canvas 1 up).
                $('suggest').onclick = () => {
                    if (S.objs.length && (!S.auto || S.manualEdit)) { compactFit(); return; }
                    optimizeSheet();
                };
                $('plano').onchange = e => { S.pl = planos[e.target.value]; update(false); };
                // Hitung ulang plano secara eksplisit (permintaan user 2026-09-18),
                // tidak bergantung pada tombol Suggestion.
                $('calcPlano').onclick = () => {
                    const info = pickBestPlano();
                    update(false);
                    $('planoNote').innerHTML += '<br><b>Calculate Plano: ' + esc(info) + '.</b>';
                };
                $('custom').onclick = () => {
                    const l = +$('cpl').value, w = +$('cpw').value;
                    if (l && w) { S.pl = ['Custom ' + l + ' × ' + w + ' cm', l, w]; $('plano').value = ''; update(false); }
                };
                $('pcsMode').onclick = () => setMode('pcs');
                $('planoMode').onclick = () => setMode('plano');
                $('zout').onclick = () => z(-.15);
                $('zin').onclick = () => z(.15);
                app.querySelectorAll('[data-io-r]').forEach(b => b.onclick = () => transform('r', +b.dataset.ioR));
                app.querySelectorAll('[data-io-f]').forEach(b => b.onclick = () => transform('f', b.dataset.ioF));
                $('resetLayout').onclick = () => { S.objs = []; S.selected = null; update(true); };
                $('save').onclick = () => persist(false);
                $('confirm').onclick = () => persist(true);
                $('printMc').onclick = openMasterCard;
                $('mcPrint').onclick = () => window.print();
                $('mcClose').onclick = closeMasterCard;
                $('mcModal').onclick = e => { if (e.target === $('mcModal')) closeMasterCard(); };
                document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMasterCard(); });
                // Bukan `= autoNest` langsung: event klik akan terkirim sebagai
                // argumen pertama (silent) dan pesan hasilnya ikut tertelan.
                $('autoNest').onclick = () => autoNest(false);
                // Artwork: file input aslinya milik Livewire (di luar wire:ignore).
                $('artFile').onclick = e => {
                    e.preventDefault();
                    if (!S.p.canUpload) {
                        msg('Job ini tidak terhubung ke Inquiry Part (Mode #2 / demo) — artwork belum bisa disimpan.', 'bad');
                        return;
                    }
                    document.getElementById('io-artFileReal').click();
                };
                window.addEventListener('prodev-artwork-updated', e => {
                    const d = e.detail || {};
                    const job = products.find(j => j.partId === d.partId);
                    if (!job) return;
                    job.artworkUrl = d.url; job.artworkName = d.name;
                    // Server sudah menganalisa (termasuk PDF/AI) -> pakai hasilnya.
                    job.artColors = d.colors || null;
                    if (S.p === job) {
                        artworkLabel(); render();
                        if (job.artColors) { specKey = ''; specSoon(); renderSpecSections(); } else { analyzeArtwork(); }
                    }
                });
                $('dieFile').onchange = e => {
                    // Dieline terpisah belum punya tempat simpan -- lihat catatan diskusi.
                    $('dieName').textContent = (e.target.files[0] && e.target.files[0].name + ' (belum disimpan)') || 'Dieline struktural bawaan job aktif';
                };
            }

            function setMode(mode) {
                S.conv = mode;
                $('pcsMode').classList.toggle('active', mode === 'pcs');
                $('planoMode').classList.toggle('active', mode === 'plano');
                $('inputLabel').textContent = mode === 'pcs' ? 'Qty order (pcs)' : 'Available plano stock (sheets)';
                $('inputHint').textContent = mode === 'pcs'
                    ? 'Masukkan jumlah produk yang harus diproduksi'
                    : 'Masukkan jumlah lembar plano yang tersedia';
                $('converterInput').value = mode === 'pcs' ? 100 : 10;
                update(false);
            }

            function msg(t, c) { $('valid').className = 'io-notice ' + (c || 'info'); $('valid').textContent = t; }
            function z(x) {
                S.zoom = Math.max(.5, Math.min(1.8, S.zoom + x));
                $('wrap').style.transform = 'scale(' + S.zoom + ')';
                $('zoom').textContent = Math.round(S.zoom * 100) + '%';
            }
            // Canvas wajib muat di MEJA MESIN CETAK **dan** MEJA MESIN PLONG
            // (instruksi user 2026-09-16) -- lembar yang muat di press tapi tidak
            // muat di plong tetap tidak bisa diproduksi. Meja plong yang belum
            // diisi di Master Mesin tidak dikarang: batasnya sekadar tidak
            // diberlakukan, dan panel infonya mengatakan itu terang-terangan.
            function bedLimits() {
                let L = S.m[1], W = S.m[2];
                if (S.d && S.d[1] && S.d[2]) { L = Math.min(L, S.d[1]); W = Math.min(W, S.d[2]); }
                return [L, W];
            }

            function ok() {
                const [bl, bw] = bedLimits();
                return S.L > 0 && S.W > 0 && S.L <= bl && S.W <= bw;
            }

            // Mesin mana yang sedang jadi pembatas -- supaya peringatannya menyebut
            // nama mesinnya, bukan cuma angka.
            function bedOwner() {
                const [bl, bw] = bedLimits();
                return (S.d && S.d[1] && S.d[2] && (bl < S.m[1] || bw < S.m[2]))
                    ? S.d[0] + ' (plong)'
                    : S.m[0];
            }
            function fit(L, W, a, b, g) {
                const c = Math.floor((L + g) / (a + g)), r = Math.floor((W + g) / (b + g));
                return { ups: c * r, c, r, rot: false };
            }
            function best() {
                const a = fit(S.L, S.W, S.p.l, S.p.w, S.g), b = fit(S.L, S.W, S.p.w, S.p.l, S.g);
                if (b.ups > a.ups) { b.rot = true; return b; }
                return a;
            }
            function selectedUp() { return S.auto ? best().ups : S.manualUp; }

            // Object yang keluar canvas TIDAK sah (tidak bisa dicetak) -- tidak
            // boleh ikut dihitung sebagai up, disimpan, atau dicetak di Master Card.
            function inCanvas(o) {
                const v = obox(o), sc = S.sc0 || 1, sw = S.L * sc, sh = S.W * sc;
                return !(v.x < S.sx0 - .6 || v.y < S.sy0 - .6 || v.x + v.w > S.sx0 + sw + .6 || v.y + v.h > S.sy0 + sh + .6);
            }

            function upValid() {
                return S.objs.length ? S.objs.filter(inCanvas).length : selectedUp();
            }

            function obox(o) {
                const a = o.r * Math.PI / 180, c = Math.abs(Math.cos(a)), s = Math.abs(Math.sin(a)),
                    W = o.w * c + o.h * s, H = o.w * s + o.h * c, cx = o.x + o.w / 2, cy = o.y + o.h / 2;
                return { x: cx - W / 2, y: cy - H / 2, w: W, h: H };
            }

            // Mask diperlebar `g` px ke segala arah (lingkaran): dua object dianggap
            // bertabrakan bila jaraknya < gap. Cukup piksel tepi yang dicap --
            // piksel dalam sudah tertutup dengan sendirinya.
            function dilateMask(m, g) {
                if (g <= 0) return m;
                const cache = m._dil || (m._dil = {});
                if (cache[g]) return cache[g];

                const W = m.w + 2 * g, H = m.h + 2 * g, out = new Uint8Array(W * H);
                const disc = [];
                for (let dy = -g; dy <= g; dy++) for (let dx = -g; dx <= g; dx++) if (dx * dx + dy * dy <= g * g) disc.push(dy * W + dx);
                const on = (x, y) => x >= 0 && y >= 0 && x < m.w && y < m.h && m.data[y * m.w + x];

                for (let y = 0; y < m.h; y++) for (let x = 0; x < m.w; x++) {
                    if (!m.data[y * m.w + x]) continue;
                    const c = (y + g) * W + x + g;
                    if (on(x - 1, y) && on(x + 1, y) && on(x, y - 1) && on(x, y + 1)) { out[c] = 1; continue; }
                    for (const d of disc) out[c + d] = 1;
                }

                return (cache[g] = { w: W, h: H, data: out, shaped: m.shaped });
            }

            // Tombol Suggest pada susunan yang sudah ada (permintaan user
            // 2026-09-18): object dirapatkan ke kiri & ke atas sampai jarak antar
            // object = gap, lalu canvas = kotak pembatas + gap keliling. Uji
            // jarak memakai bentuk dieline asli (mask), jadi blank yang saling
            // mengunci tetap boleh masuk ke cekungan tetangganya.
            async function compactFit() {
                if (S.objs.some(o => (((Math.round(o.r) % 360) + 360) % 360) % 90)) { fitToLayout(); return; }

                const base = await maskFor(S.p);
                const f = base.mmPerPx || 1;                      // mm per piksel mask
                const g = Math.max(0, Math.round(S.g * 10 / f));  // gap dalam piksel
                const sc = S.sc0 || 1;

                const it = S.objs.map(o => {
                    const r = ((Math.round(o.r) % 360) + 360) % 360;
                    const m = variantFor(base, r, o.fx, o.fy);
                    return {
                        o, m, d: dilateMask(m, g), r, r0: r,
                        x: Math.round((o.x + o.w / 2 - S.sx0) / sc * 10 / f - m.w / 2),
                        y: Math.round((o.y + o.h / 2 - S.sy0) / sc * 10 / f - m.h / 2),
                    };
                });

                // a (diperlebar gap) di (ax,ay) vs b (mask asli) di posisinya
                const clash = (a, ax, ay, b) => {
                    const x0 = ax - g, y0 = ay - g;
                    if (b.x >= x0 + a.d.w || b.x + b.m.w <= x0 || b.y >= y0 + a.d.h || b.y + b.m.h <= y0) return false;
                    const dx = b.x - x0, dy = b.y - y0;
                    return dx >= 0 ? hits(a.d, b.m, dx, dy) : hits(b.m, a.d, -dx, -dy);
                };
                const free = (a, x, y) => it.every(b => b === a || !clash(a, x, y, b));

                const norm = () => {
                    const mx = Math.min(...it.map(a => a.x)), my = Math.min(...it.map(a => a.y));
                    it.forEach(a => { a.x -= mx; a.y -= my; });
                };

                const slide = (a, axis) => {
                    let moved = false;
                    for (const step of [8, 1]) {
                        for (;;) {
                            const nx = axis === 'x' ? a.x - step : a.x, ny = axis === 'y' ? a.y - step : a.y;
                            if ((axis === 'x' ? nx : ny) < 0 || !free(a, nx, ny)) break;
                            a.x = nx; a.y = ny; moved = true;
                        }
                    }
                    return moved;
                };

                const compact = () => {
                    // 1) Yang terlalu rapat / bertumpuk didorong ke kanan sampai berjarak gap.
                    norm();
                    const placed = [];
                    for (const a of [...it].sort((p, q) => p.x - q.x || p.y - q.y)) {
                        let guard = 0;
                        while (placed.some(b => clash(a, a.x, a.y, b)) && guard++ < 5000) a.x++;
                        placed.push(a);
                    }
                    // 2) Rapatkan ke kiri lalu ke atas, berulang sampai tidak ada yang bergeser.
                    for (let pass = 0; pass < 8; pass++) {
                        norm();
                        let moved = false;
                        for (const a of [...it].sort((p, q) => p.x - q.x)) moved = slide(a, 'x') || moved;
                        for (const a of [...it].sort((p, q) => p.y - q.y)) moved = slide(a, 'y') || moved;
                        if (!moved) break;
                    }
                    norm();
                };

                // Susunan awal bisa saja bertumpuk ke bawah (mis. sisa object yang
                // tadinya di luar canvas) sehingga hasil rapatnya melebihi meja
                // mesin, padahal disusun menyamping muat. Bila begitu, object
                // disusun ulang per baris / per kolom dalam batas meja -- juga
                // dicoba dengan semua object diputar 90° -- lalu dirapatkan lagi;
                // dipilih yang muat dengan luas terkecil.
                const [bedL, bedW] = bedLimits();
                const lim = [(bedL - 2 * S.g) * 10 / f, (bedW - 2 * S.g) * 10 / f];
                const span = () => [Math.max(...it.map(a => a.x + a.m.w)), Math.max(...it.map(a => a.y + a.m.h))];
                const over = sp => Math.max(0, sp[0] - lim[0]) + Math.max(0, sp[1] - lim[1]);
                const snap = () => it.map(a => [a.x, a.y, a.r]);
                const turn = add => it.forEach(a => {
                    a.r = (a.r0 + add) % 360;
                    a.m = variantFor(base, a.r, a.o.fx, a.o.fy);
                    a.d = dilateMask(a.m, g);
                });
                const reflow = byRow => {
                    const ax = byRow ? 0 : 1, len = a => byRow ? a.m.w : a.m.h, thick = a => byRow ? a.m.h : a.m.w;
                    let pos = 0, line = 0, lineT = 0;
                    for (const a of [...it].sort((p, q) => byRow ? p.y - q.y || p.x - q.x : p.x - q.x || p.y - q.y)) {
                        if (pos > 0 && pos + len(a) > lim[ax]) { pos = 0; line += lineT + g; lineT = 0; }
                        if (byRow) { a.x = pos; a.y = line; } else { a.x = line; a.y = pos; }
                        pos += len(a) + g; lineT = Math.max(lineT, thick(a));
                    }
                };

                compact();
                let best = { pos: snap(), sp: span() };
                if (over(best.sp) > 0) {
                    for (const add of [0, 90]) {
                        turn(add);
                        for (const byRow of [true, false]) {
                            reflow(byRow);
                            compact();
                            const sp = span(), o = over(sp);
                            if (o < over(best.sp) || (o === 0 && sp[0] * sp[1] < best.sp[0] * best.sp[1])) best = { pos: snap(), sp };
                        }
                    }
                    it.forEach((a, i) => {
                        [a.x, a.y, a.r] = best.pos[i];
                        a.m = variantFor(base, a.r, a.o.fx, a.o.fy);
                        a.o.r = a.r;
                    });
                }

                const spanX = Math.max(...it.map(a => a.x + a.m.w)) * f / 10;   // cm
                const spanY = Math.max(...it.map(a => a.y + a.m.h)) * f / 10;
                const ceil1 = v => Math.ceil(v * 10 - 1e-6) / 10;
                const rl = ceil1(spanX + 2 * S.g), rw = ceil1(spanY + 2 * S.g);
                const [bl, bw] = bedLimits();
                const L = Math.min(rl, bl), W = Math.min(rw, bw);

                S.L = L; S.W = W; $('sl').value = L; $('sw').value = W;
                const [uw, uh] = usableVB();
                const n = Math.min(uw / L, uh / W), nx = (VB.w - L * n) / 2, ny = (VB.h - W * n) / 2;
                it.forEach(a => {
                    a.o.w = S.p.l * n; a.o.h = S.p.w * n;
                    a.o.x = nx + (S.g + (a.x + a.m.w / 2) * f / 10) * n - a.o.w / 2;
                    a.o.y = ny + (S.g + (a.y + a.m.h / 2) * f / 10) * n - a.o.h / 2;
                });
                S.sc0 = n; S.sx0 = nx; S.sy0 = ny; S.touched = true;
                // Susunan sudah bukan hasil auto nesting lagi; peringatan target up
                // dihitung ulang dari object yang kini benar-benar di dalam canvas.
                S.autoNestInfo = null;
                noteShortfall(S.objs.filter(inCanvas).length);

                // Plano Configuration ikut canvas baru. Plano custom (diketik
                // officer sendiri) tidak ditimpa.
                const planoInfo = planos.includes(S.pl) ? pickBestPlano() : 'plano custom ' + S.pl[0] + ' tidak diubah';

                const use = (S.objs.length * S.p.l * S.p.w / (L * W) * 100).toFixed(1);
                $('suggestNote').textContent = (rl > L || rw > W)
                    ? 'Susunan ' + S.objs.length + ' object butuh ' + rl + ' × ' + rw + ' cm — melebihi meja ' + bedOwner() + ' (' + bl + ' × ' + bw + ' cm). Canvas dipotong ke ' + L + ' × ' + W + ' cm, object di luar batas ditandai merah.'
                    : 'Canvas dipaskan ke susunan: ' + S.objs.length + ' object dirapatkan, gap ' + S.g + ' cm antar-object & keliling = ' + L + ' × ' + W + ' cm · pemakaian area ' + use + '%'
                      + ' · ' + planoInfo + ' (' + cut(S.pl).n + ' canvas/plano).';
                update(false);
                msg('Suggestion: canvas ' + L + ' × ' + W + ' cm · ' + planoInfo + '.', 'ok');
                checkManualOverlap();
            }

            // Suggest saat susunan sudah disentuh user: canvas dihitung ulang dari
            // bounding box susunan + bleed (TIDAK me-reset nesting manual).
            function fitToLayout() {
                const [uw, uh] = usableVB();
                const sc = Math.min(uw / S.L, uh / S.W), sx = (VB.w - S.L * sc) / 2, sy = (VB.h - S.W * sc) / 2, m = S.p.bleed,
                    b = S.objs.map(o => { const q = obox(o); return { x: (q.x - sx) / sc, y: (q.y - sy) / sc, X: (q.x + q.w - sx) / sc, Y: (q.y + q.h - sy) / sc }; }),
                    minx = Math.min(...b.map(v => v.x)), miny = Math.min(...b.map(v => v.y)),
                    maxx = Math.max(...b.map(v => v.X)), maxy = Math.max(...b.map(v => v.Y)),
                    cen = S.objs.map(o => ({ o, cx: (o.x + o.w / 2 - sx) / sc - minx + m, cy: (o.y + o.h / 2 - sy) / sc - miny + m })),
                    rl = +(maxx - minx + 2 * m).toFixed(1), rw = +(maxy - miny + 2 * m).toFixed(1),
                    L = Math.min(rl, S.m[1]), W = Math.min(rw, S.m[2]),
                    area = S.objs.length * S.p.l * S.p.w, use = (area / (L * W) * 100).toFixed(1);

                S.L = L; S.W = W; $('sl').value = L; $('sw').value = W;
                const n = Math.min(uw / L, uh / W), nx = (VB.w - L * n) / 2, ny = (VB.h - W * n) / 2, k = n / sc;
                cen.forEach(r => { r.o.w *= k; r.o.h *= k; r.o.x = nx + r.cx * n - r.o.w / 2; r.o.y = ny + r.cy * n - r.o.h / 2; });
                S.sc0 = n; S.sx0 = nx; S.sy0 = ny;
                $('suggestNote').textContent = (rl > L || rw > W)
                    ? 'Susunan custom butuh ' + rl + ' × ' + rw + ' cm — melebihi bed ' + S.m[0] + ' (' + S.m[1] + ' × ' + S.m[2] + '). Canvas dipotong ke ' + L + ' × ' + W + ' cm, object di luar batas ditandai merah.'
                    : 'Custom nesting: bounding box ' + S.objs.length + ' object + bleed ' + m + ' cm/sisi = ' + L + ' × ' + W + ' cm · pemakaian area ' + use + '%.';
                update(false);
            }

            function suggest() {
                if (S.touched && S.objs.length) { fitToLayout(); return; }
                const target = S.auto ? null : S.manualUp, p = S.p;
                // Batas pencarian = meja mesin cetak DAN meja plong, bukan bed
                // mesin cetak saja -- canvas yang muat di press tapi tidak muat
                // di plong tetap tidak bisa diproduksi.
                const m = bedLimits();
                let bestC = null;
                for (let L = 25; L <= m[0]; L += .5) for (let W = 20; W <= m[1]; W += .5) {
                    const a = fit(L, W, p.l, p.w, S.g), b = fit(L, W, p.w, p.l, S.g), f = b.ups > a.ups ? b : a;
                    if (target ? f.ups >= target : f.ups > 0) {
                        const area = L * W, h = Math.abs(L - p.history[0]) + Math.abs(W - p.history[1]),
                            score = target ? area + h * .15 : -(f.ups / area) + h * .002;
                        if (!bestC || score < bestC.score) bestC = { L, W, score };
                    }
                }
                if (!bestC) {
                    update(true);
                    msg('Target ' + target + ' up tidak muat: butuh canvas melebihi meja ' + bedOwner()
                        + ' (maks ' + m[0] + ' × ' + m[1] + ' cm).', 'bad');
                    return;
                }
                S.L = +bestC.L.toFixed(1); S.W = +bestC.W.toFixed(1);
                $('sl').value = S.L; $('sw').value = S.W; S.objs = [];
                $('suggestNote').textContent = S.auto
                    ? 'Auto Up: canvas disarankan dari history job, packing efficiency, dan machine bed.'
                    : 'Manual ' + target + '-up: canvas minimum yang dapat memuat jumlah object tersebut adalah ' + S.L + ' × ' + S.W + ' cm.';
                update(true);
            }

            function update(reset) {
                metrics();
                $('machineInfo').innerHTML = '<b>' + S.m[0] + '</b><br>Maximum printable bed: <b>' + S.m[1] + ' × ' + S.m[2] + ' cm</b>'
                    + (S.m[3] ? '<br><span style="color:#ffd9a1">⚠ max_paper_size belum diisi di Master Mesin — angka di atas asumsi.</span>' : '');
                $('diecutInfo').innerHTML = !S.d
                    ? '<span style="color:#ffd9a1">⚠ Belum ada mesin plong aktif di Master Mesin.</span>'
                    : '<b>' + S.d[0] + '</b><br>' + (S.d[1] && S.d[2]
                        ? 'Maksimum meja plong: <b>' + S.d[1] + ' × ' + S.d[2] + ' cm</b>'
                        : '<span style="color:#ffd9a1">⚠ Ukuran meja belum diisi di Master Mesin — batas plong belum bisa ditegakkan, canvas baru dibatasi bed mesin cetak saja.</span>');
                if (reset) S.objs = [];
                render();
                calculator();
            }

            function metrics() {
                const p = S.p;
                $('metrics').innerHTML =
                    '<div class="io-metric"><span>Finished L × W × H</span><b>' + p.fl + ' × ' + p.fw + ' × ' + p.fh + ' cm</b></div>' +
                    '<div class="io-metric"><span>Dieline / blank</span><b>' + p.l + ' × ' + p.w + ' cm</b></div>' +
                    '<div class="io-metric"><span>Blank area</span><b>' + (p.l * p.w).toFixed(1) + ' cm²</b></div>' +
                    '<div class="io-metric"><span>Bleed / Gap</span><b>' + p.bleed + ' / ' + p.gap + ' cm</b></div>';
                $('spec').innerHTML = '<table>' +
                    '<tr><td>Customer</td><td>' + p.customer + '</td></tr>' +
                    '<tr><td>Design No.</td><td>' + p.design + '</td></tr>' +
                    '<tr><td>Category</td><td>' + p.category + '</td></tr>' +
                    '<tr><td>Structure</td><td>' + p.structure + '</td></tr>' +
                    '<tr><td>Material</td><td>' + p.material + '</td></tr>' +
                    '<tr><td>Artwork</td><td>' + p.art + '</td></tr>' +
                    '<tr><td>Reg / Note</td><td>' + p.reg + '</td></tr></table>';
            }

            function render() {
                if (!ok()) {
                    const [bl, bw] = bedLimits();
                    const pembatas = (S.d && S.d[1] && S.d[2] && (bl < S.m[1] || bw < S.m[2])) ? S.d[0] + ' (plong)' : S.m[0];
                    $('arts').innerHTML = ''; $('grainG').innerHTML = '';
                    $('sheetLabel').textContent = 'CANVAS ' + S.L + ' × ' + S.W + ' cm — DI LUAR BATAS MESIN';
                    $('warning').innerHTML = '⚠ Canvas ' + S.L + ' × ' + S.W + ' cm melebihi meja ' + pembatas +
                        '<br>Maksimum ' + bl + ' × ' + bw + ' cm — ' + S.objs.length + ' object disembunyikan';
                    $('warning').classList.add('show');
                    msg('Invalid: canvas tidak boleh melebihi ' + bl + ' × ' + bw + ' cm (batas ' + pembatas + ').', 'bad');
                    $('status').textContent = 'Validation failed';
                    return;
                }
                const [uw, uh] = usableVB();
                const sc = Math.min(uw / S.L, uh / S.W), w = S.L * sc, h = S.W * sc, x = (VB.w - w) / 2, y = (VB.h - h) / 2;
                if (S.objs.length && S.sc0 && (S.sc0 !== sc || S.sx0 !== x || S.sy0 !== y)) {
                    const k = sc / S.sc0;
                    S.objs.forEach(o => {
                        const cx = (o.x + o.w / 2 - S.sx0) / S.sc0, cy = (o.y + o.h / 2 - S.sy0) / S.sc0;
                        o.w *= k; o.h *= k; o.x = x + cx * sc - o.w / 2; o.y = y + cy * sc - o.h / 2;
                    });
                }
                S.sc0 = sc; S.sx0 = x; S.sy0 = y;
                [['sheet', 'x', x], ['sheet', 'y', y], ['sheet', 'width', w], ['sheet', 'height', h],
                 ['safeRect', 'x', x + 4], ['safeRect', 'y', y + 4], ['safeRect', 'width', w - 8], ['safeRect', 'height', h - 8]]
                    .forEach(a => $(a[0]).setAttribute(a[1], a[2]));
                $('sheetLabel').textContent = 'CANVAS / PRINT SHEET: ' + S.L + ' × ' + S.W + ' cm';
                $('grainG').innerHTML = grain(x, y, w, h);
                if (!S.objs.length) createObjects(x, y, sc);
                arts(x, y, w, h);
                if (S.auto && S.objs.length) {
                    // Angka up = HASIL, jadi ditulis ke panel hasil. S.manualUp ikut
                    // disamakan supaya begitu user pindah ke mode Manual, titik awalnya
                    // angka nyata -- bukan angka bawaan mockup (dulu selalu 6).
                    S.manualUp = S.objs.length;
                    $('manualUp').value = S.objs.length;
                    $('autoResultValue').textContent = S.objs.length + ' up';
                    $('autoResultNote').textContent = S.autoNestInfo
                        ? S.autoNestInfo
                        : 'Grid otomatis dari ukuran canvas & bentuk blank. Tekan ✦ Auto Nesting untuk coba susunan interlock.';
                }
                msg('Valid canvas. Drag setiap object secara independen untuk manual nesting berdasarkan bentuk dieline.', 'ok');
                $('status').textContent = S.p.category + ' · ' + S.objs.length + ' object(s) · ' +
                    (S.auto ? 'Auto Up' : 'Manual Up') + ' · ' + (S.selected ? 'selected: ' + S.selected.toUpperCase() : 'no selection');
            }

            function grain(x, y, w, h) {
                const d = $('grain').value;
                if (d === 'none') return '';
                return d === 'L'
                    ? '<line class="io-grain" x1="' + (x + 16) + '" y1="' + (y + h - 18) + '" x2="' + (x + 16) + '" y2="' + (y + 18) + '"/>' +
                      '<text class="io-graintext" x="' + (x + 23) + '" y="' + (y + 30) + '">GRAIN · DIELINE LENGTH</text>'
                    : '<line class="io-grain" x1="' + (x + 18) + '" y1="' + (y + 16) + '" x2="' + (x + w - 18) + '" y2="' + (y + 16) + '"/>' +
                      '<text class="io-graintext" x="' + (x + 32) + '" y="' + (y + 30) + '">GRAIN · DIELINE WIDTH</text>';
            }

            // Target up yang tidak tercapai HARUS diberitahukan (user 2026-09-17:
            // "kalau dengan jumlah up ukurannya melebihi meja mesin, harus di
            // warning"). Tanpa ini layar diam-diam memangkas jumlahnya dan angka
            // yield di panel kanan terlihat wajar padahal bukan yang diminta.
            // Canvas sudah dibatasi meja mesin cetak & meja plong (bedLimits),
            // jadi "tidak muat di canvas" = tidak muat di mesin.
            function noteShortfall(tercapai) {
                if (S.auto || S.manualUp <= tercapai) { S.upShort = null; return; }

                // Dibedakan: "canvas sekarang kekecilan" (masih bisa diperbesar)
                // vs "memang tidak muat di mesin" (canvas terbesar yang diizinkan
                // meja pun tidak cukup) -- dua hal itu butuh tindakan berbeda.
                const [bl, bw] = bedLimits();
                const a = fit(bl, bw, S.p.l, S.p.w, S.g), b = fit(bl, bw, S.p.w, S.p.l, S.g);

                S.upShort = { target: S.manualUp, dapat: tercapai, maxMesin: Math.max(a.ups, b.ups) };
            }

            function createObjects(x, y, sc) {
                // Grid otomatis dibangun ulang = hasil auto nesting sebelumnya sudah
                // tidak berlaku, labelnya harus ikut hilang (jangan jadi klaim basi).
                S.touched = false;
                S.manualEdit = false;
                S.autoNestInfo = null;
                S.overlapPx = 0;
                const f = best();
                // Manual Up: SEMUA object yang diminta officer ditempatkan, walau
                // sebagian keluar canvas -- canvas ini memang dipakai untuk
                // menggeser object sendiri (instruksi user 2026-09-18). Auto Up
                // tetap memakai jumlah yang benar-benar muat.
                const n = S.auto ? Math.min(selectedUp(), f.ups) : Math.max(1, Math.round(S.manualUp) || 1);
                const cols = Math.max(1, f.c);
                noteShortfall(Math.min(n, f.ups));

                for (let i = 0; i < n; i++) {
                    const r = Math.floor(i / cols), c = i % cols;
                    const ww = (f.rot ? S.p.w : S.p.l) * sc, hh = (f.rot ? S.p.l : S.p.w) * sc;
                    S.objs.push({
                        id: 'a' + i,
                        x: x + c * ((f.rot ? S.p.w : S.p.l) + S.g) * sc,
                        y: y + r * ((f.rot ? S.p.l : S.p.w) + S.g) * sc,
                        w: ww, h: hh, r: 0, fx: 1, fy: 1,
                    });
                }
            }

            // Mode Manual Up: jumlah object di canvas HARUS sama dengan target
            // officer. Hasil auto nesting yang cuma memuat sebagian tetap dipakai,
            // sisanya ditaruh di ruang kosong SAMPING canvas yang terlihat di layar
            // (dulu di bawah canvas -> jatuh di luar bidang gambar, tak terlihat),
            // supaya tinggal digeser ke tempat yang benar.
            function ensureManualCount() {
                if (S.auto || !S.sc0) return;

                const target = Math.max(1, Math.round(S.manualUp) || 1);

                if (S.objs.length > target) {
                    S.objs = S.objs.slice(0, target);
                    if (S.selected && !S.objs.some(o => o.id === S.selected)) S.selected = null;
                    return;
                }

                const sc = S.sc0, w = S.p.l * sc, h = S.p.w * sc, gap = Math.max(4, S.g * sc);
                const right = S.sx0 + S.L * sc + gap;
                // Kolom samping yang terlihat: [kiri kolom, lebar tersedia]
                const cols = [[right, VB.w - right - 4], [4, S.sx0 - gap - 4]].filter(c => c[1] >= Math.min(w, h));
                let seq = S.objs.length, ci = 0, cy = Math.max(4, S.sy0), colX = cols.length ? cols[0][0] : 0, k = 0;

                while (S.objs.length < target) {
                    let o;
                    if (ci < cols.length) {
                        const [cx0, room] = cols[ci];
                        const rot = w > room;                        // tidak muat melintang -> putar 90°
                        const bw = rot ? h : w, bh = rot ? w : h;
                        if (cy + bh > VB.h - 4 && cy > Math.max(4, S.sy0)) {
                            // kolom penuh -> kolom berikutnya
                            colX += bw + gap;
                            if (colX + bw > cx0 + room) { ci++; colX = ci < cols.length ? cols[ci][0] : 0; }
                            cy = Math.max(4, S.sy0);
                            continue;
                        }
                        // kolom kiri diisi dari tepi canvas ke arah luar
                        const left = ci === 1 ? cx0 + room - bw - (colX - cx0) : colX;
                        o = { x: left + bw / 2 - w / 2, y: cy + bh / 2 - h / 2, r: rot ? 90 : 0 };
                        cy += bh + gap;
                    } else {
                        o = { x: S.sx0 + (k % Math.max(1, Math.floor(S.L * sc / (w + gap)))) * (w + gap),
                              y: S.sy0 + S.W * sc + gap + Math.floor(k / Math.max(1, Math.floor(S.L * sc / (w + gap)))) * (h + gap), r: 0 };
                        k++;
                    }
                    S.objs.push({ id: 'a' + (seq++), x: o.x, y: o.y, w, h, r: o.r, fx: 1, fy: 1 });
                }
            }

            // Artwork job NYATA: pakai diecut asli dari DSL dieline_spec
            // (resources/js/tmdieline.js, renderer yang sama dengan layar
            // Inquiry Product) -- bukan kotak polos, kecuali tipe produknya
            // memang belum punya elements DSL.
            function realShape(p, w, h) {
                let out = '<rect class="io-artfill" width="' + w + '" height="' + h + '" rx="1"/>';
                // Artwork asli (iqp_part.artwork_path) jadi permukaan cetak, garis
                // dieline digambar DI ATASNYA supaya batas potong tetap terbaca.
                if (p.artworkUrl) {
                    out += '<image href="' + p.artworkUrl + '" x="0" y="0" width="' + w + '" height="' + h + '" preserveAspectRatio="none"/>';
                }
                let inner = null;
                if (p.dieline && window.TMDieline) {
                    try { inner = window.TMDieline.svg(p.dieline, p.dielineDims || {}); } catch (e) { inner = null; }
                }
                if (inner) {
                    return out + inner
                        .replace('width="100%" height="100%"', 'x="0" y="0" width="' + w + '" height="' + h + '" preserveAspectRatio="none"')
                        .replace(/stroke-width="1"/g, 'stroke-width="1.9" vector-effect="non-scaling-stroke"');
                }
                return out + '<rect class="io-die" x="' + (w * .012) + '" y="' + (h * .018) + '" width="' + (w * .976) + '" height="' + (h * .964) + '"/>' +
                    '<text class="io-lbl" x="' + (w * .5) + '" y="' + (h * .52) + '" text-anchor="middle">' + (p.design || '') + '</text>';
            }

            function shape(o) {
                const w = o.w, h = o.h, f = Math.min(w, h);
                const cut = '<path class="io-cutmark" d="M-5 5H0M-5 ' + (h - 5) + 'H0M' + w + ' 5H' + (w + 5) + 'M' + w + ' ' + (h - 5) + 'H' + (w + 5) + '"/>';
                // Sampel UAT punya artwork vector bawaan (type terisi) -- itu yang
                // dipakai, karena struktur Tray Lid/2-Piece Wrap/Lock Bottom belum
                // ada di pustaka dieline DSL. Job lain pakai diecut asli dari DSL.
                if (!S.p.type) return realShape(S.p, w, h) + cut;

                if (S.p.type === 'alsamsi_top') {
                    const x1 = w * .185, x2 = w * .815, y1 = h * .22, y2 = h * .78, n = f * .11;
                    return '<path class="io-artfill" d="M' + n + ' 0H' + (w - n) + 'L' + w + ' ' + n + 'V' + (h - n) + 'L' + (w - n) + ' ' + h + 'H' + n + 'L0 ' + (h - n) + 'V' + n + 'Z" style="fill:#fbfcff"/>' +
                        '<rect x="' + x1 + '" y="' + y1 + '" width="' + (x2 - x1) + '" height="' + (y2 - y1) + '" fill="#17111a"/>' +
                        '<g fill="#413a4d"><circle cx="' + (w * .33) + '" cy="' + (h * .35) + '" r="' + (f * .075) + '"/><circle cx="' + (w * .67) + '" cy="' + (h * .65) + '" r="' + (f * .075) + '"/><circle cx="' + (w * .5) + '" cy="' + (h * .5) + '" r="' + (f * .05) + '"/><circle cx="' + (w * .33) + '" cy="' + (h * .66) + '" r="' + (f * .045) + '"/><circle cx="' + (w * .67) + '" cy="' + (h * .34) + '" r="' + (f * .045) + '"/></g>' +
                        '<rect x="' + x1 + '" y="' + y1 + '" width="' + (w * .022) + '" height="' + (y2 - y1) + '" fill="#c89b40"/><rect x="' + (x2 - w * .022) + '" y="' + y1 + '" width="' + (w * .022) + '" height="' + (y2 - y1) + '" fill="#c89b40"/>' +
                        '<rect x="' + (w * .36) + '" y="' + (h * .45) + '" width="' + (w * .28) + '" height="' + (h * .1) + '" fill="#0b0910" stroke="#c89b40" stroke-width=".7"/>' +
                        '<text class="io-lbl" x="' + (w * .5) + '" y="' + (h * .515) + '" text-anchor="middle" fill="#eacf94">AL SAMSI</text>' +
                        '<g fill="#0a0a12" stroke="#c89b40" stroke-width=".7">' +
                        '<rect x="' + (w * .055) + '" y="' + (h * .415) + '" width="' + (f * .2) + '" height="' + (f * .2) + '" transform="rotate(45 ' + (w * .055 + f * .1) + ' ' + (h * .415 + f * .1) + ')"/>' +
                        '<rect x="' + (w * .945 - f * .2) + '" y="' + (h * .415) + '" width="' + (f * .2) + '" height="' + (f * .2) + '" transform="rotate(45 ' + (w * .945 - f * .1) + ' ' + (h * .415 + f * .1) + ')"/></g>' +
                        '<text class="io-lbl" x="' + (w * .055 + f * .1) + '" y="' + (h * .53) + '" text-anchor="middle" fill="#3fa9e8">ALSAMSI</text>' +
                        '<text class="io-lbl" x="' + (w * .945 - f * .1) + '" y="' + (h * .53) + '" text-anchor="middle" fill="#3fa9e8">ALSAMSI</text>' +
                        '<text class="io-lbl" x="' + (w * .5) + '" y="' + (h * .13) + '" text-anchor="middle" fill="#6d5a2c">EXCLUSIVE · SARUNG TENUN</text>' +
                        // Hang tab (struktur: Tray Lid + Hang Tab) -- lubang gantung ikut dipotong.
                        '<circle class="io-die" cx="' + (w * .5) + '" cy="' + (h * .105) + '" r="' + (f * .045) + '"/>' +
                        '<path class="io-fold" d="M' + x1 + ' 0V' + h + 'M' + x2 + ' 0V' + h + 'M0 ' + y1 + 'H' + w + 'M0 ' + y2 + 'H' + w + '"/>' +
                        // 4 corner lock: sudut tray dipotong diagonal, bukan lurus.
                        '<path class="io-die" d="M' + x1 + ' ' + y1 + 'L0 ' + y1 + 'M' + x2 + ' ' + y1 + 'L' + w + ' ' + y1 + 'M' + x1 + ' ' + y2 + 'L0 ' + y2 + 'M' + x2 + ' ' + y2 + 'L' + w + ' ' + y2 + '"/>' +
                        '<path class="io-die" d="M' + n + ' ' + (h * .03) + 'H' + (w - n) + 'L' + (w * .97) + ' ' + n + 'V' + (h - n) + 'L' + (w - n) + ' ' + (h * .97) + 'H' + n + 'L' + (w * .03) + ' ' + (h - n) + 'V' + n + 'Z"/>' + cut;
                }

                if (S.p.type === 'osima') {
                    const fy = h * .27, gx = w * .83;
                    return '<rect class="io-artfill" width="' + w + '" height="' + h + '" rx="1" style="fill:#e9edf2"/>' +
                        '<rect width="' + gx + '" height="' + fy + '" fill="#1d2f56"/>' +
                        '<rect x="' + (w * .28) + '" y="' + (fy * .3) + '" width="' + (w * .11) + '" height="' + (fy * .42) + '" rx="2" fill="#fff"/>' +
                        '<rect x="' + (w * .52) + '" y="' + (fy * .16) + '" width="' + (w * .23) + '" height="' + (fy * .66) + '" rx="1" fill="#fff" opacity=".92"/>' +
                        '<text class="io-lbl" x="' + (w * .05) + '" y="' + (fy * .55) + '" fill="#fff">OSIMA MS · PREMIUM</text>' +
                        '<path d="M0 ' + fy + 'H' + (w * .27) + 'L' + (w * .11) + ' ' + h + 'H0Z" fill="#1d2f56"/>' +
                        '<path d="M' + (w * .27) + ' ' + fy + 'H' + gx + 'V' + h + 'H' + (w * .11) + 'Z" fill="#dde3ea"/>' +
                        '<rect x="' + (w * .40) + '" y="' + (h * .42) + '" width="' + (w * .11) + '" height="' + (h * .17) + '" rx="2" fill="#fff"/>' +
                        '<text x="' + (w * .22) + '" y="' + (h * .72) + '" style="font:800 ' + (f * .155) + 'px Inter,sans-serif;fill:#1d2f56">OSIMA</text>' +
                        '<text x="' + (w * .23) + '" y="' + (h * .83) + '" style="font:700 ' + (f * .08) + 'px Inter,sans-serif;fill:#1d2f56;letter-spacing:.08em">PREMIUM</text>' +
                        '<text x="' + (w * .22) + '" y="' + (h * .4) + '" style="font:800 ' + (f * .07) + 'px Inter,sans-serif;fill:#e2338c">MS</text>' +
                        '<ellipse cx="' + (w * .67) + '" cy="' + (h * .88) + '" rx="' + (f * .06) + '" ry="' + (f * .04) + '" fill="#d8342b"/>' +
                        '<text x="' + (w * .78) + '" y="' + (h * .62) + '" transform="rotate(-90 ' + (w * .78) + ' ' + (h * .62) + ')" style="font:700 ' + (f * .055) + 'px Inter,sans-serif;fill:#1d2f56">Japanese Technology</text>' +
                        // Hand hole (struktur: 2-Piece Wrap + Hand Hole) -- lubang jinjing.
                        '<rect class="io-die" x="' + (w * .44) + '" y="' + (h * .12) + '" width="' + (w * .12) + '" height="' + (h * .05) + '" rx="' + (h * .025) + '"/>' +
                        '<path class="io-fold" d="M0 ' + fy + 'H' + w + 'M' + gx + ' 0V' + h + 'M' + (w * .27) + ' ' + fy + 'V' + h + '"/>' +
                        // Glue flap panel B di sisi kanan (lebar gx..w) = area lem.
                        '<path class="io-die" d="M' + gx + ' ' + (h * .018) + 'V' + (h * .982) + '"/>' +
                        '<rect class="io-die" x="' + (w * .012) + '" y="' + (h * .018) + '" width="' + (w * .976) + '" height="' + (h * .964) + '"/>' + cut;
                }

                const x1 = w * .16, x2 = w * .62, x3 = w * .82, y1 = h * .62;
                return '<path class="io-artfill" d="M' + x1 + ' 0H' + w + 'V' + h + 'H' + x1 + 'L0 ' + (h * .78) + 'V' + (h * .22) + 'Z" style="fill:#efe7d2"/>' +
                    '<rect x="' + x1 + '" y="0" width="' + (x2 - x1) + '" height="' + y1 + '" fill="#f4eedd"/>' +
                    '<path d="M' + x1 + ' ' + (h * .06) + 'q' + (w * .18) + ' ' + (h * .2) + ' ' + (w * .12) + ' ' + (h * .5) + 'h' + (w * .07) + 'q' + (w * .05) + '-' + (h * .34) + '-' + (w * .09) + '-' + (h * .5) + 'z" fill="#8b6535" opacity=".85"/>' +
                    '<rect x="' + (w * .3) + '" y="' + (h * .12) + '" width="' + (w * .2) + '" height="' + (h * .22) + '" fill="#c99a3d" stroke="#7d5c1c" stroke-width=".7"/>' +
                    '<text class="io-lbl" x="' + (w * .4) + '" y="' + (h * .24) + '" text-anchor="middle" fill="#2a1c05">AL SAMSI</text>' +
                    '<text class="io-lbl" x="' + (w * .4) + '" y="' + (h * .42) + '" text-anchor="middle" fill="#6b5522">Sarung Tenun · Signature</text>' +
                    '<g fill="#fff" stroke="#6f6247" stroke-width=".6">' + [0, 1, 2, 3].map(i =>
                        '<rect x="' + (x2 + (x3 - x2) * .3) + '" y="' + (h * (.07 + i * .13)) + '" width="' + ((x3 - x2) * .4) + '" height="' + (h * .09) + '" rx="2"/>').join('') + '</g>' +
                    '<rect x="' + x3 + '" y="0" width="' + (w - x3) + '" height="' + h + '" fill="#e6dcc2"/>' +
                    '<g fill="#9a8348">' + [0, 1, 2, 3, 4, 5, 6, 7].map(i =>
                        '<rect x="' + (x3 + (w - x3) * .2) + '" y="' + (h * (.04 + i * .12)) + '" width="' + ((w - x3) * .6) + '" height="' + (h * .02) + '"/>').join('') + '</g>' +
                    '<rect x="' + (w * .21) + '" y="' + (h * .7) + '" width="' + (w * .16) + '" height="' + (h * .22) + '" fill="none" stroke="#4a4130" stroke-width=".7"/>' +
                    '<path d="M' + (w * .21) + ' ' + (h * .775) + 'h' + (w * .16) + 'M' + (w * .21) + ' ' + (h * .85) + 'h' + (w * .16) + '" stroke="#4a4130" stroke-width=".5"/>' +
                    '<text class="io-lbl" x="' + (w * .29) + '" y="' + (h * .752) + '" text-anchor="middle" fill="#3b3325">CODE / DESIGN</text>' +
                    '<text class="io-lbl" x="' + (w * .29) + '" y="' + (h * .827) + '" text-anchor="middle" fill="#3b3325">CONTENT: 10 PCS</text>' +
                    '<rect x="' + (w * .45) + '" y="' + (h * .72) + '" width="' + (w * .13) + '" height="' + (h * .16) + '" fill="#c99a3d" stroke="#7d5c1c" stroke-width=".6"/>' +
                    '<text class="io-lbl" x="' + (w * .515) + '" y="' + (h * .81) + '" text-anchor="middle" fill="#2a1c05">AL SAMSI</text>' +
                    '<path class="io-fold" d="M' + x1 + ' 0V' + h + 'M' + x2 + ' 0V' + h + 'M' + x3 + ' 0V' + h + 'M' + x1 + ' ' + y1 + 'H' + w + '"/>' +
                    // Lock bottom: lidah pengunci dasar dipotong di bawah garis y1.
                    '<path class="io-die" d="M' + x1 + ' ' + y1 + 'H' + w + 'M' + ((x1 + x2) / 2) + ' ' + y1 + 'V' + (h * .98) + 'M' + ((x2 + x3) / 2) + ' ' + y1 + 'V' + (h * .98) + '"/>' +
                    '<path class="io-die" d="M' + x1 + ' ' + (h * .02) + 'H' + (w * .985) + 'V' + (h * .98) + 'H' + x1 + 'L' + (w * .015) + ' ' + (h * .77) + 'V' + (h * .23) + 'Z"/>' + cut;
            }

            function arts(sx, sy, sw, sh) {
                const g = $('arts');
                g.innerHTML = '';
                const isOut = o => {
                    const b = obox(o);
                    return b.x < sx - .6 || b.y < sy - .6 || b.x + b.w > sx + sw + .6 || b.y + b.h > sy + sh + .6;
                };
                S.objs.forEach(o => {
                    const out = isOut(o), el = document.createElementNS('http://www.w3.org/2000/svg', 'g'),
                        cx = o.x + o.w / 2, cy = o.y + o.h / 2;
                    el.dataset.id = o.id;
                    el.setAttribute('class', 'io-art ' + (out ? 'io-out ' : '') + (S.selected === o.id ? 'selected' : ''));
                    el.setAttribute('transform', 'translate(' + cx + ' ' + cy + ') rotate(' + o.r + ') scale(' + o.fx + ' ' + o.fy + ') translate(' + (-o.w / 2) + ' ' + (-o.h / 2) + ')');
                    el.innerHTML = shape(o);
                    el.addEventListener('pointerdown', start);
                    g.appendChild(el);
                });
                $('warning').innerHTML = '⚠ Artwork melewati batas canvas / print sheet';
                $('warning').classList.toggle('show', S.objs.some(isOut));
            }

            function start(e) {
                e.preventDefault(); e.stopPropagation();
                const o = S.objs.find(q => q.id === e.currentTarget.dataset.id);
                if (!o) return;
                S.selected = o.id;
                const p = point(e);
                S.drag = { id: o.id, sx: p.x, sy: p.y, ox: o.x, oy: o.y };
                app.classList.add('dragging');
                document.addEventListener('pointermove', move);
                document.addEventListener('pointerup', end);
                document.addEventListener('pointercancel', end);
                render();
            }
            function point(e) {
                const r = $('svg').getBoundingClientRect();
                return { x: (e.clientX - r.left) * VB.w / r.width, y: (e.clientY - r.top) * VB.h / r.height };
            }
            function move(e) {
                if (!S.drag) return;
                e.preventDefault();
                const o = S.objs.find(q => q.id === S.drag.id), p = point(e);
                o.x = S.drag.ox + p.x - S.drag.sx; o.y = S.drag.oy + p.y - S.drag.sy;
                S.touched = true; S.manualEdit = true;
                render();
            }
            function end() {
                S.drag = null;
                app.classList.remove('dragging');
                document.removeEventListener('pointermove', move);
                document.removeEventListener('pointerup', end);
                document.removeEventListener('pointercancel', end);
                if (S.objs.length) noteShortfall(S.objs.filter(inCanvas).length);
                render(); calculator(); checkManualOverlap();
            }
            function transform(k, v) {
                const o = S.objs.find(x => x.id === S.selected);
                if (!o) { msg('Pilih artwork object pada canvas terlebih dahulu.', 'info'); return; }
                if (k === 'r') o.r = (o.r + v) % 360;
                else if (v === 'x') o.fx *= -1;
                else o.fy *= -1;
                S.touched = true; S.manualEdit = true;
                render(); calculator(); checkManualOverlap();
            }

            function cut(pl) {
                const n = Math.floor(pl[1] / S.L) * Math.floor(pl[2] / S.W),
                    r = Math.floor(pl[1] / S.W) * Math.floor(pl[2] / S.L), rot = r > n;
                return { n: Math.max(n, r), rot, util: Math.max(n, r) ? Math.max(n, r) * S.L * S.W / (pl[1] * pl[2]) : 0 };
            }

            function impo(c, up) {
                const p = S.p, A = p.l * p.w, ac = S.L * S.W, ap = S.pl[1] * S.pl[2],
                    cu = ac ? up * A / ac * 100 : 0, pu = c.util * 100, all = cu * pu / 100;
                $('yCanvas').textContent = cu.toFixed(1) + '%';
                $('yCanvasW').textContent = (100 - cu).toFixed(1) + '%';
                $('yPlano').textContent = pu.toFixed(1) + '%';
                $('yAll').textContent = all.toFixed(1) + '%';
                $('barFill').style.width = Math.max(0, Math.min(100, all)) + '%';
                $('barTxt').textContent = 'Material terpakai ' + all.toFixed(1) + '% · terbuang ' + (100 - all).toFixed(1) + '%';

                const sc = S.sc0 || Math.min(usableVB()[0] / S.L, usableVB()[1] / S.W), sx = S.sx0, sy = S.sy0, sw = S.L * sc, sh = S.W * sc,
                    b = S.objs.map(obox), rot = S.objs.filter(o => o.r % 360 !== 0).length,
                    mir = S.objs.filter(o => o.fx < 0 || o.fy < 0).length,
                    out = b.filter(v => v.x < sx - .6 || v.y < sy - .6 || v.x + v.w > sx + sw + .6 || v.y + v.h > sy + sh + .6).length,
                    bl = b.length ? ((Math.max(...b.map(v => v.x + v.w)) - Math.min(...b.map(v => v.x))) / sc).toFixed(1) : '—',
                    bw = b.length ? ((Math.max(...b.map(v => v.y + v.h)) - Math.min(...b.map(v => v.y))) / sc).toFixed(1) : '—',
                    g = best();

                // Tabrakan garis potong lebih gawat daripada object keluar canvas:
                // angka yield di panel ini TETAP kelihatan bagus (utilisasi malah
                // bisa >100%, dihitung dari kotak pembatas bukan bentuk asli), jadi
                // tanpa peringatan ini susunan yang tidak bisa dicetak terbaca
                // seolah paling efisien.
                const bentrok = S.overlapPx > 0;
                const kurang = S.upShort;
                $('impoMode').className = 'io-notice ' + (bentrok || out || kurang ? 'bad' : S.touched ? 'ok' : 'info');
                $('impoMode').innerHTML = (S.touched
                    ? '<b>' + (S.autoNestInfo ? 'AUTO NESTING — ' + S.autoNestInfo : 'CUSTOM IMPOSITION — nesting manual') + '</b><br>Susunan: ' + rot + ' object rotate, ' + mir + ' mirror' + (out ? ' · <b>' + out + ' di luar canvas (tidak terhitung sah)</b>' : '') + '.'
                    : '<b>AUTO GRID IMPOSITION</b><br>Susunan otomatis ' + g.c + ' kolom × ' + g.r + ' baris' + (g.rot ? ' (blank dirotate 90°)' : '')
                      + ' · mode ' + (S.auto ? 'Auto Up' : 'Manual Up') + (out ? ' · <b>' + out + ' object di luar canvas — geser manual</b>' : '') + '.')
                    + (bentrok ? '<br><b>⚠ GARIS POTONG BERTABRAKAN</b> — ' + S.overlapPx + ' mm² blank saling makan, susunan ini tidak bisa dicetak. Angka yield di bawah ikut tidak sah.' : '')
                    + (kurang
                        ? (kurang.target > kurang.maxMesin
                            ? '<br><b>⚠ TARGET ' + kurang.target + ' UP TIDAK MUAT DI MESIN</b> — blank ' + S.p.l + ' × ' + S.p.w
                              + ' cm sebanyak itu melebihi meja ' + bedOwner() + '. Canvas terbesar yang diizinkan ('
                              + bedLimits()[0] + ' × ' + bedLimits()[1] + ' cm) hanya memuat ' + kurang.maxMesin + ' up.'
                            : '<br><b>⚠ TARGET ' + kurang.target + ' UP BELUM MUAT DI CANVAS INI</b> — canvas ' + S.L + ' × ' + S.W
                              + ' cm baru memuat ' + kurang.dapat + ' up, sisanya ditempatkan DI LUAR canvas (tandai merah) untuk digeser manual. '
                              + 'Bisa juga diputar/di-mirror, perbesar canvas (meja ' + bedOwner() + ' masih mengizinkan sampai '
                              + bedLimits()[0] + ' × ' + bedLimits()[1] + ' cm), atau tekan ✦ Suggestion.')
                        : '');

                $('impoDetail').innerHTML = '<table>' +
                    '<tr><td>Object di canvas</td><td>' + up + ' up</td></tr>' +
                    '<tr><td>Bounding box susunan</td><td>' + bl + ' × ' + bw + ' cm</td></tr>' +
                    '<tr><td>Area blank total</td><td>' + (up * A).toFixed(0) + ' cm²</td></tr>' +
                    '<tr><td>Area canvas</td><td>' + ac.toFixed(0) + ' cm²</td></tr>' +
                    '<tr><td>Sisa canvas kosong</td><td>' + (ac - up * A).toFixed(0) + ' cm² (' + (100 - cu).toFixed(1) + '%)</td></tr>' +
                    '<tr><td>Canvas / plano</td><td>' + c.n + ' lembar</td></tr>' +
                    '<tr><td>Trim plano terbuang</td><td>' + (ap - c.n * ac).toFixed(0) + ' cm²</td></tr>' +
                    '<tr><td>Hasil / plano</td><td>' + (c.n * up) + ' pcs</td></tr></table>';

                const rows = planos.map(q => ({ q, c: cut(q) })).filter(x => x.c.n).sort((a, b2) => b2.c.util - a.c.util || b2.c.n - a.c.n);
                $('planoTable').innerHTML = rows.length
                    ? '<table class="rank"><tr><th>Plano</th><th>Cvs</th><th>Yield</th><th>Overall</th><th>Pcs</th></tr>' +
                      rows.map(r => '<tr class="' + (r.q[0] === S.pl[0] ? 'best' : '') + '"><td>' + r.q[0].replace(' cm', '') + '</td><td>' + r.c.n + '</td><td>' + (r.c.util * 100).toFixed(1) + '%</td><td>' + (cu * r.c.util).toFixed(1) + '%</td><td>' + (r.c.n * up) + '</td></tr>').join('') +
                      '</table>'
                    : '<table><tr><td>Tidak ada plano standar yang muat canvas ' + S.L + ' × ' + S.W + ' cm</td></tr></table>';
            }

            function calculator() {
                if (!ok()) return;
                const c = cut(S.pl), up = upValid(), val = +$('converterInput').value || 0;
                if (!c.n) {
                    // Angka & gambar lama jangan dibiarkan tampil -- dulu preview
                    // masih menunjukkan pembagian canvas SEBELUMNYA.
                    const bi = bestPlanoIndex();
                    $('planoNote').className = 'io-notice bad';
                    $('planoNote').innerHTML = 'Plano ' + esc(S.pl[0]) + ' tidak muat canvas ' + S.L + ' × ' + S.W + ' cm.'
                        + (bi >= 0 ? ' Pilih <b>' + planos[bi][0] + '</b> (' + (cut(planos[bi]).util * 100).toFixed(1) + '% sheet yield) atau tekan ✦ Suggestion.' : '');
                    $('mUp').textContent = up; $('mYield').textContent = 0; $('mPerPlano').textContent = 0; $('mWaste').textContent = '—';
                    $('resultValue').textContent = '—';
                    drawPlano(S.pl, c);
                    impo(c, up);
                    return;
                }
                const bi = bestPlanoIndex(), bp = { p: planos[bi], c: cut(planos[bi]) };
                $('planoNote').className = 'io-notice ok';
                $('planoNote').innerHTML = 'Recommended: <b>' + bp.p[0] + '</b> gives ' + bp.c.n + ' canvas(es) / plano (' +
                    (bp.c.util * 100).toFixed(1) + '% sheet yield, bahan per pcs paling hemat). ' + (S.pl[0] === bp.p[0] ? 'Selected.' : 'Manual plano selection active.');
                const perPlano = c.n * up, waste = (100 - c.util * 100).toFixed(1) + '%';
                $('mUp').textContent = up; $('mYield').textContent = c.n;
                $('mPerPlano').textContent = perPlano; $('mWaste').textContent = waste;
                if (S.conv === 'pcs') {
                    const print = Math.ceil(val / up), need = Math.ceil(print / c.n);
                    $('resultCaption').textContent = 'Plano required';
                    $('resultValue').textContent = need;
                    $('resultUnit').textContent = 'sheets';
                } else {
                    $('resultCaption').textContent = 'Production capacity';
                    $('resultValue').textContent = val * perPlano;
                    $('resultUnit').textContent = 'pcs';
                }
                drawPlano(S.pl, c);
                impo(c, up);
                specSoon();
            }

            // Pembagian 1 plano: tiap canvas bernomor + ukurannya, sisa trim
            // diarsir, ukuran plano di tepi. Label ditaruh DI LUAR kotak plano
            // (dulu tertimpa kotaknya sehingga tidak terbaca).
            function drawPlano(p, c) {
                const VW = 280, VH = 190, top = 14, left = 16, areaW = VW - left - 8, areaH = 140;
                const sc = Math.min(areaW / p[1], areaH / p[2]), W = p[1] * sc, H = p[2] * sc,
                    x = left + (areaW - W) / 2, y = top + (areaH - H) / 2,
                    cols = c.rot ? Math.floor(p[1] / S.W) : Math.floor(p[1] / S.L),
                    rows = c.rot ? Math.floor(p[2] / S.L) : Math.floor(p[2] / S.W),
                    cl = c.rot ? S.W : S.L, cwid = c.rot ? S.L : S.W,
                    cw = cl * sc, ch = cwid * sc;
                const t = (tx, ty, str, extra) => '<text x="' + tx + '" y="' + ty + '" text-anchor="middle" ' + (extra || '') + '>' + str + '</text>';
                let s = '';
                for (let r = 0; r < rows; r++) for (let col = 0; col < cols; col++) {
                    const cx = x + col * cw, cy = y + r * ch;
                    s += '<rect x="' + cx + '" y="' + cy + '" width="' + cw + '" height="' + ch + '" fill="#73ddff" fill-opacity=".8" stroke="#0e5776"/>'
                        + t(cx + cw / 2, cy + ch / 2 - 4, 'CANVAS ' + (r * cols + col + 1), 'font-size="9" font-weight="700" fill="#063049"')
                        + t(cx + cw / 2, cy + ch / 2 + 8, fmtAngka(cl) + ' × ' + fmtAngka(cwid), 'font-size="8" fill="#063049"');
                }
                if (!c.n) s = t(x + W / 2, y + H / 2, 'Canvas ' + fmtAngka(S.L) + ' × ' + fmtAngka(S.W) + ' cm tidak muat', 'font-size="10" font-weight="700" fill="#c0392b"');
                const trim = p[1] * p[2] - c.n * S.L * S.W;
                $('cut').innerHTML = '<svg viewBox="0 0 ' + VW + ' ' + VH + '" width="100%" style="max-width:' + VW + 'px">'
                    + '<defs><pattern id="io-trimHatch" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">'
                    + '<rect width="6" height="6" fill="#dfe9ee"/><line x1="0" y1="0" x2="0" y2="6" stroke="#b7c7d1" stroke-width="2"/></pattern></defs>'
                    + '<rect x="' + x + '" y="' + y + '" width="' + W + '" height="' + H + '" fill="url(#io-trimHatch)" stroke="#8fa8b6"/>' + s
                    + t(x + W / 2, y - 4, fmtAngka(p[1]) + ' cm', 'font-size="9" fill="#abc6d5"')
                    + '<text x="' + (x - 4) + '" y="' + (y + H / 2) + '" text-anchor="middle" font-size="9" fill="#abc6d5" transform="rotate(-90 ' + (x - 4) + ' ' + (y + H / 2) + ')">' + fmtAngka(p[2]) + ' cm</text>'
                    + t(VW / 2, VH - 22, p[0] + ' → ' + c.n + ' canvas (' + cols + ' × ' + rows + (c.rot ? ', canvas diputar' : '') + ')', 'font-size="10" font-weight="700" fill="#dbeaf3"')
                    + t(VW / 2, VH - 8, 'Sheet yield ' + (c.util * 100).toFixed(1) + '% · trim terbuang ' + Math.max(0, trim).toFixed(0) + ' cm² (arsir)', 'font-size="9" fill="#abc6d5"')
                    + '</svg>';
            }

            init();
        })();
        </script>
        @endverbatim
    </div>
</div>
