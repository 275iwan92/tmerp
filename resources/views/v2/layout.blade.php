<!DOCTYPE html>
<html lang="{{ \Devlib\Locale::lang() }}" data-dl-tz="{{ config('devlib.timezone') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'DevLib' }} — TELASIH MAKMUR</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ \Devlib\DevlibServiceProvider::asset('devlib.css') }}">
    <x-dl::theme-boot />
    {{-- Dibuka di laci lebar Shell (iframe name="dl-wide"): layar naik ke tepi atas, tanpa ruang kosong di atas breadcrumb (user 2026-10-04). --}}
    <script>if (window.name === 'dl-wide') document.documentElement.classList.add('dl-in-drawer');</script>
    <link rel="stylesheet" href="{{ \MockupParts\MockupPartsServiceProvider::asset('mockup-parts.css') }}">
    <script src="{{ \Devlib\DevlibServiceProvider::asset('devlib.js') }}" defer></script>
    <script src="{{ \MockupParts\MockupPartsServiceProvider::asset('mockup-parts.js') }}" defer></script>
</head>
<body class="dl-body dl-page-wide {{ $bodyClass ?? '' }}" @if ($dev ?? false) data-dl-dev="1" @endif>
    @yield('body')
</body>
</html>
