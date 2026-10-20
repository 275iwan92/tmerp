{{--
 REMARK
 Tujuan       : Halaman cetak Work Order untuk lapangan (A4 lembar berbagian) dengan kolom aktual kosong untuk diisi tangan; menggantikan halaman v1 pages::production.wo-print.
 Sumber hulu  : App\Support\V2\Data\WorkOrderPrint (props x-dl::print-sheet).
 Input        : $data (WorkOrderPrint), $back.
 Output       : x-dl::action-bar (Back, x-dl::doc-share) dan x-dl::print-sheet.
 Dipakai oleh : Route v2.production.wo.print (routes/v2_production.php); tombol Print di layar production-wo.
 Status       : Aktif (T9 pindahan v1, 2026-10-20).
--}}
@extends('v2.layout', ['title' => $data->fileTitle(), 'bodyClass' => 'dl-page-body'])
@section('body')
    @php $sheet = $data->sheet(); @endphp
    <x-dl::box class="dl-cat">
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="back" label="Back to WO Detail" :href="$back" />
            <x-dl::doc-share target="#print-sheet" type="print-wo" :doc-id="$data->wo->wo_id" :number="$data->wo->wo_no" :title="$data->fileTitle()" orientation="portrait" />
        </x-dl::action-bar>
        <x-dl::print-sheet id="print-sheet" :title="$sheet['title']" :code="$sheet['code']" :info="$sheet['info']" :sections="$sheet['sections']" :signatures="$sheet['signatures']" :footer="$sheet['footer']" />
    </x-dl::box>
@endsection
