<?php

/*
 * REMARK
 * Tujuan       : Daftarkan service penjadwalan yang masih dipakai (RateBandResolver, MachineCalendarService, BackwardSchedulingService, WoGenerationService).
 * Sumber hulu  : config/production_scheduling.php
 * Input        : Service tersebut dibuat per permintaan lewat container.
 * Output       : Instance singleton service penjadwalan.
 * Dipakai oleh : Pembuatan Work Order (Planning), tes MaintenanceSchedulingBlockTest.
 * Status       : Aktif. Kelompok PDR (PdrEngine dan kawan-kawan) dihapus 2026-10-18; penjadwal berlaku = Schedule Solver.
 */

namespace App\Providers;

use App\Services\ProDev\SpecEngine;
use App\Services\Scheduling\BackwardSchedulingService;
use App\Services\Scheduling\MachineCalendarService;
use App\Services\Scheduling\RateBandResolver;
use App\Services\Scheduling\WoGenerationService;
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

        $this->app->singleton(BackwardSchedulingService::class, function ($app) {
            return new BackwardSchedulingService(
                $app->make(RateBandResolver::class),
                $app->make(MachineCalendarService::class),
            );
        });

        $this->app->singleton(WoGenerationService::class, function ($app) {
            return new WoGenerationService($app->make(BackwardSchedulingService::class), $app->make(SpecEngine::class));
        });
    }
}
