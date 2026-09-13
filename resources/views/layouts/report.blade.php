<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ?? 'Laporan Manajemen' }} — {{ \App\Support\Branding\Brand::name() }}</title>

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles

        {{--
            Shell ini SENGAJA meniru PERSIS struktur & CSS
            printflow_erp_complete_dashboard_mockup.html (sidebar navy gradient
            260px + grup Executive/Core ERP/Control Towers) -- instruksi user
            2026-09-13 "TIRU PERSIS mockup" setelah pendekatan awal (menumpang
            sidebar app lama) ditolak keras. Class di-prefix "rs-" (report-shell)
            supaya TIDAK bentrok dgn class global .grid/.card di app.css
            (lihat feedback_legacy_grid_class_collision.md) -- app.css
            .grid{grid-template-columns:repeat(4,...)} akan menimpa .grid
            polos milik mockup kalau tidak di-scope.
        --}}
        <style>
            {{--
                BUG 2026-09-13 (ditemukan user, "graphics jelek dan tidak
                berwarna"): x-report.kpi/badge/insight/svg-* SEMUA baca
                token --sh-*/--content-bg (lihat theme-vars.blade.php), tapi
                token itu cuma didefinisikan di dalam selector ".shell" --
                class yg dipasang di root main-menu.blade.php (shell app
                lama, isi module dimuat lewat <iframe>). Halaman Laporan ini
                route top-level TERSENDIRI (bukan di dalam iframe .shell),
                jadi var(--sh-accent) dkk selalu UNDEFINED -> browser jatuh
                ke initial/inherited (nyaris hitam-putih, ikon/grafik jadi
                kosong warna). Ini bukan regresi baru, pola yg sama berlaku
                sejak layar Laporan Manajemen pertama dibuat (layouts::app
                default Livewire juga TIDAK punya class="shell" di <body>)
                -- baru ketahuan sekarang krn sebelumnya cuma diverifikasi
                lewat Livewire::test (lolos fungsional), tidak pernah dibuka
                di browser sungguhan. FIX: definisikan token di sini secara
                TETAP (palet identik dgn :root mockup aslinya) -- shell ini
                memang bukan bagian sistem tema 12-kombinasi (themestandard.md,
                itu scope-nya konten dalam iframe .shell), jadi warna tetap
                terang selalu, sama seperti mockup sumbernya sendiri (tidak
                ada toggle dark/light di mockup ini).
            --}}
            .rs-app{
                --sh-accent:#2878f0; --sh-ok:#11a879; --sh-warn:#f09a22; --sh-amber:#f09a22;
                --sh-violet:#8358e8; --sh-danger:#e64b52;
                --sh-ink:#4a5578; --sh-ink-strong:#14213a; --sh-muted:#718096;
                --sh-surface:#ffffff; --sh-surface-alt:#f4f7fb; --sh-border:#e3e9f2;
                --content-bg:#f4f7fb;
            }
            .rs-app{display:grid;grid-template-columns:260px 1fr;min-height:100vh;font:13px/1.45 Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;background:#f4f7fb;color:#14213a}
            .rs-app button{font:inherit;cursor:pointer}
            .rs-aside{background:linear-gradient(180deg,#101c35,#091225);height:100vh;position:sticky;top:0;overflow:auto;padding:20px 13px;color:#c3d0e3;z-index:3}
            .rs-brand{color:#fff;padding:3px 10px 23px;display:flex;align-items:center;gap:10px;font-size:18px;font-weight:800;text-decoration:none}
            .rs-mark{width:35px;height:35px;border-radius:10px;background:conic-gradient(from 135deg,#08a8c7,#2878f0,#8358e8,#08a8c7);display:grid;place-items:center;box-shadow:0 7px 16px #0005;flex-shrink:0}
            .rs-brand small{display:block;color:#8ca4c9;font-size:9px;letter-spacing:1.5px;margin-top:-2px;font-weight:700}
            .rs-navgroup{font-size:9px;text-transform:uppercase;font-weight:800;letter-spacing:1.1px;color:#6d86ab;padding:11px 10px 5px}
            .rs-nav a{color:#becbe0;display:flex;align-items:center;gap:10px;border:0;background:transparent;width:100%;border-radius:8px;padding:9px 10px;text-align:left;font-weight:600;margin:1px 0;text-decoration:none}
            .rs-nav a:hover{color:#fff;background:#ffffff0b}
            .rs-nav a.active{color:#fff;background:linear-gradient(90deg,#2878f04c,#2878f010);box-shadow:inset 3px 0 #2878f0}
            .rs-ico{width:18px;text-align:center;font-size:14px}
            .rs-side-note{border-top:1px solid #ffffff18;margin:19px 5px;padding:15px 3px;color:#91a7c5;font-size:11px}
            .rs-avatar{display:inline-grid;place-items:center;background:#345484;width:27px;height:27px;border-radius:50%;color:#fff;font-size:10px;font-weight:800;margin-right:7px;vertical-align:middle}
            .rs-exit{display:flex;align-items:center;gap:8px;color:#f0a0a5;text-decoration:none;font-weight:700;font-size:11.5px;padding:8px 10px;border-radius:8px;margin-top:6px}
            .rs-exit:hover{background:#ffffff0b;color:#fff}
            .rs-top{height:60px;background:#fff;border-bottom:1px solid #e3e9f2;display:flex;align-items:center;justify-content:space-between;padding:0 26px;position:sticky;top:0;z-index:2}
            .rs-crumb{color:#718096;font-size:12px}
            .rs-crumb b{color:#14213a}
            @media(max-width:1100px){.rs-app{grid-template-columns:65px 1fr}.rs-aside{padding:18px 8px}.rs-brand span,.rs-navgroup,.rs-nav .rs-text,.rs-side-note,.rs-exit span{display:none}.rs-brand{padding:3px 7px 20px;justify-content:center}.rs-nav a{justify-content:center;padding:10px}.rs-exit{justify-content:center}}
            @media(max-width:620px){.rs-app{display:block}.rs-aside{height:56px;position:fixed;left:0;right:0;bottom:0;top:auto;z-index:5;padding:5px;overflow:hidden}.rs-brand,.rs-side-note{display:none}.rs-nav{display:flex;justify-content:space-around;overflow-x:auto}.rs-nav a{width:auto;margin:0;padding:10px}.rs-exit{display:none}.rs-top{padding:0 14px;height:56px}.rs-crumb{display:none}}
        </style>
    </head>
    <body>
        @php
            $__navGroups = [
                'Executive' => [
                    ['route' => 'report.executive', 'icon' => 'fa-solid fa-table-cells-large', 'label' => 'Executive Overview'],
                ],
                'Core ERP' => [
                    ['route' => 'report.purchase', 'icon' => 'fa-solid fa-truck-ramp-box', 'label' => 'Purchasing'],
                    ['route' => 'report.sales', 'icon' => 'fa-solid fa-handshake', 'label' => 'Sales & CRM'],
                    ['route' => 'report.accounting', 'icon' => 'fa-solid fa-scale-balanced', 'label' => 'Accounting'],
                    ['route' => 'report.production', 'icon' => 'fa-solid fa-industry', 'label' => 'Manufacturing'],
                    ['route' => 'report.hr', 'icon' => 'fa-solid fa-users', 'label' => 'HR & Payroll'],
                ],
                'Control Towers' => [
                    ['slug' => 'qms', 'icon' => 'fa-solid fa-circle-check', 'label' => 'QMS'],
                    ['slug' => 'cmms', 'icon' => 'fa-solid fa-screwdriver-wrench', 'label' => 'CMMS Maintenance'],
                    ['route' => 'report.warehouse', 'icon' => 'fa-solid fa-warehouse', 'label' => 'Warehouse'],
                    ['route' => 'report.costing', 'icon' => 'fa-solid fa-calculator', 'label' => 'Estimating & Costing'],
                    ['slug' => 'ehs', 'icon' => 'fa-solid fa-leaf', 'label' => 'EHS'],
                    ['slug' => 'assets', 'icon' => 'fa-solid fa-building-columns', 'label' => 'Assets & Capital'],
                ],
            ];
            // 4 item "Control Tower" tanpa modul nyata berbagi 1 route
            // (report.control-tower/{slug}) -- item lain pakai route sendiri.
            $__href = fn (array $i) => isset($i['slug']) ? route('report.control-tower', $i['slug']) : route($i['route']);
            $__isActive = fn (array $i) => isset($i['slug'])
                ? request()->route()?->getName() === 'report.control-tower' && request()->route('slug') === $i['slug']
                : request()->route()?->getName() === $i['route'];
            $__activeLabel = collect($__navGroups)->flatten(1)->first($__isActive)['label'] ?? ($title ?? 'Laporan');
        @endphp

        <div class="rs-app">
            <aside class="rs-aside">
                <a href="{{ route('report.executive') }}" class="rs-brand">
                    <span class="rs-mark"><i class="fa-solid fa-chart-line" style="color:#fff;font-size:14px"></i></span>
                    <span>{{ \App\Support\Branding\Brand::name() }}<small>LAPORAN MANAJEMEN</small></span>
                </a>

                <nav class="rs-nav">
                    @foreach ($__navGroups as $__group => $__items)
                        <div class="rs-navgroup">{{ $__group }}</div>
                        @foreach ($__items as $__item)
                            <a href="{{ $__href($__item) }}" class="{{ $__isActive($__item) ? 'active' : '' }}">
                                <span class="rs-ico"><i class="{{ $__item['icon'] }}"></i></span>
                                <span class="rs-text">{{ $__item['label'] }}</span>
                            </a>
                        @endforeach
                    @endforeach
                </nav>

                <div class="rs-side-note">
                    {{ \App\Support\Branding\Brand::name() }}<br>
                    <span class="rs-avatar">{{ strtoupper(substr(auth()->user()->name ?? '?', 0, 2)) }}</span>{{ auth()->user()->name ?? '' }} · Direksi

                    <a href="{{ route('menu') }}" class="rs-exit" style="color:#a8c3e8">
                        <span class="rs-ico"><i class="fa-solid fa-arrow-left"></i></span>
                        <span>Kembali ke Menu Utama</span>
                    </a>
                    <a href="{{ route('logout') }}" class="rs-exit">
                        <span class="rs-ico"><i class="fa-solid fa-right-from-bracket"></i></span>
                        <span>Keluar (Logout)</span>
                    </a>
                </div>
            </aside>

            <main>
                <header class="rs-top">
                    <div class="rs-crumb">Laporan Manajemen &nbsp;/&nbsp; <b>{{ $__activeLabel }}</b></div>
                </header>

                {{ $slot }}
            </main>
        </div>

        @livewireScripts
    </body>
</html>
