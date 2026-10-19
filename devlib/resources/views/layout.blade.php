{{-- REMARK
Tujuan: kerangka HTML semua halaman situs devlib.test (judul tab, CSS/JS DevLib dan mockup-parts).
Sumber hulu: config devlib.brand.name untuk akhiran judul tab (DevLib); paket ../devlib dan packages/mockup-parts.
Input: variabel $title dan section body dari tiap view.
Output: dokumen HTML lengkap.
Dipakai oleh: view login, home, lib, generator, summary, history, agent-log di devlib/resources/views.
Status: aktif.
--}}<!DOCTYPE html>
<html lang="{{ \Devlib\Locale::lang() }}" data-dl-tz="{{ config('devlib.timezone') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'DevLib' }} — {{ config('devlib.brand.name', 'DevLib') }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ \Devlib\DevlibServiceProvider::asset('devlib.css') }}">
    <x-dl::theme-boot />
    <script src="{{ \Devlib\DevlibServiceProvider::asset('devlib.js') }}" defer></script>
    {{-- Objek form khusus (x-mp::*, packages/mockup-parts) ikut diperagakan di menu Special Forms. --}}
    <link rel="stylesheet" href="{{ \MockupParts\MockupPartsServiceProvider::asset('mockup-parts.css') }}">
    <script src="{{ \MockupParts\MockupPartsServiceProvider::asset('mockup-parts.js') }}" defer></script>
</head>
<body class="dl-body {{ $bodyClass ?? '' }}" @if ($dev ?? false) data-dl-dev="1" @endif>
    @yield('body')
</body>
</html>
