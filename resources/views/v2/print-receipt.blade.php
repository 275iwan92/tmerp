{{--
 REMARK
 Tujuan       : Cetak SPB: Goods Receipt Note (PO) atau Subcon Return Receipt, tanpa harga.
 Sumber hulu  : tm2pur.pv_receipt dan pv_line, supplier, tm2mst.wh_warehouse.
 Input        : pv, supplier, parties, columns, rows, title, subtitle, company, signatures dari route.
 Output       : x-dl::print-out dibungkus x-dl::doc-share (PDF / JPEG / online + QR / Email), Back ke purchase-receive.
 Dipakai oleh : route v2.pv.print (routes/v2.php, grup pv).
 Status       : Aktif.
--}}
@extends('v2.layout', ['title' => t($title).' '.$pv->doc_no, 'bodyClass' => 'dl-page-body'])
@section('body')
    <x-dl::box class="dl-cat">
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="back" label="Back" :href="route('v2.screen', 'purchase-receive').'?id='.$pv->pv_id" />
            <x-dl::doc-share target="#pv-print" :type="'receipt-'.strtolower($pv->i_receipt_type)" :doc-id="$pv->pv_id" :number="$pv->doc_no" :title="$title" :email-to="$supplier->email ?? null" />
        </x-dl::action-bar>
        <x-dl::box id="pv-print">
            <x-dl::print-out :title="$title" :subtitle="$subtitle" :number="$pv->doc_no" :date="$pv->i_doc_date?->toDateString()" :status="$pv->status"
                :company="$company" :printed-by="auth()->user()->login" :prices="false" :source="\App\Support\V2\Src::of('tm2pur.pv_receipt', 'tm2pur.pv_line')"
                :parties="$parties" :columns="$columns" :rows="$rows" :signatures="$signatures">
                <x-slot:notes><b>{{ t('Notes') }}:</b> {{ $pv->i_notes ?: '—' }}</x-slot:notes>
            </x-dl::print-out>
        </x-dl::box>
    </x-dl::box>
@endsection
