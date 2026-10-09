<?php

namespace App\Support\V2\Grid\Inv;

use App\Services\Inventory\Valuation\ObsolescenceService;

// Stock Aging: umur tanpa pergerakan tiap item x bin, kelas (Active / Slow / Non-moving / Dead), persen dan usulan provisi. Hanya dibaca; jurnal provisi
// diposting dari layar Obsolete Stock. To date = tanggal per saat.
class StockAgingReport extends ValuationReport
{
    public function slug(): string
    {
        return 'inv-stock-aging';
    }

    public function title(): string
    {
        return 'Stock Aging';
    }

    public function icon(): string
    {
        return 'fa-solid fa-hourglass-half';
    }

    public function guide(): ?string
    {
        return 'Stock on hand by days since its last movement (transfers do not count). The class limits are INV_AGING_SLOW / NONMOVING / DEAD_DAYS and the provision percentages '
            .'INV_OBSOLETE_PCT_* in Settings > Constants. Nothing is posted from this report.';
    }

    protected function sources(): array
    {
        return ['tm2inv.v_rm_onhand', 'tm2inv.v_sfg_onhand', 'tm2inv.v_fg_onhand', 'tm2mst.mat_raw', 'tm2mst.prod_part', 'tm2mst.prod_product'];
    }

    protected function listRows(): array
    {
        return array_map(fn (array $r) => $r + ['class' => AllStockReport::CLASSES[$r['stock_class']], 'aging' => ObsolescenceService::LABELS[$r['aging_class']],
            'tone' => $r['aging_class'] === ObsolescenceService::DEAD ? 'danger' : null], app(ObsolescenceService::class)->rows($this->asOf()));
    }

    public function rowTone(): ?string
    {
        return 'tone';
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Item', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'class', 'label' => 'Class'],
            ['key' => 'location', 'label' => 'Location'],
            ['key' => 'qty', 'label' => 'On Hand', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit', 'width' => '70px'],
            ['key' => 'value', 'label' => 'Stock Value', 'type' => 'money', 'sum' => true],
            ['key' => 'last_movement', 'label' => 'Last Movement', 'type' => 'date'],
            ['key' => 'idle_days', 'label' => 'Idle (days)', 'type' => 'integer'],
            ['key' => 'aging', 'label' => 'Aging', 'type' => 'badge', 'tones' => ['Active' => 'success', 'Slow' => 'info', 'Non-moving' => 'warn', 'Dead' => 'danger']],
            ['key' => 'pct', 'label' => 'Provision %', 'type' => 'number', 'decimals' => 2],
            ['key' => 'provision', 'label' => 'Provision', 'type' => 'money', 'sum' => true],
        ];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Slow', 'where' => ['aging' => 'Slow'], 'tone' => 'info'], ['label' => 'Non-moving', 'where' => ['aging' => 'Non-moving'], 'tone' => 'warn'],
            ['label' => 'Dead', 'where' => ['aging' => 'Dead'], 'tone' => 'danger']];
    }

    public function filters(): array
    {
        return ['aging' => 'Aging', 'class' => 'Class', 'location' => 'Location'];
    }

    public function groups(): array
    {
        return ['aging' => 'Aging', 'class' => 'Class'];
    }
}
