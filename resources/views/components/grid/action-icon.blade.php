{{--
    griduistandard.md §5 (default, w-7 tanpa border) & §10 (pilot Aksi-kiri,
    w-8 + border) -- 1 tombol ikon Aksi. `action` = string method call Livewire
    persis seperti dulu ditulis manual (mis. "openOrder(123)"). `bordered` HANYA
    dipakai 2 layar pilot (co/list, raw-material/index) -- grid lain WAJIB
    pakai default (bordered=false) sesuai §5, jangan digeneralisasi tanpa tanya.
--}}
@props(['icon', 'title', 'action', 'variant' => 'default', 'confirm' => null, 'bordered' => false])
@php
    $colors = match ($variant) {
        'danger' => 'text-[#c0392b] hover:bg-[#fdeceb]',
        'success' => 'text-[#16a34a] hover:bg-[#e9f9ef]',
        default => 'text-accent hover:bg-[#eef3fb]',
    };
    $borderColor = match ($variant) {
        'danger' => 'border-[#f5c6c6]',
        'success' => 'border-[#c8ecd6]',
        default => 'border-[#d7dbe2]',
    };
    $sizeClass = $bordered ? 'w-8 h-8' : 'w-7 h-7';
    $borderClass = $bordered ? 'border bg-white '.$borderColor : '';
@endphp
<button type="button" wire:click.stop="{{ $action }}"
    @if ($confirm) wire:confirm="{{ $confirm }}" @endif
    title="{{ $title }}"
    class="inline-flex items-center justify-center {{ $sizeClass }} rounded-md cursor-pointer {{ $borderClass }} {{ $colors }}">{{ $icon }}</button>
