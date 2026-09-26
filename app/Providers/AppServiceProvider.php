<?php

namespace App\Providers;

use App\Models\Sys\SysConstant;
use App\Support\DataOrigin\DataOriginScope;
use App\Support\V2\Lookups;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Artisan;
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
        // Herd di Windows: kompilasi view paralel bisa gagal rename (Access is denied) -> lihat kelasnya.
        if (PHP_OS_FAMILY === 'Windows') {
            $this->app->singleton('files', fn () => new \App\Support\Filesystem\WindowsSafeFilesystem);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Layar v2 (objek DevLib + mockup-parts): tooltip sumber data & Lookup master yang dipilih di layar.
        Src::$resolver = \App\Support\V2\Src::of(...);
        Lookups::register();

        // Setting admin sembunyikan data DUMMY/LEGACY hanya berlaku di request
        // web -- migration, job, dan tinker selalu melihat semua data.
        if (! $this->app->runningInConsole()) {
            DataOriginScope::register();
        }

        $this->applyTimezone();

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
