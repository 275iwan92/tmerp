<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvMovement;
use App\Models\Inventory\StockOnhand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Stock Balance (v1: pages::inventory.print.master-stock): saldo sekarang per item x lokasi + mutasi bulan berjalan
// (v1 membuka periode awal bulan s/d hari ini). Riwayat periode lain dibaca di Stock Card.
abstract class StockBalanceReport extends StockReportGrid
{
    // "item-lokasi" => ['begin', 'in', 'out', 'adj'] bulan berjalan.
    private array $month = [];

    // id item => saldo di lokasi yang bisa dipakai (pembanding stok minimum).
    private array $usable = [];

    protected function report(): array
    {
        return ['balance', 'Stock Balance'];
    }

    public function icon(): string
    {
        return 'fa-solid fa-boxes-stacked';
    }

    public function guide(): ?string
    {
        return 'On hand now, one row per item and location. Opening, In, Out and Adjustment are for the current month; '
            .'other periods are in Stock Card. Below minimum compares usable stock with the minimum stock of the item.';
    }

    protected function sourceTables(): array
    {
        return [StockOnhand::VIEWS[$this->category()], 'v_inv_movement'];
    }

    protected function query(): Builder
    {
        // FG: satu product bisa punya beberapa part di lokasi yang sama -> parts_id ikut di kunci baris.
        $part = $this->category() === 'FG' ? ", '-', IFNULL(parts_id, 0)" : '';

        return StockOnhand::of($this->category())->selectRaw("*, CONCAT({$this->itemKey()}, '-', location_id{$part}) AS row_id")
            ->orderBy('warehouse_name')->orderBy('location_name')->orderBy($this->itemKey());
    }

    protected function prefetch(Collection $items): void
    {
        $key = $this->itemKey();
        $this->loadItems($items->pluck($key)->all());
        $this->usable = $items->where('is_usable', 1)->groupBy($key)->map(fn ($g) => (float) $g->sum('qty_onhand'))->all();

        $this->month = InvMovement::query()->where('stock_class', $this->category())->where('doc_date', '>=', now()->startOfMonth())
            ->orderBy('doc_date')->orderBy('doc_id')->get(['item_id', 'location_id', 'qty_bb', 'qty_in', 'qty_out', 'qty_adj'])
            ->groupBy(fn ($r) => $r->item_id.'-'.$r->location_id)
            ->map(fn ($g) => ['begin' => (float) $g->first()->qty_bb, 'in' => (float) $g->sum('qty_in'), 'out' => (float) $g->sum('qty_out'), 'adj' => (float) $g->sum('qty_adj')])->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'item_code', 'avatar' => false],
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'warehouse', 'label' => 'Warehouse'],
            ['key' => 'location', 'label' => 'Location'],
            ['key' => 'begin', 'label' => 'Opening', 'type' => 'number', 'sum' => true],
            ['key' => 'in', 'label' => 'In', 'type' => 'number', 'sum' => true],
            ['key' => 'out', 'label' => 'Out', 'type' => 'number', 'sum' => true],
            ['key' => 'adj', 'label' => 'Adjustment', 'type' => 'number', 'sum' => true],
            ['key' => 'onhand', 'label' => 'On Hand', 'type' => 'number', 'sum' => true],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'min', 'label' => 'Minimum Stock', 'type' => 'number', 'visible' => false],
            ['key' => 'value', 'label' => 'Stock Value', 'type' => 'money', 'sum' => true],
            ['key' => 'last_movement', 'label' => 'Last Movement', 'type' => 'date'],
            ['key' => 'idle_days', 'label' => 'Idle (days)', 'type' => 'number', 'visible' => false],
            ['key' => 'stock', 'label' => 'Stock', 'type' => 'badge', 'tones' => ['OK' => 'success', 'Below minimum' => 'danger', 'Empty' => 'neutral', 'Not usable' => 'warn']],
        ];
    }

    protected function row(Model $m): array
    {
        $id = (int) $m->getAttribute($this->itemKey());
        $item = $this->item($id);
        $onhand = (float) $m->qty_onhand;
        $month = $this->month[$id.'-'.$m->location_id] ?? ['begin' => $onhand, 'in' => 0.0, 'out' => 0.0, 'adj' => 0.0];
        $moved = $this->movedAt($m->last_movement);

        return [
            'item' => $item['name'], 'item_code' => $item['code'], 'category' => $item['group'], 'warehouse' => $m->warehouse_name, 'location' => $m->location_name,
            'begin' => $month['begin'], 'in' => $month['in'], 'out' => $month['out'], 'adj' => $month['adj'], 'onhand' => $onhand, 'unit' => $m->uom_code,
            'min' => $item['min'] === null ? null : (float) $item['min'], 'value' => (float) $m->value_onhand,
            'last_movement' => $moved, 'idle_days' => $this->idleDays($moved),
            'stock' => match (true) {
                ! $m->is_usable => 'Not usable',
                (float) $item['min'] > 0 && ($this->usable[$id] ?? 0) < (float) $item['min'] => 'Below minimum',
                $onhand <= 0 => 'Empty',
                default => 'OK',
            },
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Below minimum', 'where' => ['stock' => 'Below minimum'], 'tone' => 'danger'],
            ['label' => 'Empty', 'where' => ['stock' => 'Empty'], 'tone' => 'neutral'],
            ['label' => 'Not usable', 'where' => ['stock' => 'Not usable'], 'tone' => 'warn'],
        ];
    }

    public function filters(): array
    {
        return ['category' => 'Category', 'warehouse' => 'Warehouse', 'location' => 'Location', 'stock' => 'Stock'];
    }

    public function groups(): array
    {
        return ['category' => 'Category', 'warehouse' => 'Warehouse', 'item' => 'Item'];
    }
}
