<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Order value periode berjalan. Angka besar = Customer Order Confirmed / Closed (yang dibandingkan dengan target dan periode
// lalu); baris bawah merinci Draft dan yang menunggu approval (user 2026-10-02: "dijelaskan saja draft sekian dan confirmed
// sekian"). Garis kecil = 7 periode terakhir.
final class OrderValue extends Widget
{
    public function key(): string
    {
        return 'order_value';
    }

    public function title(): string
    {
        return 'Order Value';
    }

    public function description(): string
    {
        return 'Confirmed Customer Orders of the period against the target and the period before, with the Draft amount beside it.';
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
        return ['omzet', 'omset', 'nilai order', 'penjualan bulan ini', 'omzet bulan ini', 'order value', 'revenue'];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        [$prevFrom, $prevTo] = $n->range(1);
        $now = Numbers::split($n->orders($n->from(), $n->to()));
        $target = $n->periodTarget('REVENUE');

        return ['source' => 'tm2sdi.co_order + co_order_item · tm2sys.sys_kpi_target', 'data' => [
            'value' => $now['confirmed'], 'previous' => (float) $n->confirmed($prevFrom, $prevTo)->sum('value'), 'target' => $target, 'format' => 'money',
            'spark' => array_column($n->trend(7), 'value'), 'hint' => $target === null ? $this->noTarget($n) : null, 'note' => raw($this->splitText($now, true)),
        ]];
    }
}
