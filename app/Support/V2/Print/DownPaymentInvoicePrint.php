<?php

namespace App\Support\V2\Print;

use App\Models\Acc\FinInvoice;
use App\Models\Master\CustCustomer;
use App\Models\Master\FinPaymentTerm;
use App\Models\Sdi\CoOrder;
use App\Services\Acc\CustomerInvoiceService;
use Devlib\Locale;
use Devlib\PrintDoc\Document;

// Tagihan Uang Muka (belum A; user 2026-10-02): permintaan pembayaran di muka atas satu Customer Order. Bukan piutang dan
// belum menjurnal -- bukti uangnya diterima = Tanda Terima Uang Muka (DownPaymentReceiptPrint).
// Sumber: tm2acc.fin_invoice (CUSTOMER_DP) + fin_company_bank, tm2mst.cust_customer, tm2sdi.co_order.
final class DownPaymentInvoicePrint extends Document
{
    public string $type = 'dp-invoice';

    public string $title = 'Down Payment Invoice';

    public string $numberLabel = 'Invoice No.';

    public array $refs = [
        ['key' => 'co_no', 'label' => 'CO Ref.'],
        ['key' => 'customer_po_no', 'label' => 'Customer PO No.'],
        ['key' => 'payment_term', 'label' => 'Payment Term'],
        ['key' => 'due', 'label' => 'Due Date', 'type' => 'date'],
        ['key' => 'order_value', 'label' => 'Order Value', 'type' => 'money'],
    ];

    public string $partyLabel = 'Bill To';

    public ?array $side = ['key' => 'contact', 'label' => 'Phone / Attn'];

    public bool $nbPlain = true;

    public bool $nbRaw = true;

    public string $nameLabel = 'Description';

    public array $extra = [['key' => 'co_ref', 'label' => 'CO No.', 'width' => 21], ['key' => 'percent', 'label' => 'Percent', 'width' => 17]];

    public bool $priced = true;

    public array $receivers = [['Customer', 'Receives the invoice']];

    public static function load(int $id, array $opt = []): static
    {
        $inv = FinInvoice::query()->with('companyBank')->where('invoice_type', CustomerInvoiceService::DOWN_PAYMENT)->findOrFail($id);
        $cust = CustCustomer::withoutGlobalScopes()->find($inv->partner_id);
        $co = $inv->co_id ? CoOrder::query()->find($inv->co_id) : null;
        $sub = (float) $inv->subtotal;
        $tax = (float) $inv->tax_amount;
        $lines = [['Subtotal', $sub]];
        if ($tax > 0) {
            $lines[] = ['VAT', $tax, 'suffix' => ' '.Locale::decimal($inv->tax_rate, 2).'%'];
        }

        $doc = new self([
            'number' => $inv->invoice_number ?: 'Draft #'.$inv->invoice_id, 'date' => $inv->invoice_date?->toDateString(), 'status' => CustomerInvoicePrint::status($inv),
            'printedBy' => $opt['printedBy'] ?? null, 'ppn' => $inv->is_ppn ? (float) $inv->tax_rate : 0, 'sums' => ['lines' => $lines, 'grand' => round((float) $inv->total_amount, 2)],
            'co_no' => $co?->co_no, 'customer_po_no' => $inv->customer_po_no,
            'payment_term' => FinPaymentTerm::query()->where('payment_term_code', (string) $inv->payment_term_code)->value('term_label') ?: $inv->payment_term_code,
            'due' => $inv->due_date?->toDateString(), 'order_value' => $inv->order_amount, 'notes' => $inv->notes ? [['Notes', $inv->notes]] : [],
            'contact' => implode(' · ', array_filter([$cust?->contact_name, $cust?->phone])),
            'party' => ['name' => $inv->bill_to_name ?: ($cust?->customer_name ?? ''), 'code' => $cust?->customer_code,
                'lines' => PrintDocs::address($inv->bill_to_address ?: $cust?->default_address)],
            'rows' => [['name' => $inv->description ?: self::t('Down payment'), 'qty' => 1, 'unit' => 'LS', 'co_ref' => $co?->co_no,
                'percent' => $inv->dp_percent !== null ? Locale::decimal($inv->dp_percent, 2).'%' : null, 'price' => $sub, 'subtotal' => $sub]],
        ]);
        $doc->nb = $inv->companyBank ? self::t('Please transfer the down payment to').' '.$inv->companyBank->printLabel() : '';

        return $doc;
    }
}
