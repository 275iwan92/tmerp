{{-- Satu label gudang dari isi App\Support\V2\Data\Labels::location / item / pallet ($l); $size = sm | md | lg (bawaan md).
     Isi disesuaikan dengan ukuran (Labels::fit) supaya tidak terpotong. --}}
@php
    $size ??= 'md';
    $l = \App\Support\V2\Data\Labels::fit($l, $size);
@endphp
<x-dl::label :code="$l['code']" :kind="$l['kind'] ?? null" :title="$title ?? $l['title']" :color="$l['color'] ?? null" :mark="$l['mark'] ?? null" :name="$l['name']"
    :highlight="$l['highlight'] ?? null" :lines="$l['lines']" :tags="$l['tags'] ?? []" :qr="$l['qr']" :barcode="$l['barcode']" :size="$size" :tone="$l['tone']" :footer="$l['footer']" />
