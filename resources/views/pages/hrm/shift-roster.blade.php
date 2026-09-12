<?php

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpEmployeeWorkCenterSkill;
use App\Models\Master\EmpRosterSchedule;
use App\Models\Master\EmpRosterScheduleDepartment;
use App\Models\Master\EmpShift;
use App\Models\Master\EmpShiftDetail;
use App\Models\Master\EmpShiftRoster;
use App\Models\Master\EmpShiftRosterBatch;
use App\Models\Master\EmpWorkCenter;
use App\Models\Master\OrgDepartment;
use App\Support\ActiveTheme;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// docs/mockups/mockup_roster_shift.html (keputusan user 2026-09-12: "mockup
// untuk jadwal roster HARUS BISA", "termasuk semua detail dan behaviour nya",
// "workcenter dan department dari master yang ada di table").
//
// MODEL PENEMPATAN: 1 pegawai = 1 shift untuk 1 MINGGU penuh dalam 1 Jadwal
// (bukan pilih tanggal satu per satu seperti papan lama). Satu penempatan
// ditulis jadi 1 baris emp_shift_roster PER HARI AKTIF shift tsb -- jadi
// AttendanceCalculationService (baca employee+tanggal+shift) tetap bekerja
// tanpa perubahan apa pun. Hari libur shift TIDAK dibuatkan baris.
//
// Departemen diambil dari master org_department, unit kerja dari master
// emp_work_center (yang isinya 1:1 dari prod_machine utk mesin + unit
// non-mesin per departemen) -- tidak ada daftar hardcode seperti di JS mockup.
//
// Lifecycle Draft/Confirm SENGAJA DIPERTAHANKAN (mockup tidak punya): roster
// ini jadi sumber jam kerja payroll/attendance, jadi kunci publish tetap ada.
new #[Title('Roster Shift Mingguan — TELASIH MAKMUR ERP')] class extends Component
{
    public string $view = 'roster'; // roster | shift

    public ?int $scheduleId = null;

    public ?int $departmentId = null;

    public ?int $workCenterId = null;

    public int $weekOffset = 0;

    // ---- pool ----
    public string $poolSearch = '';

    public string $groupBy = 'jabatan'; // jabatan | skill | golongan

    public bool $skillOnly = true;

    public array $selection = [];

    public array $collapsed = [];

    // ---- toast (mockup: notifikasi bawah layar) ----
    public string $toast = '';

    public bool $toastBad = false;

    // ---- modal Jadwal ----
    public bool $showScheduleModal = false;

    public bool $scheduleEditing = false;

    public string $sched_name = '';

    public array $sched_depts = [];

    public bool $showDeleteSchedule = false;

    // ---- modal Shift (dari dobel-klik kolom shift di papan) ----
    public bool $showShiftModal = false;

    public ?int $shiftEditingId = null;

    public array $shiftForm = [];

    // ---- modal Rotasi / Tukar antar minggu ----
    public bool $showRotateModal = false;

    public int $rot_from = 0;

    public int $rot_to = 0;

    public string $rot_mode = 'swap'; // swap | cycle | copy

    public ?int $rot_a = null;

    public ?int $rot_b = null;

    public string $rot_scope = 'all'; // all | wc

    public bool $rot_over = false;

    public function mount(): void
    {
        $this->scheduleId = EmpRosterSchedule::where('activate', true)->orderBy('schedule_id')->value('schedule_id');
        $this->syncScopeDefaults();
    }

    // ================= TEMA =================
    #[Computed]
    public function theme()
    {
        return ActiveTheme::resolve();
    }

    // ================= SCOPE: JADWAL / DEPARTEMEN / WORK CENTER =================
    #[Computed]
    public function schedules()
    {
        return EmpRosterSchedule::where('activate', true)->orderBy('schedule_name')->get();
    }

    #[Computed]
    public function currentSchedule(): ?EmpRosterSchedule
    {
        return $this->scheduleId ? EmpRosterSchedule::with('departmentLinks')->find($this->scheduleId) : null;
    }

    #[Computed]
    public function scheduleDepartments()
    {
        $ids = EmpRosterScheduleDepartment::where('schedule_id', $this->scheduleId)->pluck('department_id');

        return OrgDepartment::whereIn('department_id', $ids)->where('activate', true)->orderBy('department_name')->get();
    }

    #[Computed]
    public function workCenters()
    {
        if (! $this->departmentId) {
            return collect();
        }

        return EmpWorkCenter::where('department_id', $this->departmentId)
            ->where('activate', true)
            ->orderBy('sort_order')
            ->orderBy('work_center_name')
            ->get();
    }

    #[Computed]
    public function currentWorkCenter(): ?EmpWorkCenter
    {
        return $this->workCenterId ? EmpWorkCenter::find($this->workCenterId) : null;
    }

    // Katalog shift bersifat GLOBAL lintas departemen (mockup: REG/SH1/SH2/SH3
    // dipakai semua unit). Shift lama yang masih terikat 1 departemen tetap
    // ikut tampil -- department_id sekarang cuma penanda asal, bukan filter.
    #[Computed]
    public function shifts()
    {
        return EmpShift::with('details')->where('activate', true)->orderBy('shift_code')->orderBy('shift_name')->get();
    }

    #[Computed]
    public function allShifts()
    {
        return EmpShift::with('details')->orderBy('activate', 'desc')->orderBy('shift_code')->orderBy('shift_name')->get();
    }

    private function syncScopeDefaults(): void
    {
        $departments = $this->scheduleDepartments;

        if ($departments->isEmpty()) {
            $this->departmentId = null;
            $this->workCenterId = null;

            return;
        }

        if (! $departments->firstWhere('department_id', $this->departmentId)) {
            $this->departmentId = $departments->first()->department_id;
        }

        unset($this->workCenters);
        $centers = $this->workCenters;

        if (! $centers->firstWhere('work_center_id', $this->workCenterId)) {
            $this->workCenterId = $centers->first()?->work_center_id;
        }
    }

    private function refreshScope(): void
    {
        unset($this->scheduleDepartments, $this->workCenters, $this->currentWorkCenter, $this->currentSchedule);
        $this->syncScopeDefaults();
        $this->forgetWeekData();
        $this->selection = [];
    }

    private function forgetWeekData(): void
    {
        unset(
            $this->weekPlacements,
            $this->boardPlacements,
            $this->poolEmployees,
            $this->poolGroups,
            $this->employeeIndex,
            $this->skillMap,
            $this->batch,
            $this->statAssigned,
            $this->statFree,
        );
    }

    public function selectSchedule(int $id): void
    {
        $this->scheduleId = $id;
        $this->refreshScope();
    }

    public function updatedScheduleId(): void
    {
        $this->refreshScope();
    }

    public function updatedDepartmentId(): void
    {
        unset($this->workCenters);
        $this->workCenterId = $this->workCenters->first()?->work_center_id;
        $this->forgetWeekData();
        $this->selection = [];
    }

    public function updatedWorkCenterId(): void
    {
        $this->forgetWeekData();
        $this->selection = [];
    }

    public function updatedSkillOnly(): void
    {
        $this->forgetWeekData();
        $this->selection = [];
    }

    public function updatedGroupBy(): void
    {
        $this->collapsed = [];
        $this->forgetWeekData();
    }

    public function updatedPoolSearch(): void
    {
        $this->forgetWeekData();
    }

    // ================= MINGGU =================
    #[Computed]
    public function weekStart(): Carbon
    {
        return Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeeks($this->weekOffset);
    }

    #[Computed]
    public function weekEnd(): Carbon
    {
        return $this->weekStart->copy()->addDays(6);
    }

    #[Computed]
    public function weekDates(): array
    {
        return collect(range(0, 6))->map(fn ($i) => $this->weekStart->copy()->addDays($i))->all();
    }

    public function weekNumber(): int
    {
        return (int) $this->weekStart->isoWeek();
    }

    public function goWeek(int $delta): void
    {
        $this->weekOffset += $delta;
        $this->selection = [];
        $this->forgetWeekData();

        $filled = collect($this->weekPlacements)->isNotEmpty();
        $this->flash(
            'Minggu '.$this->weekNumber().' · '.($filled ? 'roster sudah terisi' : 'belum ada roster — pakai Tukar Shift untuk menyalin dari minggu lain'),
            ! $filled
        );
    }

    // ================= DATA PENEMPATAN =================
    // employee_id => ['shift_id'=>..,'work_center_id'=>..] untuk SELURUH jadwal
    // pada minggu ini (dipakai utk invarian 1 orang = 1 shift per minggu).
    #[Computed]
    public function weekPlacements(): array
    {
        if (! $this->scheduleId) {
            return [];
        }

        return EmpShiftRoster::query()
            ->where('schedule_id', $this->scheduleId)
            ->whereBetween('roster_date', [$this->weekStart->toDateString(), $this->weekEnd->toDateString()])
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($rows) => [
                'shift_id' => (int) $rows->first()->shift_id,
                'work_center_id' => (int) $rows->first()->work_center_id,
            ])
            ->all();
    }

    // shift_id => [employee_id, ...] khusus work center yang sedang dibuka.
    #[Computed]
    public function boardPlacements(): array
    {
        $out = [];

        foreach ($this->weekPlacements as $employeeId => $place) {
            if ($place['work_center_id'] !== (int) $this->workCenterId) {
                continue;
            }
            $out[$place['shift_id']][] = (int) $employeeId;
        }

        return $out;
    }

    #[Computed]
    public function employeeIndex()
    {
        $placedIds = array_keys($this->weekPlacements);

        return EmpEmployee::with('position.department', 'position.level')
            ->where(function ($q) use ($placedIds) {
                $q->whereIn('employee_id', $placedIds ?: [0]);

                if ($this->departmentId) {
                    $q->orWhereHas('position', fn ($p) => $p->where('department_id', $this->departmentId));
                }
            })
            ->get()
            ->keyBy('employee_id');
    }

    // employee_id => [work_center_id, ...]
    #[Computed]
    public function skillMap(): array
    {
        return EmpEmployeeWorkCenterSkill::where('activate', true)
            ->whereIn('employee_id', $this->employeeIndex->keys()->all() ?: [0])
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($rows) => $rows->pluck('work_center_id')->map(fn ($v) => (int) $v)->all())
            ->all();
    }

    public function hasSkill(int $employeeId, ?int $workCenterId = null): bool
    {
        $workCenterId ??= (int) $this->workCenterId;

        return in_array($workCenterId, $this->skillMap[$employeeId] ?? [], true);
    }

    #[Computed]
    public function poolEmployees()
    {
        if (! $this->departmentId) {
            return collect();
        }

        $placed = array_keys($this->weekPlacements);
        $search = trim($this->poolSearch);

        return $this->employeeIndex
            ->filter(fn (EmpEmployee $e) => $e->activate
                && $e->position?->department_id === $this->departmentId
                && ! in_array($e->employee_id, $placed)
                && (! $this->skillOnly || $this->hasSkill($e->employee_id))
                && ($search === ''
                    || str_contains(strtolower($e->full_name), strtolower($search))
                    || str_contains(strtolower((string) $e->employee_code), strtolower($search))))
            ->sortBy('full_name')
            ->values();
    }

    #[Computed]
    public function poolGroups(): array
    {
        $groups = [];
        $centerNames = EmpWorkCenter::whereIn('work_center_id', collect($this->skillMap)->flatten()->unique()->all() ?: [0])
            ->pluck('work_center_name', 'work_center_id');

        foreach ($this->poolEmployees as $employee) {
            $keys = match ($this->groupBy) {
                'skill' => collect($this->skillMap[$employee->employee_id] ?? [])
                    ->map(fn ($id) => $centerNames[$id] ?? 'Tanpa unit')->all() ?: ['Belum ada skill'],
                'golongan' => [$employee->position?->level?->level_name ?: 'Tanpa golongan'],
                default => [$employee->position?->position_name ?: 'Tanpa jabatan'],
            };

            foreach ($keys as $key) {
                $groups[$key][] = $employee;
            }
        }

        ksort($groups);

        return $groups;
    }

    // ================= STATISTIK =================
    #[Computed]
    public function statAssigned(): int
    {
        return count($this->weekPlacements);
    }

    #[Computed]
    public function statFree(): int
    {
        if (! $this->departmentId) {
            return 0;
        }

        $placed = array_keys($this->weekPlacements);

        return $this->employeeIndex
            ->filter(fn (EmpEmployee $e) => $e->activate
                && $e->position?->department_id === $this->departmentId
                && ! in_array($e->employee_id, $placed))
            ->count();
    }

    public function shiftCount(int $shiftId): int
    {
        return count($this->boardPlacements[$shiftId] ?? []);
    }

    // ================= LIFECYCLE DRAFT / CONFIRM =================
    #[Computed]
    public function batch(): ?EmpShiftRosterBatch
    {
        if (! $this->scheduleId) {
            return null;
        }

        return EmpShiftRosterBatch::where('schedule_id', $this->scheduleId)
            ->where('date_from', $this->weekStart->toDateString())
            ->where('date_to', $this->weekEnd->toDateString())
            ->first();
    }

    public function isLocked(): bool
    {
        return $this->batch?->status === 'CONFIRMED';
    }

    private function touchBatch(string $status): EmpShiftRosterBatch
    {
        $batch = EmpShiftRosterBatch::firstOrNew([
            'schedule_id' => $this->scheduleId,
            'date_from' => $this->weekStart->toDateString(),
            'date_to' => $this->weekEnd->toDateString(),
        ]);

        if (! $batch->exists) {
            $batch->created_by = Auth::user()->login;
        }

        $batch->status = $status;
        $batch->updated_by = Auth::user()->login;
        $batch->confirmed_by = $status === 'CONFIRMED' ? Auth::user()->login : null;
        $batch->confirmed_at = $status === 'CONFIRMED' ? now() : null;
        $batch->save();

        unset($this->batch);

        return $batch;
    }

    public function saveDraft(): void
    {
        $batch = $this->touchBatch('DRAFT');
        $this->attachBatchToWeek($batch->batch_id);

        $under = $this->underQuotaShifts();
        $this->flash(
            'Jadwal "'.($this->currentSchedule?->schedule_name ?? '-').'" minggu '.$this->weekNumber().' tersimpan · '.$this->statAssigned.' penempatan. '
                .($under ? count($under).' shift masih di bawah kuota.' : 'Semua shift terisi penuh.'),
            (bool) $under
        );
    }

    public function confirmBatch(): void
    {
        if ($this->statAssigned === 0) {
            $this->flash('Belum ada penempatan untuk dipublikasikan.', true);

            return;
        }

        $batch = $this->touchBatch('CONFIRMED');
        $this->attachBatchToWeek($batch->batch_id);
        $this->flash('Roster minggu '.$this->weekNumber().' dipublikasikan & dikunci.');
    }

    public function unlockBatch(): void
    {
        $this->touchBatch('DRAFT');
        $this->flash('Kunci dibuka — roster minggu '.$this->weekNumber().' bisa diubah lagi.');
    }

    private function attachBatchToWeek(int $batchId): void
    {
        EmpShiftRoster::where('schedule_id', $this->scheduleId)
            ->whereBetween('roster_date', [$this->weekStart->toDateString(), $this->weekEnd->toDateString()])
            ->update(['batch_id' => $batchId, 'updated_by' => Auth::user()->login]);
    }

    private function underQuotaShifts(): array
    {
        $under = [];

        foreach ($this->shifts as $shift) {
            $n = $this->shiftCount($shift->shift_id);
            if ($n > 0 && $n < $shift->capacity()) {
                $under[] = $shift->shift_name;
            }
        }

        return $under;
    }

    // ================= SELEKSI POOL =================
    public function toggleSelect(int $employeeId): void
    {
        $this->selection = in_array($employeeId, $this->selection, true)
            ? array_values(array_diff($this->selection, [$employeeId]))
            : [...$this->selection, $employeeId];
    }

    public function toggleGroup(string $groupName): void
    {
        $ids = collect($this->poolGroups[$groupName] ?? [])->pluck('employee_id')->all();
        $allSelected = $ids && ! array_diff($ids, $this->selection);

        $this->selection = $allSelected
            ? array_values(array_diff($this->selection, $ids))
            : array_values(array_unique([...$this->selection, ...$ids]));
    }

    public function selectAllVisible(): void
    {
        $this->selection = $this->poolEmployees->pluck('employee_id')->map(fn ($v) => (int) $v)->all();
    }

    public function clearSelection(): void
    {
        $this->selection = [];
    }

    public function toggleCollapse(string $groupName): void
    {
        $this->collapsed = in_array($groupName, $this->collapsed, true)
            ? array_values(array_diff($this->collapsed, [$groupName]))
            : [...$this->collapsed, $groupName];
    }

    // ================= PENEMPATAN =================
    // Dipanggil dari drop zone (drag 1 chip / drag blok seleksi) maupun tombol.
    public function assignToShift(int $shiftId, ?int $employeeId = null, bool $fromBoard = false): void
    {
        if ($this->isLocked()) {
            $this->flash('Roster minggu ini sudah dikunci (CONFIRMED). Buka kunci dulu untuk mengubah.', true);

            return;
        }

        if (! $this->workCenterId) {
            $this->flash('Pilih unit kerja dulu.', true);

            return;
        }

        $shift = $this->shifts->firstWhere('shift_id', $shiftId);

        if (! $shift) {
            return;
        }

        // Shift tanpa hari aktif akan menghasilkan NOL baris roster -- penempatan
        // seolah berhasil padahal tidak ada apa pun tersimpan. Ditemukan lewat
        // pengujian: 4 dari 8 shift di DB memang belum punya emp_shift_detail.
        if ($shift->activeDayCount() === 0) {
            $this->flash('"'.$shift->shift_name.'" belum punya jam kerja per hari. Isi dulu di menu Master Shift sebelum menempatkan pegawai.', true);

            return;
        }

        $ids = $employeeId ? [$employeeId] : $this->selection;

        if (! $ids) {
            $this->flash('Tidak ada pegawai yang dipilih.', true);

            return;
        }

        $ok = 0;
        $dup = 0;
        $noSkill = 0;

        foreach ($ids as $id) {
            $id = (int) $id;
            $existing = $this->weekPlacements[$id] ?? null;

            if ($existing && $fromBoard) {
                $this->deletePlacement($id);
            } elseif ($existing) {
                $dup++;

                continue;
            }

            if (! $this->hasSkill($id)) {
                $noSkill++;
            }

            $this->writePlacement($id, $shift);
            $ok++;
        }

        $this->selection = [];
        $this->forgetWeekData();

        $total = $this->shiftCount($shiftId);
        $capacity = $shift->capacity();

        if ($dup) {
            $this->flash($dup.' pegawai dilewati — sudah punya shift di minggu '.$this->weekNumber().'.', true);
        } elseif ($total > $capacity) {
            $this->flash($ok.' pegawai masuk '.$shift->shift_name.' · kuota terlampaui ('.$total.'/'.$capacity.').', true);
        } elseif ($noSkill) {
            $this->flash($ok.' pegawai → '.$shift->shift_name.', '.$noSkill.' belum bersertifikat '.($this->currentWorkCenter?->work_center_name ?? 'unit ini').'.', true);
        } else {
            $this->flash($ok.' pegawai → '.$shift->shift_name.' · '.implode(', ', $shift->summaryLines()).' ('.EmpShift::formatMinutes($shift->weekMinutes()).'/minggu).');
        }
    }

    // 1 penempatan mingguan -> 1 baris roster per HARI AKTIF shift.
    private function writePlacement(int $employeeId, EmpShift $shift, ?int $workCenterId = null, ?int $scheduleId = null, ?Carbon $weekStart = null): void
    {
        $workCenterId ??= (int) $this->workCenterId;
        $scheduleId ??= (int) $this->scheduleId;
        $weekStart ??= $this->weekStart;

        $center = EmpWorkCenter::with('department')->find($workCenterId);
        $pattern = $shift->dayPattern();
        $rows = [];

        foreach ($pattern as $index => $day) {
            if (! $day['on']) {
                continue;
            }

            $rows[] = [
                'employee_id' => $employeeId,
                'division_id' => $center?->department?->division_id,
                'machine_id' => $center?->machine_id,
                'work_center_id' => $workCenterId,
                'schedule_id' => $scheduleId,
                'shift_id' => $shift->shift_id,
                'roster_date' => $weekStart->copy()->addDays($index)->toDateString(),
                'assigned_by_role' => 'PPIC',
                'is_overtime' => false,
                'created_by' => Auth::user()->login,
                'updated_by' => Auth::user()->login,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows) {
            EmpShiftRoster::insert($rows);
        }
    }

    private function deletePlacement(int $employeeId, ?int $scheduleId = null, ?Carbon $weekStart = null): void
    {
        $scheduleId ??= (int) $this->scheduleId;
        $weekStart ??= $this->weekStart;

        EmpShiftRoster::where('schedule_id', $scheduleId)
            ->where('employee_id', $employeeId)
            ->whereBetween('roster_date', [$weekStart->toDateString(), $weekStart->copy()->addDays(6)->toDateString()])
            ->delete();
    }

    public function removePlacement(int $employeeId): void
    {
        if ($this->isLocked()) {
            $this->flash('Roster minggu ini sudah dikunci (CONFIRMED).', true);

            return;
        }

        $this->deletePlacement($employeeId);
        $this->forgetWeekData();
        $this->flash(($this->employeeIndex[$employeeId]->full_name ?? 'Pegawai').' dilepas dari roster minggu '.$this->weekNumber().'.');
    }

    // ================= MODAL: JADWAL =================
    public function openScheduleModal(bool $edit = false): void
    {
        $this->resetErrorBag();
        $this->scheduleEditing = $edit;

        if ($edit && $this->currentSchedule) {
            $this->sched_name = $this->currentSchedule->schedule_name;
            $this->sched_depts = $this->currentSchedule->departmentIds();
        } else {
            $this->sched_name = '';
            $this->sched_depts = [];
        }

        $this->showScheduleModal = true;
    }

    public function saveSchedule(): void
    {
        $data = $this->validate([
            'sched_name' => ['required', 'string', 'max:150'],
            'sched_depts' => ['required', 'array', 'min:1'],
            'sched_depts.*' => ['integer', 'exists:tm2mst.org_department,department_id'],
        ], [
            'sched_name.required' => 'Nama jadwal wajib diisi.',
            'sched_depts.required' => 'Pilih minimal satu departemen.',
        ]);

        if ($this->scheduleEditing && $this->currentSchedule) {
            $schedule = $this->currentSchedule;
            $schedule->update(['schedule_name' => $data['sched_name'], 'updated_by' => Auth::user()->login]);
        } else {
            $schedule = EmpRosterSchedule::create([
                'schedule_name' => $data['sched_name'],
                'activate' => true,
                'created_by' => Auth::user()->login,
                'updated_by' => Auth::user()->login,
            ]);
            $this->scheduleId = $schedule->schedule_id;
        }

        EmpRosterScheduleDepartment::where('schedule_id', $schedule->schedule_id)
            ->whereNotIn('department_id', $data['sched_depts'])->delete();

        foreach ($data['sched_depts'] as $departmentId) {
            EmpRosterScheduleDepartment::firstOrCreate([
                'schedule_id' => $schedule->schedule_id,
                'department_id' => $departmentId,
            ]);
        }

        $this->showScheduleModal = false;
        unset($this->schedules);
        $this->refreshScope();
        $this->flash($this->scheduleEditing ? 'Jadwal diperbarui.' : 'Jadwal "'.$data['sched_name'].'" dibuat — silakan isi roster-nya.');
    }

    public function deleteSchedule(): void
    {
        if ($this->schedules->count() < 2) {
            $this->flash('Minimal satu jadwal harus ada.', true);
            $this->showDeleteSchedule = false;

            return;
        }

        $schedule = $this->currentSchedule;
        $freed = EmpShiftRoster::where('schedule_id', $schedule->schedule_id)->count();

        EmpShiftRoster::where('schedule_id', $schedule->schedule_id)->delete();
        EmpRosterScheduleDepartment::where('schedule_id', $schedule->schedule_id)->delete();
        EmpShiftRosterBatch::where('schedule_id', $schedule->schedule_id)->delete();
        $name = $schedule->schedule_name;
        $schedule->delete();

        $this->showDeleteSchedule = false;
        unset($this->schedules);
        $this->scheduleId = $this->schedules->first()?->schedule_id;
        $this->refreshScope();
        $this->flash('Jadwal "'.$name.'" dihapus'.($freed ? ' · '.$freed.' baris roster ikut dilepas' : '').'.');
    }

    public function scheduleRosterCount(): int
    {
        return $this->scheduleId ? EmpShiftRoster::where('schedule_id', $this->scheduleId)->count() : 0;
    }

    // ================= MASTER SHIFT =================
    private function blankShiftForm(): array
    {
        return [
            'code' => '',
            'name' => '',
            'color' => EmpShift::DEFAULT_COLOR,
            'cap' => 4,
            'active' => true,
            'days' => collect(EmpShift::DAY_ORDER)->map(fn ($day, $i) => [
                'on' => $i < 5,
                'in' => '08:00',
                'out' => '16:00',
                'brk' => 60,
            ])->all(),
        ];
    }

    private function formFromShift(EmpShift $shift): array
    {
        return [
            'code' => (string) $shift->shift_code,
            'name' => $shift->shift_name,
            'color' => $shift->color(),
            'cap' => $shift->capacity(),
            'active' => (bool) $shift->activate,
            'days' => collect($shift->dayPattern())->map(fn ($d) => [
                'on' => $d['on'],
                'in' => $d['in'] ?: '08:00',
                'out' => $d['out'] ?: '16:00',
                'brk' => $d['brk'],
            ])->all(),
        ];
    }

    public function openShiftModal(?int $shiftId = null): void
    {
        $this->resetErrorBag();
        $this->shiftEditingId = $shiftId;
        $shift = $shiftId ? EmpShift::with('details')->find($shiftId) : null;
        $this->shiftForm = $shift ? $this->formFromShift($shift) : $this->blankShiftForm();
        $this->showShiftModal = true;
    }

    // Mockup: "Samakan Sen-Jum" / "Samakan Sen-Sab" — salin jam hari aktif
    // pertama ke hari 1..N supaya tidak perlu ketik ulang tiap hari.
    public function fillDays(int $upto): void
    {
        $source = collect($this->shiftForm['days'])->first(fn ($d) => $d['on']);

        if (! $source) {
            $this->flash('Aktifkan minimal satu hari dulu.', true);

            return;
        }

        for ($i = 0; $i < $upto; $i++) {
            $this->shiftForm['days'][$i] = ['on' => true, 'in' => $source['in'], 'out' => $source['out'], 'brk' => $source['brk']];
        }

        $this->flash('Sen–'.EmpShift::DAY_LABELS[EmpShift::DAY_ORDER[$upto - 1]].' disamakan '.$source['in'].'–'.$source['out'].'.');
    }

    public function saveShift(): void
    {
        $data = $this->validate([
            'shiftForm.code' => ['required', 'string', 'max:10'],
            'shiftForm.name' => ['required', 'string', 'max:150'],
            'shiftForm.color' => ['required', 'string', 'max:7'],
            'shiftForm.cap' => ['required', 'integer', 'min:1', 'max:30'],
            'shiftForm.days' => ['array', 'size:7'],
            // 'on' WAJIB punya rule sendiri -- validate() hanya mengembalikan
            // atribut yang divalidasi, jadi tanpa baris ini flag hari aktif
            // hilang dari $data dan simpan shift selalu ditolak "minimal satu
            // hari harus aktif" (ketemu lewat pengujian).
            'shiftForm.days.*.on' => ['boolean'],
            'shiftForm.days.*.in' => ['nullable', 'date_format:H:i'],
            'shiftForm.days.*.out' => ['nullable', 'date_format:H:i'],
            'shiftForm.days.*.brk' => ['nullable', 'integer', 'min:0', 'max:240'],
        ], [
            'shiftForm.code.required' => 'Kode shift wajib diisi.',
            'shiftForm.name.required' => 'Nama shift wajib diisi.',
        ])['shiftForm'];

        $activeDays = collect($data['days'])->filter(fn ($d) => ! empty($d['on']));

        if ($activeDays->isEmpty()) {
            $this->addError('shiftForm.days', 'Minimal satu hari harus aktif.');

            return;
        }

        $code = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', $data['code']));

        $codeTaken = EmpShift::where('shift_code', $code)
            ->when($this->shiftEditingId, fn ($q) => $q->where('shift_id', '!=', $this->shiftEditingId))
            ->exists();

        if ($codeTaken) {
            $this->addError('shiftForm.code', 'Kode "'.$code.'" sudah dipakai shift lain.');

            return;
        }

        $payload = [
            'shift_code' => $code,
            'shift_name' => $data['name'],
            'color_hex' => $data['color'],
            'capacity_per_wc' => $data['cap'],
            'activate' => (bool) ($this->shiftForm['active'] ?? true),
            'updated_by' => Auth::user()->login,
        ];

        if ($this->shiftEditingId) {
            $shift = EmpShift::findOrFail($this->shiftEditingId);
            $shift->update($payload);
        } else {
            $payload['created_by'] = Auth::user()->login;
            $shift = EmpShift::create($payload);
        }

        foreach (EmpShift::DAY_ORDER as $index => $dayName) {
            $day = $data['days'][$index];

            if (empty($day['on'])) {
                EmpShiftDetail::where('shift_id', $shift->shift_id)->where('day_of_week', $dayName)->delete();

                continue;
            }

            EmpShiftDetail::updateOrCreate(
                ['shift_id' => $shift->shift_id, 'day_of_week' => $dayName],
                [
                    'start_time' => $day['in'],
                    'end_time' => $day['out'],
                    'break_minutes' => (int) ($day['brk'] ?? 0),
                    'updated_by' => Auth::user()->login,
                ]
            );
        }

        $this->showShiftModal = false;
        $this->shiftEditingId = null;
        unset($this->shifts, $this->allShifts);
        $this->forgetWeekData();
        $this->flash('"'.$data['name'].'" disimpan · '.implode(', ', $shift->fresh(['details'])->summaryLines()).'.');
    }

    public function shiftUsage(int $shiftId): int
    {
        return EmpShiftRoster::where('shift_id', $shiftId)->count();
    }

    public function duplicateShift(int $shiftId): void
    {
        $source = EmpShift::with('details')->findOrFail($shiftId);

        $code = ($source->shift_code ?: 'SHF').'_C';
        $i = 2;
        while (EmpShift::where('shift_code', $code)->exists()) {
            $code = ($source->shift_code ?: 'SHF').'_C'.$i++;
        }

        $copy = EmpShift::create([
            'department_id' => $source->department_id,
            'shift_code' => substr($code, 0, 10),
            'shift_name' => $source->shift_name.' (salinan)',
            'color_hex' => $source->color(),
            'capacity_per_wc' => $source->capacity(),
            'activate' => true,
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ]);

        foreach ($source->details as $detail) {
            EmpShiftDetail::create([
                'shift_id' => $copy->shift_id,
                'day_of_week' => $detail->day_of_week,
                'start_time' => $detail->start_time?->format('H:i'),
                'end_time' => $detail->end_time?->format('H:i'),
                'break_out' => $detail->break_out?->format('H:i'),
                'break_in' => $detail->break_in?->format('H:i'),
                'break_minutes' => $detail->break_minutes,
                'created_by' => Auth::user()->login,
                'updated_by' => Auth::user()->login,
            ]);
        }

        unset($this->shifts, $this->allShifts);
        $this->flash('Shift diduplikat — sesuaikan kode & jamnya.');
    }

    public function deleteShift(int $shiftId): void
    {
        $used = $this->shiftUsage($shiftId);

        if ($used) {
            $this->flash('Tidak bisa dihapus — shift ini dipakai '.$used.' baris roster.', true);

            return;
        }

        if (EmpShift::where('activate', true)->count() < 2) {
            $this->flash('Minimal satu shift aktif harus ada.', true);

            return;
        }

        EmpShiftDetail::where('shift_id', $shiftId)->delete();
        EmpShift::where('shift_id', $shiftId)->delete();

        unset($this->shifts, $this->allShifts);
        $this->forgetWeekData();
        $this->flash('Shift dihapus.');
    }

    public function toggleShiftActive(int $shiftId): void
    {
        $shift = EmpShift::findOrFail($shiftId);

        if ($shift->activate && $this->shiftUsage($shiftId)) {
            $this->flash('"'.$shift->shift_name.'" masih dipakai '.$this->shiftUsage($shiftId).' baris roster. Kosongkan dulu sebelum dinonaktifkan.', true);

            return;
        }

        $shift->update(['activate' => ! $shift->activate, 'updated_by' => Auth::user()->login]);
        unset($this->shifts, $this->allShifts);
        $this->forgetWeekData();
        $this->flash($shift->shift_name.($shift->activate ? ' diaktifkan.' : ' dinonaktifkan.'));
    }

    // ================= MODAL: ROTASI ANTAR MINGGU =================
    public function openRotate(): void
    {
        $this->resetErrorBag();
        $this->rot_from = $this->weekNumber();
        $this->rot_to = $this->weekNumber() + 1;
        $this->rot_mode = 'swap';
        $this->rot_a = $this->shifts->first()?->shift_id;
        $this->rot_b = $this->shifts->skip(1)->first()?->shift_id;
        $this->rot_scope = 'all';
        $this->rot_over = false;
        $this->showRotateModal = true;
    }

    private function weekStartForNumber(int $weekNumber): Carbon
    {
        return $this->weekStart->copy()->addWeeks($weekNumber - $this->weekNumber());
    }

    #[Computed]
    public function rotatePreview(): array
    {
        if ($this->rot_from === $this->rot_to) {
            return ['valid' => false, 'moves' => 0, 'occupied' => false, 'busy' => 0];
        }

        $sourceStart = $this->weekStartForNumber($this->rot_from);
        $targetStart = $this->weekStartForNumber($this->rot_to);

        $source = EmpShiftRoster::where('schedule_id', $this->scheduleId)
            ->whereBetween('roster_date', [$sourceStart->toDateString(), $sourceStart->copy()->addDays(6)->toDateString()])
            ->when($this->rot_scope === 'wc', fn ($q) => $q->where('work_center_id', $this->workCenterId))
            ->get()
            ->unique('employee_id');

        $targetEmployees = EmpShiftRoster::whereBetween('roster_date', [$targetStart->toDateString(), $targetStart->copy()->addDays(6)->toDateString()])
            ->pluck('employee_id')
            ->unique();

        $occupied = EmpShiftRoster::where('schedule_id', $this->scheduleId)
            ->whereBetween('roster_date', [$targetStart->toDateString(), $targetStart->copy()->addDays(6)->toDateString()])
            ->exists();

        return [
            'valid' => true,
            'moves' => $source->count(),
            'occupied' => $occupied,
            'busy' => $source->filter(fn ($r) => $targetEmployees->contains($r->employee_id))->count(),
        ];
    }

    public function runRotate(): void
    {
        if ($this->rot_from === $this->rot_to) {
            $this->flash('Minggu sumber dan tujuan harus berbeda.', true);

            return;
        }

        $sourceStart = $this->weekStartForNumber($this->rot_from);
        $targetStart = $this->weekStartForNumber($this->rot_to);
        $liveShifts = $this->shifts->values();

        $mapShift = function (int $shiftId) use ($liveShifts): int {
            if ($this->rot_mode === 'copy') {
                return $shiftId;
            }

            if ($this->rot_mode === 'swap') {
                if ($shiftId === (int) $this->rot_a) {
                    return (int) $this->rot_b;
                }

                return $shiftId === (int) $this->rot_b ? (int) $this->rot_a : $shiftId;
            }

            $index = $liveShifts->search(fn ($s) => $s->shift_id === $shiftId);

            return $index === false ? $shiftId : $liveShifts[($index + 1) % $liveShifts->count()]->shift_id;
        };

        $source = EmpShiftRoster::where('schedule_id', $this->scheduleId)
            ->whereBetween('roster_date', [$sourceStart->toDateString(), $sourceStart->copy()->addDays(6)->toDateString()])
            ->when($this->rot_scope === 'wc', fn ($q) => $q->where('work_center_id', $this->workCenterId))
            ->get()
            ->unique('employee_id');

        if ($source->isEmpty()) {
            $this->flash('Minggu '.$this->rot_from.' belum punya penempatan untuk jadwal ini.', true);

            return;
        }

        if ($this->rot_over) {
            EmpShiftRoster::where('schedule_id', $this->scheduleId)
                ->whereBetween('roster_date', [$targetStart->toDateString(), $targetStart->copy()->addDays(6)->toDateString()])
                ->when($this->rot_scope === 'wc', fn ($q) => $q->where('work_center_id', $this->workCenterId))
                ->delete();
        }

        $targetEmployees = EmpShiftRoster::whereBetween('roster_date', [$targetStart->toDateString(), $targetStart->copy()->addDays(6)->toDateString()])
            ->pluck('employee_id')
            ->unique()
            ->all();

        $ok = 0;
        $skipped = 0;

        foreach ($source as $row) {
            if (in_array($row->employee_id, $targetEmployees)) {
                $skipped++;

                continue;
            }

            $shift = EmpShift::with('details')->find($mapShift((int) $row->shift_id));

            if (! $shift) {
                continue;
            }

            $this->writePlacement((int) $row->employee_id, $shift, (int) $row->work_center_id, (int) $this->scheduleId, $targetStart);
            $ok++;
        }

        $this->weekOffset += $this->rot_to - $this->rot_from;
        $this->showRotateModal = false;
        $this->forgetWeekData();
        $this->flash('Minggu '.$this->rot_to.' terisi '.$ok.' penempatan dari minggu '.$this->rot_from.'.'.($skipped ? ' '.$skipped.' dilewati (sudah punya shift).' : ''), (bool) $skipped);
    }

    // ================= UTIL =================
    private function flash(string $message, bool $bad = false): void
    {
        $this->toast = $message;
        $this->toastBad = $bad;
    }

    public function dismissToast(): void
    {
        $this->toast = '';
    }

    public function initials(string $name): string
    {
        return \App\Support\Grid\Avatar::initials($name);
    }
};
?>

@php
    $rsTheme = $this->theme;
    $rsDark = $rsTheme->mode === 'dark';
    $shifts = $this->shifts;
    $board = $this->boardPlacements;
    $locked = $this->isLocked();
@endphp

<div class="rs-root min-h-screen" style="
    --rs-bg: {{ $rsDark ? ($rsTheme->content_bg_color ?: '#12162a') : ($rsTheme->content_bg_color ?: '#f4f6fa') }};
    --rs-surface: {{ $rsDark ? '#1b2140' : '#ffffff' }};
    --rs-surface-alt: {{ $rsDark ? '#20264a' : '#fafbfd' }};
    --rs-sunken: {{ $rsDark ? '#161b36' : '#eef1f7' }};
    --rs-border: {{ $rsDark ? '#2a3157' : '#dfe4ee' }};
    --rs-border-strong: {{ $rsDark ? '#3a4368' : '#c6cede' }};
    --rs-ink: {{ $rsDark ? '#e7ecf6' : '#151b28' }};
    --rs-ink-2: {{ $rsDark ? '#a4b0c7' : '#48546b' }};
    --rs-muted: {{ $rsDark ? '#77839c' : '#7b869c' }};
    --rs-accent: {{ $rsTheme->primary_color }};
    --rs-accent-soft: {{ $rsDark ? '#1d2942' : '#e7efff' }};
    --rs-ok: {{ $rsDark ? '#4cc38a' : '#1f8a5f' }};
    --rs-ok-bg: {{ $rsDark ? '#122a21' : '#e6f5ee' }};
    --rs-warn: {{ $rsDark ? '#e0a338' : '#b5730b' }};
    --rs-warn-bg: {{ $rsDark ? '#2b2113' : '#fdf3e0' }};
    --rs-bad: {{ $rsDark ? '#f07167' : '#c0392b' }};
    --rs-bad-bg: {{ $rsDark ? '#2e1717' : '#fbeceb' }};
    background: var(--rs-bg); color: var(--rs-ink);
">

    {{-- ===================== BAR ATAS: VIEW + NAV MINGGU + AKSI ===================== --}}
    <div class="bg-navy text-white px-4 py-2.5 flex items-center gap-3 flex-wrap">
        <div class="flex gap-0.5 bg-white/10 rounded-md p-0.5">
            <button type="button" wire:click="$set('view', 'roster')"
                class="px-3.5 py-1.5 rounded text-xs font-bold uppercase tracking-wide cursor-pointer {{ $view === 'roster' ? 'bg-white text-navy' : 'text-white/70 hover:text-white' }}">Roster Mingguan</button>
            <button type="button" wire:click="$set('view', 'shift')"
                class="px-3.5 py-1.5 rounded text-xs font-bold uppercase tracking-wide cursor-pointer {{ $view === 'shift' ? 'bg-white text-navy' : 'text-white/70 hover:text-white' }}">Master Shift</button>
        </div>

        @if ($view === 'roster')
            <div class="flex items-center gap-0.5 bg-white/10 rounded-md p-0.5 ml-auto">
                <button type="button" wire:click="goWeek(-1)" title="Minggu sebelumnya" class="px-2.5 py-1 rounded text-sm cursor-pointer hover:bg-white/15">&lsaquo;</button>
                <div class="px-2 text-center leading-tight">
                    <div class="text-[13px] font-bold tracking-wide">MINGGU {{ $this->weekNumber() }}</div>
                    <div class="text-[10px] text-white/70">{{ $this->weekStart->translatedFormat('d M') }} – {{ $this->weekEnd->translatedFormat('d M Y') }}</div>
                </div>
                <button type="button" wire:click="goWeek(1)" title="Minggu berikutnya" class="px-2.5 py-1 rounded text-sm cursor-pointer hover:bg-white/15">&rsaquo;</button>
            </div>

            <span class="text-[10px] font-bold px-2.5 py-1 rounded-md uppercase {{ $locked ? 'bg-[#e2f4e6] text-[#1e7d3a]' : 'bg-[#fff8e7] text-[#b45309]' }}">
                {{ $locked ? 'CONFIRMED & TERKUNCI' : 'DRAFT' }}
            </span>

            <button type="button" wire:click="openRotate" class="px-3 py-1.5 rounded-md text-xs font-semibold cursor-pointer bg-white/10 border border-white/20 text-white hover:bg-white/20">⇄ Tukar Shift</button>

            @if ($locked)
                <button type="button" wire:click="unlockBatch" class="px-3 py-1.5 rounded-md text-xs font-semibold cursor-pointer bg-white/10 border border-white/20 text-white hover:bg-white/20">🔓 Buka Kunci</button>
            @else
                <button type="button" wire:click="saveDraft" class="px-3 py-1.5 rounded-md text-xs font-semibold cursor-pointer bg-white/10 border border-white/20 text-white hover:bg-white/20">Simpan</button>
                <button type="button" wire:click="confirmBatch" class="px-3 py-1.5 rounded-md text-xs font-semibold cursor-pointer bg-accent text-white">✓ Publish</button>
            @endif
        @else
            <span class="ml-auto"></span>
            <a href="{{ route('hrm.shift-roster.print', ['mode' => 'shift']) }}" target="_blank"
                class="px-3 py-1.5 rounded-md text-xs font-semibold no-underline bg-white/10 border border-white/20 text-white hover:bg-white/20">🖨 Cetak Definisi Shift</a>
            <button type="button" wire:click="openShiftModal" class="px-3 py-1.5 rounded-md text-xs font-semibold cursor-pointer bg-accent text-white">＋ Tambah Shift</button>
        @endif
    </div>

    @if ($view === 'roster')
        {{-- ===================== BAR JADWAL ===================== --}}
        <div class="bg-[var(--rs-surface-alt)] border-b border-[var(--rs-border)] px-4 py-2.5 flex items-center gap-2.5 flex-wrap">
            <span class="text-[11px] font-bold uppercase tracking-wider text-[var(--rs-muted)]">Jadwal</span>

            <select wire:model.live="scheduleId"
                class="min-w-[250px] px-2.5 py-1.5 rounded-md text-xs font-semibold bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)] cursor-pointer">
                @foreach ($this->schedules as $schedule)
                    <option value="{{ $schedule->schedule_id }}">{{ $schedule->schedule_name }}</option>
                @endforeach
            </select>

            <div class="text-[11px] text-[var(--rs-muted)] flex gap-3 flex-wrap">
                <span>Cakupan <b class="text-[var(--rs-ink-2)]">{{ $this->scheduleDepartments->pluck('department_name')->implode(', ') ?: '—' }}</b></span>
                <span>Terisi <b class="text-[var(--rs-ink-2)]">{{ $this->statAssigned }} pegawai</b>@if (! $this->statAssigned) <span> — minggu ini masih kosong</span>@endif</span>
            </div>

            <div class="ml-auto flex gap-1.5 flex-wrap">
                <button type="button" wire:click="openScheduleModal(false)" class="px-2.5 py-1.5 rounded-md text-[11px] font-semibold cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">＋ Jadwal Baru</button>
                <button type="button" wire:click="openScheduleModal(true)" class="px-2.5 py-1.5 rounded-md text-[11px] font-semibold cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">✎ Edit Jadwal</button>
                <button type="button" wire:click="$set('showDeleteSchedule', true)" class="px-2.5 py-1.5 rounded-md text-[11px] font-semibold cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-bad)]">🗑 Hapus</button>
                <a href="{{ route('hrm.shift-roster.print', ['mode' => 'roster', 'schedule' => $scheduleId, 'week' => $weekOffset]) }}" target="_blank"
                    class="px-2.5 py-1.5 rounded-md text-[11px] font-semibold no-underline bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">🖨 Cetak Jadwal Ini</a>
            </div>
        </div>

        {{-- ===================== FILTER + STATISTIK ===================== --}}
        <div class="bg-[var(--rs-surface)] border-b border-[var(--rs-border)] px-4 py-2.5 flex items-end gap-4 flex-wrap">
            <div class="flex flex-col gap-1">
                <label class="text-[10px] uppercase font-bold tracking-wider text-[var(--rs-muted)]">Departemen</label>
                <select wire:model.live="departmentId"
                    class="min-w-[210px] px-2.5 py-1.5 rounded-md text-xs bg-[var(--rs-surface-alt)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)] cursor-pointer">
                    @foreach ($this->scheduleDepartments as $department)
                        <option value="{{ $department->department_id }}">{{ $department->department_name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-[10px] uppercase font-bold tracking-wider text-[var(--rs-muted)]">Unit Kerja / Mesin</label>
                <select wire:model.live="workCenterId"
                    class="min-w-[240px] px-2.5 py-1.5 rounded-md text-xs bg-[var(--rs-surface-alt)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)] cursor-pointer">
                    @forelse ($this->workCenters as $center)
                        <option value="{{ $center->work_center_id }}">{{ $center->work_center_name }}{{ $center->machine_id ? '' : ' (non-mesin)' }}</option>
                    @empty
                        <option value="">Belum ada unit kerja di departemen ini</option>
                    @endforelse
                </select>
            </div>

            <div class="ml-auto">
                <x-grid.stat-row :count="3">
                    <x-grid.stat-box tone="total" label="Pool Tampil" :value="$this->poolEmployees->count()" />
                    <x-grid.stat-box tone="active" label="Terjadwal" :value="$this->statAssigned" />
                    <x-grid.stat-box tone="warn" label="Belum" :value="$this->statFree" />
                </x-grid.stat-row>
            </div>
        </div>

        {{-- ===================== PAPAN + POOL ===================== --}}
        <div class="px-4 pb-7 pt-4 [display:grid] grid-cols-1 xl:grid-cols-[minmax(0,1fr)_340px] gap-4 items-start">

            {{-- ---------- PAPAN SHIFT ---------- --}}
            <section class="bg-[var(--rs-surface)] border border-[var(--rs-border)] rounded-lg overflow-hidden">
                <div class="flex items-center gap-2.5 px-3.5 py-2.5 border-b border-[var(--rs-border)] flex-wrap">
                    <h2 class="m-0 text-sm font-bold uppercase tracking-wide text-[var(--rs-ink)]">{{ $this->currentWorkCenter?->work_center_name ?: 'Pilih unit kerja' }}</h2>
                    <span class="text-[11px] text-[var(--rs-muted)]">1 penempatan = 1 minggu penuh, jam mengikuti definisi shift</span>
                    <span class="ml-auto text-[11px] text-[var(--rs-muted)]">Dobel-klik kolom shift = ubah jam</span>
                </div>

                <div class="overflow-x-auto">
                    <div class="min-w-[900px]">
                        <div class="[display:grid] grid-cols-[238px_minmax(0,1fr)] bg-[var(--rs-surface-alt)] border-b border-[var(--rs-border-strong)]">
                            <div class="px-3 py-2 text-[11px] font-bold uppercase tracking-wider text-[var(--rs-muted)]">Shift / Pola Jam</div>
                            <div class="[display:grid] grid-cols-7">
                                @foreach ($this->weekDates as $date)
                                    <div class="px-1.5 py-2 text-center border-l border-[var(--rs-border)]">
                                        <div class="text-[13px] font-bold tracking-wide {{ $date->dayOfWeek === 0 ? 'text-[var(--rs-bad)]' : 'text-[var(--rs-ink-2)]' }}">{{ $date->translatedFormat('D') }}</div>
                                        <div class="text-[10px] text-[var(--rs-muted)] tabular-nums">{{ $date->format('d/m') }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        @forelse ($shifts as $shift)
                            @php
                                $pattern = $shift->dayPattern();
                                $occupants = $board[$shift->shift_id] ?? [];
                                $count = count($occupants);
                                $capacity = $shift->capacity();
                                $percent = min(100, (int) round($count / max(1, $capacity) * 100));
                                $barColor = $count > $capacity ? 'var(--rs-bad)' : ($count >= $capacity ? 'var(--rs-ok)' : $shift->color());
                                $capClass = $count > $capacity ? 'text-[var(--rs-bad)]' : ($count === $capacity ? 'text-[var(--rs-ok)]' : ($count === 0 ? 'text-[var(--rs-muted)]' : 'text-[var(--rs-warn)]'));
                            @endphp
                            <div wire:key="shiftrow-{{ $shift->shift_id }}" class="[display:grid] grid-cols-[238px_minmax(0,1fr)] border-b border-[var(--rs-border)]">
                                <div wire:dblclick="openShiftModal({{ $shift->shift_id }})" title="Dobel-klik untuk ubah nama & jam shift ini"
                                    class="px-3 py-2.5 border-r border-[var(--rs-border)] bg-[var(--rs-surface-alt)] flex flex-col gap-1.5 cursor-pointer hover:bg-[var(--rs-accent-soft)]">
                                    <span class="text-[15px] font-bold tracking-wide flex items-center gap-2 text-[var(--rs-ink)]">
                                        <i class="w-2.5 h-2.5 rounded-sm shrink-0 inline-block" style="background: {{ $shift->color() }}"></i>{{ $shift->shift_name }}
                                        @if ($shift->activeDayCount() === 0)
                                            <span class="text-[9.5px] font-bold uppercase px-1.5 py-0.5 rounded bg-[var(--rs-warn-bg)] text-[var(--rs-warn)]" title="Isi jam kerja per hari di Master Shift dulu">jam kosong</span>
                                        @endif
                                    </span>
                                    <span class="text-[10px] leading-relaxed text-[var(--rs-ink-2)] font-mono">
                                        @foreach ($shift->summaryLines() as $line)
                                            {{ $line }}<br>
                                        @endforeach
                                        <em class="not-italic text-[var(--rs-muted)]">{{ $shift->activeDayCount() }} hari · {{ \App\Models\Master\EmpShift::formatMinutes($shift->weekMinutes()) }}/minggu</em>
                                    </span>
                                    <span class="flex items-center gap-2">
                                        <span class="flex-1 h-1.5 rounded-full bg-[var(--rs-sunken)] overflow-hidden">
                                            <i class="block h-full rounded-full" style="width: {{ $percent }}%; background: {{ $barColor }}"></i>
                                        </span>
                                        <span class="text-[11px] font-mono tabular-nums {{ $capClass }}">{{ $count }}/{{ $capacity }}</span>
                                    </span>
                                </div>

                                <div class="min-h-[112px] flex flex-col"
                                    x-data="{ over: false }"
                                    x-on:dragover.prevent="over = true"
                                    x-on:dragleave="over = false"
                                    x-on:drop.prevent="over = false; $wire.assignToShift({{ $shift->shift_id }}, JSON.parse($event.dataTransfer.getData('text/plain')).employeeId, JSON.parse($event.dataTransfer.getData('text/plain')).fromBoard)"
                                    :class="over ? 'bg-[var(--rs-accent-soft)] outline outline-2 outline-dashed outline-[var(--rs-accent)] -outline-offset-2' : ''">
                                    <div class="[display:grid] grid-cols-7 border-b border-dashed border-[var(--rs-border)]">
                                        @foreach ($pattern as $day)
                                            <div class="px-1 py-1 text-center border-l border-[var(--rs-border)] text-[9.5px] font-mono leading-snug tabular-nums {{ $day['on'] ? 'text-[var(--rs-ink-2)]' : 'text-[var(--rs-muted)] bg-[var(--rs-sunken)]' }}">
                                                @if ($day['on'])
                                                    {{ $day['in'] }}
                                                    <i class="block not-italic text-[9px] text-[var(--rs-muted)]">{{ $day['out'] }}{{ \App\Models\Master\EmpShift::isNight($day) ? ' +1' : '' }}</i>
                                                @else
                                                    LIBUR
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="flex-1 p-2">
                                        @if ($count)
                                            <div class="flex flex-wrap gap-1.5 items-start">
                                                @foreach ($occupants as $employeeId)
                                                    @php
                                                        $employee = $this->employeeIndex[$employeeId] ?? null;
                                                        $noSkill = ! $this->hasSkill($employeeId);
                                                    @endphp
                                                    <div wire:key="chip-{{ $shift->shift_id }}-{{ $employeeId }}" draggable="true"
                                                        x-on:dragstart="$event.dataTransfer.setData('text/plain', JSON.stringify({employeeId: {{ $employeeId }}, fromBoard: true}))"
                                                        title="{{ $employee?->full_name }}{{ $noSkill ? ' · belum bersertifikat unit ini' : '' }}"
                                                        class="inline-flex items-center gap-1.5 rounded-md border border-l-[3px] px-1.5 py-1 max-w-[190px] cursor-grab bg-[var(--rs-surface)] {{ $noSkill ? 'border-[var(--rs-warn)] bg-[var(--rs-warn-bg)]' : 'border-[var(--rs-border-strong)]' }}"
                                                        style="border-left-color: {{ $shift->color() }}">
                                                        <span class="w-5 h-5 rounded shrink-0 grid place-items-center text-[10px] font-bold text-white bg-gradient-to-br {{ \App\Support\Grid\Avatar::gradient($employeeId) }}">{{ $this->initials($employee?->full_name ?? '?') }}</span>
                                                        <span class="min-w-0 flex flex-col leading-tight">
                                                            <b class="text-[11.5px] font-semibold truncate text-[var(--rs-ink)]">{{ $employee?->full_name ?? '?' }}</b>
                                                            <span class="text-[9.5px] font-mono text-[var(--rs-muted)] truncate">{{ $employee?->employee_code }} · {{ $employee?->position?->position_name ?? '—' }}</span>
                                                        </span>
                                                        <span class="text-[9.5px] font-mono text-[var(--rs-muted)] border border-[var(--rs-border)] rounded px-1 shrink-0">{{ $noSkill ? '⚠' : '' }}{{ $shift->activeDayCount() }}h</span>
                                                        <button type="button" wire:click="removePlacement({{ $employeeId }})" title="Lepas dari roster"
                                                            class="text-[13px] leading-none px-0.5 rounded text-[var(--rs-muted)] hover:text-[var(--rs-bad)] cursor-pointer">×</button>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="flex items-center justify-center min-h-[64px] text-center px-2.5 text-xs text-[var(--rs-muted)]">
                                                Tarik pegawai ke sini — otomatis mengikuti pola jam shift ini
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="px-4 py-8 text-center text-sm text-[var(--rs-muted)]">
                                Belum ada shift aktif. Buka menu <b>Master Shift</b> untuk mendefinisikan.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 items-center px-4 py-2.5 text-[11px] text-[var(--rs-muted)]">
                    @foreach ($shifts as $shift)
                        <span><i class="inline-block w-2.5 h-2.5 rounded-sm mr-1.5 align-[-1px]" style="background: {{ $shift->color() }}"></i>{{ $shift->shift_name }}</span>
                    @endforeach
                    <span><i class="inline-block w-2.5 h-2.5 rounded-sm mr-1.5 align-[-1px] bg-[var(--rs-warn)]"></i>Tanpa skill unit ini</span>
                    <span class="ml-auto">Jam kerja diambil dari <b>Master Shift</b> · 1 pegawai = 1 shift per minggu, lintas jadwal.</span>
                </div>
            </section>

            {{-- ---------- POOL PEGAWAI ---------- --}}
            <aside class="bg-[var(--rs-surface)] border border-[var(--rs-border)] rounded-lg overflow-hidden xl:sticky xl:top-4">
                <div class="flex items-center gap-2.5 px-3.5 py-2.5 border-b border-[var(--rs-border)]">
                    <h2 class="m-0 text-sm font-bold uppercase tracking-wide text-[var(--rs-ink)]">Pool Pegawai</h2>
                    <span class="text-[11px] text-[var(--rs-muted)] ml-auto">{{ $this->poolEmployees->count() }} tersedia</span>
                </div>

                <div class="px-3 py-2.5 flex flex-col gap-2 border-b border-[var(--rs-border)] bg-[var(--rs-surface-alt)]">
                    <div class="text-[11px] leading-snug rounded-md px-2 py-1.5 bg-[var(--rs-accent-soft)] border border-[var(--rs-border)] text-[var(--rs-ink-2)]">
                        Filter aktif: <b>{{ $this->scheduleDepartments->firstWhere('department_id', $departmentId)?->department_name ?? '—' }}</b> →
                        <b>{{ $this->currentWorkCenter?->work_center_name ?? '—' }}</b>{{ $skillOnly ? ' · skill cocok' : ' · semua skill' }}<br>
                        Belum terjadwal di <b>minggu {{ $this->weekNumber() }}</b>: <b>{{ $this->statFree }}</b> dari departemen ini
                    </div>

                    <input type="search" wire:model.live.debounce.300ms="poolSearch" placeholder="Cari nama atau kode pegawai…"
                        class="w-full px-2.5 py-1.5 rounded-md text-xs bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">

                    <div class="flex items-center gap-2">
                        <label class="text-[10px] uppercase font-bold tracking-wider text-[var(--rs-muted)] whitespace-nowrap">Kelompok</label>
                        <select wire:model.live="groupBy" class="flex-1 px-2 py-1.5 rounded-md text-xs bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)] cursor-pointer">
                            <option value="jabatan">Jabatan</option>
                            <option value="skill">Skill Unit Kerja</option>
                            <option value="golongan">Golongan</option>
                        </select>
                    </div>

                    <label class="flex items-center gap-2 text-xs text-[var(--rs-ink-2)] cursor-pointer">
                        <input type="checkbox" wire:model.live="skillOnly" class="cursor-pointer accent-[var(--rs-accent)]">
                        Hanya yang punya skill unit ini
                    </label>
                </div>

                @if (count($selection))
                    <div draggable="true"
                        x-on:dragstart="$event.dataTransfer.setData('text/plain', JSON.stringify({employeeId: null, fromBoard: false}))"
                        title="Tarik blok ini ke baris shift"
                        class="flex items-center gap-2 px-3 py-2 bg-[var(--rs-accent-soft)] border-b border-[var(--rs-border)] cursor-grab">
                        <span class="text-sm text-[var(--rs-accent)] tracking-tighter">⣿</span>
                        <b class="text-[13px] text-[var(--rs-ink)]">{{ count($selection) }} pegawai dipilih</b>
                        <span class="ml-auto flex gap-1.5">
                            <button type="button" wire:click="selectAllVisible" class="px-2 py-0.5 rounded text-[11px] cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">Pilih semua tampil</button>
                            <button type="button" wire:click="clearSelection" class="px-2 py-0.5 rounded text-[11px] cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">Batal</button>
                        </span>
                    </div>
                @endif

                <div class="max-h-[calc(100vh-300px)] overflow-y-auto">
                    @forelse ($this->poolGroups as $groupName => $members)
                        @php $isCollapsed = in_array($groupName, $collapsed, true); @endphp
                        <div wire:key="grp-{{ \Illuminate\Support\Str::slug($groupName) }}">
                            <div class="flex items-center gap-2 px-3 py-1.5 bg-[var(--rs-sunken)] border-y border-[var(--rs-border)] sticky top-0 z-[1]">
                                <input type="checkbox" wire:click="toggleGroup(@js($groupName))" class="cursor-pointer accent-[var(--rs-accent)]"
                                    @checked(! array_diff(collect($members)->pluck('employee_id')->all(), $selection))>
                                <button type="button" wire:click="toggleCollapse(@js($groupName))" class="text-[10px] w-3 cursor-pointer text-[var(--rs-muted)]">{{ $isCollapsed ? '▶' : '▼' }}</button>
                                <span class="text-[13px] font-bold uppercase tracking-wide text-[var(--rs-ink)]">{{ $groupName }}</span>
                                <span class="ml-auto text-[10.5px] font-mono text-[var(--rs-muted)]">{{ count($members) }} org</span>
                            </div>

                            @if (! $isCollapsed)
                                @foreach ($members as $employee)
                                    @php $isSelected = in_array($employee->employee_id, $selection, true); @endphp
                                    <div wire:key="pool-{{ $employee->employee_id }}" draggable="true"
                                        wire:click="toggleSelect({{ $employee->employee_id }})"
                                        {{-- Menyeret item yang sedang tercentang ikut membawa SELURUH seleksi
                                             (employeeId null = pakai $selection), sama seperti mockup. --}}
                                        x-on:dragstart="$event.dataTransfer.setData('text/plain', JSON.stringify({employeeId: {{ $isSelected ? 'null' : $employee->employee_id }}, fromBoard: false}))"
                                        class="flex items-center gap-2.5 px-3 py-1.5 border-b border-[var(--rs-border)] cursor-grab select-none {{ $isSelected ? 'bg-[var(--rs-accent-soft)]' : 'hover:bg-[var(--rs-surface-alt)]' }}">
                                        <input type="checkbox" @checked($isSelected) class="pointer-events-none shrink-0 accent-[var(--rs-accent)]">
                                        <span class="w-6 h-6 rounded shrink-0 grid place-items-center text-[11px] font-bold text-white bg-gradient-to-br {{ \App\Support\Grid\Avatar::gradient($employee->employee_id) }}">{{ $this->initials($employee->full_name) }}</span>
                                        <span class="min-w-0 flex-1">
                                            <b class="block text-[12.5px] font-semibold truncate text-[var(--rs-ink)]">{{ $employee->full_name }}</b>
                                            <span class="text-[10px] font-mono text-[var(--rs-muted)]">{{ $employee->employee_code }} · {{ count($this->skillMap[$employee->employee_id] ?? []) }} skill</span>
                                        </span>
                                        <span class="text-[10.5px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded shrink-0 bg-[var(--rs-sunken)] text-[var(--rs-ink-2)]">{{ $employee->position?->position_name ?? '—' }}</span>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    @empty
                        <div class="px-4 py-7 text-center text-xs leading-relaxed text-[var(--rs-muted)]">
                            Tidak ada pegawai yang cocok.<br>
                            {{ $skillOnly ? 'Lepas centang "Hanya yang punya skill unit ini" untuk melihat pegawai lain di departemen ini.' : 'Semua pegawai departemen ini sudah terjadwal minggu ini.' }}
                        </div>
                    @endforelse
                </div>
            </aside>
        </div>
    @else
        {{-- ===================== VIEW: MASTER SHIFT ===================== --}}
        <div class="px-4 py-4 flex flex-col gap-3.5 max-w-[1180px]">
            <div class="flex items-end gap-3.5 flex-wrap">
                <div>
                    <h1 class="m-0 text-2xl font-bold uppercase tracking-wide text-[var(--rs-ink)]">Master Shift</h1>
                    <p class="m-0 text-xs text-[var(--rs-muted)] max-w-[62ch]">
                        Definisi pola kerja. Jam bisa berbeda tiap hari — mis. Reguler Senin–Jumat 07:30–16:30 tapi Sabtu 07:30–15:30.
                        Perubahan di sini langsung dipakai seluruh jadwal roster.
                    </p>
                </div>
            </div>

            @foreach ($this->allShifts as $shift)
                @php
                    $pattern = $shift->dayPattern();
                    $used = $this->shiftUsage($shift->shift_id);
                @endphp
                <div wire:key="shiftcard-{{ $shift->shift_id }}"
                    class="bg-[var(--rs-surface)] border border-[var(--rs-border)] rounded-lg overflow-hidden {{ $shift->activate ? '' : 'opacity-60' }}">
                    <div class="flex items-center gap-2.5 px-3.5 py-2.5 border-b border-[var(--rs-border)] flex-wrap border-l-[5px]" style="border-left-color: {{ $shift->color() }}">
                        <span class="font-mono text-[11.5px] px-2 py-1 rounded border border-[var(--rs-border-strong)] bg-[var(--rs-sunken)] text-[var(--rs-ink)] w-[86px] text-center">{{ $shift->shift_code ?: '—' }}</span>
                        <span class="text-[19px] font-bold tracking-wide text-[var(--rs-ink)]">{{ $shift->shift_name }}</span>
                        <span class="text-[11.5px] text-[var(--rs-muted)]">Kuota/unit <b class="text-[var(--rs-ink-2)]">{{ $shift->capacity() }}</b></span>
                        <span class="text-[13px] font-bold px-2.5 py-1 rounded bg-[var(--rs-ok-bg)] text-[var(--rs-ok)] whitespace-nowrap">{{ $shift->activeDayCount() }} hari · {{ \App\Models\Master\EmpShift::formatMinutes($shift->weekMinutes()) }}</span>
                        @if ($used)
                            <span class="text-[11px] text-[var(--rs-muted)]">dipakai {{ $used }} baris roster</span>
                        @endif

                        <span class="ml-auto flex gap-1.5 flex-wrap">
                            <button type="button" wire:click="openShiftModal({{ $shift->shift_id }})" class="px-2.5 py-1 rounded text-xs cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">✎ Ubah</button>
                            <button type="button" wire:click="duplicateShift({{ $shift->shift_id }})" class="px-2.5 py-1 rounded text-xs cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">Duplikat</button>
                            <button type="button" wire:click="toggleShiftActive({{ $shift->shift_id }})" class="px-2.5 py-1 rounded text-xs cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">{{ $shift->activate ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                            <button type="button" wire:click="deleteShift({{ $shift->shift_id }})" wire:confirm="Hapus shift {{ $shift->shift_name }}?" class="px-2.5 py-1 rounded text-xs cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-bad)]">Hapus</button>
                        </span>
                    </div>

                    <div class="[display:grid] grid-cols-2 sm:grid-cols-4 lg:grid-cols-7">
                        @foreach ($pattern as $day)
                            <div class="border-l border-[var(--rs-border)] px-2.5 py-2.5 flex flex-col gap-1.5 {{ $day['on'] ? '' : 'bg-[var(--rs-sunken)]' }}">
                                <b class="text-[13px] font-bold uppercase tracking-wide {{ $day['label'] === 'Min' ? 'text-[var(--rs-bad)]' : 'text-[var(--rs-ink)]' }}">{{ $day['label'] }}</b>
                                @if ($day['on'])
                                    <span class="font-mono text-[11.5px] text-[var(--rs-ink-2)]">{{ $day['in'] }}</span>
                                    <span class="font-mono text-[11.5px] text-[var(--rs-ink-2)]">{{ $day['out'] }}{{ \App\Models\Master\EmpShift::isNight($day) ? ' +1' : '' }}</span>
                                    <span class="text-[10.5px] text-[var(--rs-muted)]">Istirahat {{ $day['brk'] }} mnt</span>
                                    <span class="font-mono text-[11px] font-semibold text-right {{ \App\Models\Master\EmpShift::isNight($day) ? 'text-[#8b5cf6]' : 'text-[var(--rs-ok)]' }}">{{ \App\Models\Master\EmpShift::formatMinutes(\App\Models\Master\EmpShift::dayMinutes($day)) }}</span>
                                @else
                                    <span class="grid place-items-center min-h-[70px] text-[13px] tracking-widest text-[var(--rs-muted)]">LIBUR</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <p class="text-xs text-[var(--rs-muted)] m-0">
                Total {{ $this->allShifts->count() }} shift · {{ $shifts->count() }} aktif. Kode shift dipakai sebagai kunci di papan roster — ubah kode hanya sebelum jadwal dipublikasikan.
            </p>
        </div>
    @endif

    {{-- ===================== MODAL: JADWAL ===================== --}}
    @if ($showScheduleModal)
        <div class="fixed inset-0 bg-black/50 z-[70] p-5 overflow-auto" wire:click.self="$set('showScheduleModal', false)">
            <div class="max-w-[520px] mx-auto rounded-xl border border-[var(--rs-border)] bg-[var(--rs-surface)] text-[var(--rs-ink)]">
                <div class="flex items-center gap-2.5 px-4 py-3 border-b border-[var(--rs-border)]">
                    <h3 class="m-0 text-base font-bold uppercase tracking-wide">{{ $scheduleEditing ? 'Ubah Jadwal' : 'Jadwal Baru' }}</h3>
                    <button type="button" wire:click="$set('showScheduleModal', false)" class="ml-auto text-xl leading-none cursor-pointer text-[var(--rs-muted)]">×</button>
                </div>

                <div class="p-4 flex flex-col gap-3">
                    <x-form.input label="Nama Jadwal *" name="sched_name" wire:model="sched_name" placeholder="mis. Jadwal Cetak Offset – Shift Malam" />

                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] text-muted uppercase font-semibold">Cakupan Departemen *</label>
                        <div class="[display:grid] grid-cols-2 gap-1.5 max-h-[260px] overflow-y-auto">
                            @foreach (\App\Models\Master\OrgDepartment::where('activate', true)->orderBy('department_name')->get() as $department)
                                <label class="flex items-center gap-2 text-xs px-2 py-1.5 rounded-md border border-[var(--rs-border)] bg-[var(--rs-surface-alt)] cursor-pointer">
                                    <input type="checkbox" value="{{ $department->department_id }}" wire:model="sched_depts" class="accent-[var(--rs-accent)]">
                                    {{ $department->department_name }}
                                </label>
                            @endforeach
                        </div>
                        @error('sched_depts') <span class="text-[10.5px] text-red-600">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex gap-2 justify-end px-4 py-3 border-t border-[var(--rs-border)] bg-[var(--rs-surface-alt)] rounded-b-xl">
                    <button type="button" wire:click="$set('showScheduleModal', false)" class="px-3.5 py-1.5 rounded-md text-xs font-semibold cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">Batal</button>
                    <button type="button" wire:click="saveSchedule" class="px-3.5 py-1.5 rounded-md text-xs font-semibold cursor-pointer bg-accent text-white">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===================== MODAL: HAPUS JADWAL ===================== --}}
    @if ($showDeleteSchedule)
        <div class="fixed inset-0 bg-black/50 z-[70] p-5 overflow-auto" wire:click.self="$set('showDeleteSchedule', false)">
            <div class="max-w-[480px] mx-auto rounded-xl border border-[var(--rs-border)] bg-[var(--rs-surface)] text-[var(--rs-ink)]">
                <div class="flex items-center gap-2.5 px-4 py-3 border-b border-[var(--rs-border)]">
                    <h3 class="m-0 text-base font-bold uppercase tracking-wide">Hapus Jadwal</h3>
                    <button type="button" wire:click="$set('showDeleteSchedule', false)" class="ml-auto text-xl leading-none cursor-pointer text-[var(--rs-muted)]">×</button>
                </div>
                <div class="p-4 text-sm">
                    <p class="mt-0 mb-3">Hapus jadwal <b>"{{ $this->currentSchedule?->schedule_name }}"</b>?</p>
                    @if ($this->scheduleRosterCount())
                        <div class="text-xs rounded-md px-3 py-2 bg-[var(--rs-warn-bg)] border border-[var(--rs-warn)] text-[var(--rs-warn)]">
                            Jadwal ini berisi <b>{{ $this->scheduleRosterCount() }} baris roster</b> di seluruh minggu. Semua pegawai tersebut akan dilepas dan kembali ke pool.
                        </div>
                    @else
                        <p class="text-xs text-[var(--rs-muted)] m-0">Jadwal ini masih kosong.</p>
                    @endif
                    <p class="text-[11px] text-[var(--rs-muted)] mt-3 mb-0">Tindakan ini tidak bisa dibatalkan.</p>
                </div>
                <div class="flex gap-2 justify-end px-4 py-3 border-t border-[var(--rs-border)] bg-[var(--rs-surface-alt)] rounded-b-xl">
                    <button type="button" wire:click="$set('showDeleteSchedule', false)" class="px-3.5 py-1.5 rounded-md text-xs font-semibold cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">Batal</button>
                    <button type="button" wire:click="deleteSchedule" class="px-3.5 py-1.5 rounded-md text-xs font-semibold cursor-pointer bg-[var(--rs-bad)] text-white">Hapus Jadwal</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===================== MODAL: EDIT SHIFT ===================== --}}
    @if ($showShiftModal)
        <div class="fixed inset-0 bg-black/50 z-[70] p-5 overflow-auto" wire:click.self="$set('showShiftModal', false)">
            <div class="max-w-[1020px] mx-auto rounded-xl border border-[var(--rs-border)] bg-[var(--rs-surface)] text-[var(--rs-ink)]">
                <div class="flex items-center gap-2.5 px-4 py-3 border-b border-[var(--rs-border)]">
                    <h3 class="m-0 text-base font-bold uppercase tracking-wide">{{ $shiftEditingId ? 'Ubah Shift' : 'Shift Baru' }}</h3>
                    <button type="button" wire:click="$set('showShiftModal', false)" class="ml-auto text-xl leading-none cursor-pointer text-[var(--rs-muted)]">×</button>
                </div>

                <div class="p-4">
                    <div class="flex gap-3 flex-wrap items-end mb-3.5">
                        <div class="flex-1 min-w-[200px]">
                            <x-form.input label="Nama Shift *" name="shiftForm.name" wire:model="shiftForm.name" />
                        </div>
                        <div class="w-[120px]">
                            <x-form.input label="Kode *" name="shiftForm.code" wire:model="shiftForm.code" maxlength="10" />
                        </div>
                        <div class="w-[130px] flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Warna</label>
                            <input type="color" wire:model="shiftForm.color" class="w-full h-[30px] rounded border border-[var(--rs-border-strong)] bg-[var(--rs-surface)] cursor-pointer">
                        </div>
                        <div class="w-[120px]">
                            <x-form.input label="Kuota/Unit" name="shiftForm.cap" type="number" wire:model="shiftForm.cap" min="1" max="30" />
                        </div>
                        <label class="flex items-center gap-2 text-xs pb-1.5 cursor-pointer text-[var(--rs-ink-2)]">
                            <input type="checkbox" wire:model="shiftForm.active" class="accent-[var(--rs-accent)] cursor-pointer"> Aktif
                        </label>
                    </div>

                    <div class="flex gap-2 flex-wrap items-center mb-2.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-[var(--rs-muted)]">Jam per hari</span>
                        <button type="button" wire:click="fillDays(5)" class="px-2 py-0.5 rounded text-[11px] cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">Samakan Sen–Jum</button>
                        <button type="button" wire:click="fillDays(6)" class="px-2 py-0.5 rounded text-[11px] cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">Samakan Sen–Sab</button>
                    </div>

                    <div class="[display:grid] grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 border border-[var(--rs-border)] rounded-md overflow-hidden">
                        @foreach (\App\Models\Master\EmpShift::DAY_ORDER as $i => $dayName)
                            <div wire:key="shiftday-{{ $i }}" class="border-l border-[var(--rs-border)] px-2 py-2 flex flex-col gap-1.5 {{ ($shiftForm['days'][$i]['on'] ?? false) ? '' : 'bg-[var(--rs-sunken)]' }}">
                                <label class="flex items-center gap-1.5 text-[13px] font-bold uppercase tracking-wide cursor-pointer {{ $dayName === 'Sunday' ? 'text-[var(--rs-bad)]' : 'text-[var(--rs-ink)]' }}">
                                    {{ \App\Models\Master\EmpShift::DAY_LABELS[$dayName] }}
                                    <input type="checkbox" wire:model.live="shiftForm.days.{{ $i }}.on" class="ml-auto accent-[var(--rs-accent)] cursor-pointer">
                                </label>

                                @if ($shiftForm['days'][$i]['on'] ?? false)
                                    <input type="time" wire:model="shiftForm.days.{{ $i }}.in" class="w-full px-1 py-1 rounded border border-[var(--rs-border-strong)] bg-[var(--rs-surface-alt)] text-[11.5px] font-mono text-[var(--rs-ink)]">
                                    <input type="time" wire:model="shiftForm.days.{{ $i }}.out" class="w-full px-1 py-1 rounded border border-[var(--rs-border-strong)] bg-[var(--rs-surface-alt)] text-[11.5px] font-mono text-[var(--rs-ink)]">
                                    <div class="flex items-center gap-1 text-[10.5px] text-[var(--rs-muted)]">
                                        Ist.
                                        <input type="number" min="0" max="240" step="5" wire:model="shiftForm.days.{{ $i }}.brk"
                                            class="w-[52px] px-1 py-0.5 rounded border border-[var(--rs-border-strong)] bg-[var(--rs-surface-alt)] text-[11px] font-mono text-center text-[var(--rs-ink)]"> mnt
                                    </div>
                                @else
                                    <span class="grid place-items-center min-h-[70px] text-[13px] tracking-widest text-[var(--rs-muted)]">LIBUR</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @error('shiftForm.days') <span class="text-[10.5px] text-red-600">{{ $message }}</span> @enderror

                    <p class="text-[11.5px] text-[var(--rs-muted)] mt-2.5 mb-0">
                        Jam pulang lebih kecil dari jam masuk berarti shift lewat tengah malam (mis. 21:15–05:00) dan otomatis dihitung sebagai lintas hari.
                    </p>
                </div>

                <div class="flex gap-2 justify-end items-center px-4 py-3 border-t border-[var(--rs-border)] bg-[var(--rs-surface-alt)] rounded-b-xl">
                    <span class="mr-auto text-[11.5px] text-[var(--rs-muted)]">Perubahan berlaku untuk semua jadwal yang memakai shift ini.</span>
                    <button type="button" wire:click="$set('showShiftModal', false)" class="px-3.5 py-1.5 rounded-md text-xs font-semibold cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">Batal</button>
                    <button type="button" wire:click="saveShift" class="px-3.5 py-1.5 rounded-md text-xs font-semibold cursor-pointer bg-accent text-white">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===================== MODAL: ROTASI ANTAR MINGGU ===================== --}}
    @if ($showRotateModal)
        @php $preview = $this->rotatePreview; @endphp
        <div class="fixed inset-0 bg-black/50 z-[70] p-5 overflow-auto" wire:click.self="$set('showRotateModal', false)">
            <div class="max-w-[560px] mx-auto rounded-xl border border-[var(--rs-border)] bg-[var(--rs-surface)] text-[var(--rs-ink)]">
                <div class="flex items-center gap-2.5 px-4 py-3 border-b border-[var(--rs-border)]">
                    <h3 class="m-0 text-base font-bold uppercase tracking-wide">Tukar Shift ke Minggu Lain</h3>
                    <button type="button" wire:click="$set('showRotateModal', false)" class="ml-auto text-xl leading-none cursor-pointer text-[var(--rs-muted)]">×</button>
                </div>

                <div class="p-4 flex flex-col gap-3">
                    <p class="m-0 text-xs text-[var(--rs-muted)]">
                        Menyalin roster <b>"{{ $this->currentSchedule?->schedule_name }}"</b> dari minggu sumber ke minggu tujuan sambil menukar shift-nya — supaya tidak perlu input ulang tiap minggu.
                    </p>

                    <div class="flex gap-3 flex-wrap">
                        <div class="flex-1 min-w-[130px]">
                            <x-form.input label="Minggu Sumber" type="number" wire:model.live="rot_from" />
                        </div>
                        <div class="flex-1 min-w-[130px]">
                            <x-form.input label="Minggu Tujuan" type="number" wire:model.live="rot_to" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] text-muted uppercase font-semibold">Pola</label>
                        <select wire:model.live="rot_mode" class="px-2.5 py-1.5 rounded-md text-xs bg-[var(--rs-surface-alt)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)] cursor-pointer">
                            <option value="swap">Tukar 2 shift (A ⇄ B)</option>
                            <option value="cycle">Rotasi berputar (semua shift maju satu)</option>
                            <option value="copy">Salin sama persis (tanpa rotasi)</option>
                        </select>
                    </div>

                    @if ($rot_mode === 'swap')
                        <div class="flex gap-3 flex-wrap items-end">
                            <div class="flex-1 min-w-[170px] flex flex-col gap-1">
                                <label class="text-[10px] text-muted uppercase font-semibold">Shift A</label>
                                <select wire:model.live="rot_a" class="px-2.5 py-1.5 rounded-md text-xs bg-[var(--rs-surface-alt)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)] cursor-pointer">
                                    @foreach ($shifts as $shift)
                                        <option value="{{ $shift->shift_id }}">{{ $shift->shift_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <span class="text-xl text-[var(--rs-accent)] pb-1.5">⇄</span>
                            <div class="flex-1 min-w-[170px] flex flex-col gap-1">
                                <label class="text-[10px] text-muted uppercase font-semibold">Shift B</label>
                                <select wire:model.live="rot_b" class="px-2.5 py-1.5 rounded-md text-xs bg-[var(--rs-surface-alt)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)] cursor-pointer">
                                    @foreach ($shifts as $shift)
                                        <option value="{{ $shift->shift_id }}">{{ $shift->shift_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endif

                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] text-muted uppercase font-semibold">Cakupan</label>
                        <select wire:model.live="rot_scope" class="px-2.5 py-1.5 rounded-md text-xs bg-[var(--rs-surface-alt)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)] cursor-pointer">
                            <option value="all">Seluruh unit kerja di jadwal ini</option>
                            <option value="wc">Hanya {{ $this->currentWorkCenter?->work_center_name }}</option>
                        </select>
                    </div>

                    <label class="flex items-center gap-2 text-xs cursor-pointer text-[var(--rs-ink-2)]">
                        <input type="checkbox" wire:model.live="rot_over" class="accent-[var(--rs-accent)] cursor-pointer">
                        Timpa data yang sudah ada di minggu tujuan
                    </label>

                    <div class="text-[11.5px] leading-snug rounded-md px-3 py-2 bg-[var(--rs-sunken)] border border-[var(--rs-border)] text-[var(--rs-ink-2)]">
                        @if (! $preview['valid'])
                            <b class="text-[var(--rs-bad)]">Minggu sumber dan tujuan harus berbeda.</b>
                        @else
                            <b>{{ $preview['moves'] }}</b> penempatan akan dipindah dari minggu <b>{{ $rot_from }}</b> ke minggu <b>{{ $rot_to }}</b>.
                            @if ($preview['occupied'])
                                <br><b class="text-[var(--rs-warn)]">Minggu {{ $rot_to }} sudah berisi data jadwal ini.</b> {{ $rot_over ? 'Akan ditimpa.' : 'Centang "Timpa" atau data lama dipertahankan.' }}
                            @endif
                            @if ($preview['busy'])
                                <br><b class="text-[var(--rs-warn)]">{{ $preview['busy'] }} pegawai</b> sudah punya shift lain di minggu {{ $rot_to }} — akan dilewati.
                            @endif
                        @endif
                    </div>
                </div>

                <div class="flex gap-2 justify-end px-4 py-3 border-t border-[var(--rs-border)] bg-[var(--rs-surface-alt)] rounded-b-xl">
                    <button type="button" wire:click="$set('showRotateModal', false)" class="px-3.5 py-1.5 rounded-md text-xs font-semibold cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">Batal</button>
                    <button type="button" wire:click="runRotate" class="px-3.5 py-1.5 rounded-md text-xs font-semibold cursor-pointer bg-accent text-white">Jalankan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===================== TOAST ===================== --}}
    @if ($toast)
        <div wire:key="toast" wire:click="dismissToast"
            class="fixed left-1/2 -translate-x-1/2 bottom-6 z-[90] px-4 py-2.5 rounded-lg text-xs text-white shadow-lg cursor-pointer max-w-[calc(100%-32px)] {{ $toastBad ? 'bg-[var(--rs-bad)]' : 'bg-navy' }}">
            {{ $toast }}
        </div>
    @endif
</div>
