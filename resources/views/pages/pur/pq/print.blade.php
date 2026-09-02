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
            session()->flash('error', 'PQ ini masih draft — konfirmasi dulu sebelum bisa dicetak.');
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
        <a href="{{ route('pur.pq.entry', ['pq' => $this->pqId]) }}" class="sc">← Kembali ke PQ</a>
    </x-slot:toolbar>

    <x-print.sheet>
        <x-print.header :company="$this->company" doc-title="PURCHASE QUOTATION (REKAP PENAWARAN)" :doc-no="$this->pq->doc_no">
            <x-slot:meta>
                Tanggal: {{ $this->pq->i_doc_date?->translatedFormat('d F Y') }}
                · Berlaku s/d: <span class="ref">{{ $this->pq->i_quotation_valid_until?->translatedFormat('d F Y') ?? '—' }}</span>
                · Mata Uang: <span class="ref">{{ $this->currencyCode }}</span>
            </x-slot:meta>
        </x-print.header>

        <x-print.parties columns="1fr 1fr">
            <x-print.party label="SUPPLIER PEMBERI PENAWARAN">
                <div class="nm">{{ $this->supplier?->supplier_name ?? '—' }}</div>
                <div class="dt">Kode: <b>{{ $this->supplier?->supplier_code ?? '—' }}</b></div>
            </x-print.party>
            <x-print.party label="REFERENSI">
                <div class="dt">
                    Ref. PR: <b>{{ $this->pq->requisition?->doc_no ?? '—' }}</b><br>
                    Termin Bayar: <b>{{ $this->pq->i_payment_term ?? '—' }}</b><br>
                    Lead Time: <b>{{ $this->pq->i_lead_time_day ?? '—' }} hari</b>
                </div>
            </x-print.party>
        </x-print.parties>

        <x-print.section number="1" title="Rincian Penawaran">
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:6%">No.</th>
                        <th class="desc">Deskripsi</th>
                        <th style="width:11%">Qty</th>
                        <th style="width:15%">Harga ({{ $this->currencyCode }})</th>
                        <th style="width:16%">Subtotal (IDR)</th>
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
                            <td class="q">{{ number_format($line->i_qty, 2, ',', '.') }} {{ $line->i_uom }}</td>
                            <td>{{ number_format($line->i_price_fx, 2, ',', '.') }}</td>
                            <td class="amt">{{ number_format($line->c_subtotal_idr, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totwrap" style="grid-template-columns:1fr 78mm">
                <div class="tot-note">
                    <h5>CATATAN</h5>
                    {{ $this->pq->i_notes ?: 'Rekap penawaran ini dipakai sebagai dasar pembanding sebelum penerbitan Purchase Order.' }}
                </div>
                <table class="tot">
                    <tr class="grand"><td class="k">TOTAL (IDR)</td><td class="v">{{ number_format($this->pq->c_total_idr, 0, ',', '.') }}</td></tr>
                </table>
            </div>
        </x-print.section>

        <x-print.signatures>
            <x-print.signature company="Dicatat oleh" role="Purchasing"
                name="{{ $this->pq->created_by ?? '—' }}" sub="Dibuat" />
            <x-print.signature company="{{ $this->company?->company_name ?? 'PT Telasih Makmur' }}"
                role="Konfirmasi" name="( ................................. )" sub="Kepala Purchasing" />
        </x-print.signatures>

        <x-print.footer page="Halaman 1 dari 1">
            {{ $this->pq->doc_no }} · {{ $this->supplier?->supplier_name }} ·
            dicetak {{ now()->format('d/m/Y H:i') }} · dokumen sah terbit sistem TMERP
        </x-print.footer>
    </x-print.sheet>
</x-print.document>
