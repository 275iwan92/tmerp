<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Target dan hasil produksi per periode: batang = hasil (qty baik dicatat), garis = target (qty operasi terjadwal selesai).
final class ProductionTrend extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'production_trend', 'title' => 'Production per Period: Target vs Result', 'type' => 'chart', 'dashboard' => 'PRODUCTION', 'module' => 'PROD', 'size' => 8,
            'description' => 'Production result per period as bars and the planned quantity as a line.',
            'icon' => 'fa-chart-column', 'phrases' => ['grafik produksi', 'tren produksi', 'produksi per periode', 'production trend', 'production chart'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $buckets = $n->buckets();
        $p = array_map(fn (array $b) => $n->production($b['from'], $b['to']), $buckets);

        return ['caption' => 'Bars = result, line = target', 'source' => 'tm2prd.prd_wo_operation · prd_wo_operation_output',
            'data' => ['chart' => 'combo', 'format' => 'number', 'height' => '300px', 'labels' => array_column($buckets, 'label'),
                'series' => [['name' => t('Result'), 'data' => array_column($p, 'result'), 'type' => 'bar', 'tone' => 'accent'],
                    ['name' => t('Target'), 'data' => array_column($p, 'target'), 'type' => 'line', 'tone' => 'danger']]],
            'open' => $this->open('PROD_OUTPUT', 'Production Output', 'fa-solid fa-industry')];
    }
}
