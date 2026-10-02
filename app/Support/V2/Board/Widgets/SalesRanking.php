<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Order value bulan berjalan per sales person terhadap target REVENUE pribadinya; urut dari persen tertinggi.
// Order tanpa sales person dijumlah di baris sendiri supaya totalnya sama dengan Order Value This Month.
final class SalesRanking extends Widget
{
    public function key(): string
    {
        return 'sales_ranking';
    }

    public function title(): string
    {
        return 'Target Achievement per Sales';
    }

    public function description(): string
    {
        return 'Confirmed order value of each sales person this month against the personal target.';
    }

    public function type(): string
    {
        return 'ranking';
    }

    public function group(): string
    {
        return 'Sales';
    }

    public function icon(): string
    {
        return 'fa-ranking-star';
    }

    public function size(): int
    {
        return 6;
    }

    public function module(): ?string
    {
        return 'SALES';
    }

    public function needsSalesPrice(): bool
    {
        return true;
    }

    public function phrases(): array
    {
        return ['pencapaian sales', 'target per sales', 'peringkat sales', 'ranking sales', 'sales ranking', 'target per sales person'];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $month = $n->month();
        $by = $n->confirmed($month, $n->now->endOfMonth())->groupBy(fn ($o) => (int) $o->sales_person_id);
        $rows = [];
        foreach ($n->salesPeople() as $id => $name) {
            $target = $n->target('REVENUE', $month, (int) $id);
            $actual = (float) ($by->get((int) $id)?->sum('value') ?? 0);
            if ($target !== null || $actual > 0) {
                $rows[] = ['name' => $name, 'sub' => '', 'target' => $target, 'actual' => $actual];
            }
        }
        if ($by->has(0)) {
            $rows[] = ['name' => t('No sales person'), 'sub' => '', 'target' => null, 'actual' => (float) $by->get(0)->sum('value')];
        }
        usort($rows, fn ($a, $b) => [$b['target'] ? $b['actual'] / $b['target'] : -1, $b['actual']] <=> [$a['target'] ? $a['actual'] / $a['target'] : -1, $a['actual']]);

        return ['caption' => 'This month, highest first', 'source' => 'tm2sdi.co_order · tm2mst.sales_person · tm2sys.sys_kpi_target',
            'data' => ['name_label' => 'Sales Person', 'format' => 'money', 'rows' => $rows]];
    }
}
