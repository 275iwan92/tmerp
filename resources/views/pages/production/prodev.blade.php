<?php

use App\Models\Master\MatPlanoSize;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdPart;
use App\Models\Sdi\IqpPart;
use App\Models\Sys\SysConstant;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
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
// default (sys_constant PRODEV_GAP_MM). Yang BELUM: penyimpanan hasil
// nesting (prod_layout_group) -- masih jadi bahan diskusi, lihat catatan di
// akhir sesi/commit.
new #[Title('Pro-Dev — TELASIH MAKMUR ERP')] class extends Component
{
    use WithFileUploads;

    public ?int $partId = null;

    // Job yang sedang dibuka di canvas -- di-set dari JS tiap ganti job, supaya
    // upload artwork tahu part mana yang dituju (sisanya layar ini client-side).
    public ?int $activeJobPartId = null;

    public $artworkUpload = null;

    public string $artworkError = '';

    public function mount(?int $part = null): void
    {
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

        $this->validate([
            'artworkUpload' => 'image|mimes:jpg,jpeg,png|max:'.($this->artworkMaxMb() * 1024),
        ], [], ['artworkUpload' => 'artwork']);

        $old = $iqpPart->artwork_path;
        $path = $this->artworkUpload->store("inquiry/{$iqpPart->id_part}/artwork", 'public');

        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }

        $iqpPart->update([
            'artwork_path' => $path,
            'artwork_original_filename' => $this->artworkUpload->getClientOriginalName(),
        ]);

        $this->dispatch('prodev-artwork-updated',
            partId: $this->activeJobPartId,
            url: asset('storage/'.$path),
            name: $this->artworkUpload->getClientOriginalName(),
        );

        $this->artworkUpload = null;
        unset($this->jobs);
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
            ->where('machine_type', 'Cetak')
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

    // Job = prod_part yang punya geometri dieline. Sumber geometri berlapis:
    // die_w/h_cm (kolom teknis ProDev) -> printsize_p/l -> flat_w/h dari
    // Inquiry. Part tanpa satupun dari itu dilewati (tidak bisa di-nesting).
    #[Computed]
    public function jobs(): array
    {
        $parts = ProdPart::with('product')
            ->where('activate', true)
            ->orderByDesc('id_part')
            ->limit(150)
            ->get();

        $origins = IqpPart::with('inquiry.customer')
            ->whereIn('prod_part_id', $parts->pluck('id_part'))
            ->get()
            ->keyBy('prod_part_id');

        $gap = $this->gapCm;
        $jobs = [];

        foreach ($parts as $p) {
            $src = $origins->get($p->id_part);

            $l = (float) ($p->die_w_cm ?: $p->printsize_p ?: $src?->flat_w ?: 0);
            $w = (float) ($p->die_h_cm ?: $p->printsize_l ?: $src?->flat_h ?: 0);

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
                'material' => $p->product?->product_name ?: '—',
                'structure' => $p->ecma_code ?: ($p->joint_type ?: '—'),
                'art' => $p->cfg_color ?: '—',
                'customer' => $src?->inquiry?->customer?->customer_name ?: 'Mode #2 — manual / standalone',
                'design' => $p->displayMastercardCode(),
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
                'artworkUrl' => $src?->artwork_path ? asset('storage/'.$src->artwork_path) : null,
                'artworkName' => $src?->artwork_original_filename,
                'canUpload' => $src !== null,
            ];
        }

        return array_merge($jobs, $this->demoJobs());
    }

    // 3 job demo bawaan mockup -- artwork-nya digambar vector di JS (shape()),
    // dipertahankan supaya layar ini tetap bisa didemokan & jadi pembanding
    // visual walau data prod_part belum lengkap dielinenya.
    private function demoJobs(): array
    {
        return [
            [
                'id' => 'demo-alsamsi-top', 'kind' => 'demo', 'partId' => null, 'type' => 'alsamsi_top',
                'name' => '[DEMO] AL SAMSI Exclusive Top — Sarung Tenun', 'category' => 'Rigid Lid / Tray Lid',
                'fl' => 26.5, 'fw' => 20, 'fh' => 5.5, 'l' => 42, 'w' => 35.5, 'bleed' => .3, 'gap' => .3, 'grain' => 'L',
                'material' => 'Art Carton 310 gsm + Gold Foil / UV', 'structure' => 'Tray Lid — 4 Corner Lock + Hang Tab',
                'art' => 'AL SAMSI · EXCLUSIVE TOP (4-up mirror)', 'customer' => 'AL SAMSI — Sarung Tenun',
                'design' => 'DS002047', 'reg' => 'IDM000807653', 'history' => [85, 71.5],
                'dieline' => null, 'dielineDims' => (object) [], 'artworkUrl' => null, 'artworkName' => null, 'canUpload' => false,
            ],
            [
                'id' => 'demo-osima', 'kind' => 'demo', 'partId' => null, 'type' => 'osima',
                'name' => '[DEMO] OSIMA MS N100 CV — Battery Carton', 'category' => 'Battery / Automotive Carton',
                'fl' => 41.3, 'fw' => 18, 'fh' => 26, 'l' => 50, 'w' => 40.5, 'bleed' => .3, 'gap' => .4, 'grain' => 'L',
                'material' => 'Duplex 400 gsm / E-flute Laminated', 'structure' => '2-Piece Wrap (Panel A + B) + Hand Hole',
                'art' => 'OSIMA MS PREMIUM · Japanese Technology', 'customer' => 'PT. SELATAN JADI JAYA',
                'design' => 'DS003146', 'reg' => 'SNI · Rev.02 07/03/2026', 'history' => [101, 41],
                'dieline' => null, 'dielineDims' => (object) [], 'artworkUrl' => null, 'artworkName' => null, 'canUpload' => false,
            ],
            [
                'id' => 'demo-alsamsi-sig', 'kind' => 'demo', 'partId' => null, 'type' => 'alsamsi_sig',
                'name' => '[DEMO] AL SAMSI Signature — Lock Bottom 10 Pcs', 'category' => 'Folding Carton — Inner Box',
                'fl' => 37.5, 'fw' => 19.7, 'fh' => 25.5, 'l' => 59.2, 'w' => 39.5, 'bleed' => .3, 'gap' => .4, 'grain' => 'W',
                'material' => 'Ivory / Duplex 350 gsm + Gold Foil', 'structure' => 'Lock Bottom (Blank A + Blank B)',
                'art' => 'AL SAMSI SIGNATURE · Premium Quality', 'customer' => 'AL SAMSI — Sarung Tenun',
                'design' => 'TM 25-11.25', 'reg' => 'IDM000807653', 'history' => [80, 60],
                'dieline' => null, 'dielineDims' => (object) [], 'artworkUrl' => null, 'artworkName' => null, 'canUpload' => false,
            ],
        ];
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
            planos: @json($this->planos),
            initialJob: @json($this->initialJobIndex),
        };
    </script>

    {{-- Input file NYATA dipegang Livewire, ditaruh DI LUAR wire:ignore supaya
         morph-nya tetap jalan. Tombol "Upload artwork" di panel kiri cuma
         memicu klik ke sini (label lintas-subtree lewat getElementById). --}}
    <div class="io-uploader">
        <input type="file" id="io-artFileReal" accept="image/jpeg,image/png" wire:model="artworkUpload">
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
        .io-top{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:17px}
        .io-top h1{font-size:25px;margin:0;color:var(--io-text)}
        .io-sub{margin:4px 0 0;color:var(--io-muted);font-size:13px}
        .io-badge{padding:7px 11px;border:1px solid #2c6c92;background:#0b2b44;color:#91e8ff;border-radius:999px;font-weight:700;font-size:12px}
        .io-layout{display:grid;grid-template-columns:345px minmax(560px,1fr) 340px;gap:16px}
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
        .io-canvas-card{padding:15px;display:flex;flex-direction:column;min-height:748px}
        .io-toolbar{display:flex;gap:7px;flex-wrap:wrap;align-items:center;margin-bottom:11px;background:none;border:0;padding:0}
        .io-toolbar .io-btn{padding:7px 9px}
        .io-status{margin-left:auto;color:var(--io-muted);font-size:12px}
        .io-pill{padding:3px 7px;border-radius:5px;background:#17415f;color:#c8efff;font-size:11px}
        .io-stage{position:relative;flex:1;min-height:610px;overflow:hidden;border:1px solid #284860;border-radius:11px;background:#071522;
            background-image:linear-gradient(#102940 1px,transparent 1px),linear-gradient(90deg,#102940 1px,transparent 1px);background-size:20px 20px}
        .io-rulerx{position:absolute;z-index:3;left:0;right:0;top:0;height:25px;background:#0b2032;border-bottom:1px solid #31536d;padding:5px 8px;font-size:10px;color:#88a7bd}
        .io-rulery{position:absolute;z-index:3;top:25px;bottom:0;width:25px;background:#0b2032;border-right:1px solid #31536d}
        .io-viewport{position:absolute;left:25px;right:0;top:25px;bottom:0;overflow:auto}
        .io-wrap{width:100%;height:100%;min-width:740px;min-height:575px;display:flex;align-items:center;justify-content:center;transform-origin:center}
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
        .io-cut{height:150px;background:#07131f;border:1px solid #395a76;border-radius:8px;margin:9px 0;display:flex;align-items:center;justify-content:center;overflow:hidden}
        .io-instructions{font-size:12px;line-height:1.45;color:#c7e7f7;background:#102b41;border:1px solid #2d6689;border-radius:8px;padding:9px;margin-top:9px}
        .io-instructions b{color:#9eeaff}
        @media(max-width:1250px){.io-layout{grid-template-columns:320px 1fr}.io-right{grid-column:1/-1}
            .io-right>.io-panel{display:grid;grid-template-columns:1fr 1fr;gap:16px}.io-right .io-group{border:0;padding:0;margin:0}}
        @media(max-width:800px){.io-app{padding:10px}.io-layout{display:block}.io-panel,.io-canvas-card{margin-bottom:12px}
            .io-right>.io-panel{display:block}.io-right .io-group{border-top:1px solid var(--io-line);padding-top:13px;margin-top:13px}
            .io-top{align-items:flex-start}.io-badge{display:none}}
        </style>

        <div class="io-shell">
            <div class="io-top">
                <div>
                    <h1>Imposition Plano Optimizer</h1>
                    <p class="io-sub">Pro-Dev — nesting manual &amp; optimasi plano per job cetak (artwork vector + garis dieline struktural)</p>
                </div>
                <div class="io-badge" id="io-jobcount">LIVE PACKAGING JOBS</div>
            </div>

            <div class="io-layout">
                <aside class="io-panel">
                    <h2>Job Configuration</h2>
                    <div class="io-group">
                        <label>Product inquiry / job</label>
                        <select id="io-product"></select>
                        <div id="io-metrics" class="io-metric-grid"></div>
                        <div id="io-spec" class="io-spec"></div>
                    </div>
                    <div class="io-group">
                        <label>Printing machine</label>
                        <select id="io-machine"></select>
                        <div id="io-machineInfo" class="io-notice info"></div>
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
                            <div id="io-manualGroup" class="io-manual-disabled">
                                <label>Manual up</label>
                                <input id="io-manualUp" type="number" min="1" value="6">
                                <p class="io-note">Nonaktifkan Auto Up untuk menentukan jumlah object.</p>
                            </div>
                        </div>
                    </div>
                    <div class="io-group">
                        <h2>2. Gap</h2>
                        <label>Jarak antar artwork / dieline (cm)</label>
                        <input id="io-gap" type="number" min="0" step=".05" value=".3">
                    </div>
                    <div class="io-group">
                        <h2>3. Lembar Cetak / Canvas</h2>
                        <div class="io-row">
                            <div><label>Length (cm)</label><input id="io-sl" type="number" min="1" step=".1"></div>
                            <div><label>Width (cm)</label><input id="io-sw" type="number" min="1" step=".1"></div>
                        </div>
                        <button id="io-suggest" class="io-btn green" style="width:100%;margin-top:9px">✦ Suggest canvas size</button>
                        <p id="io-suggestNote" class="io-note"></p>
                    </div>
                    <div class="io-group">
                        <label>Grain direction</label>
                        <select id="io-grain">
                            <option value="L">Along dieline length</option>
                            <option value="W">Along dieline width</option>
                            <option value="none">No grain constraint</option>
                        </select>
                        <label>Artwork asset</label>
                        <label class="io-upload">Upload artwork<input id="io-artFile" type="file" accept="image/*,.pdf,.ai,.svg"></label>
                        <div id="io-artName" class="io-file">Artwork bawaan job aktif</div>
                        <label>Separate dieline</label>
                        <label class="io-upload">Upload dieline<input id="io-dieFile" type="file" accept="image/*,.pdf,.ai,.svg,.dxf"></label>
                        <div id="io-dieName" class="io-file">Dieline struktural bawaan job aktif</div>
                    </div>
                </aside>

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
                        <span class="io-status" id="io-status"></span>
                    </div>
                    <div class="io-stage">
                        <div class="io-rulerx">CANVAS / PRINT SHEET RULER — cm</div>
                        <div class="io-rulery"></div>
                        <div id="io-sheetLabel" class="io-sheetlabel"></div>
                        <div id="io-warning" class="io-warn">⚠ Artwork melewati batas canvas / print sheet</div>
                        <div class="io-viewport">
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
                        diatur, tombol <b>✦ Suggest canvas size</b> tidak me-reset layout — ia menghitung ulang canvas dari
                        bounding box susunan Anda + bleed. Tekan <b>Reset layout</b> bila ingin kembali ke grid otomatis.
                    </div>
                    <div id="io-valid" class="io-notice info">Set a valid canvas size.</div>
                </main>

                <aside class="io-right">
                    <div class="io-panel">
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
                        <div class="io-group">
                            <h2>Imposition &amp; Yield</h2>
                            <div id="io-impoMode" class="io-notice info"></div>
                            <div class="io-mini-grid">
                                <div class="io-mini"><span>Canvas utilization</span><b id="io-yCanvas">—</b></div>
                                <div class="io-mini"><span>Canvas waste</span><b id="io-yCanvasW">—</b></div>
                                <div class="io-mini"><span>Plano sheet yield</span><b id="io-yPlano">—</b></div>
                                <div class="io-mini"><span>Overall efficiency</span><b id="io-yAll">—</b></div>
                            </div>
                            <div class="io-bar"><i id="io-barFill"></i><span id="io-barTxt"></span></div>
                            <div id="io-impoDetail" class="io-spec"></div>
                        </div>
                        <div class="io-group">
                            <h2>Plano Optimization</h2>
                            <label>Market plano size</label>
                            <select id="io-plano"></select>
                            <div class="io-row">
                                <div><label>Custom L</label><input id="io-cpl" type="number" step=".1" placeholder="cm"></div>
                                <div><label>Custom W</label><input id="io-cpw" type="number" step=".1" placeholder="cm"></div>
                            </div>
                            <button id="io-custom" class="io-btn" style="width:100%;margin-top:8px">Use custom plano</button>
                            <div id="io-planoNote" class="io-notice info"></div>
                            <div id="io-planoTable" class="io-spec"></div>
                            <div id="io-cut" class="io-cut"></div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        <script>
        (function () {
            const DATA = window.__PRODEV || { jobs: [], machines: [], planos: [], initialJob: 0 };
            const products = DATA.jobs;
            const machines = DATA.machines.map(m => [m.name, m.L, m.W, m.est]);
            const planos = DATA.planos.map(p => [p.name, p.L, p.W]);
            if (!products.length || !machines.length || !planos.length) {
                document.getElementById('io-valid').textContent =
                    'Data master belum lengkap: butuh minimal 1 part berdimensi, 1 mesin cetak, dan 1 ukuran plano aktif.';
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

            function init() {
                products.forEach((x, i) => $('product').add(new Option(x.name, i)));
                machines.forEach((x, i) => $('machine').add(new Option(x[0], i)));
                planos.forEach((x, i) => $('plano').add(new Option(x[0], i)));
                $('product').value = String(products.indexOf(S.p));
                $('jobcount').textContent = products.length + ' JOB SIAP IMPOSISI';
                $('sl').value = S.L; $('sw').value = S.W; $('gap').value = S.g; $('grain').value = S.p.grain;
                bind();
                artworkLabel();
                syncActiveJob();
                update(true);
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
                    artworkLabel(); syncActiveJob(); suggest();
                };
                $('machine').onchange = e => { S.m = machines[e.target.value]; S.objs = []; suggest(); };
                $('autoUp').onchange = e => {
                    S.auto = e.target.checked;
                    $('manualGroup').classList.toggle('io-manual-disabled', S.auto);
                    $('modeText').textContent = S.auto
                        ? 'Aktif: gunakan up maksimum yang memungkinkan'
                        : 'Nonaktif: masukkan jumlah up secara manual';
                    S.objs = []; suggest();
                };
                $('manualUp').oninput = e => {
                    S.manualUp = Math.max(1, +e.target.value || 1);
                    if (!S.auto) { S.objs = []; update(true); }
                };
                $('suggest').onclick = suggest;
                $('plano').onchange = e => { S.pl = planos[e.target.value]; update(false); };
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
                    if (S.p === job) { artworkLabel(); render(); }
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
            function ok() { return S.L > 0 && S.W > 0 && S.L <= S.m[1] && S.W <= S.m[2]; }
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

            function obox(o) {
                const a = o.r * Math.PI / 180, c = Math.abs(Math.cos(a)), s = Math.abs(Math.sin(a)),
                    W = o.w * c + o.h * s, H = o.w * s + o.h * c, cx = o.x + o.w / 2, cy = o.y + o.h / 2;
                return { x: cx - W / 2, y: cy - H / 2, w: W, h: H };
            }

            // Suggest saat susunan sudah disentuh user: canvas dihitung ulang dari
            // bounding box susunan + bleed (TIDAK me-reset nesting manual).
            function fitToLayout() {
                const sc = Math.min(600 / S.L, 430 / S.W), sx = (650 - S.L * sc) / 2, sy = (500 - S.W * sc) / 2, m = S.p.bleed,
                    b = S.objs.map(o => { const q = obox(o); return { x: (q.x - sx) / sc, y: (q.y - sy) / sc, X: (q.x + q.w - sx) / sc, Y: (q.y + q.h - sy) / sc }; }),
                    minx = Math.min(...b.map(v => v.x)), miny = Math.min(...b.map(v => v.y)),
                    maxx = Math.max(...b.map(v => v.X)), maxy = Math.max(...b.map(v => v.Y)),
                    cen = S.objs.map(o => ({ o, cx: (o.x + o.w / 2 - sx) / sc - minx + m, cy: (o.y + o.h / 2 - sy) / sc - miny + m })),
                    rl = +(maxx - minx + 2 * m).toFixed(1), rw = +(maxy - miny + 2 * m).toFixed(1),
                    L = Math.min(rl, S.m[1]), W = Math.min(rw, S.m[2]),
                    area = S.objs.length * S.p.l * S.p.w, use = (area / (L * W) * 100).toFixed(1);

                S.L = L; S.W = W; $('sl').value = L; $('sw').value = W;
                const n = Math.min(600 / L, 430 / W), nx = (650 - L * n) / 2, ny = (500 - W * n) / 2, k = n / sc;
                cen.forEach(r => { r.o.w *= k; r.o.h *= k; r.o.x = nx + r.cx * n - r.o.w / 2; r.o.y = ny + r.cy * n - r.o.h / 2; });
                S.sc0 = n; S.sx0 = nx; S.sy0 = ny;
                $('suggestNote').textContent = (rl > L || rw > W)
                    ? 'Susunan custom butuh ' + rl + ' × ' + rw + ' cm — melebihi bed ' + S.m[0] + ' (' + S.m[1] + ' × ' + S.m[2] + '). Canvas dipotong ke ' + L + ' × ' + W + ' cm, object di luar batas ditandai merah.'
                    : 'Custom nesting: bounding box ' + S.objs.length + ' object + bleed ' + m + ' cm/sisi = ' + L + ' × ' + W + ' cm · pemakaian area ' + use + '%.';
                update(false);
            }

            function suggest() {
                if (S.touched && S.objs.length) { fitToLayout(); return; }
                const target = S.auto ? null : S.manualUp, p = S.p, m = S.m;
                let bestC = null;
                for (let L = 25; L <= m[1]; L += .5) for (let W = 20; W <= m[2]; W += .5) {
                    const a = fit(L, W, p.l, p.w, S.g), b = fit(L, W, p.w, p.l, S.g), f = b.ups > a.ups ? b : a;
                    if (target ? f.ups >= target : f.ups > 0) {
                        const area = L * W, h = Math.abs(L - p.history[0]) + Math.abs(W - p.history[1]),
                            score = target ? area + h * .15 : -(f.ups / area) + h * .002;
                        if (!bestC || score < bestC.score) bestC = { L, W, score };
                    }
                }
                if (!bestC) {
                    update(true);
                    msg('Manual ' + target + '-up tidak muat pada batas mesin ' + m[1] + ' × ' + m[2] + ' cm.', 'bad');
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
                    $('arts').innerHTML = ''; $('grainG').innerHTML = '';
                    $('sheetLabel').textContent = 'CANVAS ' + S.L + ' × ' + S.W + ' cm — DI LUAR BATAS MESIN';
                    $('warning').innerHTML = '⚠ Canvas ' + S.L + ' × ' + S.W + ' cm melebihi bed ' + S.m[0] +
                        '<br>Maksimum ' + S.m[1] + ' × ' + S.m[2] + ' cm — ' + S.objs.length + ' object disembunyikan';
                    $('warning').classList.add('show');
                    msg('Invalid: canvas tidak boleh melebihi ' + S.m[1] + ' × ' + S.m[2] + ' cm pada ' + S.m[0] + '.', 'bad');
                    $('status').textContent = 'Validation failed';
                    return;
                }
                const sc = Math.min(600 / S.L, 430 / S.W), w = S.L * sc, h = S.W * sc, x = (650 - w) / 2, y = (500 - h) / 2;
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

            function createObjects(x, y, sc) {
                S.touched = false;
                const f = best();
                let n = selectedUp(), i = 0;
                if (n > f.ups) { msg('Selected ' + n + '-up exceeds physical maximum ' + f.ups + '-up on this canvas.', 'bad'); n = f.ups; }
                for (let r = 0; r < f.r && i < n; r++) for (let c = 0; c < f.c && i < n; c++, i++) {
                    const ww = (f.rot ? S.p.w : S.p.l) * sc, hh = (f.rot ? S.p.l : S.p.w) * sc;
                    S.objs.push({
                        id: 'a' + i,
                        x: x + c * ((f.rot ? S.p.w : S.p.l) + S.g) * sc,
                        y: y + r * ((f.rot ? S.p.l : S.p.w) + S.g) * sc,
                        w: ww, h: hh, r: 0, fx: 1, fy: 1,
                    });
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
                if (S.p.kind === 'real') return realShape(S.p, w, h) + cut;

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
                return { x: (e.clientX - r.left) * 650 / r.width, y: (e.clientY - r.top) * 500 / r.height };
            }
            function move(e) {
                if (!S.drag) return;
                e.preventDefault();
                const o = S.objs.find(q => q.id === S.drag.id), p = point(e);
                o.x = S.drag.ox + p.x - S.drag.sx; o.y = S.drag.oy + p.y - S.drag.sy;
                S.touched = true;
                render();
            }
            function end() {
                S.drag = null;
                app.classList.remove('dragging');
                document.removeEventListener('pointermove', move);
                document.removeEventListener('pointerup', end);
                document.removeEventListener('pointercancel', end);
                render(); calculator();
            }
            function transform(k, v) {
                const o = S.objs.find(x => x.id === S.selected);
                if (!o) { msg('Pilih artwork object pada canvas terlebih dahulu.', 'info'); return; }
                if (k === 'r') o.r = (o.r + v) % 360;
                else if (v === 'x') o.fx *= -1;
                else o.fy *= -1;
                S.touched = true;
                render(); calculator();
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

                const sc = S.sc0 || Math.min(600 / S.L, 430 / S.W), sx = S.sx0, sy = S.sy0, sw = S.L * sc, sh = S.W * sc,
                    b = S.objs.map(obox), rot = S.objs.filter(o => o.r % 360 !== 0).length,
                    mir = S.objs.filter(o => o.fx < 0 || o.fy < 0).length,
                    out = b.filter(v => v.x < sx - .6 || v.y < sy - .6 || v.x + v.w > sx + sw + .6 || v.y + v.h > sy + sh + .6).length,
                    bl = b.length ? ((Math.max(...b.map(v => v.x + v.w)) - Math.min(...b.map(v => v.x))) / sc).toFixed(1) : '—',
                    bw = b.length ? ((Math.max(...b.map(v => v.y + v.h)) - Math.min(...b.map(v => v.y))) / sc).toFixed(1) : '—',
                    g = best();

                $('impoMode').className = 'io-notice ' + (out ? 'bad' : S.touched ? 'ok' : 'info');
                $('impoMode').innerHTML = S.touched
                    ? '<b>CUSTOM IMPOSITION — nesting manual</b><br>Susunan diatur user: ' + rot + ' object rotate, ' + mir + ' mirror' + (out ? ' · <b>' + out + ' di luar canvas (tidak terhitung sah)</b>' : '') + '.'
                    : '<b>AUTO GRID IMPOSITION</b><br>Susunan otomatis ' + g.c + ' kolom × ' + g.r + ' baris' + (g.rot ? ' (blank dirotate 90°)' : '') + ' · mode ' + (S.auto ? 'Auto Up' : 'Manual Up') + '.';

                $('impoDetail').innerHTML = '<table>' +
                    '<tr><td>Object di canvas</td><td>' + up + ' up</td></tr>' +
                    '<tr><td>Bounding box susunan</td><td>' + bl + ' × ' + bw + ' cm</td></tr>' +
                    '<tr><td>Area blank total</td><td>' + (up * A).toFixed(0) + ' cm²</td></tr>' +
                    '<tr><td>Area canvas</td><td>' + ac.toFixed(0) + ' cm²</td></tr>' +
                    '<tr><td>Sisa canvas kosong</td><td>' + (ac - up * A).toFixed(0) + ' cm² (' + (100 - cu).toFixed(1) + '%)</td></tr>' +
                    '<tr><td>Canvas / plano</td><td>' + c.n + ' lembar</td></tr>' +
                    '<tr><td>Trim plano terbuang</td><td>' + (ap - c.n * ac).toFixed(0) + ' cm²</td></tr>' +
                    '<tr><td>Hasil / plano</td><td>' + (c.n * up) + ' pcs</td></tr></table>';

                const rows = planos.map(q => ({ q, c: cut(q) })).filter(x => x.c.n).sort((a, b2) => b2.c.n * up - a.c.n * up || b2.c.util - a.c.util);
                $('planoTable').innerHTML = rows.length
                    ? '<table class="rank"><tr><th>Plano</th><th>Cvs</th><th>Yield</th><th>Overall</th><th>Pcs</th></tr>' +
                      rows.map(r => '<tr class="' + (r.q[0] === S.pl[0] ? 'best' : '') + '"><td>' + r.q[0].replace(' cm', '') + '</td><td>' + r.c.n + '</td><td>' + (r.c.util * 100).toFixed(1) + '%</td><td>' + (cu * r.c.util).toFixed(1) + '%</td><td>' + (r.c.n * up) + '</td></tr>').join('') +
                      '</table>'
                    : '<table><tr><td>Tidak ada plano standar yang muat canvas ' + S.L + ' × ' + S.W + ' cm</td></tr></table>';
            }

            function calculator() {
                if (!ok()) return;
                const c = cut(S.pl), up = S.objs.length || selectedUp(), val = +$('converterInput').value || 0;
                if (!c.n) {
                    $('planoNote').className = 'io-notice bad';
                    $('planoNote').textContent = 'Selected plano cannot yield this canvas size.';
                    impo(c, up);
                    return;
                }
                const bp = planos.map(p => ({ p, c: cut(p) })).filter(x => x.c.n).sort((a, b) => b.c.n - a.c.n || b.c.util - a.c.util)[0];
                $('planoNote').className = 'io-notice ok';
                $('planoNote').innerHTML = 'Recommended: <b>' + bp.p[0] + '</b> gives ' + bp.c.n + ' canvas(es) / plano (' +
                    (bp.c.util * 100).toFixed(1) + '% sheet yield). ' + (S.pl[0] === bp.p[0] ? 'Selected.' : 'Manual plano selection active.');
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
            }

            function drawPlano(p, c) {
                const sc = Math.min(250 / p[1], 130 / p[2]), W = p[1] * sc, H = p[2] * sc,
                    x = (280 - W) / 2, y = (150 - H) / 2,
                    cols = c.rot ? Math.floor(p[1] / S.W) : Math.floor(p[1] / S.L),
                    rows = c.rot ? Math.floor(p[2] / S.L) : Math.floor(p[2] / S.W),
                    cw = (c.rot ? S.W : S.L) * sc, ch = (c.rot ? S.L : S.W) * sc;
                let s = '';
                for (let r = 0; r < rows; r++) for (let col = 0; col < cols; col++) {
                    s += '<rect x="' + (x + col * cw) + '" y="' + (y + r * ch) + '" width="' + cw + '" height="' + ch + '" fill="#73ddff" fill-opacity=".75" stroke="#0e5776"/>' +
                        '<text x="' + (x + col * cw + cw / 2) + '" y="' + (y + r * ch + ch / 2) + '" text-anchor="middle" dominant-baseline="middle" font-size="9" fill="#063049">CANVAS</text>';
                }
                $('cut').innerHTML = '<svg width="280" height="150"><rect x="' + x + '" y="' + y + '" width="' + W + '" height="' + H + '" fill="#dfe9ee" stroke="#8fa8b6"/>' + s +
                    '<text x="140" y="143" text-anchor="middle" fill="#abc6d5" font-size="10">' + p[0] + ' · ' + c.n + ' canvas yield</text></svg>';
            }

            init();
        })();
        </script>
        @endverbatim
    </div>
</div>
