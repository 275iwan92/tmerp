<?php

namespace App\Support\V2\Grid\Hrm;

use App\Models\Master\OrgCompany;
use App\Models\Master\OrgDepartment;
use App\Models\Master\OrgDivision;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

// Master Department (tm2mst.org_department): dipilih di Master Posisi, Purchase Requisition, Shift, Roster, Stock Out,
// Permohonan Tenaga Kerja, SOP. Dulu hanya ditulis ETL migrasi pegawai -- tidak ada layar isinya (audit 2026-10-02).
class DepartmentLookup extends DbLookup
{
    public function slug(): string
    {
        return 'hrm-department';
    }

    public function title(): string
    {
        return 'Department';
    }

    public function module(): string
    {
        return 'HRM';
    }

    public function moduleLabel(): string
    {
        return 'Human Resources';
    }

    public function group(): string
    {
        return 'Organization';
    }

    public function icon(): string
    {
        return 'fa-solid fa-people-group';
    }

    public function guide(): ?string
    {
        return 'Departments chosen in Master Position, Purchase Requisition, Shift, Roster and Stock Out. Division is optional (empty = serves all divisions). '
            .'A saved code cannot be changed because programs and documents refer to it; deactivate it instead.';
    }

    protected function models(): array
    {
        return [OrgDepartment::class, OrgDivision::class];
    }

    protected function query(): Builder
    {
        return OrgDepartment::query()->orderBy('department_name');
    }

    public function columns(): array
    {
        $divisions = OrgDivision::query()->where('activate', true)->orWhereIn('division_id', OrgDepartment::query()->whereNotNull('division_id')->pluck('division_id')->all())
            ->orderBy('division_name')->pluck('division_name', 'division_id')->all();

        return [
            ['key' => 'department_code', 'label' => 'Code', 'required' => true, 'unique' => true, 'lockSaved' => true, 'case' => 'uppercase', 'width' => '180px'],
            ['key' => 'department_name', 'label' => 'Department Name', 'required' => true, 'width' => '320px'],
            ['key' => 'division_id', 'label' => 'Division', 'type' => 'select', 'options' => $divisions, 'width' => '220px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
        ];
    }

    protected function prepare(array $in): array
    {
        if (isset($in['department_code']) && is_string($in['department_code'])) {
            $in['department_code'] = strtoupper(trim($in['department_code']));
        }

        return $in;
    }

    protected function rules(): array
    {
        return ['department_code' => ['string', 'max:20'], 'department_name' => ['string', 'max:150'], 'division_id' => [Rule::exists(OrgDivision::class, 'division_id')]];
    }

    // Perusahaannya satu (Settings > Identifikasi): baris baru otomatis milik perusahaan itu.
    protected function defaults(): array
    {
        return ['company_id' => OrgCompany::query()->where('activate', true)->orderBy('company_id')->value('company_id')];
    }
}
