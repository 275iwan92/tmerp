<?php

namespace App\Support\V2\Print;

use App\Models\Pur\PrRequisition;
use App\Support\V2\Data\SubconDelivery;
use Devlib\PrintDoc\Document;

// Surat Jalan Subkon (belum A; backlog02 §19.1): tata letak Surat Jalan (tanpa harga, tanpa total), penerima = supplier
// subkon, rujukan CO asal + PR + proses. Kendaraan, Pengemudi, Berat, dan Ikat belum punya kolom: garis isi tangan.
// Sumber: tm2sdi.do_delivery (do_type SUBCON, Confirmed) + do_line + tm2mst.sup_supplier + tm2pur.pr_requisition.
final class SubconDeliveryNotePrint extends Document
{
    public string $type = 'subcon-delivery-note';

    public string $title = 'Subcontract Delivery Note';

    public string $numberLabel = 'Delivery Note No.';

    public array $refs = [
        ['key' => 'process', 'label' => 'Process'],
        ['key' => 'co_no', 'label' => 'CO Ref.'],
        ['key' => 'pr_no', 'label' => 'PR Ref.'],
        ['key' => 'vehicle', 'label' => 'Vehicle', 'hand' => true],
        ['key' => 'driver', 'label' => 'Driver', 'hand' => true],
    ];

    public string $partyLabel = 'Ship To';

    public ?array $side = ['key' => 'contact', 'label' => 'Phone / Attn', 'hand' => true];

    public string $nb = 'The goods stay the property of the sender and are returned after the process, quoting this note number.';

    public array $extra = [
        ['key' => 'weight_kg', 'label' => 'Weight (kg)', 'width' => 21, 'type' => 'money'],
        ['key' => 'bundles', 'label' => 'Bundles', 'width' => 13, 'type' => 'int'],
        ['key' => 'remark', 'label' => 'Remarks', 'width' => 50],
    ];

    public array $receivers = [['Receiver', 'Subcontractor'], ['Driver', '']];

    public static function load(int $id, array $opt = []): static
    {
        $do = SubconDelivery::find($id) ?? abort(404);
        abort_unless(in_array($do->status, ['CONFIRMED', 'SHIPPED'], true), 404);
        $s = $do->supplier;

        return new self([
            'number' => $do->do_no, 'date' => $do->do_date?->toDateString(), 'status' => $do->status, 'printedBy' => $opt['printedBy'] ?? null,
            'process' => $do->subcon_process, 'co_no' => $do->lines->pluck('order.co_no')->filter()->unique()->implode(', '),
            'pr_no' => $do->pr_id ? PrRequisition::query()->whereKey($do->pr_id)->value('doc_no') : null,
            'contact' => implode(' · ', array_filter([$s?->contact_name, $s?->phone])),
            'party' => ['name' => $s?->supplier_name ?? '', 'lines' => PrintDocs::address($do->ship_address_detail, $do->ship_postal_code)],
            'notes' => $do->notes ? [['Notes', $do->notes]] : [],
            'rows' => $do->lines->map(fn ($l) => ['name' => $l->item_description, 'sub' => $l->order?->co_no, 'qty' => (float) $l->qty_do, 'unit' => $l->qty_unit])->all(),
        ]);
    }
}
