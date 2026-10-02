<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        @php($__brandName = \App\Support\Branding\Brand::name())
        <title>{{ $title ? str_replace(['TELASIH MAKMUR ERP', 'TELASIH MAKMUR'], [$__brandName, $__brandName], $title) : $__brandName }}</title>

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('partials.js-text')
        @include('partials.theme-vars')
        @include('partials.brand-font')
        @include('partials.pwa-head')

        @livewireStyles
    </head>
    <body class="guestpage">
        {{ $slot }}

        @livewireScripts
    </body>
</html>
