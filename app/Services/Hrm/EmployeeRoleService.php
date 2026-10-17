<?php

/*
 * REMARK
 * Tujuan       : Satu pegawai memegang lebih dari satu jabatan: daftar jabatan (utama + tambahan), tambah, lepas, dan jadikan utama. Posisi utama tetap emp_employee.position_id.
 * Sumber hulu  : tm2mst.emp_employee (position_id = utama), tm2mst.emp_employee_position (jabatan tambahan), tm2mst.org_position.
 * Input        : Pegawai, id jabatan, tanggal mulai opsional, user yang bertindak.
 * Output       : Baris emp_employee_position berubah; Make primary menukar posisi utama (approval, struktur organisasi, payroll mengikuti posisi utama); ValidationException bila aturan dilanggar.
 * Dipakai oleh : routes/v2.php (hrm.employee.position.*), layar hrm-employee-form tab Job, tes EmployeeRoleServiceTest.
 * Status       : Aktif. Hanya posisi utama yang menentukan approval, struktur organisasi, dan payroll (keputusan tahap A); jabatan lain menambah job description, KPI, dan skor.
 */

namespace App\Services\Hrm;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpEmployeePosition;
use App\Models\Master\OrgPosition;
use App\Models\Sys\SecUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class EmployeeRoleService
{
    /** @return Collection<int, array{position: OrgPosition, primary: bool, since: mixed}> posisi utama dulu, lalu tambahan menurut tanggal mulai */
    public function held(EmpEmployee $e): Collection
    {
        $rows = collect();
        if ($primary = $e->position()->with('department')->first()) {
            $rows->push(['position' => $primary, 'primary' => true, 'since' => $e->join_date]);
        }
        EmpEmployeePosition::with('position.department')->where('employee_id', $e->employee_id)->where('activate', true)->orderByRaw('since_date is null')->orderBy('since_date')->orderBy('id_employee_position')->get()
            ->each(fn (EmpEmployeePosition $x) => $x->position ? $rows->push(['position' => $x->position, 'primary' => false, 'since' => $x->since_date]) : null);

        return $rows;
    }

    public function add(EmpEmployee $e, int $positionId, ?string $since, SecUser $user): EmpEmployeePosition
    {
        Validator::make(['position_id' => $positionId, 'since_date' => $since], [
            'position_id' => ['required', 'integer', 'exists:tm2mst.org_position,position_id'],
            'since_date' => ['nullable', 'date'],
        ])->validate();

        if ($positionId === (int) $e->position_id) {
            throw ValidationException::withMessages(['position_id' => 'This is already the primary position.']);
        }

        $row = EmpEmployeePosition::firstOrNew(['employee_id' => $e->employee_id, 'position_id' => $positionId]);
        if ($row->exists && $row->activate) {
            throw ValidationException::withMessages(['position_id' => 'This position is already held.']);
        }
        $row->fill(['since_date' => $since ?: null, 'activate' => true, 'updated_by' => $user->login]);
        $row->created_by ??= $user->login;
        $row->save();

        return $row;
    }

    public function remove(EmpEmployee $e, int $positionId, SecUser $user): void
    {
        if ($positionId === (int) $e->position_id) {
            throw ValidationException::withMessages(['position_id' => 'The primary position cannot be removed. Make another position primary first.']);
        }
        $row = EmpEmployeePosition::where('employee_id', $e->employee_id)->where('position_id', $positionId)->where('activate', true)->first()
            ?? throw ValidationException::withMessages(['position_id' => 'This position is not held by the employee.']);
        $row->update(['activate' => false, 'updated_by' => $user->login]);
    }

    // Tukar: posisi utama lama menjadi jabatan tambahan, jabatan tambahan terpilih menjadi utama.
    public function makePrimary(EmpEmployee $e, int $positionId, SecUser $user): void
    {
        $row = EmpEmployeePosition::where('employee_id', $e->employee_id)->where('position_id', $positionId)->where('activate', true)->first()
            ?? throw ValidationException::withMessages(['position_id' => 'Only a position the employee already holds can become primary.']);

        DB::connection('tm2mst')->transaction(function () use ($e, $row, $positionId, $user) {
            $old = (int) $e->position_id;
            $row->update(['activate' => false, 'updated_by' => $user->login]);
            if ($old) {
                $prev = EmpEmployeePosition::firstOrNew(['employee_id' => $e->employee_id, 'position_id' => $old]);
                $prev->fill(['activate' => true, 'updated_by' => $user->login]);
                $prev->created_by ??= $user->login;
                $prev->save();
            }
            $e->update(['position_id' => $positionId, 'updated_by' => $user->login]);
        });
    }
}
