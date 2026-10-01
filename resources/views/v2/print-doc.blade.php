{{-- Cetakan dokumen transaksi (user 2026-10-01): satu halaman untuk semua dokumen. Tata letak = objek DevLib x-dl::print-doc
     (A5 landscape 1-5 baris / A4 portrait); Print, PDF, JPEG, online + QR, dan Email = x-dl::doc-share yang menunjuk lembarnya.
     Isi dokumen = kelas di App\Support\V2\Print (route v2.print). --}}
@php
    $number = $doc->data['number'] ?? null;
    $twoFormats = $type === 'customer-order' && auth()->user()->canViewSalesPrice();
    $self = fn (int $p) => route('v2.print', ['type' => $type, 'id' => $id, 'prices' => $p] + request()->only('from'));
@endphp
@extends('v2.layout', ['title' => trim($doc->fileTitle().' '.$number), 'bodyClass' => 'dl-page-body'])
@section('body')
    <div class="dl-cat">
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="back" label="Back" :href="$back" />
            @if ($twoFormats)
                <x-dl::button :variant="$prices ? 'primary' : 'secondary'" label="With Price" :href="$self(1)" />
                <x-dl::button :variant="$prices ? 'secondary' : 'primary'" label="Without Price" :href="$self(0)" />
            @endif
            <x-dl::doc-share target="#print-doc" :type="'print-'.$type" :doc-id="$id" :number="$number" :title="$doc->fileTitle()" :email-to="$doc->data['email'] ?? null"
                :orientation="$doc->paper() === 'a5' ? 'landscape' : 'portrait'" />
        </x-dl::action-bar>
        <x-dl::print-doc :doc="$doc" id="print-doc" />
    </div>
@endsection
