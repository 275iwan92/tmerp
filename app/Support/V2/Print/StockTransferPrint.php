<?php

namespace App\Support\V2\Print;

use App\Models\Inventory\InvTransfer;
use App\Models\Master\WhLoc;
use App\Support\V2\Data\StockTransfer;
use Devlib\PrintDoc\Document;

// Stock Transfer (belum A; user 2026-10-02: "input moving item antar warehouse"): bukti pindah stok antar bin / gudang,
// tanpa harga. Satu baris = satu item dari satu bin asal ke satu bin tujuan. Dicetak setelah Confirm (nomor sudah lahir).
// Sumber: tm2inv.inv_transfer + inv_transfer_line + tm2mst.wh_warehouse / wh_loc.
final class StockTransferPrint extends Document
{
    public string $type = 'stock-transfer';

    public string $title = 'Stock Transfer';

    public ?string $subtitle = 'Internal Stock Movement';

    public string $numberLabel = 'Transfer No.';

    public array $refs = [
        ['key' => 'from_warehouse', 'label' => 'From Warehouse'],
        ['key' => 'reason', 'label' => 'Reason'],
        ['key' => 'carried_by', 'label' => 'Carried By', 'hand' => true],
    ];

    public string $partyLabel = 'To Warehouse';

    public string $nb = 'Stock moves between bins of the company. Ownership and value do not change. DEAD = item that has not moved for months.';

    public array $extra = [
        ['key' => 'from_bin', 'label' => 'From Bin', 'width' => 32],
        ['key' => 'to_bin', 'label' => 'To Bin', 'width' => 32],
        ['key' => 'lot_no', 'label' => 'Lot No.', 'width' => 20],
    ];

    public array $receivers = [['Receiving Warehouse', 'Receives the goods']];

    public static function load(int $id, array $opt = []): static
    {
        $t = StockTransfer::find($id) ?? abort(404);
        abort_if($t->status() === 'DRAFT', 404);
        $bins = WhLoc::query()->whereKey($t->lines->pluck('from_location_id')->merge($t->lines->pluck('to_location_id'))->unique()->all())->pluck('location_code', 'location_id');
        $to = $t->toWarehouse;

        return new self([
            'number' => $t->transfer_no, 'date' => $t->transfer_date?->toDateString(), 'status' => $t->status(), 'printedBy' => $opt['printedBy'] ?? null,
            'from_warehouse' => trim(($t->fromWarehouse?->warehouse_code ?? '').' · '.($t->fromWarehouse?->warehouse_name ?? ''), ' ·'),
            'reason' => $t->reason ? self::t(InvTransfer::REASONS[$t->reason] ?? $t->reason) : null, 'carried_by' => $t->carried_by,
            'party' => ['name' => trim(($to?->warehouse_code ?? '').' · '.($to?->warehouse_name ?? ''), ' ·'), 'lines' => PrintDocs::address($to?->address)],
            'notes' => array_values(array_filter([
                $t->stat_cancel ? ['Cancel Reason', (string) $t->cancel_reason] : null,
                $t->notes ? ['Notes', $t->notes] : null,
            ])),
            'rows' => $t->lines->map(fn ($l) => ['name' => $l->item_name, 'sub' => $l->item_code.($l->was_dead_stock ? ' · DEAD' : ''), 'qty' => $l->qty, 'unit' => $l->uom_code,
                'from_bin' => $bins[$l->from_location_id] ?? null, 'to_bin' => $bins[$l->to_location_id] ?? null, 'lot_no' => $l->lot_no])->all(),
        ]);
    }
}
