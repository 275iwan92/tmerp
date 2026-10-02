<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Stock Card (v1: pages::inventory.print.stock-card): semua mutasi satu kelas stok, terbaru di atas. v1 memilih satu item
// dulu; di sini item disaring lewat kotak cari / saring kolom, atau dikelompokkan per item.
abstract class StockCardReport extends StockReportGrid
{
    protected function report(): array
    {
        return ['card', 'Stock Card'];
    }

    public function icon(): string
    {
        return 'fa-solid fa-clipboard-list';
    }

    public function guide(): ?string
    {
        return 'Every stock movement, newest first. To read the card of one item, type its code in the search box or group by Item; '
            .'Balance is the stock of that item in that location right after the movement.';
    }

    public function search(): string
    {
        return 'Search item, doc no, reference...';
    }

    protected function sourceTables(): array
    {
        return ['v_inv_movement'];
    }

    protected function query(): Builder
    {
        return InvMovement::query()->where('stock_class', $this->category())->orderByDesc('doc_date')->orderByDesc('doc_id');
    }

    public function columns(): array
    {
        return [
            ['key' => 'doc_date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'doc_no', 'label' => 'Doc No.'],
            ['key' => 'move', 'label' => 'Movement', 'type' => 'badge', 'tones' => ['In' => 'success', 'Out' => 'danger', 'Adjustment' => 'warn', 'Opening' => 'neutral', 'Transfer in' => 'info', 'Transfer out' => 'info']],
            ['key' => 'doc_cat', 'label' => 'Category', 'visible' => false],
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'item_code', 'avatar' => false],
            ['key' => 'warehouse', 'label' => 'Warehouse'],
            ['key' => 'location', 'label' => 'Location'],
            ['key' => 'lot', 'label' => 'Lot No.', 'visible' => false],
            ['key' => 'ref_no', 'label' => 'Reference'],
            ['key' => 'begin', 'label' => 'Opening', 'type' => 'number'],
            ['key' => 'in', 'label' => 'In', 'type' => 'number', 'sum' => true],
            ['key' => 'out', 'label' => 'Out', 'type' => 'number', 'sum' => true],
            ['key' => 'adj', 'label' => 'Adjustment', 'type' => 'number', 'sum' => true],
            ['key' => 'balance', 'label' => 'Balance', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
        ];
    }

    protected function row(Model $m): array
    {
        $in = (float) $m->qty_in;
        $out = (float) $m->qty_out;
        $adj = (float) $m->qty_adj;

        return [
            'doc_date' => $m->doc_date?->toDateString(), 'doc_no' => $m->doc_no, 'doc_cat' => $m->doc_cat,
            'move' => match (true) {
                // Stock Transfer (doc_type TF): pindah bin / gudang, bukan penerimaan atau pemakaian.
                $m->doc_type === 'TF' => $in > 0 ? 'Transfer in' : 'Transfer out',
                $adj != 0.0 => 'Adjustment',
                $in > 0 => 'In',
                $out > 0 => 'Out',
                default => 'Opening',
            },
            'item' => $m->item_name, 'item_code' => $m->item_code, 'warehouse' => $m->warehouse_name, 'location' => $m->location_name,
            'lot' => $m->lot_no, 'ref_no' => $m->ref_doc_no, 'begin' => (float) $m->qty_bb, 'in' => $in, 'out' => $out, 'adj' => $adj,
            'balance' => (float) $m->qty_eb, 'unit' => $m->uom_code,
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
        return ['move' => 'Movement', 'doc_cat' => 'Category', 'warehouse' => 'Warehouse', 'location' => 'Location'];
    }

    public function groups(): array
    {
        return ['item' => 'Item', 'warehouse' => 'Warehouse', 'move' => 'Movement'];
    }
}
