<?php

declare(strict_types=1);

namespace App\Services\Scheduling\Plan;

use Carbon\CarbonImmutable;

/** A Work Order as the solver sees it: its operations in sequence plus the facts that decide its priority. */
final class PlanWo
{
    /** @param  list<PlanOp>  $ops */
    public function __construct(
        public readonly int $id,
        public readonly string $no,
        public readonly ?CarbonImmutable $due,
        public readonly bool $hot,
        public readonly int $tier,
        public readonly int $ageDays,
        public readonly array $ops,
        public readonly string $label = '',
        public readonly bool $moveUp = false,
    ) {}
}
