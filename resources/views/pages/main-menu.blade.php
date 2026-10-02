<?php

use App\Models\Sys\SysAppMenu;
use App\Models\Sys\SysAppMenuColor;
use App\Support\Branding\Brand;
use App\Support\Localization\Translator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

// #[Title] PHP attribute WAJIB constant expression -- TIDAK BISA panggil
// Brand::name() (query DB) langsung di sini. SOLUSI 2026-09-02 (bukan lagi
// "di luar cakupan"): literal "TELASIH MAKMUR ERP" di string ini cuma
// PLACEHOLDER, diganti live jadi APP_NAME sungguhan oleh layouts::shell/
// guest/app (str_replace di <title>, lihat komentar di sana) -- jangan
// hapus/ubah literalnya sembarangan, string INI yang dicari-ganti.
new #[Layout('layouts::shell')] #[Title('Home — TELASIH MAKMUR ERP')] class extends Component
{
    use WithFileUploads;

    // Panel dropdown user (Notifikasi/Chat/AI/Edit profil/Ubah password/User
    // Configuration) dipindah ke App\Livewire\Shell\UserPanels 2026-09-18 --
    // dipakai bersama shell Laporan Manajemen, jangan dibuat ulang di sini.

    // Help & Support (tiket) DIPINDAH ke resources/views/pages/help/index.blade.php
    // 2026-08-30 (instruksi user: gabung ticketing + Help Center jadi 1 tempat) --
    // properti support_*/myTickets/submitSupportTicket/resetSupportForm yang
    // dulu ada di sini SUDAH DIHAPUS, jangan bikin lagi versi duplikat di sini.

    // Direksi BOLEH membuka menu operasional (aturan diubah user 2026-09-25; kunci 2026-09-13 dicabut).
    // Pendaratan awal Direksi tetap Laporan Manajemen -- diatur di pages/auth/login.blade.php.

    // code sys_app_menu -> URL route Laravel nyata. Item yang tak disebut di
    // sini tetap tampil di menu (placeholder pane) — lihat resources/js/app-shell.js.
    protected function itemRoutes(): array
    {
        return \App\Support\Menu\MenuRoutes::map();
    }

    // Identitas aplikasi (docs/configurationstandard.md) -- APP_NAME BUKAN
    // "TELASIH MAKMUR" hardcode lagi, dibaca dari tm2sys.sys_constant lewat
    // App\Support\Branding\Brand, diedit di layar Configuration.
    #[Computed]
    public function brandName(): string
    {
        return Brand::name();
    }

    #[Computed]
    public function brandTagline(): string
    {
        return Brand::tagline();
    }

    #[Computed]
    public function brandLogoUrl(): ?string
    {
        return Brand::logoUrl();
    }

    // Pohon menu (module->group->item) dari sys_app_menu, difilter izin lewat
    // SecUser::visibleAppMenuCodes() (-> sec_module). Dikirim ke Alpine sekali
    // via @js() — navigasi tab/sidebar sesudahnya murni client-side.
    #[Computed]
    public function menuTree(): array
    {
        $visible = Auth::user()->visibleAppMenuCodes();

        $modules = SysAppMenu::query()
            ->where('menu_type', 'module')
            ->where('activate', true)
            ->whereIn('code', $visible)
            ->orderBy('sort_order')
            ->get();

        $children = SysAppMenu::query()
            ->whereNotNull('parent_id')
            ->where('activate', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('parent_id');

        $routes = $this->itemRoutes();
        $statusColors = SysAppMenuColor::all()->keyBy('status_code');

        // module_status_system (globalrules.yaml): item bawa status_code sendiri
        // dari DB; module/group DERIVE dari anak-anaknya (worst-case menang) --
        // TIDAK disimpan sendiri, supaya "Sales" langsung kelihatan abu kalau ADA
        // satu submenu yang belum dibangun, tanpa perlu expand satu-satu.
        $deriveStatus = function (array $childStatuses): string {
            if (empty($childStatuses) || in_array('NOT_BUILT', $childStatuses, true)) {
                return 'NOT_BUILT';
            }

            return in_array('READY_FOR_QC', $childStatuses, true) ? 'READY_FOR_QC' : 'LOCKED';
        };

        $build = function (SysAppMenu $node) use (&$build, $children, $routes, $statusColors, $deriveStatus) {
            $builtChildren = ($children->get($node->id) ?? collect())->map($build)->values()->all();

            $statusCode = $node->menu_type === 'item'
                ? ($node->status_code ?: 'NOT_BUILT')
                : $deriveStatus(array_column($builtChildren, 'statusCode'));

            $color = $statusColors->get($statusCode);

            return [
                'id' => $node->id,
                'code' => $node->code,
                'label' => t($node->label, 'tm2sys.sys_app_menu.label'),
                'shortLabel' => $node->short_label,
                'tooltip' => $node->tooltip ? t($node->tooltip, 'tm2sys.sys_app_menu.tooltip') : null,
                'icon' => $this->iconFor($node),
                'menuType' => $node->menu_type,
                'objectType' => $node->object_type,
                'url' => $routes[$node->code] ?? null,
                'statusCode' => $statusCode,
                'statusColor' => $color->color_hex ?? '#9ca3af',
                'statusLabel' => t($color->label ?? 'Not Available'),
                'isClickable' => (bool) ($color->is_clickable ?? false),
                'children' => $builtChildren,
            ];
        };

        return $modules->map($build)->values()->all();
    }

    // Font Awesome: module pakai icon kolom (sudah FA, lihat migration
    // 2026_08_09_000003). Group tak butuh ikon (hanya label capsule di
    // sidebar). Item tanpa icon sendiri jatuh ke ikon per object_type,
    // mirror typeIcon() di docs/mockups/main_menu_v2.html.
    protected function iconFor(SysAppMenu $node): ?string
    {
        if ($node->menu_type === 'group') {
            return null;
        }

        return $node->icon ?: match ($node->object_type) {
            'grid' => 'fa-solid fa-table-list',
            'report' => 'fa-solid fa-chart-column',
            default => 'fa-solid fa-pen-to-square',
        };
    }

    // Daftar datar item (utk command palette & asisten AI preview).
    #[Computed]
    public function flatItems(): array
    {
        $flat = [];

        $walk = function (array $nodes, string $modLabel) use (&$walk, &$flat) {
            foreach ($nodes as $n) {
                $mod = $n['menuType'] === 'module' ? $n['label'] : $modLabel;

                if ($n['menuType'] === 'item') {
                    $flat[] = [
                        'code' => $n['code'],
                        'label' => $n['label'],
                        'icon' => $n['icon'],
                        'objectType' => $n['objectType'],
                        'url' => $n['url'],
                        'statusCode' => $n['statusCode'],
                        'statusColor' => $n['statusColor'],
                        'statusLabel' => $n['statusLabel'],
                        'isClickable' => $n['isClickable'],
                        'mod' => $mod,
                    ];
                }

                if (! empty($n['children'])) {
                    $walk($n['children'], $mod);
                }
            }
        };

        $walk($this->menuTree, '');

        return $flat;
    }

    // ================= TEMA (sys_theme, instruksi user 2026-08-31) =================
    // Light/dark BUKAN sumbu terpisah dari tema warna -- 1 baris sys_theme =
    // hue + mode sekaligus (mis. "Navy Gelap"), dipilih lewat sec_user.theme_code
    // yang sudah ada. theme-vars.blade.php baca ulang tiap full page load, jadi
    // ganti tema butuh reload (lihat pemanggil $wire.setTheme()/quickToggleTheme()
    // di Blade -- selalu diikuti location.reload()).
    // Matrix 4 mode (pastel/cerah/dark/hitam) x 3 corak (blue/green/red) --
    // instruksi user 2026-08-31 "matrix-kan dengan corak, taruh dalam tabel
    // supaya mudah diubah ke depannya". Data 100% dari sys_theme.family/hue
    // (migration 2026_08_31_000004) -- tambah baris baru di DB otomatis
    // muncul di sel yang sesuai, tak perlu ubah kode ini. Tema lama yang di
    // luar B/G/R (Slate/Indigo/Plum/Teal, family NULL) tampil terpisah di
    // grup "Klasik".
    #[Computed]
    public function themeMatrix(): array
    {
        $themes = \App\Models\Sys\SysTheme::where('activate', true)->orderBy('sort_order')->get();

        $families = ['pastel' => t('Pastel'), 'cerah' => t('Bright'), 'dark' => t('Dark'), 'hitam' => t('Black')];
        $hues = ['blue' => 'B', 'green' => 'G', 'red' => 'R'];

        $matrix = [];
        foreach ($families as $fKey => $fLabel) {
            $cells = [];
            foreach ($hues as $hKey => $hLabel) {
                $t = $themes->first(fn ($x) => $x->family === $fKey && $x->hue === $hKey);
                $cells[] = $t ? ['code' => $t->code, 'name' => $t->name, 'primary' => $t->primary_color] : null;
            }
            $matrix[] = ['label' => $fLabel, 'cells' => $cells];
        }

        $classic = $themes->whereNull('family')
            ->map(fn ($t) => ['code' => $t->code, 'name' => $t->name, 'primary' => $t->primary_color, 'mode' => $t->mode])
            ->values()->all();

        return ['families' => array_values($families), 'hues' => array_values($hues), 'matrix' => $matrix, 'classic' => $classic];
    }

    public function currentThemeCode(): string
    {
        return \App\Support\ActiveTheme::resolve()->code;
    }

    public function currentThemeMode(): string
    {
        return \App\Support\ActiveTheme::resolve()->mode ?? 'light';
    }

    public function setTheme(string $code): void
    {
        if (! \App\Models\Sys\SysTheme::where('code', $code)->where('activate', true)->exists()) {
            return;
        }

        Auth::user()->update(['theme_code' => $code]);
    }

    // Kerangka i18n (2026-09-07) -- mirror pola setTheme()/currentThemeCode()
    // di atas, cuma target kolomnya sec_user.lang_code. Lihat
    // App\Support\Localization\Translator utk cakupan & alasan baru shell
    // chrome yang ditarik (BELUM semua layar).
    public function currentLangCode(): string
    {
        return \App\Support\Localization\Translator::lang();
    }

    public function setLang(string $code): void
    {
        \App\Support\Localization\Translator::setLang($code);
    }

    // Flag emoji cuma UI sugar (bukan kolom DB, sys_languages tak punya kolom
    // flag) -- daftar bahasa AKTUAL tetap dari tabel (Settings > Language),
    // menambah baris baru otomatis nongol di sini dgn fallback ikon globe.
    #[Computed]
    public function availableLanguages()
    {
        $flags = ['id' => '🇮🇩', 'en' => '🇬🇧'];

        return \App\Models\Sys\SysLanguage::orderByDesc('is_default')->orderBy('code')->get()
            ->map(fn ($l) => ['code' => $l->code, 'name' => $l->name, 'flag' => $flags[$l->code] ?? null]);
    }

    public function quickToggleTheme(): void
    {
        $current = \App\Support\ActiveTheme::resolve();

        if (($current->mode ?? 'light') === 'dark') {
            $lightCode = str_ends_with($current->code, '_dark') ? substr($current->code, 0, -5) : null;
            $target = $lightCode && \App\Models\Sys\SysTheme::where('code', $lightCode)->where('activate', true)->exists()
                ? $lightCode
                : (\App\Models\Sys\SysTheme::where('is_default', true)->value('code') ?? 'navy');
        } else {
            $darkCode = $current->code.'_dark';
            $target = \App\Models\Sys\SysTheme::where('code', $darkCode)->where('activate', true)->exists()
                ? $darkCode
                : (\App\Models\Sys\SysTheme::where('mode', 'dark')->orderBy('sort_order')->value('code') ?? 'navy_dark');
        }

        $this->setTheme($target);
    }

    public function greetName(): string
    {
        $name = Auth::user()->name ?: t('User');

        return trim(strtok($name, ' '));
    }

    // Item FLAT dropdown avatar (Pesan & Info + Akun), sumbernya sys_user_menu
    // -- lihat App\Models\Sys\SysUserMenu docblock kenapa widget Theme/Bahasa
    // TIDAK ikut sini (tetap hardcode di bawah, terlalu bespoke).
    #[Computed]
    public function userMenuItems()
    {
        return $this->visibleUserMenuItems(['Pesan & Info', 'Akun'])->groupBy('section');
    }

    // Sub-item dalam grup collapsible "Configuration" (Aktivitas saya, User
    // Configuration) -- section SENDIRI (bukan ikut 'Akun') 2026-09-07 supaya
    // tetap dirender nested di dalam collapse, bukan ikut nongol di list flat
    // atas. Widget Tema/Bahasa TETAP hardcode (bukan baris label+klik biasa).
    #[Computed]
    public function configMenuItems()
    {
        return $this->visibleUserMenuItems(['Configuration']);
    }

    protected function visibleUserMenuItems(array $sections)
    {
        $user = Auth::user();

        return \App\Models\Sys\SysUserMenu::query()
            ->where('activate', 1)
            ->whereIn('section', $sections)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($item) => match ($item->visibility) {
                'employee_linked' => (bool) $user->employee_id,
                'non_employee_linked' => ! $user->employee_id,
                'setup_non_admin' => $user->canSetup() && ! $user->is_admin,
                default => true,
            });
    }

    public function logout()
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }

};
?>

<div class="shell"
     x-data="appShell(@js($this->menuTree), @js($this->flatItems), @js(t('Home')))"
     :class="{ mini: mini, nosidebar: nosidebar, notopbar: notopbar }"
     @keydown.ctrl.k.window.prevent="paletteOpen ? closePalette() : openPalette()"
     @keydown.cmd.k.window.prevent="paletteOpen ? closePalette() : openPalette()"
     @keydown.escape.window="closePalette()"
     @click.outside="ddOpen = false">

    <aside>
        <div class="side-head">
            <button type="button" class="logo-home" @click="selectModule('HOME')" title="{{ t('Back to Home') }}">
                <span class="logo-badge">
                    @if ($this->brandLogoUrl)
                        <img src="{{ $this->brandLogoUrl }}" alt="{{ $this->brandName }}" class="w-full h-full object-cover rounded-[9px]">
                    @endif
                </span>
                <span class="logo-txt"><b>{{ $this->brandName }}</b>@if ($this->brandTagline)<br><span>{{ $this->brandTagline }}</span>@endif</span>
            </button>
            <button type="button" class="side-collapse" @click="mini = !mini" title="{{ t('Collapse sidebar (mini)') }}">⊟</button>
        </div>
        <div class="mod-title">
            <template x-if="currentModule">
                <span class="ic"><i :class="currentModule.icon"></i></span>
            </template>
            <template x-if="!currentModule">
                <span class="ic"><i class="fa-solid fa-house"></i></span>
            </template>
            <span class="lbl" x-text="currentModule ? currentModule.label.toUpperCase() : @js(mb_strtoupper(t('Home')))"></span>
        </div>
        <div class="side-nav tm-scroll">
            <template x-if="activeMod === 'HOME'">
                <div class="side-hint">{{ t('Module') }} <b>{{ t('Home') }}</b>. {{ t('Choose another module above to show its submenu here.') }}</div>
            </template>
            <template x-for="grp in sidebarGroups" :key="grp.id">
                <div x-data="{ open: false }">
                    <template x-if="grp.menuType === 'group'">
                        <button type="button" class="side-cap-btn" @click="open = !open" :title="open ? @js(t('Collapse')) : @js(t('Open'))">
                            <span class="side-cap" x-text="grp.label"></span>
                            <i class="fa-solid fa-chevron-down chev" :class="{ collapsed: !open }"></i>
                        </button>
                    </template>
                    <template x-for="it in (grp.menuType === 'group' ? grp.children : [grp])" :key="it.id">
                        <button type="button" class="leaf" x-show="grp.menuType !== 'group' || open"
                            :class="{ active: activeTabCode === it.code, 'not-built': !it.isClickable }" :title="it.statusLabel" @click="openTab(it.code)">
                            <span class="lic"><i :class="it.icon || 'fa-solid fa-file-lines'"></i></span>
                            <span class="lbl" x-text="it.label"></span>
                            <span class="status-dot" :style="{ background: it.statusColor }"></span>
                        </button>
                    </template>
                </div>
            </template>
        </div>
        @if (Auth::user()->is_admin)
            {{-- Footer "Settings"/"Security" -- 2026-09-07: dulu 2 array Alpine
            literal hardcode di sini (label/icon/urutan tulis manual di Blade,
            beda sumber dari sys_app_menu.label yang dibaca modul lain) --
            sekarang 100% data-driven dari sys_app_menu lewat setupGroups
            (code SETUP -> group SET_GRP_SETTINGS/SET_GRP_SECURITY -> leaf
            item, migration 2026_09_07_000004). Supaya menu ini gampang
            dikonfigurasi (ubah label/icon/urutan/tambah-hapus item) cukup
            edit baris tabel, TIDAK perlu sentuh Blade lagi -- sama seperti
            semua modul lain. Markup persis mirror blok `sidebarGroups` di
            atas. docs/sidebarmenustandard.md. is_admin only (SETUP module
            gate) -- Portal Karyawan tab Security (profil+password non-admin)
            TIDAK berubah/tidak kena gate ini. --}}
            <template x-for="grp in setupGroups" :key="grp.id">
                <div class="side-footer" x-data="{ open: false }">
                    <template x-if="grp.menuType === 'group'">
                        <button type="button" class="side-cap-btn" @click="open = !open" :title="open ? @js(t('Collapse')) : @js(t('Open'))">
                            <span class="side-cap" x-text="grp.label"></span>
                            <i class="fa-solid fa-chevron-down chev" :class="{ collapsed: !open }"></i>
                        </button>
                    </template>
                    <template x-for="it in (grp.menuType === 'group' ? grp.children : [grp])" :key="it.id">
                        <button type="button" class="leaf" x-show="grp.menuType !== 'group' || open"
                            :class="{ active: activeTabCode === it.code, 'not-built': !it.isClickable }" :title="it.statusLabel" @click="openTab(it.code)">
                            <span class="lic"><i :class="it.icon || 'fa-solid fa-file-lines'"></i></span>
                            <span class="lbl" x-text="it.label"></span>
                            <span class="status-dot" :style="{ background: it.statusColor }"></span>
                        </button>
                    </template>
                </div>
            </template>
        @endif
    </aside>

    <div class="main">
        <header class="topbar">
            <button type="button" class="tb-btn" @click="nosidebar = !nosidebar" title="{{ t('Hide sidebar') }}">☰</button>
            <nav class="mega">
                {{--
                    SETUP sengaja tidak muncul di sini -- khusus admin, aksesnya
                    lewat menu profil ("Pengaturan (Setup)" di dropdown avatar).

                    MGMT (Laporan Manajemen) JUGA dikeluarkan dari daftar module
                    biasa (keluhan user 2026-09-13, screenshot: "kebodohan kamu,
                    buat Laporan Manajemen di Operational menu" -- MGMT bukan
                    modul operasional yg diklik-lalu-expand-groups seperti
                    Sales/Purchasing, itu APLIKASI TERPISAH dgn shell dashboard
                    sendiri (layouts::report, dark sidebar). Menaruhnya sejajar
                    modul lain di mega-nav lalu expand ke grup "Ringkasan" yg
                    isinya cuma 1 item terasa aneh & duplikat. Diganti 1 tombol
                    langsung di sebelah kanan (seperti tombol lain di topbar
                    ini), tampil HANYA kalau user punya akses modul MGMT.
                --}}
                <template x-for="m in menuTree.filter(x => x.code !== 'SETUP' && x.code !== 'MGMT')" :key="m.id">
                    <button type="button" class="mega-item" :class="{ active: activeMod === m.code }" :title="(m.tooltip || m.label) + (m.statusLabel ? ' — '+m.statusLabel : '')" @click="selectModule(m.code)">
                        <span class="mi"><i :class="m.icon"></i></span> <span x-text="m.shortLabel || m.label"></span>
                        <span class="status-dot" :style="{ background: m.statusColor }"></span>
                    </button>
                </template>
            </nav>
            <div class="tb-spacer"></div>
            @if (collect($this->menuTree)->contains('code', 'MGMT'))
                {{--
                    Pill switcher, meniru persis .rs-switch di sisi Laporan
                    Manajemen (keputusan user 2026-09-13, lihat app.css §mm-switch).
                    Kontainer pakai style INLINE (bukan class .mm-switch murni) --
                    gotcha build: rule CSS kontainer selector-2-class tanpa
                    elemen anak (".shell .mm-switch{...}") selalu hilang dari
                    hasil build @tailwindcss/vite, sudah dicoba ganti nama
                    class & lepas @apply, tetap hilang -- didiagnosis di app.css.
                --}}
                <div class="mm-switch" style="display:flex;align-items:center;border-radius:9px;padding:3px;gap:2px;flex-shrink:0;background:var(--side-hover-soft)">
                    <a class="active"><i class="fa-solid fa-grip"></i> {{ t('Operational') }}</a>
                    <a href="{{ route('report.executive') }}"><i class="fa-solid fa-chart-line"></i> {{ t('Management Report') }}</a>
                </div>
            @endif
            {{--
                Dirapikan (keluhan user 2026-09-14, dua putaran): pertama
                tombol Cari/Palette, Chat, Notifikasi, Toggle Tema dihapus,
                "Sembunyikan top bar" sempat dipertahankan (ikon diganti
                fa-angles-up). Putaran kedua: "hilangkan icon hide menu di
                menu operational" -- tombol itu DIHAPUS juga, topbar kanan
                sekarang cuma switch MGMT (kalau ada aksesnya) + avatar
                user. Fitur di baliknya (openPalette/chatOpen/notifOpen/
                quickToggleTheme/notopbar) TIDAK dihapus dari komponen --
                cuma tombol pemicunya yang dilepas dari topbar.
            --}}
            {{--
                Dropdown avatar diekstrak ke komponen reusable
                app/Livewire/Shell/UserMenu.php (instruksi user 2026-09-14:
                "user menu di management report dan di operational menu
                harus sama") -- SATU implementasi dipakai di sini DAN di
                layouts/report.blade.php, bukan 2 salinan yang bisa
                menyimpang. Alpine ddOpen/configOpen/openTabs/activeTabCode/
                openTab() yang dipakai di dalam komponen tetap datang dari
                x-data root .shell ini (TIDAK diubah) -- Alpine scope
                menembus batas komponen Livewire lewat DOM biasa, jadi
                perilaku persis sama seperti sebelum diekstrak.
            --}}
            <livewire:shell.user-menu />
        </header>
        <div id="topbar-restore"><button type="button" @click="notopbar = false">▾ {{ t('Show top bar') }}</button></div>

        <div class="tabbar">
            <template x-for="t in openTabs" :key="t.code">
                <div class="tab" :class="{ active: activeTabCode === t.code }" @click="activate(t.code)">
                    <i :class="t.icon || 'fa-solid fa-file-lines'"></i>
                    <span class="tlbl" x-text="t.label"></span>
                    <button type="button" class="tx" @click.stop="closeTab(t.code)" title="{{ t('Close') }}">×</button>
                </div>
            </template>
        </div>

        <div class="content">
            <div class="pane" :class="{ active: activeTabCode === null }">
                <div class="home">
                    <div class="hero"></div>
                    <h1>{{ t('Welcome,') }} {{ $this->greetName() }} 👋</h1>
                    <p>{!! t('Pick a module from the <b>mega menu</b> above — its submenu appears in the sidebar. Every form/grid opens as a <b>tab</b> in this frame and stays open even when you switch modules.') !!}</p>
                    <div class="hint">{!! t('Ctrl+K quick search · <kbd>F1</kbd> help · <kbd>⊟</kbd> mini sidebar · <kbd>☰</kbd> hide sidebar · <kbd>︿</kbd> hide top bar.') !!}</div>
                </div>
            </div>
            <template x-for="t in openTabs" :key="'pane-'+t.code">
                <div class="pane" :class="{ active: activeTabCode === t.code, framed: !!t.url }">
                    <template x-if="t.url"><iframe :src="t.url" :title="t.label" :data-tab-code="t.code" x-on:load="try { $el.dataset.home ??= $el.contentWindow.location.pathname + $el.contentWindow.location.search } catch (e) {}"></iframe></template>
                    <template x-if="!t.url"><div x-html="placeholderHtml(t)"></div></template>
                </div>
            </template>
        </div>
    </div>

    <livewire:shell.user-panels />

    {{-- Command palette --}}
    <div class="pal-ov" :class="{ open: paletteOpen }" @click="$event.target === $el && closePalette()">
        <div class="pal">
            <div class="pal-in"><span>🔎</span>
                <input type="text" x-ref="paletteInput" x-model="paletteQuery" placeholder="{{ t('Type a form, grid, or report name…') }}" autocomplete="off"
                       @keydown.down.prevent="paletteMove(1)" @keydown.up.prevent="paletteMove(-1)"
                       @keydown.enter="paletteChoose()" @keydown.escape="closePalette()">
            </div>
            <div class="pal-list">
                <template x-if="paletteResults.length === 0">
                    <div class="pal-empty">{{ t('No matches.') }}</div>
                </template>
                <template x-for="(o, i) in paletteResults" :key="o.code">
                    <div class="pal-row" :class="{ sel: i === paletteSel }" @click="openTab(o.code); closePalette()">
                        <span class="lic"><i :class="o.icon || 'fa-solid fa-file-lines'"></i></span>
                        <div><div class="nm" x-text="o.label"></div><div class="mm" x-text="o.mod"></div></div>
                    </div>
                </template>
            </div>
            <div class="pal-foot"><span><kbd>↑</kbd><kbd>↓</kbd> {{ t('select') }}</span><span><kbd>↵</kbd> {{ t('open') }}</span><span><kbd>esc</kbd> {{ t('close') }}</span></div>
        </div>
    </div>

    <div x-text="toastMsg"
         style="position:fixed;left:50%;bottom:26px;transform:translateX(-50%);background:#2f3a5e;color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;transition:opacity .25s;z-index:130;pointer-events:none"
         :style="{ opacity: toastShow ? 1 : 0 }"></div>
</div>
