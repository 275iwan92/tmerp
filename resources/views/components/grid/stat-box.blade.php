{{--
    griduistandard.md §2 -- 1 kotak statistik pastel. Tone dipetakan ke salah
    satu dari 5 palet baku (bukan warna bebas). Tiap cabang match() di bawah
    HARUS string kelas Tailwind LITERAL utuh (bukan hasil concat) supaya
    lolos content-scan build-time Tailwind v4 -- lihat gotcha yang sama di
    griduistandard.md §6 soal reset <input>.
--}}
@props(['tone' => 'total', 'label', 'value', 'subtitle' => null])
@php
    $c = match ($tone) {
        'active' => ['box' => 'bg-[#e9f9ef] border-[#c8ecd6]', 'label' => 'text-[#2f7a52]', 'value' => 'text-[#16a34a]', 'sub' => 'text-[#2f7a52]/70'],
        'warn' => ['box' => 'bg-[#fef6e7] border-[#f5e0b3]', 'label' => 'text-[#8a6d00]', 'value' => 'text-[#b45309]', 'sub' => 'text-[#8a6d00]/70'],
        'critical' => ['box' => 'bg-[#fdecec] border-[#f5c6c6]', 'label' => 'text-[#a3453f]', 'value' => 'text-[#c0392b]', 'sub' => 'text-[#a3453f]/70'],
        'extra' => ['box' => 'bg-[#f1ecfc] border-[#ddd0f7]', 'label' => 'text-[#6b4fa0]', 'value' => 'text-[#6d28d9]', 'sub' => 'text-[#6b4fa0]/70'],
        default => ['box' => 'bg-[#eaf2ff] border-[#c8ddfb]', 'label' => 'text-[#3b5b99]', 'value' => 'text-[#1d4ed8]', 'sub' => 'text-[#3b5b99]/70'],
    };
@endphp
<div class="rounded-lg p-3.5 shadow-sm border {{ $c['box'] }}">
    <p class="text-[10.5px] font-bold uppercase {{ $c['label'] }}">{{ $label }}</p>
    <p class="text-xl font-extrabold {{ $c['value'] }} mt-0.5">{{ $value }}</p>
    @if ($subtitle)
        <p class="text-[10px] {{ $c['sub'] }} mt-0.5">{{ $subtitle }}</p>
    @endif
</div>
