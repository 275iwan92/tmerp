{{--
 REMARK
 Tujuan       : Menampilkan dieline dan artwork bagian yang dikerjakan sebuah Work Order sebagai acuan di layar produksi.
 Sumber hulu  : App\Support\V2\Data\Ppic::jobVisual (Master Card hasil Product Development) dan Data\Sales::dielineTypes().
 Input        : $woId (id Work Order), $height opsional.
 Output       : x-mp::sales.part-visual; bila tidak ada dieline / artwork tampil x-dl::alert info.
 Dipakai oleh : Di-include production-scheduling dan ppic-color-sequence.
 Status       : Aktif.
--}}
@php
    $visual = \App\Support\V2\Data\Ppic::jobVisual((int) $woId);
@endphp
@if ($visual)
    <x-mp::sales.part-visual :part="$visual" :types="\App\Support\V2\Data\Sales::dielineTypes()" :height="$height ?? '200px'" />
@else
    <x-dl::alert tone="info" message="No dieline or artwork for this job in Product Development." />
@endif
