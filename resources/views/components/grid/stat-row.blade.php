{{--
    griduistandard.md §2 -- wrapper baris kotak statistik. `.grid`/`two`/
    `three`/`five` itu CSS custom di resources/css/app.css (unlayered, BUKAN
    utility Tailwind grid-cols-X yang diam-diam kalah/tak jalan) -- lihat
    catatan panjang di griduistandard.md §2. :count wajib diisi jumlah
    <x-grid.stat-box> anak supaya varian kelasnya tepat.
--}}
@props(['count'])
@php
    $variant = match (true) {
        (int) $count === 2 => 'two',
        (int) $count === 3 => 'three',
        (int) $count >= 5 => 'five',
        default => '', // 1 atau 4 kotak -> ".grid" polos
    };
@endphp
<div {{ $attributes->merge(['class' => trim('grid '.$variant.' mb-3.5')]) }}>
    {{ $slot }}
</div>
