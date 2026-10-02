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
            session()->flash('error', t('This CO is not Confirmed yet — confirm it first before it can be printed.'));
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
        <a href="{{ route('co.entry', ['order' => $this->orderId]) }}" class="sc" data-back>← {{ t('Back to CO') }}</a>
    </x-slot:toolbar>

    <x-print.sheet>
        <x-print.header :company="$this->company" :doc-title="t('CUSTOMER ORDER NOTE')" :doc-no="$this->order->co_no">
            <x-slot:meta>
                {{ t('Date') }}: {{ $this->order->co_date?->translatedFormat('d F Y') }}
                @if ($this->order->customer_po_no) · {{ t('Customer PO Number') }}: <span class="ref">{{ $this->order->customer_po_no }}</span> @endif
                · {{ t('Ref. Quotation') }}: <span class="ref">{{ $this->order->quotation?->quotation_no ?? '—' }}</span>
            </x-slot:meta>
        </x-print.header>

        <x-print.parties columns="1.2fr 1fr">
            <x-print.party :label="mb_strtoupper(t('Addressed to'))">
                <div class="nm">{{ $this->order->quotation?->customer?->customer_name ?? '—' }}</div>
                <div class="dt">
                    {{ t('Customer Code') }}: <b>{{ $this->order->quotation?->customer?->customer_code ?? '—' }}</b>
                </div>
            </x-print.party>
            <x-print.party :label="mb_strtoupper(t('Shipping Address'))">
                <div class="dt">
                    {{ $this->order->ship_address_label ?? '—' }}<br>
                    {{ $this->order->ship_address_detail ?? '' }}
                    @if ($this->order->ship_postal_code) {{ $this->order->ship_postal_code }} @endif
                </div>
            </x-print.party>
        </x-print.parties>

        <x-print.section number="1" :title="t('Order Details')">
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:6%">{{ t('No.') }}</th>
                        <th class="desc">{{ t('Product Description & Specification') }}</th>
                        <th class="num" style="width:11%">{{ t('Qty') }}</th>
                        <th class="num" style="width:13%">{{ t('Unit Price') }}</th>
                        <th class="num" style="width:16%">{{ t('Subtotal') }}</th>
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
                            <td class="num q">{{ number_format($item->qty, 0, ',', '.') }} {{ $item->qty_unit }}</td>
                            <td class="num">{{ number_format($item->unit_price, 0, ',', '.') }}{{ $item->discount_pct > 0 ? ' (-'.number_format($item->discount_pct, 1).'%)' : '' }}</td>
                            <td class="num amt">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totwrap">
                <div class="tot-note">
                    <h5>{{ mb_strtoupper(t('Notes')) }}</h5>
                    {{ $this->order->notes ?: t('This order is binding once the CO is confirmed.').' '.t('Changes to quantity/specification must go through a written revision.') }}
                </div>
                <table class="tot">
                    <tr class="sub"><td class="k">{{ t('Item Subtotal') }}</td><td class="v">{{ number_format($this->totals['item_subtotal'], 0, ',', '.') }}</td></tr>
                    @if ($this->totals['discount_amount'] > 0)
                        <tr class="sub"><td class="k">{{ t('Discount') }}</td><td class="v">-{{ number_format($this->totals['discount_amount'], 0, ',', '.') }}</td></tr>
                    @endif
                    @if ($this->totals['external_cost'] > 0)
                        <tr class="sub"><td class="k">{{ t('External Cost') }}</td><td class="v">{{ number_format($this->totals['external_cost'], 0, ',', '.') }}</td></tr>
                    @endif
                    <tr class="sub"><td class="k">{{ t('DPP') }}</td><td class="v">{{ number_format($this->totals['dpp'], 0, ',', '.') }}</td></tr>
                    <tr class="sub"><td class="k">{{ t('VAT') }} {{ number_format($this->totals['ppn_pct'], 0) }}%</td><td class="v">{{ number_format($this->totals['ppn'], 0, ',', '.') }}</td></tr>
                    @if ($this->totals['wht23'] > 0)
                        <tr class="sub"><td class="k">{{ t('WHT 23 (Service)') }}</td><td class="v">-{{ number_format($this->totals['wht23'], 0, ',', '.') }}</td></tr>
                    @endif
                    <tr class="grand"><td class="k">{{ mb_strtoupper(t('Total Bill')) }}</td><td class="v">Rp {{ number_format($this->totals['net_receivable'], 0, ',', '.') }}</td></tr>
                </table>
            </div>
        </x-print.section>

        <x-print.signatures>
            <x-slot:intro>
                {{ str_replace(':company', \App\Support\Branding\Brand::companyName(), t('This note is the official order confirmation after the CO is confirmed by :company.')) }}
                {{ t('Please keep this document as a reference for delivery & invoicing.') }}
            </x-slot:intro>

            <x-print.signature :company="t('Confirmed by,').' '.\App\Support\Branding\Brand::companyName()"
                :role="t('Sales')" name="{{ $this->order->updated_by ?? '—' }}" :sub="t('Order Confirmed')" />
            <x-print.signature :company="t('Received & approved by')" :role="t('Customer')"
                name="( ................................. )" :sub="t('Full name, position & stamp')" />
        </x-print.signatures>

        <x-print.footer :page="str_replace([':p', ':n'], [1, 1], t('Page :p of :n'))">
            {{ $this->order->co_no }} · {{ $this->order->quotation?->customer?->customer_name }} ·
            {{ t('printed on') }} {{ now()->format('d/m/Y H:i') }} · {{ t('valid document issued by the TMERP system') }}
        </x-print.footer>
    </x-print.sheet>
</x-print.document>
