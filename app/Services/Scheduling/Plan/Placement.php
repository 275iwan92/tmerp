<?php

declare(strict_types=1);

namespace App\Services\Scheduling\Plan;

use Carbon\CarbonImmutable;

/** Where and when the solver put one operation. `setup` = SetupTimeEngine result (base, change, tool, parts). */
final class Placement
{
    /**
     * @param  array<string, mixed>  $setup
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly int $opId,
        public readonly int $woId,
        public readonly int $machineId,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $finish,
        public readonly int $setupMinutes,
        public readonly int $runMinutes,
        public readonly array $setup,
        public readonly array $warnings = [],
        public readonly int $rank = 99,
    ) {}
}
