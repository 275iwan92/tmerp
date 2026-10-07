<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpEmployeeWorkCenterSkill;
use App\Models\Master\EmpRosterSchedule;
use App\Models\Master\EmpRosterScheduleDepartment;
use App\Models\Master\EmpShift;
use App\Models\Master\EmpShiftRoster;
use App\Models\Master\EmpShiftRosterBatch;
use App\Models\Master\EmpWorkCenter;
use App\Models\Master\OrgDepartment;
use App\Models\Sys\SysGridPreference;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

// HRM > Time Attendance > Shift Roster (v2): papan roster mingguan. Aturan sama dengan layar v1:
//  - 1 penempatan = 1 pegawai di 1 shift untuk 1 MINGGU penuh dalam 1 Jadwal; ditulis 1 baris emp_shift_roster per HARI AKTIF shift
//    (jadi AttendanceCalculationService tidak berubah). 1 pegawai hanya boleh 1 shift per minggu dalam satu jadwal (lintas unit kerja).
//  - Lingkup: Jadwal -> Departemen (kosong = semua) -> Unit kerja (emp_work_center); katalog shift global (Master Shift).
//  - Siklus: DRAFT (Save) -> CONFIRMED (Publish & Lock, papan terkunci) -> Unlock kembali ke DRAFT (emp_shift_roster_batch per jadwal + minggu).
//  - Shift tanpa jam kerja satu hari pun tidak boleh dipakai; shift yang disembunyikan user = setelan tampilan per user (ikut cetakan v1).
// Papan = objek x-mp::roster-board; Save mengirim seluruh isi papan (id pegawai => kunci shift) dan server menerapkan selisihnya.
final class ShiftRoster
{
    public const HIDDEN_SHIFTS_KEY = 'hrm.shift-roster.hidden-shifts';

    public const TONES = ['success', 'accent', 'warn', 'violet', 'danger', 'info'];

    private function __construct(public readonly ?int $scheduleId, public readonly ?int $departmentId, public readonly ?int $workCenterId, public readonly int $weekOffset) {}

    public static function for(?int $scheduleId, ?int $departmentId, ?int $workCenterId, int $weekOffset): self
    {
        $schedule = ($scheduleId && EmpRosterSchedule::where('activate', true)->whereKey($scheduleId)->exists())
            ? $scheduleId : EmpRosterSchedule::where('activate', true)->orderBy('schedule_id')->value('schedule_id');
        $dept = $departmentId && $schedule && EmpRosterScheduleDepartment::where('schedule_id', $schedule)->where('department_id', $departmentId)->exists() ? $departmentId : null;
        $self = new self($schedule ? (int) $schedule : null, $dept, null, $weekOffset);
        $centers = $self->workCenters();
        $wc = $workCenterId && $centers->firstWhere('work_center_id', $workCenterId) ? $workCenterId
            : ($centers->first(fn ($c) => collect($self->placements())->pluck('work_center_id')->contains((int) $c->work_center_id))?->work_center_id ?? $centers->first()?->work_center_id);

        return new self($self->scheduleId, $dept, $wc ? (int) $wc : null, $weekOffset);
    }

    // ---------- lingkup & minggu ----------

    public function schedules(): Collection
    {
        return EmpRosterSchedule::where('activate', true)->orderBy('schedule_name')->get();
    }

    public function departments(): Collection
    {
        $ids = EmpRosterScheduleDepartment::where('schedule_id', $this->scheduleId)->pluck('department_id');

        return OrgDepartment::whereIn('department_id', $ids)->where('activate', true)->orderBy('department_name')->get();
    }

    public function workCenters(): Collection
    {
        return EmpWorkCenter::with('department')->where('activate', true)->when($this->departmentId, fn ($q) => $q->where('department_id', $this->departmentId))
            ->orderBy('department_id')->orderBy('sort_order')->orderBy('work_center_name')->get();
    }

    public function weekStart(): Carbon
    {
        return Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeeks($this->weekOffset);
    }

    public function weekEnd(): Carbon
    {
        return $this->weekStart()->copy()->addDays(6);
    }

    public function weekNumber(): int
    {
        return (int) $this->weekStart()->isoWeek();
    }

    // ---------- shift ----------

    /** @return Collection<int, EmpShift> katalog global aktif */
    public function shifts(): Collection
    {
        return EmpShift::with('details')->where('activate', true)->orderBy('shift_code')->orderBy('shift_name')->get();
    }

    /** @return list<int> shift_id yang disembunyikan user ini */
    public static function hiddenShifts(string $login): array
    {
        $row = SysGridPreference::where('user_login', $login)->where('grid_key', self::HIDDEN_SHIFTS_KEY)->first();

        return collect($row->visible_columns ?? [])->map(fn ($v) => (int) $v)->all();
    }

    public static function toggleShift(string $login, int $shiftId): bool
    {
        $hidden = self::hiddenShifts($login);
        $nowHidden = ! in_array($shiftId, $hidden, true);
        $hidden = $nowHidden ? [...$hidden, $shiftId] : array_values(array_diff($hidden, [$shiftId]));
        SysGridPreference::updateOrCreate(['user_login' => $login, 'grid_key' => self::HIDDEN_SHIFTS_KEY], ['visible_columns' => array_values($hidden)]);

        return $nowHidden;
    }

    // ---------- data papan ----------

    /** @return array<int, array{shift_id: int, work_center_id: int}> pegawai => penempatan, seluruh jadwal minggu ini */
    public function placements(): array
    {
        if (! $this->scheduleId) {
            return [];
        }

        return EmpShiftRoster::query()->where('schedule_id', $this->scheduleId)->whereBetween('roster_date', [$this->weekStart()->toDateString(), $this->weekEnd()->toDateString()])
            ->get()->groupBy('employee_id')->map(fn ($rows) => ['shift_id' => (int) $rows->first()->shift_id, 'work_center_id' => (int) $rows->first()->work_center_id])->all();
    }

    public function batch(): ?EmpShiftRosterBatch
    {
        return $this->scheduleId ? EmpShiftRosterBatch::where('schedule_id', $this->scheduleId)->where('date_from', $this->weekStart()->toDateString())
            ->where('date_to', $this->weekEnd()->toDateString())->first() : null;
    }

    public function isLocked(): bool
    {
        return $this->batch()?->status === 'CONFIRMED';
    }

    /** @return array{shifts: list<array<string, mixed>>, days: list<array<string, mixed>>, people: list<array<string, mixed>>, assign: array<int, string>} */
    public function board(string $login): array
    {
        $hidden = self::hiddenShifts($login);
        $dayKeys = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $days = collect($dayKeys)->map(fn ($k, $i) => ['key' => $k, 'label' => t($k), 'date' => $this->weekStart()->copy()->addDays($i)->format('d/m'), 'off' => $i === 6])->all();

        $shifts = $this->shifts()->reject(fn (EmpShift $s) => in_array($s->shift_id, $hidden, true))->values()->map(function (EmpShift $s, int $i) use ($dayKeys) {
            $pattern = [];
            foreach ($s->dayPattern() as $idx => $d) {
                $pattern[$dayKeys[$idx]] = $d['on'] ? [$d['in'], $d['out']] : null;
            }

            return ['key' => (string) $s->shift_id, 'label' => $s->shift_name, 'tone' => self::TONES[$i % count(self::TONES)], 'capacity' => $s->capacity(), 'pattern' => $pattern];
        })->all();

        $placed = $this->placements();
        $mine = array_filter($placed, fn ($p) => $p['work_center_id'] === (int) $this->workCenterId);
        $hiddenPlaced = array_keys(array_filter($mine, fn ($p) => in_array($p['shift_id'], $hidden, true)));
        $others = [...array_keys(array_diff_key($placed, $mine)), ...$hiddenPlaced];
        $skills = $this->skillCounts();

        $people = EmpEmployee::with('position')->where(function ($q) use ($mine) {
            $q->whereIn('employee_id', array_keys($mine) ?: [0]);
            $this->departmentId ? $q->orWhereHas('position', fn ($p) => $p->where('department_id', $this->departmentId)) : $q->orWhere('activate', true);
        })->whereNotIn('employee_id', $others ?: [0])->orderBy('full_name')->get()
            ->filter(fn (EmpEmployee $e) => $e->activate || isset($mine[$e->employee_id]))
            ->map(fn (EmpEmployee $e) => ['id' => $e->employee_id, 'name' => $e->full_name, 'code' => (string) $e->employee_code, 'role' => $e->position?->position_name ?? t('No position'),
                'skills' => $skills[$e->employee_id] ?? 0, 'certified' => in_array((int) $this->workCenterId, $this->skillIds($e->employee_id), true)])->values()->all();

        $assign = [];
        foreach ($mine as $employeeId => $p) {
            if (! in_array($p['shift_id'], $hidden, true)) {
                $assign[$employeeId] = (string) $p['shift_id'];
            }
        }

        return ['shifts' => $shifts, 'days' => $days, 'people' => $people, 'assign' => $assign];
    }

    /** @return array<int, int> pegawai => jumlah unit kerja bersertifikat */
    private function skillCounts(): array
    {
        return EmpEmployeeWorkCenterSkill::where('activate', true)->get()->groupBy('employee_id')->map->count()->all();
    }

    /** @return list<int> */
    private function skillIds(int $employeeId): array
    {
        static $map = null;
        $map ??= EmpEmployeeWorkCenterSkill::where('activate', true)->get()->groupBy('employee_id')->map(fn ($r) => $r->pluck('work_center_id')->map(fn ($v) => (int) $v)->all())->all();

        return $map[$employeeId] ?? [];
    }

    // ---------- aksi ----------

    /**
     * Terapkan isi papan unit kerja ini: yang baru / pindah shift ditulis, yang hilang dari papan dihapus, lalu batch DRAFT.
     *
     * @param  array<int|string, string>  $assign  pegawai => shift_id @return array{placed: int, removed: int, skipped: int, uncertified: int, under: list<string>}
     */
    public function save(array $assign, string $by): array
    {
        $this->requireOpen();
        if (! $this->workCenterId) {
            throw ValidationException::withMessages(['roster' => t('Choose a work unit first.')]);
        }
        $shifts = $this->shifts()->keyBy('shift_id');
        $placed = $this->placements();
        $center = EmpWorkCenter::with('department')->find($this->workCenterId);
        $stat = ['placed' => 0, 'removed' => 0, 'skipped' => 0, 'uncertified' => 0];

        foreach ($assign as $employeeId => $shiftKey) {
            $employeeId = (int) $employeeId;
            $shift = $shifts->get((int) $shiftKey) ?? throw ValidationException::withMessages(['roster' => t('Unknown shift.')]);
            $current = $placed[$employeeId] ?? null;
            if ($current && $current['work_center_id'] !== (int) $this->workCenterId) {
                $stat['skipped']++;

                continue;
            }
            if ($current && $current['shift_id'] === $shift->shift_id) {
                continue;
            }
            if ($shift->activeDayCount() === 0) {
                throw ValidationException::withMessages(['roster' => tf('":shift" has no working hours per day yet. Fill them in Master Shift before placing employees.', ['shift' => $shift->shift_name])]);
            }
            $current && $this->deletePlacement($employeeId);
            $this->writePlacement($employeeId, $shift, $center, $by);
            $stat['placed']++;
            $stat['uncertified'] += in_array((int) $this->workCenterId, $this->skillIds($employeeId), true) ? 0 : 1;
        }

        $onBoard = collect($placed)->filter(fn ($p) => $p['work_center_id'] === (int) $this->workCenterId)->keys();
        $hidden = self::hiddenShifts($by);
        foreach ($onBoard as $employeeId) {
            if (! array_key_exists($employeeId, $assign) && ! in_array($placed[$employeeId]['shift_id'], $hidden, true)) {
                $this->deletePlacement((int) $employeeId);
                $stat['removed']++;
            }
        }

        $this->touchBatch('DRAFT', $by);

        return $stat + ['under' => $this->underQuota()];
    }

    public function confirm(string $by): void
    {
        $this->requireOpen();
        if (! $this->placements()) {
            throw ValidationException::withMessages(['roster' => t('There are no placements to publish yet.')]);
        }
        $this->touchBatch('CONFIRMED', $by);
    }

    public function unlock(string $by): void
    {
        $this->touchBatch('DRAFT', $by);
    }

    /** @return list<string> shift (unit ini) yang terisi tapi masih di bawah kuota */
    public function underQuota(): array
    {
        $count = collect($this->placements())->filter(fn ($p) => $p['work_center_id'] === (int) $this->workCenterId)->groupBy('shift_id')->map->count();

        return $this->shifts()->filter(fn (EmpShift $s) => ($n = $count[$s->shift_id] ?? 0) > 0 && $n < $s->capacity())->pluck('shift_name')->all();
    }

    // ---------- jadwal (emp_roster_schedule + departemen) ----------

    /** @param list<int|string> $departmentIds */
    public static function saveSchedule(?int $id, string $name, array $departmentIds, string $by): EmpRosterSchedule
    {
        $d = Validator::make(['name' => $name, 'departments' => $departmentIds], [
            'name' => ['required', 'string', 'max:150'], 'departments' => ['required', 'array', 'min:1'], 'departments.*' => ['integer', 'exists:tm2mst.org_department,department_id'],
        ], [], ['name' => t('Schedule Name'), 'departments' => t('Departments')])->validate();

        $schedule = $id ? EmpRosterSchedule::findOrFail($id) : null;
        $schedule ? $schedule->update(['schedule_name' => $d['name'], 'updated_by' => $by])
            : $schedule = EmpRosterSchedule::create(['schedule_name' => $d['name'], 'activate' => true, 'created_by' => $by, 'updated_by' => $by]);
        EmpRosterScheduleDepartment::where('schedule_id', $schedule->schedule_id)->whereNotIn('department_id', $d['departments'])->delete();
        foreach ($d['departments'] as $departmentId) {
            EmpRosterScheduleDepartment::firstOrCreate(['schedule_id' => $schedule->schedule_id, 'department_id' => $departmentId]);
        }

        return $schedule;
    }

    /** At least one schedule must remain. @return int roster rows released */
    public static function deleteSchedule(int $id): int
    {
        if (EmpRosterSchedule::where('activate', true)->count() < 2) {
            throw ValidationException::withMessages(['schedule' => t('At least one schedule must remain.')]);
        }
        $schedule = EmpRosterSchedule::findOrFail($id);
        $freed = EmpShiftRoster::where('schedule_id', $id)->count();
        EmpShiftRoster::where('schedule_id', $id)->delete();
        EmpRosterScheduleDepartment::where('schedule_id', $id)->delete();
        EmpShiftRosterBatch::where('schedule_id', $id)->delete();
        $schedule->delete();

        return $freed;
    }

    public function rosterRowCount(): int
    {
        return $this->scheduleId ? EmpShiftRoster::where('schedule_id', $this->scheduleId)->count() : 0;
    }

    // ---------- rotasi antar minggu (swap / cycle / copy) ----------

    /** @return array{valid: bool, moves: int, occupied: bool, busy: int} */
    public function rotatePreview(Carbon $from, Carbon $to, string $scope): array
    {
        $from = $from->copy()->startOfWeek(Carbon::MONDAY);
        $to = $to->copy()->startOfWeek(Carbon::MONDAY);
        if ($from->equalTo($to)) {
            return ['valid' => false, 'moves' => 0, 'occupied' => false, 'busy' => 0];
        }
        $range = [$to->toDateString(), $to->copy()->addDays(6)->toDateString()];
        $source = $this->sourceRows($from, $scope);
        $targetEmployees = EmpShiftRoster::whereBetween('roster_date', $range)->pluck('employee_id')->unique();
        $occupied = EmpShiftRoster::where('schedule_id', $this->scheduleId)->whereBetween('roster_date', $range)->exists();

        return ['valid' => true, 'moves' => $source->count(), 'occupied' => $occupied, 'busy' => $source->filter(fn ($r) => $targetEmployees->contains($r->employee_id))->count()];
    }

    /**
     * mode swap = tukar shift A dan B, cycle = geser tiap shift ke shift berikutnya, copy = salin apa adanya. scope all = semua unit, wc = unit ini.
     * overwrite = kosongkan dulu minggu tujuan (lingkup yang sama). Minggu tujuan yang sudah dikunci (CONFIRMED) ditolak.
     *
     * @return array{placed: int, skipped: int}
     */
    public function rotate(Carbon $from, Carbon $to, string $mode, ?int $a, ?int $b, string $scope, bool $overwrite, string $by): array
    {
        $from = $from->copy()->startOfWeek(Carbon::MONDAY);
        $to = $to->copy()->startOfWeek(Carbon::MONDAY);
        $fail = fn (string $m) => throw ValidationException::withMessages(['rotate' => t($m)]);
        $from->equalTo($to) && $fail('The source and target weeks must be different.');
        EmpShiftRosterBatch::where('schedule_id', $this->scheduleId)->where('date_from', $to->toDateString())->where('status', 'CONFIRMED')->exists()
            && $fail('The target week is locked (CONFIRMED). Unlock it first.');
        $mode === 'swap' && (! $a || ! $b || $a === $b) && $fail('Choose two different shifts to swap.');
        $source = $this->sourceRows($from, $scope);
        $source->isEmpty() && $fail('The source week has no placements for this schedule.');

        $live = $this->shifts()->values();
        $map = fn (int $id): int => match ($mode) {
            'swap' => $id === $a ? (int) $b : ($id === $b ? (int) $a : $id),
            'cycle' => ($i = $live->search(fn ($s) => $s->shift_id === $id)) === false ? $id : $live[($i + 1) % $live->count()]->shift_id,
            default => $id,
        };

        $range = [$to->toDateString(), $to->copy()->addDays(6)->toDateString()];
        $overwrite && EmpShiftRoster::where('schedule_id', $this->scheduleId)->whereBetween('roster_date', $range)
            ->when($scope === 'wc', fn ($q) => $q->where('work_center_id', $this->workCenterId))->delete();
        $busy = EmpShiftRoster::whereBetween('roster_date', $range)->pluck('employee_id')->unique()->all();

        $stat = ['placed' => 0, 'skipped' => 0];
        foreach ($source as $row) {
            if (in_array($row->employee_id, $busy, true)) {
                $stat['skipped']++;

                continue;
            }
            $shift = EmpShift::with('details')->find($map((int) $row->shift_id));
            if (! $shift) {
                continue;
            }
            $this->writePlacement((int) $row->employee_id, $shift, EmpWorkCenter::with('department')->find($row->work_center_id), $by, $to);
            $stat['placed']++;
        }

        return $stat;
    }

    /** @return Collection<int, EmpShiftRoster> satu baris per pegawai di minggu sumber */
    private function sourceRows(Carbon $weekStart, string $scope): Collection
    {
        return EmpShiftRoster::where('schedule_id', $this->scheduleId)->whereBetween('roster_date', [$weekStart->toDateString(), $weekStart->copy()->addDays(6)->toDateString()])
            ->when($scope === 'wc', fn ($q) => $q->where('work_center_id', $this->workCenterId))->get()->unique('employee_id');
    }

    private function requireOpen(): void
    {
        if ($this->isLocked()) {
            throw ValidationException::withMessages(['roster' => t('This week\'s roster is locked (CONFIRMED). Unlock it first to change it.')]);
        }
    }

    private function writePlacement(int $employeeId, EmpShift $shift, ?EmpWorkCenter $center, string $by, ?Carbon $weekStart = null): void
    {
        $weekStart ??= $this->weekStart();
        $rows = [];
        foreach ($shift->dayPattern() as $index => $day) {
            if (! $day['on']) {
                continue;
            }
            $rows[] = ['employee_id' => $employeeId, 'division_id' => $center?->department?->division_id, 'machine_id' => $center?->machine_id, 'work_center_id' => $center?->work_center_id ?? $this->workCenterId,
                'schedule_id' => $this->scheduleId, 'shift_id' => $shift->shift_id, 'roster_date' => $weekStart->copy()->addDays($index)->toDateString(),
                'assigned_by_role' => 'PPIC', 'is_overtime' => false, 'created_by' => $by, 'updated_by' => $by, 'created_at' => now(), 'updated_at' => now()];
        }
        $rows && EmpShiftRoster::insert($rows);
    }

    private function deletePlacement(int $employeeId): void
    {
        EmpShiftRoster::where('schedule_id', $this->scheduleId)->where('employee_id', $employeeId)
            ->whereBetween('roster_date', [$this->weekStart()->toDateString(), $this->weekEnd()->toDateString()])->delete();
    }

    private function touchBatch(string $status, string $by): EmpShiftRosterBatch
    {
        $batch = EmpShiftRosterBatch::firstOrNew(['schedule_id' => $this->scheduleId, 'date_from' => $this->weekStart()->toDateString(), 'date_to' => $this->weekEnd()->toDateString()]);
        $batch->exists || $batch->created_by = $by;
        $batch->fill(['status' => $status, 'updated_by' => $by, 'confirmed_by' => $status === 'CONFIRMED' ? $by : null, 'confirmed_at' => $status === 'CONFIRMED' ? now() : null])->save();
        EmpShiftRoster::where('schedule_id', $this->scheduleId)->whereBetween('roster_date', [$this->weekStart()->toDateString(), $this->weekEnd()->toDateString()])
            ->update(['batch_id' => $batch->batch_id, 'updated_by' => $by]);

        return $batch;
    }
}
