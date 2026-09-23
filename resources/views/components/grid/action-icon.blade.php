{{--
    griduistandard.md §5 -- 1 tombol ikon di kolom Aksi. `action` = string
    pemanggilan method Livewire persis spt ditulis manual (mis. "openOrder(123)").

    Sejak 2026-09-23 hanya pembungkus x-ui.icon-button (componentstandard.md):
    warna/ukuran diatur varian action* di App\View\Components\Ui\Button, bukan
    di sini. API props tidak berubah.
    - Ikon Font Awesome (`icon="fa-pen"`); karakter lama masih diterima.
    - Berbingkai = default (`bordered=true`, w-8); `:bordered="false"` -> w-7 tanpa bingkai.
    - `:active="$row->activate"` = tombol aktif/nonaktif master: ikon & warnanya
      DITENTUKAN DI SINI (aktif -> fa-ban merah, nonaktif -> fa-circle-check hijau).
--}}
@props(['icon' => null, 'title', 'action', 'variant' => 'default', 'confirm' => null, 'bordered' => true, 'active' => null])
@php
    if ($active !== null) {
        $icon = $active ? 'fa-ban' : 'fa-circle-check';
        $variant = $active ? 'danger' : 'success';
    }
    $uiVariant = ($bordered ? 'action' : 'action-plain').($variant === 'default' ? '' : '-'.$variant);
@endphp
<x-ui.icon-button :icon="$icon" :variant="$uiVariant" :size="$bordered ? 'md' : 'sm'"
    wire:click.stop="{{ $action }}" :wire:confirm="$confirm" title="{{ $title }}" />
