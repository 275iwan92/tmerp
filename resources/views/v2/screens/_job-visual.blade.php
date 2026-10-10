{{-- Dieline and artwork preview of the part a Work Order makes (Product Development): the reference every worker sees on a production screen,
     also used to check the work before it (die-cut, glue). Props: $woId, $height (optional). --}}
@php
    $visual = \App\Support\V2\Data\Ppic::jobVisual((int) $woId);
@endphp
@if ($visual)
    <x-mp::sales.part-visual :part="$visual" :types="\App\Support\V2\Data\Sales::dielineTypes()" :height="$height ?? '200px'" />
@else
    <x-dl::alert tone="info" message="No dieline or artwork for this job in Product Development." />
@endif
