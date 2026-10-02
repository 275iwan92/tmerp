<?php

namespace App\Support\V2\Grid\Hrm;

use App\Models\Master\EmpBankAccount;
use App\Models\Master\EmpEmployee;
use App\Models\Master\SupSupplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Master Karyawan & Rekening Bank (v1: pages::hrm.payroll-employee-bank). Tanpa rekening utama = dibayar tunai.
class BankAccountGrid extends HrmGrid
{
    private array $banks = [];

    public function slug(): string
    {
        return 'hrm-bank';
    }

    public function title(): string
    {
        return t('Employee & Bank Account Master');
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
        return 'hrm.payroll.employee-bank';
    }

    protected function canCreate(): bool
    {
        return false;
    }

    protected function models(): array
    {
        return [EmpEmployee::class, EmpBankAccount::class, SupSupplier::class];
    }

    public function search(): string
    {
        return 'Search name, code, account no....';
    }

    protected function query(): Builder
    {
        return EmpEmployee::query()->with('position.department')->where('activate', true)->orderBy('full_name');
    }

    protected function prefetch(Collection $items): void
    {
        $this->banks = EmpBankAccount::query()->with('bank')->whereIn('employee_id', $items->modelKeys())
            ->where('is_primary', true)->where('activate', true)->get()->keyBy('employee_id')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Employee No. / Name', 'type' => 'doc', 'party' => 'name'],
            ['key' => 'dept', 'label' => 'Department'],
            ['key' => 'position', 'label' => 'Position'],
            ['key' => 'method', 'label' => 'Salary Method', 'type' => 'badge', 'tones' => ['Transfer' => 'info', t('Cash') => 'warn']],
            ['key' => 'bank', 'label' => 'Bank'],
            ['key' => 'account', 'label' => 'Account No.'],
            ['key' => 'holder', 'label' => 'Account Holder'],
        ];
    }

    protected function row(Model $m): array
    {
        $b = $this->banks[$m->getKey()] ?? null;

        return [
            'name' => $m->full_name, 'code' => $m->employee_code, 'dept' => $m->position?->department?->department_name,
            'position' => $m->position?->position_name, 'method' => $b ? 'Transfer' : t('Cash'),
            'bank' => $b?->bank?->supplier_name ?? $b?->bank_name, 'account' => $b?->account_no, 'holder' => $b?->account_holder_name,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'Active Employees'],
            ['label' => 'Transfer', 'where' => ['method' => 'Transfer'], 'tone' => 'info'],
            ['label' => 'Cash', 'where' => ['method' => t('Cash')], 'tone' => 'warn'],
        ];
    }

    public function filters(): array
    {
        return ['dept' => 'Department', 'method' => 'Salary Method', 'bank' => 'Bank'];
    }
}
