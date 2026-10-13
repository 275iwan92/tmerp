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
                memang bukan bagian sistem tema 12-kombinasi (themestandard.md (diarsipkan),
                itu scope-nya konten dalam iframe .shell), jadi warna tetap
                terang selalu, sama seperti mockup sumbernya sendiri (tidak
                ada toggle dark/light di mockup ini).
            --}}
            .rs-app, .upanels{
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
            .rs-top{height:60px;background:#fff;border-bottom:1px solid #e3e9f2;display:flex;align-items:center;justify-content:space-between;padding:0 26px;position:sticky;top:0;z-index:4;gap:12px}
            .rs-crumb{color:#718096;font-size:12px;white-space:nowrap}
            .rs-crumb b{color:#14213a}
            {{--
                Switcher mode -- LAWAN dari sidebar app lama (main-menu.blade.php
                topbar) yg juga dapat pill sejenis (lihat itemRoutes/@if MGMT di
                sana), supaya berpindah Operasional<->Laporan simetris 1 klik
                dari KEDUA sisi, bukan cuma satu arah.
            --}}
            .rs-switch{display:flex;background:#eef2f8;border-radius:10px;padding:3px;gap:2px;flex-shrink:1;min-width:0}
            .rs-switch a{display:flex;align-items:center;gap:6px;padding:7px 12px;border-radius:8px;font-size:11.5px;font-weight:700;color:#5a6a86;text-decoration:none;white-space:nowrap}
            .rs-switch a:hover{color:#14213a}
            .rs-switch a.active{background:#fff;color:#2878f0;box-shadow:0 1px 3px #1c365b1f}
            {{--
                CSS di bawah ini mereplikasi PERSIS class .pop/.avatar-btn/
                .avatar/.av-dot/.pop-menu/.um-*/tema-matrix milik komponen
                app/Livewire/Shell/UserMenu.php (aslinya scoped ".shell" di
                app.css) -- shell ini TIDAK punya class="shell" (sengaja
                fixed-light, di luar sistem tema 12-kombinasi, lihat komentar
                --sh-* di atas), jadi nilai warna literal mode-terang
                disalin ke sini pakai token --sh-* yang SAMA persis dgn
                app.css supaya dropdown tampil IDENTIK di kedua shell.
            --}}
            .pop{position:relative;display:flex;align-items:center}
            .avatar-btn{display:flex;align-items:center;gap:7px;background:transparent;border:0;cursor:pointer;padding:6px;border-radius:8px;font-family:inherit}
            .avatar-btn:hover{background:rgba(20,26,45,.06)}
            .avatar{width:30px;height:30px;border-radius:50%;background:#c3ccdf;display:flex;align-items:center;justify-content:center;position:relative;cursor:pointer}
            .avatar svg{width:20px;height:20px;fill:#5a6488}
            .av-dot{position:absolute;top:-1px;right:-1px;width:10px;height:10px;border-radius:50%;background:#e5484d;border:2px solid #fff}
            .av-dot.hide{display:none}
            .pop-menu{display:none;position:absolute;right:0;top:46px;background:var(--sh-surface);color:var(--sh-ink);border-radius:12px;min-width:256px;overflow:hidden;z-index:70;box-shadow:0 16px 44px rgba(15,22,45,.24)}
            .pop-menu.open{display:block}
            .um-head{display:flex;align-items:center;gap:12px;padding:15px 16px;border-bottom:1px solid var(--sh-border)}
            .um-av{width:44px;height:44px;border-radius:50%;background:#dce1ee;display:flex;align-items:center;justify-content:center;flex-shrink:0}
            .um-av svg{width:26px;height:26px;fill:#7b85a4}
            .um-name{font-weight:700;color:var(--sh-ink-strong);font-size:14.5px}
            .um-mail{font-size:12px;color:var(--sh-muted)}
            .um-role{display:inline-block;margin-top:3px;font-size:10px;font-weight:700;color:var(--sh-accent);background:#e7effe;padding:2px 8px;border-radius:10px}
            .um-list{padding:6px}
            .um-item{display:flex;align-items:center;gap:11px;width:100%;background:transparent;border:0;text-align:left;padding:10px 12px;border-radius:8px;cursor:pointer;font-size:13.5px;color:var(--sh-ink);text-decoration:none;font-family:inherit}
            .um-item:hover{background:#f2f5fb}
            .um-item i{width:18px;text-align:center;color:var(--sh-muted)}
            .um-item.danger{color:#d0453f}
            .um-item.danger:hover{background:#fdeceb}
            .um-sep{height:1px;background:var(--sh-border);margin:6px 8px}
            .um-cap{font-size:10px;letter-spacing:.6px;text-transform:uppercase;color:var(--sh-muted);font-weight:700;padding:10px 12px 3px}
            .um-count{margin-left:auto;background:#e5484d;color:#fff;font-size:10.5px;font-weight:700;border-radius:8px;min-width:18px;height:18px;padding:0 6px;display:flex;align-items:center;justify-content:center}
            .um-flag{border:1px solid var(--sh-border);background:#fff;border-radius:7px;padding:2px 7px;font-size:14px;cursor:pointer}
            .um-flag.active{border-color:var(--sh-accent);box-shadow:0 0 0 2px rgba(47,111,237,.15)}
            .um-theme-grid{display:flex;flex-wrap:wrap;gap:8px;padding:4px 12px 10px}
            .um-swatch{width:22px;height:22px;border-radius:50%;border:2px solid transparent;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;font-size:9px;flex-shrink:0}
            .um-swatch.active{border-color:var(--sh-ink-strong);box-shadow:0 0 0 2px var(--sh-surface)}
            .um-theme-matrix{padding:4px 12px 6px}
            .utm-hdr{display:grid;gap:6px;margin-bottom:4px;grid-template-columns:44px repeat(3,22px)}
            .utm-hdr span{font-size:9px;font-weight:700;text-align:center;color:var(--sh-muted)}
            .utm-row{display:grid;gap:6px;align-items:center;margin-bottom:6px;grid-template-columns:44px repeat(3,22px)}
            .utm-lbl{font-size:10.5px;font-weight:600;color:var(--sh-ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            .utm-empty{width:22px;height:22px}
            .rs-topactions{display:flex;align-items:center;gap:10px;min-width:0}
            @media(max-width:1100px){.rs-app{grid-template-columns:65px 1fr}.rs-aside{padding:18px 8px}.rs-brand span,.rs-navgroup,.rs-nav .rs-text,.rs-side-note{display:none}.rs-brand{padding:3px 7px 20px;justify-content:center}.rs-nav a{justify-content:center;padding:10px}}
            @media(max-width:620px){.rs-app{display:block}.rs-aside{height:56px;position:fixed;left:0;right:0;bottom:0;top:auto;z-index:5;padding:5px;overflow:hidden}.rs-brand,.rs-side-note{display:none}.rs-nav{display:flex;justify-content:space-around;overflow-x:auto}.rs-nav a{width:auto;margin:0;padding:10px}.rs-top{padding:0 10px;height:52px}.rs-crumb{display:none}.rs-switch a span{display:none}.rs-switch a{padding:8px 10px}}
            /* Dibuka di tab Menu v2 (mockup.test): panel kiri & top bar laporan disembunyikan -- daftar laporan dan
               pengalih Operasional | Laporan Manajemen sudah ada di Menu v2 (keputusan user 2026-09-25). */
            .rs-embed .rs-app{grid-template-columns:1fr}
            .rs-embed .rs-aside,.rs-embed .rs-top{display:none}
        </style>
            @if (config('app.ui') === 'v2')
            {{-- Hanya di dalam tab (iframe) Menu v2; dibuka langsung di tab browser tetap lengkap. --}}
            <script>if (window.self !== window.top) document.documentElement.classList.add('rs-embed');</script>
        @endif
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
                    ['route' => 'report.warehouse', 'icon' => 'fa-solid fa-warehouse', 'label' => 'Warehouse'],
                ],
            ];
            // Item placeholder QMS / CMMS / EHS / Assets / Costing dibuang (Jo 2026-10-13): hanya laporan yang punya data nyata.
            $__href = fn (array $i) => route($i['route']);
            $__isActive = fn (array $i) => request()->route()?->getName() === $i['route'];
            $__activeLabel = collect($__navGroups)->flatten(1)->first($__isActive)['label'] ?? ($title ?? 'Laporan');
            $__aiKeywords = [
                'report.executive' => 'ringkasan eksekutif overview', 'report.purchase' => 'pembelian purchase',
                'report.sales' => 'penjualan customer crm', 'report.accounting' => 'akuntansi keuangan finance',
                'report.production' => 'produksi manufacturing', 'report.hr' => 'karyawan gaji payroll sdm',
                'report.warehouse' => 'gudang stok inventory',
            ];
            $__aiNav = collect($__navGroups)->flatten(1)->map(fn ($i) => [
                'label' => $i['label'],
                'href' => $__href($i),
                'keywords' => $__aiKeywords[$i['route']] ?? '',
            ])->values();
        @endphp

        <div x-data="reportShell(@js($__aiNav))">
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
                </div>
            </aside>

            <main>
                {{--
                    Switcher mode Operasional<->Laporan Manajemen (keputusan
                    user 2026-09-13, "switch tidak efektif, jelek banget" --
                    sebelumnya cuma link kecil "Kembali ke Menu Utama" di
                    dasar sidebar, dan HILANG SAMA SEKALI di layar sempit
                    (<620px, .rs-exit{display:none}) -- tidak ada jalan
                    keluar sama sekali di mobile). Sekarang: segmented
                    control jelas di topbar, SELALU tampil (topbar tidak
                    ikut disembunyikan di breakpoint manapun), 1 klik.
                --}}
                <header class="rs-top">
                    <div class="rs-crumb">Laporan Manajemen &nbsp;/&nbsp; <b>{{ $__activeLabel }}</b></div>
                    <div class="rs-topactions">
                        <div class="rs-switch">
                            <a href="{{ route('menu') }}"><i class="fa-solid fa-grip"></i><span>Operasional</span></a>
                            <a class="active" href="{{ route('report.executive') }}"><i class="fa-solid fa-chart-line"></i><span>Laporan Manajemen</span></a>
                        </div>
                        {{--
                            Dropdown user = komponen yg SAMA dgn Menu
                            Operasional (instruksi user 2026-09-14). Panelnya
                            (Notifikasi/Chat/AI/Edit profil/Ubah password/User
                            Configuration) dibuka DI SINI lewat
                            App\Livewire\Shell\UserPanels di bawah -- TIDAK lagi
                            melempar user ke Menu Operasional (keluhan user
                            2026-09-18). Flag-nya dari x-data reportShell.
                        --}}
                        <div @click.outside="ddOpen = false">
                            <livewire:shell.user-menu :standalone="true" />
                        </div>
                    </div>
                </header>

                {{ $slot }}
            </main>
        </div>
        <livewire:shell.user-panels :standalone="true" />
        </div>

        @livewireScripts
    </body>
</html>
