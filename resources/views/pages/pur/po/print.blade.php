<?php

use App\Models\Master\FinCurrency;
use App\Models\Master\OrgCompany;
use App\Models\Pur\PoOrder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// docs/BACKLOG.md §12.7 "print PR/PO/PQ" -- PO paling kritis (dikirim ke
// supplier) jadi dikerjakan duluan. Pola sama persis quotation/print.blade.php
// (komponen resources/views/components/print/*, docs/printnotastandard.md).
// Gate cetak: HANYA setelah APPROVED (draft belum final) -- sama alasan
// quotation harus PUBLISHED dulu.
new #[Title('Purchase Order — TELASIH MAKMUR ERP')] class extends Component
{
    public int $poId;

    public function mount(int $po): void
    {
        $doc = PoOrder::findOrFail($po);

        if (in_array($doc->status, ['DRAFT', 'SUBMITTED'], true)) {
            session()->flash('error', t('This PO is not approved yet — approve it before it can be printed.'));
            $this->redirect(route('pur.po.entry', ['po' => $po]));

            return;
        }

        $this->poId = $po;
    }

    #[Computed]
    public function po(): PoOrder
    {
        return PoOrder::with(['lines' => fn ($q) => $q->orderBy('line_no')])->findOrFail($this->poId);
    }

    #[Computed]
    public function supplier()
    {
        return DB::connection('tm2mst')->table('sup_supplier')->where('id_supplier', $this->po->i_supplier_id)->first();
    }

    #[Computed]
    public function currencyCode(): string
    {
        return $this->po->i_currency_id
            ? (FinCurrency::find($this->po->i_currency_id)?->currency_code ?? 'IDR')
            : 'IDR';
    }

    #[Computed]
    public function company(): ?OrgCompany
    {
        return OrgCompany::where('activate', true)->first();
    }
};
?>

<x-print.document :theme="['accent' => '#1f6f4a', 'accent-soft' => '#e9f6ef']" info="{{ $this->po->doc_no }} · {{ $this->po->status }}">
    <x-slot:toolbar>
        <a href="{{ route('pur.po.entry', ['po' => $this->poId]) }}" class="sc" data-back>← {{ t('Back to PO') }}</a>
    </x-slot:toolbar>

    <x-print.sheet>
        <x-print.header :company="$this->company" :doc-title="t('PURCHASE ORDER')" :doc-no="$this->po->doc_no">
            <x-slot:meta>
                {{ t('Date') }}: {{ $this->po->i_doc_date?->translatedFormat('d F Y') }}
                @if ($this->po->revision_no > 0) · Rev. {{ str_pad($this->po->revision_no, 2, '0', STR_PAD_LEFT) }} @endif
                · {{ t('Currency') }}: <span class="ref">{{ $this->currencyCode }}</span>
            </x-slot:meta>
        </x-print.header>

        <x-print.parties columns="1.2fr 1fr">
            <x-print.party :label="t('TO SUPPLIER')">
                <div class="nm">{{ $this->supplier?->supplier_name ?? '—' }}</div>
                <div class="dt">
                    {{ t('Code') }}: <b>{{ $this->supplier?->supplier_code ?? '—' }}</b><br>
                    {{ $this->supplier?->default_address ?? '—' }}<br>
                    @if ($this->supplier?->contact_name) {{ t('Attn.') }} <b>{{ $this->supplier->contact_name }}</b> @endif
                    @if ($this->supplier?->phone) · {{ $this->supplier->phone }} @endif
                </div>
            </x-print.party>
            <x-print.party :label="mb_strtoupper(t('Delivery'))">
                <div class="dt">
                    {{ t('Deliver to') }}: <b>{{ $this->po->i_ship_to ?? '—' }}</b><br>
                    {{ t('Requested Delivery Date') }}: <b>{{ $this->po->i_delivery_date?->translatedFormat('d F Y') ?? '—' }}</b><br>
                    {{ t('Payment Terms') }}: <b>{{ $this->po->i_payment_term ?? '—' }}</b>
                </div>
            </x-print.party>
        </x-print.parties>

        <x-print.section number="1" :title="t('Order Details')">
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:6%">{{ t('No.') }}</th>
                        <th class="desc">{{ t('Goods/Service Description') }}</th>
                        <th class="num" style="width:11%">{{ t('Qty') }}</th>
                        <th class="num" style="width:13%">{{ t('Unit Price') }}</th>
                        <th class="num" style="width:10%">{{ t('Disc') }}</th>
                        <th class="num" style="width:16%">{{ t('Subtotal') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->po->lines as $line)
                        <tr>
                            <td class="ref">{{ $line->line_no }}</td>
                            <td class="desc">
                                <div class="nm">{{ $line->i_description }}</div>
                                @if ($line->c_item_code)
                                    <div class="sp">{{ t('Code') }}: {{ $line->c_item_code }}</div>
                                @endif
                            </td>
                            <td class="num q">{{ number_format($line->i_qty, 2, ',', '.') }} {{ $line->i_uom }}</td>
                            <td class="num">{{ number_format($line->i_price, 2, ',', '.') }}</td>
                            <td class="num">{{ $line->i_disc_pct > 0 ? number_format($line->i_disc_pct, 1).'%' : '—' }}</td>
                            <td class="num amt">{{ number_format($line->c_line_net, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totwrap">
                <div class="tot-note">
                    <h5>{{ mb_strtoupper(t('Notes')) }}</h5>
                    {{ $this->po->i_notes ?: t('Please confirm receipt of this PO & include the PO number on the Delivery Note/Invoice.') }}
                </div>
                <table class="tot">
                    <tr class="sub"><td class="k">{{ t('Taxable Base') }}</td><td class="v">{{ number_format($this->po->c_dpp, 2, ',', '.') }}</td></tr>
                    <tr class="sub"><td class="k">{{ t('VAT') }}</td><td class="v">{{ number_format($this->po->c_ppn, 2, ',', '.') }}</td></tr>
                    @if ($this->po->c_pph > 0)
                        <tr class="sub"><td class="k">{{ t('WHT Art. 23 (Services)') }}</td><td class="v">-{{ number_format($this->po->c_pph, 2, ',', '.') }}</td></tr>
                    @endif
                    <tr class="grand"><td class="k">{{ t('TOTAL PAYABLE') }}</td><td class="v">{{ $this->currencyCode }} {{ number_format($this->po->c_net_payable, 2, ',', '.') }}</td></tr>
                </table>
            </div>
        </x-print.section>

        <x-print.signatures>
            <x-slot:intro>
                {{ str_replace(':company', \App\Support\Branding\Brand::companyName(), t('This Purchase Order is officially issued by :company.')) }}
                {{ t('Please confirm availability & delivery schedule with our Purchasing PIC.') }}
            </x-slot:intro>

            <x-print.signature company="{{ t('Issued by', 'print') }}, {{ \App\Support\Branding\Brand::companyName() }}"
                :role="t('Purchasing')" name="{{ $this->po->created_by ?? '—' }}" :sub="t('Prepared')" />
            <x-print.signature company="{{ \App\Support\Branding\Brand::companyName() }}"
                :role="t('Approval')" name="( ................................. )" :sub="t('Head of Purchasing/Directors')" />
            <x-print.signature :company="t('Received & approved by')" :role="t('Supplier')"
                name="( ................................. )" :sub="t('Full name, title & stamp')" />
        </x-print.signatures>

        <x-print.footer page="{{ t('Page 1 of 1') }}">
            {{ $this->po->doc_no }} · {{ $this->supplier?->supplier_name }} ·
            {{ str_replace(':date', now()->format('d/m/Y H:i'), t('printed :date')) }} · {{ t('official document issued by the TMERP system') }}
        </x-print.footer>
    </x-print.sheet>
</x-print.document>
