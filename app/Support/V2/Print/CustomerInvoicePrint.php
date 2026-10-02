<?php

namespace App\Support\V2\Print;

use App\Models\Acc\FinDpAllocation;
use App\Models\Acc\FinInvoice;
use App\Models\Master\CustCustomer;
use App\Models\Master\FinPaymentTerm;
use App\Models\Sdi\CoOrder;
use App\Services\Acc\CustomerInvoiceService;
use Devlib\Locale;
use Devlib\PrintDoc\Document;

// Customer Invoice (belum A; user 2026-10-02, docs/md_for_systems/customerinvoice_accounting.md): baris = barang yang
// diterima customer per Surat Jalan. Nilai invoice selalu penuh; uang muka tampil sebagai potongan dan yang ditagih = sisanya.
// Order PPN: kop badan hukum + NPWP, dasar pajak dan nomor Faktur Pajak di catatan. Rekening transfer = rekening perusahaan
// yang dipilih di invoice. Angka = yang tersimpan (subtotal / tax_amount / total_amount / dp_applied_amount), tidak dihitung ulang.
// Sumber: tm2acc.fin_invoice + fin_invoice_line + fin_dp_allocation + fin_company_bank, tm2mst.cust_customer, tm2sdi.co_order.
final class CustomerInvoicePrint extends Document
{
    public string $type = 'customer-invoice';

    public string $title = 'Customer Invoice';

    public ?string $subtitle = 'Faktur Tagihan';

    public string $numberLabel = 'Invoice No.';

    public array $refs = [
        ['key' => 'co_no', 'label' => 'CO Ref.'],
        ['key' => 'customer_po_no', 'label' => 'Customer PO No.'],
        ['key' => 'payment_term', 'label' => 'Payment Term'],
        ['key' => 'due', 'label' => 'Due Date', 'type' => 'date'],
        ['key' => 'note', 'label' => 'Notes'],
    ];

    public string $partyLabel = 'Bill To';

    public ?array $side = ['key' => 'tax_id', 'label' => 'Tax ID (NPWP)'];

    public bool $nbPlain = true;

    public bool $nbRaw = true;

    public string $nameLabel = 'Product Name';

    public array $extra = [['key' => 'cfg', 'label' => 'CFG', 'width' => 9], ['key' => 'type', 'label' => 'Type', 'width' => 9],
        ['key' => 'dn', 'label' => 'Delivery Note No.', 'width' => 20, 'wrap' => true]];

    public bool $priced = true;

    public array $receivers = [['Customer', 'Receives the invoice']];

    public float $rowHeight = 7.1;

    public float $tailHeight = 21;

    public static function load(int $id, array $opt = []): static
    {
        $inv = FinInvoice::query()->with(['lines', 'companyBank'])->where('invoice_type', CustomerInvoiceService::INVOICE)->findOrFail($id);
        $cust = CustCustomer::withoutGlobalScopes()->find($inv->partner_id);
        $t = app(CustomerInvoiceService::class)->totals($inv);
        $dps = FinDpAllocation::query()->with('dpInvoice')->where('final_invoice_id', $inv->invoice_id)->orderBy('allocation_id')->get()
            ->map(fn (FinDpAllocation $a) => ['no' => $a->dpInvoice?->invoice_number, 'date' => $a->dpInvoice?->invoice_date?->toDateString(), 'amount' => round((float) $a->allocated_base + (float) $a->allocated_tax, 2)]);
        if ($inv->doc_status === 'DRAFT') {
            $plan = FinInvoice::withoutGlobalScopes()->whereKey(array_keys((array) $inv->dp_plan))->get()->keyBy('invoice_id');
            $dps = collect((array) $inv->dp_plan)->map(fn ($amount, $dpId) => ['no' => $plan->get((int) $dpId)?->invoice_number, 'date' => $plan->get((int) $dpId)?->invoice_date?->toDateString(), 'amount' => (float) $amount])->values();
        }
        $lines = [['Subtotal', $t['subtotal']]];
        if ($t['discount'] > 0) {
            $lines[] = ['Discount', $t['discount'], 'minus' => true, 'suffix' => ' '.Locale::decimal($inv->discount_pct, 2).'%'];
        }
        if ($t['charge'] > 0) {
            $lines[] = [$inv->charge_note ?: 'Other Charges', $t['charge']];
        }
        if ($t['tax'] > 0) {
            $lines[] = ['VAT', $t['tax'], 'suffix' => ' '.Locale::decimal($inv->tax_rate, 2).'%'];
        }
        if ($dps->isNotEmpty() && count($lines) > 1) {
            $lines[] = ['Total', $t['total']];
        }
        foreach ($dps as $dp) {
            $lines[] = ['Down Payment', $dp['amount'], 'minus' => true, 'suffix' => $dp['date'] ? ' '.Locale::d($dp['date']) : ''];
        }
        $notes = [];
        // PMK 131/2024: tarif 12% atas dasar pengenaan nilai lain (11/12 harga jual) = efektif 11%.
        if ($t['tax'] > 0 && abs((float) $inv->tax_rate - 11) < 0.001) {
            $notes[] = ['Tax Base (DPP Nilai Lain)', Locale::number(round($t['dpp'] * 11 / 12, 2), 2).' · 12%'];
        }
        if ($inv->is_ppn) {
            $notes[] = ['Tax Invoice No.', $inv->tax_invoice_no ?: '—'];
        }
        // Uang muka yang dipotong disebut nomornya supaya terlacak ke tagihan uang muka dan Customer Order-nya.
        if ($dps->isNotEmpty()) {
            $notes[] = ['Down Payment Ref.', $dps->pluck('no')->filter()->implode(', ') ?: '—'];
        }

        $doc = new self([
            'number' => $inv->invoice_number ?: 'Draft #'.$inv->invoice_id, 'date' => $inv->invoice_date?->toDateString(), 'status' => self::status($inv),
            'printedBy' => $opt['printedBy'] ?? null, 'ppn' => $inv->is_ppn ? (float) $inv->tax_rate : 0,
            'sums' => ['lines' => $lines, 'grand' => round($t['total'] - $t['dp'], 2), 'balance' => $dps->isNotEmpty()],
            'co_no' => $inv->co_id ? CoOrder::query()->whereKey($inv->co_id)->value('co_no') : null, 'customer_po_no' => $inv->customer_po_no,
            'payment_term' => FinPaymentTerm::query()->where('payment_term_code', (string) $inv->payment_term_code)->value('term_label') ?: $inv->payment_term_code,
            'due' => $inv->due_date?->toDateString(), 'note' => $inv->notes, 'tax_id' => $cust?->tax_code, 'notes' => $notes,
            'party' => ['name' => $inv->bill_to_name ?: ($cust?->customer_name ?? ''), 'lines' => PrintDocs::address($inv->bill_to_address ?: $cust?->default_address)],
            // Invoice lama tanpa baris (dibuat sebelum ada baris Surat Jalan): satu baris = uraian tagihan dengan nilai DPP-nya.
            'rows' => $inv->lines->isEmpty()
                ? [['name' => $inv->description ?: self::t('Customer Invoice'), 'price' => $t['subtotal'], 'subtotal' => $t['subtotal']]]
                : $inv->lines->map(fn ($l) => ['name' => $l->description, 'qty' => (float) $l->qty_invoiced, 'unit' => $l->qty_unit, 'cfg' => $l->color_config,
                    'type' => $l->order_type, 'dn' => $l->delivery_no, 'price' => (float) $l->unit_price, 'subtotal' => (float) $l->line_total])->all(),
        ]);
        $doc->nb = $inv->companyBank ? self::t('Please transfer the payment to').' '.$inv->companyBank->printLabel() : '';

        return $doc;
    }

    public static function status(FinInvoice $inv): string
    {
        return match (true) {
            $inv->doc_status === 'CANCELLED' => 'CANCELLED',
            $inv->doc_status !== 'POSTED' => 'DRAFT',
            $inv->payment_status === 'PAID' => 'PAID',
            default => 'OPEN',
        };
    }

    // Ada potongan uang muka: baris tebal = "Balance Due" (sisa yang harus dibayar), bukan "Total".
    public function totals(): array
    {
        $t = parent::totals();
        if (! empty($this->data['sums']['balance'])) {
            $t['lines'][array_key_last($t['lines'])][0] = self::t('Balance Due');
        }

        return $t;
    }
}
