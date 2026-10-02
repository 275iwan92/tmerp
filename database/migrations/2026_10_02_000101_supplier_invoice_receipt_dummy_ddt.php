<?php

use App\Models\Acc\FinInvoice;
use App\Models\Acc\FinInvoiceReceipt;
use App\Models\Acc\FinInvoiceReceiptLine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Contoh data Tanda Terima Tagihan Supplier untuk UAT -- HANYA di database dummy ddt* (aturan B.12: layar baru wajib punya
// dummy). Di tm2* migration ini tidak menulis apa pun. Dua dokumen dari tagihan supplier dummy yang sudah ada: satu
// Confirmed (dua invoice, siap cetak) dan satu Draft.
return new class extends Migration
{
    private const TAG = '[UAT supplier invoice receipt]';

    private function isDummy(): bool
    {
        return str_starts_with(DB::connection('tm2acc')->getDatabaseName(), 'ddt');
    }

    public function up(): void
    {
        if (! $this->isDummy() || FinInvoiceReceipt::query()->where('notes', 'like', '%'.self::TAG.'%')->exists()) {
            return;
        }
        // Supplier dengan tagihan terbanyak -> dua invoice untuk dokumen Confirmed; supplier berikutnya -> satu untuk Draft.
        $bills = FinInvoice::query()->where('invoice_type', 'SUPPLIER_BILL')->where('doc_status', 'POSTED')->orderByDesc('invoice_date')->get()->groupBy('partner_id')
            ->sortByDesc(fn ($g) => $g->count())->values();
        if ($bills->isEmpty()) {
            return;
        }
        $docs = array_filter([
            ['CONFIRMED', $bills[0]->take(2)],
            isset($bills[1]) ? ['DRAFT', $bills[1]->take(1)] : null,
        ]);
        DB::connection('tm2acc')->transaction(function () use ($docs) {
            foreach ($docs as [$status, $set]) {
                $first = $set->first();
                $r = FinInvoiceReceipt::create([
                    'receipt_no' => $status === 'CONFIRMED' ? FinInvoiceReceipt::nextNo() : null, 'receipt_date' => now()->toDateString(), 'supplier_id' => $first->partner_id,
                    'handed_by' => 'Bp. Anton', 'payment_term' => 'Net 30', 'planned_payment_date' => $set->max('due_date')?->toDateString() ?? now()->addDays(30)->toDateString(),
                    'payment_method' => 'TRANSFER', 'att_invoice' => true, 'att_tax_invoice' => $set->contains(fn ($b) => (bool) $b->tax_invoice_no), 'att_delivery_note' => true,
                    'notes' => 'Sample for UAT. '.self::TAG, 'stat_confirm' => $status === 'CONFIRMED', 'created_by' => 'uat', 'updated_by' => 'uat',
                ]);
                foreach ($set->values() as $i => $b) {
                    FinInvoiceReceiptLine::create(['invoice_receipt_id' => $r->id_invoice_receipt, 'line_no' => $i + 1, 'invoice_id' => $b->invoice_id,
                        'supplier_invoice_no' => $b->partner_ref ?: $b->invoice_number, 'invoice_date' => $b->invoice_date?->toDateString(), 'tax_invoice_no' => $b->tax_invoice_no,
                        'amount' => $b->total_amount, 'created_by' => 'uat', 'updated_by' => 'uat']);
                }
            }
        });
    }

    public function down(): void
    {
        if ($this->isDummy()) {
            FinInvoiceReceipt::query()->where('notes', 'like', '%'.self::TAG.'%')->get()->each->delete();
        }
    }
};
