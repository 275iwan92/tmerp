<?php

use App\Models\Master\FinCurrency;
use App\Models\Master\OrgCompany;
use App\Models\Pur\PqQuotation;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// docs/BACKLOG.md §12.7 "print PR/PO/PQ" -- PQ = catatan harga penawaran 1
// supplier per dokumen (pq_quotation.i_supplier_id tunggal). Print berfungsi
// sbg lembar rekap penawaran resmi (internal, dipakai bandingkan >1 PQ utk
// 1 PR yang sama saat memutuskan pemenang) -- BUKAN RFQ letter ke supplier
// (itu di luar scope, arah dokumen ini catat HASIL bukan minta penawaran).
// Gate cetak: CONFIRMED saja (2 status total: DRAFT/CONFIRMED).
new #[Title('Purchase Quotation — TELASIH MAKMUR ERP')] class extends Component
{
    public int $pqId;

    public function mount(int $pq): void
    {
        $doc = PqQuotation::findOrFail($pq);

        if ($doc->status === 'DRAFT') {
            session()->flash('error', t('This PQ is still a draft — confirm it before it can be printed.'));
            $this->redirect(route('pur.pq.entry', ['pq' => $pq]));

            return;
        }

        $this->pqId = $pq;
    }

    #[Computed]
    public function pq(): PqQuotation
    {
        return PqQuotation::with(['lines' => fn ($q) => $q->orderBy('line_no'), 'requisition'])->findOrFail($this->pqId);
    }

    #[Computed]
    public function supplier()
    {
        return DB::connection('tm2mst')->table('sup_supplier')->where('id_supplier', $this->pq->i_supplier_id)->first();
    }

    #[Computed]
    public function currencyCode(): string
    {
        return $this->pq->i_currency_id
            ? (FinCurrency::find($this->pq->i_currency_id)?->currency_code ?? 'IDR')
            : 'IDR';
    }

    #[Computed]
    public function company(): ?OrgCompany
    {
        return OrgCompany::where('activate', true)->first();
    }
};
?>

<x-print.document :theme="['accent' => '#7c5cd6', 'accent-soft' => '#f1ecfc']" info="{{ $this->pq->doc_no }} · {{ $this->pq->status }}">
    <x-slot:toolbar>
        <a href="{{ route('pur.pq.entry', ['pq' => $this->pqId]) }}" class="sc" data-back>← {{ t('Back to PQ') }}</a>
    </x-slot:toolbar>

    <x-print.sheet>
        <x-print.header :company="$this->company" :doc-title="t('PURCHASE QUOTATION (QUOTE SUMMARY)')" :doc-no="$this->pq->doc_no">
            <x-slot:meta>
                {{ t('Date') }}: {{ $this->pq->i_doc_date?->translatedFormat('d F Y') }}
                · {{ t('Valid Until') }}: <span class="ref">{{ $this->pq->i_quotation_valid_until?->translatedFormat('d F Y') ?? '—' }}</span>
                · {{ t('Currency') }}: <span class="ref">{{ $this->currencyCode }}</span>
            </x-slot:meta>
        </x-print.header>

        <x-print.parties columns="1fr 1fr">
            <x-print.party :label="t('QUOTING SUPPLIER')">
                <div class="nm">{{ $this->supplier?->supplier_name ?? '—' }}</div>
                <div class="dt">{{ t('Code') }}: <b>{{ $this->supplier?->supplier_code ?? '—' }}</b></div>
            </x-print.party>
            <x-print.party :label="mb_strtoupper(t('Reference'))">
                <div class="dt">
                    {{ t('PR Ref.') }}: <b>{{ $this->pq->requisition?->doc_no ?? '—' }}</b><br>
                    {{ t('Payment Terms') }}: <b>{{ $this->pq->i_payment_term ?? '—' }}</b><br>
                    {{ t('Lead Time') }}: <b>{{ str_replace(':n', $this->pq->i_lead_time_day ?? '—', t(':n days')) }}</b>
                </div>
            </x-print.party>
        </x-print.parties>

        <x-print.section number="1" :title="t('Offer Detail')">
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:6%">{{ t('No.') }}</th>
                        <th class="desc">{{ t('Item Description') }}</th>
                        <th class="num" style="width:11%">{{ t('Qty') }}</th>
                        <th class="num" style="width:15%">{{ t('Price') }} ({{ $this->currencyCode }})</th>
                        <th class="num" style="width:16%">{{ t('Subtotal') }} (IDR)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->pq->lines as $line)
                        <tr>
                            <td class="ref">{{ $line->line_no }}</td>
                            <td class="desc">
                                <div class="nm">{{ $line->i_description }}</div>
                                @if ($line->i_notes)
                                    <div class="sp">{{ $line->i_notes }}</div>
                                @endif
                            </td>
                            <td class="num q">{{ number_format($line->i_qty, 2, ',', '.') }} {{ $line->i_uom }}</td>
                            <td class="num">{{ number_format($line->i_price_fx, 2, ',', '.') }}</td>
                            <td class="num amt">{{ number_format($line->c_subtotal_idr, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totwrap" style="grid-template-columns:1fr 78mm">
                <div class="tot-note">
                    <h5>{{ mb_strtoupper(t('Notes')) }}</h5>
                    {{ $this->pq->i_notes ?: t('This quotation summary is used as the comparison basis before a Purchase Order is issued.') }}
                </div>
                <table class="tot">
                    <tr class="grand"><td class="k">{{ t('TOTAL') }} (IDR)</td><td class="v">{{ number_format($this->pq->c_total_idr, 0, ',', '.') }}</td></tr>
                </table>
            </div>
        </x-print.section>

        <x-print.signatures>
            <x-print.signature :company="t('Recorded by')" :role="t('Purchasing')"
                name="{{ $this->pq->created_by ?? '—' }}" :sub="t('Prepared')" />
            <x-print.signature company="{{ \App\Support\Branding\Brand::companyName() }}"
                :role="t('Confirmation')" name="( ................................. )" :sub="t('Head of Purchasing')" />
        </x-print.signatures>

        <x-print.footer page="{{ t('Page 1 of 1') }}">
            {{ $this->pq->doc_no }} · {{ $this->supplier?->supplier_name }} ·
            {{ str_replace(':date', now()->format('d/m/Y H:i'), t('printed :date')) }} · {{ t('official document issued by the TMERP system') }}
        </x-print.footer>
    </x-print.sheet>
</x-print.document>
