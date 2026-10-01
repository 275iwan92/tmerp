<?php

namespace App\Support\V2\Print;

use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\DoDeliveryPod;
use App\Services\Sales\DeliveryPromiseService;
use App\Support\V2\Data\ProofOfDelivery;
use Devlib\Locale;
use Devlib\PrintDoc\Document;

// Bukti Terima / Proof of Delivery (format A, user 2026-10-02): satu lembar per Surat Jalan, isi dari catatan kedatangan terakhir
// (tm2sdi.do_delivery_pod). Belum ada catatan = kolom hasil kosong untuk diisi tangan di tempat customer.
final class ProofOfDeliveryPrint extends Document
{
    public string $type = 'proof-of-delivery';

    public string $title = 'Proof of Delivery';

    public ?string $subtitle = 'POD';

    public string $numberLabel = 'Delivery Note No.';

    public array $refs = [
        ['key' => 'promise', 'label' => 'Delivery Promise'],
        ['key' => 'arrived_at', 'label' => 'Arrived at Customer', 'hand' => true],
        ['key' => 'result', 'label' => 'Result', 'hand' => true],
        ['key' => 'received_by', 'label' => 'Received by', 'hand' => true],
        ['key' => 'miss_cause', 'label' => 'Cause if Missed', 'hand' => true],
    ];

    public string $partyLabel = 'Customer';

    public ?array $side = ['key' => 'contact', 'label' => 'Phone / Attn', 'hand' => true];

    public string $nb = 'Result: RECEIVED / REFUSED / CUSTOMER CLOSED. The promise is kept when the goods arrive and are received at the promised time.';

    public array $extra = [
        ['key' => 'co_no', 'label' => 'CO No.', 'width' => 17],
        ['key' => 'received_qty', 'label' => 'Received', 'width' => 21, 'type' => 'money', 'hand' => true],
        ['key' => 'remark', 'label' => 'Condition / Notes', 'width' => 46, 'hand' => true],
    ];

    public array $receivers = [['Receiver', 'Customer'], ['Driver', '']];

    public static function load(int $id, array $opt = []): static
    {
        $do = DoDelivery::with(['customer', 'lines' => fn ($q) => $q->where('status', '<>', 'CANCELLED')->orderBy('line_no'), 'lines.order:id_order,co_no'])->findOrFail($id);
        $pod = DoDeliveryPod::where('delivery_id', $do->id_delivery)->orderBy('attempt_no')->get()->last();
        $c = $do->customer;
        $promise = $pod?->promise_date ?? $do->scheduled_ship_date ?? $do->do_date;

        return new self([
            'number' => $do->do_no, 'date' => ($pod?->arrived_at ?? $do->do_date)?->toDateString(), 'status' => strtoupper(ProofOfDelivery::state($pod)), 'printedBy' => $opt['printedBy'] ?? null,
            'promise' => trim((Locale::d($promise) ?? '').' '.($pod?->promise_time ? substr((string) $pod->promise_time, 0, 5) : '')),
            'arrived_at' => Locale::dt($pod?->arrived_at), 'result' => $pod ? (DeliveryPromiseService::RESULTS[$pod->result] ?? $pod->result) : null,
            'received_by' => $pod?->received_by, 'miss_cause' => $pod?->miss_cause ? (DeliveryPromiseService::CAUSES[$pod->miss_cause][0] ?? $pod->miss_cause) : null,
            'contact' => implode(' · ', array_filter([$c?->contact_name, $c?->phone])),
            'party' => ['name' => $c?->customer_name ?? '', 'lines' => PrintDocs::address($do->ship_address_detail, $do->ship_address_label, $do->ship_postal_code)],
            'notes' => $pod?->notes ? [['Notes', $pod->notes]] : [],
            'rows' => $do->lines->map(fn ($l) => ['name' => $l->item_description, 'qty' => (float) $l->qty_do, 'unit' => $l->qty_unit, 'co_no' => $l->order?->co_no])->all(),
        ]);
    }
}
