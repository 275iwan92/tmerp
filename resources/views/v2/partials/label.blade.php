{{--
 REMARK
 Tujuan       : Merender satu label gudang dari data label yang sudah disesuaikan ukurannya.
 Sumber hulu  : App\Support\V2\Data\Labels (location / item / pallet, Labels::fit).
 Input        : $l (isi label), $size sm | md | lg (bawaan md), $title opsional.
 Output       : x-dl::label.
 Dipakai oleh : @include dari v2.label-sheet dan screens/stock-transfer.
 Status       : Aktif.
--}}
@php
    $size ??= 'md';
    $l = \App\Support\V2\Data\Labels::fit($l, $size);
@endphp
<x-dl::label :code="$l['code']" :kind="$l['kind'] ?? null" :title="$title ?? $l['title']" :color="$l['color'] ?? null" :mark="$l['mark'] ?? null" :name="$l['name']"
    :highlight="$l['highlight'] ?? null" :lines="$l['lines']" :tags="$l['tags'] ?? []" :qr="$l['qr']" :barcode="$l['barcode']" :size="$size" :tone="$l['tone']" :footer="$l['footer']" />
