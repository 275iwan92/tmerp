<?php

/*
 * REMARK
 * Tujuan       : Sisi baca layar v2 hrm-position-form (Master Position): pilihan departemen / level / atasan, dan daftar pemegang jabatan (utama + tambahan).
 * Sumber hulu  : tm2mst.org_department, org_level, org_position, emp_employee (position_id = utama), emp_employee_position (jabatan tambahan).
 * Input        : Jabatan (OrgPosition) bila daftar pemegang diminta.
 * Output       : Array opsi [id => nama] dan daftar [name, code, primary]; tidak menyimpan apa pun.
 * Dipakai oleh : resources/views/v2/screens/hrm-position-form.blade.php, grid PositionGrid (hitungan pemegang).
 * Status       : Aktif.
 */

namespace App\Support\V2\Data;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpEmployeePosition;
use App\Models\Master\OrgDepartment;
use App\Models\Master\OrgLevel;
use App\Models\Master\OrgPosition;

final class PositionDesk
{
    public static function departments(): array
    {
        return OrgDepartment::where('activate', true)->orderBy('department_name')->pluck('department_name', 'department_id')->all();
    }

    public static function levels(): array
    {
        return OrgLevel::where('activate', true)->orderBy('level_rank')->pluck('level_name', 'level_id')->all();
    }

    // Kandidat atasan: semua jabatan aktif kecuali dirinya sendiri; siklus yang lebih dalam dicegah saat simpan.
    public static function reportsTo(?int $exceptId): array
    {
        return OrgPosition::where('activate', true)->when($exceptId, fn ($q) => $q->where('position_id', '!=', $exceptId))->orderBy('position_name')->pluck('position_name', 'position_id')->all();
    }

    /** @return list<array{name: string, code: string, primary: bool}> pegawai aktif yang memegang jabatan ini */
    public static function holders(OrgPosition $p): array
    {
        $main = EmpEmployee::where('activate', true)->where('position_id', $p->position_id)->orderBy('full_name')->get()
            ->map(fn ($e) => ['name' => $e->full_name, 'code' => $e->employee_code, 'primary' => true]);
        $extra = EmpEmployeePosition::with('employee')->where('position_id', $p->position_id)->where('activate', true)->get()
            ->filter(fn ($x) => $x->employee?->activate)->map(fn ($x) => ['name' => $x->employee->full_name, 'code' => $x->employee->employee_code, 'primary' => false])
            ->sortBy('name');

        return $main->concat($extra)->values()->all();
    }

    /** @return array<int, int> position_id => jumlah pegawai aktif (utama + tambahan, satu orang dihitung sekali) */
    public static function holderCounts(array $positionIds): array
    {
        $by = [];
        EmpEmployee::where('activate', true)->whereIn('position_id', $positionIds)->get(['employee_id', 'position_id'])->each(function ($e) use (&$by) {
            $by[$e->position_id][$e->employee_id] = true;
        });
        EmpEmployeePosition::with('employee:employee_id,activate')->whereIn('position_id', $positionIds)->where('activate', true)->get(['employee_id', 'position_id'])->each(function ($x) use (&$by) {
            if ($x->employee?->activate) {
                $by[$x->position_id][$x->employee_id] = true;
            }
        });

        return array_map('count', $by);
    }
}
