<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planning\BookingMaintenance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

// Lepas Hold yang habis, beri peringatan booking basi, dan lepas booking yang terlalu lama tanpa Work Order
// (INV_BOOKING_HOLD_WORKDAYS, INV_BOOKING_STALE_WARN_DAYS, INV_BOOKING_STALE_RELEASE_DAYS). Belum dijadwalkan di routes/console.php.
class BookingMaintain extends Command
{
    protected $signature = 'inventory:booking-maintain {--dry-run : List what would happen without changing anything}';

    protected $description = 'Release expired booking holds, warn about stale bookings and release the ones that stayed too long without a work order';

    public function handle(): int
    {
        if (! Schema::connection('tm2inv')->hasColumn('rm_booking', 'stale_warned_on')) {
            $this->warn('rm_booking.stale_warned_on does not exist in this database yet (run migration 2026_10_08_000130). Skipped.');

            return self::SUCCESS;
        }
        $dry = (bool) $this->option('dry-run');
        $res = (new BookingMaintenance('system'))->run($dry);
        foreach (['holds' => 'Hold ended', 'warned' => 'Stale warning', 'released' => 'Stale released'] as $key => $label) {
            $this->info($label.($dry ? ' (dry run)' : '').': '.count($res[$key]).($res[$key] ? ' ('.implode(', ', $res[$key]).')' : ''));
        }

        return self::SUCCESS;
    }
}
