<?php

namespace App\Console\Commands;

use App\Services\Pur\ShortageAutoDetector;
use Illuminate\Console\Command;

// Compares the material issued to each finished work order with its good output plus standard waste (from production data) and makes a DRAFT
// shortage request when the gap is above the PO tolerance. Pairs it cannot judge are listed with the reason; those stay manual entries.
class InvDetectShortage extends Command
{
    protected $signature = 'inv:detect-shortage {--wo= : only this work order id} {--dry-run : list only, create nothing} {--by=system (shortage detection) : name recorded on the requests}';

    protected $description = 'Detect short deliveries from production output and create Draft shortage requests';

    public function handle(): int
    {
        $rows = ShortageAutoDetector::scan($this->option('wo') ? (int) $this->option('wo') : null, (bool) $this->option('dry-run'), (string) $this->option('by'));
        $count = [];
        foreach ($rows as $r) {
            $count[$r['state']] = ($count[$r['state']] ?? 0) + 1;
            if (in_array($r['state'], ['CREATED', 'WOULD_CREATE'], true)) {
                $this->line("{$r['state']}  {$r['wo_no']}  {$r['material']}  suspected {$r['candidate']}  {$r['request_no']}");
            } elseif ($this->getOutput()->isVerbose()) {
                $this->line("{$r['state']}  {$r['wo_no']}  {$r['material']}  {$r['detail']}");
            }
        }
        $this->info(count($rows).' pair(s) checked: '.($count ? implode(', ', array_map(fn ($s, $n) => "{$n} {$s}", array_keys($count), $count)) : 'none').'. Use -v to see why pairs were skipped.');

        return self::SUCCESS;
    }
}
