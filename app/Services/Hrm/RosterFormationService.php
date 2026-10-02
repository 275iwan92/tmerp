<?php

namespace App\Services\Hrm;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpEmployeeWorkCenterSkill;
use App\Models\Master\EmpRosterScheduleDepartment;
use App\Models\Master\EmpShift;
use App\Models\Master\EmpShiftRoster;
use App\Models\Master\EmpWorkCenter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// Tampilan "Formasi Harian" roster = bentuk sheet formasi HR (Posisi x Jam Kerja
// x Tanggal, isi nama panggilan) -- desain disetujui user 2026-09-19
// (docs/mockups/mockup_roster_formasi_harian.jpg). Beda dari papan Roster
// Mingguan (1 pegawai = 1 shift seminggu): di sini tiap baris = 1 unit + 1 shift
// dan tiap orang punya hari masuknya sendiri, jadi pergantian tengah minggu &
// borongan yang dipanggil saat kerja berlebih (pindah titik di hari yang sama)
// tercatat apa adanya. Datanya tetap baris emp_shift_roster per hari -- sumber
// yang sama dgn papan, presensi & payroll.
class RosterFormationService
{
    // Unit sheet formasi HR diberi sort_order 1..999 (migration 2026_09_19_000104);
    // unit lain >= 1000. Unit formasi selalu tampil walau kosong (baris "Kosong").
    public const FORMASI_SORT_LIMIT = 1000;

    private const DAY_SHORT = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    /** @return Carbon[] Senin..Minggu */
    public function dates(Carbon $weekStart): array
    {
        return array_map(fn ($i) => $weekStart->copy()->addDays($i), range(0, 6));
    }

    /**
     * @return array<int, array<string, mixed>> 1 baris per unit+shift:
     *                                          wc, shift, span, first, empty, people[id => {label,name,code,borongan,skill,days[date],multi[date=>unit]}],
     *                                          cells[date => ids], summary{base, groups[{label, ids}], handover}
     */
    public function matrix(int $scheduleId, Carbon $weekStart, bool $allUnits): array
    {
        $dates = array_map(fn ($d) => $d->toDateString(), $this->dates($weekStart));
        $rows = EmpShiftRoster::where('schedule_id', $scheduleId)
            ->whereBetween('roster_date', [$dates[0], $dates[6]])
            ->get(['employee_id', 'work_center_id', 'shift_id', 'roster_date']);

        $employees = EmpEmployee::with('position')->whereIn('employee_id', $rows->pluck('employee_id')->unique()->all() ?: [0])
            ->get()->keyBy('employee_id');
        $skills = EmpEmployeeWorkCenterSkill::where('activate', true)
            ->whereIn('employee_id', $employees->keys()->all() ?: [0])->get()
            ->groupBy('employee_id')->map(fn ($r) => $r->pluck('work_center_id')->map(fn ($v) => (int) $v)->all());
        $centerNames = EmpWorkCenter::pluck('work_center_name', 'work_center_id');

        // employee|date => [work_center_id, ...] utk tanda "juga di titik lain hari ini"
        $placesPerDay = $rows->groupBy(fn ($r) => $r->employee_id.'|'.$r->roster_date->toDateString())
            ->map(fn ($g) => $g->pluck('work_center_id')->map(fn ($v) => (int) $v)->unique()->values()->all());

        $grid = [];
        foreach ($rows as $r) {
            $e = $employees[$r->employee_id] ?? null;
            if (! $e) {
                continue;
            }
            $wc = (int) $r->work_center_id;
            $sid = (int) $r->shift_id;
            $date = $r->roster_date->toDateString();
            $p = &$grid[$wc][$sid]['people'][$e->employee_id];
            $p ??= [
                'label' => $e->nickname ?: $e->full_name, 'name' => $e->full_name, 'code' => $e->employee_code,
                'borongan' => $e->position?->position_code === 'BORONGAN',
                'skill' => in_array($wc, $skills[$e->employee_id] ?? [], true),
                'days' => [], 'multi' => [],
            ];
            $p['days'][$date] = true;
            $others = array_diff($placesPerDay[$e->employee_id.'|'.$date], [$wc]);
            if ($others) {
                $p['multi'][$date] = $centerNames[reset($others)] ?? '?';
            }
            $grid[$wc][$sid]['cells'][$date][] = $e->employee_id;
            unset($p);
        }

        $deptIds = EmpRosterScheduleDepartment::where('schedule_id', $scheduleId)->pluck('department_id');
        $centers = EmpWorkCenter::where('activate', true)
            ->where(fn ($q) => $q->where('sort_order', '<', self::FORMASI_SORT_LIMIT)
                ->orWhereIn('work_center_id', array_keys($grid) ?: [0])
                ->when($allUnits, fn ($w) => $w->orWhereIn('department_id', $deptIds)))
            ->orderBy('sort_order')->orderBy('work_center_name')->get();

        $shifts = EmpShift::with('details')->whereIn('shift_id', $rows->pluck('shift_id')->unique()->all() ?: [0])
            ->get()->keyBy('shift_id');

        $out = [];
        foreach ($centers as $wc) {
            $shiftIds = array_keys($grid[$wc->work_center_id] ?? []);
            usort($shiftIds, fn ($a, $b) => strcmp($shifts[$a]->shift_code ?? '', $shifts[$b]->shift_code ?? ''));
            $shiftIds = $shiftIds ?: [null];
            foreach ($shiftIds as $i => $sid) {
                $cell = $sid ? $grid[$wc->work_center_id][$sid] : ['people' => [], 'cells' => []];
                $out[] = [
                    'wc' => $wc,
                    'shift' => $sid ? $shifts[$sid] : null,
                    'span' => count($shiftIds),
                    'first' => $i === 0,
                    'empty' => $sid === null,
                    'people' => $cell['people'],
                    'cells' => $cell['cells'],
                    'summary' => $sid ? $this->summarize($cell['people'], $shifts[$sid], $dates) : null,
                ];
            }
        }

        return $out;
    }

    // Ringkas seminggu: orang yang masuk di SEMUA hari kerja shift = "tetap";
    // sisanya dikelompokkan per himpunan hari ("Sen–Rab", "Sen saja").
    // Kalau tidak ada yang tetap & kelompoknya tidak beririsan = pergantian.
    private function summarize(array $people, EmpShift $shift, array $dates): array
    {
        $pattern = $shift->dayPattern();
        $workDates = array_values(array_filter($dates, fn ($d, $i) => $pattern[$i]['on'], ARRAY_FILTER_USE_BOTH));

        $base = [];
        $groups = [];
        foreach ($people as $id => $p) {
            $mine = array_values(array_intersect($dates, array_keys($p['days'])));
            if ($workDates && ! array_diff($workDates, $mine)) {
                $base[] = $id;

                continue;
            }
            $key = implode(',', $mine);
            $groups[$key] ??= ['label' => $this->dayLabel($mine, $dates), 'first' => array_search($mine[0], $dates, true), 'ids' => [], 'dates' => $mine];
            $groups[$key]['ids'][] = $id;
        }
        uasort($groups, fn ($a, $b) => $a['first'] <=> $b['first']);
        $groups = array_values($groups);

        $handover = ! $base && count($groups) > 1;
        for ($i = 1; $handover && $i < count($groups); $i++) {
            $handover = ! array_intersect($groups[$i - 1]['dates'], $groups[$i]['dates']);
        }

        return ['base' => $base, 'groups' => $groups, 'handover' => $handover];
    }

    private function dayLabel(array $mine, array $dates): string
    {
        $idx = array_map(fn ($d) => array_search($d, $dates, true), $mine);
        if (count($idx) === 1) {
            return str_replace(':day', t(self::DAY_SHORT[$idx[0]]), t(':day only'));
        }
        if ($idx === range($idx[0], end($idx))) {
            return t(self::DAY_SHORT[$idx[0]]).'–'.t(self::DAY_SHORT[end($idx)]);
        }

        return implode(', ', array_map(fn ($i) => t(self::DAY_SHORT[$i]), $idx));
    }

    /** @return array{today:int, shifts: array<int, array{shift: EmpShift, count:int}>, borongan:int, empty: string[], unscheduled:int} */
    public function stats(array $matrix, string $focusDate, int $unscheduled): array
    {
        $today = [];
        $byShift = [];
        $borongan = [];
        $empty = [];
        foreach ($matrix as $row) {
            if ($row['empty']) {
                $empty[] = $row['wc']->work_center_name;

                continue;
            }
            foreach ($row['cells'][$focusDate] ?? [] as $id) {
                $today[$id] = true;
                $byShift[$row['shift']->shift_id]['shift'] = $row['shift'];
                $byShift[$row['shift']->shift_id]['ids'][$id] = true;
                if ($row['people'][$id]['borongan']) {
                    $borongan[$id] = true;
                }
            }
        }
        // Shift bernomor dulu (SH1, SH2, ...), Reguler & lainnya di belakang.
        $order = fn ($s) => (str_starts_with($s->shift_code, 'SH') ? '0' : '1').$s->shift_code;
        uasort($byShift, fn ($a, $b) => strcmp($order($a['shift']), $order($b['shift'])));

        return [
            'today' => count($today),
            'shifts' => array_map(fn ($s) => ['shift' => $s['shift'], 'count' => count($s['ids'])], array_values($byShift)),
            'borongan' => count($borongan),
            'empty' => $empty,
            'unscheduled' => $unscheduled,
        ];
    }

    // Karyawan aktif mingguan di departemen jadwal ini yang belum punya jadwal
    // minggu tsb. Borongan tidak dihitung (dipanggil hanya saat kerja berlebih).
    public function unscheduled(int $scheduleId, Carbon $weekStart, ?int $departmentId = null)
    {
        $deptIds = $departmentId ? [$departmentId] : EmpRosterScheduleDepartment::where('schedule_id', $scheduleId)->pluck('department_id')->all();
        $scheduled = EmpShiftRoster::whereBetween('roster_date', [$weekStart->toDateString(), $weekStart->copy()->addDays(6)->toDateString()])
            ->distinct()->pluck('employee_id');

        return EmpEmployee::with('position.department')
            ->where('activate', true)->where('payment_frequency', 'WEEKLY')
            ->whereNotIn('employee_id', $scheduled->all() ?: [0])
            ->whereHas('position', fn ($q) => $q->whereIn('department_id', $deptIds ?: [0])->where('position_code', '<>', 'BORONGAN'))
            ->orderBy('full_name')->get();
    }

    /**
     * Simpan 1 baris formasi (unit + shift) seminggu dari centang orang x hari.
     *
     * @param  array<int|string, array<string, bool>>  $grid  employee_id => [Y-m-d => true]
     */
    public function saveRow(int $scheduleId, int $workCenterId, int $shiftId, Carbon $weekStart, array $grid, string $actor): void
    {
        DB::connection('tm2mst')->transaction(function () use ($scheduleId, $workCenterId, $shiftId, $weekStart, $grid, $actor) {
            foreach ($this->dates($weekStart) as $d) {
                $date = $d->toDateString();
                $ids = array_keys(array_filter($grid, fn ($days) => ! empty($days[$date])));
                $this->assign($scheduleId, $workCenterId, $shiftId, [$date], $ids, $actor);
            }
        });
    }

    /**
     * Isi 1 unit + 1 shift untuk tanggal-tanggal $dates persis = $employeeIds.
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
            throw new \RuntimeException(t('The target week already has a schedule.'));
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
