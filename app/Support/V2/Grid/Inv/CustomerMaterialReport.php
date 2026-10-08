<?php

declare(strict_types=1);

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\RmStockcard;
use App\Models\Master\CustCustomer;
use App\Models\Master\MatRaw;
use App\Models\Master\WhLoc;

// Customer Material (v2.01, D3): bahan titipan customer yang masih ada di gudang (stok pemilik Customer, tanpa nilai). Pemilik sebuah posisi (bahan di satu bin)
// = pemilik baris kartu stoknya yang terakhir, sama dengan yang dipakai alokasi pick. Hanya dibaca.
class CustomerMaterialReport extends QualityDocGrid
{
    public function slug(): string
    {
        return 'customer-material';
    }

    public function title(): string
    {
        return 'Customer Material';
    }

    public function group(): string
    {
        return 'Warehouse › Report';
    }

    public function icon(): string
    {
        return 'fa-solid fa-user-tag';
    }

    public function guide(): ?string
    {
        return 'Material that belongs to a customer and sits in our warehouse. It has no value and no journal, and it can only be picked for that customer\'s jobs. Age counts from the last movement.';
    }

    protected function sources(): array
    {
        return ['tm2inv.rm_stockcard', 'tm2mst.cust_customer', 'tm2mst.wh_loc'];
    }

    protected function listRows(): array
    {
        $positions = RmStockcard::query()->where('owner_type', 'CUSTOMER')->select('material_id', 'location_id')->distinct()->get();
        $last = $positions->map(fn ($p) => RmStockcard::query()->where('material_id', $p->material_id)->where('location_id', $p->location_id)
            ->orderByDesc('doc_date')->orderByDesc('doc_id')->first())->filter(fn ($c) => $c && $c->owner_type === 'CUSTOMER' && (float) $c->qty_eb > 0.00005)->values();
        $customers = CustCustomer::withoutGlobalScopes()->whereKey($last->pluck('owner_id')->filter()->unique()->all())->pluck('customer_name', 'id_customer');
        $materials = MatRaw::withoutGlobalScopes()->whereKey($last->pluck('material_id')->unique()->all())->get(['material_id', 'material_code', 'material_name'])->keyBy('material_id');
        $bins = WhLoc::query()->whereKey($last->pluck('location_id')->unique()->all())->pluck('location_code', 'location_id');

        return $last->map(fn ($c) => ['id' => $c->doc_id, 'customer' => trim((string) ($customers[$c->owner_id] ?? '#'.$c->owner_id)), 'item' => $materials[$c->material_id]->material_name ?? '#'.$c->material_id,
            'item_code' => $materials[$c->material_id]->material_code ?? null, 'bin' => $bins[$c->location_id] ?? '—', 'qty' => (float) $c->qty_eb, 'unit' => $c->uom_code,
            'last' => $c->doc_date?->toDateString(), 'age' => (int) $c->doc_date?->diffInDays(today(), true)])->sortBy([['customer', 'asc'], ['item', 'asc']])->values()->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'item_code', 'avatar' => false],
            ['key' => 'bin', 'label' => 'Bin'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'decimals' => 2, 'sum' => true],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'last', 'label' => 'Last Movement', 'type' => 'date'],
            ['key' => 'age', 'label' => 'Age (days)', 'type' => 'number', 'decimals' => 0],
        ];
    }

    public function filters(): array
    {
        return ['customer' => 'Customer'];
    }
}
