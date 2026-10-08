<?php

declare(strict_types=1);

namespace App\Support\V2\Print;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvPallet;
use App\Models\Master\WhLoc;
use App\Support\V2\Data\PickLists;
use Devlib\PrintDoc\Document;

// Pick List (gudang): daftar ambil per WO untuk petugas gudang, tanpa harga. Satu baris = satu palet; kolom Picked dicentang tangan di
// lantai gudang (konfirmasi sebenarnya = pindai di layar). Baris SHORT dicetak dengan kolom palet "SHORT".
// A5 landscape untuk 1-5 baris, A4 portrait di atasnya (aturan Document). Sumber: tm2inv.wh_pick_list + wh_pick_list_line.
final class PickListPrint extends Document
{
    public string $type = 'pick-list';

    public string $title = 'Pick List';

    public ?string $subtitle = 'Warehouse Material Pick';

    public string $numberLabel = 'Pick List No.';

    public array $refs = [
        ['key' => 'wo_no', 'label' => 'Work Order'],
        ['key' => 'booking', 'label' => 'Booking'],
        ['key' => 'need', 'label' => 'Needed By', 'type' => 'date'],
        ['key' => 'picked_by', 'label' => 'Picked By', 'hand' => true],
    ];

    public string $partyLabel = 'Deliver To';

    public string $nb = 'Take only the pallet listed, from the bin listed. Scan the pallet, then the bin, before the material leaves the warehouse.';

    public array $extra = [
        ['key' => 'pallet', 'label' => 'Pallet', 'width' => 26],
        ['key' => 'batch', 'label' => 'Batch', 'width' => 22],
        ['key' => 'bin', 'label' => 'Bin', 'width' => 24],
        ['key' => 'ok', 'label' => 'Picked', 'width' => 12, 'hand' => true],
    ];

    public array $receivers = [['Production', 'Receives the material']];

    public static function load(int $id, array $opt = []): static
    {
        $list = PickLists::find($id) ?? abort(404);
        $head = PickLists::header($list);
        $lines = $list->lines->where('status', '!=', 'CANCELLED')->values();
        $pallets = InvPallet::query()->whereKey($lines->pluck('pallet_id')->filter()->all())->pluck('pallet_no', 'id_pallet');
        $batches = InvBatch::query()->whereKey($lines->pluck('batch_id')->filter()->all())->pluck('batch_no', 'id_batch');
        $bins = WhLoc::query()->whereKey($lines->pluck('from_location_id')->filter()->all())->pluck('location_code', 'location_id');

        return new self([
            'number' => $list->pick_no, 'date' => $list->pick_date?->toDateString(), 'status' => PickLists::STATUS_LABELS[$list->status] ?? $list->status, 'printedBy' => $opt['printedBy'] ?? null,
            'wo_no' => $list->wo_no, 'booking' => $head['booking'], 'need' => $list->need_date?->toDateString(),
            'party' => ['name' => $head['deliver_to'] ?? '—', 'lines' => []],
            'notes' => array_values(array_filter([$list->status === 'CANCELLED' ? ['Cancel Reason', (string) $list->cancel_reason] : null, $list->notes ? ['Notes', $list->notes] : null])),
            'rows' => $lines->map(fn ($l) => ['name' => $l->item_name, 'sub' => trim($l->item_code.($l->requirement ? ' · '.$l->requirement : '')), 'qty' => $l->status === 'SHORT' ? $l->qty_need : $l->qty_allocated,
                'unit' => $l->uom_code, 'pallet' => $l->status === 'SHORT' ? 'SHORT' : ($pallets[$l->pallet_id] ?? null), 'batch' => $batches[$l->batch_id] ?? null,
                'bin' => $bins[$l->from_location_id] ?? null, 'ok' => ''])->all(),
        ]);
    }
}
