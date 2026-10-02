<?php

namespace App\Support\V2\Board\Widgets;

use App\Assistant\Metrics\DeadstockValue as DeadstockMetric;
use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Nilai stok yang tidak bergerak lebih dari ASSISTANT_DEADSTOCK_MONTHS bulan (hitungan yang sama dengan jawaban asisten "deadstock").
final class DeadstockValue extends Widget
{
    public function key(): string
    {
        return 'deadstock_value';
    }

    public function title(): string
    {
        return 'Deadstock Value';
    }

    public function description(): string
    {
        return 'Value of stock that has not moved for months: raw material, semi finished and finished goods together.';
    }

    public function type(): string
    {
        return 'kpi';
    }

    public function group(): string
    {
        return 'Inventory';
    }

    public function icon(): string
    {
        return 'fa-box-archive';
    }

    public function tone(): string
    {
        return 'warn';
    }

    public function size(): int
    {
        return 3;
    }

    public function module(): ?string
    {
        return 'INV';
    }

    public function phrases(): array
    {
        return ['deadstock', 'dead stock', 'stok mati', 'stok tidak bergerak', 'nilai deadstock', 'deadstock value'];
    }

    public function dashboard(): ?string
    {
        return 'INVENTORY';
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $totals = collect(DeadstockMetric::totals());
        $hint = str_replace([':n', ':m'], [(string) $totals->sum('items'), (string) DeadstockMetric::months()], t(':n items not moved for more than :m months'));

        return ['source' => 'tm2inv stock on hand (RM / SFG / FG)', 'data' => ['value' => (float) $totals->sum('value'), 'format' => 'money', 'good_when' => 'down', 'hint' => raw($hint)]];
    }
}
