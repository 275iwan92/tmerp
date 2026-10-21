<?php

namespace App\Services\ProDev;

use App\Models\Master\MatPlanoSize;
use App\Models\Master\MatRaw;
use App\Models\Master\ProdLayoutGroup;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdPart;
use App\Models\Sdi\IqpLayout;
use App\Models\Sdi\IqpPart;
use App\Models\Sys\SysConstant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/*
 * REMARK
 * Tujuan       : Aturan workbench ProDev (Imposition Plano Optimizer) untuk satu part: daftar job, mesin cetak/plong, plano, simpan/Confirm layout (prod_layout_group + iqp_layout), pratinjau BOM/Ink/BOP, cari bahan, PR bahan, subkon, warna artwork, unggah artwork.
 * Sumber hulu  : prod_part, iqp_part (asal Inquiry), prod_machine, mat_plano_size, sys_constant (PRODEV_*), SpecEngine, ProcurementBridge, ArtworkAnalyzer.
 * Input        : id part ProDev; state dari workbench.js (cm/persen apa adanya, dikonversi ke mm di sini).
 * Output       : prod_layout_group (+ pivot), iqp_layout (snapshot per Inquiry Part), tabel BOM/Ink/BOP lewat SpecEngine::persist, iqp_part.artwork_*.
 * Dipakai oleh : App\Support\V2\Data\Prodev (layar ProDev v2 dan rute v2.prodev.call), StageBuilder, MasterCardDummyEngine.
 * Status       : Aktif. Dipindahkan apa adanya dari komponen v1 pages::production.prodev (diarsipkan 2026-10-21, T10 tahap 5); perilaku tidak diubah.
 */
class ProdevWorkbenchService
{
    // Sampel UAT (mastercard_code -> nama artwork vector di JS). Barisnya data NYATA di prod_part (migration 2026_09_17_000002).
    private const SAMPLE_ARTWORK = [
        'UAT-ALSAMSI-TOP' => 'alsamsi_top',
        'UAT-OSIMA' => 'osima',
        'UAT-ALSAMSI-SIG' => 'alsamsi_sig',
    ];

    private ?array $jobsCache = null;

    public function __construct(public readonly int $partId) {}

    public function artworkMaxMb(): float
    {
        return SysConstant::number('PRODEV_UPLOAD_MAX_MB', 25);
    }

    // Upload artwork dari ProDev ditulis ke iqp_part.artwork_path (keputusan user 2026-09-14): jalur dan folder sama dengan
    // Inquiry Product (disk public, inquiry/{id_part}/artwork), jadi satu file dipakai bersama kedua layar.
    // Balikan: ['ok' => false, 'message'] atau ['ok' => true, partId, url, name, colors]. Validasi gagal = ValidationException.
    public function storeArtwork(UploadedFile $file): array
    {
        $iqpPart = IqpPart::where('prod_part_id', $this->partId)->first();

        if (! $iqpPart) {
            return ['ok' => false, 'message' => 'Job ini tidak punya Inquiry Part (Mode #2 manual), artwork belum bisa disimpan.'];
        }

        // PDF/AI/EPS ikut diterima sejak Imagick+Ghostscript dipasang (2026-09-18): file vektor dirasterisasi di server.
        Validator::make(['artworkUpload' => $file], ['artworkUpload' => 'file|max:'.($this->artworkMaxMb() * 1024)], [], ['artworkUpload' => 'artwork'])->validate();

        $ext = strtolower($file->getClientOriginalExtension());

        if (! in_array($ext, ['jpg', 'jpeg', 'png', ...ArtworkAnalyzer::VECTOR], true)) {
            return ['ok' => false, 'message' => 'Format artwork yang didukung: JPG, PNG, PDF, AI, EPS.'];
        }

        if (in_array($ext, ArtworkAnalyzer::VECTOR, true) && ! ArtworkAnalyzer::available()) {
            return ['ok' => false, 'message' => 'File vektor butuh Imagick + Ghostscript di server -- belum aktif.'];
        }

        $old = $iqpPart->artwork_path;
        $path = $file->store("inquiry/{$iqpPart->id_part}/artwork", 'public');

        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
            Storage::disk('public')->delete($old.'.preview.png');
        }

        $iqpPart->update(['artwork_path' => $path, 'artwork_original_filename' => $file->getClientOriginalName()]);

        // Analisa warna + pratinjau langsung di server (tak perlu browser).
        $abs = Storage::disk('public')->path($path);
        $previewAbs = in_array($ext, ArtworkAnalyzer::VECTOR, true) ? $abs.'.preview.png' : null;
        $analysis = app(ArtworkAnalyzer::class)->analyze($abs, $previewAbs);

        if ($analysis) {
            $iqpPart->update(['artwork_colors' => $analysis]);
        }

        $this->jobsCache = null;
        $iqp = IqpPart::where('prod_part_id', $this->partId)->first();
        $preview = Storage::disk('public')->exists($path.'.preview.png') ? $path.'.preview.png' : $path;

        return ['ok' => true, 'partId' => $this->partId, 'url' => asset('storage/'.$preview),
            'name' => $iqp->artwork_original_filename, 'colors' => $iqp->artwork_colors];
    }

    // Simpan (draft, bisa dipanggil berkali-kali) / Confirm (final, dikunci + dikirim ke Product Costing) hasil nesting.
    // $state datang dari JS (gatherState() workbench.js), dalam cm/persen apa adanya dari UI -- konversi ke mm dilakukan di sini,
    // supaya satuan yang tersimpan di DB konsisten dengan kolom lama (*_mm) tanpa tergantung JS.
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

        // Pisau = bentang seluruh susunan (bukan 1 blank). Nama mengikuti pola Master Card: "PISAU <PRODUK> ISI <n> MATA".
        $knifeW = isset($state['knifeW']) ? round((float) $state['knifeW'], 2) : null;
        $knifeH = isset($state['knifeH']) ? round((float) $state['knifeH'], 2) : null;
        $knifeCode = mb_substr('PISAU '.mb_strtoupper($part->product?->product_name ?: $part->part_name)." ISI {$upCount} MATA", 0, 80);

        $layoutDetail = [
            'source' => 'imposition_plano_optimizer',
            'canvas_l_cm' => $state['canvasL'] ?? null,
            'canvas_w_cm' => $state['canvasW'] ?? null,
            // Pilihan "Canvas limit" layar (CUSTOM / PRESS / DIECUT / BOTH).
            'canvas_limit' => $state['canvasLimit'] ?? null,
            'gap_cm' => $state['gapCm'] ?? null,
            'plano_name' => $state['spec']['planoName'] ?? null,
            'machine' => $state['machine'] ?? null,
            // Mesin pond ikut disimpan: dipakai Work Order saat menurunkan BOP.
            'diecut_machine_id' => $state['spec']['diecutMachineId'] ?? null,
            'objects' => $state['objects'] ?? [],
        ];

        // ===== ARAH 1: MASTER (tm2mst.prod_layout_group), reusable lintas Inquiry.
        // Draft existing (punya part ini, BELUM CONFIRMED) ditimpa, bukan bikin baris baru terus-menerus -- CONFIRMED tidak pernah ditimpa.
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

        // ===== ARAH 2: SNAPSHOT INQUIRY (tm2sdi.iqp_layout), HANYA kalau part ini Mode #1 (tertaut ke Inquiry).
        $iqpPart = IqpPart::where('prod_part_id', $part->id_part)->first();
        $costingUrl = null;

        if ($iqpPart) {
            // firstOrNew + exists (BUKAN updateOrCreate): created_by hanya boleh diisi saat baris benar-benar LAHIR.
            $iqpLayout = IqpLayout::firstOrNew(['part_id' => $iqpPart->id_part]);

            $iqpLayout->fill([
                // flat_w/flat_h sudah milimeter.
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

        // BOM / Ink / BOP (hasil engine atau override officer) ikut tersimpan 2 arah pada Save yang sama.
        $engine = app(SpecEngine::class);
        $skipped = $engine->persist($part, $iqpPart,
            $engine->generate($part, $iqpPart, $this->specContext($state['spec'] ?? []), $this->specOverrides($state['specOverride'] ?? [])),
            $login,
            array_values(array_intersect((array) ($state['specReset'] ?? []), SpecEngine::SECTIONS)));

        $this->jobsCache = null;

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

    // Pratinjau hasil engine (dan override officer yang sedang diedit) untuk panel BOM / Ink / BOP -- tidak menulis apa pun.
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

        $rows = MatRaw::where('activate', 1)
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

    public function lookups(): array
    {
        $mst = DB::connection('tm2mst');

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

    // Hasil analisa warna artwork (dihitung di browser dari gambar yang sama dengan canvas) disimpan di Inquiry Part,
    // supaya tidak dihitung ulang dan bisa dipakai layar lain.
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

    public function gapCm(): float
    {
        return round(SysConstant::number('PRODEV_GAP_MM', 3.0) / 10, 2);
    }

    // Mesin cetak dari master. prod_machine.max_paper_size SAAT INI kosong di semua baris, jadi bed dipakai dari asumsi
    // (plano terbesar + toleransi) dan ditandai est=true supaya panel info jujur bilang "belum diisi".
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

    // Mesin plong/ponds: canvas harus muat di MEJA PLONG juga, bukan cuma bed mesin cetak (instruksi user 2026-09-16).
    // max_paper_size Die Cutting SAAT INI kosong, jadi L/W sengaja null -- batas yang tidak diketahui TIDAK dikarang.
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
            // Yang ukuran mejanya sudah diketahui ditaruh di depan supaya pilihan default benar-benar memberi batas.
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

    // Job = HANYA part yang dipilih di grid (user 2026-09-16: "saat user pilih di grid... sudah pasti").
    // Geometri dicari berlapis: die_w/h_cm (kolom teknis ProDev) -> printsize_p/l -> flat_w/h dari Inquiry. Kalau tidak satupun
    // terisi, job TIDAK dikarang: dikembalikan kosong dan alasannya disampaikan lewat jobIssue().
    public function jobs(): array
    {
        return $this->jobsCache ??= $this->buildJobs();
    }

    private function buildJobs(): array
    {
        $parts = ProdPart::with('product')
            ->where('id_part', $this->partId)
            ->get();

        $origins = IqpPart::with('inquiry.customer')
            ->whereIn('prod_part_id', $parts->pluck('id_part'))
            ->get()
            ->keyBy('prod_part_id');

        $gap = $this->gapCm();
        $jobs = [];

        foreach ($parts as $p) {
            $src = $origins->get($p->id_part);

            // iqp_part.flat_w/flat_h = MILIMETER (hasil TMDieline), kolom teknis ProDev = cm.
            $l = (float) ($p->die_w_cm ?: $p->printsize_p ?: ($src?->flat_w ? $src->flat_w / 10 : 0));
            $w = (float) ($p->die_h_cm ?: $p->printsize_l ?: ($src?->flat_h ? $src->flat_h / 10 : 0));

            if ($l <= 0 || $w <= 0) {
                continue;
            }

            [$l, $w] = [max($l, $w), min($l, $w)];

            $spec = $src?->dieline_spec ?? [];
            // Batas canvas yang terakhir dipakai. Layout lama (sebelum pilihan ini ada) dibuat dengan aturan
            // meja cetak + plong = BOTH; part tanpa layout -> null (layar v2 memakai Custom).
            $lastLayout = $p->layoutGroups()->orderByDesc('prod_layout_group.id_layout_group')->first();

            $jobs[] = [
                'canvasLimit' => $lastLayout ? ($lastLayout->layout_detail['canvas_limit'] ?? 'BOTH') : null,
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
                // Sampel UAT (AL SAMSI/OSIMA) punya artwork vector bawaan yang digambar di JS, dikenali lewat kode Master Card-nya.
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
                // Canvas awal (juga jadi bias scoring di suggest()): ukuran lembar cetak yang sudah pernah dipakai part ini kalau ada.
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

        return $jobs;
    }

    // Layout part ini sudah di-CONFIRM? Master Card yang belum confirm dicap "NOT CONFIRMED" (permintaan user 2026-09-18).
    private function layoutConfirmed(ProdPart $p): bool
    {
        return $p->layoutGroups()->where('status', 'CONFIRMED')->exists();
    }

    // File vektor digambar dari pratinjau PNG-nya (canvas tidak bisa menggambar PDF/AI langsung).
    private function artworkDisplayPath(string $path): string
    {
        return Storage::disk('public')->exists($path.'.preview.png') ? $path.'.preview.png' : $path;
    }

    // Alasan jujur kalau part yang dipilih tidak bisa dikerjakan -- jangan diam atau menampilkan job kosong tanpa keterangan.
    public function jobIssue(): ?string
    {
        if ($this->jobs() !== []) {
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
    public function initialJobIndex(): int
    {
        foreach ($this->jobs() as $i => $j) {
            if (($j['partId'] ?? null) === $this->partId) {
                return $i;
            }
        }

        return 0;
    }
}
