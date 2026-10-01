<?php

namespace App\Support\V2\Grid\Hrm;

use App\Models\Master\EmpEmployee;
use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
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
        return 'Cetak & Kirim Slip Gaji';
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
        return 'Cari pegawai, periode...';
    }

    protected function query(): Builder
    {
        return PyrPayslip::query()->with('run')
            ->whereHas('run', fn ($q) => $q->whereIn('run_status', ['CALCULATED', 'VERIFIED', 'CONFIRMED', 'PAID', 'CLOSED']))
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
            ['key' => 'period', 'label' => 'Periode'],
            ['key' => 'dept', 'label' => 'Departemen'],
            ['key' => 'net', 'label' => 'Net Salary', 'type' => 'money'],
            ['key' => 'printed', 'label' => 'Printout', 'type' => 'badge', 'tones' => ['Sudah' => 'success', 'Belum' => 'neutral']],
            ['key' => 'wa', 'label' => 'Send WhatsApp', 'type' => 'badge', 'tones' => ['Terkirim' => 'success', 'Belum' => 'neutral']],
            ['key' => 'anomaly', 'label' => 'Anomali', 'type' => 'badge', 'tones' => ['Ada' => 'danger']],
        ];
    }

    protected function row(Model $m): array
    {
        $e = $this->employees[$m->employee_id] ?? null;
        $run = $m->run;

        return [
            'period' => $run ? $run->period_type.' '.substr((string) $run->period_start, 0, 10).' s/d '.substr((string) $run->period_end, 0, 10) : null,
            'name' => $e?->full_name, 'code' => $e?->employee_code, 'dept' => $e?->position?->department?->department_name,
            'net' => (float) $m->total_gaji_bersih, 'printed' => $m->print_flag ? 'Sudah' : 'Belum', 'wa' => $m->wa_flag ? 'Terkirim' : 'Belum',
            'anomaly' => $m->has_anomaly ? 'Ada' : null,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All slips'],
            ['label' => 'Not printed', 'where' => ['printed' => 'Belum'], 'tone' => 'warn'],
            ['label' => 'WhatsApp not sent', 'where' => ['wa' => 'Belum'], 'tone' => 'info'],
            ['label' => 'Anomali', 'where' => ['anomaly' => 'Ada'], 'tone' => 'danger'],
        ];
    }

    public function filters(): array
    {
        return ['period' => 'Periode', 'dept' => 'Departemen'];
    }
}
