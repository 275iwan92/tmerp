<?php

namespace App\Support\V2\Grid\Hrm;

use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Perhitungan Gaji (v1: pages::hrm.payroll-batch-run). Baris = periode payroll; buka = alur DRAFT..CLOSED di layar v1.
class PayrollRunGrid extends HrmGrid
{
    private array $slips = [];

    public function slug(): string
    {
        return 'hrm-payroll-run';
    }

    public function title(): string
    {
        return 'Perhitungan Gaji';
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function group(): string
    {
        return 'Payroll';
    }

    protected function v1Route(): ?string
    {
        return 'hrm.payroll.batch-run';
    }

    protected function models(): array
    {
        return [PyrPayrollRun::class, PyrPayslip::class];
    }

    public function search(): string
    {
        return 'Cari periode, status...';
    }

    protected function query(): Builder
    {
        return PyrPayrollRun::query()->orderByDesc('period_start');
    }

    protected function prefetch(Collection $items): void
    {
        $this->slips = PyrPayslip::query()->whereIn('id_run', $items->modelKeys())->groupBy('id_run')
            ->selectRaw('id_run, COUNT(*) n, SUM(has_anomaly) anomaly')->get()->keyBy('id_run')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'type', 'label' => 'Periode', 'type' => 'badge', 'tones' => ['WEEKLY' => 'warn', 'MONTHLY' => 'info']],
            ['key' => 'start', 'label' => 'From', 'type' => 'date'],
            ['key' => 'end', 'label' => 'Until', 'type' => 'date'],
            ['key' => 'slips', 'label' => 'Slip', 'type' => 'number'],
            ['key' => 'anomaly', 'label' => 'Anomali', 'type' => 'number'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'steps', 'steps' => ['DRAFT', 'CALCULATED', 'VERIFIED', 'CONFIRMED', 'PAID', 'CLOSED']],
        ];
    }

    protected function row(Model $m): array
    {
        $s = $this->slips[$m->getKey()] ?? null;

        return [
            'type' => $m->period_type, 'start' => substr((string) $m->period_start, 0, 10), 'end' => substr((string) $m->period_end, 0, 10),
            'slips' => (int) ($s->n ?? 0), 'anomaly' => (int) ($s->anomaly ?? 0), 'status' => $m->run_status,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All periods'],
            ['label' => 'Berjalan', 'where' => ['status' => ['DRAFT', 'CALCULATED', 'VERIFIED']], 'tone' => 'warn'],
            ['label' => 'Confirmed / Paid', 'where' => ['status' => ['CONFIRMED', 'PAID']], 'tone' => 'info'],
            ['label' => 'Closed', 'where' => ['status' => 'CLOSED'], 'tone' => 'success'],
        ];
    }

    public function filters(): array
    {
        return ['type' => 'Periode', 'status' => 'Status'];
    }
}
