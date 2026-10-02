<?php

namespace App\Support\V2\Grid\Inv;

use App\Services\Inventory\StockLedger;

// Stock Card semua kelas stok untuk satu periode: per item = baris saldo awal, lalu tiap pergerakan urut tanggal dengan
// SALDO BERJALAN ITEM (semua bin dijumlah). Stock Transfer tampil di kolom Transfer dan tidak mengubah saldo item.
// Kartu satu item: isi kotak Item (kode atau nama) lalu Show; cetakannya = kartu stok item itu.
class StockCardAllReport extends AllStockReport
{
    // Batas baris di layar: di atas ini periode dipersempit atau item diisi (grid memuat semua baris sekaligus).
    private const MAX_ROWS = 20000;

    private bool $truncated = false;

    public function slug(): string
    {
        return 'inv-rpt-card';
    }

    public function title(): string
    {
        return 'Stock Card';
    }

    public function icon(): string
    {
        return 'fa-solid fa-clipboard-list';
    }

    public function params(): array
    {
        return ['item' => 'Item'];
    }

    public function search(): string
    {
        return 'Search item, doc no, reference...';
    }

    public function guide(): ?string
    {
        return $this->truncated ? 'Only the first 20,000 rows are shown. Shorten the period or fill in Item, then click Show.'
            : 'Stock card of the period, grouped by item: opening balance, then every movement by date with the running balance in all bins. Transfers have their own column and do not change the balance. For one item, type its code or name in Item and click Show.';
    }

    public function groupBy(): ?string
    {
        return 'item';
    }

    public function rows(): array
    {
        $ledger = $this->ledger();
        $items = $ledger->items();
        $this->loadMeta($items);
        $find = mb_strtolower($this->param('item'));
        $moved = [];
        foreach ($ledger->movements() as $m) {
            $moved[StockLedger::itemKey($m['class'], $m['item_id'])][] = $m;
        }

        // Tanpa saringan: item yang bergerak di periode ini. Dengan saringan: semua item yang cocok, juga yang hanya punya saldo awal.
        $cards = [];
        foreach ($items as $key => $i) {
            $meta = $this->meta($i['class'], $i['item_id']);
            $match = $find === '' ? isset($moved[$key]) : str_contains(mb_strtolower($meta['code'].' '.$meta['name']), $find);
            if ($match) {
                $cards[$meta['code'].' '.$key] = [$key, $i, $meta];
            }
        }
        ksort($cards);

        $rows = [];
        $from = $ledger->from->toDateString();
        foreach ($cards as [$key, $i, $meta]) {
            $base = ['class' => self::CLASSES[$i['class']], 'item' => $meta['name'], 'item_code' => $meta['code'], 'unit' => $i['uom']];
            $rows[] = ['id' => 'O-'.$key, 'doc_date' => $from, 'doc_no' => null, 'move' => 'Opening', 'warehouse' => null, 'location' => null, 'lot' => null, 'ref_no' => null,
                'in' => 0.0, 'out' => 0.0, 'adj' => 0.0, 'transfer' => 0.0, 'balance' => $i['opening_qty'], 'location_balance' => null, 'value' => null, 'avg_price' => null] + $base;
            foreach ($moved[$key] ?? [] as $m) {
                $rows[] = ['id' => $m['class'].'-'.$m['doc_id'], 'doc_date' => $m['date'], 'doc_no' => $m['doc_no'], 'move' => $m['kind'], 'warehouse' => $m['warehouse'],
                    'location' => $m['location'], 'lot' => $m['lot'], 'ref_no' => $m['ref_no'], 'in' => $m['in'], 'out' => $m['out'], 'adj' => $m['adj'], 'transfer' => $m['transfer'],
                    'balance' => $m['item_balance'], 'location_balance' => $m['location_balance'], 'value' => $m['value'], 'avg_price' => $m['avg_price'], 'unit' => $m['uom'] ?: $i['uom']] + $base;
            }
            if (count($rows) > self::MAX_ROWS) {
                $this->truncated = true;
                break;
            }
        }

        return $rows;
    }

    public function columns(): array
    {
        return [
            ['key' => 'doc_date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'doc_no', 'label' => 'Doc No.'],
            ['key' => 'move', 'label' => 'Movement', 'type' => 'badge', 'tones' => ['In' => 'success', 'Out' => 'danger', 'Adjustment' => 'warn', 'Opening' => 'neutral', 'Transfer in' => 'info', 'Transfer out' => 'info']],
            ['key' => 'class', 'label' => 'Class', 'visible' => false],
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'item_code', 'avatar' => false],
            ['key' => 'warehouse', 'label' => 'Warehouse'],
            ['key' => 'location', 'label' => 'Location'],
            ['key' => 'lot', 'label' => 'Lot No.', 'visible' => false],
            ['key' => 'ref_no', 'label' => 'Reference'],
            ['key' => 'in', 'label' => 'In', 'type' => 'number', 'sum' => true],
            ['key' => 'out', 'label' => 'Out', 'type' => 'number', 'sum' => true],
            ['key' => 'adj', 'label' => 'Adjustment', 'type' => 'number', 'sum' => true],
            ['key' => 'transfer', 'label' => 'Transfer', 'type' => 'number', 'visible' => false],
            ['key' => 'balance', 'label' => 'Balance', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'location_balance', 'label' => 'Balance in Location', 'type' => 'number', 'visible' => false],
            ['key' => 'value', 'label' => 'Movement Value', 'type' => 'money', 'sum' => true, 'visible' => false],
            ['key' => 'avg_price', 'label' => 'Average Price', 'type' => 'money', 'visible' => false],
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'In', 'where' => ['move' => 'In'], 'tone' => 'success'],
            ['label' => 'Out', 'where' => ['move' => 'Out'], 'tone' => 'danger'],
            ['label' => 'Adjustment', 'where' => ['move' => 'Adjustment'], 'tone' => 'warn'],
            ['label' => 'Transfer', 'where' => ['move' => ['Transfer in', 'Transfer out']], 'tone' => 'info'],
        ];
    }

    public function filters(): array
    {
        return ['move' => 'Movement', 'class' => 'Class', 'warehouse' => 'Warehouse', 'location' => 'Location'];
    }

    public function groups(): array
    {
        return ['item' => 'Item', 'warehouse' => 'Warehouse', 'move' => 'Movement'];
    }

    public function printSummary(): array
    {
        return [['label' => t('In'), 'sum' => 'in'], ['label' => t('Out'), 'sum' => 'out'], ['label' => t('Adjustment'), 'sum' => 'adj', 'signed' => true]];
    }
}
