<?php

namespace App\Support\V2\Grid\Hrm;

use App\Models\Master\EmpEmployee;
use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
use App\Services\Payroll\PayrollAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Cetak & Kirim Slip Gaji (v1: pages::hrm.payroll-payslip-list). Semua slip periode yang sudah dihitung; buka = cetak slip.
class PayslipGrid extends HrmGrid
{
    private array $employees = [];

    public function slug(): string
    {
        return 'hrm-payslip';
    }

    public function title(): string
    {
        return t('Print & Send Payslips');
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
        return 'hrm.payroll.payslip-list';
    }

    protected function canCreate(): bool
    {
        return false;
    }

    public function openUrl(): ?string
    {
        return str_replace('999999999', '{id}', route('hrm.payroll.payslip-print', ['payslip' => 999999999]));
    }

    protected function models(): array
    {
        return [PyrPayslip::class, PyrPayrollRun::class, EmpEmployee::class];
    }

    public function search(): string
    {
        return 'Search employee, period...';
    }

    protected function query(): Builder
    {
        return PyrPayslip::query()->with('run')
            ->whereHas('run', fn ($q) => $q->whereIn('run_status', ['CALCULATED', 'VERIFIED', 'CONFIRMED', 'PAID', 'CLOSED']))
            ->tap(fn ($q) => PayrollAccess::restrict($q, auth()->user()))
            ->orderByDesc('id_run')->orderBy('employee_id');
    }

    protected function prefetch(Collection $items): void
    {
        $this->employees = EmpEmployee::query()->with('position.department')->whereIn('employee_id', $items->pluck('employee_id')->unique())
            ->get()->keyBy('employee_id')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Employee No. / Name', 'type' => 'doc', 'party' => 'name'],
            ['key' => 'period', 'label' => 'Period'],
            ['key' => 'dept', 'label' => 'Department'],
            ['key' => 'net', 'label' => 'Net Salary', 'type' => 'money'],
            ['key' => 'printed', 'label' => 'Printout', 'type' => 'badge', 'tones' => [t('Printed') => 'success', t('Not printed') => 'neutral']],
            ['key' => 'wa', 'label' => 'Send WhatsApp', 'type' => 'badge', 'tones' => [t('Sent') => 'success', t('Not sent') => 'neutral']],
            ['key' => 'anomaly', 'label' => 'Anomaly', 'type' => 'badge', 'tones' => [t('Yes') => 'danger']],
        ];
    }

    protected function row(Model $m): array
    {
        $e = $this->employees[$m->employee_id] ?? null;
        $run = $m->run;

        return [
            'period' => $run ? $run->period_type.' '.tf(':from to :to', ['from' => substr((string) $run->period_start, 0, 10), 'to' => substr((string) $run->period_end, 0, 10)]) : null,
            'name' => $e?->full_name, 'code' => $e?->employee_code, 'dept' => $e?->position?->department?->department_name,
            'net' => (float) $m->total_gaji_bersih, 'printed' => t($m->print_flag ? 'Printed' : 'Not printed'), 'wa' => t($m->wa_flag ? 'Sent' : 'Not sent'),
            'anomaly' => $m->has_anomaly ? t('Yes') : null,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All slips'],
            ['label' => 'Not printed', 'where' => ['printed' => t('Not printed')], 'tone' => 'warn'],
            ['label' => 'WhatsApp not sent', 'where' => ['wa' => t('Not sent')], 'tone' => 'info'],
            ['label' => 'Anomaly', 'where' => ['anomaly' => t('Yes')], 'tone' => 'danger'],
        ];
    }

    public function filters(): array
    {
        return ['period' => 'Period', 'dept' => 'Department'];
    }
}
