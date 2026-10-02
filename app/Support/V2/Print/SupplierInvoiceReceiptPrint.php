<?php

namespace App\Support\V2\Print;

use App\Models\Acc\FinInvoiceReceipt;
use App\Support\V2\Data\SupplierInvoiceReceipt;
use Devlib\PrintDoc\Document;

// Tanda Terima Tagihan Supplier (belum A; user 2026-10-02): lembar yang dibawa pulang supplier sebagai bukti invoicenya
// sudah diterima. Satu baris = satu invoice; total = jumlah nilai invoice (tidak dihitung pajaknya lagi). Bukan bukti bayar.
// Kop PPN bila ada invoice berfaktur pajak. Tanpa slot "Approved By": cukup yang menyerahkan, mengetahui, dan menerima.
// Sumber: tm2acc.fin_invoice_receipt + fin_invoice_receipt_line + tm2mst.sup_supplier.
final class SupplierInvoiceReceiptPrint extends Document
{
    public string $type = 'supplier-invoice-receipt';

    public string $title = 'Supplier Invoice Receipt';

    public bool $titleCompact = true;

    public string $numberLabel = 'Invoice Receipt No.';

    public array $refs = [
        ['key' => 'payment_term', 'label' => 'Payment Terms'],
        ['key' => 'planned', 'label' => 'Planned Payment', 'type' => 'date'],
        ['key' => 'method', 'label' => 'Payment Method'],
    ];

    public string $partyLabel = 'Received From';

    public ?array $side = ['key' => 'handed_by', 'label' => 'Handed Over By', 'hand' => true];

    public string $nb = 'This receipt is not proof of payment. Payment follows the planned date after the invoices are matched with the PO and the goods receipt.';

    public string $nameLabel = 'Supplier Invoice No.';

    public bool $qtyCols = false;

    public array $extra = [
        ['key' => 'invoice_date', 'label' => 'Invoice Date', 'width' => 21, 'type' => 'date'],
        ['key' => 'po_no', 'label' => 'PO No.', 'width' => 23],
        ['key' => 'tax_invoice_no', 'label' => 'Tax Invoice No.', 'width' => 30],
    ];

    public bool $priced = true;

    public array $receivers = [['Supplier', 'Hands over the invoices']];

    public array $signLabels = ['approve' => '', 'know' => 'Acknowledged By', 'make' => 'Receiver'];

    public array $signNotes = ['approve' => '', 'know' => '', 'make' => 'Finance'];

    protected function priceColumns(): array
    {
        return [['key' => 'amount', 'label' => 'Invoice Amount', 'width' => 46, 'type' => 'money']];
    }

    public static function load(int $id, array $opt = []): static
    {
        $r = SupplierInvoiceReceipt::find($id) ?? abort(404);
        abort_if($r->status() === 'DRAFT', 404);
        $s = $r->supplier;
        $attached = array_keys(array_filter(FinInvoiceReceipt::ATTACHMENTS, fn (string $f) => (bool) $r->{$f}, ARRAY_FILTER_USE_KEY));

        return new self([
            'number' => $r->receipt_no, 'date' => $r->receipt_date?->toDateString(), 'status' => $r->status(), 'printedBy' => $opt['printedBy'] ?? null,
            'ppn' => $r->lines->contains(fn ($l) => trim((string) $l->tax_invoice_no) !== '') ? 1 : 0,
            'sums' => ['lines' => [], 'grand' => $r->total()],
            'payment_term' => $r->payment_term, 'planned' => $r->planned_payment_date?->toDateString(),
            'method' => $r->payment_method ? self::t(FinInvoiceReceipt::METHODS[$r->payment_method] ?? $r->payment_method) : null,
            'handed_by' => $r->handed_by, 'email' => $s?->email,
            'party' => ['name' => $s?->supplier_name ?? '', 'lines' => PrintDocs::address($s?->default_address, $s?->postal_code)],
            'notes' => array_values(array_filter([
                $attached ? ['Attachments', implode(' · ', array_map(fn (string $f) => self::t(FinInvoiceReceipt::ATTACHMENTS[$f]), $attached))] : null,
                $r->stat_cancel ? ['Cancel Reason', (string) $r->cancel_reason] : null,
                $r->notes ? ['Notes', $r->notes] : null,
            ])),
            'rows' => $r->lines->map(fn ($l) => ['name' => $l->supplier_invoice_no, 'invoice_date' => $l->invoice_date?->toDateString(), 'po_no' => $l->po_no,
                'tax_invoice_no' => $l->tax_invoice_no, 'amount' => (float) $l->amount])->all(),
        ]);
    }
}
