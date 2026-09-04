<?php

use App\Models\Master\OrgCompany;
use App\Models\Master\ProdMastercardSignoff;
use App\Models\Master\ProdPart;
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
        $origin = \App\Models\Sdi\IqpPart::where('prod_part_id', $this->partId)->whereHas(
            'inquiry', fn ($q) => $q->where('stat_confirm', true)->whereNotIn('sub_state', ['DRAFT', 'CANCELLED', 'LOST'])
        )->value('inquiry_id');

        return [
            'inquiry' => [
                'label' => '1. Inquiry',
                'url' => $origin ? route('inquiry.form', ['id' => $origin]) : null,
                'reason' => 'Part ini Mode #2 (manual/standalone) -- tidak berasal dari Inquiry mana pun.',
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
    <x-prodev.wizard-bar current="mastercard" :steps="$this->wizardSteps" />
</div>
<x-print.document :theme="['accent' => '#8a5a00', 'accent-soft' => '#fdf3e2']" info="{{ $this->part->displayMastercardCode() }}">
    <x-slot:toolbar>
        <a href="{{ route('production.prodev', ['part' => $this->partId]) }}" class="sc">← Kembali ke ProDev</a>
    </x-slot:toolbar>

    <x-print.sheet>
        <x-print.header :company="$this->company" doc-title="MASTER CARD" :doc-no="$this->part->displayMastercardCode()">
            <x-slot:meta>
                No. Desain: <span class="ref">{{ $this->part->product?->design_no ?? '—' }}</span>
                · Dicetak {{ now()->format('d/m/Y H:i') }}
            </x-slot:meta>
        </x-print.header>

        <x-print.parties columns="1.2fr 1fr">
            <x-print.party label="PRODUK">
                <div class="nm">{{ $this->part->product?->product_name }}</div>
                <div class="dt">
                    Customer: <b>{{ $this->part->product?->customer?->customer_name ?? '—' }}</b><br>
                    Part: <b>{{ $this->part->part_name }}</b> ({{ $this->part->part_code }})
                </div>
            </x-print.party>
            <x-print.party label="DIMENSI">
                <div class="dt">
                    Badan (P x L): <b>{{ $this->part->cm_length }} x {{ $this->part->cm_width }} cm</b><br>
                    Bahan Mentah: <b>{{ $this->part->sheet_raw_w_cm ?? '—' }} x {{ $this->part->sheet_raw_h_cm ?? '—' }} cm</b><br>
                    Cetak: <b>{{ $this->part->printsize_p ?? '—' }} x {{ $this->part->printsize_l ?? '—' }} cm</b>
                </div>
            </x-print.party>
        </x-print.parties>

        <x-print.section number="1" title="Spesifikasi Teknis">
            <table class="items">
                <tbody>
                    <tr><td class="desc">Kode Pisau</td><td class="amt">{{ $this->part->die_code ?? '—' }}</td></tr>
                    <tr><td class="desc">Ukuran Pisau</td><td class="amt">{{ $this->part->die_w_cm ?? '—' }} x {{ $this->part->die_h_cm ?? '—' }} cm</td></tr>
                    <tr><td class="desc">Bentuk Sambungan</td><td class="amt">{{ $this->part->joint_type ?? '—' }}</td></tr>
                    <tr><td class="desc">Warna Sisi Pertama</td><td class="amt">{{ implode(', ', $this->part->color_set_1 ?? []) ?: '—' }}</td></tr>
                    <tr><td class="desc">Warna Sisi Kedua</td><td class="amt">{{ implode(', ', $this->part->color_set_2 ?? []) ?: '—' }}</td></tr>
                    <tr><td class="desc">Pack Qty</td><td class="amt">{{ $this->part->pack_qty ?? '—' }} pcs/pack</td></tr>
                    <tr><td class="desc">Lidah (Flap)</td><td class="amt">{{ $this->part->flap_side ?? '—' }} · {{ $this->part->flap_length_mm ?? '—' }}mm</td></tr>
                </tbody>
            </table>
        </x-print.section>

        <x-print.section number="2" title="Isi Dalam Satu Cetakan (Imposition)">
            @if ($this->layoutGroup)
                <table class="items">
                    <tbody>
                        <tr><td class="desc">Plano</td><td class="amt">{{ $this->layoutGroup->plano_w_mm }} x {{ $this->layoutGroup->plano_h_mm }} mm</td></tr>
                        <tr><td class="desc">Metode</td><td class="amt">{{ $this->layoutGroup->method }}</td></tr>
                        <tr><td class="desc">Isi (UP)</td><td class="amt">{{ $this->layoutGroup->up_count }}</td></tr>
                        <tr><td class="desc">Efisiensi</td><td class="amt">{{ number_format($this->layoutGroup->efficiency_pct, 0) }}%</td></tr>
                        <tr><td class="desc">Status</td><td class="amt">{{ $this->layoutGroup->status }}</td></tr>
                    </tbody>
                </table>
            @else
                <div class="cap">Belum ada hasil Engine Imposition tersimpan untuk part ini.</div>
            @endif
        </x-print.section>

        <x-print.section number="3" title="Urutan Pekerjaan (Routing)">
            @if ($this->part->processes->isNotEmpty())
                <table class="items">
                    <thead><tr><th style="width:10%">Seq</th><th class="desc">Proses</th><th>Mesin</th></tr></thead>
                    <tbody>
                        @foreach ($this->part->processes as $pp)
                            <tr><td class="ref">{{ $pp->step_seq }}</td><td class="desc">{{ $pp->process?->process_name }}</td><td class="amt">{{ $pp->machine?->machine_name ?? '—' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="cap">Belum ada routing.</div>
            @endif
        </x-print.section>

        <x-print.section number="4" title="Finishing">
            @if ($this->part->finishings->isNotEmpty())
                <table class="items">
                    <thead><tr><th class="desc">Jenis</th><th>Catatan</th></tr></thead>
                    <tbody>
                        @foreach ($this->part->finishings as $f)
                            <tr><td class="desc">{{ $f->finishingType?->finishing_name }}</td><td class="amt">{{ $f->notes ?? '—' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="cap">Tidak ada finishing.</div>
            @endif
        </x-print.section>

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
</x-print.document>
</div>
