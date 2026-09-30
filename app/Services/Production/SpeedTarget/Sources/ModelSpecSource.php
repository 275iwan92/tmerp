<?php

namespace App\Services\Production\SpeedTarget\Sources;

use App\Services\Production\SpeedTarget\MachineSpeedContext;
use App\Services\Production\SpeedTarget\SpeedPeers;
use App\Services\Production\SpeedTarget\SpeedSettings;
use App\Services\Production\SpeedTarget\SpeedSuggestion;

// (5) Kecepatan maks (mesin, atau model katalog) x faktor efisiensi penggerak dari Settings > Constants.
final class ModelSpecSource implements SpeedSource
{
    public function __construct(private readonly SpeedSettings $settings) {}

    public function suggest(MachineSpeedContext $m, SpeedPeers $peers): ?SpeedSuggestion
    {
        if (! $m->maxSpeed) {
            return null;
        }
        $drive = $m->drive ?? 'MANUAL';
        $eff = $this->settings->efficiency($drive);

        return new SpeedSuggestion(
            round($m->maxSpeed * $eff), $m->primaryUom(), 'MODEL_SPEC', 'LOW', 0,
            sprintf('max %s/h%s x %s efficiency %.2f (constant SPEED_EFF_%s)', number_format($m->maxSpeed), $m->modelCode ? " ({$m->modelCode})" : '', $drive, $eff, $drive),
        );
    }
}
