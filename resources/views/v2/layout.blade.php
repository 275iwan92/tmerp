{{--
 REMARK
 Tujuan       : Kerangka halaman HTML semua layar v2: head, stylesheet / skrip DevLib dan MockupParts, boot tema, dan body.
 Sumber hulu  : DevLib (devlib.css, devlib.js), MockupParts (mockup-parts.css, mockup-parts.js), x-dl::theme-boot.
 Input        : title, bodyClass, dev dari view anak; Devlib\Locale (bahasa, zona waktu).
 Output       : Dokumen HTML lengkap dengan yield body.
 Dipakai oleh : @extends oleh semua view v2 (home, dictionary, dummy, print-doc, print-report, dst).
 Status       : Aktif.
--}}
<!DOCTYPE html>
<html lang="{{ \Devlib\Locale::lang() }}" data-dl-tz="{{ config('devlib.timezone') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'DevLib' }} — TELASIH MAKMUR</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ \Devlib\DevlibServiceProvider::asset('devlib.css') }}">
    <link rel="stylesheet" href="{{ \Devlib\DevlibServiceProvider::asset('envbadge.css') }}">
    <x-dl::theme-boot />
    <link rel="stylesheet" href="{{ \MockupParts\MockupPartsServiceProvider::asset('mockup-parts.css') }}">
    <script src="{{ \Devlib\DevlibServiceProvider::asset('devlib.js') }}" defer></script>
    <script src="{{ \MockupParts\MockupPartsServiceProvider::asset('mockup-parts.js') }}" defer></script>
</head>
<body class="dl-body dl-page-wide {{ $bodyClass ?? '' }}" @if ($dev ?? false) data-dl-dev="1" @endif>
    @yield('body')
</body>
</html>
