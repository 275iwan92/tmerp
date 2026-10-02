<?php

use App\Models\Master\OrgCompany;
use App\Models\Master\ProdMastercardSignoff;
use App\Models\Master\ProdPart;
use App\Models\Sdi\IqpPart;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// docs/inquiry_product_costing/productdevelopment_tier1.md §4.1 -- Master Card
// BUKAN layar input, ini HASIL PRINT OUT dari data prod_part+relasinya yang
// dikerjakan di layar ProDev (prodev.blade.php). Pola sama persis dokumen
// cetak lain (docs/printnotastandard.md), komponen reusable resources/views/components/print/*.
new #[Title('Master Card — TELASIH MAKMUR ERP')] class extends Component
{
    public int $partId;

    public function mount(int $part): void
    {
        $this->partId = $part;
    }

    #[Computed]
    public function part(): ProdPart
    {
        return ProdPart::with([
            'product',
            'processes.process',
            'processes.machine',
            'finishings.finishingType',
            'attachments',
            'layoutGroups',
        ])->findOrFail($this->partId);
    }

    #[Computed]
    public function layoutGroup()
    {
        return $this->part->layoutGroups->sortByDesc('id_layout_group')->first();
    }

    // docs/mockups/mockup_mastercard.jpg 2026-09-09: mockup ini baru ditambahkan
    // ke repo (belum ada saat layar ini pertama dibangun), jadi belum pernah
    // jadi acuan -- gap "kenapa beda dari mockup" bukan kelalaian, sumbernya
    // memang belum ada. ProdPart (Mode #2 standalone) TIDAK PUNYA field
    // material/gramatur sama sekali (itu domain Inquiry, bukan ProDev -- lihat
    // CLAUDE.md §4 "2 mode independen"). Kalau part ini Mode #1 (link dari
    // Inquiry BARU), ambil material via iqp_part_material sbg REFERENSI --
    // sama prinsip panel "Referensi Inquiry Asal" yg sudah ada di prodev.blade.php,
    // read-only, TIDAK BOLEH diedit dari sini.
    #[Computed]
    public function originIqpPart(): ?IqpPart
    {
        return IqpPart::with('materials')
            ->where('prod_part_id', $this->partId)
            ->whereHas('inquiry', fn ($q) => $q->where('stat_confirm_sales', true)->whereNotIn('sub_state', ['DRAFT', 'CANCELLED', 'LOST']))
            ->first();
    }

    #[Computed]
    public function originMaterial()
    {
        $materials = $this->originIqpPart?->materials;

        return $materials?->firstWhere('is_primary', true) ?? $materials?->first();
    }

    // Visual "Layout Lembar Cetak" -- pola SAMA dgn tab Master Card WO Detail
    // (wo-entry.blade.php::officialArtwork()/prodevArtwork(), BACKLOG.md §6 R24):
    // artwork resmi (prod_part_artwork) dulu, fallback attachment JPEG ProDev
    // kalau belum ada. TIDAK bikin render dieline baru di sini -- ProdPart tidak
    // punya dieline_spec (DSL) spt IqpPart, cuma dieline_ref (teks referensi),
    // jadi tidak ada data utk TMDieline.render(). Kalau tidak ada gambar sama
    // sekali, tampilkan placeholder jujur, bukan tebakan visual.
    #[Computed]
    public function officialArtwork(): ?object
    {
        return DB::connection('tm2mst')->table('prod_part_artwork')
            ->where('part_id', $this->partId)->where('activate', true)->first();
    }

    #[Computed]
    public function fallbackArtwork()
    {
        return $this->part->attachments->where('file_type', 'JPEG')->sortByDesc('uploaded_at')->first();
    }

    #[Computed]
    public function company(): ?OrgCompany
    {
        return OrgCompany::where('activate', true)->first();
    }

    // Wizard step-bar lintas modul (user 2026-09-03), print:hidden -- tidak
    // pernah ikut tercetak/PDF, murni navigasi on-screen. Pola sama dgn
    // prodev.blade.php::wizardSteps(), job aktif = part ini.
    #[Computed]
    public function wizardSteps(): array
    {
        $origin = $this->originIqpPart?->inquiry_id;

        return [
            'inquiry' => [
                // SELALU ada url (user 2026-09-04) -- lihat catatan sama di
                // production/prodev.blade.php::wizardSteps().
                'label' => '1. '.t('Inquiry'),
                'url' => $origin
                    ? route('inquiry.form', ['id' => $origin, 'view' => 1])
                    : route('inquiry.form', ['view' => 1, 'from_part' => $this->partId]),
            ],
            'development' => [
                'label' => '2. '.t('Development'),
                'url' => route('production.old-prodev', ['part' => $this->partId]),
            ],
            'costing' => [
                'label' => '3. '.t('Costing'),
                'url' => $origin ? route('inquiry.costing', ['inquiry' => $origin, 'view' => 1]) : null,
                'reason' => t('This part is Mode #2 (manual/standalone) -- no Inquiry yet to calculate its Costing.'),
            ],
            'mastercard' => [
                'label' => t('Master Card'),
                'url' => route('production.old-prodev.print', ['part' => $this->partId]),
            ],
            'analytics' => [
                'label' => t('Analytics'),
                'url' => null,
                'reason' => t('The Analytics module has not been built yet.'),
            ],
        ];
    }

    #[Computed]
    public function signoffs()
    {
        $existing = ProdMastercardSignoff::where('product_id', $this->part->product_id)
            ->get()->keyBy('role_name');

        $roles = [
            'DESAINER' => t('Designer / Product Development'),
            'MARKETING' => t('Sales Officer'),
            'PPIC' => t('PPIC'),
            'QA' => t('Quality Control'),
            'PLANT_MANAGER' => t('Plant Manager'),
        ];

        return collect($roles)->map(fn ($label, $code) => (object) [
            'code' => $code,
            'label' => $label,
            'signed_by' => $existing->get($code)?->signed_by,
            'signed_at' => $existing->get($code)?->signed_at,
        ]);
    }
};
?>

<div>
<div class="print:hidden">
    <x-prodev.wizard-bar current="mastercard" :steps="$this->wizardSteps" :back-url="route('production.old-prodev.list')" />
</div>
{{-- Permintaan user 2026-09-09: format A4 LANDSCAPE + hitam-putih/grayscale
(BUKAN toggle opsional spt .bw Nota Inquiry -- ini format tetap utk Master
Card), KECUALI gambar artwork/preview yang tetap berwarna asli, semua
tulisan hitam. Theme di-set langsung ke hitam/putih (bukan andalkan class
.bw fight inline style -- prop `theme` dirender jadi inline style di root
.print-doc, itu SELALU menang lawan `.print-doc.bw{...}` class rule utk
custom property yang sama, jadi cara paling tepercaya adalah set nilainya
langsung di sini, bukan override via class). class="landscape" ubah ukuran
fisik halaman (lihat <style> di bawah) -- @page global document.blade.php
tetap A4 portrait utk semua dokumen Nota lain, TIDAK disentuh. --}}
<x-print.document class="landscape"
    :theme="['ink' => '#000000', 'muted' => '#555555', 'line' => '#000000', 'line2' => '#999999', 'accent' => '#000000', 'accent-soft' => '#ffffff', 'warn' => '#000000', 'gold' => '#000000', 'danger' => '#000000']"
    info="{{ $this->part->displayMastercardCode() }}">
    <x-slot:toolbar>
        <a href="{{ route('production.old-prodev', ['part' => $this->partId]) }}" class="sc">← {{ t('Back to ProDev') }}</a>
    </x-slot:toolbar>

    <x-print.sheet>
        <x-print.header :company="$this->company" :doc-title="t('MASTER CARD')" :doc-no="$this->part->displayMastercardCode()">
            <x-slot:meta>
                {{ t('Design No.:') }} <span class="ref">{{ $this->part->product?->design_no ?? '—' }}</span>
                · {{ t('Printed on') }} {{ now()->format('d/m/Y H:i') }}
            </x-slot:meta>
        </x-print.header>

        {{-- Layout 2-kolom ala mockup_mastercard.jpg: kiri tabel spesifikasi
        ringkas, kanan visual layout cetak (artwork resmi/referensi ProDev,
        BUKAN dieline generated -- part.dieline_ref cuma teks referensi. --}}
        {{-- BACKLOG.md §0 R35 (2026-09-10): 12 baris 1-pasang/baris ternyata bikin
        tumpah ke halaman 2 -- mockup cuma ~7 baris krn 2 pasang label-value
        digabung per baris. Dipadatkan ulang jadi 4-kolom (desc/amt/desc/amt),
        baris yg field-nya ganjil (Pack Qty, Jenis Finishing) pakai colspan="3"
        supaya tetap sejajar dgn baris 2-pasang lain. --}}
        <div class="mc-top">
            <table class="items items-2col">
                <colgroup><col style="width:14%"><col style="width:36%"><col style="width:14%"><col style="width:36%"></colgroup>
                <tbody>
                    <tr>
                        <td class="desc">{{ t('Item Name') }}</td><td class="amt">{{ $this->part->product?->product_name ?? '—' }}</td>
                        <td class="desc">{{ t('Customer Name') }}</td><td class="amt">{{ $this->part->product?->customer?->customer_name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="desc">{{ t('Part') }}</td><td class="amt">{{ $this->part->part_name }} <span class="ref">({{ $this->part->part_code }})</span></td>
                        <td class="desc">{{ t('Dimension L x W x H') }}</td><td class="amt">{{ $this->part->cm_length ?? '—' }} x {{ $this->part->cm_width ?? '—' }} x {{ $this->part->cm_height ?? '—' }} cm</td>
                    </tr>
                    <tr>
                        <td class="desc">{{ t('Die Code') }}</td><td class="amt">{{ $this->part->die_code ?? '—' }}</td>
                        <td class="desc">{{ t('Die Size') }}</td><td class="amt">{{ $this->part->die_w_cm ?? '—' }} x {{ $this->part->die_h_cm ?? '—' }} cm</td>
                    </tr>
                    <tr>
                        <td class="desc">{{ t('Material') }}</td>
                        <td class="amt">
                            {{ $this->originMaterial?->rawmat_name ?? '—' }}
                            @if (! $this->originIqpPart)
                                <div class="sp">{{ t('Mode #2 — no source Inquiry for the material yet.') }}</div>
                            @endif
                        </td>
                        <td class="desc">{{ t('Quality') }}</td><td class="amt">{{ $this->originMaterial?->gram_weight ? number_format((float) $this->originMaterial->gram_weight, 0).' gsm' : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="desc">{{ t('Raw Material Size') }}</td><td class="amt">{{ $this->part->sheet_raw_w_cm ?? '—' }} x {{ $this->part->sheet_raw_h_cm ?? '—' }} cm</td>
                        <td class="desc">{{ t('Print Size') }}</td><td class="amt">{{ $this->part->printsize_p ?? '—' }} x {{ $this->part->printsize_l ?? '—' }} cm</td>
                    </tr>
                    <tr>
                        <td class="desc">{{ t('Pack Qty') }}</td><td class="amt" colspan="3">{{ $this->part->pack_qty ?? '—' }} pcs/pack</td>
                    </tr>
                    <tr>
                        <td class="desc">{{ t('Finishing Type') }}</td><td class="amt" colspan="3">{{ $this->part->finishings->pluck('finishingType.finishing_name')->filter()->implode(', ') ?: '—' }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="mc-visual">
                @if ($this->officialArtwork)
                    <img src="{{ asset('storage/'.$this->officialArtwork->artwork_path) }}" alt="{{ t('Official artwork') }}">
                @elseif ($this->fallbackArtwork)
                    <img src="{{ asset('storage/'.$this->fallbackArtwork->file_path) }}" alt="{{ t('ProDev reference') }}">
                @else
                    <div class="mc-visual-empty">{{ t('No artwork/preview saved for this part yet.') }}</div>
                @endif
                <div class="mc-visual-cap">
                    @if ($this->layoutGroup)
                        {{ t('PRINT SHEET LAYOUT') }} ({{ $this->layoutGroup->plano_w_mm }} x {{ $this->layoutGroup->plano_h_mm }} mm) — {{ $this->layoutGroup->up_count }} UP · {{ $this->layoutGroup->method }}
                    @else
                        {{ t('No Imposition Engine result saved for this part yet.') }}
                    @endif
                </div>
            </div>
        </div>

        <div class="print-two">
            <x-print.section number="1" title="{{ t('Primary Colors') }}">
                @php($colors1 = $this->part->color_set_1 ?? [])
                @if (count($colors1))
                    <table class="items">
                        <tbody>
                            @foreach ($colors1 as $i => $c)
                                <tr><td class="desc">{{ t('Unit') }} {{ $i + 1 }}</td><td class="amt">{{ $c }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="cap">{{ t('Not filled in yet.') }}</div>
                @endif
            </x-print.section>

            <x-print.section number="2" title="{{ t('Work Sequence (Routing)') }}">
                @if ($this->part->processes->isNotEmpty())
                    <table class="items">
                        <tbody>
                            @foreach ($this->part->processes as $pp)
                                <tr>
                                    <td class="ref" style="width:12%">{{ $pp->step_seq }}</td>
                                    <td class="amt">{{ $pp->process?->process_name }}{{ $pp->machine ? ' · '.$pp->machine->machine_name : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="cap">{{ t('No routing yet.') }}</div>
                @endif
            </x-print.section>
        </div>

        <x-print.signatures>
            <x-slot:intro>
                {{ t('This Master Card becomes the official production reference once signed by all parties below.') }}
            </x-slot:intro>

            @foreach ($this->signoffs as $s)
                <x-print.signature role="{{ $s->label }}" name="{{ $s->signed_by ?? '( ................................. )' }}"
                    sub="{{ $s->signed_at?->format('d/m/Y') ?? t('Not signed yet') }}" />
            @endforeach
        </x-print.signatures>

        <x-print.footer page="{{ t('Page 1 of 1') }}">
            {{ $this->part->displayMastercardCode() }} · {{ $this->part->product?->product_name }} ·
            {{ t('printed on') }} {{ now()->format('d/m/Y H:i') }} · {{ t('official document issued by the TMERP system') }}
        </x-print.footer>
    </x-print.sheet>

    <style>
        {{-- Style KONTEN spesifik Master Card (printnotastandard.md §2) --
        tabel spesifikasi key-value + kotak visual layout cetak. Belum pernah
        ada <style> di layar ini sebelumnya (gap terpisah dari soal mockup --
        table.items/.desc/.amt/.cap dipakai tanpa definisi CSS lokal sama
        sekali, tampil polos tanpa styling). --}}
        {{-- BACKLOG.md §0 R35: A4 landscape cuma 210mm tinggi -- diukur nyata via
        browser (getBoundingClientRect), .print-page awalnya 233mm (23mm
        kelebihan) sebelum padding tabel dipadatkan di sini. --}}
        .print-doc table.items{width:100%;border-collapse:collapse;font-size:7.6pt;border:1px solid var(--print-line)}
        .print-doc table.items td{padding:3px 7px;border-bottom:1px solid var(--print-line2);vertical-align:top;line-height:1.35}
        .print-doc table.items tbody tr:last-child td{border-bottom:none}
        {{-- Label kolom pakai abu-abu netral fix (BUKAN var(--print-accent-soft),
        itu sekarang putih polos -- perlu kontras sendiri spy label tetap
        kebeda dari kolom nilai walau seluruh dokumen hitam-putih). --}}
        .print-doc table.items td.desc{width:38%;color:#000;font-weight:600;background:#eee}
        .print-doc table.items td.amt{font-weight:700;color:#000}
        {{-- .items-2col (BACKLOG.md §0 R35): 4 kolom per baris (desc/amt/desc/amt),
        lebar diatur via <colgroup> di markup, bukan .desc{width:38%} di atas
        (itu utk tabel 2-kolom biasa spt Warna Pertama/Routing, jangan dipakai
        di sini -- table-layout:fixed spy colgroup jadi otoritatif). --}}
        .print-doc table.items-2col{table-layout:fixed}
        .print-doc table.items-2col td.desc{width:auto}
        .print-doc table.items td.ref{font-family:Consolas,monospace;font-weight:700;color:#000}
        .print-doc table.items .sp{font-weight:400;color:#555;font-size:6.8pt;margin-top:2px}
        .print-doc .cap{font-size:7.4pt;color:#555;text-align:center;padding:10px 4px}

        .print-doc .mc-top{display:grid;grid-template-columns:1.15fr 1fr;gap:10px;margin-top:2px}
        .print-doc .mc-visual{border:1px solid #000;border-radius:4px;padding:6px;
            display:flex;flex-direction:column;background:#f4f4f4}
        {{-- Satu-satunya elemen yang SENGAJA tidak ikut hitam-putih -- artwork/
        preview asli tetap tampil warna aslinya (permintaan user), makanya
        TIDAK ada filter:grayscale di sini. --}}
        .print-doc .mc-visual img{width:100%;height:auto;max-height:64mm;object-fit:contain;border-radius:2px}
        .print-doc .mc-visual-empty{flex:1;min-height:50mm;display:flex;align-items:center;justify-content:center;
            color:#555;font-size:7.6pt;text-align:center;padding:8px}
        .print-doc .mc-visual-cap{color:#000;font-size:6.6pt;text-align:center;margin-top:5px;letter-spacing:.3px}

        {{-- Format tetap A4 LANDSCAPE (bukan portrait default document.blade.php)
        -- override lokal, TIDAK mengubah @page global (dokumen Nota lain tetap
        portrait). Selector sama spesifisitasnya dgn shared style tapi ditulis
        belakangan di HTML -> menang di cascade (bukan override .bw class, ini
        permanen bukan toggle). --}}
        .print-doc.landscape .print-bar{width:297mm}
        .print-doc.landscape .print-page{width:297mm;min-height:210mm}
        {{-- Muat pas 210mm tapi mepet (0mm sisa di layar, cetak asli sedikit
        lebih longgar krn padding print-mode lebih kecil) -- kecilkan strip
        tanda tangan sedikit lagi utk kasih jarak aman, bukan krn kurang
        tempat tanda tangan fisik (28px masih cukup). --}}
        .print-doc.landscape .print-sign-cell .line{height:28px;margin-bottom:2px}
        .print-doc.landscape .print-sign-b{padding:7px 9px}
        .print-doc.landscape .print-sign-intro{margin-bottom:5px}
        @media print{
            .print-doc.landscape .print-page{width:auto;min-height:auto}
            @page{size:A4 landscape;margin:0}
        }
    </style>
</x-print.document>
</div>
