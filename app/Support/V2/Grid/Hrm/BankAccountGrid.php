<?php

namespace App\Support\V2\Grid\Hrm;

use App\Models\Master\EmpBankAccount;
use App\Models\Master\EmpEmployee;
use App\Models\Master\SupSupplier;
use App\Services\Payroll\PayrollAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Master Karyawan & Rekening Bank (rekening gaji utama per pegawai aktif; tanpa rekening = dibayar tunai). Daftar ini dibatasi PayrollAccess (screenAllowed + restrict): Direksi semua pegawai, HR Officer hanya Non-staff; Open membuka tab Bank di Master Employee v2 tempat rekening diubah.
 * Sumber hulu  : tm2mst.emp_employee + emp_bank_account + sup_supplier (BANK) + org_position / org_department.
 * Input        : Pencarian, filter Department / Salary Method / Bank, kotak status.
 * Output       : Baris grid (hanya baca; perubahan lewat hrm-employee-form tab Bank, EmployeeBankService).
 * Dipakai oleh : Menu HRM > Payroll (v2:hrm-bank), layar hrm-employee-form.
 * Status       : Aktif. Halaman v1 pages::hrm.payroll-employee-bank sudah dipindah ke tmerp_unused (aturan D.25, T7b HRM).
 */
class BankAccountGrid extends HrmGrid
{
    private array $banks = [];

    public function slug(): string
    {
        return 'hrm-bank';
    }

    public function title(): string
    {
        return t('Master Employee & Bank Account');
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
        return route('v2.screen', 'hrm-employee-form').'?id={id}&tab=bank';
    }

    public function newUrl(): ?string
    {
        return null;
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
        return PayrollAccess::restrict(EmpEmployee::query()->with('position.department')->where('activate', true), auth()->user())->orderBy('full_name');
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
