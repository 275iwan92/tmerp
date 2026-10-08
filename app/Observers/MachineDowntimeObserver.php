<?php

namespace App\Observers;

use App\Models\Master\ProdMachineDowntime;
use App\Services\Scheduling\Plan\PlanEvents;

// A machine going down, or coming back (Breakdown Maintenance Order Done closes the downtime), makes the PPIC engines prepare a new Draft plan
// around it. The observer sits on the model so Maintenance never needs to know about scheduling.
class MachineDowntimeObserver
{
    public function created(ProdMachineDowntime $downtime): void
    {
        if ($downtime->end_at === null) {
            PlanEvents::replan('machine '.$downtime->machine_id.' down');
        }
    }

    public function updated(ProdMachineDowntime $downtime): void
    {
        if ($downtime->wasChanged('end_at') && $downtime->end_at !== null) {
            PlanEvents::replan('machine '.$downtime->machine_id.' back in service');
        }
    }
}
