<?php

namespace App\Console\Commands;

use App\Services\Scheduling\Plan\AutoPlanRunner;
use Illuminate\Console\Command;

// Scheduled trigger of the PPIC autopilot: the engines prepare a Draft plan (never the real schedule); the scheduling officer confirms it.
// DAILY = nightly run, SHIFT = at the start of each shift. New Work Orders and other events start the same runner from the screens.
class PpicAutoPlan extends Command
{
    protected $signature = 'ppic:auto-plan {--trigger=DAILY : DAILY | SHIFT | EVENT | MANUAL}';

    protected $description = 'Run the PPIC auto-planner: prepare a Draft plan for the officer to confirm';

    public function handle(AutoPlanRunner $runner): int
    {
        $trigger = strtoupper((string) $this->option('trigger'));
        $run = $runner->run(in_array($trigger, ['DAILY', 'SHIFT', 'EVENT', 'MANUAL'], true) ? $trigger : 'DAILY', 'ENGINE');
        $this->info("Draft plan {$run->run_id}: {$run->operations_planned} operations planned, {$run->exceptions_found} need attention.");

        return self::SUCCESS;
    }
}
