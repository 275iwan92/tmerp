<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Order value per periode (batang) terhadap target REVENUE-nya (garis): 14 hari, 12 minggu, 12 bulan, 8 kuartal, 6 semester
// atau 5 tahun terakhir, menurut pilihan periode papan. Harian dan mingguan tanpa garis target (target diisi per bulan).
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
        return 'Confirmed order value per period as bars and its target as a line: days, weeks, months, quarters or years.';
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
        return ['grafik omzet', 'grafik penjualan', 'tren omzet', 'target vs pencapaian', 'grafik nilai order', 'revenue trend', 'sales chart', 'target vs actual'];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $trend = $n->trend();
        $target = array_map(fn (array $p) => (float) ($p['target'] ?? 0), $trend);
        $series = [['name' => t('Actual'), 'data' => array_column($trend, 'value'), 'type' => 'bar', 'tone' => 'accent']];
        if (array_sum($target) > 0) {
            $series[] = ['name' => t('Target'), 'data' => $target, 'type' => 'line', 'tone' => 'danger'];
        }

        return ['caption' => 'Bars = actual, line = target', 'source' => 'tm2sdi.co_order + co_order_item · tm2sys.sys_kpi_target',
            'data' => ['chart' => 'combo', 'format' => 'money', 'height' => '320px', 'labels' => array_column($trend, 'label'), 'series' => $series]];
    }
}
