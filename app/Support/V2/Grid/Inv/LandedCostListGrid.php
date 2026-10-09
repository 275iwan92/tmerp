<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvLandedCost;
use App\Models\Master\SupSupplier;

// Landed Cost: daftar dokumen biaya (ongkos angkut, bea, asuransi) yang dibagi ke penerimaan barang. Klik baris = buka dokumen (?id=), + New Record = ?new=1.
class LandedCostListGrid extends ValuationReport
{
    public const TONES = ['Draft' => 'neutral', 'Confirmed' => 'success', 'Cancelled' => 'danger'];

    public function slug(): string
    {
        return 'landed-cost';
    }

    public function title(): string
    {
        return 'Landed Cost';
    }

    public function icon(): string
    {
        return 'fa-solid fa-truck-ramp-box';
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function printable(): bool
    {
        return false;
    }

    protected function defaultPeriod(): ?array
    {
        return null;
    }

    public function guide(): ?string
    {
        return 'Freight, duty or insurance that arrives after the goods receipt. It is shared over the receipt lines by value or quantity: the part still in stock raises the inventory value, '
            .'the part already issued goes to the landed cost variance account.';
    }

    public function openUrl(): ?string
    {
        return $this->url().'?id={id}';
    }

    public function newUrl(): ?string
    {
        return $this->url().'?new=1';
    }

    protected function sources(): array
    {
        return ['tm2inv.inv_landed_cost', 'tm2inv.inv_landed_cost_line', 'tm2mst.sup_supplier'];
    }

    protected function listRows(): array
    {
        $docs = InvLandedCost::query()->withCount('lines')->orderByDesc('id_landed_cost')->limit($this->limit)->get();
        $names = SupSupplier::query()->whereKey($docs->pluck('supplier_id')->filter()->unique()->all())->pluck('supplier_name', 'id_supplier');

        return $docs->map(fn (InvLandedCost $d) => ['id' => $d->id_landed_cost, 'no' => $d->doc_no ?: 'DRAFT-'.$d->id_landed_cost, 'date' => $d->doc_date?->toDateString(),
            'status' => ucfirst(strtolower($d->status())), 'type' => InvLandedCost::TYPES[$d->cost_type] ?? $d->cost_type, 'supplier' => $names[$d->supplier_id] ?? '', 'description' => (string) $d->description,
            'basis' => InvLandedCost::BASES[$d->alloc_basis] ?? $d->alloc_basis, 'lines' => $d->lines_count, 'amount' => $d->amount, 'to_stock' => $d->amount_to_stock, 'to_variance' => $d->amount_to_variance])->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Doc No.', 'width' => '120px'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::TONES],
            ['key' => 'type', 'label' => 'Cost Type'],
            ['key' => 'supplier', 'label' => 'Charged By'],
            ['key' => 'description', 'label' => 'Description'],
            ['key' => 'basis', 'label' => 'Share By', 'visible' => false],
            ['key' => 'lines', 'label' => 'Receipt Lines', 'type' => 'integer'],
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'money', 'sum' => true],
            ['key' => 'to_stock', 'label' => 'To Inventory', 'type' => 'money', 'sum' => true],
            ['key' => 'to_variance', 'label' => 'To Variance', 'type' => 'money', 'sum' => true],
        ];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Draft', 'where' => ['status' => 'Draft'], 'tone' => 'neutral'], ['label' => 'Confirmed', 'where' => ['status' => 'Confirmed'], 'tone' => 'success'],
            ['label' => 'Cancelled', 'where' => ['status' => 'Cancelled'], 'tone' => 'danger']];
    }

    public function filters(): array
    {
        return ['status' => 'Status', 'type' => 'Cost Type', 'supplier' => 'Charged By'];
    }
}
