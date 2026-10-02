<?php

namespace App\Support\V2\Grid\Inv;

// Stock Summary: ringkasan NILAI semua stok untuk satu periode, satu baris per kelas stok x kategori item -- jumlah item,
// nilai awal, nilai masuk, nilai keluar, nilai penyesuaian, nilai akhir, dan porsinya dari seluruh nilai stok.
// Jumlah (qty) tidak dijumlah di sini karena satuan tiap item berbeda; rincian per item ada di Stock Recapitulation.
class StockSummaryReport extends AllStockReport
{
    public function slug(): string
    {
        return 'inv-rpt-summary';
    }

    public function title(): string
    {
        return 'Stock Summary';
    }

    public function icon(): string
    {
        return 'fa-solid fa-layer-group';
    }

    public function search(): string
    {
        return 'Search class, category...';
    }

    public function guide(): ?string
    {
        return 'Value of all stock for the period, one row per stock class and item category. Opening + In - Out + Adjustment = Closing. Quantities are not added up here because items have different units; the items are in Stock Recapitulation.';
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
        foreach ($items as $i) {
            $category = $this->meta($i['class'], $i['item_id'])['group'];
            $key = array_search($i['class'], array_keys(self::CLASSES), true).'|'.$category;
            $rows[$key] ??= ['id' => $i['class'].'|'.$category, 'class' => self::CLASSES[$i['class']], 'category' => $category, 'items' => 0, 'items_moved' => 0,
                'opening_value' => 0.0, 'in_value' => 0.0, 'out_value' => 0.0, 'adj_value' => 0.0, 'closing_value' => 0.0];
            $rows[$key]['items']++;
            $rows[$key]['items_moved'] += $i['moves'] > 0 ? 1 : 0;
            foreach (['opening_value', 'in_value', 'out_value', 'adj_value', 'closing_value'] as $k) {
                $rows[$key][$k] += $i[$k];
            }
        }
        ksort($rows);
        $total = array_sum(array_column($rows, 'closing_value'));

        return array_values(array_map(fn (array $r) => $r + ['change' => $r['closing_value'] - $r['opening_value'],
            'share' => $total > 0 ? round($r['closing_value'] / $total * 100, 1) : 0.0], $rows));
    }

    public function columns(): array
    {
        return [
            ['key' => 'class', 'label' => 'Class'],
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'items', 'label' => 'Items', 'type' => 'number', 'sum' => true],
            ['key' => 'items_moved', 'label' => 'Items Moved', 'type' => 'number', 'sum' => true, 'visible' => false],
            ['key' => 'opening_value', 'label' => 'Opening Value', 'type' => 'money', 'sum' => true],
            ['key' => 'in_value', 'label' => 'In Value', 'type' => 'money', 'sum' => true],
            ['key' => 'out_value', 'label' => 'Out Value', 'type' => 'money', 'sum' => true],
            ['key' => 'adj_value', 'label' => 'Adjustment Value', 'type' => 'money', 'sum' => true],
            ['key' => 'closing_value', 'label' => 'Closing Value', 'type' => 'money', 'sum' => true],
            ['key' => 'change', 'label' => 'Change', 'type' => 'money', 'sum' => true, 'visible' => false],
            ['key' => 'share', 'label' => 'Share (%)', 'type' => 'number', 'sum' => true],
        ];
    }

    public function filters(): array
    {
        return ['class' => 'Class', 'category' => 'Category'];
    }

    public function groups(): array
    {
        return ['class' => 'Class'];
    }

    public function printSummary(): array
    {
        return [['label' => t('Opening Value'), 'sum' => 'opening_value'], ['label' => t('In Value'), 'sum' => 'in_value'], ['label' => t('Out Value'), 'sum' => 'out_value'],
            ['label' => t('Adjustment Value'), 'sum' => 'adj_value', 'signed' => true], ['label' => t('Closing Value'), 'sum' => 'closing_value']];
    }

    public function printSignatures(): array
    {
        return [['Prepared By', 'Warehouse'], ['Checked By', 'Accounting'], ['Approved By', 'Head of Warehouse']];
    }
}
