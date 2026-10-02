<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Master\EmpEmployee;
use App\Models\Master\SalesPerson;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

// Master Sales Person (tm2mst.sales_person): PIC Sales di Inquiry, Quotation dan Customer Order. Dulu tidak ada layar isinya
// (audit 2026-10-02). Inquiry & Quotation menyimpan NAMA-nya, jadi nama yang sudah tersimpan tidak bisa diganti (lockSaved).
class SalesPersonLookup extends MasterDataLookup
{
    public const TYPES = ['INTERNAL' => 'Internal', 'EXTERNAL' => 'External'];

    public function slug(): string
    {
        return 'md-sales-person';
    }

    public function title(): string
    {
        return 'Sales Person';
    }

    public function icon(): string
    {
        return 'fa-solid fa-user-tie';
    }

    public function guide(): ?string
    {
        return 'People chosen as PIC Sales in Inquiry, Quotation and Customer Order. Internal = our employee (choose the employee), External = agent or freelance. '
            .'A saved name cannot be changed because documents refer to it; deactivate it instead.';
    }

    protected function models(): array
    {
        return [SalesPerson::class, EmpEmployee::class];
    }

    protected function query(): Builder
    {
        return SalesPerson::query()->orderBy('sales_type')->orderBy('sales_name');
    }

    // Pegawai aktif + pegawai yang sudah terpasang di baris (supaya pilihan lama tetap terbaca).
    private function employees(): array
    {
        return EmpEmployee::query()->where('activate', true)->orWhereIn('employee_id', SalesPerson::query()->whereNotNull('employee_id')->pluck('employee_id')->all())
            ->orderBy('full_name')->get(['employee_id', 'full_name', 'employee_code'])
            ->mapWithKeys(fn (EmpEmployee $e) => [$e->employee_id => $e->full_name.' · '.$e->employee_code])->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'sales_name', 'label' => 'Sales Name', 'required' => true, 'unique' => true, 'lockSaved' => true, 'width' => '280px'],
            ['key' => 'sales_type', 'label' => 'Type', 'type' => 'select', 'options' => array_map('t', self::TYPES), 'required' => true, 'width' => '130px'],
            ['key' => 'employee_id', 'label' => 'Employee', 'type' => 'select', 'options' => $this->employees(), 'width' => '320px'],
            ['key' => 'phone', 'label' => 'Phone', 'width' => '180px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
        ];
    }

    protected function prepare(array $in): array
    {
        if (isset($in['sales_name']) && is_string($in['sales_name'])) {
            $in['sales_name'] = trim($in['sales_name']);
        }

        return $in;
    }

    protected function rules(): array
    {
        return ['sales_name' => ['string', 'max:150'], 'sales_type' => [Rule::in(array_keys(self::TYPES))],
            'employee_id' => [Rule::exists(EmpEmployee::class, 'employee_id')], 'phone' => ['string', 'max:50']];
    }

    protected function check(array $in, ?int $id): ?string
    {
        return ($in['sales_type'] ?? null) === 'EXTERNAL' && ! empty($in['employee_id']) ? t('An external sales person cannot be linked to an employee.') : null;
    }
}
