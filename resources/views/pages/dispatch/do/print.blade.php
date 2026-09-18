<?php

use App\Models\Master\OrgCompany;
use App\Models\Sdi\DoDelivery;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// dispatch_tier2.md §F -- Delivery Order (internal) = Sisi A, harga tampil
// penuh. Hanya bisa diakses dari status CONFIRMED/SHIPPED (belum ada do_no
// selama DRAFT). Kerangka dari resources/views/components/print/* --
// printnotastandard.md (DO/Surat Jalan eksplisit termasuk keluarga "Nota",
// §1 tabel referensi -- BEDA dari Laporan Inventory yang landscape+navy-header).
new #[Title('Delivery Order — TELASIH MAKMUR ERP')] class extends Component
{
    public int $doId;

    public function mount(int $doId): void
    {
        $do = DoDelivery::findOrFail($doId);

        if (! in_array($do->status, ['CONFIRMED', 'SHIPPED'], true)) {
            session()->flash('error', 'Delivery Order ini belum Confirm — belum bisa dicetak.');
            $this->redirect(route('dispatch.do.entry', ['doId' => $doId]));

            return;
        }

        $this->doId = $doId;
    }

    #[Computed]
    public function do(): DoDelivery
    {
        return DoDelivery::with(['customer', 'lines' => fn ($q) => $q->where('status', '<>', 'CANCELLED')->orderBy('line_no')])->findOrFail($this->doId);
    }

    #[Computed]
    public function company(): ?OrgCompany
    {
        return OrgCompany::where('company_code', 'TM')->first();
    }

    #[Computed]
    public function totals(): array
    {
        $lines = $this->do->lines;

        return ['qty' => $lines->sum('qty_do'), 'value' => $lines->sum('subtotal')];
    }

    #[Computed]
    public function coRefs(): string
    {
        return $this->do->lines->pluck('order.co_no')->filter()->unique()->implode(', ');
    }
};
?>

<x-print.document :theme="['accent' => '#2c3e69', 'accent-soft' => '#eef1f7']" info="{{ $this->do->do_no }} · {{ $this->do->status }}">
    <x-slot:toolbar>
        <a href="{{ route('dispatch.do.entry', ['doId' => $this->doId]) }}" class="sc" data-back>← Kembali ke DO</a>
    </x-slot:toolbar>

    <x-print.sheet>
        <x-print.header :company="$this->company" doc-title="DELIVERY ORDER" :doc-no="$this->do->do_no">
            <x-slot:meta>
                Tanggal DO: {{ $this->do->do_date?->translatedFormat('d F Y') }}
                @if ($this->do->actual_ship_date) · Terkirim: {{ $this->do->actual_ship_date->translatedFormat('d F Y') }} @endif
                <br>Ref. CO: <span class="ref">{{ $this->coRefs ?: '—' }}</span>
            </x-slot:meta>
        </x-print.header>

        <x-print.parties columns="1.2fr 1fr">
            <x-print.party label="DIKIRIM KE">
                <div class="nm">{{ $this->do->customer?->customer_name ?? '—' }}</div>
                <div class="dt">
                    Kode Customer: <b>{{ $this->do->customer?->customer_code ?? '—' }}</b><br>
                    {{ $this->do->ship_address_label }}<br>
                    {{ $this->do->ship_address_detail }}
                    @if ($this->do->ship_postal_code) · {{ $this->do->ship_postal_code }} @endif
                </div>
            </x-print.party>
            <x-print.party label="DIKIRIM DARI">
                <div class="nm">{{ $this->company?->company_name ?? 'PT Telasih Makmur' }}</div>
                <div class="dt">Status: <b>{{ $this->do->status }}</b></div>
            </x-print.party>
        </x-print.parties>

        <x-print.section number="1" title="Rincian Barang">
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:12%">No. CO</th>
                        <th class="desc">Deskripsi Barang</th>
                        <th style="width:14%">Qty</th>
                        <th style="width:14%">Harga Satuan</th>
                        <th style="width:16%">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->do->lines as $line)
                        <tr>
                            <td class="ref">{{ $line->order?->co_no ?? '—' }}</td>
                            <td class="desc"><div class="nm">{{ $line->item_description }}</div></td>
                            <td class="q">{{ number_format($line->qty_do, 0, ',', '.') }} {{ $line->qty_unit }}</td>
                            <td>{{ number_format($line->unit_price, 0, ',', '.') }}</td>
                            <td class="amt">{{ number_format($line->subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totwrap">
                <div class="tot-note">
                    <h5>CATATAN</h5>
                    Dokumen internal Sales/Finance — dasar verifikasi sebelum barang berangkat.
                    Untuk salinan tanpa harga (dibawa kurir), lihat cetakan Surat Jalan.
                </div>
                <table class="tot">
                    <tr class="grand"><td class="k">TOTAL NILAI</td><td class="v">Rp {{ number_format($this->totals['value'], 0, ',', '.') }}</td></tr>
                </table>
            </div>
        </x-print.section>

        <x-print.signatures>
            <x-print.signature company="{{ $this->company?->company_name ?? 'PT Telasih Makmur' }}" role="Disiapkan"
                name="( ................................. )" sub="Admin Sales / Dispatch" />
            <x-print.signature role="Diperiksa" name="( ................................. )" sub="Kepala Gudang" />
        </x-print.signatures>

        <x-print.footer page="Halaman 1 dari 1">{{ $this->do->do_no }} · {{ $this->do->customer?->customer_name }} · dicetak {{ now()->format('d/m/Y H:i') }}</x-print.footer>
    </x-print.sheet>

    <style>
        {{-- Style KONTEN spesifik DO -- kerangka (bar/page/kop/parties/sec-h/sign/foot)
             sudah dari resources/views/components/print/*, printnotastandard.md §2. --}}
        .print-doc .print-kop .doc-meta .ref{color:var(--print-accent);font-weight:600}

        .print-doc table.items{width:100%;border-collapse:collapse;font-size:8.4pt;border:1px solid var(--print-line)}
        .print-doc table.items th{background:var(--print-accent);color:#fff;font-size:6.8pt;letter-spacing:.5px;
                       padding:5px 8px;text-align:right;font-weight:700}
        .print-doc table.items th:first-child{text-align:left}
        .print-doc table.items th.desc{text-align:left;width:40%}
        .print-doc table.items td{padding:6px 8px;border-bottom:1px solid var(--print-line2);text-align:right;
                       vertical-align:top;font-variant-numeric:tabular-nums}
        .print-doc table.items td.desc{text-align:left}
        .print-doc table.items td.desc .nm{font-weight:700;font-size:8.8pt}
        .print-doc table.items td.ref{font-family:Consolas,monospace;font-weight:700;color:var(--print-accent);font-size:7.8pt;text-align:left}
        .print-doc table.items td.q{font-weight:600}
        .print-doc table.items td.amt{font-weight:700}

        .print-doc .totwrap{display:grid;grid-template-columns:1fr 78mm;gap:10px;margin-top:8px}
        .print-doc .tot-note{border:1px solid var(--print-line);padding:7px 9px;font-size:7.6pt;line-height:1.55}
        .print-doc .tot-note h5{font-size:6.6pt;letter-spacing:.9px;color:var(--print-muted);font-weight:700;margin-bottom:3px}
        .print-doc table.tot{width:100%;border-collapse:collapse;font-size:8.6pt}
        .print-doc table.tot td{padding:4px 9px;font-variant-numeric:tabular-nums}
        .print-doc table.tot tr.grand td{background:var(--print-accent);color:#fff;font-weight:700;font-size:11pt;padding:7px 9px}
        .print-doc table.tot tr.grand td.k{color:#fff;letter-spacing:.5px;text-align:left}
        .print-doc table.tot tr.grand td.v{text-align:right}
    </style>
</x-print.document>
