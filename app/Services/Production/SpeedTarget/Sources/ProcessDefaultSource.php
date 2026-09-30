<?php

namespace App\Services\Production\SpeedTarget\Sources;

use App\Services\Production\SpeedTarget\MachineSpeedContext;
use App\Services\Production\SpeedTarget\SpeedPeers;
use App\Services\Production\SpeedTarget\SpeedSuggestion;

// (6) Default kecepatan proses utama (Master Proses) — jaring terakhir.
final class ProcessDefaultSource implements SpeedSource
{
    public function suggest(MachineSpeedContext $m, SpeedPeers $peers): ?SpeedSuggestion
    {
        if (! $m->processDefaultSpeed) {
            return null;
        }
        $process = $m->primaryProcess()['process_name'] ?? '?';

        return new SpeedSuggestion($m->processDefaultSpeed, $m->primaryUom(), 'PROCESS_DEFAULT', 'LOW', 0, "default speed of process '{$process}'");
    }
}
