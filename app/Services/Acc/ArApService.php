<?php

namespace App\Services\Acc;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinInvoice;
use App\Models\Acc\FinPartnerAccount;
use App\Models\Acc\FinPaymentAlloc;
use App\Models\Acc\FinVoucher;
use App\Models\Master\CustCustomer;
use App\Models\Master\FinTaxScheme;
use App\Models\Master\SupSupplier;
use Illuminate\Support\Facades\DB;

// Subledger AR/AP -- Order to Cash & Procure to Pay dari blueprint:
//   Invoice  : Dr Piutang / Cr Pendapatan / Cr PPN Keluaran
//   Tagihan  : Dr Beban-Persediaan + Dr PPN Masukan / Cr Hutang (+ Cr PPh 23)
//   Terima   : Dr Bank / Cr Piutang, dialokasikan ke faktur (tak boleh melebihi sisa)
//   Bayar    : Dr Hutang / Cr Bank
// Nilai PPN/PPh dihitung dari tm2mst.fin_tax_scheme, bukan angka hardcode.
class ArApService
{
    public function __construct(private JournalPostingService $journal, private FinPeriodGuard $guard) {}

    public function taxRate(string $code): float
    {
        return FinTaxScheme::find($code)?->effectiveRate() ?? 0.0;
    }

    public function accountId(string $code): int
    {
        return (int) FinAccount::withoutGlobalScopes()->where('account_code', $code)->value('account_id');
    }

    public function arAccountFor(CustCustomer $c): int
    {
        $mapped = FinPartnerAccount::withoutGlobalScopes()->where('partner_type', 'CUSTOMER')->where('partner_id', $c->id_customer)->value('ar_account_id');

        return (int) ($mapped ?: $this->accountId($c->customer_type === 'import' ? '112200' : '112100'));
    }

    public function apAccountFor(SupSupplier $s): int
    {
        $mapped = FinPartnerAccount::withoutGlobalScopes()->where('partner_type', 'SUPPLIER')->where('partner_id', $s->id_supplier)->value('ap_account_id');
        if ($mapped) {
            return (int) $mapped;
        }
        if ($s->is_subcon) {
            return $this->accountId('211300');
        }

        return $this->accountId(str_contains(strtolower((string) $s->category), 'tinta') || str_contains(strtolower((string) $s->category), 'chemical') ? '211200' : '211100');
    }

    public function nextInvoiceNumber(string $kind, string $date): string
    {
        $prefix = $kind === 'CUSTOMER_INVOICE' ? 'INV-AR-' : 'BILL-AP-';
        $last = FinInvoice::withoutGlobalScopes()->where('invoice_number', 'like', $prefix.'%')->orderByDesc('invoice_number')->value('invoice_number');

        return $prefix.str_pad((string) (($last ? (int) substr($last, strlen($prefix)) : 0) + 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * Faktur penjualan (AR). $data: customer_id, invoice_date, due_date, subtotal,
     * ppn (bool), description, co_id?, revenue_account_id, tax_invoice_no?
     */
    public function createCustomerInvoice(array $data, string $user, bool $post = true): FinInvoice
    {
        $customer = CustCustomer::withoutGlobalScopes()->findOrFail($data['customer_id']);
        $subtotal = round((float) $data['subtotal'], 2);
        abort_if($subtotal <= 0, 422, t('The invoice amount must be more than zero.'));
        $tax = ! empty($data['ppn']) ? round($subtotal * $this->taxRate('PPN_OUT'), 2) : 0.0;

        // Kontrol duplikat (blueprint validasi §2): customer + nilai + tanggal sama.
        $dupe = FinInvoice::withoutGlobalScopes()->where('invoice_type', 'CUSTOMER_INVOICE')
            ->where('partner_id', $customer->id_customer)->where('invoice_date', $data['invoice_date'])
            ->where('total_amount', $subtotal + $tax)->where('doc_status', '<>', 'CANCELLED')->first();
        abort_if((bool) $dupe, 422, tf('A similar invoice already exists: :no (duplicate control).', ['no' => $dupe?->invoice_number]));

        $invoice = FinInvoice::create([
            'invoice_number' => $this->nextInvoiceNumber('CUSTOMER_INVOICE', $data['invoice_date']),
            'invoice_type' => 'CUSTOMER_INVOICE', 'partner_type' => 'CUSTOMER', 'partner_id' => $customer->id_customer,
            'source_type' => $data['source_type'] ?? 'MANUAL', 'source_id' => $data['source_id'] ?? null,
            'co_id' => $data['co_id'] ?? null, 'description' => $data['description'] ?? null,
            'invoice_date' => $data['invoice_date'], 'due_date' => $data['due_date'],
            'subtotal' => $subtotal, 'tax_amount' => $tax, 'total_amount' => $subtotal + $tax,
            'tax_invoice_no' => $data['tax_invoice_no'] ?? null, 'tax_validated' => ! empty($data['tax_invoice_no']),
            'revenue_account_id' => $data['revenue_account_id'] ?? $this->accountId('411000'),
            'payment_status' => 'UNPAID', 'doc_status' => 'DRAFT',
            'data_origin' => $data['data_origin'] ?? 'LIVE',
            'created_by' => $user, 'updated_by' => $user,
        ]);

        return $post ? $this->postCustomerInvoice($invoice, $user) : $invoice;
    }

    public function postCustomerInvoice(FinInvoice $invoice, string $user): FinInvoice
    {
        abort_unless($invoice->doc_status !== 'POSTED', 422, t('This invoice is already posted.'));
        $customer = CustCustomer::withoutGlobalScopes()->findOrFail($invoice->partner_id);

        $lines = [[
            'account_id' => $this->arAccountFor($customer), 'debit' => $invoice->total_amount,
            'partner_type' => 'CUSTOMER', 'partner_id' => $customer->id_customer,
            'narration' => 'Piutang '.$invoice->invoice_number,
        ], [
            'account_id' => $invoice->revenue_account_id ?: $this->accountId('411000'),
            'credit' => $invoice->subtotal, 'co_id' => $invoice->co_id,
            'narration' => $invoice->description ?: 'Penjualan',
        ]];
        if ((float) $invoice->tax_amount > 0) {
            $lines[] = ['account_id' => $this->accountId('212100'), 'credit' => $invoice->tax_amount, 'narration' => 'PPN Keluaran'];
        }

        $voucher = $this->journal->post('SAL', 'CUSTOMER_INVOICE', $invoice->invoice_id,
            'Faktur '.$invoice->invoice_number.' — '.$customer->customer_name, $lines, $user,
            $invoice->invoice_date->toDateString(), [
                'journal_kind' => 'SYSTEM', 'reference_no' => $invoice->invoice_number,
                'partner_name' => $customer->customer_name, 'ppn_type' => (float) $invoice->tax_amount > 0 ? 'PPN_OUT' : null,
                'efaktur_no' => $invoice->tax_invoice_no, 'data_origin' => $invoice->data_origin,
            ]);

        $invoice->update(['voucher_id' => $voucher->voucher_id, 'doc_status' => 'POSTED', 'updated_by' => $user]);

        return $invoice->refresh();
    }

    /**
     * Tagihan supplier (AP). $data: supplier_id, partner_ref, invoice_date, due_date,
     * subtotal, ppn (bool), pph_code (PPH23|null), expense_account_id, description,
     * source_type/source_id (PV), tax_invoice_no.
     */
    public function createVendorBill(array $data, string $user, bool $post = true): FinInvoice
    {
        $supplier = SupSupplier::withoutGlobalScopes()->findOrFail($data['supplier_id']);
        $subtotal = round((float) $data['subtotal'], 2);
        abort_if($subtotal <= 0, 422, t('The bill amount must be more than zero.'));
        $tax = ! empty($data['ppn']) ? round($subtotal * $this->taxRate('PPN_IN'), 2) : 0.0;
        $pph = ! empty($data['pph_code']) ? round($subtotal * $this->taxRate($data['pph_code']), 2) : 0.0;

        $dupe = FinInvoice::withoutGlobalScopes()->where('invoice_type', 'SUPPLIER_BILL')
            ->where('partner_id', $supplier->id_supplier)->where('partner_ref', $data['partner_ref'] ?? null)
            ->where('doc_status', '<>', 'CANCELLED')->first();
        abort_if((bool) $dupe && ! empty($data['partner_ref']), 422, tf('A bill with the same vendor number is already recorded: :no.', ['no' => $dupe?->invoice_number]));

        $invoice = FinInvoice::create([
            'invoice_number' => $this->nextInvoiceNumber('SUPPLIER_BILL', $data['invoice_date']),
            'invoice_type' => 'SUPPLIER_BILL', 'partner_type' => 'SUPPLIER', 'partner_id' => $supplier->id_supplier,
            'source_type' => $data['source_type'] ?? 'MANUAL', 'source_id' => $data['source_id'] ?? null,
            'description' => $data['description'] ?? null, 'partner_ref' => $data['partner_ref'] ?? null,
            'invoice_date' => $data['invoice_date'], 'due_date' => $data['due_date'],
            'subtotal' => $subtotal, 'tax_amount' => $tax, 'pph_amount' => $pph, 'pph_code' => $data['pph_code'] ?? null,
            'total_amount' => $subtotal + $tax,
            'tax_invoice_no' => $data['tax_invoice_no'] ?? null, 'tax_validated' => ! empty($data['tax_invoice_no']),
            'revenue_account_id' => $data['expense_account_id'] ?? $this->accountId('211900'),
            'payment_status' => 'UNPAID', 'doc_status' => 'DRAFT',
            'data_origin' => $data['data_origin'] ?? 'LIVE',
            'created_by' => $user, 'updated_by' => $user,
        ]);

        return $post ? $this->postVendorBill($invoice, $user) : $invoice;
    }

    public function postVendorBill(FinInvoice $invoice, string $user): FinInvoice
    {
        abort_unless($invoice->doc_status !== 'POSTED', 422, t('This bill is already posted.'));
        $supplier = SupSupplier::withoutGlobalScopes()->findOrFail($invoice->partner_id);

        $lines = [['account_id' => $invoice->revenue_account_id ?: $this->accountId('211900'), 'debit' => $invoice->subtotal, 'narration' => $invoice->description ?: 'Tagihan supplier']];
        if ((float) $invoice->tax_amount > 0) {
            $lines[] = ['account_id' => $this->accountId('114100'), 'debit' => $invoice->tax_amount, 'narration' => 'PPN Masukan'];
        }
        $lines[] = [
            'account_id' => $this->apAccountFor($supplier), 'credit' => (float) $invoice->total_amount - (float) $invoice->pph_amount,
            'partner_type' => 'SUPPLIER', 'partner_id' => $supplier->id_supplier, 'narration' => 'Hutang '.$invoice->invoice_number,
        ];
        if ((float) $invoice->pph_amount > 0) {
            $lines[] = ['account_id' => $this->accountId($invoice->pph_code === 'PPH22' ? '212300' : '212200'), 'credit' => $invoice->pph_amount, 'narration' => 'Potongan '.$invoice->pph_code];
        }

        $voucher = $this->journal->post('PUR', 'VENDOR_BILL', $invoice->invoice_id,
            'Tagihan '.$invoice->invoice_number.' — '.$supplier->supplier_name, $lines, $user,
            $invoice->invoice_date->toDateString(), [
                'journal_kind' => 'SYSTEM', 'reference_no' => $invoice->partner_ref ?: $invoice->invoice_number,
                'partner_name' => $supplier->supplier_name, 'ppn_type' => (float) $invoice->tax_amount > 0 ? 'PPN_IN' : null,
                'efaktur_no' => $invoice->tax_invoice_no, 'pph_type' => $invoice->pph_code, 'data_origin' => $invoice->data_origin,
            ]);

        $invoice->update(['voucher_id' => $voucher->voucher_id, 'doc_status' => 'POSTED', 'updated_by' => $user]);

        return $invoice->refresh();
    }

    /**
     * Terima pembayaran customer / bayar supplier + alokasi ke faktur.
     * $allocations: [invoice_id => amount]. Alokasi tak boleh > sisa faktur.
     */
    public function settle(string $mode, int $bankAccountId, string $date, array $allocations, string $user, string $note = ''): FinVoucher
    {
        abort_if(empty($allocations), 422, t('No invoice has been allocated yet.'));
        $this->guard->assertOpen($date);
        $invoices = FinInvoice::withoutGlobalScopes()->whereIn('invoice_id', array_keys($allocations))->get()->keyBy('invoice_id');
        $total = 0.0;
        foreach ($allocations as $invoiceId => $amount) {
            $inv = $invoices[$invoiceId] ?? abort(404, t('Invoice not found.'));
            $amount = round((float) $amount, 2);
            abort_if($amount <= 0, 422, t('The allocation amount must be more than zero.'));
            abort_if($amount > $inv->outstanding() + 0.01, 422, tf('The allocation to :no exceeds the outstanding amount (:amount).', ['no' => $inv->invoice_number, 'amount' => number_format($inv->outstanding(), 2)]));
            $total += $amount;
        }

        $first = $invoices->first();
        $isReceipt = $mode === 'RECEIPT';
        $partnerName = $isReceipt
            ? CustCustomer::withoutGlobalScopes()->find($first->partner_id)?->customer_name
            : SupSupplier::withoutGlobalScopes()->find($first->partner_id)?->supplier_name;
        $controlAccount = $isReceipt
            ? $this->arAccountFor(CustCustomer::withoutGlobalScopes()->findOrFail($first->partner_id))
            : $this->apAccountFor(SupSupplier::withoutGlobalScopes()->findOrFail($first->partner_id));

        $lines = [];
        foreach ($allocations as $invoiceId => $amount) {
            $inv = $invoices[$invoiceId];
            $lines[] = [
                'account_id' => $controlAccount,
                $isReceipt ? 'credit' : 'debit' => round((float) $amount, 2),
                'partner_type' => $isReceipt ? 'CUSTOMER' : 'SUPPLIER', 'partner_id' => $inv->partner_id,
                'co_id' => $inv->co_id, 'narration' => ($isReceipt ? 'Pelunasan ' : 'Pembayaran ').$inv->invoice_number,
            ];
        }
        $lines[] = ['account_id' => $bankAccountId, $isReceipt ? 'debit' : 'credit' => round($total, 2), 'narration' => $note ?: ($isReceipt ? 'Penerimaan bank' : 'Pembayaran bank')];

        $bankIsCash = str_starts_with((string) FinAccount::withoutGlobalScopes()->find($bankAccountId)?->account_code, '1111');
        $voucherType = $isReceipt ? ($bankIsCash ? 'BKM' : 'BBM') : ($bankIsCash ? 'BKK' : 'BBK');

        return DB::connection('tm2acc')->transaction(function () use ($isReceipt, $lines, $user, $date, $voucherType, $partnerName, $allocations, $invoices, $note, $total, $first) {
            $voucher = $this->journal->post($voucherType[1] === 'K' ? 'CSH' : 'BNK', $isReceipt ? 'AR_RECEIPT' : 'AP_PAYMENT', $first->invoice_id,
                ($isReceipt ? 'Penerimaan dari ' : 'Pembayaran ke ').$partnerName.($note ? ' — '.$note : ''), $lines, $user, $date, [
                    'voucher_number' => $this->journal->nextInSeries($voucherType.'/'.date('Y/m', strtotime($date)).'/', 3),
                    'voucher_type' => $voucherType, 'journal_kind' => 'SYSTEM', 'main_account_id' => end($lines)['account_id'],
                    'partner_name' => $partnerName, 'data_origin' => $first->data_origin,
                ]);

            foreach ($allocations as $invoiceId => $amount) {
                $inv = $invoices[$invoiceId];
                FinPaymentAlloc::create([
                    'voucher_id' => $voucher->voucher_id, 'invoice_id' => $invoiceId, 'amount' => round((float) $amount, 2),
                    'alloc_date' => $date, 'data_origin' => $inv->data_origin, 'created_by' => $user,
                ]);
                $paid = round((float) $inv->paid_amount + (float) $amount, 2);
                $due = round((float) $inv->total_amount - (float) $inv->pph_amount, 2);
                $inv->update([
                    'paid_amount' => $paid,
                    'payment_status' => $paid + 0.01 >= $due ? 'PAID' : ($paid > 0 ? 'PARTIAL' : 'UNPAID'),
                    'updated_by' => $user,
                ]);
            }
            unset($total);

            return $voucher;
        });
    }

    /** Faktur terbuka per mitra (untuk layar alokasi). */
    public function openInvoices(string $type, ?int $partnerId = null)
    {
        return FinInvoice::where('invoice_type', $type)->where('doc_status', 'POSTED')
            ->where('payment_status', '<>', 'PAID')
            ->when($partnerId, fn ($q) => $q->where('partner_id', $partnerId))
            ->orderBy('due_date')->get();
    }
}
