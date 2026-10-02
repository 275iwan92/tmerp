<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Nilai stok on hand yang bisa dipakai, per kelas: bahan baku, barang setengah jadi, barang jadi.
// Referensi: inventory value by location / class (Business Central).
final class StockValue extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'stock_value', 'title' => 'Stock Value', 'type' => 'chart', 'group' => 'Inventory', 'module' => 'INV',
            'description' => 'Value of usable stock on hand: raw material, semi finished goods and finished goods.',
            'icon' => 'fa-boxes-stacked', 'tone' => 'teal', 'phrases' => ['nilai stok', 'nilai persediaan', 'stok', 'stock value', 'inventory value'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $v = $n->stockValue();

        return ['caption' => raw($this->say('Total :v', ['v' => $this->money(array_sum($v))])), 'source' => 'tm2inv stock on hand (RM / SFG / FG)',
            'open' => $this->open('INV_RAW_RM', 'Stock Balance', 'fa-solid fa-boxes-stacked'),
            'data' => ['chart' => 'donut', 'format' => 'money', 'height' => '240px', 'labels' => [t('Raw material'), t('Semi finished goods'), t('Finished goods')],
                'series' => [['name' => t('Stock value'), 'data' => [(float) ($v['RM'] ?? 0), (float) ($v['SFG'] ?? 0), (float) ($v['FG'] ?? 0)]]]]];
    }
}
