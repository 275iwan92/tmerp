<?php

declare(strict_types=1);

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\StockOnhand;
use App\Services\Inventory\ItemAverageCost;
use App\Support\V2\Src;

// Valuation Check (Average per item, INV_AVG_SCOPE = ITEM): bukti ke user bahwa nilai persediaan per item tidak bergeser. Per item:
// qty milik Company, Average item sekarang, nilai item menurut rantai Average item, jumlah nilai semua bin (kartu stok), dan selisihnya
// (harus 0). Selisih <> 0 berarti kartu stok dan rantai tidak sepakat -> jalankan inv:revalue-per-item --check.
// Hanya dibaca. Biaya: satu rantai per item (ItemAverageCost::recalculate tanpa menulis).
class ValuationCheckReport extends AllStockReport
{
    private const TOLERANCE = 0.005;

    public function slug(): string
    {
        return 'inv-rpt-valuation-check';
    }

    public function title(): string
    {
        return 'Valuation Check';
    }

    public function icon(): string
    {
        return 'fa-solid fa-scale-balanced';
    }

    public function search(): string
    {
        return 'Search item code, item name...';
    }

    public function guide(): ?string
    {
        return 'Proves the Average per item value: for every item, the stock card value of all bins must equal the item value from the single Average item. Difference must be 0. Works when the valuation scope is Item; with scope Bin it only lists the bin totals.';
    }

    protected function defaultPeriod(): ?array
    {
        return null;
    }

    public function groupBy(): ?string
    {
        return 'class';
    }

    public function rowTone(): ?string
    {
        return 'tone';
    }

    protected function sourceText(): string
    {
        return Src::of('tm2inv.v_rm_onhand', 'tm2inv.v_sfg_onhand', 'tm2inv.v_fg_onhand', 'tm2inv.inv_item_avg_open', 'tm2inv.rm_stockcard', 'tm2inv.sfg_stockcard', 'tm2inv.fg_stockcard', 'tm2mst.mat_raw', 'tm2mst.prod_part', 'tm2mst.prod_product');
    }

    public function rows(): array
    {
        $scopeItem = ItemAverageCost::enabled();
        $found = [];
        foreach (array_keys(self::CLASSES) as $class) {
            $key = StockOnhand::ITEM_KEYS[$class];
            $part = $class === 'FG' ? 'IFNULL(parts_id, 0)' : '0';
            foreach (StockOnhand::of($class)->selectRaw("{$key} AS item_id, {$part} AS part_id, SUM(value_onhand) AS bin_value")->groupByRaw($class === 'FG' ? "{$key}, {$part}" : $key)->get() as $r) {
                $found[] = ['class' => $class, 'item_id' => (int) $r->item_id, 'part_id' => (int) $r->part_id ?: null, 'bin_value' => (float) $r->bin_value];
            }
        }
        $this->loadMeta($found);

        $rows = [];
        foreach ($found as $f) {
            $chain = $scopeItem ? ItemAverageCost::recalculate($f['class'], $f['item_id'], $f['part_id'], null, false) : null;
            if (! $chain && abs($f['bin_value']) < self::TOLERANCE) {
                continue;
            }
            $meta = $this->meta($f['class'], $f['item_id']);
            $diff = $chain ? round($f['bin_value'] - $chain['value'], 4) : null;
            $rows[] = ['id' => $f['class'].'|'.$f['item_id'].'|'.(int) $f['part_id'], 'class' => self::CLASSES[$f['class']], 'item' => $meta['name'], 'item_code' => $meta['code'],
                'qty' => $chain['qty'] ?? null, 'average' => $chain['avg'] ?? null, 'item_value' => $chain['value'] ?? null, 'bin_value' => $f['bin_value'], 'difference' => $diff,
                'result' => $chain === null ? 'Scope Bin' : (abs($diff) <= self::TOLERANCE ? 'OK' : 'Check'),
                'tone' => $chain !== null && abs($diff) > self::TOLERANCE ? 'danger' : null];
        }
        usort($rows, fn ($a, $b) => [abs((float) $b['difference']), $a['class'], $a['item']] <=> [abs((float) $a['difference']), $b['class'], $b['item']]);

        return $rows;
    }

    public function columns(): array
    {
        return [
            ['key' => 'class', 'label' => 'Class'],
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'item_code', 'avatar' => false],
            ['key' => 'qty', 'label' => 'Qty (Company)', 'type' => 'number', 'decimals' => 2],
            ['key' => 'average', 'label' => 'Average Item', 'type' => 'money'],
            ['key' => 'item_value', 'label' => 'Item Value', 'type' => 'money', 'sum' => true],
            ['key' => 'bin_value', 'label' => 'Bin Value Total', 'type' => 'money', 'sum' => true],
            ['key' => 'difference', 'label' => 'Difference', 'type' => 'money', 'sum' => true],
            ['key' => 'result', 'label' => 'Result', 'type' => 'badge', 'tones' => ['OK' => 'success', 'Check' => 'danger', 'Scope Bin' => 'neutral']],
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Check', 'where' => ['result' => 'Check'], 'tone' => 'danger'],
            ['label' => 'OK', 'where' => ['result' => 'OK'], 'tone' => 'success'],
        ];
    }

    public function filters(): array
    {
        return ['class' => 'Class', 'result' => 'Result'];
    }

    public function groups(): array
    {
        return ['class' => 'Class', 'result' => 'Result'];
    }

    public function printSummary(): array
    {
        return [['label' => t('Item Value'), 'sum' => 'item_value'], ['label' => t('Bin Value Total'), 'sum' => 'bin_value'], ['label' => t('Difference'), 'sum' => 'difference', 'signed' => true]];
    }

    public function printSignatures(): array
    {
        return [['Prepared By', 'Warehouse'], ['Checked By', 'Accounting']];
    }
}
