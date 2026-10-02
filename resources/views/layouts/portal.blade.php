<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="{{ $metaDescription ?? t('Careers at CV. Telasih Makmur -- offset printing & packaging job vacancies.') }}">

        <title>{{ $title ?? t('Careers') }} — Telasih Makmur</title>

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('partials.js-text')
        @livewireStyles
    </head>
    <body class="bg-[#f8fafc] text-[#1e2433] antialiased min-h-screen flex flex-col">
        <header class="bg-white border-b border-[#e6e9ef] sticky top-0 z-30">
            <div class="max-w-[1100px] mx-auto px-5 h-16 flex items-center justify-between">
                <a href="{{ route('careers.landing') }}" class="flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-[10px] flex-shrink-0" style="background: conic-gradient(from 200deg, #ff5f6d, #ffc371, #2f6fed, #8a5cf6, #ff5f6d);"></span>
                    <span class="leading-tight">
                        <b class="block text-[15px] tracking-[.3px] text-navy">TELASIH MAKMUR</b>
                        <span class="block text-[10px] tracking-[2px] text-muted uppercase">{{ t('Careers') }}</span>
                    </span>
                </a>
                <nav class="flex items-center gap-5 text-[13px] font-semibold">
                    <a href="{{ route('careers.landing') }}" class="text-[#495166] hover:text-navy">{{ t('Vacancies') }}</a>
                    @if (session('portal_candidate_uid'))
                        <a href="{{ route('careers.dashboard') }}" class="text-[#495166] hover:text-navy">{{ t('My Applications') }}</a>
                        <a href="{{ route('careers.logout') }}" class="bg-[#f1f4f9] text-navy px-3.5 py-2 rounded-md hover:bg-[#e6e9ef]">{{ t('Sign Out') }}</a>
                    @else
                        <a href="{{ route('careers.login') }}" class="text-[#495166] hover:text-navy">{{ t('Sign In') }}</a>
                        <a href="{{ route('careers.register') }}" class="bg-navy text-white px-3.5 py-2 rounded-md hover:opacity-90">{{ t('Register') }}</a>
                    @endif
                </nav>
            </div>
        </header>

        <main class="flex-1">
            {{ $slot }}
        </main>

        <footer class="bg-white border-t border-[#e6e9ef] mt-10">
            <div class="max-w-[1100px] mx-auto px-5 py-8 text-[12px] text-muted flex items-center justify-between flex-wrap gap-3">
                <span>&copy; {{ date('Y') }} CV. Telasih Makmur — {{ t('Offset Printing & Packaging') }}</span>
                <span>{{ t('Questions about your application? Contact HRD during working hours.') }}</span>
            </div>
        </footer>

        @livewireScripts
    </body>
</html>
