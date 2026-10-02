<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;
use Carbon\CarbonImmutable;

// Order value 12 bulan terakhir (batang) terhadap target REVENUE per bulan (garis). Bulan tanpa target = garis di nol.
final class RevenueTrend extends Widget
{
    public function key(): string
    {
        return 'revenue_trend';
    }

    public function title(): string
    {
        return 'Order Value: Target vs Actual';
    }

    public function description(): string
    {
        return 'Confirmed order value of the last 12 months as bars, the monthly target as a line.';
    }

    public function type(): string
    {
        return 'chart';
    }

    public function group(): string
    {
        return 'Sales';
    }

    public function icon(): string
    {
        return 'fa-chart-column';
    }

    public function size(): int
    {
        return 8;
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
        return ['grafik omzet', 'grafik penjualan', 'tren omzet', 'target vs pencapaian', 'omzet 12 bulan', 'revenue trend', 'sales chart', 'target vs actual'];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $actual = $n->monthlyOrderValue(12);
        $months = array_map(fn (string $ym) => CarbonImmutable::parse($ym.'-01'), array_keys($actual));
        $target = array_map(fn (CarbonImmutable $m) => (float) ($n->target('REVENUE', $m) ?? 0), $months);
        $series = [['name' => t('Actual'), 'data' => array_values($actual), 'type' => 'bar', 'tone' => 'accent']];
        if (array_sum($target) > 0) {
            $series[] = ['name' => t('Target'), 'data' => $target, 'type' => 'line', 'tone' => 'danger'];
        }

        return ['caption' => 'Last 12 months · bars = actual, line = target', 'source' => 'tm2sdi.co_order + co_order_item · tm2sys.sys_kpi_target',
            'data' => ['chart' => 'combo', 'format' => 'money', 'height' => '320px', 'labels' => array_map(fn (CarbonImmutable $m) => self::monthName($m), $months), 'series' => $series]];
    }
}
