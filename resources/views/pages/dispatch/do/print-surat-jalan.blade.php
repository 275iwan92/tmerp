<?php

use App\Models\Master\OrgCompany;
use App\Models\Sdi\DoDelivery;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// dispatch_tier2.md §F / Tier1 §4.1 -- Surat Jalan (eksternal, dibawa kurir)
// = Sisi B: kolom harga TIDAK ADA DI QUERY CETAK sama sekali (bukan cuma
// disembunyikan CSS, pola sama Procurement PV). $lines di sini SENGAJA
// hanya select kolom non-harga -- jangan tambah unit_price/subtotal ke
// select() ini, sekalipun cuma untuk "ditampilkan nanti".
new #[Title('Delivery Note — TELASIH MAKMUR ERP')] class extends Component
{
    public int $doId;

    public function mount(int $doId): void
    {
        $do = DoDelivery::findOrFail($doId);

        if (! in_array($do->status, ['CONFIRMED', 'SHIPPED'], true)) {
            session()->flash('error', t('This Delivery Order is not Confirmed yet — the Delivery Note cannot be printed yet.'));
            $this->redirect(route('dispatch.do.entry', ['doId' => $doId]));

            return;
        }

        $this->doId = $doId;
    }

    #[Computed]
    public function do(): DoDelivery
    {
        return DoDelivery::with('customer')->findOrFail($this->doId);
    }

    // Sisi B: select() eksplisit TANPA unit_price/subtotal -- Tier1 §4.1
    // "kolom harga tidak disertakan di query cetak sama sekali".
    #[Computed]
    public function lines()
    {
        return $this->do->lines()
            ->where('status', '<>', 'CANCELLED')
            ->with('order:id_order,co_no')
            ->select(['id_line', 'delivery_id', 'co_order_id', 'item_description', 'qty_do', 'qty_unit', 'line_no'])
            ->orderBy('line_no')
            ->get();
    }

    #[Computed]
    public function company(): ?OrgCompany
    {
        return OrgCompany::where('company_code', 'TM')->first();
    }

    #[Computed]
    public function coRefs(): string
    {
        return $this->lines->pluck('order.co_no')->filter()->unique()->implode(', ');
    }
};
?>

<x-print.document :ppn="$this->do->is_ppn" :theme="['accent' => '#0d5c46', 'accent-soft' => '#eaf3f0']" info="{{ $this->do->do_no }} · {{ t('Delivery Note') }}">
    <x-slot:toolbar>
        <a href="{{ route('dispatch.do.entry', ['doId' => $this->doId]) }}" class="sc" data-back>← {{ t('Back to DO') }}</a>
    </x-slot:toolbar>

    <x-print.sheet>
        <x-print.header :company="$this->company" :ppn="$this->do->is_ppn" :doc-title="mb_strtoupper(t('Delivery Note'))" :doc-no="$this->do->do_no">
            <x-slot:meta>
                {{ t('Date') }}: {{ $this->do->do_date?->translatedFormat('d F Y') }}
                @if ($this->do->actual_ship_date) · {{ t('Shipped on') }}: {{ $this->do->actual_ship_date->translatedFormat('d F Y') }} @endif
                <br>{{ t('Ref. CO') }}: <span class="ref">{{ $this->coRefs ?: '—' }}</span>
            </x-slot:meta>
        </x-print.header>

        <x-print.parties columns="1.2fr 1fr">
            <x-print.party :label="mb_strtoupper(t('Shipped To'))">
                <div class="nm">{{ $this->do->customer?->customer_name ?? '—' }}</div>
                <div class="dt">
                    {{ $this->do->ship_address_label }}<br>
                    {{ $this->do->ship_address_detail }}
                    @if ($this->do->ship_postal_code) · {{ $this->do->ship_postal_code }} @endif
                </div>
            </x-print.party>
            <x-print.party :label="mb_strtoupper(t('Shipped From'))">
                <div class="nm"><x-print.company-name :ppn="$this->do->is_ppn" /></div>
                <div class="dt">{{ $this->company?->address ?? '—' }}</div>
            </x-print.party>
        </x-print.parties>

        <x-print.section number="1" :title="t('Goods Detail')">
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:12%">{{ t('CO No.') }}</th>
                        <th class="desc">{{ t('Goods Description') }}</th>
                        <th class="num" style="width:20%">{{ t('Qty') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->lines as $line)
                        <tr>
                            <td class="ref">{{ $line->order?->co_no ?? '—' }}</td>
                            <td class="desc"><div class="nm">{{ $line->item_description }}</div></td>
                            <td class="num q">{{ number_format($line->qty_do, 0, ',', '.') }} {{ $line->qty_unit }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-print.section>

        <x-print.signatures :title="mb_strtoupper(t('Goods Handover'))">
            <x-print.signature :role="t('Delivered by')" :sub="t('Sender / Warehouse Checker')" name="( ................................. )" />
            <x-print.signature :role="t('Received')" :sub="t('Full name, date & recipient stamp')" name="( ................................. )" />
        </x-print.signatures>

        <x-print.footer :page="str_replace([':p', ':n'], [1, 1], t('Page :p of :n'))">{{ $this->do->do_no }} · {{ $this->do->customer?->customer_name }} · {{ t('printed on') }} {{ now()->format('d/m/Y H:i') }}</x-print.footer>
    </x-print.sheet>

    <style>
        .print-doc .print-kop .doc-meta .ref{color:var(--print-accent);font-weight:600}

        .print-doc table.items{width:100%;border-collapse:collapse;font-size:9pt;border:1px solid var(--print-line)}
        .print-doc table.items th{background:var(--print-accent);color:#fff;font-size:7pt;letter-spacing:.5px;
                       padding:6px 9px;text-align:right;font-weight:700}
        .print-doc table.items th:first-child{text-align:left}
        .print-doc table.items th.desc{text-align:left;width:55%}
        .print-doc table.items td{padding:7px 9px;border-bottom:1px solid var(--print-line2);text-align:right;
                       vertical-align:top;font-variant-numeric:tabular-nums}
        .print-doc table.items td.desc{text-align:left}
        .print-doc table.items td.desc .nm{font-weight:700;font-size:9.2pt}
        .print-doc table.items td.ref{font-family:Consolas,monospace;font-weight:700;color:var(--print-accent);font-size:8pt;text-align:left}
        .print-doc table.items td.q{font-weight:700;font-size:9.5pt}
    </style>
</x-print.document>
