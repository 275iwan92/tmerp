<?php

namespace App\Console\Commands;

use App\Services\Maintenance\MaintenanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

// Harian 06:00: plan preventive yang jatuh tempo -> MWO Draft (kepala maintenance atur jadwal & Confirm).
class GenerateMaintenancePm extends Command
{
    protected $signature = 'maintenance:generate-pm';

    protected $description = 'Create draft Maintenance Work Orders for preventive plans that are due';

    public function handle(): int
    {
        if (! Schema::connection('tm2prd')->hasTable('mnt_plan')) {
            $this->warn('mnt_plan belum ada di database ini (migration Maintenance belum dijalankan) - dilewati.');

            return self::SUCCESS;
        }
        $made = (new MaintenanceService('system'))->generateDue();
        $this->info(count($made).' WO preventive dibuat.');

        return self::SUCCESS;
    }
}
