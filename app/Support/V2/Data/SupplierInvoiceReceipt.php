<?php

namespace App\Support\V2\Data;

use App\Models\Acc\FinInvoice;
use App\Models\Acc\FinInvoiceReceipt;
use App\Models\Acc\FinInvoiceReceiptLine;
use App\Models\Master\SupSupplier;
use App\Models\Pur\PoOrder;
use Devlib\Locale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Tanda Terima Tagihan Supplier v2 (user 2026-10-02): supplier menyerahkan invoice -> Finance mencatat invoice yang diterima
// -> Confirm (nomor lahir, siap dicetak dan dibawa pulang supplier) -> Cancel wajib alasan. Bukan bukti bayar dan tidak
// membuat jurnal. Baris boleh menunjuk tagihan yang sudah dicatat (fin_invoice SUPPLIER_BILL): nomor, tanggal, faktur
// pajak, dan nilainya disalin saat Save; invoice yang belum dicatat diketik langsung.
final class SupplierInvoiceReceipt
{
    public const STATUS_TONES = ['DRAFT' => 'neutral', 'CONFIRMED' => 'success', 'CANCELLED' => 'danger'];

    public static function find(int $id): ?FinInvoiceReceipt
    {
        return FinInvoiceReceipt::query()->with(['supplier', 'lines'])->find($id);
    }

    public static function rows(): array
    {
        return FinInvoiceReceipt::query()->with(['supplier', 'lines'])->orderByDesc('id_invoice_receipt')->limit(500)->get()->map(fn (FinInvoiceReceipt $r) => [
            'id' => $r->id_invoice_receipt, 'no' => $r->receipt_no ?: 'DRAFT-'.$r->id_invoice_receipt, 'supplier' => $r->supplier?->supplier_name,
            'date' => $r->receipt_date?->toDateString(), 'planned' => $r->planned_payment_date?->toDateString(), 'handed_by' => $r->handed_by,
            'method' => FinInvoiceReceipt::METHODS[$r->payment_method] ?? null, 'total' => $r->total(), 'status' => $r->status(),
            'items' => $r->lines->map(fn (FinInvoiceReceiptLine $l) => ['invoice' => $l->supplier_invoice_no, 'invoice_date' => $l->invoice_date?->toDateString(),
                'po' => $l->po_no, 'tax' => $l->tax_invoice_no, 'amount' => (float) $l->amount])->values()->all(),
        ])->all();
    }

    public static function supplierOptions(): array
    {
        return PurchaseOrder::suppliers();
    }

    // Tagihan supplier ini yang sudah dicatat dan belum ada di tanda terima lain yang masih berlaku.
    public static function billOptions(FinInvoiceReceipt $r): array
    {
        $taken = FinInvoiceReceiptLine::query()->whereNotNull('invoice_id')->where('invoice_receipt_id', '<>', $r->id_invoice_receipt)
            ->whereHas('receipt', fn ($q) => $q->where('stat_cancel', 0))->pluck('invoice_id')->all();

        return FinInvoice::query()->where('invoice_type', 'SUPPLIER_BILL')->where('partner_id', $r->supplier_id)->where('doc_status', '<>', 'CANCELLED')
            ->whereNotIn('invoice_id', $taken)->orderByDesc('invoice_date')->limit(200)->get()
            ->mapWithKeys(fn (FinInvoice $i) => [$i->invoice_id => implode(' · ', array_filter([$i->partner_ref ?: $i->invoice_number, Locale::d($i->invoice_date), Locale::number($i->total_amount, 2)]))])->all();
    }

    // PO supplier ini (lintas-DB tm2acc -> tm2pur: query terpisah).
    public static function poOptions(FinInvoiceReceipt $r): array
    {
        return PoOrder::query()->where('i_supplier_id', $r->supplier_id)->where('stat_cancel', 0)->whereNotNull('doc_no')
            ->orderByDesc('po_id')->limit(200)->pluck('doc_no', 'po_id')->all();
    }

    public static function create(int $supplierId): FinInvoiceReceipt
    {
        $s = SupSupplier::withoutGlobalScopes()->findOrFail($supplierId);
        $login = Auth::user()->login;

        return FinInvoiceReceipt::create(['receipt_date' => now()->toDateString(), 'supplier_id' => $s->id_supplier, 'payment_term' => $s->payment_term ?: null,
            'payment_method' => 'TRANSFER', 'att_invoice' => true, 'created_by' => $login, 'updated_by' => $login]);
    }

    // Header + baris (tambah / ubah / hapus). Baris baru tanpa tagihan dan tanpa nomor invoice dilewati.
    public static function save(FinInvoiceReceipt $r, array $in): void
    {
        abort_unless($r->status() === 'DRAFT', 422, t('This receipt is already Confirmed and cannot be changed.'));
        $login = Auth::user()->login;
        $text = fn (string $k, int $max) => mb_substr(trim((string) ($in[$k] ?? '')), 0, $max) ?: null;
        $r->fill([
            'receipt_date' => trim((string) ($in['receipt_date'] ?? '')) ?: $r->receipt_date, 'handed_by' => $text('handed_by', 80), 'payment_term' => $text('payment_term', 60),
            'planned_payment_date' => trim((string) ($in['planned_payment_date'] ?? '')) ?: null,
            'payment_method' => isset(FinInvoiceReceipt::METHODS[$in['payment_method'] ?? '']) ? $in['payment_method'] : null,
            'notes' => $text('notes', 255), 'updated_by' => $login,
        ]);
        foreach (array_keys(FinInvoiceReceipt::ATTACHMENTS) as $f) {
            $r->{$f} = ! empty($in[$f]);
        }
        $bills = FinInvoice::query()->where('invoice_type', 'SUPPLIER_BILL')->where('partner_id', $r->supplier_id)
            ->whereKey(collect((array) ($in['lines'] ?? []))->pluck('invoice_id')->filter()->map(fn ($v) => (int) $v)->all())->get()->keyBy('invoice_id');
        $pos = self::poOptions($r);

        DB::connection('tm2acc')->transaction(function () use ($r, $in, $login, $bills, $pos) {
            $r->save();
            $lines = $r->lines()->get()->keyBy('id_invoice_receipt_line');
            foreach (array_keys((array) ($in['remove'] ?? [])) as $id) {
                $lines->get((int) $id)?->delete();
            }
            $next = (int) $lines->max('line_no');
            foreach ((array) ($in['lines'] ?? []) as $id => $row) {
                $row = (array) $row;
                if (isset($in['remove'][$id])) {
                    continue;
                }
                $line = is_numeric($id) ? $lines->get((int) $id) : null;
                $bill = $bills->get((int) ($row['invoice_id'] ?? 0));
                $no = mb_substr(trim((string) ($row['supplier_invoice_no'] ?? '')), 0, 60) ?: (string) ($bill?->partner_ref ?: $bill?->invoice_number);
                if (! $line) {
                    if ($no === '') {
                        continue;
                    }
                    $line = new FinInvoiceReceiptLine(['invoice_receipt_id' => $r->id_invoice_receipt, 'line_no' => ++$next, 'created_by' => $login]);
                }
                $po = (int) ($row['po_id'] ?? 0);
                $amount = (float) (Locale::input($row['amount'] ?? 0) ?? 0);
                $line->fill([
                    'invoice_id' => $bill?->invoice_id, 'supplier_invoice_no' => $no !== '' ? $no : $line->supplier_invoice_no,
                    'invoice_date' => trim((string) ($row['invoice_date'] ?? '')) ?: $bill?->invoice_date?->toDateString(),
                    'po_id' => isset($pos[$po]) ? $po : null, 'po_no' => $pos[$po] ?? null,
                    'tax_invoice_no' => mb_substr(trim((string) ($row['tax_invoice_no'] ?? '')), 0, 40) ?: $bill?->tax_invoice_no,
                    'amount' => $amount > 0 ? $amount : (float) ($bill?->total_amount ?? 0), 'updated_by' => $login,
                ])->save();
            }
        });
    }

    public static function confirmErrors(FinInvoiceReceipt $r): array
    {
        $r->load('lines');
        $errors = [];
        if ($r->lines->isEmpty()) {
            $errors[] = t('Add at least one supplier invoice.');
        }
        if ($r->lines->contains(fn (FinInvoiceReceiptLine $l) => (float) $l->amount <= 0)) {
            $errors[] = t('Every invoice needs its amount.');
        }
        if ($r->lines->pluck('supplier_invoice_no')->duplicates()->isNotEmpty()) {
            $errors[] = t('The same invoice number appears twice.');
        }
        $bills = $r->lines->pluck('invoice_id')->filter()->all();
        if ($bills && FinInvoiceReceiptLine::query()->whereIn('invoice_id', $bills)->where('invoice_receipt_id', '<>', $r->id_invoice_receipt)
            ->whereHas('receipt', fn ($q) => $q->where('stat_cancel', 0))->exists()) {
            $errors[] = t('One of the invoices is already on another receipt.');
        }

        return $errors;
    }

    // confirm | cancel -> pesan salah, null = berhasil.
    public static function act(FinInvoiceReceipt $r, string $action, string $reason = ''): ?string
    {
        $login = Auth::user()->login;
        if ($action === 'confirm') {
            if ($r->status() !== 'DRAFT') {
                return t('Only a Draft can be confirmed.');
            }
            if ($errors = self::confirmErrors($r)) {
                return implode(' ', $errors);
            }
            DB::connection('tm2acc')->transaction(fn () => $r->forceFill(['receipt_no' => FinInvoiceReceipt::nextNo(), 'stat_confirm' => true, 'updated_by' => $login])->save());

            return null;
        }
        if ($r->status() !== 'CONFIRMED') {
            return t('Only a Confirmed receipt can be cancelled.');
        }
        if (trim($reason) === '') {
            return t('Fill in the reason for cancelling.');
        }
        $r->forceFill(['stat_cancel' => true, 'cancel_reason' => mb_substr(trim($reason), 0, 255), 'updated_by' => $login])->save();

        return null;
    }

    // Delete = hapus permanen, hanya Draft (rulebook C.6); baris ikut terhapus (FK cascade).
    public static function delete(FinInvoiceReceipt $r): void
    {
        abort_unless($r->status() === 'DRAFT', 422, t('Only a Draft can be deleted.'));
        $r->delete();
    }

    public static function formStatus(FinInvoiceReceipt $r): string
    {
        return strtolower($r->status());
    }
}
