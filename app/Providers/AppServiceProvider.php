<?php

namespace App\Providers;

use App\Models\Sys\SysConstant;
use App\Support\DataOrigin\DataOriginScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Setting admin sembunyikan data DUMMY/LEGACY hanya berlaku di request
        // web -- migration, job, dan tinker selalu melihat semua data.
        if (! $this->app->runningInConsole()) {
            DataOriginScope::register();
        }

        $this->applyTimezone();
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
