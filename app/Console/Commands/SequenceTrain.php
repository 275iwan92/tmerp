<?php

namespace App\Console\Commands;

use App\Services\Scheduling\Sequence\SequenceTrainer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

// Job order learning: trains the pairwise ranking model from the officer's APPLY arrangements. Idempotent (no new data = no new version).
class SequenceTrain extends Command
{
    protected $signature = 'sequence:train';

    protected $description = 'Train the job order suggestion model from the officer arrangements recorded at APPLY (needs 30 pairs and a better hold-out accuracy than the engine to become ACTIVE)';

    public function handle(): int
    {
        if (! Schema::connection('tm2prd')->hasTable('prd_sequence_model')) {
            $this->warn('prd_sequence_model is not migrated here.');

            return self::SUCCESS;
        }
        $m = SequenceTrainer::run();
        if (! $m) {
            $this->info('Nothing new to learn from.');

            return self::SUCCESS;
        }
        $this->info(sprintf('Version %d %s: %d pairs from %d arrangements, hold-out model %.1f%% vs engine %.1f%%.', $m->version, $m->status, $m->pairs_count, $m->arrangements_count,
            (float) $m->holdout_accuracy_model * 100, (float) $m->holdout_accuracy_engine * 100));

        return self::SUCCESS;
    }
}
