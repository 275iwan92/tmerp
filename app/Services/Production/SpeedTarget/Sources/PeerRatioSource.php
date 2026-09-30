<?php

namespace App\Services\Production\SpeedTarget\Sources;

use App\Services\Production\SpeedTarget\MachineSpeedContext;
use App\Services\Production\SpeedTarget\SpeedPeers;
use App\Services\Production\SpeedTarget\SpeedSuggestion;

// (4b) Mesin sejenis (jenis + penggerak sama) yang punya riwayat DAN kecepatan maks: median rasio aktual/maks x
// kecepatan maks mesin ini — efisiensi nyata pabrik, bukan angka brosur.
final class PeerRatioSource implements SpeedSource
{
    public function suggest(MachineSpeedContext $m, SpeedPeers $peers): ?SpeedSuggestion
    {
        if (! $m->maxSpeed) {
            return null;
        }
        $drive = $m->drive ?? 'MANUAL';
        $same = $peers->withHistory($m, fn (MachineSpeedContext $c) => $c->type === $m->type && ($c->drive ?? 'MANUAL') === $drive && $c->maxSpeed > 0);
        if ($same === []) {
            return null;
        }
        $ratios = array_map(fn ($p) => $p['suggestion']->speed / $p['context']->maxSpeed, $same);
        sort($ratios);
        $mid = intdiv(count($ratios), 2);
        $ratio = count($ratios) % 2 ? $ratios[$mid] : ($ratios[$mid - 1] + $ratios[$mid]) / 2;
        $list = implode(', ', array_map(fn ($p) => sprintf('%s %.2f', $p['context']->code, $p['suggestion']->speed / $p['context']->maxSpeed), $same));

        return new SpeedSuggestion(
            round($m->maxSpeed * $ratio), $m->primaryUom(), 'PEER_RATIO', count($same) >= 3 ? 'MEDIUM' : 'LOW', count($same),
            sprintf('max %s/h x median actual/max ratio %.2f of %s %s machines (%s)', number_format($m->maxSpeed), $ratio, $drive, $m->type, $list),
        );
    }
}
