<?php

namespace App\Services\Acc;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinInvoice;
use App\Models\Acc\FinTaxRegister;
use App\Models\Master\FinTaxScheme;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// Tax-to-Accounting Flow (blueprint): klasifikasi -> hitung -> posting ->
// rekonsiliasi (register vs GL vs sumber) -> bayar & lapor. Semua tarif &
// jatuh tempo dibaca dari tm2mst.fin_tax_scheme (bisa diubah tanpa kode).
class TaxEngine
{
    public function __construct(private LedgerService $ledger, private JournalPostingService $journal) {}

    private function movement(string $code, string $from, string $to): array
    {
        $ids = FinAccount::withoutGlobalScopes()->where('account_code', $code)->pluck('account_id');
        $m = $this->ledger->movements($from, $to);

        return ['debit' => $ids->sum(fn ($i) => $m[$i]['debit'] ?? 0), 'credit' => $ids->sum(fn ($i) => $m[$i]['credit'] ?? 0)];
    }

    public function dueDates(string $taxCode, int $year, ?int $month): array
    {
        $s = FinTaxScheme::find($taxCode);
        if (! $s || ! $month) {
            return [null, null];
        }
        $next = Carbon::create($year, $month, 1)->addMonth();
        $pay = $s->payment_due_day ? $next->copy()->day(min($s->payment_due_day, $next->daysInMonth)) : null;
        $file = $s->filing_due_day === null ? null : ($s->filing_due_day === 0 ? $next->copy()->endOfMonth() : $next->copy()->day($s->filing_due_day));

        return [$pay?->toDateString(), $file?->toDateString()];
    }

    public function payrollPph21(int $year, int $month): float
    {
        $from = sprintf('%04d-%02d-01', $year, $month);

        return round((float) DB::connection('tm2pyr')->table('pyr_payslip as s')
            ->join('pyr_payroll_run as r', 'r.id_run', '=', 's.id_run')
            ->whereBetween('r.period_end', [$from, date('Y-m-t', strtotime($from))])
            ->sum('s.total_pph21'), 2);
    }

    // Susun/refresh register masa (PPN, PPh 21, PPh 23) dari GL & payroll.
    // Baris yang sudah dibayar/dilapor tidak diubah.
    public function generateMonth(int $year, int $month, string $user, string $origin = 'LIVE'): array
    {
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to = date('Y-m-t', strtotime($from));
        $label = Carbon::parse($from)->format('M Y');

        $out = $this->movement('212100', $from, $to);
        $in = $this->movement('114100', $from, $to);
        $pph21Gl = $this->movement('214100', $from, $to)['credit'];
        $pph21 = $pph21Gl > 0 ? $pph21Gl : $this->payrollPph21($year, $month);
        $pph23 = $this->movement('212200', $from, $to)['credit'];

        $rows = [
            'PPN_OUT' => ['VAT', "PPN Masa — {$label}", 'Output VAT less input VAT', $out['credit'] - $out['debit'], $in['debit'] - $in['credit']],
            'PPH21' => ['WHT21', "PPh 21 — {$label}", 'Employee income tax withholding', $pph21, 0],
            'PPH23' => ['WHT23', "PPh 23 — {$label}", 'Service and royalty withholding', $pph23, 0],
        ];
        $saved = [];
        foreach ($rows as $code => [$prefix, $name, $desc, $base, $credit]) {
            $reg = FinTaxRegister::withoutGlobalScopes()->firstOrNew(['tax_code' => $code, 'fiscal_year' => $year, 'period_no' => $month]);
            if ($reg->exists && ! in_array($reg->status, ['DRAFT', 'REVIEW'], true)) {
                $saved[] = $reg;

                continue;
            }
            [$pay, $file] = $this->dueDates($code, $year, $month);
            $reg->fill([
                'register_code' => sprintf('%s-%04d-%02d', $prefix, $year, $month),
                'description' => $name.' · '.$desc,
                'base_amount' => round($base, 2), 'credit_amount' => round($credit, 2),
                'payable_amount' => round($base - $credit, 2),
                'payment_due' => $pay, 'filing_due' => $file,
                'status' => $reg->status ?: 'DRAFT',
                'updated_by' => $user,
            ]);
            if (! $reg->exists) {
                $reg->created_by = $user;
                $reg->data_origin = $origin;
            }
            $reg->save();
            $saved[] = $reg;
        }

        return $saved;
    }

    // Bayar: jurnal pelunasan hutang pajak ke bank (PPN: saling hapus Keluaran-Masukan).
    public function pay(FinTaxRegister $reg, string $date, string $ntpn, int $bankAccountId, string $user): void
    {
        abort_if(in_array($reg->status, ['PAID', 'FILED'], true), 422, t('This tax has already been paid.'));
        $acc = fn ($c) => (int) FinAccount::withoutGlobalScopes()->where('account_code', $c)->value('account_id');
        $amount = round((float) $reg->payable_amount, 2);
        if ($reg->tax_code === 'PPN_OUT') {
            $lines = [
                ['account_id' => $acc('212100'), 'debit' => $reg->base_amount, 'narration' => 'Kompensasi PPN Keluaran '.$reg->register_code],
                ['account_id' => $acc('114100'), 'credit' => $reg->credit_amount, 'narration' => 'Kompensasi PPN Masukan'],
            ];
        } else {
            $payable = ['PPH21' => '214100', 'PPH23' => '212200', 'PPH_BADAN' => '214200'][$reg->tax_code] ?? '212200';
            $lines = [['account_id' => $acc($payable), 'debit' => $amount, 'narration' => 'Setor '.$reg->register_code]];
        }
        if ($amount > 0) {
            $lines[] = ['account_id' => $bankAccountId, 'credit' => $amount, 'narration' => 'NTPN '.$ntpn];
        }
        $v = $this->journal->post('BNK', 'TAX_PAYMENT', $reg->tax_reg_id, 'Setoran '.$reg->description, $lines, $user, $date, [
            'voucher_type' => 'BBK', 'journal_kind' => 'SYSTEM', 'reference_no' => $ntpn, 'main_account_id' => $bankAccountId,
            'voucher_number' => $this->journal->nextInSeries('BBK/'.date('Y/m', strtotime($date)).'/', 3), 'data_origin' => $reg->data_origin,
        ]);
        $reg->update(['status' => 'PAID', 'ntpn' => $ntpn, 'paid_at' => $date, 'voucher_id' => $v->voucher_id, 'updated_by' => $user]);
    }

    public function file(FinTaxRegister $reg, string $ref, string $user): void
    {
        abort_unless($reg->status === 'PAID' || (float) $reg->payable_amount <= 0, 422, t('Pay the tax before filing (unless nil/overpaid).'));
        $reg->update(['status' => 'FILED', 'filing_ref' => $ref, 'filed_at' => now(), 'updated_by' => $user]);
    }

    // Provisi PPh Badan: laba sebelum pajak YTD x tarif, jurnal hanya selisihnya.
    public function provisionCit(int $year, string $asOf, string $user, string $origin = 'LIVE'): FinTaxRegister
    {
        $pbt = $this->ledger->incomeStatement("{$year}-01-01", $asOf)['profit_before_tax'];
        $rate = (float) (FinTaxScheme::find('PPH_BADAN')?->rate ?? 22) / 100;
        $target = round(max(0, $pbt) * $rate, 2);
        $booked = $this->movement('911000', "{$year}-01-01", $asOf);
        $delta = round($target - ($booked['debit'] - $booked['credit']), 2);

        $reg = FinTaxRegister::withoutGlobalScopes()->firstOrNew(['tax_code' => 'PPH_BADAN', 'fiscal_year' => $year, 'period_no' => null]);
        if (abs($delta) >= 1) {
            $acc = fn ($c) => (int) FinAccount::withoutGlobalScopes()->where('account_code', $c)->value('account_id');
            $this->journal->post('GEN', 'TAX_PROVISION', $year, "Provisi PPh Badan {$year} s.d. {$asOf}", [
                ['account_id' => $acc('911000'), $delta > 0 ? 'debit' : 'credit' => abs($delta)],
                ['account_id' => $acc('214200'), $delta > 0 ? 'credit' : 'debit' => abs($delta)],
            ], $user, $asOf, ['journal_kind' => 'ACCRUAL', 'data_origin' => $reg->exists ? $reg->data_origin : $origin]);
        }
        $reg->fill([
            'register_code' => "CIT-{$year}", 'description' => "PPh Badan — FY {$year} · Corporate income tax provision",
            'base_amount' => $pbt, 'credit_amount' => 0, 'payable_amount' => $target,
            'status' => 'ACCRUED', 'workpaper_note' => 'Laba sebelum pajak s.d. '.$asOf.' x '.($rate * 100).'% (estimasi, sebelum koreksi fiskal)',
            'updated_by' => $user,
        ]);
        if (! $reg->exists) {
            $reg->created_by = $user;
            $reg->data_origin = $origin;
        }
        $reg->save();

        return $reg;
    }

    /** Status rekonsiliasi pajak utk 1 masa (panel "Tax Reconciliation Status"). */
    public function reconciliation(int $year, int $month): array
    {
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to = date('Y-m-t', strtotime($from));
        $invTax = (float) FinInvoice::where('invoice_type', 'CUSTOMER_INVOICE')->where('doc_status', 'POSTED')->whereBetween('invoice_date', [$from, $to])->sum('tax_amount')
            + (float) FinInvoice::where('invoice_type', 'CUSTOMER_DP')->whereBetween('invoice_date', [$from, $to])->sum('tax_amount');
        $gl = $this->movement('212100', $from, $to);
        $glOut = $gl['credit'];
        $unvalidated = FinInvoice::where('invoice_type', 'SUPPLIER_BILL')->where('doc_status', 'POSTED')->whereBetween('invoice_date', [$from, $to])
            ->where('tax_amount', '>', 0)->where('tax_validated', false)->count();
        $payroll = $this->payrollPph21($year, $month);
        $pph21Reg = (float) FinTaxRegister::where('tax_code', 'PPH21')->where('fiscal_year', $year)->where('period_no', $month)->value('base_amount');
        $cit = FinTaxRegister::where('tax_code', 'PPH_BADAN')->where('fiscal_year', $year)->first();

        return [
            ['Output VAT reconciliation', abs($invTax - $glOut) < 1 ? t('Sales invoices and GL output VAT aligned') : str_replace([':invoice', ':gl'], [number_format($invTax, 0, ',', '.'), number_format($glOut, 0, ',', '.')], t('Invoice Rp :invoice vs GL Rp :gl')), abs($invTax - $glOut) < 1 ? 'Matched' : 'Review'],
            ['Input VAT reconciliation', $unvalidated ? str_replace(':n', (string) $unvalidated, t(':n supplier tax invoices not validated')) : t('All supplier tax invoices validated'), $unvalidated ? 'Review' : 'Matched'],
            ['PPh 21 payroll reconciliation', abs($payroll - $pph21Reg) < 1 ? t('Payroll tax liability agrees to payroll') : str_replace([':payroll', ':register'], [number_format($payroll, 0, ',', '.'), number_format($pph21Reg, 0, ',', '.')], t('Payroll Rp :payroll vs register Rp :register')), abs($payroll - $pph21Reg) < 1 ? 'Matched' : 'Review'],
            ['Fiscal adjustment workpaper', $cit ? t('PPh Badan provision booked; fiscal corrections pending review') : t('CIT provision not yet calculated'), $cit ? 'Draft' : 'Review'],
        ];
    }
}
