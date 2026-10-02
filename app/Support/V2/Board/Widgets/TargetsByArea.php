<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Target perusahaan bulan berjalan per bidang (Setup > KPI Targets, sales person kosong) dan pencapaiannya sampai hari ini.
final class TargetsByArea extends Widget
{
    public function key(): string
    {
        return 'targets_by_area';
    }

    public function title(): string
    {
        return 'Targets by Area';
    }

    public function description(): string
    {
        return 'Company targets of this month and what has been reached so far.';
    }

    public function type(): string
    {
        return 'progress';
    }

    public function group(): string
    {
        return 'Management';
    }

    public function icon(): string
    {
        return 'fa-bullseye';
    }

    public function module(): ?string
    {
        return 'MGMT';
    }

    public function needsSalesPrice(): bool
    {
        return true;
    }

    public function phrases(): array
    {
        return ['target per bidang', 'target perusahaan', 'pencapaian target', 'targets by area', 'company targets'];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $month = $n->month();
        $end = $n->now->endOfMonth();
        $ship = $n->onTime($month, $end);
        $rows = [
            ['label' => 'Order Value', 'sub' => 'confirmed Customer Orders', 'actual' => (float) $n->confirmed($month, $end)->sum('value'), 'target' => $n->target('REVENUE', $month),
                'format' => 'money', 'icon' => 'fa-sack-dollar', 'tone' => 'accent'],
            ['label' => 'New Customer Orders', 'sub' => 'documents entered', 'actual' => $n->orders($month, $end)->count(), 'target' => $n->target('NEW_ORDERS', $month),
                'icon' => 'fa-bag-shopping', 'tone' => 'teal'],
            ['label' => 'New Customers', 'sub' => 'first order', 'actual' => $n->newCustomers($month, $end), 'target' => $n->target('NEW_CUSTOMERS', $month),
                'icon' => 'fa-user-plus', 'tone' => 'violet'],
            ['label' => 'On-time Delivery', 'sub' => 'against the promised date', 'actual' => $ship['shipped'] ? $ship['on_time'] / $ship['shipped'] * 100 : 0,
                'target' => $ship['shipped'] ? $n->target('ONTIME_DELIVERY', $month) : null, 'format' => 'percent', 'icon' => 'fa-truck-fast', 'tone' => 'success'],
            ['label' => 'Overdue Receivables', 'sub' => 'lower is better', 'actual' => $n->overdue(), 'target' => $n->target('OVERDUE_RECEIVABLES', $month),
                'format' => 'money', 'good_when' => 'down', 'icon' => 'fa-file-invoice-dollar', 'tone' => 'danger'],
        ];

        return ['caption' => 'This month against target', 'source' => 'tm2sys.sys_kpi_target · tm2sdi.co_order · tm2sdi.do_delivery · tm2acc.v1_fin_ar_aging',
            'data' => ['rows' => $rows], 'open' => $this->open('SET_KPI_TARGETS', 'KPI Targets', 'fa-solid fa-bullseye')];
    }
}
