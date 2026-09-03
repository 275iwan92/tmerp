<?php

use App\Models\Master\OrgCompany;
use App\Models\Sdi\CoOrder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// docs/BACKLOG.md §12.7 "Nota Quotation & Nota CO print" -- Quotation print
// sudah ada (quotation/print.blade.php), CO belum. Nota CO = konfirmasi
// pesanan resmi ke customer setelah CO di-Confirm (order acknowledgment),
// pola sama persis (resources/views/components/print/*). Gate: CONFIRMED
// saja (CO cuma 2 status: DRAFT/CONFIRMED).
new #[Title('Nota Customer Order — TELASIH MAKMUR ERP')] class extends Component
{
    public int $orderId;

    public function mount(int $order): void
    {
        $doc = CoOrder::findOrFail($order);

        if ($doc->doc_status !== 'CONFIRMED') {
            session()->flash('error', 'CO ini belum di-Confirm — confirm dulu sebelum bisa dicetak.');
            $this->redirect(route('co.entry', ['order' => $order]));

            return;
        }

        $this->orderId = $order;
    }

    #[Computed]
    public function order(): CoOrder
    {
        return CoOrder::with(['items', 'quotation.customer', 'externalCosts'])->findOrFail($this->orderId);
    }

    #[Computed]
    public function totals(): array
    {
        return $this->order->totals();
    }

    #[Computed]
    public function company(): ?OrgCompany
    {
        return OrgCompany::where('activate', true)->first();
    }
};
?>

<x-print.document :theme="['accent' => '#1f6f4a', 'accent-soft' => '#e9f6ef']" info="{{ $this->order->co_no }} · {{ $this->order->doc_status }}">
    <x-slot:toolbar>
        <a href="{{ route('co.entry', ['order' => $this->orderId]) }}" class="sc">← Kembali ke CO</a>
    </x-slot:toolbar>

    <x-print.sheet>
        <x-print.header :company="$this->company" doc-title="NOTA CUSTOMER ORDER" :doc-no="$this->order->co_no">
            <x-slot:meta>
                Tanggal: {{ $this->order->co_date?->translatedFormat('d F Y') }}
                @if ($this->order->customer_po_no) · No. PO Customer: <span class="ref">{{ $this->order->customer_po_no }}</span> @endif
                · Ref. Quotation: <span class="ref">{{ $this->order->quotation?->quotation_no ?? '—' }}</span>
            </x-slot:meta>
        </x-print.header>

        <x-print.parties columns="1.2fr 1fr">
            <x-print.party label="KEPADA YTH.">
                <div class="nm">{{ $this->order->quotation?->customer?->customer_name ?? '—' }}</div>
                <div class="dt">
                    Kode Customer: <b>{{ $this->order->quotation?->customer?->customer_code ?? '—' }}</b>
                </div>
            </x-print.party>
            <x-print.party label="ALAMAT PENGIRIMAN">
                <div class="dt">
                    {{ $this->order->ship_address_label ?? '—' }}<br>
                    {{ $this->order->ship_address_detail ?? '' }}
                    @if ($this->order->ship_postal_code) {{ $this->order->ship_postal_code }} @endif
                </div>
            </x-print.party>
        </x-print.parties>

        <x-print.section number="1" title="Rincian Pesanan">
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:6%">No.</th>
                        <th class="desc">Deskripsi Produk &amp; Spesifikasi</th>
                        <th style="width:11%">Qty</th>
                        <th style="width:13%">Harga Satuan</th>
                        <th style="width:16%">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->order->items as $item)
                        <tr>
                            <td class="ref">{{ $item->line_seq }}</td>
                            <td class="desc">
                                <div class="nm">{{ $item->description }}</div>
                                @if ($item->spec_note)
                                    <div class="sp">{{ $item->spec_note }}</div>
                                @endif
                            </td>
                            <td class="q">{{ number_format($item->qty, 0, ',', '.') }} {{ $item->qty_unit }}</td>
                            <td>{{ number_format($item->unit_price, 0, ',', '.') }}{{ $item->discount_pct > 0 ? ' (-'.number_format($item->discount_pct, 1).'%)' : '' }}</td>
                            <td class="amt">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totwrap">
                <div class="tot-note">
                    <h5>CATATAN</h5>
                    {{ $this->order->notes ?: 'Pesanan ini mengikat setelah CO dikonfirmasi. Perubahan jumlah/spesifikasi harus melalui revisi tertulis.' }}
                </div>
                <table class="tot">
                    <tr class="sub"><td class="k">Subtotal Item</td><td class="v">{{ number_format($this->totals['item_subtotal'], 0, ',', '.') }}</td></tr>
                    @if ($this->totals['discount_amount'] > 0)
                        <tr class="sub"><td class="k">Diskon</td><td class="v">-{{ number_format($this->totals['discount_amount'], 0, ',', '.') }}</td></tr>
                    @endif
                    @if ($this->totals['external_cost'] > 0)
                        <tr class="sub"><td class="k">Biaya Eksternal</td><td class="v">{{ number_format($this->totals['external_cost'], 0, ',', '.') }}</td></tr>
                    @endif
                    <tr class="sub"><td class="k">DPP</td><td class="v">{{ number_format($this->totals['dpp'], 0, ',', '.') }}</td></tr>
                    <tr class="sub"><td class="k">PPN {{ number_format($this->totals['ppn_pct'], 0) }}%</td><td class="v">{{ number_format($this->totals['ppn'], 0, ',', '.') }}</td></tr>
                    @if ($this->totals['wht23'] > 0)
                        <tr class="sub"><td class="k">PPh 23 (Jasa)</td><td class="v">-{{ number_format($this->totals['wht23'], 0, ',', '.') }}</td></tr>
                    @endif
                    <tr class="grand"><td class="k">TOTAL TAGIHAN</td><td class="v">Rp {{ number_format($this->totals['net_receivable'], 0, ',', '.') }}</td></tr>
                </table>
            </div>
        </x-print.section>

        <x-print.signatures>
            <x-slot:intro>
                Nota ini adalah konfirmasi resmi pesanan setelah CO dikonfirmasi oleh {{ $this->company?->company_name ?? 'PT Telasih Makmur' }}.
                Mohon simpan dokumen ini sebagai acuan pengiriman &amp; penagihan.
            </x-slot:intro>

            <x-print.signature company="Dikonfirmasi oleh, {{ $this->company?->company_name ?? 'PT Telasih Makmur' }}"
                role="Sales" name="{{ $this->order->updated_by ?? '—' }}" sub="Order Confirmed" />
            <x-print.signature company="Diterima &amp; disetujui oleh" role="Customer"
                name="( ................................. )" sub="Nama jelas, jabatan &amp; stempel" />
        </x-print.signatures>

        <x-print.footer page="Halaman 1 dari 1">
            {{ $this->order->co_no }} · {{ $this->order->quotation?->customer?->customer_name }} ·
            dicetak {{ now()->format('d/m/Y H:i') }} · dokumen sah terbit sistem TMERP
        </x-print.footer>
    </x-print.sheet>
</x-print.document>
