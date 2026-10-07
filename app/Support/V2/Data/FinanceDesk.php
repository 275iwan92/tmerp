<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Acc\FinApprovalRule;
use App\Services\Acc\FinanceMetrics;
use App\Support\Finance\Fmt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

// Finance > Control Center (v2): Accounting Dashboard dan Accounting Process Engine. Angka & daftar = App\Services\Acc\FinanceMetrics (sama dengan v1);
// kelas ini menyusun bentuk objek DevLib (x-dl::kpi, x-dl::chart, x-dl::status-card, x-dl::flow) dan mengelola aturan workflow (fin_approval_rule).
final class FinanceDesk
{
    public const PROCESSES = ['JOURNAL' => 'Manual journal', 'CUSTOMER_INVOICE' => 'Customer invoice', 'VENDOR_BILL' => 'Vendor bill', 'BANK_ADJUSTMENT' => 'Bank adjustment', 'ASSET' => 'Asset capitalization'];

    // Kode nada FinanceMetrics -> nada + ikon objek DevLib.
    private const TONES = ['re' => ['danger', 'triangle-exclamation'], 'or' => ['warn', 'clock'], 'bl' => ['info', 'circle-check'], 'gr' => ['success', 'circle-check'], 'pu' => ['violet', 'box']];

    public static function metrics(): FinanceMetrics
    {
        return app(FinanceMetrics::class);
    }

    /** Satu butir daftar FinanceMetrics [ikon, nada, judul, keterangan, jumlah] -> props x-dl::status-card (teks sudah diterjemahkan service). */
    public static function card(array $item): array
    {
        [$tone, $icon] = self::TONES[$item[1]] ?? ['neutral', 'circle-info'];

        return ['title' => $item[2], 'desc' => $item[3], 'status' => (string) ($item[4] ?? ''), 'tone' => $tone, 'icon' => $icon];
    }

    /** @return list<array<string, mixed>> */
    public static function cards(array $items): array
    {
        return array_map([self::class, 'card'], $items);
    }

    // ---------- Dashboard ----------

    /** @return list<array<string, mixed>> empat kartu KPI (props x-dl::kpi) */
    public static function dashboardKpis(array $h): array
    {
        $prev = fn (float $now, ?float $pct) => $pct === null || $pct <= -100 ? null : $now / (1 + $pct / 100);

        return array_map(fn (array $k) => $k + ['format' => 'money', 'goodWhen' => 'up', 'previous' => null, 'note' => null], [
            ['label' => 'Cash & Bank', 'value' => $h['cash'], 'previous' => $prev((float) $h['cash'], $h['cash_change']), 'icon' => 'fa-vault', 'tone' => 'accent'],
            ['label' => 'Accounts Receivable', 'value' => $h['ar'], 'icon' => 'fa-arrow-up-from-bracket', 'tone' => 'warn', 'goodWhen' => 'down',
                'note' => tf(':n invoices', ['n' => $h['ar_n']]).' · '.tf(':amount overdue', ['amount' => Fmt::short($h['ar_overdue'])])],
            ['label' => 'Accounts Payable', 'value' => $h['ap'], 'icon' => 'fa-arrow-down-to-bracket', 'tone' => 'warn', 'goodWhen' => 'down',
                'note' => tf(':n bills', ['n' => $h['ap_n']]).' · '.tf(':amount due this week', ['amount' => Fmt::short($h['ap_due'])])],
            ['label' => 'Net Profit YTD', 'value' => $h['net_profit'], 'previous' => $prev((float) $h['net_profit'], $h['profit_change']), 'icon' => 'fa-arrow-trend-up', 'tone' => $h['net_profit'] >= 0 ? 'success' : 'danger'],
        ]);
    }

    /** @return array{labels: list<string>, series: list<array<string, mixed>>} tren arus kas operasi 12 bulan untuk x-dl::chart */
    public static function cashTrend(): array
    {
        $trend = self::metrics()->cashTrend();

        return ['labels' => array_keys($trend), 'series' => [['name' => t('Operating cash'), 'data' => array_values($trend)]]];
    }

    // ---------- Process Engine ----------

    /** @return list<array<string, mixed>> empat kartu KPI engine */
    public static function engineKpis(array $m): array
    {
        $kpi = fn (string $label, float|int $value, string $icon, string $tone, ?string $note, string $format = 'number') => ['label' => $label, 'value' => $value, 'format' => $format, 'previous' => null,
            'goodWhen' => 'up', 'icon' => $icon, 'tone' => $tone, 'note' => $note];

        return [
            $kpi('Source Documents', $m['source_docs'], 'fa-file-lines', 'accent', tf(':month transactions', ['month' => now()->format('F Y')])),
            $kpi('Pending Approval', $m['pending_n'], 'fa-hourglass-half', $m['pending_n'] ? 'warn' : 'success', $m['pending_n'] ? tf(':amount exposure', ['amount' => Fmt::short($m['pending_value'])]) : t('Queue empty')),
            $kpi('Posted to GL', $m['posted_n'], 'fa-circle-check', $m['tb_balanced'] ? 'success' : 'danger', $m['tb_balanced'] ? t('Balanced') : t('Out of balance')),
            $kpi('Close Readiness', $m['close_pct'], 'fa-lock', $m['close_exceptions'] ? 'warn' : 'success',
                $m['close_exceptions'] ? tf(':n control exceptions', ['n' => $m['close_exceptions']]) : t('No exceptions'), 'percent'),
        ];
    }

    /** @return list<array<string, mixed>> langkah x-dl::flow: alur posting akuntansi baku */
    public static function postingFlow(array $m): array
    {
        $step = fn (string $key, string $label, string $icon, string $note, ?string $badge = null) => ['key' => $key, 'label' => t($label), 'icon' => $icon, 'status' => $badge ? 'review' : 'todo',
            'note' => t($note).($badge ? ' · '.$badge : '')];

        return [
            $step('source', 'Source Event', 'fa-file-import', 'Invoice, bill, receipt, bank transaction, asset purchase, tax, or manual journal.', $m['source_docs'] ? (string) $m['source_docs'] : null),
            $step('validation', 'Validation', 'fa-list-check', 'Period open, master data, tax logic, document balance, duplicate and control-account checks.'),
            $step('approval', 'Approval', 'fa-stamp', 'Role, amount and segregation-of-duties matrix (fin_approval_rule).', $m['pending_n'] ? (string) $m['pending_n'] : null),
            $step('subledger', 'Subledger', 'fa-book', 'AR, AP, bank, fixed assets, tax and amortization schedules keep the detail.'),
            $step('gl', 'GL Posting', 'fa-scale-balanced', 'Balanced debit/credit voucher, audit trail, source reference, posting date.', $m['posted_n'] ? (string) $m['posted_n'] : null),
            $step('close', 'Reporting & Close', 'fa-lock', 'Trial balance, statements, tax workpapers, reconciliation, period lock.', $m['close_pct'].'%'),
        ];
    }

    /** @return list<array{title: string, text: string, slug: string}> peta integrasi modul (slug layar v2 bila ada, jika tidak nama rute v1) */
    public static function integrationMap(): array
    {
        return [
            ['title' => 'Order to Cash', 'text' => 'Invoice → AR → receipt allocation → bank → revenue and PPN posting', 'url' => route('v2.screen', 'customer-invoice')],
            ['title' => 'Procure to Pay', 'text' => 'Purchase receive → GR/IR → bill → AP → payment, input PPN & PPh 23', 'url' => route('v2.screen', 'acc-payables')],
            ['title' => 'Bank & Cash', 'text' => 'Cashier vouchers → statement import → auto-match → reconciliation', 'url' => route('v2.screen', 'acc-bank')],
            ['title' => 'Assets & Amortization', 'text' => 'Capitalization → schedule → periodic depreciation/amortization journals', 'url' => route('v2.screen', 'acc-amortization')],
            ['title' => 'Tax Compliance', 'text' => 'PPN & PPh derived from taxable events, reconciled to GL, then filed', 'url' => route('v2.screen', 'acc-tax')],
            ['title' => 'Inventory & Production', 'text' => 'Purchase receive and raw-material issue post automatically (PUR & USG journals)', 'url' => route('v2.screen', 'acc-rpt-ledger')],
        ];
    }

    // ---------- Aturan workflow (fin_approval_rule) ----------

    /** @return Collection<int, FinApprovalRule> */
    public static function rules(): Collection
    {
        return FinApprovalRule::orderBy('process_code')->orderBy('min_amount')->get();
    }

    /** @return array<string, string> kode grup => "KODE · Nama" untuk penyetuju */
    public static function approverGroups(): array
    {
        return DB::connection('tm2sys')->table('sec_group')->where('activate', true)->orderBy('group_code')->get(['group_code', 'group_name'])
            ->mapWithKeys(fn ($g) => [$g->group_code => $g->group_code.' · '.$g->group_name])->all();
    }

    /** @param array<string, mixed> $in */
    public static function saveRule(array $in, string $by): FinApprovalRule
    {
        $d = Validator::make($in, [
            'rule_name' => ['required', 'string', 'max:120'], 'process_code' => ['required', 'in:'.implode(',', array_keys(self::PROCESSES))],
            'min_amount' => ['required', 'numeric', 'min:0'], 'approver_group' => ['required', 'string', 'max:30'],
        ], [], ['rule_name' => t('Rule name'), 'process_code' => t('Process'), 'min_amount' => t('Minimum amount'), 'approver_group' => t('Approver group')])->validate();

        if (! DB::connection('tm2sys')->table('sec_group')->where('group_code', $d['approver_group'])->exists()) {
            throw ValidationException::withMessages(['approver_group' => t('Unknown approver group.')]);
        }

        return FinApprovalRule::create(['rule_name' => $d['rule_name'], 'process_code' => $d['process_code'], 'min_amount' => $d['min_amount'], 'approver_group' => $d['approver_group'],
            'segregation_of_duties' => ! empty($in['sod']), 'activate' => true, 'created_by' => $by]);
    }

    public static function toggleRule(int $id, string $by): bool
    {
        $r = FinApprovalRule::findOrFail($id);
        $r->update(['activate' => ! $r->activate, 'updated_by' => $by]);

        return (bool) $r->activate;
    }

    /** @return array{0: list<string>, 1: list<list<mixed>>} header + baris unduhan aturan (sama dengan v1) */
    public static function rulesCsv(): array
    {
        return [['Rule', 'Process', 'Minimum amount', 'Approver group', 'Segregation of duties', 'Active'],
            self::rules()->map(fn ($r) => [$r->rule_name, $r->process_code, (float) $r->min_amount, $r->approver_group, $r->segregation_of_duties ? 'yes' : 'no', $r->activate ? 'yes' : 'no'])->all()];
    }
}
