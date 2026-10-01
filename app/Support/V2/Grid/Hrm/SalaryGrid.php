<?php

namespace App\Support\V2\Grid\Hrm;

use App\Models\Master\EmpEmployee;
use App\Models\Pyr\PyrEmployeeSalary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Master Gaji & Pajak Pegawai (v1: pages::hrm.payroll-employee-salary). Baris = pegawai aktif; setelan gaji di tm2pyr
// (terenkripsi, dibuka lewat cast model) dicocokkan di PHP (lintas database).
class SalaryGrid extends HrmGrid
{
    private array $salaries = [];

    public function slug(): string
    {
        return 'hrm-salary';
    }

    public function title(): string
    {
        return 'Master Gaji & Pajak Pegawai';
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
        return 'hrm.payroll.employee-salary';
    }

    protected function canCreate(): bool
    {
        return false;
    }

    protected function models(): array
    {
        return [EmpEmployee::class, PyrEmployeeSalary::class];
    }

    public function search(): string
    {
        return 'Search name, employee code...';
    }

    protected function query(): Builder
    {
        return EmpEmployee::query()->where('activate', true)->orderBy('full_name');
    }

    protected function prefetch(Collection $items): void
    {
        $this->salaries = PyrEmployeeSalary::query()->whereIn('employee_id', $items->modelKeys())->where('activate', true)
            ->get()->keyBy('employee_id')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Employee No. / Name', 'type' => 'doc', 'party' => 'name'],
            ['key' => 'basic', 'label' => 'Base Salary/Month', 'type' => 'money'],
            ['key' => 'skill', 'label' => 'Skill Allowance/Month', 'type' => 'money'],
            ['key' => 'ptkp', 'label' => 'PTKP'],
            ['key' => 'bpjs', 'label' => 'BPJS', 'type' => 'badge', 'tones' => ['Aktif' => 'success', 'Tidak' => 'neutral']],
            ['key' => 'pph21', 'label' => 'PPh21', 'type' => 'badge', 'tones' => ['Aktif' => 'success', 'Tidak' => 'neutral']],
            ['key' => 'set', 'label' => 'Salary Setup', 'type' => 'badge', 'tones' => ['Sudah' => 'success', 'Belum diatur' => 'warn']],
        ];
    }

    protected function row(Model $m): array
    {
        $s = $this->salaries[$m->getKey()] ?? null;

        return [
            'name' => $m->full_name, 'code' => $m->employee_code,
            'basic' => $s ? (float) $s->basic_salary_monthly : null, 'skill' => $s ? (float) $s->tj_keahlian_monthly : null,
            'ptkp' => $s?->ptkp_status, 'bpjs' => $s?->bpjs_active ? 'Aktif' : 'Tidak', 'pph21' => $s?->pph21_active ? 'Aktif' : 'Tidak',
            'set' => $s ? 'Sudah' : 'Belum diatur',
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'Active Employees'],
            ['label' => 'Not set', 'where' => ['set' => 'Belum diatur'], 'tone' => 'warn'],
            ['label' => 'BPJS active', 'where' => ['bpjs' => 'Aktif'], 'tone' => 'info'],
            ['label' => 'PPh21 active', 'where' => ['pph21' => 'Aktif'], 'tone' => 'success'],
        ];
    }

    public function filters(): array
    {
        return ['ptkp' => 'PTKP', 'set' => 'Salary Setup'];
    }
}
