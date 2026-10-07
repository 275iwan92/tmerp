<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinTaxRegister;
use App\Models\Master\FinTaxScheme;
use App\Services\Acc\TaxEngine;
use App\Support\V2\Data\Concerns\ReportsBusinessErrors;
use Carbon\Carbon;
use Devlib\Locale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

// Finance > Controls & Tax > Indonesian Tax (v2): Tax-to-Accounting Flow (Classify -> Calculate -> Post -> Reconcile -> File & Pay). PPN dari mutasi GL 212100 / 114100,
// PPh 21 dari payroll (tm2pyr) atau GL, PPh 23 dari 212200, PPh Badan = provisi laba sebelum pajak x tarif; tarif & jatuh tempo dari tm2mst.fin_tax_scheme
// (PMK 131/2024 & 81/2024). Aturan di TaxEngine (sama dengan v1); kelas ini menyusun bentuk objek DevLib dan memeriksa masukan.
final class TaxDesk
{
    use ReportsBusinessErrors;

    public const STATUS_LABELS = ['DRAFT' => 'Draft return', 'REVIEW' => 'Review', 'PAYMENT_PENDING' => 'Payment pending', 'PAID' => 'Paid', 'FILED' => 'Filed', 'ACCRUED' => 'Accrued'];

    public const STATUS_TONES = ['DRAFT' => 'warn', 'REVIEW' => 'warn', 'PAYMENT_PENDING' => 'danger', 'PAID' => 'info', 'FILED' => 'success', 'ACCRUED' => 'success'];

    private const PAYABLE = ['DRAFT', 'REVIEW', 'PAYMENT_PENDING', 'ACCRUED'];

    private const FILEABLE = ['PAID', 'DRAFT', 'REVIEW'];

    public static function engine(): TaxEngine
    {
        return app(TaxEngine::class);
    }

    public static function defaultPeriod(): string
    {
        return now()->subMonthNoOverflow()->format('Y-m');
    }

    /** @return array{0: int, 1: int} tahun dan bulan dari "Y-m"; "Y-m" tak valid jatuh ke masa default */
    public static function ym(string $period): array
    {
        $period = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) ? $period : self::defaultPeriod();

        return [(int) substr($period, 0, 4), (int) substr($period, 5, 2)];
    }

    /** Nama bulan penuh mengikuti bahasa tampilan (Locale::at()->translatedFormat). */
    public static function monthName(string $period): string
    {
        [$y, $m] = self::ym($period);

        return Locale::at(Carbon::create($y, $m, 1))->translatedFormat('F Y');
    }

    /** @return array<string, string> 18 masa terakhir "Y-m" => nama bulan */
    public static function periodOptions(): array
    {
        $out = [];
        for ($i = 0; $i < 18; $i++) {
            $d = now()->startOfMonth()->subMonths($i);
            $out[$d->format('Y-m')] = self::monthName($d->format('Y-m'));
        }

        return $out;
    }

    /** @return Collection<string, FinTaxScheme> */
    public static function schemes(): Collection
    {
        return FinTaxScheme::where('activate', true)->get()->keyBy('tax_code');
    }

    /** @return Collection<int, FinTaxRegister> */
    public static function registers(string $type = '', string $search = ''): Collection
    {
        return FinTaxRegister::query()
            ->when($type !== '', fn ($q) => $q->where('tax_code', $type))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('register_code', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")))
            ->orderByDesc('fiscal_year')->orderByDesc('period_no')->limit(200)->get();
    }

    /** @return array{ppn: ?FinTaxRegister, pph21: ?FinTaxRegister, pph23: ?FinTaxRegister, cit: ?FinTaxRegister} register masa terpilih */
    public static function current(string $period): array
    {
        [$y, $m] = self::ym($period);
        $get = fn (string $code) => FinTaxRegister::where('tax_code', $code)->where('fiscal_year', $y)->where('period_no', $m)->first();

        return ['ppn' => $get('PPN_OUT'), 'pph21' => $get('PPH21'), 'pph23' => $get('PPH23'),
            'cit' => FinTaxRegister::where('tax_code', 'PPH_BADAN')->where('fiscal_year', $y)->whereNull('period_no')->first()];
    }

    /** @return Collection<int, FinTaxRegister> kewajiban terbuka terdekat */
    public static function calendar(): Collection
    {
        return FinTaxRegister::whereNotIn('status', ['FILED'])->whereNotNull('payment_due')->orderBy('payment_due')->limit(8)->get();
    }

    /** @return list<array{0: string, 1: string, 2: string}> [judul, keterangan, Matched|Review|Draft] */
    public static function reconciliation(string $period): array
    {
        [$y, $m] = self::ym($period);

        return self::engine()->reconciliation($y, $m);
    }

    /** @return list<array<string, mixed>> kartu KPI (props x-dl::kpi) */
    public static function kpis(array $c, string $period): array
    {
        $kpi = fn (string $label, ?FinTaxRegister $r, string $icon, string $tone, ?string $note) => ['label' => $label, 'value' => (float) ($r?->payable_amount ?? 0), 'format' => 'money', 'previous' => null,
            'goodWhen' => 'down', 'icon' => $icon, 'tone' => $tone, 'note' => $note];

        return [
            $kpi('PPN Net Payable', $c['ppn'], 'fa-percent', $c['ppn'] ? self::tone($c['ppn']->status) : 'neutral', tf('Period :month', ['month' => self::monthName($period)])),
            $kpi('PPh 21 Payable', $c['pph21'], 'fa-percent', $c['pph21'] ? self::tone($c['pph21']->status) : 'neutral', $c['pph21'] ? t(self::STATUS_LABELS[$c['pph21']->status] ?? $c['pph21']->status) : t('Not generated')),
            $kpi('PPh 23 Withheld', $c['pph23'], 'fa-percent', 'accent', self::monthName($period)),
            $kpi('PPh Badan Provision', $c['cit'], 'fa-file-contract', 'violet', tf('FY :year estimate', ['year' => substr($period, 0, 4)])),
        ];
    }

    /** @return list<array<string, mixed>> langkah x-dl::flow (Tax-to-Accounting Flow) */
    public static function flow(): array
    {
        $step = fn (string $key, string $label, string $icon, string $note) => ['key' => $key, 'label' => t($label), 'icon' => $icon, 'status' => 'todo', 'note' => t($note)];

        return [
            $step('classify', 'Classify Event', 'fa-tags', 'Tax profile, counterparty, tax code, invoice data and supporting documents.'),
            $step('calculate', 'Calculate Tax', 'fa-calculator', 'PPN output/input & PPh withholding from fin_tax_scheme (rate configurable).'),
            $step('post', 'Post Accounting', 'fa-book', 'Tax payable/receivable accounts with source transaction references.'),
            $step('reconcile', 'Reconcile', 'fa-arrows-left-right', 'Tax register vs invoice subledger vs GL control accounts.'),
            $step('file', 'File & Pay', 'fa-file-signature', 'Approved workpaper, NTPN payment evidence, filing tracking and archive.'),
        ];
    }

    public static function tone(string $status): string
    {
        return self::STATUS_TONES[$status] ?? 'neutral';
    }

    /** @return array{0: string, 1: string} [teks jatuh tempo, nada] */
    public static function dueTag($date): array
    {
        if (! $date) {
            return ['—', 'neutral'];
        }
        $days = (int) today()->diffInDays($date, false);

        return [tf('Due :date', ['date' => Locale::d($date)]), $days < 0 ? 'danger' : ($days <= 7 ? 'warn' : 'info')];
    }

    public static function canPay(FinTaxRegister $r): bool
    {
        return in_array($r->status, self::PAYABLE, true);
    }

    public static function canFile(FinTaxRegister $r): bool
    {
        return in_array($r->status, self::FILEABLE, true);
    }

    /** @return array<int, string> register yang masih punya aksi (bayar / lapor / review) => nama untuk kotak pilih */
    public static function actionable(): array
    {
        return FinTaxRegister::query()->whereNotIn('status', ['FILED'])->orderByDesc('fiscal_year')->orderByDesc('period_no')->limit(100)->get()
            ->filter(fn ($r) => self::canPay($r) || self::canFile($r))->mapWithKeys(fn ($r) => [$r->tax_reg_id => $r->register_code.' · '.t(self::STATUS_LABELS[$r->status] ?? $r->status)])->all();
    }

    // ---------- aksi ----------

    /** @return int jumlah register masa yang disusun */
    public static function generate(string $period, string $by): int
    {
        [$y, $m] = self::ym($period);

        return count(self::run(fn () => self::engine()->generateMonth($y, $m, $by)));
    }

    public static function provision(string $period, string $by): void
    {
        [$y, $m] = self::ym($period);
        self::run(fn () => self::engine()->provisionCit($y, date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $y, $m))), $by));
    }

    /** @param array<string, mixed> $in pay_date, ntpn, pay_bank */
    public static function pay(int $registerId, array $in, string $by): void
    {
        $d = Validator::make($in, ['pay_date' => ['required', 'date'], 'ntpn' => ['required', 'string', 'max:30'], 'pay_bank' => ['required', 'exists:tm2acc.fin_account,account_id']],
            [], ['pay_date' => t('Payment date'), 'ntpn' => 'NTPN', 'pay_bank' => t('Pay from')])->validate();
        self::run(fn () => self::engine()->pay(FinTaxRegister::findOrFail($registerId), $d['pay_date'], $d['ntpn'], (int) $d['pay_bank'], $by));
    }

    public static function fileReturn(int $registerId, string $ref, string $by): void
    {
        $d = Validator::make(['filing_ref' => $ref], ['filing_ref' => ['required', 'string', 'max:50']], [], ['filing_ref' => t('Filing reference')])->validate();
        self::run(fn () => self::engine()->file(FinTaxRegister::findOrFail($registerId), $d['filing_ref'], $by));
    }

    public static function review(int $registerId, string $by): void
    {
        self::run(fn () => FinTaxRegister::findOrFail($registerId)->update(['status' => 'REVIEW', 'updated_by' => $by]));
    }

    /** @return array<int, string> rekening kas / bank untuk "Pay from" */
    public static function bankOptions(): array
    {
        return FinAccount::where('is_postable', true)->where('activate', true)->where('account_code', 'like', '111%')->orderBy('account_code')->get()
            ->mapWithKeys(fn ($a) => [$a->account_id => $a->account_code.' · '.$a->account_name])->all();
    }

    /** @return array{0: list<string>, 1: list<list<mixed>>} unduhan register pajak (sama dengan v1) */
    public static function csv(Collection $registers): array
    {
        return [['Register', 'Tax', 'Year', 'Period', 'Description', 'Base/Output', 'Credit/Input', 'Payable', 'Payment due', 'Filing due', 'Status', 'NTPN', 'Filing ref'],
            $registers->map(fn ($r) => [$r->register_code, $r->tax_code, $r->fiscal_year, $r->period_no, $r->description, (float) $r->base_amount, (float) $r->credit_amount, (float) $r->payable_amount,
                $r->payment_due?->format('Y-m-d'), $r->filing_due?->format('Y-m-d'), $r->status, $r->ntpn, $r->filing_ref])->all()];
    }
}
