<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Order value bulan berjalan (Customer Order Confirmed / Closed) terhadap target REVENUE dan bulan lalu; garis kecil = 7 bulan terakhir.
final class RevenueMonth extends Widget
{
    public function key(): string
    {
        return 'revenue_month';
    }

    public function title(): string
    {
        return 'Order Value This Month';
    }

    public function description(): string
    {
        return 'Confirmed Customer Orders of this month against the target and last month.';
    }

    public function type(): string
    {
        return 'kpi';
    }

    public function group(): string
    {
        return 'Sales';
    }

    public function icon(): string
    {
        return 'fa-sack-dollar';
    }

    public function size(): int
    {
        return 3;
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
        return ['omzet bulan ini', 'omset bulan ini', 'nilai order bulan ini', 'penjualan bulan ini', 'order value', 'revenue this month'];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $month = $n->month();
        $last = $n->month(1);
        $target = $n->target('REVENUE', $month);

        return ['source' => 'tm2sdi.co_order + co_order_item · tm2sys.sys_kpi_target', 'data' => [
            'value' => (float) $n->confirmed($month, $n->now->endOfMonth())->sum('value'),
            'previous' => (float) $n->confirmed($last, $last->endOfMonth())->sum('value'),
            'target' => $target, 'format' => 'money', 'spark' => array_values($n->monthlyOrderValue(7)),
            'hint' => $target === null ? 'No target set for this month' : null,
        ]];
    }
}
