<?php

namespace App\Providers;

use App\Models\Sys\SysConstant;
use App\Services\Chat\UserDirectory;
use App\Services\Sales\InquiryStage;
use App\Support\DataOrigin\DataOriginScope;
use App\Support\Filesystem\WindowsSafeFilesystem;
use App\Support\Localization\Dictionary;
use App\Support\V2\Board\WidgetCatalog;
use App\Support\V2\Lookups;
use Chat\Chat;
use Chat\Contracts\Directory;
use Devlib\Lang;
use Devlib\Locale;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use MockupParts\Src;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('Support/Localization/helpers.php');  // t() -- teks tampilan lewat kamus
        // Katalog widget opening screen: satu kali baca per permintaan (dipakai papan dan perintah asisten).
        $this->app->scoped(WidgetCatalog::class);
        // Herd di Windows: kompilasi view paralel bisa gagal rename (Access is denied) -> lihat kelasnya.
        if (PHP_OS_FAMILY === 'Windows') {
            $this->app->singleton('files', fn () => new WindowsSafeFilesystem);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Layar v2 (objek DevLib + mockup-parts): tooltip sumber data & Lookup master yang dipilih di layar.
        Src::$resolver = \App\Support\V2\Src::of(...);
        // x-dl::env-badge texts (empty on the live site): title suffix of the logo (ERP TM - mockup ver 0.20), host / IP under the user name.
        $this->app->bind('devlib.env_title', fn () => \App\Support\EnvBadge::title());
        $this->app->bind('devlib.env_where', fn () => \App\Support\EnvBadge::where());
        // x-dl::net-line under the user name for ALL users (Jo 2026-10-23): client IP -> server IP + connection status, so a complaint can be traced to an area. Replaces the env_where text in the Layer Menu.
        $this->app->bind('devlib.net', fn () => \Devlib\NetworkInfo::of(request()));
        // Identitas aplikasi (nama / tagline / logo) dibaca DevLib dari tabel konstanta saat tampil, bukan ditulis di config.
        $this->app->bind('devlib.brand', fn () => ['name' => \App\Support\Branding\Brand::name(), 'tagline' => \App\Support\Branding\Brand::tagline(), 'logo' => \App\Support\Branding\Brand::logoUrl()]);
        Lookups::register();

        // PPIC: a machine going down or coming back makes the engines prepare a new Draft plan (App\Services\Scheduling\Plan\PlanEvents).
        \App\Models\Master\ProdMachineDowntime::observe(\App\Observers\MachineDowntimeObserver::class);

        // Aturan tampil per bahasa (angka, bulan) + terjemahan label DevLib dari kamus DB (LOCKED user 2026-10-01).
        Locale::languagesUsing(fn () => Dictionary::languages());
        // Bahasa tampilan ikut AKUN (sec_user.lang_code), bukan cookie perangkat; tamu / halaman login memakai cookie.
        Locale::langUsing(fn () => Auth::user()?->lang_code);
        // Kamus aplikasi menang atas kamus bawaan DevLib (user 2026-10-03): null = belum ada -> DevLib memakai DICT-nya.
        Lang::translateUsing(fn (string $en, string $lang, ?string $group = null) => Dictionary::lookup($en, 'ui', $lang, $group));
        Lang::dictUsing(fn (string $lang) => Dictionary::uiMap($lang));
        Blade::directive('t', fn ($e) => "<?php echo e(t($e)); ?>");

        // Setting admin sembunyikan data DUMMY/LEGACY hanya berlaku di request
        // web -- migration, job, dan tinker selalu melihat semua data.
        if (! $this->app->runningInConsole()) {
            DataOriginScope::register();
        }

        $this->applyTimezone();

        // Tanda tahap Inquiry (ProDev selesai / costing published) mengikuti sumbernya di layar mana pun.
        InquiryStage::listen();

        // Paket chat (packages/chat): usernya = sec_user terdaftar, angka setelan dari Settings > Constants kategori Chat
        // (attachment_max_mb -> CHAT_ATTACHMENT_MAX_MB, dst), teks galat lewat kamus.
        $this->app->bind(Directory::class, UserDirectory::class);
        Chat::settingsUsing(fn (string $key, float $default) => SysConstant::number('CHAT_'.strtoupper($key), $default));
        Chat::translateUsing(fn (string $en) => t($en));

        // Struktur DB berubah (migrate / rollback) -> Data Dictionary layar v2 ikut diperbarui.
        Event::listen(MigrationsEnded::class, fn () => Artisan::call('schema:dictionary'));
    }

    // Zona waktu dari Settings -> Constants (APP_TIMEZONE). DB belum siap (install awal) atau nilai
    // bukan nama IANA -> tetap config('app.timezone').
    private function applyTimezone(): void
    {
        try {
            $tz = Cache::remember('app.timezone', 600, fn () => SysConstant::text('APP_TIMEZONE'));
        } catch (\Throwable) {
            return;
        }
        if ($tz && in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            config(['app.timezone' => $tz]);
            date_default_timezone_set($tz);
        }
    }
}
