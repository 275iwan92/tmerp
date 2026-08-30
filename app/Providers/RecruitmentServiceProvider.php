<?php

namespace App\Providers;

use App\Contracts\Recruitment\PortalBackendAdapterInterface;
use App\Models\Hrr\AppApplication;
use App\Observers\AppApplicationObserver;
use App\Services\Recruitment\FirebaseSyncAdapter;
use App\Services\Recruitment\MariaDbPortalSyncAdapter;
use Illuminate\Support\ServiceProvider;

// Binding adapter Plan A/B (docs/recruitment_tier3.md §1.1) -- resolve dari
// config('recruitment.backend'), SATU baris ganti utk switch Firebase<->MariaDB.
class RecruitmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/recruitment.php', 'recruitment');

        $this->app->bind(PortalBackendAdapterInterface::class, function () {
            return match (config('recruitment.backend')) {
                'firebase' => $this->app->make(FirebaseSyncAdapter::class),
                default => $this->app->make(MariaDbPortalSyncAdapter::class),
            };
        });
    }

    public function boot(): void
    {
        // pushStatusUpdate() generik lintas layar internal -- lihat komentar AppApplicationObserver.
        AppApplication::observe(AppApplicationObserver::class);
    }
}
