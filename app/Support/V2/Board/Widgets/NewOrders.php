<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Jumlah dokumen Customer Order periode berjalan (semua status kecuali Cancelled) terhadap target NEW_ORDERS dan periode lalu;
// baris bawah merinci berapa yang Confirmed dan berapa yang masih Draft.
final class NewOrders extends Widget
{
    public function key(): string
    {
        return 'new_orders';
    }

    public function title(): string
    {
        return 'New Customer Orders';
    }

    public function description(): string
    {
        return 'Number of Customer Orders entered in the period, without the cancelled ones: Confirmed and Draft.';
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
        return 'fa-bag-shopping';
    }

    public function tone(): string
    {
        return 'teal';
    }

    public function size(): int
    {
        return 3;
    }

    public function module(): ?string
    {
        return 'SALES';
    }

    public function phrases(): array
    {
        return ['order baru', 'customer order baru', 'jumlah order', 'co baru', 'new orders', 'new customer orders'];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        [$prevFrom, $prevTo] = $n->range(1);
        $orders = $n->orders($n->from(), $n->to());
        $target = $n->periodTarget('NEW_ORDERS');

        return ['source' => 'tm2sdi.co_order · tm2sys.sys_kpi_target', 'data' => [
            'value' => $orders->count(), 'previous' => (float) $n->orders($prevFrom, $prevTo)->count(), 'target' => $target, 'format' => 'number',
            'hint' => $target === null ? $this->noTarget($n) : null, 'note' => raw($this->splitText(Numbers::split($orders), false)),
        ]];
    }
}
