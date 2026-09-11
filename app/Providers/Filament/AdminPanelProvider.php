<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Http\Middleware\SetUserLocale;
use App\Support\Branding\Brand;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// FASE MIGRASI FILAMENT (2026-08-30, keputusan user "migrasi total" via
// AskUserQuestion): panel ini jalan BERDAMPINGAN dgn shell custom lama
// (/menu, main-menu.blade.php) selama migrasi bertahap per-modul -- path
// 'admin' SENGAJA dipertahankan (bukan '/') supaya /menu lama tidak mati
// begitu 1-2 modul pertama dipindah. Ganti ke path('') hanya setelah SEMUA
// modul selesai dimigrasikan & shell lama resmi dipensiunkan.
//
// AUTH: TIDAK pakai ->login() bawaan Filament (form email+password) --
// SecUser (tm2sys.sec_user) pakai kolom `login`+`pswd`, bukan email/password
// (lihat SecUser::getAuthPassword()). Kalau dipaksa pakai form default,
// attempt(['email'=>..,'password'=>..]) akan SELALU gagal krn kolom `email`
// bukan identifier login -- ini "crash" nyata yang saya cegah, bukan
// tebakan. Solusinya: guard 'web' dipakai APA ADANYA (provider SecUser
// sudah didaftarkan di config/auth.php, jadi SESSION LOGIN YANG SAMA dgn
// /login custom otomatis dikenali di /admin -- tidak perlu login 2x), dan
// custom Login page (App\Filament\Pages\Auth\Login) HANYA dipakai kalau
// Filament butuh redirect ke halaman login (guest belum sesi) -- field
// disesuaikan ke `login`, bukan `email`.
class AdminPanelProvider extends PanelProvider
{
    // Mitigasi cepat BACKLOG §9.1 (2026-09-10, "klik menu sidebar tidak
    // merespons saat modal form terbuka"): root cause adalah backdrop modal
    // ManageRecords --simple menelan klik pertama ke sidebar. Solusi jangka
    // panjang (auto-save draft / pindah ke halaman Create-Edit terpisah)
    // masih menunggu diskusi user -- ini cuma matikan tutup-modal-diam-diam
    // via klik-di-luar, supaya user tidak kehilangan input tanpa sadar
    // selagi menunggu. Method ini toggle STATIS global Filament (bukan
    // fluent per-Action -- versi Filament ini tidak punya
    // Action::closedByClickingAway()), jadi berlaku ke SEMUA modal panel
    // /admin, bukan cuma EmpEmployeeResource/OrgPositionResource -- sejalan
    // dgn niat asli backlog "dan Resource --simple lain yg menyusul".
    // Reversibel: hapus baris ini utk kembali ke bawaan Filament.
    public function boot(): void
    {
        \Filament\Support\View\Components\ModalComponent::closedByClickingAway(false);
    }

    public function panel(Panel $panel): Panel
    {
        // PENTING (ditemukan & diperbaiki 2026-09-02): panel() TIDAK jalan
        // per-request meski terlihat begitu -- PanelProvider::register() cuma
        // daftar closure ke Filament::registerPanel(), yg dieksekusi via
        // app()->resolving(PanelRegistry::class, ...) SEKALI SAJA (pertama
        // kali PanelRegistry di-resolve dari container, jauh sebelum
        // middleware auth/session jalan utk request itu). Auth::user() di
        // SINI SELALU null -- personalisasi per-user (accent/dark-mode)
        // TIDAK BOLEH dihitung di method ini. Sudah dipindah ke render hook
        // filament.partials.theme-matrix (HEAD_END, lihat di bawah) yang
        // TERBUKTI jalan per-request (diverifikasi Livewire::test() lintas
        // 5 mode). Di sini cukup default statis (blue/light, sama spt semua
        // user sebelum fitur personalisasi ada).
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->authGuard('web')
            // Brand (nama/logo) dibaca dari tm2sys.sys_constant lewat Brand::
            // (docs/configurationstandard.md), diedit di layar Configuration
            // shell lama -- SATU sumber, bukan hardcode terpisah lagi per panel.
            ->brandName(Brand::name())
            ->when(Brand::logoUrl(), fn (Panel $p, string $url) => $p->brandLogo($url))
            ->favicon(Brand::logoUrl() ?? asset('favicon.ico'))
            // Accent warna default (blue, sama dgn --sh-accent shell lama) --
            // ini BUKAN accent final yang dilihat user login (itu di-override
            // per-user oleh render hook theme-matrix di bawah, oklch(--primary-*)
            // var yang sama dihasilkan Color::generatePalette() ulang di sana).
            // Tetap dibutuhkan sbg fallback utk guest/halaman login (belum ada
            // user utk dipersonalisasi).
            ->colors([
                'primary' => Color::hex(\App\Support\Branding\DashboardAccent::hex('blue')),
            ])
            // ->darkMode() (BUKAN darkMode(false)) WAJIB tetap enabled --
            // kalau false, Filament maksa localStorage.theme='light' tiap load
            // (lihat layout/base.blade.php), yang akan menimpa override render
            // hook. Default mode di sini juga cuma fallback guest (light) --
            // mode final per-user diatur render hook (set localStorage + toggle
            // class .dark langsung, override urutan render Filament sendiri).
            ->darkMode()
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('filament.partials.theme-matrix')->render())
            ->profile(EditProfile::class, isSimple: false)
            ->sidebarCollapsibleOnDesktop()
            // Override menu atas (2026-08-30): ->topNavigation() adalah fitur
            // BAWAAN Filament yang TERNYATA sudah persis meniru pola shell lama
            // (top mega-nav pilih modul + sub-sidebar kiri isi grup/item), tanpa
            // perlu render hook/Blade override custom -- dikonfirmasi via HTTP
            // kernel asli: topbar selalu berisi "Dashboard | Human Resources"
            // (level Cluster=Module) di SEMUA halaman, sementara sidebar kiri
            // baru menampilkan isi grup (Organization/Employee/Recruitment) +
            // item (Master Posisi/Master Employee/Pipeline Board) saat SEDANG
            // di dalam cluster tsb -- persis Module->Group->Item lama, cuma
            // modul-switcher-nya pindah dari <nav class="mega"> custom ke
            // topbar Filament bawaan. Form/Grid (Resource form()/table()) TIDAK
            // disentuh sama sekali oleh perubahan ini.
            ->topNavigation()
            // Filament secara default mengurutkan grup navigasi berdasar urutan
            // DITEMUKAN saat discovery (bukan sort_order kita) -- didaftarkan
            // eksplisit di sini SUPAYA urutan grup persis sys_app_menu (module
            // HRM, lihat migration 2026_08_29_000004): Organization(5) <
            // Employee(10) < Payroll(15) < Time Attendance(20) < Recruitment(50).
            // Tambahkan grup modul lain di sini saat migrasinya menyusul.
            ->navigationGroups([
                NavigationGroup::make('Organization'),
                NavigationGroup::make('Employee'),
                NavigationGroup::make('Payroll'),
                NavigationGroup::make('Time Attendance'),
                NavigationGroup::make('Recruitment'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                SetUserLocale::class,
            ]);
    }
}
