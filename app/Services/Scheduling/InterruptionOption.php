<?php

namespace App\Services\Scheduling;

/** Value object satu opsi penyisipan interupsi — Tier1 §5A.2. */
final class InterruptionOption
{
    public function __construct(
        public readonly string $label,
        public readonly ?int $displacedOperationId,
        public readonly float $impactHours,
        public readonly bool $isSafe,
        public readonly ?float $estimatedCost = null,
        public readonly bool $requiresSubcontractPo = false,
    ) {}

    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'displaced_operation_id' => $this->displacedOperationId,
            'impact_hours' => $this->impactHours,
            'is_safe' => $this->isSafe,
            'estimated_cost' => $this->estimatedCost,
            'requires_subcontract_po' => $this->requiresSubcontractPo,
        ];
    }
}
