<?php

namespace App\Console\Commands;

use App\Services\Inventory\CountTaskService;
use Illuminate\Console\Command;

// Makes COUNT tasks (and locks the bins) for every storage area whose count interval (wh_zone.count_every_days) has passed.
// Run it by hand or from the scheduler; --dry-run only lists the areas that are due.
class InvCountDue extends Command
{
    protected $signature = 'inv:count-due {--dry-run : only list the due areas} {--zone= : only this area code} {--by=system (count schedule) : name recorded on the tasks}';

    protected $description = 'Create warehouse count tasks for the areas that are due and lock their bins';

    public function handle(CountTaskService $counts): int
    {
        $due = $counts->dueZones()->when($this->option('zone'), fn ($zones, $code) => $zones->where('zone_code', $code));
        foreach ($due as $zone) {
            $this->line("Due: {$zone->zone_code} {$zone->zone_name} (every {$zone->count_every_days} days)");
        }
        if ($this->option('dry-run') || $due->isEmpty()) {
            $this->info($due->isEmpty() ? 'No area is due.' : $due->count().' area(s) due.');

            return self::SUCCESS;
        }
        $made = $due->sum(fn ($zone) => $counts->start($zone, (string) $this->option('by')));
        $this->info("{$made} count task(s) created for {$due->count()} area(s).");

        return self::SUCCESS;
    }
}
