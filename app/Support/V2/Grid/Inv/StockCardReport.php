<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvMovement;
use App\Services\Inventory\ItemBalanceChain;
use App\Services\Inventory\StockCardChain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Stock Card (v1: pages::inventory.print.stock-card): semua mutasi satu kelas stok, terbaru di atas. v1 memilih satu item
// dulu; di sini item disaring lewat kotak cari / saring kolom, atau dikelompokkan per item.
// RUMUS BERSAMBUNG per item (user 2026-10-08, sama dengan StockCardAllReport): BB = saldo item (semua lokasi) sebelum baris,
// EB = BB + In - Out + Adj, BB baris berikutnya = EB baris sebelumnya (App\Services\Inventory\ItemBalanceChain). Opening / Balance
// lama yang per lokasi tetap ada sebagai kolom opsional tersembunyi Location Opening / Location Balance.
abstract class StockCardReport extends StockReportGrid
{
    /** @var array<int, array{bb: float, eb: float}> doc_id => saldo item sebelum / sesudah baris */
    private array $chain = [];

    protected function prefetch(Collection $items): void
    {
        $this->chain = ItemBalanceChain::forNewest($this->category(), $items);
    }

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
            .'BB is the balance of the item (all locations) before the movement, the same as the EB of the row before it; EB is the balance after it.';
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
            ['key' => 'move', 'label' => 'Movement', 'type' => 'badge', 'tones' => ['In' => 'success', 'Out' => 'danger', 'Adjustment' => 'warn', 'Opening' => 'neutral', 'Value adjustment' => 'info', 'Transfer in' => 'info', 'Transfer out' => 'info']],
            ['key' => 'doc_cat', 'label' => 'Category', 'visible' => false],
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'item_code', 'avatar' => false],
            ['key' => 'warehouse', 'label' => 'Warehouse'],
            ['key' => 'location', 'label' => 'Location'],
            ['key' => 'lot', 'label' => 'Lot No.', 'visible' => false],
            ['key' => 'ref_no', 'label' => 'Reference'],
            ['key' => 'bb', 'label' => raw('BB'), 'type' => 'number'],
            ['key' => 'in', 'label' => 'In', 'type' => 'number', 'sum' => true],
            ['key' => 'out', 'label' => 'Out', 'type' => 'number', 'sum' => true],
            ['key' => 'adj', 'label' => 'Adjustment', 'type' => 'number', 'sum' => true],
            ['key' => 'eb', 'label' => raw('EB'), 'type' => 'number'],
            ['key' => 'loc_begin', 'label' => 'Location Opening', 'type' => 'number', 'visible' => false],
            ['key' => 'loc_balance', 'label' => 'Location Balance', 'type' => 'number', 'visible' => false],
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
                $m->doc_type === StockCardChain::VALUE_ADJUSTMENT => 'Value adjustment',
                $adj != 0.0 => 'Adjustment',
                $in > 0 => 'In',
                $out > 0 => 'Out',
                default => 'Opening',
            },
            'item' => $m->item_name, 'item_code' => $m->item_code, 'warehouse' => $m->warehouse_name, 'location' => $m->location_name,
            'lot' => $m->lot_no, 'ref_no' => $m->ref_doc_no, 'bb' => $this->chain[$m->doc_id]['bb'] ?? (float) $m->qty_bb, 'in' => $in, 'out' => $out, 'adj' => $adj,
            'eb' => $this->chain[$m->doc_id]['eb'] ?? (float) $m->qty_eb, 'loc_begin' => (float) $m->qty_bb, 'loc_balance' => (float) $m->qty_eb, 'unit' => $m->uom_code,
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
