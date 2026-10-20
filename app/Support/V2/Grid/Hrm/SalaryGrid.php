<?php

namespace App\Support\V2\Grid\Hrm;

use App\Models\Master\EmpEmployee;
use App\Models\Pyr\PyrEmployeeSalary;
use App\Services\Payroll\PayrollAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Master Employee Salary & Tax: pegawai aktif dengan setelan gaji aktif (nominal hanya untuk yang berhak payroll atas pegawai itu); Open membuka form v2 hrm-salary-form. Tanpa + New Record (baris = pegawai aktif).
 * Sumber hulu  : tm2mst.emp_employee + tm2pyr.pyr_employee_salary (terenkripsi lewat cast model), dicocokkan di PHP (lintas database).
 * Input        : Pencarian, filter PTKP / Salary Setup, kotak status.
 * Output       : Baris grid (hanya baca; perubahan lewat form), unduhan Excel untuk GM / Direksi.
 * Dipakai oleh : Menu HRM > Payroll (v2:hrm-salary), layar hrm-salary-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::hrm.payroll-employee-salary sudah dipindah ke tmerp_unused (aturan D.25, T8 Payroll).
 */
class SalaryGrid extends HrmGrid
{
    private array $salaries = [];

    public function slug(): string
    {
        return 'hrm-salary';
    }

    public function title(): string
    {
        return t('Master Employee Salary & Tax');
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
        return route('v2.screen', 'hrm-salary-form').'?id={id}';
    }

    protected function canCreate(): bool
    {
        return false;
    }

    // Hanya level GM dan Direksi (SecUser::canExportPayrollAmounts) yang mendapat tombol; route-nya sendiri juga menolak yang lain.
    public function excelUrl(): ?string
    {
        return auth()->user()?->canExportPayrollAmounts() ? route('v2.payroll.employee-salary-xlsx') : null;
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
        return PayrollAccess::restrict(EmpEmployee::query()->where('activate', true), auth()->user(), 'employee_id')->orderBy('full_name');
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
            ['key' => 'bpjs', 'label' => 'BPJS', 'type' => 'badge', 'tones' => [t('Active') => 'success', t('No') => 'neutral']],
            ['key' => 'pph21', 'label' => 'PPh21', 'type' => 'badge', 'tones' => [t('Active') => 'success', t('No') => 'neutral']],
            ['key' => 'set', 'label' => 'Salary Setup', 'type' => 'badge', 'tones' => [t('Set') => 'success', t('Not set') => 'warn']],
        ];
    }

    protected function row(Model $m): array
    {
        $s = $this->salaries[$m->getKey()] ?? null;

        return [
            'name' => $m->full_name, 'code' => $m->employee_code,
            'basic' => $s ? (float) $s->basic_salary_monthly : null, 'skill' => $s ? (float) $s->tj_keahlian_monthly : null,
            'ptkp' => $s?->ptkp_status, 'bpjs' => t($s?->bpjs_active ? 'Active' : 'No'), 'pph21' => t($s?->pph21_active ? 'Active' : 'No'),
            'set' => t($s ? 'Set' : 'Not set'),
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'Active Employees'],
            ['label' => 'Not set', 'where' => ['set' => t('Not set')], 'tone' => 'warn'],
            ['label' => 'BPJS active', 'where' => ['bpjs' => t('Active')], 'tone' => 'info'],
            ['label' => 'PPh21 active', 'where' => ['pph21' => t('Active')], 'tone' => 'success'],
        ];
    }

    public function filters(): array
    {
        return ['ptkp' => 'PTKP', 'set' => 'Salary Setup'];
    }
}
