<?php

declare(strict_types=1);

namespace App\Support\V2\Grid\Inv;

use App\Support\V2\Data\ReceivingPutaway;
use App\Support\V2\Grid\DocListGrid;

// Inventory > Warehouse > Receiving & Putaway (tm2inv.wh_task): daftar tugas putaway. Kotak statistik = saringan; Open membuka tugas di
// layar yang sama (?task=) untuk pindai palet + bin. Baris dan usulan bin: ReceivingPutaway (data) + WarehouseTaskService (logika).
class ReceivingPutawayGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'wh-receiving-putaway';
    }

    public function title(): string
    {
        return 'Receiving & Putaway';
    }

    public function module(): string
    {
        return 'INV';
    }

    public function moduleLabel(): string
    {
        return 'Inventory';
    }

    public function group(): string
    {
        return 'Warehouse';
    }

    public function icon(): string
    {
        return 'fa-solid fa-dolly';
    }

    protected function listRows(): array
    {
        return ReceivingPutaway::rows();
    }

    protected function sources(): array
    {
        return ['tm2inv.wh_task', 'tm2inv.inv_pallet', 'tm2inv.inv_batch', 'tm2mst.wh_loc', 'tm2mst.wh_zone'];
    }

    public function search(): string
    {
        return 'Search pallet, receipt, item, batch...';
    }

    public function guide(): ?string
    {
        return 'Pallets waiting in a receiving or QC hold area. Open a row to scan the pallet and then the bin. A pallet whose batch is still waiting for sampling cannot be put away yet; '
            .'a Conditional (URGENT) batch goes straight to the production line.';
    }

    public function newUrl(): ?string
    {
        return null;
    }

    public function columns(): array
    {
        return [
            ['key' => 'pallet', 'label' => 'Pallet'],
            ['key' => 'receipt', 'label' => 'Receipt'],
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'item_code', 'avatar' => false],
            ['key' => 'batch', 'label' => 'Batch'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'arrived', 'label' => 'Arrived', 'type' => 'datetime'],
            ['key' => 'quality', 'label' => 'Quality', 'type' => 'badge', 'tones' => ReceivingPutaway::QUALITY_TONES],
            ['key' => 'suggested', 'label' => 'Suggested Bin', 'type' => 'identity', 'code' => 'why', 'avatar' => false],
            ['key' => 'action', 'label' => 'Action', 'type' => 'badge', 'tones' => ReceivingPutaway::ACTION_TONES],
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All today'],
            ['label' => 'Waiting sampling', 'where' => ['state' => 'WAITING'], 'tone' => 'warn'],
            ['label' => 'Ready to put away', 'where' => ['state' => 'READY'], 'tone' => 'success'],
            ['label' => 'URGENT to line', 'where' => ['state' => 'TO_LINE'], 'tone' => 'violet'],
            ['label' => 'Done', 'where' => ['state' => 'DONE'], 'tone' => 'neutral'],
        ];
    }

    public function filters(): array
    {
        return ['quality' => 'Quality', 'action' => 'Action'];
    }
}
