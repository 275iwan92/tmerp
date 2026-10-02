<?php

namespace App\Support\V2\Grid\Inv;

// Stock Recapitulation: SATU baris per item (semua kelas stok, semua bin dijumlah) untuk satu periode -- saldo awal, masuk,
// keluar, penyesuaian, saldo akhir, dan nilainya. Item yang punya saldo awal tetapi tidak bergerak tetap tampil.
class StockRecapReport extends AllStockReport
{
    public function slug(): string
    {
        return 'inv-rpt-recap';
    }

    public function title(): string
    {
        return 'Stock Recapitulation';
    }

    public function icon(): string
    {
        return 'fa-solid fa-table-list';
    }

    public function guide(): ?string
    {
        return 'One row per item for the period: opening, in, out, adjustment and closing balance, with the closing value. Transfers between bins are not counted. Value per movement and Turnover are in Columns; the movements behind a row are in Stock Card.';
    }

    public function groupBy(): ?string
    {
        return 'class';
    }

    public function rows(): array
    {
        $items = $this->ledger()->items();
        $this->loadMeta($items);
        $rows = [];
        foreach ($items as $key => $i) {
            $meta = $this->meta($i['class'], $i['item_id']);
            $rows[$i['class'].' '.$meta['code'].' '.$key] = ['id' => $key, 'item' => $meta['name'], 'item_code' => $meta['code'], 'class' => self::CLASSES[$i['class']], 'category' => $meta['group'],
                'opening' => $i['opening_qty'], 'in' => $i['in'], 'out' => $i['out'], 'adj' => $i['adj'], 'closing' => $i['closing_qty'], 'unit' => $i['uom'],
                'avg_price' => $i['closing_qty'] != 0.0 ? $i['closing_value'] / $i['closing_qty'] : null, 'opening_value' => $i['opening_value'], 'in_value' => $i['in_value'],
                'out_value' => $i['out_value'], 'adj_value' => $i['adj_value'], 'closing_value' => $i['closing_value'], 'activity' => $i['moves'] > 0 ? 'With movement' : 'No movement',
                'stock' => $i['closing_qty'] < 0 ? 'Negative' : ($i['closing_qty'] == 0.0 ? 'Empty' : 'In stock'), 'turnover' => self::turnover($i)];
        }
        ksort($rows);

        return array_values($rows);
    }

    public function columns(): array
    {
        return [
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'item_code', 'avatar' => false],
            ['key' => 'class', 'label' => 'Class'],
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'opening', 'label' => 'Opening', 'type' => 'number', 'sum' => true],
            ['key' => 'in', 'label' => 'In', 'type' => 'number', 'sum' => true],
            ['key' => 'out', 'label' => 'Out', 'type' => 'number', 'sum' => true],
            ['key' => 'adj', 'label' => 'Adjustment', 'type' => 'number', 'sum' => true],
            ['key' => 'closing', 'label' => 'Closing', 'type' => 'number', 'sum' => true],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'avg_price', 'label' => 'Average Price', 'type' => 'money', 'visible' => false],
            ['key' => 'opening_value', 'label' => 'Opening Value', 'type' => 'money', 'sum' => true, 'visible' => false],
            ['key' => 'in_value', 'label' => 'In Value', 'type' => 'money', 'sum' => true, 'visible' => false],
            ['key' => 'out_value', 'label' => 'Out Value', 'type' => 'money', 'sum' => true, 'visible' => false],
            ['key' => 'adj_value', 'label' => 'Adjustment Value', 'type' => 'money', 'sum' => true, 'visible' => false],
            ['key' => 'closing_value', 'label' => 'Closing Value', 'type' => 'money', 'sum' => true],
            ['key' => 'turnover', 'label' => 'Turnover', 'type' => 'number', 'visible' => false],
            ['key' => 'activity', 'label' => 'Activity', 'type' => 'badge', 'tones' => ['With movement' => 'success', 'No movement' => 'neutral']],
            ['key' => 'stock', 'label' => 'Stock', 'type' => 'badge', 'tones' => ['In stock' => 'success', 'Empty' => 'neutral', 'Negative' => 'danger'], 'visible' => false],
        ];
    }

    // Perputaran stok periode itu: nilai keluar dibagi rata-rata nilai awal dan akhir. null = tidak ada stok untuk dibandingkan.
    private static function turnover(array $i): ?float
    {
        $average = ($i['opening_value'] + $i['closing_value']) / 2;

        return $average > 0 ? round($i['out_value'] / $average, 2) : null;
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'With movement', 'where' => ['activity' => 'With movement'], 'tone' => 'success'],
            ['label' => 'No movement', 'where' => ['activity' => 'No movement'], 'tone' => 'neutral'],
            ['label' => 'Negative', 'where' => ['stock' => 'Negative'], 'tone' => 'danger'],
        ];
    }

    public function filters(): array
    {
        return ['class' => 'Class', 'category' => 'Category', 'activity' => 'Activity', 'stock' => 'Stock'];
    }

    public function groups(): array
    {
        return ['class' => 'Class', 'category' => 'Category'];
    }

    public function printSummary(): array
    {
        return [['label' => t('Closing Value'), 'sum' => 'closing_value']];
    }
}
