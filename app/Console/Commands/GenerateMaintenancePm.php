<?php

namespace App\Console\Commands;

use App\Services\Maintenance\PlanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

// Tiap pagi: Preventive Plan yang jatuh tempo (tanggal atau meter) -> Preventive Maintenance Order Draft.
// Kepala maintenance mengatur jadwal dan teknisi lalu Confirm; sistem hanya membuat draf.
class GenerateMaintenancePm extends Command
{
    protected $signature = 'maintenance:generate-pm';

    protected $description = 'Create Draft Preventive Maintenance Orders for the Preventive Plans that are due';

    public function handle(): int
    {
        if (! Schema::connection('tm2mnt')->hasTable('mnt_plan')) {
            $this->warn('mnt_plan does not exist in this database yet (the Maintenance migration has not run). Skipped.');

            return self::SUCCESS;
        }
        $made = (new PlanService('system'))->generateDue();
        $this->info(count($made).' Draft order(s) created.');
        foreach ($made as $o) {
            $this->line('  #'.$o->id_order.' '.$o->description);
        }

        return self::SUCCESS;
    }
}
