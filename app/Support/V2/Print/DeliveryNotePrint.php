<?php

namespace App\Support\V2\Print;

use App\Models\Sdi\DoDelivery;
use Devlib\PrintDoc\Document;

// Surat Jalan (format A, user 2026-10-01): tanpa harga, tanpa total; kolom Berat (kg) dan Ikat. Sumber: tm2sdi.do_delivery
// + do_line (DO yang sudah Confirmed / Shipped). Kendaraan, Pengemudi, Diberangkatkan, Berat, dan Ikat belum punya kolom di
// database: tercetak kosong (garis isi tangan) sampai inputnya ada di form Delivery Order.
final class DeliveryNotePrint extends Document
{
    public string $type = 'delivery-note';

    public string $title = 'Delivery Note';

    public string $numberLabel = 'Delivery Note No.';

    public string $qrHint = 'online document & proof of receipt';

    public array $refs = [
        ['key' => 'customer_po_no', 'label' => 'Customer PO No.', 'hand' => true],
        ['key' => 'co_no', 'label' => 'CO Ref.'],
        ['key' => 'vehicle', 'label' => 'Vehicle', 'hand' => true],
        ['key' => 'driver', 'label' => 'Driver', 'hand' => true],
        ['key' => 'departed_at', 'label' => 'Departed', 'hand' => true],
    ];

    public string $partyLabel = 'Ship To';

    public ?array $side = ['key' => 'contact', 'label' => 'Phone / Attn', 'hand' => true];

    public string $nb = 'Complaints no later than 7 days after the goods are received';

    public array $extra = [
        ['key' => 'weight_kg', 'label' => 'Weight (kg)', 'width' => 21, 'type' => 'money'],
        ['key' => 'bundles', 'label' => 'Bundles', 'width' => 13, 'type' => 'int'],
        ['key' => 'remark', 'label' => 'Remarks', 'width' => 50],
    ];

    public array $receivers = [['Receiver', 'Customer'], ['Driver', '']];

    public static function load(int $id, array $opt = []): static
    {
        $do = DoDelivery::with(['customer', 'supplier', 'lines' => fn ($q) => $q->where('status', '<>', 'CANCELLED')->orderBy('line_no'),
            'lines.order:id_order,co_no,customer_po_no'])->findOrFail($id);
        $party = $do->customer ?? $do->supplier;
        $orders = $do->lines->pluck('order')->filter()->unique('id_order');

        return new self([
            'number' => $do->do_no, 'date' => $do->do_date?->toDateString(), 'status' => $do->status, 'printedBy' => $opt['printedBy'] ?? null,
            'customer_po_no' => $orders->pluck('customer_po_no')->filter()->unique()->implode(', '),
            'co_no' => $orders->pluck('co_no')->filter()->implode(', '),
            'contact' => implode(' · ', array_filter([$party?->contact_name, $party?->phone])),
            'party' => ['name' => $party?->customer_name ?? $party?->supplier_name ?? '', 'lines' => PrintDocs::address($do->ship_address_detail, $do->ship_address_label, $do->ship_postal_code)],
            'rows' => $do->lines->map(fn ($l) => ['name' => $l->item_description, 'qty' => (float) $l->qty_do, 'unit' => $l->qty_unit])->all(),
        ]);
    }
}
