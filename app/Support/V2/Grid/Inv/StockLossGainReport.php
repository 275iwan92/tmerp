<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvMovement;
use Devlib\Locale;

// Stock Loss & Gain: selisih stok SEMUA item per BULAN (user 2026-10-02: "loss and gain all items every monthly"; di
// Indonesia selisih itu masuk lewat stock opname). Sumber = baris kartu stok penyesuaian (hasil dokumen Physical Count /
// Adjustment yang sudah Posted): Gain = penyesuaian tambah, Loss = penyesuaian kurang, nilainya = qty x harga rata-rata
// saat itu (penyesuaian tidak mengubah harga rata-rata). Satu baris = satu item di satu bulan.
class StockLossGainReport extends AllStockReport
{
    public function slug(): string
    {
        return 'inv-rpt-loss-gain';
    }

    public function title(): string
    {
        return 'Stock Loss & Gain';
    }

    public function icon(): string
    {
        return 'fa-solid fa-scale-unbalanced';
    }

    public function guide(): ?string
    {
        return 'Stock gained and lost per item per month, from posted physical counts and adjustments. Gain = stock added, Loss = stock taken off, valued at the average price of that moment. The count documents behind a month are in Stock Opname.';
    }

    // Bawaan: awal tahun berjalan sampai hari ini (rekap bulanan).
    protected function defaultPeriod(): ?array
    {
        return [now()->toImmutable()->startOfYear(), now()->toImmutable()->startOfDay()];
    }

    public function groupBy(): ?string
    {
        return 'month';
    }

    public function rows(): array
    {
        [$from, $to] = $this->period();
        $moves = InvMovement::query()->whereBetween('doc_date', [$from->startOfDay(), $to->endOfDay()])
            ->where(fn ($q) => $q->whereIn('doc_type', ['AD', 'ADJ'])->orWhereIn('doc_cat', array_keys(StockOpnameReport::KINDS)))
            ->where('doc_type', '<>', 'TF')->where('qty_adj', '<>', 0)->orderBy('doc_date')->orderBy('doc_id')->get();
        $this->loadMeta($moves->map(fn ($m) => ['class' => (string) $m->stock_class, 'item_id' => (int) $m->item_id]));

        $rows = [];
        foreach ($moves as $m) {
            $class = (string) $m->stock_class;
            $month = $m->doc_date->format('Y-m');
            $key = $month.'|'.$class.'|'.$m->item_id;
            if (! isset($rows[$key])) {
                $meta = $this->meta($class, (int) $m->item_id);
                $rows[$key] = ['id' => $key, 'month' => $month.' · '.Locale::at($m->doc_date)->translatedFormat('M Y'), 'item' => $meta['name'], 'item_code' => $meta['code'],
                    'class' => self::CLASSES[$class], 'category' => $meta['group'], 'gain' => 0.0, 'loss' => 0.0, 'unit' => $m->uom_code, 'gain_value' => 0.0, 'loss_value' => 0.0,
                    'docs' => [], 'sources' => []];
            }
            $qty = (float) $m->qty_adj;
            $value = abs($qty) * (float) $m->avg_unitprice;
            $rows[$key][$qty > 0 ? 'gain' : 'loss'] += abs($qty);
            $rows[$key][$qty > 0 ? 'gain_value' : 'loss_value'] += $value;
            $rows[$key]['docs'][$m->doc_no ?? '#'.$m->doc_id] = true;
            $rows[$key]['sources'][StockOpnameReport::KINDS[$m->doc_cat] ?? 'Adjustment'] = true;
        }
        ksort($rows);

        return array_values(array_map(function (array $r) {
            $net = $r['gain_value'] - $r['loss_value'];

            return ['net' => $r['gain'] - $r['loss'], 'net_value' => $net, 'documents' => count($r['docs']), 'source' => implode(' + ', array_keys($r['sources'])),
                'result' => $net > 0 ? 'Gain' : ($net < 0 ? 'Loss' : 'Balanced')] + array_diff_key($r, ['docs' => 1, 'sources' => 1]);
        }, $rows));
    }

    public function columns(): array
    {
        return [
            ['key' => 'month', 'label' => 'Month'],
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'item_code', 'avatar' => false],
            ['key' => 'class', 'label' => 'Class'],
            ['key' => 'category', 'label' => 'Category', 'visible' => false],
            ['key' => 'gain', 'label' => 'Gain', 'type' => 'number', 'sum' => true],
            ['key' => 'loss', 'label' => 'Loss', 'type' => 'number', 'sum' => true],
            ['key' => 'net', 'label' => 'Net', 'type' => 'number', 'sum' => true],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'gain_value', 'label' => 'Gain Value', 'type' => 'money', 'sum' => true],
            ['key' => 'loss_value', 'label' => 'Loss Value', 'type' => 'money', 'sum' => true],
            ['key' => 'net_value', 'label' => 'Net Value', 'type' => 'money', 'sum' => true],
            ['key' => 'documents', 'label' => 'Documents', 'type' => 'number', 'visible' => false],
            ['key' => 'source', 'label' => 'Source', 'visible' => false],
            ['key' => 'result', 'label' => 'Result', 'type' => 'badge', 'tones' => ['Gain' => 'success', 'Loss' => 'danger', 'Balanced' => 'neutral']],
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Loss', 'where' => ['result' => 'Loss'], 'tone' => 'danger'],
            ['label' => 'Gain', 'where' => ['result' => 'Gain'], 'tone' => 'success'],
        ];
    }

    public function filters(): array
    {
        return ['month' => 'Month', 'class' => 'Class', 'category' => 'Category', 'result' => 'Result', 'source' => 'Source'];
    }

    public function groups(): array
    {
        return ['month' => 'Month', 'class' => 'Class', 'category' => 'Category', 'item' => 'Item'];
    }

    public function printSummary(): array
    {
        return [['label' => t('Gain Value'), 'sum' => 'gain_value'], ['label' => t('Loss Value'), 'sum' => 'loss_value'], ['label' => t('Net Value'), 'sum' => 'net_value', 'signed' => true]];
    }

    public function printSignatures(): array
    {
        return [['Prepared By', 'Warehouse'], ['Checked By', 'Accounting'], ['Approved By', 'Head of Warehouse']];
    }
}
