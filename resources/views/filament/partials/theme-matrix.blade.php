{{-- Matrix warna dashboard Filament (docs/admindashboardstandard.md §10,
    mockup docs/mockups/mockup_dashboardcolor.html) -- LAPISAN KOSMETIK di
    ATAS sistem native Filament (accent asli via ->colors(), dark/light asli
    via ->darkMode()); ini cuma menambah variasi background per mode (pastel
    tint/glass blur/OLED hitam) yang Filament sendiri tidak punya. TIDAK
    mengklaim override SEMUA komponen Filament -- cuma container besar yang
    class-nya stabil (body.fi-body/.fi-sidebar/.fi-topbar/.fi-main).

    data-fi-theme/data-fi-accent di-set 2 jalur: (a) server-side di sini dari
    preferensi tersimpan user (hindari flash saat load pertama), (b) client
    Alpine di dashboard-theme-picker.blade.php saat user ganti langsung (live,
    persis mockup) -- keduanya tulis ke atribut yang sama di <html>. --}}
@php
    $__user = auth()->user();
    $__fiTheme = $__user->admin_theme_mode ?? 'light';
    $__fiAccent = $__user->admin_theme_accent ?? 'blue';
@endphp
<script>document.documentElement.setAttribute('data-fi-theme', @js($__fiTheme)); document.documentElement.setAttribute('data-fi-accent', @js($__fiAccent));</script>
<style>
    :root[data-fi-accent="blue"] { --fi-accent-tint: #f0f4ff; }
    :root[data-fi-accent="green"] { --fi-accent-tint: #f0fdf4; }
    :root[data-fi-accent="red"] { --fi-accent-tint: #fff1f2; }
    :root[data-fi-accent="purple"] { --fi-accent-tint: #f5f3ff; }
    :root[data-fi-accent="amber"] { --fi-accent-tint: #fffbeb; }
    :root[data-fi-accent="cyan"] { --fi-accent-tint: #ecfeff; }
    :root[data-fi-accent="mono"] { --fi-accent-tint: #f8fafc; }

    html[data-fi-theme="pastel"] body.fi-body,
    html[data-fi-theme="pastel"] .fi-main { background-color: var(--fi-accent-tint) !important; }
    html[data-fi-theme="pastel"] .fi-sidebar,
    html[data-fi-theme="pastel"] .fi-topbar { background-color: rgba(255, 255, 255, .7) !important; }

    html[data-fi-theme="bright"] body.fi-body { background: linear-gradient(135deg, #e0e7ff 0%, #fae8ff 100%) !important; }
    html[data-fi-theme="bright"] .fi-sidebar,
    html[data-fi-theme="bright"] .fi-topbar,
    html[data-fi-theme="bright"] .fi-main .fi-section-content-ctn { background-color: rgba(255, 255, 255, .55) !important; backdrop-filter: blur(16px); }

    html[data-fi-theme="black"] body.fi-body,
    html[data-fi-theme="black"] .fi-main { background-color: #000000 !important; }
    html[data-fi-theme="black"] .fi-sidebar,
    html[data-fi-theme="black"] .fi-topbar { background-color: #09090b !important; border-color: #27272a !important; }
</style>
