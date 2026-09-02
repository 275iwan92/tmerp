{{-- Personalisasi dashboard Filament PER-USER (docs/admindashboardstandard.md
    §10, mockup docs/mockups/mockup_dashboardcolor.html).

    KENAPA SEMUA LOGIKA PERSONALISASI ADA DI SINI, BUKAN DI AdminPanelProvider::panel():
    panel() cuma dieksekusi SEKALI saat PanelRegistry pertama kali di-resolve
    dari container (lihat Filament::registerPanel() -> app()->resolving()),
    JAUH SEBELUM middleware auth/session jalan utk request itu -- Auth::user()
    di situ SELALU null (ditemukan & diperbaiki 2026-09-02, awalnya salah kira
    panel() jalan per-request). Render hook HEAD_END ini TERBUKTI beda --
    dieksekusi saat render Blade sungguhan, SESUDAH auth mapan, jadi
    auth()->user() di sini valid per-request. --}}
@php
    $__user = auth()->user();
    $__fiTheme = $__user->admin_theme_mode ?? 'light';
    $__fiAccent = $__user->admin_theme_accent ?? 'blue';
    $__isDark = in_array($__fiTheme, ['dark', 'black'], true);
    // Palette 11-shade SAMA PERSIS dgn yang dipakai Panel::colors() internal
    // (Filament\Support\Colors\Color::generatePalette()) -- override
    // --primary-* di sini menang krn <style> ini dirender PALING AKHIR di
    // <head> (HEAD_END), cascade CSS menang berdasar urutan sumber untuk
    // selector/specificity yang sama (:root).
    $__palette = \Filament\Support\Colors\Color::hex(\App\Support\Branding\DashboardAccent::hex($__fiAccent));
@endphp
<style>
    :root {
        @foreach ($__palette as $__shade => $__value)
            --primary-{{ $__shade }}: {{ $__value }};
        @endforeach
    }

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
<script>
    (function () {
        var mode = @js($__fiTheme);
        var isDark = @js($__isDark);

        document.documentElement.setAttribute('data-fi-theme', mode);
        document.documentElement.setAttribute('data-fi-accent', @js($__fiAccent));

        // Filament BAWAAN baca localStorage.theme (fallback --default-theme-mode,
        // itu sendiri statis krn dihitung di panel() -- lihat catatan atas).
        // Set localStorage DI SINI supaya Filament::loadDarkMode() (jalan
        // ulang tiap event livewire:navigated, SPA-style) otomatis konsisten
        // ke preferensi user tanpa perlu di-override manual tiap navigasi.
        try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch (e) {}

        // Toggle langsung juga (bukan cuma andalkan localStorage) supaya paint
        // pertama benar walau Filament::loadDarkMode() dari base.blade.php
        // (yang jalan SEBELUM hook ini di <head>) sempat baca localStorage lama.
        document.documentElement.classList.toggle('dark', isDark);
    })();
</script>
