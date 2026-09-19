<?php

namespace App\Services\Hrm;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpRosterScheduleDepartment;
use App\Models\Master\EmpShift;
use App\Models\Master\EmpShiftRoster;
use App\Models\Master\EmpWorkCenter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// Tampilan "Formasi Harian" roster = bentuk sheet formasi HR (Posisi x Jam Kerja
// x Tanggal, isi nama panggilan). Beda dari papan Roster Mingguan (1 pegawai =
// 1 shift seminggu): di sini tiap SEL = 1 unit + 1 shift + 1 tanggal, jadi
// pergantian tengah minggu & borongan yang pindah titik di hari yang sama
// (dipanggil saat kerja berlebih) tercatat apa adanya. Datanya tetap baris
// emp_shift_roster per hari -- sumber yang sama dgn papan, presensi & payroll.
class RosterFormationService
{
    /** @return Carbon[] Senin..Minggu */
    public function dates(Carbon $weekStart): array
    {
        return array_map(fn ($i) => $weekStart->copy()->addDays($i), range(0, 6));
    }

    /**
     * @return array<int, array{wc: EmpWorkCenter, shift: ?EmpShift, span: int, first: bool, cells: array<string, array<int, array{id:int,label:string,name:string,code:string,multi:bool}>>}>
     */
    public function matrix(int $scheduleId, Carbon $weekStart, bool $allUnits): array
    {
        $end = $weekStart->copy()->addDays(6);
        $rows = EmpShiftRoster::where('schedule_id', $scheduleId)
            ->whereBetween('roster_date', [$weekStart->toDateString(), $end->toDateString()])
            ->get(['employee_id', 'work_center_id', 'shift_id', 'roster_date']);

        $employees = EmpEmployee::whereIn('employee_id', $rows->pluck('employee_id')->unique()->all() ?: [0])
            ->get(['employee_id', 'employee_code', 'full_name', 'nickname'])->keyBy('employee_id');

        // Orang yang di hari yang sama tercatat di >1 titik (borongan pindah titik).
        $perDay = $rows->groupBy(fn ($r) => $r->employee_id.'|'.$r->roster_date->toDateString())->map->count();

        $cells = [];
        foreach ($rows as $r) {
            $e = $employees[$r->employee_id] ?? null;
            if (! $e) {
                continue;
            }
            $date = $r->roster_date->toDateString();
            $cells[$r->work_center_id][$r->shift_id][$date][] = [
                'id' => $e->employee_id,
                'label' => $e->nickname ?: $e->full_name,
                'name' => $e->full_name,
                'code' => $e->employee_code,
                'multi' => $perDay[$e->employee_id.'|'.$date] > 1,
            ];
        }

        $deptIds = EmpRosterScheduleDepartment::where('schedule_id', $scheduleId)->pluck('department_id');
        $centers = EmpWorkCenter::where('activate', true)
            ->where(fn ($q) => $allUnits
                ? $q->whereIn('department_id', $deptIds)->orWhereIn('work_center_id', array_keys($cells) ?: [0])
                : $q->whereIn('work_center_id', array_keys($cells) ?: [0]))
            ->orderBy('sort_order')->orderBy('work_center_name')->get();

        $shifts = EmpShift::with('details')->whereIn('shift_id', $rows->pluck('shift_id')->unique()->all() ?: [0])
            ->orderBy('shift_code')->get()->keyBy('shift_id');

        $out = [];
        foreach ($centers as $wc) {
            $shiftIds = array_keys($cells[$wc->work_center_id] ?? []);
            usort($shiftIds, fn ($a, $b) => strcmp($shifts[$a]->shift_code ?? '', $shifts[$b]->shift_code ?? ''));
            $shiftIds = $shiftIds ?: [null];
            foreach ($shiftIds as $i => $sid) {
                $out[] = [
                    'wc' => $wc,
                    'shift' => $sid ? $shifts[$sid] : null,
                    'span' => count($shiftIds),
                    'first' => $i === 0,
                    'cells' => $sid ? ($cells[$wc->work_center_id][$sid] ?? []) : [],
                ];
            }
        }

        return $out;
    }

    /**
     * Isi 1 unit + 1 shift untuk tanggal-tanggal $dates persis = $employeeIds
     * (yang tidak ada di daftar dihapus dari sel itu, yang baru ditambahkan).
     *
     * @param  string[]  $dates
     * @param  int[]  $employeeIds
     */
    public function assign(int $scheduleId, int $workCenterId, int $shiftId, array $dates, array $employeeIds, string $actor): void
    {
        $wc = EmpWorkCenter::with('department')->findOrFail($workCenterId);
        $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));

        DB::connection('tm2mst')->transaction(function () use ($scheduleId, $wc, $shiftId, $dates, $employeeIds, $actor) {
            foreach ($dates as $date) {
                $base = EmpShiftRoster::where('schedule_id', $scheduleId)->where('work_center_id', $wc->work_center_id)
                    ->where('shift_id', $shiftId)->whereDate('roster_date', $date);

                (clone $base)->whereNotIn('employee_id', $employeeIds ?: [0])->delete();
                $existing = (clone $base)->pluck('employee_id')->map(fn ($v) => (int) $v)->all();

                $new = [];
                foreach (array_diff($employeeIds, $existing) as $employeeId) {
                    $new[] = [
                        'employee_id' => $employeeId, 'division_id' => $wc->department?->division_id,
                        'machine_id' => $wc->machine_id, 'work_center_id' => $wc->work_center_id,
                        'schedule_id' => $scheduleId, 'shift_id' => $shiftId, 'roster_date' => $date,
                        'assigned_by_role' => 'HR', 'is_overtime' => 0,
                        'created_at' => now(), 'created_by' => $actor, 'updated_at' => now(), 'updated_by' => $actor,
                    ];
                }
                if ($new) {
                    EmpShiftRoster::insert($new);
                }
            }
        });
    }

    // Salin seluruh formasi 1 jadwal dari minggu $from ke minggu $to (harus kosong).
    public function copyWeek(int $scheduleId, Carbon $from, Carbon $to, string $actor): int
    {
        $range = fn (Carbon $s) => [$s->toDateString(), $s->copy()->addDays(6)->toDateString()];
        if (EmpShiftRoster::where('schedule_id', $scheduleId)->whereBetween('roster_date', $range($to))->exists()) {
            throw new \RuntimeException('Minggu tujuan sudah berisi jadwal.');
        }

        $shiftDays = (int) $from->diffInDays($to, false);
        $rows = EmpShiftRoster::where('schedule_id', $scheduleId)->whereBetween('roster_date', $range($from))->get()
            ->map(fn ($r) => [
                'employee_id' => $r->employee_id, 'division_id' => $r->division_id, 'machine_id' => $r->machine_id,
                'work_center_id' => $r->work_center_id, 'schedule_id' => $scheduleId, 'shift_id' => $r->shift_id,
                'roster_date' => $r->roster_date->copy()->addDays($shiftDays)->toDateString(),
                'assigned_by_role' => $r->assigned_by_role, 'is_overtime' => $r->is_overtime,
                'created_at' => now(), 'created_by' => $actor, 'updated_at' => now(), 'updated_by' => $actor,
            ])->all();

        foreach (array_chunk($rows, 300) as $chunk) {
            EmpShiftRoster::insert($chunk);
        }

        return count($rows);
    }
}
