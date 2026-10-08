<?php

declare(strict_types=1);

namespace App\Services\Scheduling\Plan;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Events that must make the engines prepare a new Draft (a machine goes down or comes back, a Work Order is created). One door, so other modules
 * never call the planner directly: this skips environments without the scheduling tables and never lets a planning problem break the caller.
 */
final class PlanEvents
{
    public static function replan(string $reason): void
    {
        try {
            if (! Schema::connection('tm2prd')->hasTable('prd_plan_run')) {
                return;
            }
            $run = app(AutoPlanRunner::class)->run('EVENT', 'ENGINE');
            Log::info('PPIC re-plan: '.$reason, ['run' => $run->run_id, 'planned' => $run->operations_planned, 'exceptions' => $run->exceptions_found]);
        } catch (Throwable $e) {
            Log::warning('PPIC re-plan failed: '.$reason.' — '.$e->getMessage());
        }
    }
}
