<?php

namespace App\Providers;

use App\Support\DataOrigin\DataOriginScope;
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
    }
}
