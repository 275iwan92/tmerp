{{-- Cetak SPB (user 2026-10-01): Penerimaan Barang jenis PO dan Subkon. Objek DevLib x-dl::print-out (kop + pihak + tabel +
     tanda tangan) + x-dl::doc-share (PDF / JPEG / online + QR / Email). Dokumen gudang: tanpa harga. Label English. --}}
@extends('v2.layout', ['title' => $title.' '.$pv->doc_no, 'bodyClass' => 'dl-page-body'])
@section('body')
    <div class="dl-cat">
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="back" label="Back" :href="route('v2.screen', 'purchase-receive').'?id='.$pv->pv_id" />
            <x-dl::doc-share target="#pv-print" :type="'receipt-'.strtolower($pv->i_receipt_type)" :doc-id="$pv->pv_id" :number="$pv->doc_no" :title="$title" :email-to="$supplier->email ?? null" />
        </x-dl::action-bar>
        <div id="pv-print">
            <x-dl::print-out :title="$title" :subtitle="$subtitle" :number="$pv->doc_no" :date="$pv->i_doc_date?->toDateString()" :status="$pv->status"
                :company="$company" :printed-by="auth()->user()->login" :prices="false" :source="\App\Support\V2\Src::of('tm2pur.pv_receipt', 'tm2pur.pv_line')"
                :parties="$parties" :columns="$columns" :rows="$rows" :signatures="$signatures">
                <x-slot:notes><b>{{ t('Notes') }}:</b> {{ $pv->i_notes ?: '—' }}</x-slot:notes>
            </x-dl::print-out>
        </div>
    </div>
@endsection
