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
            ->whereHas('inquiry', fn ($q) => $q->where('stat_confirm', true)->whereNotIn('sub_state', ['DRAFT', 'CANCELLED', 'LOST']))
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
                'label' => '1. Inquiry',
                'url' => $origin
                    ? route('inquiry.form', ['id' => $origin, 'view' => 1])
                    : route('inquiry.form', ['view' => 1, 'from_part' => $this->partId]),
            ],
            'development' => [
                'label' => '2. Development',
                'url' => route('production.prodev', ['part' => $this->partId]),
            ],
            'costing' => [
                'label' => '3. Costing',
                'url' => $origin ? route('inquiry.costing', ['inquiry' => $origin, 'view' => 1]) : null,
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
    public function signoffs()
    {
        $existing = ProdMastercardSignoff::where('product_id', $this->part->product_id)
            ->get()->keyBy('role_name');

        $roles = [
            'DESAINER' => 'Desainer / Product Development',
            'MARKETING' => 'Sales Officer',
            'PPIC' => 'PPIC',
            'QA' => 'Quality Control',
            'PLANT_MANAGER' => 'Kepala Pabrik',
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
    <x-prodev.wizard-bar current="mastercard" :steps="$this->wizardSteps" :back-url="route('production.prodev.list')" />
</div>
<x-print.document :theme="['accent' => '#8a5a00', 'accent-soft' => '#fdf3e2']" info="{{ $this->part->displayMastercardCode() }}">
    <x-slot:toolbar>
        <a href="{{ route('production.prodev', ['part' => $this->partId]) }}" class="sc">← Kembali ke ProDev</a>
    </x-slot:toolbar>

    <x-print.sheet>
        <x-print.header :company="$this->company" doc-title="MASTER CARD / KARTU INDUK" :doc-no="$this->part->displayMastercardCode()">
            <x-slot:meta>
                No. Desain: <span class="ref">{{ $this->part->product?->design_no ?? '—' }}</span>
                · Dicetak {{ now()->format('d/m/Y H:i') }}
            </x-slot:meta>
        </x-print.header>

        {{-- Layout 2-kolom ala mockup_mastercard.jpg: kiri tabel spesifikasi
        ringkas, kanan visual layout cetak (artwork resmi/referensi ProDev,
        BUKAN dieline generated -- part.dieline_ref cuma teks referensi. --}}
        <div class="mc-top">
            <table class="items">
                <tbody>
                    <tr><td class="desc">Nama Barang</td><td class="amt">{{ $this->part->product?->product_name ?? '—' }}</td></tr>
                    <tr><td class="desc">Nama Pelanggan</td><td class="amt">{{ $this->part->product?->customer?->customer_name ?? '—' }}</td></tr>
                    <tr><td class="desc">Part</td><td class="amt">{{ $this->part->part_name }} <span class="ref">({{ $this->part->part_code }})</span></td></tr>
                    <tr><td class="desc">Dimensi P x L x T</td><td class="amt">{{ $this->part->cm_length ?? '—' }} x {{ $this->part->cm_width ?? '—' }} x {{ $this->part->cm_height ?? '—' }} cm</td></tr>
                    <tr><td class="desc">Kode Pisau</td><td class="amt">{{ $this->part->die_code ?? '—' }}</td></tr>
                    <tr><td class="desc">Ukuran Pisau</td><td class="amt">{{ $this->part->die_w_cm ?? '—' }} x {{ $this->part->die_h_cm ?? '—' }} cm</td></tr>
                    <tr>
                        <td class="desc">Material</td>
                        <td class="amt">
                            {{ $this->originMaterial?->rawmat_name ?? '—' }}
                            @if (! $this->originIqpPart)
                                <div class="sp">Part ini Mode #2 (manual/standalone) — belum ada Inquiry sumber material.</div>
                            @endif
                        </td>
                    </tr>
                    <tr><td class="desc">Kualitas</td><td class="amt">{{ $this->originMaterial?->gram_weight ? number_format((float) $this->originMaterial->gram_weight, 0).' gsm' : '—' }}</td></tr>
                    <tr><td class="desc">Ukuran Bahan</td><td class="amt">{{ $this->part->sheet_raw_w_cm ?? '—' }} x {{ $this->part->sheet_raw_h_cm ?? '—' }} cm</td></tr>
                    <tr><td class="desc">Ukuran Cetak</td><td class="amt">{{ $this->part->printsize_p ?? '—' }} x {{ $this->part->printsize_l ?? '—' }} cm</td></tr>
                    <tr><td class="desc">Pack Qty</td><td class="amt">{{ $this->part->pack_qty ?? '—' }} pcs/pack</td></tr>
                    <tr><td class="desc">Jenis Finishing</td><td class="amt">{{ $this->part->finishings->pluck('finishingType.finishing_name')->filter()->implode(', ') ?: '—' }}</td></tr>
                </tbody>
            </table>

            <div class="mc-visual">
                @if ($this->officialArtwork)
                    <img src="{{ asset('storage/'.$this->officialArtwork->artwork_path) }}" alt="Artwork resmi">
                @elseif ($this->fallbackArtwork)
                    <img src="{{ asset('storage/'.$this->fallbackArtwork->file_path) }}" alt="Referensi ProDev">
                @else
                    <div class="mc-visual-empty">Belum ada artwork/preview tersimpan untuk part ini.</div>
                @endif
                <div class="mc-visual-cap">
                    @if ($this->layoutGroup)
                        LAYOUT LEMBAR CETAK ({{ $this->layoutGroup->plano_w_mm }} x {{ $this->layoutGroup->plano_h_mm }} mm) — {{ $this->layoutGroup->up_count }} UP · {{ $this->layoutGroup->method }}
                    @else
                        Belum ada hasil Engine Imposition tersimpan untuk part ini.
                    @endif
                </div>
            </div>
        </div>

        <div class="print-two">
            <x-print.section number="1" title="Warna Pertama">
                @php($colors1 = $this->part->color_set_1 ?? [])
                @if (count($colors1))
                    <table class="items">
                        <tbody>
                            @foreach ($colors1 as $i => $c)
                                <tr><td class="desc">Unit {{ $i + 1 }}</td><td class="amt">{{ $c }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="cap">Belum diisi.</div>
                @endif
            </x-print.section>

            <x-print.section number="2" title="Urutan Pekerjaan (Routing)">
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
                    <div class="cap">Belum ada routing.</div>
                @endif
            </x-print.section>
        </div>

        @php($finishingNotes = $this->part->finishings->filter(fn ($f) => filled($f->notes)))
        @if ($finishingNotes->isNotEmpty())
            <x-print.section number="3" title="Catatan Finishing">
                <table class="items">
                    <tbody>
                        @foreach ($finishingNotes as $f)
                            <tr><td class="desc">{{ $f->finishingType?->finishing_name }}</td><td class="amt">{{ $f->notes }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </x-print.section>
        @endif

        <x-print.signatures>
            <x-slot:intro>
                Master Card ini berlaku sebagai acuan resmi produksi setelah ditandatangani seluruh pihak berikut.
            </x-slot:intro>

            @foreach ($this->signoffs as $s)
                <x-print.signature role="{{ $s->label }}" name="{{ $s->signed_by ?? '( ................................. )' }}"
                    sub="{{ $s->signed_at?->format('d/m/Y') ?? 'Belum ditandatangani' }}" />
            @endforeach
        </x-print.signatures>

        <x-print.footer page="Halaman 1 dari 1">
            {{ $this->part->displayMastercardCode() }} · {{ $this->part->product?->product_name }} ·
            dicetak {{ now()->format('d/m/Y H:i') }} · dokumen sah terbit sistem TMERP
        </x-print.footer>
    </x-print.sheet>

    <style>
        {{-- Style KONTEN spesifik Master Card (printnotastandard.md §2) --
        tabel spesifikasi key-value + kotak visual layout cetak. Belum pernah
        ada <style> di layar ini sebelumnya (gap terpisah dari soal mockup --
        table.items/.desc/.amt/.cap dipakai tanpa definisi CSS lokal sama
        sekali, tampil polos tanpa styling). --}}
        .print-doc table.items{width:100%;border-collapse:collapse;font-size:8pt;border:1px solid var(--print-line)}
        .print-doc table.items td{padding:5px 8px;border-bottom:1px solid var(--print-line2);vertical-align:top}
        .print-doc table.items tbody tr:last-child td{border-bottom:none}
        .print-doc table.items td.desc{width:38%;color:var(--print-muted);font-weight:600;background:var(--print-accent-soft)}
        .print-doc table.items td.amt{font-weight:700}
        .print-doc table.items td.ref{font-family:Consolas,monospace;font-weight:700;color:var(--print-accent)}
        .print-doc table.items .sp{font-weight:400;color:var(--print-muted);font-size:6.8pt;margin-top:2px}
        .print-doc .cap{font-size:7.4pt;color:var(--print-muted);text-align:center;padding:10px 4px}

        .print-doc .mc-top{display:grid;grid-template-columns:1.15fr 1fr;gap:10px;margin-top:2px}
        .print-doc .mc-visual{border:1px solid var(--print-line);border-radius:4px;padding:6px;
            display:flex;flex-direction:column;background:#0b1220}
        .print-doc .mc-visual img{width:100%;height:auto;max-height:64mm;object-fit:contain;border-radius:2px}
        .print-doc .mc-visual-empty{flex:1;min-height:50mm;display:flex;align-items:center;justify-content:center;
            color:#8a94a6;font-size:7.6pt;text-align:center;padding:8px}
        .print-doc .mc-visual-cap{color:#c6ccd6;font-size:6.6pt;text-align:center;margin-top:5px;letter-spacing:.3px}
    </style>
</x-print.document>
</div>
