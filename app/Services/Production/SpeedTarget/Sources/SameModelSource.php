<?php

namespace App\Services\Production\SpeedTarget\Sources;

use App\Services\Production\SpeedTarget\MachineSpeedContext;
use App\Services\Production\SpeedTarget\SpeedPeers;
use App\Services\Production\SpeedTarget\SpeedSuggestion;

// (4a) Mesin lain dengan model katalog yang sama dan sudah punya riwayat — angkanya dipinjam apa adanya.
final class SameModelSource implements SpeedSource
{
    public function suggest(MachineSpeedContext $m, SpeedPeers $peers): ?SpeedSuggestion
    {
        if (! $m->modelId) {
            return null;
        }
        $twins = $peers->withHistory($m, fn (MachineSpeedContext $c) => $c->modelId === $m->modelId);
        if ($twins === []) {
            return null;
        }
        $speeds = array_map(fn ($t) => $t['suggestion']->speed, $twins);
        $codes = implode(', ', array_map(fn ($t) => $t['context']->code, $twins));

        return new SpeedSuggestion(
            round(array_sum($speeds) / count($speeds)), $twins[0]['suggestion']->uom, 'SAME_MODEL', 'MEDIUM',
            array_sum(array_map(fn ($t) => $t['suggestion']->sampleSize, $twins)),
            "same model {$m->modelCode} as {$codes} (their own history)",
        );
    }
}
