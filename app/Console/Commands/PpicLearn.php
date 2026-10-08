<?php

namespace App\Console\Commands;

use App\Services\Scheduling\Learning\LearningEngine;
use Illuminate\Console\Command;

// Learning engine: corrects the Assumed changeover minutes from real setup times of the operator timer. Every change is logged and can be reverted.
class PpicLearn extends Command
{
    protected $signature = 'ppic:learn';

    protected $description = 'Adjust assumed changeover values from finished jobs (limits in Settings > Constants, PPIC_LEARN_*)';

    public function handle(LearningEngine $engine): int
    {
        $applied = $engine->run('ENGINE');
        foreach ($applied as $a) {
            $this->line(sprintf('machine %d %s: %d -> %d min (%d jobs)', $a['machine'], $a['kind'], $a['old'], $a['new'], $a['samples']));
        }
        $this->info(count($applied).' value(s) adjusted.');

        return self::SUCCESS;
    }
}
