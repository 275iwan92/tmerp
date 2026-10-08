<?php

declare(strict_types=1);

namespace App\Services\Scheduling\Plan;

/** Output of ScheduleSolver: placements, exceptions that need a person, priority facts per Work Order and machine load. */
final class PlanResult
{
    /** @var array<int, Placement> op id => placement */
    public array $placements = [];

    /** @var list<array{wo: int, op: ?int, kind: string, message: string, data: array}> */
    public array $exceptions = [];

    /** @var array<int, array{layer: int, cr: ?float, slack_h: ?float}> */
    public array $priority = [];

    /** @var array<int, array{busy: int, span: int, pct: float}> */
    public array $loads = [];

    public function place(Placement $p): void
    {
        $this->placements[$p->opId] = $p;
    }

    public function exception(int $woId, ?int $opId, string $kind, string $message, array $data = []): void
    {
        $this->exceptions[] = ['wo' => $woId, 'op' => $opId, 'kind' => $kind, 'message' => $message, 'data' => $data];
    }

    public function exceptionsOf(string $kind): array
    {
        return array_values(array_filter($this->exceptions, fn ($e) => $e['kind'] === $kind));
    }
}
