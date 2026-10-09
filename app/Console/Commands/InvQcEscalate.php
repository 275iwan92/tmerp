<?php

namespace App\Console\Commands;

use App\Services\Inventory\QcEscalationService;
use Illuminate\Console\Command;

// Sends the bell notices for Conditional / Quarantine batches that passed INV_QC_ESCALATE_HOURS or INV_QC_DEADLINE_HOURS. NOT scheduled yet:
// run it by hand or add it to routes/console.php (e.g. hourly) when the user agrees.
class InvQcEscalate extends Command
{
    protected $signature = 'inv:qc-escalate {--dry-run : only list the batches, send nothing}';

    protected $description = 'Notify QC and operations managers about Conditional batches without a quality decision in time';

    public function handle(): int
    {
        $rows = QcEscalationService::run((bool) $this->option('dry-run'));
        foreach ($rows as $r) {
            $this->line("{$r['batch_no']}  {$r['state']}  {$r['hours']} h  notices sent: {$r['sent']}");
        }
        $this->info(count($rows).' batch(es) past the time limit.');

        return self::SUCCESS;
    }
}
