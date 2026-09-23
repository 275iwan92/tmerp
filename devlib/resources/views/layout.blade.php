<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'DevLib' }} — TELASIH MAKMUR</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ \Devlib\DevlibServiceProvider::asset('devlib.css') }}">
    <script src="{{ \Devlib\DevlibServiceProvider::asset('devlib.js') }}" defer></script>
</head>
<body class="dl-body">
    @yield('body')
</body>
</html>
