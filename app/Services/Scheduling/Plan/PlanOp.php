<?php

declare(strict_types=1);

namespace App\Services\Scheduling\Plan;

use Carbon\CarbonImmutable;

/**
 * One operation as the solver sees it (plain data, no Eloquent).
 *
 * candidates: machine_id => ['rank' => int, 'rate' => ?float units per hour (of the operation unit), 'setup_base' => ?int minutes]
 * facts:      setup facts, see SetupTimeEngine
 * fixed:      already confirmed and locked placement ['machine' => int, 'start' => CarbonImmutable, 'finish' => CarbonImmutable]
 * kind:       MACHINE (finite capacity) | MANUAL (crew job, no machine capacity: QC, packing) | SUBCON (outsourced, lead days)
 * gate:       READY | WAIT | BLOCK (material / tooling / artwork combined); WAIT uses minStart as the arrival time
 */
final class PlanOp
{
    /**
     * @param  array<int, array{rank: int, rate: ?float, setup_base: ?int}>  $candidates
     * @param  array<string, mixed>  $facts
     * @param  array{machine: int, start: CarbonImmutable, finish: CarbonImmutable}|null  $fixed
     */
    public function __construct(
        public readonly int $id,
        public readonly int $woId,
        public readonly int $seq,
        public readonly string $processName,
        public readonly string $stage,
        public readonly float $qtyIn,
        public readonly array $candidates,
        public readonly array $facts = [],
        public readonly int $waitAfterMinutes = 0,
        public readonly ?CarbonImmutable $minStart = null,
        public readonly string $gate = 'READY',
        public readonly ?string $gateNote = null,
        public readonly ?array $fixed = null,
        public readonly string $kind = 'MACHINE',
        public readonly int $leadDays = 0,
    ) {}
}
