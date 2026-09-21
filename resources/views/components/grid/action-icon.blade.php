{{--
    griduistandard.md §5 -- 1 tombol ikon di kolom Aksi. `action` = string
    pemanggilan method Livewire persis spt ditulis manual (mis. "openOrder(123)").

    STANDAR BARU (keputusan user 2026-09-21, "Pilihan C", BACKLOG §22.2):
    - Ikon = Font Awesome (`icon="fa-pen"`), BUKAN karakter Unicode (✎/⏻ tipis,
      bentuknya beda-beda antar perangkat, dan tidak sebaris dgn ikon Font
      Awesome tombol Back). Karakter biasa/emoji masih diterima apa adanya
      supaya pemanggil lama tidak pecah, tapi jangan dipakai lagi utk layar baru.
    - Berbingkai kotak jadi DEFAULT (`bordered=true`, w-8) -- dulu cuma 2 layar
      pilot. `:bordered="false"` disediakan utk kasus khusus, bukan gaya kedua.
    - `:active="$row->activate"` = tombol aktif/nonaktif master: ikon & warnanya
      DITENTUKAN DI SINI (aktif -> fa-ban merah "Nonaktifkan", nonaktif ->
      fa-circle-check hijau "Aktifkan") supaya tidak ada layar yang menyimpang.
      Pemanggil cukup mengurus `title`, `action`, dan `confirm`.
--}}
@props(['icon' => null, 'title', 'action', 'variant' => 'default', 'confirm' => null, 'bordered' => true, 'active' => null])
@php
    if ($active !== null) {
        $icon = $active ? 'fa-ban' : 'fa-circle-check';
        $variant = $active ? 'danger' : 'success';
    }

    $isFontAwesome = is_string($icon) && str_starts_with($icon, 'fa-');

    $colors = match ($variant) {
        'danger' => 'text-[#c0392b] hover:bg-[#fdeceb]',
        'success' => 'text-[#16a34a] hover:bg-[#e9f9ef]',
        default => 'text-accent hover:bg-accent-soft',
    };
    $borderColor = match ($variant) {
        'danger' => 'border-[#f5c6c6]',
        'success' => 'border-[#c8ecd6]',
        default => 'border-field-border',
    };
    $sizeClass = $bordered ? 'w-8 h-8' : 'w-7 h-7';
    $borderClass = $bordered ? 'border bg-white '.$borderColor : '';
@endphp
<button type="button" wire:click.stop="{{ $action }}"
    @if ($confirm) wire:confirm="{{ $confirm }}" @endif
    title="{{ $title }}"
    class="inline-flex items-center justify-center {{ $sizeClass }} rounded-md cursor-pointer {{ $borderClass }} {{ $colors }}">@if ($isFontAwesome)<i class="fa-solid {{ $icon }}"></i>@else{{ $icon }}@endif</button>
