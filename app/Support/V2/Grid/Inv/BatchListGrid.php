<?php

declare(strict_types=1);

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvBatch;
use App\Support\V2\Grid\DocListGrid;

// Inventory > Batch & Quality > Batch (docs/mockups/inv_v201_batch_grid.jpg): daftar batch sebagai Grid Transactional; Open = form batch
// (resources/views/v2/screens/inv-batch.blade.php), + New Record = form batch kosong. Baris dan kotak statistik datang dari BatchLookup
// (satu sumber, termasuk On Hand dari kartu stok dan Expires soon); menyimpan lewat BatchLookup::save.
class BatchListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'inv-batch';
    }

    public function title(): string
    {
        return 'Batch';
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
        return 'Batch & Quality';
    }

    public function icon(): string
    {
        return 'fa-solid fa-tags';
    }

    protected function listRows(): array
    {
        return (new BatchLookup)->rows();
    }

    protected function sources(): array
    {
        return ['tm2inv.inv_batch', 'tm2inv.inv_pallet', 'tm2inv.rm_stockcard', 'tm2inv.sfg_stockcard', 'tm2inv.fg_stockcard', 'tm2mst.sup_supplier'];
    }

    public function search(): string
    {
        return 'Search batch, item, supplier lot...';
    }

    public function guide(): ?string
    {
        return 'Expiry is empty for an item without an expiry date. On Hand is counted from the stock card of the batch. Status is set by the quality decision, not typed.';
    }

    public function columns(): array
    {
        return [
            ['key' => 'batch_no', 'label' => 'Batch', 'type' => 'identity', 'code' => 'supplier_lot', 'avatar' => false],
            ['key' => 'item_name', 'label' => 'Item', 'type' => 'identity', 'code' => 'item_code', 'avatar' => false],
            ['key' => 'supplier_name', 'label' => 'Supplier'],
            ['key' => 'production_date', 'label' => 'Production', 'type' => 'date'],
            ['key' => 'expiry_date', 'label' => 'Expiry', 'type' => 'date', 'toneKey' => 'expiry_tone'],
            ['key' => 'qc_status', 'label' => 'Status', 'type' => 'badge', 'tones' => array_column(InvBatch::STATUSES, 'tone', 'label')],
            ['key' => 'cert_label', 'label' => 'Certification'],
            ['key' => 'food_label', 'label' => 'Food Grade'],
            ['key' => 'on_hand', 'label' => 'On Hand (kg / unit)', 'type' => 'number', 'decimals' => 2],
            ['key' => 'pallet_count', 'label' => 'Pallets', 'type' => 'number', 'decimals' => 0],
        ];
    }

    public function stats(): array
    {
        return (new BatchLookup)->stats();
    }

    public function filters(): array
    {
        return ['item_name' => 'Item', 'supplier_name' => 'Supplier', 'cert_label' => 'Certification'];
    }

    public function screen(): array
    {
        return ['view' => 'v2.screens.inv-batch'] + parent::screen();
    }
}
