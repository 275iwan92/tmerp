<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Inventory\InvSubconIssue;
use App\Models\Inventory\InvSubconIssueLine;
use App\Models\Master\SupSupplier;
use App\Models\Master\WhLoc;
use App\Models\Pur\PoOrder;
use App\Services\Inventory\WarehouseStock;
use Devlib\Locale;

// Data layar Warehouse > Send to Subcontractor (subcon-send): daftar kirim bahan ke subkon dan isian kirim baru. Logika: App\Services\Inventory\SubconStockService.
final class SubconSends
{
    public const TONES = ['Sent' => 'info', 'Closed' => 'success', 'Cancelled' => 'neutral'];

    public static function find(int $id): ?InvSubconIssue
    {
        return InvSubconIssue::query()->with('lines')->find($id);
    }

    /** @return list<array<string, mixed>> */
    public static function rows(): array
    {
        $issues = InvSubconIssue::query()->with('lines')->orderByDesc('id_subcon_issue')->limit(300)->get();
        $names = SupSupplier::query()->whereKey($issues->pluck('supplier_id')->unique()->all())->pluck('supplier_name', 'id_supplier');

        return $issues->map(fn (InvSubconIssue $i) => ['id' => $i->id_subcon_issue, 'no' => $i->issue_no, 'po' => $i->po_no, 'supplier' => trim((string) ($names[$i->supplier_id] ?? '#'.$i->supplier_id)),
            'date' => $i->issue_date?->toDateString(), 'due' => $i->due_return_date?->toDateString(), 'item' => $i->lines->pluck('item_name')->implode(', '),
            'sent' => (float) $i->lines->sum('qty_sent'), 'at_vendor' => (float) $i->lines->sum(fn (InvSubconIssueLine $l) => $l->atVendor()), 'status' => InvSubconIssue::STATUSES[$i->status] ?? $i->status,
            'overdue' => $i->status === 'SENT' && $i->due_return_date && $i->due_return_date->lt(today()) ? 'Overdue' : null,
            'items' => $i->lines->map(fn (InvSubconIssueLine $l) => ['code' => $l->item_code, 'name' => $l->item_name, 'qty' => $l->qty_sent, 'unit' => $l->uom_code,
                'used' => $l->qty_consumed, 'back' => $l->qty_returned])->all()])->all();
    }

    // Subcon PO yang masih terbuka: id => "nomor · pemasok".
    /** @return array<int, string> */
    public static function poOptions(): array
    {
        $orders = PoOrder::query()->where('i_po_type', 'SUBCONTRACT')->whereIn('status', ['SENT', 'PARTIALLY_RECEIVED'])->where('stat_cancel', false)->orderByDesc('po_id')->limit(200)->get();
        $names = SupSupplier::query()->whereKey($orders->pluck('i_supplier_id')->unique()->all())->pluck('supplier_name', 'id_supplier');

        return $orders->mapWithKeys(fn (PoOrder $o) => [$o->po_id => $o->doc_no.' · '.trim((string) ($names[$o->i_supplier_id] ?? ''))])->all();
    }

    // Bahan baku di bin gudang nyata (bukan area virtual): "idBahan:idBin" => "Kode Nama | Bin | stok".
    /** @return array<string, string> */
    public static function positionOptions(): array
    {
        $positions = WarehouseStock::positions()->where('class', 'RM')->values();
        $virtual = WhLoc::query()->whereKey($positions->pluck('location_id')->unique()->all())->whereHas('zone', fn ($z) => $z->where('is_virtual', true))->pluck('location_id')->flip();

        return $positions->reject(fn (array $p) => $virtual->has($p['location_id']))->sortBy('code')->take(1500)
            ->mapWithKeys(fn (array $p) => [$p['item_id'].':'.$p['location_id'] => $p['code'].' '.$p['name'].' | '.$p['location_code'].' | '.Locale::decimal($p['qty'], 2).' '.$p['uom']])->all();
    }
}
