<?php

namespace App\Services\Scheduling;

/**
 * Value object hasil Composite Priority Score — readonly supaya tak diam-diam
 * diubah di tempat lain. Dipisah file sendiri (PSR-4).
 * Sumber: production_scheduling_tier1.md §5A.4.
 */
final class PriorityScore
{
    public function __construct(
        public readonly int $layer,
        public readonly float|int $tiebreak,
        public readonly ?int $resourceFitPenalty = null,
        public readonly ?int $fifoTiebreak = null,
        public readonly string $label = '',
    ) {}
}
