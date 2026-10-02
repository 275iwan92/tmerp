<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Target perusahaan periode berjalan per bidang (Setup > KPI Targets, Sales Person = Company) dan pencapaiannya sampai hari ini.
// Baris Order Value merinci Draft di samping Confirmed.
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
        return 'Company targets of the period and what has been reached so far.';
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
        $from = $n->from();
        $end = $n->to();
        $ship = $n->onTime($from, $end);
        $orders = $n->orders($from, $end);
        $split = Numbers::split($orders);
        $rows = [
            ['label' => 'Order Value', 'sub' => raw($this->splitText($split, true)), 'actual' => $split['confirmed'], 'target' => $n->periodTarget('REVENUE'),
                'format' => 'money', 'icon' => 'fa-sack-dollar', 'tone' => 'accent'],
            ['label' => 'New Customer Orders', 'sub' => raw($this->splitText($split, false)), 'actual' => $orders->count(), 'target' => $n->periodTarget('NEW_ORDERS'),
                'icon' => 'fa-bag-shopping', 'tone' => 'teal'],
            ['label' => 'New Customers', 'sub' => 'first order', 'actual' => $n->newCustomers($from, $end), 'target' => $n->periodTarget('NEW_CUSTOMERS'),
                'icon' => 'fa-user-plus', 'tone' => 'violet'],
            ['label' => 'On-time Delivery', 'sub' => 'against the promised date', 'actual' => $ship['shipped'] ? $ship['on_time'] / $ship['shipped'] * 100 : 0,
                'target' => $ship['shipped'] ? $n->periodTarget('ONTIME_DELIVERY') : null, 'format' => 'percent', 'icon' => 'fa-truck-fast', 'tone' => 'success'],
            ['label' => 'Overdue Receivables', 'sub' => 'as of today, lower is better', 'actual' => $n->overdue(), 'target' => $n->target('OVERDUE_RECEIVABLES', $n->month()),
                'format' => 'money', 'good_when' => 'down', 'icon' => 'fa-file-invoice-dollar', 'tone' => 'danger'],
        ];

        return ['caption' => raw($n->rangeText()), 'source' => 'tm2sys.sys_kpi_target · tm2sdi.co_order · tm2sdi.do_delivery · tm2acc.v1_fin_ar_aging',
            'data' => ['rows' => $rows], 'open' => $this->open('SET_KPI_TARGETS', 'KPI Targets', 'fa-solid fa-bullseye')];
    }
}
