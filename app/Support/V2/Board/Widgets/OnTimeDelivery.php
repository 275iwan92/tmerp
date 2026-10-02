<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Persen Delivery Order customer yang berangkat tepat janji pada periode berjalan, terhadap target ONTIME_DELIVERY dan periode lalu.
final class OnTimeDelivery extends Widget
{
    public function key(): string
    {
        return 'ontime_delivery';
    }

    public function title(): string
    {
        return 'On-time Delivery';
    }

    public function description(): string
    {
        return 'Share of customer deliveries that left on or before the promised date in the period.';
    }

    public function type(): string
    {
        return 'kpi';
    }

    public function group(): string
    {
        return 'Delivery';
    }

    public function icon(): string
    {
        return 'fa-truck-fast';
    }

    public function tone(): string
    {
        return 'success';
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
        return ['pengiriman tepat waktu', 'kirim tepat waktu', 'ketepatan kirim', 'on time delivery', 'on-time delivery', 'otd'];
    }

    public function dashboard(): ?string
    {
        return 'SALES';
    }

    public function build(Numbers $n, SecUser $user): array
    {
        [$prevFrom, $prevTo] = $n->range(1);
        $now = $n->onTime($n->from(), $n->to());
        $before = $n->onTime($prevFrom, $prevTo);
        $pct = fn (array $x) => $x['shipped'] ? $x['on_time'] / $x['shipped'] * 100 : null;
        $base = ['source' => 'tm2sdi.do_delivery + do_line + co_order_delivery · tm2sys.sys_kpi_target'];
        if (! $now['shipped']) {
            return $base + ['data' => ['value' => '—', 'format' => 'percent', 'hint' => 'No delivery has left in this period']];
        }

        return $base + ['data' => ['value' => $pct($now), 'previous' => $pct($before), 'target' => $n->periodTarget('ONTIME_DELIVERY'), 'format' => 'percent',
            'note' => raw(str_replace([':a', ':b'], [(string) $now['on_time'], (string) $now['shipped']], t(':a of :b deliveries on time')))]];
    }
}
