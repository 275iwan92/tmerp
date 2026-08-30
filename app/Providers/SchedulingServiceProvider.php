<?php

namespace App\Providers;

use App\Services\Scheduling\BackwardSchedulingService;
use App\Services\Scheduling\CompositePriorityScorer;
use App\Services\Scheduling\CrewAvailabilityChecker;
use App\Services\Scheduling\InterruptionOptionGenerator;
use App\Services\Scheduling\MachineCalendarService;
use App\Services\Scheduling\MpsEngine;
use App\Services\Scheduling\PdrEngine;
use App\Services\Scheduling\RateBandResolver;
use App\Services\Scheduling\SchedulingRuleEngine;
use Illuminate\Support\ServiceProvider;

/**
 * Semua di-bind sebagai singleton per-request — service ini stateless
 * (tak menyimpan state antar-request), aman di-share.
 */
class SchedulingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/production_scheduling.php', 'production_scheduling');

        $this->app->singleton(RateBandResolver::class, function () {
            return new RateBandResolver(
                config('production_scheduling.rate_band_min_datapoint', 5)
            );
        });

        $this->app->singleton(MachineCalendarService::class);
        $this->app->singleton(CrewAvailabilityChecker::class);

        $this->app->singleton(BackwardSchedulingService::class, function ($app) {
            return new BackwardSchedulingService(
                $app->make(RateBandResolver::class),
                $app->make(MachineCalendarService::class),
            );
        });

        $this->app->singleton(CompositePriorityScorer::class, function ($app) {
            return new CompositePriorityScorer($app->make(CrewAvailabilityChecker::class));
        });

        $this->app->singleton(SchedulingRuleEngine::class);

        $this->app->singleton(PdrEngine::class, function ($app) {
            return new PdrEngine(
                $app->make(BackwardSchedulingService::class),
                $app->make(CompositePriorityScorer::class),
                $app->make(SchedulingRuleEngine::class),
                $app->make(CrewAvailabilityChecker::class),
            );
        });

        $this->app->singleton(InterruptionOptionGenerator::class, function ($app) {
            return new InterruptionOptionGenerator($app->make(PdrEngine::class));
        });

        $this->app->singleton(MpsEngine::class, function ($app) {
            return new MpsEngine($app->make(PdrEngine::class));
        });
    }
}
