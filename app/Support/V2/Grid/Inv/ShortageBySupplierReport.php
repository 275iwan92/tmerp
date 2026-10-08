<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvShortageRequest;

// Shortage per supplier (Q-16): berapa permintaan kirim kekurangan, berapa yang terbukti kurang, dikirim, ditolak, dan butuh debit note.
// Bahan skor mutu pemasok. Hanya dibaca; permintaan yang ditutup sebagai varians pemakaian tidak dihitung.
class ShortageBySupplierReport extends QualityDocGrid
{
    public function slug(): string
    {
        return 'shortage-by-supplier';
    }

    public function title(): string
    {
        return 'Shortage by Supplier';
    }

    public function icon(): string
    {
        return 'fa-solid fa-chart-column';
    }

    public function guide(): ?string
    {
        return 'Per supplier or subcontractor: shortage requests that were confirmed, the quantity proven short, how many were delivered afterwards, refused, or still open. Requests closed as a usage variance are left out.';
    }

    protected function sources(): array
    {
        return ['tm2inv.inv_shortage_request', 'tm2mst.sup_supplier'];
    }

    protected function listRows(): array
    {
        $all = InvShortageRequest::query()->where('source_type', '<>', 'CUSTOMER')->where('status', '<>', 'DRAFT')->where(fn ($q) => $q->whereNull('outcome')->orWhere('outcome', '<>', 'USAGE_VARIANCE'))->get();
        $names = $this->supplierNames($all->pluck('supplier_id'));

        return $all->groupBy('supplier_id')->map(function ($set, $supplierId) use ($names) {
            return ['id' => (int) $supplierId, 'supplier' => $names[$supplierId] ?? '#'.$supplierId, 'requests' => $set->count(), 'short' => (float) $set->sum('qty_short'),
                'delivered' => $set->where('outcome', 'DELIVERED')->count(), 'refused' => $set->whereIn('outcome', ['REFUSED', 'WINDOW_LAPSED'])->count(),
                'open' => $set->whereIn('status', ['CONFIRMED', 'SENT'])->count(), 'debit' => $set->where('debit_note_required', true)->count(),
                'last' => $set->max('confirmed_at') ? $this->dt($set->max('confirmed_at')) : ''];
        })->sortByDesc('short')->values()->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'supplier', 'label' => 'Supplier'],
            ['key' => 'requests', 'label' => 'Requests', 'type' => 'number', 'decimals' => 0, 'sum' => true],
            ['key' => 'short', 'label' => 'Qty Proven Short', 'type' => 'number', 'decimals' => 2, 'sum' => true],
            ['key' => 'delivered', 'label' => 'Delivered After', 'type' => 'number', 'decimals' => 0, 'sum' => true],
            ['key' => 'refused', 'label' => 'Refused / Lapsed', 'type' => 'number', 'decimals' => 0, 'sum' => true],
            ['key' => 'open', 'label' => 'Still Open', 'type' => 'number', 'decimals' => 0, 'sum' => true],
            ['key' => 'debit', 'label' => 'Debit Notes Needed', 'type' => 'number', 'decimals' => 0, 'sum' => true],
            ['key' => 'last', 'label' => 'Last Confirmed'],
        ];
    }
}
