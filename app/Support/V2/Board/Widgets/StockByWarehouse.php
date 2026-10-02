<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Nilai stok on hand yang bisa dipakai per gudang (bahan baku, setengah jadi, barang jadi digabung).
final class StockByWarehouse extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'stock_by_warehouse', 'title' => 'Stock Value by Warehouse', 'type' => 'chart', 'dashboard' => 'INVENTORY', 'module' => 'INV', 'size' => 6,
            'description' => 'Value of usable stock on hand per warehouse.',
            'icon' => 'fa-warehouse', 'tone' => 'teal', 'phrases' => ['stok per gudang', 'nilai stok gudang', 'stock by warehouse', 'warehouse stock'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $by = collect($n->stockByWarehouse())->filter(fn (float $v) => $v > 0)->take(8);

        return ['caption' => 'As of today', 'source' => 'tm2inv stock on hand (RM / SFG / FG) · tm2mst.wh_warehouse',
            'data' => ['chart' => 'hbar', 'format' => 'money', 'height' => '240px', 'legend' => false, 'labels' => $by->keys()->all(),
                'series' => [['name' => t('Stock value'), 'data' => $by->values()->all(), 'tone' => 'teal']]], 'open' => $this->open('INV_RAW_RM', 'Stock Balance', 'fa-solid fa-boxes-stacked')];
    }
}
