<?php

namespace App\Support\V2\Print;

use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderDelivery;
use Devlib\Locale;
use Devlib\PrintDoc\Document;

// Customer Plan Delivery (format A, user 2026-10-01): rencana kirim satu CO, TANPA harga, satu baris per kiriman.
// Produk yang dipecah (split) ditandai tiga kali: garis tebal di kiri baris, kolom Kiriman "SPLIT 1/3", dan ringkasan
// "Split" di bawah tabel. Penanda tangan: Customer (terakhir), Operations Manager, PPIC, Sales (pembuat).
// Sumber: tm2sdi.co_order_delivery (ship_date / ship_time = janji ke customer, original_date = due date awal CO).
final class CpdelPrint extends Document
{
    public string $type = 'cpdel';

    public string $title = 'Customer Plan Delivery';

    public ?string $subtitle = 'Delivery Plan · No Prices';

    public string $numberLabel = 'CO No.';

    public array $refs = [
        ['key' => 'customer', 'label' => 'Customer'],
        ['key' => 'customer_po_no', 'label' => 'Customer PO No.'],
        ['key' => 'co_date', 'label' => 'CO Date', 'type' => 'date'],
    ];

    public string $partyLabel = 'Ship To';

    public ?array $side = ['key' => 'contact', 'label' => 'Phone / Attn'];

    public string $nb = 'SPLIT = one product shipped in stages (thick bar on the left). QTY is the quantity per shipment.';

    public string $nameLabel = 'Product Name';

    public array $extra = [
        ['key' => 'part', 'label' => 'Shipment', 'width' => 17],
        ['key' => 'ship_date', 'label' => 'Ship Date', 'width' => 21, 'type' => 'date'],
        ['key' => 'ship_time', 'label' => 'Time', 'width' => 11],
        ['key' => 'due_date', 'label' => 'CO Due', 'width' => 21, 'type' => 'date'],
        ['key' => 'state', 'label' => 'Status', 'width' => 14],
    ];

    public array $receivers = [['Customer', 'Agrees to the schedule']];

    public array $signLabels = ['approve' => 'Operations Manager', 'know' => 'PPIC', 'make' => 'Sales'];

    public array $signNotes = ['approve' => 'Approved By', 'know' => 'Approved By', 'make' => 'Prepared By'];

    // $id = id Customer Order (satu lembar rencana per CO).
    public static function load(int $id, array $opt = []): static
    {
        $o = CoOrder::with('customer')->findOrFail($id);
        $rows = CoOrderDelivery::with('item')->where('order_id', $id)->orderBy('item_id')->orderBy('ship_date')->orderBy('id_delivery')->get();
        $c = $o->customer;

        return new self([
            'number' => $o->co_no, 'date' => now()->toDateString(), 'status' => $o->doc_status, 'printedBy' => $opt['printedBy'] ?? null,
            'customer' => $c?->customer_name, 'customer_po_no' => $o->customer_po_no, 'co_date' => $o->co_date?->toDateString(),
            'contact' => implode(' · ', array_filter([$c?->contact_name, $c?->phone])),
            'party' => ['name' => $c?->customer_name ?? '', 'lines' => PrintDocs::address($o->ship_address_detail, $o->ship_address_label, $o->ship_postal_code)],
            'rows' => $rows->map(fn (CoOrderDelivery $d) => ['item' => $d->item_id, 'name' => $d->item?->description, 'qty' => (float) $d->ship_qty, 'unit' => $d->item?->qty_unit,
                'ship_date' => $d->ship_date?->toDateString(), 'ship_time' => $d->ship_time ? substr((string) $d->ship_time, 0, 5) : null,
                'due_date' => ($d->original_date ?? $d->item?->due_date)?->toDateString(),
                'state' => $d->posted_at && $d->status === 'PLANNED' ? 'Posted' : ucfirst(strtolower((string) $d->status))])->all(),
        ]);
    }

    // Baris satu produk (item CO) yang lebih dari satu = split: beri tanda + ringkasan pembagiannya.
    protected function prepare(array $rows): array
    {
        $groups = [];
        foreach ($rows as $i => $r) {
            $groups[$r['item']][] = $i;
        }
        foreach ($groups as $idx) {
            $n = count($idx);
            foreach ($idx as $k => $i) {
                $rows[$i] += $n > 1 ? ['cls' => 'is-split', 'part' => 'SPLIT '.($k + 1).'/'.$n] : ['part' => self::t('Full')];
            }
            if ($n > 1) {
                $first = $rows[$idx[0]];
                $qty = array_map(fn ($i) => (float) $rows[$i]['qty'], $idx);
                $this->data['notes'][] = ['Split', $first['name'].' — '.Locale::number(array_sum($qty), 2).' '.$first['unit'].' = '.implode(' + ', array_map(fn ($q) => Locale::number($q, 2), $qty))];
            }
        }

        return $rows;
    }
}
