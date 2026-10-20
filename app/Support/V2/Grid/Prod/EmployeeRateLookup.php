<?php

/* REMARK
 * Tujuan       : Sheet Target Rate per Pegawai (tab Pegawai, kerja manual): default qty per jam untuk Produk + Part + Proses + Pegawai.
 * Sumber hulu  : prod_part_process_employee_rate (tm2mst) lewat ProdPartProcessEmployeeRate; pegawai aktif (200 pertama menurut nama, sama dengan v1).
 * Input        : sel yang berubah; aturan umum di ProcessRateLookup.
 * Output       : baris rate pegawai.
 * Dipakai oleh : layar v2 production-process-rate (tab Pegawai).
 * Status       : Aktif (T9 pindahan v1, 2026-10-20).
 */

namespace App\Support\V2\Grid\Prod;

use App\Models\Master\EmpEmployee;
use App\Models\Master\ProdPartProcessEmployeeRate;
use Illuminate\Database\Eloquent\Builder;

class EmployeeRateLookup extends ProcessRateLookup
{
    public function slug(): string
    {
        return 'prod-rate-employee';
    }

    public function title(): string
    {
        return 'Employee Rates (Manual)';
    }

    protected function query(): Builder
    {
        return ProdPartProcessEmployeeRate::query()->orderByDesc('rate_id');
    }

    protected function resourceColumn(): array
    {
        return ['key' => 'employee_id', 'label' => 'Employee', 'options' => EmpEmployee::where('activate', true)->orderBy('full_name')->limit(200)->pluck('full_name', 'employee_id')->all()];
    }
}
