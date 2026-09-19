{{--
    griduistandard.md §2 -- 1 kotak statistik pastel. Tone dipetakan ke salah
    satu dari 5 palet baku (bukan warna bebas). Tiap cabang match() di bawah
    HARUS string kelas Tailwind LITERAL utuh (bukan hasil concat) supaya
    lolos content-scan build-time Tailwind v4 -- lihat gotcha yang sama di
    griduistandard.md §6 soal reset <input>.

    action (opsional) = method Livewire -> kotak jadi tombol filter (klik =
    wire:click), selected = sedang dipakai sbg filter (ring tebal).
--}}
@props(['tone' => 'total', 'label', 'value', 'subtitle' => null, 'action' => null, 'selected' => false])
@php
    $c = match ($tone) {
        'active' => ['box' => 'bg-[#e9f9ef] border-[#c8ecd6]', 'label' => 'text-[#2f7a52]', 'value' => 'text-[#16a34a]', 'sub' => 'text-[#2f7a52]/70'],
        'warn' => ['box' => 'bg-[#fef6e7] border-[#f5e0b3]', 'label' => 'text-[#8a6d00]', 'value' => 'text-[#b45309]', 'sub' => 'text-[#8a6d00]/70'],
        'critical' => ['box' => 'bg-[#fdecec] border-[#f5c6c6]', 'label' => 'text-[#a3453f]', 'value' => 'text-[#c0392b]', 'sub' => 'text-[#a3453f]/70'],
        'extra' => ['box' => 'bg-[#f1ecfc] border-[#ddd0f7]', 'label' => 'text-[#6b4fa0]', 'value' => 'text-[#6d28d9]', 'sub' => 'text-[#6b4fa0]/70'],
        default => ['box' => 'bg-[#eaf2ff] border-[#c8ddfb]', 'label' => 'text-[#3b5b99]', 'value' => 'text-[#1d4ed8]', 'sub' => 'text-[#3b5b99]/70'],
    };
@endphp
@if ($action)
<button type="button" wire:click="{{ $action }}" aria-pressed="{{ $selected ? 'true' : 'false' }}"
    class="text-left w-full rounded-lg p-3.5 shadow-sm border cursor-pointer transition hover:brightness-[0.97] {{ $c['box'] }} {{ $selected ? 'ring-2 ring-[#1d4ed8] ring-offset-1' : '' }}">
@else
<div class="rounded-lg p-3.5 shadow-sm border {{ $c['box'] }}">
@endif
    <p class="text-[10.5px] font-bold uppercase {{ $c['label'] }}">{{ $label }}</p>
    <p class="text-xl font-extrabold {{ $c['value'] }} mt-0.5">{{ $value }}</p>
    @if ($subtitle)
        <p class="text-[10px] {{ $c['sub'] }} mt-0.5">{{ $subtitle }}</p>
    @endif
@if ($action)
</button>
@else
</div>
@endif
