<?php

namespace App\Support\V2\Print;

use App\Models\Inventory\RmBooking;
use App\Models\Sdi\CoOrder;
use App\Support\V2\Data\MaterialPlanning;
use Devlib\Locale;
use Devlib\PrintDoc\Document;

// Material Booking (belum A): kebutuhan bahan satu Customer Order, tanpa harga. Baris = hasil MaterialPlanningEngine yang
// sama dengan layar (App\Support\V2\Data\MaterialPlanning::lineRows). $id = id Customer Order.
final class MaterialBookingPrint extends Document
{
    public string $type = 'material-booking';

    public string $title = 'Material Booking';

    public ?string $subtitle = 'Material Reservation · Internal';

    public string $numberLabel = 'Booking No.';

    public array $refs = [
        ['key' => 'co_no', 'label' => 'CO No.'],
        ['key' => 'co_date', 'label' => 'CO Date', 'type' => 'date'],
        ['key' => 'calculated_at', 'label' => 'Calculated'],
        ['key' => 'confirmed_by', 'label' => 'Confirmed By'],
    ];

    public string $partyLabel = 'Customer';

    public string $nb = 'Material needs of one Customer Order. SHORT = not yet covered by stock, PO, or PR.';

    public string $nameLabel = 'Material / Product';

    public array $extra = [
        ['key' => 'need_date', 'label' => 'Needed Date', 'width' => 21, 'type' => 'date'],
        ['key' => 'covered_by', 'label' => 'Covered By', 'width' => 28, 'wrap' => true],
        ['key' => 'short', 'label' => 'Short', 'width' => 21, 'type' => 'money'],
        ['key' => 'coverage', 'label' => 'Status', 'width' => 14],
    ];

    public array $receivers = [['Warehouse', 'Prepares the material']];

    public static function load(int $id, array $opt = []): static
    {
        $co = CoOrder::with('customer')->findOrFail($id);
        $b = RmBooking::where('co_id', $co->id_order)->where('stat_cancel', 0)->first();

        return new self([
            'number' => $b?->booking_no ?? '—', 'date' => now()->toDateString(), 'status' => $b?->doc_status ?? 'NOT BOOKED', 'printedBy' => $opt['printedBy'] ?? null,
            'co_no' => $co->co_no, 'co_date' => $co->co_date?->toDateString(), 'calculated_at' => Locale::dt($b?->last_run_at), 'confirmed_by' => PrintDocs::person($b?->confirm_by),
            'party' => ['name' => $co->customer?->customer_name ?? '', 'lines' => []],
            'rows' => array_map(fn ($l) => ['name' => $l['material'], 'sub' => trim($l['product'].($l['part'] !== '—' ? ' · '.$l['part'] : '')), 'qty' => $l['qty'], 'unit' => $l['uom'],
                'need_date' => $l['need'], 'covered_by' => $l['covered'], 'short' => $l['short'] > 0 ? $l['short'] : null, 'coverage' => $l['status']], MaterialPlanning::lineRows((int) $co->id_order)),
        ]);
    }
}
