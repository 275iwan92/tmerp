<?php

namespace App\Providers;

use App\Models\Inventory\RmBooking;
use App\Models\Prd\PrdWo;
use App\Models\Sdi\CoOrder;
use App\Services\Planning\MaterialPlanningEngine;
use App\Support\V2\Data\MaterialPlanning;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

// Pemicu Material Planning (docs/materialbooking_tier1.md §2): menempel di event model supaya layar CO / CPDEL (v1 maupun v2)
// tidak perlu diubah. Gagal menghitung booking TIDAK boleh menggagalkan Confirm CO / Post to Production -> dibungkus try.
class PlanningServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // CO menjadi CONFIRMED -> usulan booking dibuat; CO batal / kembali Draft -> booking dilepas.
        CoOrder::updated(function (CoOrder $co) {
            if (! $co->wasChanged(['doc_status', 'stat_cancel'])) {
                return;
            }
            $this->guarded(function () use ($co) {
                $engine = new MaterialPlanningEngine(Auth::user()?->login ?? 'system');
                if ($co->stat_cancel || $co->doc_status !== 'CONFIRMED') {
                    $engine->release((int) $co->getKey(), 'Customer Order '.($co->stat_cancel ? 'cancelled' : 'back to '.$co->doc_status));
                } else {
                    $engine->persist((int) $co->getKey());
                }
            });
        });

        // WO lahir (Post to Production): booking CO itu dihitung ulang bila belum dikunci, dan mencatat WO pertamanya.
        PrdWo::created(function (PrdWo $wo) {
            if (! $wo->source_co_id) {
                return;
            }
            $this->guarded(function () use ($wo) {
                $b = (new MaterialPlanningEngine(Auth::user()?->login ?? 'system'))->persist((int) $wo->source_co_id);
                if ($b && ! $b->wo_id) {
                    RmBooking::whereKey($b->getKey())->update(['wo_id' => $wo->getKey()]);
                }
            });
        });
    }

    private function guarded(callable $fn): void
    {
        try {
            // Lingkungan yang belum menjalankan migration Material Planning (tm2* sebelum disetujui) dilewati.
            if (! Schema::connection('tm2inv')->hasColumn('rm_booking', 'co_id')) {
                return;
            }
            $fn();
            MaterialPlanning::changed();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
