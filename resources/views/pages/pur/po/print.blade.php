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
            session()->flash('error', 'PO ini belum di-approve — approve dulu sebelum bisa dicetak.');
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
        <a href="{{ route('pur.po.entry', ['po' => $this->poId]) }}" class="sc" data-back>← Kembali ke PO</a>
    </x-slot:toolbar>

    <x-print.sheet>
        <x-print.header :company="$this->company" doc-title="PURCHASE ORDER" :doc-no="$this->po->doc_no">
            <x-slot:meta>
                Tanggal: {{ $this->po->i_doc_date?->translatedFormat('d F Y') }}
                @if ($this->po->revision_no > 0) · Rev. {{ str_pad($this->po->revision_no, 2, '0', STR_PAD_LEFT) }} @endif
                · Mata Uang: <span class="ref">{{ $this->currencyCode }}</span>
            </x-slot:meta>
        </x-print.header>

        <x-print.parties columns="1.2fr 1fr">
            <x-print.party label="KEPADA SUPPLIER">
                <div class="nm">{{ $this->supplier?->supplier_name ?? '—' }}</div>
                <div class="dt">
                    Kode: <b>{{ $this->supplier?->supplier_code ?? '—' }}</b><br>
                    {{ $this->supplier?->default_address ?? '—' }}<br>
                    @if ($this->supplier?->contact_name) UP. <b>{{ $this->supplier->contact_name }}</b> @endif
                    @if ($this->supplier?->phone) · {{ $this->supplier->phone }} @endif
                </div>
            </x-print.party>
            <x-print.party label="PENGIRIMAN">
                <div class="dt">
                    Dikirim ke: <b>{{ $this->po->i_ship_to ?? '—' }}</b><br>
                    Tgl Kirim Diminta: <b>{{ $this->po->i_delivery_date?->translatedFormat('d F Y') ?? '—' }}</b><br>
                    Termin Bayar: <b>{{ $this->po->i_payment_term ?? '—' }}</b>
                </div>
            </x-print.party>
        </x-print.parties>

        <x-print.section number="1" title="Rincian Pesanan">
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:6%">No.</th>
                        <th class="desc">Deskripsi Barang/Jasa</th>
                        <th class="num" style="width:11%">Qty</th>
                        <th class="num" style="width:13%">Harga Satuan</th>
                        <th class="num" style="width:10%">Disc</th>
                        <th class="num" style="width:16%">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->po->lines as $line)
                        <tr>
                            <td class="ref">{{ $line->line_no }}</td>
                            <td class="desc">
                                <div class="nm">{{ $line->i_description }}</div>
                                @if ($line->c_item_code)
                                    <div class="sp">Kode: {{ $line->c_item_code }}</div>
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
                    <h5>CATATAN</h5>
                    {{ $this->po->i_notes ?: 'Mohon konfirmasi penerimaan PO ini & sertakan nomor PO pada Surat Jalan/Invoice.' }}
                </div>
                <table class="tot">
                    <tr class="sub"><td class="k">DPP</td><td class="v">{{ number_format($this->po->c_dpp, 2, ',', '.') }}</td></tr>
                    <tr class="sub"><td class="k">PPN</td><td class="v">{{ number_format($this->po->c_ppn, 2, ',', '.') }}</td></tr>
                    @if ($this->po->c_pph > 0)
                        <tr class="sub"><td class="k">PPh 23 (Jasa)</td><td class="v">-{{ number_format($this->po->c_pph, 2, ',', '.') }}</td></tr>
                    @endif
                    <tr class="grand"><td class="k">TOTAL DIBAYAR</td><td class="v">{{ $this->currencyCode }} {{ number_format($this->po->c_net_payable, 2, ',', '.') }}</td></tr>
                </table>
            </div>
        </x-print.section>

        <x-print.signatures>
            <x-slot:intro>
                Purchase Order ini diterbitkan resmi oleh {{ $this->company?->company_name ?? 'PT Telasih Makmur' }}.
                Mohon konfirmasi ketersediaan &amp; jadwal pengiriman kepada PIC Purchasing kami.
            </x-slot:intro>

            <x-print.signature company="Diterbitkan oleh, {{ $this->company?->company_name ?? 'PT Telasih Makmur' }}"
                role="Purchasing" name="{{ $this->po->created_by ?? '—' }}" sub="Dibuat" />
            <x-print.signature company="{{ $this->company?->company_name ?? 'PT Telasih Makmur' }}"
                role="Approval" name="( ................................. )" sub="Kepala Purchasing/Direksi" />
            <x-print.signature company="Diterima &amp; disetujui oleh" role="Supplier"
                name="( ................................. )" sub="Nama jelas, jabatan &amp; stempel" />
        </x-print.signatures>

        <x-print.footer page="Halaman 1 dari 1">
            {{ $this->po->doc_no }} · {{ $this->supplier?->supplier_name }} ·
            dicetak {{ now()->format('d/m/Y H:i') }} · dokumen sah terbit sistem TMERP
        </x-print.footer>
    </x-print.sheet>
</x-print.document>
