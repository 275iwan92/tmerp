<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Acc\FinCloseTask;
use App\Models\Acc\FinPeriod;
use App\Services\Acc\CloseService;
use App\Services\Acc\FinPeriodGuard;
use App\Support\V2\Data\Concerns\ReportsBusinessErrors;
use Devlib\Locale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

// Finance > Controls & Tax > Period Closing (v2): kontrol tutup buku bulanan, cut-off akuntansi, persetujuan dan kunci periode. Aturan LOCKED
// (financeaccounting_delta_period_close.md) ada di CloseService / FinPeriodGuard dan sama dengan v1: status OPEN / ON_HOLD / CLOSED, CLOSED tidak bisa dibuka lagi,
// gate wajib ke tm2inv.inv_period, checklist dan kontrol harus lulus, voucher penutup tahun dibuat DRAFT (tidak auto-post). 13 periode per tahun fiskal.
final class ClosingDesk
{
    use ReportsBusinessErrors;

    public const STATUSES = ['OPEN' => 'Open', 'ON_HOLD' => 'On hold', 'CLOSED' => 'Closed'];

    public const STATUS_TONES = ['OPEN' => 'success', 'ON_HOLD' => 'warn', 'CLOSED' => 'neutral'];

    public const CONTROL_TONES = ['Passed' => 'info', 'Exception' => 'danger'];

    public static function close(): CloseService
    {
        return app(CloseService::class);
    }

    /** @return array<int, int> tahun fiskal yang ada, terbaru dulu */
    public static function years(): array
    {
        return FinPeriod::select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year', 'fiscal_year')->map(fn ($y) => (int) $y)->all();
    }

    /** Tahun awal layar: tahun periode yang sedang ditutup, jika tidak ada = tahun berjalan. */
    public static function defaultYear(): int
    {
        return (int) (self::close()->currentPeriod()?->fiscal_year ?? now()->format('Y'));
    }

    /** @return Collection<int, FinPeriod> */
    public static function periods(int $year): Collection
    {
        return FinPeriod::where('fiscal_year', $year)->orderBy('period_no')->get();
    }

    /** Periode terpilih: ?period valid di tahun itu; kalau tidak, periode berjalan (bila di tahun itu) atau periode pertama yang belum CLOSED. */
    public static function period(int $year, ?int $periodId): ?FinPeriod
    {
        if ($periodId && ($p = FinPeriod::where('fiscal_year', $year)->find($periodId))) {
            return $p;
        }
        $current = self::close()->currentPeriod();
        if ($current && (int) $current->fiscal_year === $year) {
            return $current;
        }

        return FinPeriod::where('fiscal_year', $year)->where('is_adjustment', false)->where('status', '<>', 'CLOSED')->orderBy('period_no')->first();
    }

    /** @return Collection<int, FinCloseTask> */
    public static function tasks(?FinPeriod $period): Collection
    {
        return $period ? $period->tasks()->orderBy('step_no')->get() : collect();
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: string}> kontrol otomatis [ikon, judul, keterangan, Passed|Exception|...] */
    public static function controls(?FinPeriod $period): array
    {
        return $period ? self::close()->controls($period) : [];
    }

    /** @return list<string> */
    public static function blockers(?FinPeriod $period): array
    {
        return $period ? app(FinPeriodGuard::class)->closeBlockers($period) : [];
    }

    /** @return array{pct: int, done: int, total: int, exceptions: int} */
    public static function completion(Collection $tasks, array $controls): array
    {
        $done = $tasks->where('status', 'COMPLETE')->count();

        return ['pct' => $tasks->count() ? (int) round($done / $tasks->count() * 100) : 0, 'done' => $done, 'total' => $tasks->count(),
            'exceptions' => collect($controls)->filter(fn ($c) => $c[3] === 'Exception')->count() + $tasks->where('status', 'EXCEPTION')->count()];
    }

    /** @return list<array<string, mixed>> kartu KPI (props x-dl::kpi) */
    public static function kpis(?FinPeriod $period, array $completion, ?FinPeriod $lastClosed): array
    {
        $kpi = fn (string $label, float|int|string $value, string $icon, string $tone, ?string $note) => ['label' => $label, 'value' => $value, 'format' => 'number', 'previous' => null,
            'goodWhen' => 'up', 'icon' => $icon, 'tone' => $tone, 'note' => $note];

        return [
            $kpi('Closing Period', $period?->period_name ?? '—', 'fa-calendar-check', self::STATUS_TONES[$period?->status] ?? 'neutral', $period ? t(self::STATUSES[$period->status] ?? $period->status) : t('No open period')),
            $kpi('Completion', $completion['pct'].'%', 'fa-circle-check', 'accent', tf(':done of :total controls', ['done' => $completion['done'], 'total' => $completion['total']])),
            $kpi('Open Exceptions', $completion['exceptions'], 'fa-triangle-exclamation', $completion['exceptions'] ? 'danger' : 'success', $completion['exceptions'] ? t('Need resolution') : t('None')),
            $kpi('Previous Period', $lastClosed?->period_name ?? '—', 'fa-lock', $lastClosed ? 'success' : 'warn', $lastClosed ? t('Locked') : t('Nothing closed yet')),
        ];
    }

    /** @return list<array<string, mixed>> langkah x-dl::flow (alur tutup buku bulanan) */
    public static function flow(): array
    {
        $step = fn (string $key, string $label, string $icon, string $note) => ['key' => $key, 'label' => t($label), 'icon' => $icon, 'status' => 'todo', 'note' => t($note)];

        return [
            $step('cutoff', 'Transaction Cut-off', 'fa-scissors', 'Complete operational invoices, bills, receipts and bank entries.'),
            $step('reconcile', 'Reconcile', 'fa-arrows-left-right', 'Bank, AR, AP, tax and asset balances.'),
            $step('schedules', 'Run Schedules', 'fa-clock-rotate-left', 'Depreciation, amortization, accruals and allocations.'),
            $step('review', 'Review TB', 'fa-magnifying-glass-chart', 'Balance, variance, reasonableness and analytical controls.'),
            $step('lock', 'Approve & Lock', 'fa-lock', 'Controller approval; CLOSED is irreversible — no posting afterwards.'),
        ];
    }

    // ---------- aksi ----------

    public static function start(FinPeriod $period, string $by): void
    {
        self::run(fn () => self::close()->start($period, $by));
    }

    public static function setTask(int $taskId, bool $complete, string $by): void
    {
        self::run(fn () => self::close()->setTask(FinCloseTask::findOrFail($taskId), $complete ? 'COMPLETE' : 'IN_PROGRESS', $by));
    }

    /** Ubah status periode (OPEN / ON_HOLD / CLOSED); alasan wajib (min. 5 huruf) dan tercatat di audit trail. */
    public static function changeStatus(FinPeriod $period, string $status, string $reason, string $by): void
    {
        $d = Validator::make(['status' => $status, 'reason' => $reason], ['status' => ['required', 'in:'.implode(',', array_keys(self::STATUSES))], 'reason' => ['required', 'string', 'min:5', 'max:255']],
            [], ['status' => t('Status'), 'reason' => t('Reason')])->validate();
        self::run(fn () => app(FinPeriodGuard::class)->changeStatus($period, $d['status'], $d['reason'], $by));
    }

    /** @return string nomor voucher penutup tahun (DRAFT) */
    public static function yearEnd(int $year, string $by): string
    {
        return self::run(fn () => (string) self::close()->yearEndVoucher($year, $by)->voucher_number);
    }

    /** @return array{0: list<string>, 1: list<list<mixed>>} checklist untuk unduhan CSV (sama dengan v1) */
    public static function checklistCsv(Collection $tasks): array
    {
        return [['Step', 'Control', 'Description', 'Status', 'Completed by', 'Completed at'],
            $tasks->map(fn ($t) => [$t->step_no, $t->title, $t->description, $t->status, $t->completed_by, $t->completed_at?->format('Y-m-d H:i')])->all()];
    }

    public static function rangeText(FinPeriod $p): string
    {
        return Locale::d($p->start_date).' — '.Locale::d($p->end_date);
    }
}
