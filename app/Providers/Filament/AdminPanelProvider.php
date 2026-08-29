<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
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
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->authGuard('web')
            ->brandName('TELASIH MAKMUR ERP')
            // Samakan primary color dgn --sh-accent shell lama (resources/css/app.css)
            // supaya transisi antar 2 shell tidak terasa seperti 2 aplikasi beda.
            ->colors([
                'primary' => Color::hex('#2f6fed'),
            ])
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
            ]);
    }
}
