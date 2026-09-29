<!DOCTYPE html>
<html lang="id" data-dl-tz="{{ config('devlib.timezone') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'DevLib' }} — TELASIH MAKMUR</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ \Devlib\DevlibServiceProvider::asset('devlib.css') }}">
    <x-dl::theme-boot />
    <link rel="stylesheet" href="{{ \MockupParts\MockupPartsServiceProvider::asset('mockup-parts.css') }}">
    @if (($framed ?? false) && \App\Support\V2\Shell::isV2Site())
        {{-- Cadangan KeepScreensInShell untuk browser tanpa Sec-Fetch-Dest: layar menu wajib di dalam tab shell. --}}
        <script>if (window.top === window.self) location.replace(@json(\App\Support\V2\Shell::url(array_filter(['open' => \App\Support\V2\Shell::codeFor(request())]))));</script>
    @endif
    <script src="{{ \Devlib\DevlibServiceProvider::asset('devlib.js') }}" defer></script>
    <script src="{{ \MockupParts\MockupPartsServiceProvider::asset('mockup-parts.js') }}" defer></script>
</head>
<body class="dl-body {{ $bodyClass ?? '' }}" @if ($dev ?? false) data-dl-dev="1" @endif>
    @yield('body')
</body>
</html>
