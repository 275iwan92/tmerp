<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Dokumen stok bertanggal pada periode itu menurut arah: masuk, keluar, penyesuaian / opname, retur.
final class StockDocuments extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'stock_documents', 'title' => 'Stock Documents', 'type' => 'chart', 'dashboard' => 'INVENTORY', 'module' => 'INV',
            'description' => 'Stock documents of the period by direction: goods in, goods out, adjustment or stock count, return.',
            'icon' => 'fa-right-left', 'phrases' => ['dokumen stok', 'mutasi stok', 'barang masuk keluar', 'stock documents', 'stock movement'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $d = $n->stockDocuments($n->from(), $n->to());

        return ['caption' => raw($n->rangeText()), 'source' => 'tm2inv.v_inv_movement',
            'data' => ['chart' => 'donut', 'format' => 'number', 'height' => '240px', 'labels' => [t('Goods in'), t('Goods out'), t('Adjustment / count'), t('Return')],
                'series' => [['name' => t('Documents'), 'data' => [$d['in'], $d['out'], $d['adjust'], $d['return']]]]]];
    }
}
