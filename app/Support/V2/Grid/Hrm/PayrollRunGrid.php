<?php

namespace App\Support\V2\Grid\Hrm;

use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
use App\Services\Payroll\PayrollAccess;
use Devlib\Locale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Payroll Runs: periode payroll dengan jumlah slip dan anomali (dalam cakupan user) serta langkah status DRAFT sampai CLOSED; Open membuka form v2 hrm-payroll-run-form (alur siklus), + New Record membuka form periode baru.
 * Sumber hulu  : tm2pyr.pyr_payroll_run + pyr_payslip.
 * Input        : Pencarian, filter Period / Status, kotak status.
 * Output       : Baris grid (hanya baca; perubahan lewat form).
 * Dipakai oleh : Menu HRM > Payroll (v2:hrm-payroll-run), layar hrm-payroll-run-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::hrm.payroll-batch-run sudah dipindah ke tmerp_unused (aturan D.25, T8 Payroll).
 */
class PayrollRunGrid extends HrmGrid
{
    private array $slips = [];

    public function slug(): string
    {
        return 'hrm-payroll-run';
    }

    public function title(): string
    {
        return t('Payroll Runs');
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function group(): string
    {
        return 'Payroll';
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'hrm-payroll-run-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return route('v2.screen', 'hrm-payroll-run-form').'?new=1';
    }

    protected function models(): array
    {
        return [PyrPayrollRun::class, PyrPayslip::class];
    }

    public function search(): string
    {
        return 'Search period, status...';
    }

    protected function query(): Builder
    {
        return PyrPayrollRun::query()->orderByDesc('period_start');
    }

    protected function prefetch(Collection $items): void
    {
        $this->slips = PayrollAccess::restrict(PyrPayslip::query()->whereIn('id_run', $items->modelKeys()), auth()->user())->groupBy('id_run')
            ->selectRaw('id_run, COUNT(*) n, SUM(has_anomaly) anomaly')->get()->keyBy('id_run')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'period', 'label' => 'Period Start / Type', 'type' => 'doc', 'party' => 'type'],
            ['key' => 'start', 'label' => 'From', 'type' => 'date', 'visible' => false],
            ['key' => 'end', 'label' => 'Until', 'type' => 'date'],
            ['key' => 'slips', 'label' => 'Slip', 'type' => 'number'],
            ['key' => 'anomaly', 'label' => 'Anomaly', 'type' => 'number'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'steps', 'steps' => ['DRAFT', 'CALCULATED', 'VERIFIED', 'CONFIRMED', 'PAID', 'CLOSED']],
        ];
    }

    protected function row(Model $m): array
    {
        $s = $this->slips[$m->getKey()] ?? null;

        return [
            'period' => Locale::date(substr((string) $m->period_start, 0, 10)), 'type' => $m->period_type, 'start' => substr((string) $m->period_start, 0, 10), 'end' => substr((string) $m->period_end, 0, 10),
            'slips' => (int) ($s->n ?? 0), 'anomaly' => (int) ($s->anomaly ?? 0), 'status' => $m->run_status,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All periods'],
            ['label' => 'In Progress', 'where' => ['status' => ['DRAFT', 'CALCULATED', 'VERIFIED']], 'tone' => 'warn'],
            ['label' => 'Confirmed / Paid', 'where' => ['status' => ['CONFIRMED', 'PAID']], 'tone' => 'info'],
            ['label' => 'Closed', 'where' => ['status' => 'CLOSED'], 'tone' => 'success'],
        ];
    }

    public function filters(): array
    {
        return ['type' => 'Period', 'status' => 'Status'];
    }
}
