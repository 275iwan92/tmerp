{{--
 REMARK
 Tujuan       : Halaman cetak satu dokumen transaksi (A5 landscape 1-5 baris atau A4 portrait) dengan pilihan format dan harga.
 Sumber hulu  : App\Support\V2\Print\PrintDocs (kelas dokumen per type).
 Input        : doc, type, id, prices, back, query from; hak canViewSalesPrice untuk customer-order.
 Output       : x-dl::action-bar, x-dl::print-doc, x-dl::doc-share.
 Dipakai oleh : route v2.print (routes/v2.php); tombol Print dari grid (DbGrid) dan form dokumen.
 Status       : Aktif.
--}}
@php
    $number = $doc->data['number'] ?? null;
    $twoFormats = $type === 'customer-order' && auth()->user()->canViewSalesPrice();
    $self = fn (int $p) => route('v2.print', ['type' => $type, 'id' => $id, 'prices' => $p] + request()->only('from'));
@endphp
@extends('v2.layout', ['title' => trim($doc->fileTitle().' '.$number), 'bodyClass' => 'dl-page-body'])
@section('body')
    <x-dl::box class="dl-cat">
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="back" label="Back" :href="$back" />
            @if ($twoFormats)
                <x-dl::button :variant="$prices ? 'primary' : 'secondary'" label="With Price" :href="$self(1)" />
                <x-dl::button :variant="$prices ? 'secondary' : 'primary'" label="Without Price" :href="$self(0)" />
            @endif
            @foreach (\App\Support\V2\Print\PrintDocs::formats($type, $id, request()->only('from')) as $f)
                <x-dl::button :variant="$f['active'] ? 'primary' : 'secondary'" :label="$f['label']" :href="$f['url']" />
            @endforeach
            <x-dl::doc-share target="#print-doc" :type="'print-'.$type" :doc-id="$id" :number="$number" :title="$doc->fileTitle()" :email-to="$doc->data['email'] ?? null"
                :orientation="$doc->paper() === 'a5' ? 'landscape' : 'portrait'" />
        </x-dl::action-bar>
        <x-dl::print-doc :doc="$doc" id="print-doc" />
    </x-dl::box>
@endsection
