<?php

namespace App\Services\Production\SpeedTarget\Sources;

use App\Services\Production\SpeedTarget\MachineSpeedContext;
use App\Services\Production\SpeedTarget\SpeedPeers;
use App\Services\Production\SpeedTarget\SpeedSuggestion;

interface SpeedSource
{
    public function suggest(MachineSpeedContext $m, SpeedPeers $peers): ?SpeedSuggestion;
}
