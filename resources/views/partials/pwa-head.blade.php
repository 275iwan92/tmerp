{{--
    Tag <head> + registrasi Service Worker supaya app bisa "Add to Home
    Screen"/"Install" di HP (Android Chrome & iOS Safari) -- lihat
    routes/web.php::pwa.manifest utk isi manifest dinamis & public/sw.js
    utk service worker (sengaja minimal, TIDAK caching agresif krn app ini
    Livewire server-rendered).
--}}
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="#2c3e69">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="ERP TM">
<link rel="apple-touch-icon" href="{{ asset('images/pwa-icon-192.png') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/pwa-icon-192.png') }}">

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }
</script>
