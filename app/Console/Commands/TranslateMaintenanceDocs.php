<?php

namespace App\Console\Commands;

use App\Services\Maintenance\DocumentTranslator;
use Illuminate\Console\Command;

// Masukkan terjemahan Indonesia SOP / WI Maintenance (resources/data/maintenance/sop_wi_id.json) ke kamus, konteks maintenance_doc.
class TranslateMaintenanceDocs extends Command
{
    protected $signature = 'maintenance:translate-docs {--force : Overwrite translations that are already filled}';

    protected $description = 'Load the Indonesian translations of the Maintenance SOP and work instructions into the dictionary';

    public function handle(): int
    {
        $n = DocumentTranslator::import((bool) $this->option('force'));
        $this->info("$n translations written (context ".DocumentTranslator::CONTEXT.').');

        return self::SUCCESS;
    }
}
