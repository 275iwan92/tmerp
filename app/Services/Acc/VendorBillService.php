<?php

/*
 * REMARK
 * Tujuan       : Tagihan vendor (Procure to Pay) dari layar v2 acc-payables-form: catat draft, Confirm (Approve and Post, atau Pending approval di atas batas), setujui, batalkan draft, validasi faktur pajak, bayar (Payment Run), salin.
 * Sumber hulu  : Aturan dipindah apa adanya dari halaman v1 pages::finance.payables (diarsipkan di tmerp_unused/finance_payables_v1_2026-10-18.blade.php); rumus PPN / PPh, jurnal posting dan alokasi pembayaran tetap di ArApService, aturan persetujuan di fin_approval_rule (VENDOR_BILL) via JournalPostingService.
 * Input        : Isian form (vendor, no. tagihan vendor, tanggal, deskripsi, akun debit, net, PPN, PPh, e-Faktur, PV sumber), alokasi pembayaran, user.
 * Output       : tm2acc.fin_invoice (SUPPLIER_BILL) + jurnal PUR + fin_payment_alloc + voucher BBK / BKK berubah; ValidationException / abort 422 bila aturan dilanggar.
 * Dipakai oleh : routes/v2_finance.php (v2.acc.payables.*), layar acc-payables-form, tes VendorBillServiceTest.
 * Status       : Aktif. Draft tidak bisa diedit (sama seperti v1): batalkan lalu catat ulang. Tagihan Posted tidak bisa dibatalkan di sini.
 */

namespace App\Services\Acc;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinInvoice;
use App\Models\Acc\FinVoucher;
use App\Models\Master\FinPaymentTerm;
use App\Models\Master\SupSupplier;
use App\Models\Pur\PvReceipt;
use App\Models\Sys\SecUser;
use Carbon\Carbon;
use Devlib\Locale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

final class VendorBillService
{
    public const PPH = ['PPH23' => 'PPh 23 (services, 2%)', 'PPH22' => 'PPh 22 (1.5%)'];

    public function __construct(private ArApService $ap, private JournalPostingService $journal) {}

    /** Isian awal tagihan baru menurut vendor: PPh 23 untuk subcon, jatuh tempo dari termin bayar. */
    public function defaults(SupSupplier $s, ?string $billDate = null): array
    {
        $date = Carbon::parse($billDate ?: now());
        $days = FinPaymentTerm::firstWhere('payment_term_code', $s->payment_term)?->days ?? (preg_match('/(\d+)/', (string) $s->payment_term, $m) ? (int) $m[1] : 30);

        return ['pph_code' => $s->is_subcon ? 'PPH23' : '', 'bill_date' => $date->toDateString(), 'due_date' => $date->copy()->addDays($days)->toDateString(),
            'expense_account_id' => (int) FinAccount::where('account_code', '211900')->value('account_id')];
    }

    /** Catat tagihan sebagai DRAFT (belum diposting). */
    public function create(array $in, SecUser $user): FinInvoice
    {
        $in['subtotal'] = Locale::input($in['subtotal'] ?? null) ?? ($in['subtotal'] ?? null);
        $in['ppn'] = ! empty($in['ppn']);
        $d = Validator::make($in, [
            'supplier_id' => ['required', 'integer'],
            'partner_ref' => ['required', 'string', 'max:50'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:bill_date'],
            'description' => ['required', 'string', 'max:255'],
            'expense_account_id' => ['required', 'exists:tm2acc.fin_account,account_id'],
            'subtotal' => ['required', 'numeric', 'min:1'],
            'pph_code' => ['nullable', 'in:PPH23,PPH22'],
            'tax_invoice_no' => ['nullable', 'string', 'max:50'],
            'pv_id' => ['nullable', 'integer'],
        ], [], ['supplier_id' => 'Vendor', 'partner_ref' => 'Vendor bill no.', 'subtotal' => 'Net amount', 'bill_date' => 'Bill date', 'due_date' => 'Due date', 'expense_account_id' => 'Debit account'])->validate();

        return $this->ap->createVendorBill([
            'supplier_id' => $d['supplier_id'], 'partner_ref' => $d['partner_ref'], 'invoice_date' => $d['bill_date'], 'due_date' => $d['due_date'],
            'subtotal' => $d['subtotal'], 'ppn' => $in['ppn'], 'pph_code' => ($d['pph_code'] ?? null) ?: null, 'expense_account_id' => (int) $d['expense_account_id'],
            'description' => $d['description'], 'source_type' => ! empty($d['pv_id']) ? 'PV' : 'MANUAL', 'source_id' => ($d['pv_id'] ?? null) ?: null,
            'tax_invoice_no' => ($d['tax_invoice_no'] ?? null) ?: null,
        ], $user->login, false);
    }

    /** Salinan tagihan (biasanya yang dibatalkan) sebagai Draft baru bertanggal hari ini. */
    public function duplicate(FinInvoice $b, SecUser $user): FinInvoice
    {
        $days = max(0, (int) $b->invoice_date->diffInDays($b->due_date));

        return $this->create(['supplier_id' => $b->partner_id, 'partner_ref' => $b->partner_ref, 'bill_date' => now()->toDateString(), 'due_date' => now()->addDays($days)->toDateString(),
            'description' => $b->description, 'expense_account_id' => $b->revenue_account_id, 'subtotal' => (float) $b->subtotal, 'ppn' => (float) $b->tax_amount > 0,
            'pph_code' => $b->pph_code, 'tax_invoice_no' => $b->tax_invoice_no, 'pv_id' => $b->source_type === 'PV' ? $b->source_id : null], $user);
    }

    /** Confirm: posting langsung, atau Pending approval bila nilai tagihan di atas batas fin_approval_rule. @return string posted|submitted */
    public function confirm(FinInvoice $b, SecUser $user): string
    {
        abort_unless($b->doc_status === 'DRAFT', 422, 'Only a DRAFT bill can be confirmed.');
        if ($this->journal->requiresApproval('VENDOR_BILL', (float) $b->total_amount)) {
            $b->update(['doc_status' => 'SUBMITTED', 'updated_by' => $user->login]);

            return 'submitted';
        }
        $this->ap->postVendorBill($b, $user->login);

        return 'posted';
    }

    public function approve(FinInvoice $b, SecUser $user): void
    {
        abort_unless(in_array($b->doc_status, ['DRAFT', 'SUBMITTED'], true), 422, 'The bill is not waiting for approval.');
        $rule = $this->journal->ruleFor('VENDOR_BILL', (float) $b->total_amount);
        if ($rule) {
            abort_unless($this->journal->canApprove($user, $rule->approver_group), 422, 'You are not authorized to approve this bill (approver: '.$rule->approver_group.').');
            abort_if($rule->segregation_of_duties && ! $user->is_admin && $b->created_by === $user->login, 422, 'Segregation of duties: the person who recorded a bill cannot approve their own bill.');
        }
        $this->ap->postVendorBill($b, $user->login);
    }

    public function cancel(FinInvoice $b, SecUser $user): void
    {
        abort_if($b->doc_status === 'POSTED', 422, 'A posted bill cannot be cancelled here: use a reversing journal.');
        $b->update(['doc_status' => 'CANCELLED', 'updated_by' => $user->login]);
    }

    public function validateTax(FinInvoice $b, SecUser $user): void
    {
        $b->update(['tax_validated' => true, 'updated_by' => $user->login]);
    }

    /**
     * Payment Run: bayar satu atau beberapa tagihan terbuka milik vendor yang sama (Dr Hutang / Cr Bank).
     *
     * @param  array<int|string, mixed>  $alloc  invoice_id => ['pay' => nilai]
     */
    public function pay(FinInvoice $b, array $in, array $alloc, SecUser $user): FinVoucher
    {
        $d = Validator::make($in, ['pay_date' => ['required', 'date'], 'pay_bank' => ['required', 'exists:tm2acc.fin_account,account_id']], [], ['pay_date' => 'Payment date', 'pay_bank' => 'Pay from'])->validate();
        $open = $this->openForPay($b)->keyBy('invoice_id');
        $rows = [];
        foreach ($alloc as $id => $row) {
            $amount = (float) (Locale::input(is_array($row) ? ($row['pay'] ?? null) : $row) ?? 0);
            if ($amount > 0) {
                abort_unless($open->has((int) $id), 422, 'Only open bills of the same vendor can be paid together.');
                $rows[(int) $id] = $amount;
            }
        }

        return $this->ap->settle('PAYMENT', (int) $d['pay_bank'], $d['pay_date'], $rows, $user->login, trim((string) ($in['pay_note'] ?? '')));
    }

    /** @return Collection<int, FinInvoice> tagihan terbuka vendor yang sama, jatuh tempo terdekat dulu */
    public function openForPay(FinInvoice $b): Collection
    {
        return $this->ap->openInvoices('SUPPLIER_BILL', (int) $b->partner_id);
    }

    public function canApprove(FinInvoice $b, SecUser $user): bool
    {
        if ($b->doc_status !== 'SUBMITTED') {
            return false;
        }
        $rule = $this->journal->ruleFor('VENDOR_BILL', (float) $b->total_amount);

        return ! $rule || $this->journal->canApprove($user, $rule->approver_group);
    }

    /** @return array{0: string, 1: ?string} [status panel DevLib, tahap] */
    public function formState(FinInvoice $b): array
    {
        return match (true) {
            $b->doc_status === 'CANCELLED' => ['cancelled', null],
            $b->doc_status === 'SUBMITTED' => ['approval', 'Pending approval'],
            $b->doc_status === 'DRAFT' => ['draft', null],
            $b->payment_status === 'PAID' => ['closed', 'Paid'],
            $b->due_date->lt(today()) => ['confirmed', 'Overdue'],
            $b->due_date->lte(today()->addDays(7)) => ['confirmed', 'Due this week'],
            default => ['confirmed', $b->payment_status === 'PARTIAL' ? 'Partially paid' : 'Open'],
        };
    }

    /** Riwayat tab History: terbaru di atas. */
    public function history(FinInvoice $b): array
    {
        $b->loadMissing('voucher', 'allocations.voucher');
        $rows = [['at' => $b->created_at?->toDateTimeString(), 'by' => $b->created_by, 'action' => 'Recorded', 'note' => '']];
        if ($b->voucher) {
            $rows[] = ['at' => $b->voucher->posted_at?->toDateTimeString(), 'by' => $b->voucher->posted_by, 'action' => 'Posted', 'note' => $b->voucher->voucher_number];
        }
        foreach ($b->allocations as $a) {
            $rows[] = ['at' => $a->created_at?->toDateTimeString() ?? $a->alloc_date?->toDateString(), 'by' => $a->created_by, 'action' => 'Paid', 'note' => 'IDR '.Locale::number((float) $a->amount, 2).' · '.$a->voucher?->voucher_number];
        }
        if ($b->doc_status === 'CANCELLED') {
            $rows[] = ['at' => $b->updated_at?->toDateTimeString(), 'by' => $b->updated_by, 'action' => 'Cancelled', 'note' => ''];
        }

        return array_reverse($rows);
    }

    /** @return array<int, string> id => "kode · nama" vendor aktif */
    public function supplierOptions(): array
    {
        return SupSupplier::where('activate', true)->orderBy('supplier_name')->get(['id_supplier', 'supplier_code', 'supplier_name'])
            ->mapWithKeys(fn ($s) => [$s->id_supplier => $s->supplier_code.' · '.$s->supplier_name])->all();
    }

    /** @return array<int, string> id => "no. PV · tanggal": penerimaan barang vendor ini (3-way match) */
    public function pvOptions(int $supplierId): array
    {
        return PvReceipt::where('i_supplier_id', $supplierId)->orderByDesc('i_doc_date')->limit(30)->get()
            ->mapWithKeys(fn ($p) => [$p->pv_id => $p->doc_no.' · '.$p->i_doc_date?->format('d-M-Y')])->all();
    }

    /** @return array<int, string> akun yang boleh didebit tagihan: GR/IR 211900, persediaan, aset, HPP, beban */
    public function debitOptions(?int $include = null): array
    {
        return FinAccount::where('is_postable', true)->where(fn ($q) => $q->where('activate', true)->when($include, fn ($qq) => $qq->orWhere('account_id', $include)))
            ->where(fn ($q) => $q->where('account_code', '211900')->orWhere('account_code', 'like', '113%')->orWhere('account_code', 'like', '115%')
                ->orWhere('account_code', 'like', '12%')->orWhere('account_code', 'like', '13%')->orWhere('account_code', 'like', '5%')->orWhere('account_code', 'like', '6%')
                ->when($include, fn ($qq) => $qq->orWhere('account_id', $include)))
            ->where('account_code', 'not like', '1219%')->where('account_code', 'not like', '1319%')
            ->orderBy('account_code')->get(['account_id', 'account_code', 'account_name'])->mapWithKeys(fn ($a) => [$a->account_id => $a->account_code.' · '.$a->account_name])->all();
    }

    /** @return array<int, string> account_id => "code · name": akun kas & bank (111x) untuk sumber pembayaran */
    public function bankOptions(): array
    {
        return FinAccount::where('is_postable', true)->where('activate', true)->where('account_code', 'like', '111%')->orderBy('account_code')->get(['account_id', 'account_code', 'account_name'])
            ->mapWithKeys(fn ($a) => [$a->account_id => $a->account_code.' · '.$a->account_name])->all();
    }
}
