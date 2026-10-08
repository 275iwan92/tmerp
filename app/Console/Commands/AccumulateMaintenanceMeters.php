<?php

namespace App\Console\Commands;

use App\Services\Maintenance\MeterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

// Tiap pagi: tambahkan output produksi (jam jalan, impresi) sejak pembacaan terakhir ke meter mesin yang sudah punya pembacaan awal.
class AccumulateMaintenanceMeters extends Command
{
    protected $signature = 'maintenance:accumulate-meters';

    protected $description = 'Add production hours or output to machine meters since the last reading';

    public function handle(): int
    {
        if (! Schema::connection('tm2mnt')->hasTable('mnt_meter_reading')) {
            $this->warn('mnt_meter_reading does not exist in this database yet. Skipped.');

            return self::SUCCESS;
        }
        $added = (new MeterService('system'))->accumulateFromProduction();
        $this->info(count($added).' meter(s) updated.');
        foreach ($added as $code => $delta) {
            $this->line("  $code +$delta");
        }

        return self::SUCCESS;
    }
}
