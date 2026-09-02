{{-- Font aplikasi (docs/configurationstandard.md) -- CSS var --brand-font
    dirender sekali di <head> (server-side), dibaca app.css `.shell`/
    `.authpage`. Link Google Fonts cuma dimuat kalau preset yang dipilih BUKAN
    system-ui (default = 0 request eksternal). --}}
@php
    $__brandFontStack = \App\Support\Branding\Brand::fontStack();
    $__googleFontUrl = \App\Support\Branding\Brand::googleFontUrl();
@endphp
@if ($__googleFontUrl)
    <link rel="stylesheet" href="{{ $__googleFontUrl }}">
@endif
<style>:root { --brand-font: {{ $__brandFontStack }}; }</style>
