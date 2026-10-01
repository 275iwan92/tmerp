<?php

namespace App\Support\V2\Print;

use App\Models\Acc\FinInvoice;
use App\Models\Master\CustCustomer;
use App\Models\Sdi\CoOrder;
use Devlib\Locale;
use Devlib\PrintDoc\Document;

// Sales Invoice / Down Payment Invoice (belum A; backlog01 UI-19): tagihan ke customer. Kop PPN (badan hukum + NPWP) bila
// tagihan kena PPN. fin_invoice tidak punya baris barang -- satu baris = uraian tagihan dengan nilai DPP; angka pajak dan
// total = yang tersimpan (subtotal / tax_amount / pph_amount / total_amount), tidak dihitung ulang.
// Sumber: tm2acc.fin_invoice (CUSTOMER_INVOICE / CUSTOMER_DP) + tm2mst.cust_customer + tm2sdi.co_order (query terpisah).
final class SalesInvoicePrint extends Document
{
    private const TYPES = ['CUSTOMER_INVOICE' => 'Sales Invoice', 'CUSTOMER_DP' => 'Down Payment Invoice'];

    private const PAYMENT = ['UNPAID' => 'Unpaid', 'PARTIAL' => 'Partially paid', 'PAID' => 'Paid'];

    public string $type = 'sales-invoice';

    public string $title = 'Sales Invoice';

    public string $numberLabel = 'Invoice No.';

    public array $refs = [
        ['key' => 'co_no', 'label' => 'CO Ref.'],
        ['key' => 'due', 'label' => 'Due Date', 'type' => 'date'],
        ['key' => 'tax_invoice_no', 'label' => 'Tax Invoice No.'],
        ['key' => 'payment', 'label' => 'Payment'],
        ['key' => 'paid', 'label' => 'Paid', 'type' => 'money'],
    ];

    public string $partyLabel = 'Bill To';

    public ?array $side = ['key' => 'tax_id', 'label' => 'Tax ID (NPWP)'];

    public string $nb = 'Please pay the total below no later than the due date and quote the invoice number on the transfer.';

    public string $nameLabel = 'Description';

    public bool $qtyCols = false;

    public array $extra = [['key' => 'remark', 'label' => 'Remarks', 'width' => 74, 'wrap' => true]];

    public bool $priced = true;

    public array $receivers = [['Customer', 'Received by']];

    public static function load(int $id, array $opt = []): static
    {
        $inv = FinInvoice::query()->whereIn('invoice_type', array_keys(self::TYPES))->findOrFail($id);
        $cust = CustCustomer::withoutGlobalScopes()->find($inv->partner_id);
        $sub = (float) $inv->subtotal;
        $tax = (float) $inv->tax_amount;
        $pph = (float) $inv->pph_amount;
        $lines = [['Subtotal', $sub]];
        if ($tax > 0) {
            $lines[] = ['VAT', $tax, 'suffix' => $sub > 0 ? ' '.Locale::decimal($tax / $sub * 100, 2).'%' : ''];
        }
        if ($pph > 0) {
            $lines[] = ['Income Tax 23', $pph, 'minus' => true];
        }

        $doc = new self([
            'number' => $inv->invoice_number ?: 'Draft #'.$inv->invoice_id, 'date' => $inv->invoice_date?->toDateString(), 'status' => $inv->doc_status,
            'printedBy' => $opt['printedBy'] ?? null, 'ppn' => $sub > 0 ? round($tax / $sub * 100, 2) : 0,
            'sums' => ['lines' => $lines, 'grand' => round((float) $inv->total_amount - $pph, 2)],
            'co_no' => $inv->co_id ? CoOrder::query()->whereKey($inv->co_id)->value('co_no') : null, 'due' => $inv->due_date?->toDateString(),
            'tax_invoice_no' => $inv->tax_invoice_no, 'payment' => self::t(self::PAYMENT[$inv->payment_status] ?? $inv->payment_status),
            'paid' => (float) $inv->paid_amount > 0 ? (float) $inv->paid_amount : null, 'tax_id' => $cust?->tax_code,
            'party' => ['name' => $cust?->customer_name ?? '', 'lines' => PrintDocs::address($cust?->default_address)],
            'rows' => [['name' => $inv->description ?: self::t(self::TYPES[$inv->invoice_type]), 'remark' => $inv->partner_ref, 'price' => $sub, 'subtotal' => $sub]],
        ]);
        $doc->title = self::TYPES[$inv->invoice_type];

        return $doc;
    }
}
