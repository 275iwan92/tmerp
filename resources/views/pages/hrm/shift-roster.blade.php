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
use App\Models\Sys\SysGridPreference;
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
    // Pool sengaja HANYA punya 2 filter (keputusan user 2026-09-13): Departemen
    // dan pencarian nama. Filter Unit Kerja, Kelompok, dan centang "hanya yang
    // punya skill" dihapus karena dinilai tidak praktis. $departmentId = null
    // berarti "Semua Departemen" -- termasuk pegawai yang tidak punya departemen.
    public string $poolSearch = '';

    public array $selection = [];

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

    // Shift yang disembunyikan user dari papan. Disimpan PER USER supaya halaman
    // cetak (proses request terpisah) ikut setelan yang sama -- permintaan user
    // 2026-09-12: "saat di cetak pun mengikuti hide and show yang sudah di set".
    public array $hiddenShifts = [];

    public bool $showVisibilityModal = false;

    public function mount(): void
    {
        $this->scheduleId = EmpRosterSchedule::where('activate', true)->orderBy('schedule_id')->value('schedule_id');
        $this->hiddenShifts = self::loadHiddenShifts(Auth::user()->login);
        $this->syncScopeDefaults();
    }

    // Dipakai layar ini DAN halaman cetak. Menumpang tabel preferensi grid yang
    // sudah ada (tm2sys.sys_grid_preference) -- isi JSON-nya di grid_key ini
    // adalah daftar shift_id yang DISEMBUNYIKAN (bukan yang tampil), supaya
    // shift baru otomatis ikut terlihat tanpa perlu diatur ulang.
    public const HIDDEN_SHIFTS_KEY = 'hrm.shift-roster.hidden-shifts';

    public static function loadHiddenShifts(string $userLogin): array
    {
        $row = SysGridPreference::where('user_login', $userLogin)
            ->where('grid_key', self::HIDDEN_SHIFTS_KEY)
            ->first();

        return collect($row->visible_columns ?? [])->map(fn ($v) => (int) $v)->all();
    }

    public function toggleShiftVisibility(int $shiftId): void
    {
        $this->hiddenShifts = in_array($shiftId, $this->hiddenShifts, true)
            ? array_values(array_diff($this->hiddenShifts, [$shiftId]))
            : [...$this->hiddenShifts, $shiftId];

        SysGridPreference::updateOrCreate(
            ['user_login' => Auth::user()->login, 'grid_key' => self::HIDDEN_SHIFTS_KEY],
            ['visible_columns' => array_values($this->hiddenShifts)],
        );

        unset($this->visibleShifts);

        $shift = $this->shifts->firstWhere('shift_id', $shiftId);
        $isHidden = in_array($shiftId, $this->hiddenShifts, true);
        $this->flash(($shift?->shift_name ?? 'Shift').($isHidden ? ' disembunyikan — ikut hilang dari cetakan.' : ' ditampilkan kembali.'));
    }

    public function showAllShifts(): void
    {
        $this->hiddenShifts = [];

        SysGridPreference::updateOrCreate(
            ['user_login' => Auth::user()->login, 'grid_key' => self::HIDDEN_SHIFTS_KEY],
            ['visible_columns' => []],
        );

        unset($this->visibleShifts);
        $this->flash('Semua shift ditampilkan kembali.');
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

    // Departemen null (Semua Departemen) -> semua unit kerja aktif ikut tampil,
    // supaya papan tetap bisa dipilih unitnya tanpa perlu memilih departemen.
    #[Computed]
    public function workCenters()
    {
        return EmpWorkCenter::with('department')
            ->where('activate', true)
            ->when($this->departmentId, fn ($q) => $q->where('department_id', $this->departmentId))
            ->orderBy('department_id')
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

    // Papan & legenda & cetak pakai ini. Hide/show adalah setelan TAMPILAN --
    // data penempatan shift yang disembunyikan tetap utuh di DB, dan operasi
    // data (assign, rotasi) tetap memakai $shifts penuh.
    #[Computed]
    public function visibleShifts()
    {
        return $this->shifts->reject(fn ($shift) => in_array($shift->shift_id, $this->hiddenShifts, true))->values();
    }

    // Shift tersembunyi yang ternyata masih punya penempatan di minggu ini --
    // perlu diberitahu supaya user tidak salah sangka roster-nya kosong.
    public function hiddenWithPlacements(): int
    {
        $board = $this->boardPlacements;

        return collect($this->hiddenShifts)->sum(fn ($id) => count($board[$id] ?? []));
    }

    #[Computed]
    public function allShifts()
    {
        return EmpShift::with('details')->orderBy('activate', 'desc')->orderBy('shift_code')->orderBy('shift_name')->get();
    }

    // departmentId TIDAK dipaksa ke departemen pertama -- null = Semua Departemen
    // adalah keadaan sah (dan default saat layar dibuka). Yang dijaga hanya:
    // kalau departemen dipilih tapi bukan bagian cakupan jadwal ini, dikembalikan
    // ke Semua Departemen; dan unit kerja aktif harus selalu valid.
    private function syncScopeDefaults(): void
    {
        if ($this->departmentId && ! $this->scheduleDepartments->firstWhere('department_id', $this->departmentId)) {
            $this->departmentId = null;
        }

        unset($this->workCenters);
        $centers = $this->workCenters;

        if (! $centers->firstWhere('work_center_id', $this->workCenterId)) {
            $this->workCenterId = $this->firstFilledCenter($centers) ?? $centers->first()?->work_center_id;
        }
    }

    // Unit kerja awal = yang sudah ada roster minggu ini, supaya layar tidak
    // terbuka di unit kosong (mis. Cetak 1) padahal jadwalnya ada di unit lain.
    private function firstFilledCenter($centers): ?int
    {
        $filled = collect($this->weekPlacements)->pluck('work_center_id')->unique();

        return $centers->first(fn ($c) => $filled->contains((int) $c->work_center_id))?->work_center_id;
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
            $this->employeeIndex,
            $this->skillMap,
            $this->batch,
            $this->statAssigned,
            $this->statFree,
            $this->formasi,
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
        $this->forgetWeekData();
        $this->workCenterId = $this->firstFilledCenter($this->workCenters) ?? $this->workCenters->first()?->work_center_id;
        $this->selection = [];
    }

    public function updatedWorkCenterId(): void
    {
        $this->forgetWeekData();
        $this->selection = [];
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

    // $departmentId null = "Semua Departemen" (default layar dibuka) -- termasuk
    // pegawai yang position-nya sendiri tidak punya departemen. Bukan filter
    // tambahan, jadi selalu memuat SEMUA pegawai aktif ketika null.
    #[Computed]
    public function employeeIndex()
    {
        $placedIds = array_keys($this->weekPlacements);

        return EmpEmployee::with('position.department', 'position.level')
            ->where(function ($q) use ($placedIds) {
                $q->whereIn('employee_id', $placedIds ?: [0]);

                if ($this->departmentId) {
                    $q->orWhereHas('position', fn ($p) => $p->where('department_id', $this->departmentId));
                } else {
                    $q->orWhere('activate', true);
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

    // Sengaja HANYA 2 filter (keputusan user 2026-09-13): departemen (lewat
    // $employeeIndex, null = semua) dan pencarian nama/kode. Filter skill dan
    // pengelompokan yang tadinya ada di sini dihapus -- terlalu banyak kontrol
    // untuk sekadar memilih siapa yang mau ditarik ke papan.
    #[Computed]
    public function poolEmployees()
    {
        $placed = array_keys($this->weekPlacements);
        $search = trim($this->poolSearch);

        return $this->employeeIndex
            ->filter(fn (EmpEmployee $e) => $e->activate
                && ! in_array($e->employee_id, $placed)
                && ($search === ''
                    || str_contains(strtolower($e->full_name), strtolower($search))
                    || str_contains(strtolower((string) $e->employee_code), strtolower($search))))
            ->sortBy('full_name')
            ->values();
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
        return $this->poolEmployees->count();
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

    public function selectAllVisible(): void
    {
        $this->selection = $this->poolEmployees->pluck('employee_id')->map(fn ($v) => (int) $v)->all();
    }

    public function clearSelection(): void
    {
        $this->selection = [];
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

    // ================= FORMASI HARIAN =================
    // Bentuk sheet formasi HR (Posisi x Jam Kerja x Tanggal) -- permintaan user
    // 2026-09-19 supaya jadwal manual HR bisa dilihat & diinput persis seperti
    // biasanya. Detail di App\Services\Hrm\RosterFormationService.
    public bool $formasiAllUnits = false;

    public bool $showCellModal = false;

    public ?int $cell_wc = null;

    public ?int $cell_shift = null;

    public string $cell_date = '';

    public array $cell_ids = [];

    public string $cell_scope = 'day'; // day | rest | week

    public string $cell_search = '';

    #[Computed]
    public function formasi(): array
    {
        return $this->scheduleId
            ? app(\App\Services\Hrm\RosterFormationService::class)->matrix($this->scheduleId, $this->weekStart, $this->formasiAllUnits)
            : [];
    }

    public function updatedFormasiAllUnits(): void
    {
        unset($this->formasi);
    }

    public function openCell(int $workCenterId, ?int $shiftId, string $date): void
    {
        $this->cell_wc = $workCenterId;
        $this->cell_shift = $shiftId;
        $this->cell_date = $date;
        $this->cell_scope = 'day';
        $this->cell_search = '';
        $this->cell_ids = $shiftId
            ? EmpShiftRoster::where('schedule_id', $this->scheduleId)->where('work_center_id', $workCenterId)
                ->where('shift_id', $shiftId)->whereDate('roster_date', $date)->pluck('employee_id')->map(fn ($v) => (int) $v)->all()
            : [];
        $this->showCellModal = true;
    }

    #[Computed]
    public function cellPeople()
    {
        return EmpEmployee::whereIn('employee_id', $this->cell_ids ?: [0])->orderBy('full_name')->get();
    }

    #[Computed]
    public function cellCandidates()
    {
        $term = trim($this->cell_search);

        return EmpEmployee::with('position')->where('activate', true)
            ->whereNotIn('employee_id', $this->cell_ids ?: [0])
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('full_name', 'like', "%{$term}%")
                ->orWhere('nickname', 'like', "%{$term}%")->orWhere('employee_code', 'like', "%{$term}%")))
            ->orderBy('full_name')->limit(25)->get();
    }

    public function addToCell(int $employeeId): void
    {
        $this->cell_ids = array_values(array_unique([...$this->cell_ids, $employeeId]));
        $this->cell_search = '';
        unset($this->cellPeople, $this->cellCandidates);
    }

    public function removeFromCell(int $employeeId): void
    {
        $this->cell_ids = array_values(array_diff($this->cell_ids, [$employeeId]));
        unset($this->cellPeople, $this->cellCandidates);
    }

    public function saveCell(): void
    {
        if ($this->isLocked()) {
            $this->flash('Minggu ini sudah CONFIRMED & terkunci — buka kunci dulu untuk mengubah formasi.', true);

            return;
        }
        $shift = $this->cell_shift ? $this->shifts->firstWhere('shift_id', $this->cell_shift) : null;
        if (! $shift || ! $this->cell_wc) {
            $this->flash('Pilih jam kerja (shift) dulu.', true);

            return;
        }

        $date = Carbon::parse($this->cell_date);
        $from = $this->cell_scope === 'week' ? $this->weekStart->copy() : $date;
        $to = $this->cell_scope === 'day' ? $date : $this->weekEnd->copy();
        $pattern = collect($shift->dayPattern())->keyBy('day');
        $dates = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            if ($d->isSameDay($date) || ($pattern[$d->format('l')]['on'] ?? false)) {
                $dates[] = $d->toDateString();
            }
        }

        app(\App\Services\Hrm\RosterFormationService::class)
            ->assign($this->scheduleId, $this->cell_wc, $shift->shift_id, $dates, $this->cell_ids, Auth::user()->login);

        $this->showCellModal = false;
        unset($this->formasi);
        $this->forgetWeekData();
        $this->flash(count($this->cell_ids).' orang → '.($this->currentCellCenter()?->work_center_name ?? '').' · '.$shift->shift_name.' · '.count($dates).' hari.');
    }

    public function currentCellCenter(): ?EmpWorkCenter
    {
        return $this->cell_wc ? EmpWorkCenter::find($this->cell_wc) : null;
    }

    public function copyPrevWeek(): void
    {
        try {
            $n = app(\App\Services\Hrm\RosterFormationService::class)
                ->copyWeek($this->scheduleId, $this->weekStart->copy()->subWeek(), $this->weekStart, Auth::user()->login);
            unset($this->formasi);
            $this->forgetWeekData();
            $this->flash($n ? "Formasi minggu lalu disalin ({$n} baris) — tinggal ubah yang berbeda." : 'Minggu lalu kosong, tidak ada yang disalin.', ! $n);
        } catch (\RuntimeException $e) {
            $this->flash($e->getMessage(), true);
        }
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
    $visibleShifts = $this->visibleShifts;
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
            <button type="button" wire:click="$set('view', 'formasi')"
                class="px-3.5 py-1.5 rounded text-xs font-bold uppercase tracking-wide cursor-pointer {{ $view === 'formasi' ? 'bg-white text-navy' : 'text-white/70 hover:text-white' }}">Formasi Harian</button>
            <button type="button" wire:click="$set('view', 'shift')"
                class="px-3.5 py-1.5 rounded text-xs font-bold uppercase tracking-wide cursor-pointer {{ $view === 'shift' ? 'bg-white text-navy' : 'text-white/70 hover:text-white' }}">Master Shift</button>
        </div>

        @if ($view !== 'shift')
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

    @if ($view !== 'shift')
        {{-- ===================== BAR JADWAL ===================== --}}
        <div class="bg-[var(--rs-surface-alt)] border-b border-[var(--rs-border)] px-4 py-2.5 flex items-center gap-2.5 flex-wrap">
            <span class="text-[11px] font-bold uppercase tracking-wider text-[var(--rs-muted)]">Jadwal</span>

            <x-form.select wire:model.live="scheduleId" liveSync :selected="$scheduleId" :placeholder="false"
                :options="$this->schedules->pluck('schedule_name', 'schedule_id')" />

            <div class="text-[11px] text-[var(--rs-muted)] flex gap-3 flex-wrap">
                <span>Cakupan <b class="text-[var(--rs-ink-2)]">{{ $this->scheduleDepartments->pluck('department_name')->implode(', ') ?: '—' }}</b></span>
                <span>Terisi <b class="text-[var(--rs-ink-2)]">{{ $this->statAssigned }} pegawai</b>@if (! $this->statAssigned) <span> — minggu ini masih kosong</span>@endif</span>
            </div>

            <div class="ml-auto flex gap-1.5 flex-wrap">
                <button type="button" wire:click="openScheduleModal(false)" class="px-2.5 py-1.5 rounded-md text-[11px] font-semibold cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">＋ Jadwal Baru</button>
                <button type="button" wire:click="openScheduleModal(true)" class="px-2.5 py-1.5 rounded-md text-[11px] font-semibold cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">✎ Edit Jadwal</button>
                <button type="button" wire:click="$set('showDeleteSchedule', true)" class="px-2.5 py-1.5 rounded-md text-[11px] font-semibold cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-bad)]">🗑 Hapus</button>
                <a href="{{ route('hrm.shift-roster.print', ['mode' => $view === 'formasi' ? 'formasi' : 'roster', 'schedule' => $scheduleId, 'week' => $weekOffset]) }}" target="_blank"
                    class="px-2.5 py-1.5 rounded-md text-[11px] font-semibold no-underline bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">🖨 Cetak Jadwal Ini</a>
            </div>
        </div>

        @if ($view === 'formasi')
            @include('pages.hrm._roster-formasi')
        @else
        {{-- ===================== PAPAN + POOL =====================
             Kotak statistik dihapus (2026-09-12). Pool sengaja HANYA punya 2
             filter (2026-09-13): Departemen (termasuk opsi "Semua Departemen")
             + cari nama. Unit Kerja/Mesin pindah ke sini (header papan) karena
             itu menentukan ISI PAPAN, bukan isi Pool. --}}
        <div class="px-4 pb-7 pt-4 [display:grid] grid-cols-1 xl:grid-cols-[minmax(0,1fr)_340px] gap-4 items-start">

            {{-- ---------- PAPAN SHIFT ---------- --}}
            <section class="bg-[var(--rs-surface)] border border-[var(--rs-border)] rounded-lg overflow-hidden">
                <div class="flex items-center gap-2.5 px-3.5 py-2.5 border-b border-[var(--rs-border)] flex-wrap">
                    <label class="text-[10px] uppercase font-bold tracking-wider text-[var(--rs-muted)] shrink-0">Unit Kerja / Mesin</label>
                    <x-form.select wire:model.live="workCenterId" liveSync :selected="$workCenterId" :placeholder="false"
                        :options="$this->workCenters->isEmpty()
                            ? ['' => 'Belum ada unit kerja']
                            : $this->workCenters->mapWithKeys(fn ($center) => [
                                $center->work_center_id => $center->work_center_name.($center->machine_id ? '' : ' (non-mesin)').' — '.$center->department->department_name,
                            ])" />
                    <span class="text-[11px] text-[var(--rs-muted)]">1 penempatan = 1 minggu penuh, jam mengikuti definisi shift</span>
                    <span class="ml-auto text-[11px] text-[var(--rs-muted)]">Dobel-klik kolom shift = ubah jam</span>

                    {{-- Satu ikon saja (permintaan user 2026-09-12: bar daftar shift
                         tersembunyi terlalu boros tempat) -- daftar & checkbox-nya
                         pindah ke popup. --}}
                    <button type="button" wire:click="$set('showVisibilityModal', true)"
                        title="Atur shift yang tampil / disembunyikan"
                        class="relative shrink-0 px-2 py-1 rounded-md text-[13px] leading-none cursor-pointer border {{ count($hiddenShifts) ? 'bg-[var(--rs-warn-bg)] border-[var(--rs-warn)] text-[var(--rs-warn)]' : 'bg-[var(--rs-surface)] border-[var(--rs-border-strong)] text-[var(--rs-muted)]' }}">
                        👁
                        @if (count($hiddenShifts))
                            <span class="absolute -top-1.5 -right-1.5 min-w-[16px] h-4 px-1 rounded-full text-[9.5px] font-bold grid place-items-center text-white {{ $this->hiddenWithPlacements() ? 'bg-[var(--rs-bad)]' : 'bg-[var(--rs-warn)]' }}">{{ count($hiddenShifts) }}</span>
                        @endif
                    </button>
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

                        @forelse ($visibleShifts as $shift)
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
                                        {{-- x-on:click.stop supaya tidak ikut memicu dobel-klik "ubah shift" di sel ini --}}
                                        <button type="button" wire:click="toggleShiftVisibility({{ $shift->shift_id }})" x-on:dblclick.stop
                                            title="Sembunyikan shift ini dari papan &amp; cetakan"
                                            class="ml-auto shrink-0 text-[12px] leading-none px-1 py-0.5 rounded cursor-pointer text-[var(--rs-muted)] hover:text-[var(--rs-accent)] hover:bg-[var(--rs-surface)]">👁</button>
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
                                @if (count($hiddenShifts) && $shifts->count())
                                    Semua shift sedang disembunyikan. Klik ikon <b>👁</b> di kanan atas papan untuk menampilkannya lagi.
                                @else
                                    Belum ada shift aktif. Buka menu <b>Master Shift</b> untuk mendefinisikan.
                                @endif
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 items-center px-4 py-2.5 text-[11px] text-[var(--rs-muted)]">
                    @foreach ($visibleShifts as $shift)
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
                    <div class="flex flex-col gap-1">
                        <x-form.select wire:model.live="departmentId" liveSync :selected="$departmentId"
                            placeholder="Semua Departemen"
                            :options="$this->scheduleDepartments->pluck('department_name', 'department_id')" />
                    </div>

                    <x-form.input type="search" wire:model.live.debounce.300ms="poolSearch"
                        placeholder="Cari nama atau kode pegawai…" />

                    <div class="text-[11px] leading-snug rounded-md px-2 py-1.5 bg-[var(--rs-accent-soft)] border border-[var(--rs-border)] text-[var(--rs-ink-2)]">
                        Belum terjadwal di <b>minggu {{ $this->weekNumber() }}</b>: <b>{{ $this->statFree }}</b>
                        {{ $departmentId ? 'dari departemen ini' : 'dari seluruh departemen' }} ·
                        terjadwal <b>{{ $this->statAssigned }}</b> di jadwal ini
                        @unless ($departmentId)
                            <br><span class="text-[var(--rs-warn)]">Semua Departemen — pegawai yang belum bersertifikat unit kerja terpilih tetap bisa ditarik, ditandai ⚠.</span>
                        @endunless
                    </div>
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

                {{-- Daftar flat, tanpa pengelompokan (dihapus 2026-09-13 -- dinilai
                     tidak praktis). Departemen pegawai tetap ditampilkan sebagai
                     info kecil per baris, terutama berguna saat "Semua Departemen"
                     dipilih. --}}
                <div class="max-h-[calc(100vh-300px)] overflow-y-auto">
                    @forelse ($this->poolEmployees as $employee)
                        @php
                            $isSelected = in_array($employee->employee_id, $selection, true);
                            $noSkill = ! $this->hasSkill($employee->employee_id);
                        @endphp
                        <div wire:key="pool-{{ $employee->employee_id }}" draggable="true"
                            wire:click="toggleSelect({{ $employee->employee_id }})"
                            {{-- Menyeret item yang sedang tercentang ikut membawa SELURUH seleksi
                                 (employeeId null = pakai $selection), sama seperti mockup. --}}
                            x-on:dragstart="$event.dataTransfer.setData('text/plain', JSON.stringify({employeeId: {{ $isSelected ? 'null' : $employee->employee_id }}, fromBoard: false}))"
                            class="flex items-center gap-2.5 px-3 py-1.5 border-b border-[var(--rs-border)] cursor-grab select-none {{ $isSelected ? 'bg-[var(--rs-accent-soft)]' : 'hover:bg-[var(--rs-surface-alt)]' }}">
                            <input type="checkbox" @checked($isSelected) class="pointer-events-none shrink-0 accent-[var(--rs-accent)]">
                            <span class="w-6 h-6 rounded shrink-0 grid place-items-center text-[11px] font-bold text-white bg-gradient-to-br {{ \App\Support\Grid\Avatar::gradient($employee->employee_id) }}">{{ $this->initials($employee->full_name) }}</span>
                            <span class="min-w-0 flex-1">
                                <b class="block text-[12.5px] font-semibold truncate text-[var(--rs-ink)]">{{ $employee->full_name }}{{ $noSkill ? ' ⚠' : '' }}</b>
                                <span class="text-[10px] font-mono text-[var(--rs-muted)] truncate block">{{ $employee->employee_code }} · {{ $employee->position?->department?->department_name ?? 'tanpa departemen' }}</span>
                            </span>
                            <span class="text-[10.5px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded shrink-0 bg-[var(--rs-sunken)] text-[var(--rs-ink-2)]">{{ $employee->position?->position_name ?? '—' }}</span>
                        </div>
                    @empty
                        <div class="px-4 py-7 text-center text-xs leading-relaxed text-[var(--rs-muted)]">
                            Tidak ada pegawai yang cocok.<br>
                            @if ($departmentId)
                                Semua pegawai departemen ini sudah terjadwal minggu ini — pilih <b>Semua Departemen</b> di atas untuk meminjam dari departemen lain.
                            @else
                                Seluruh pegawai aktif sudah terjadwal di minggu ini.
                            @endif
                        </div>
                    @endforelse
                </div>
            </aside>
        </div>
        @endif
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
                        @if (in_array($shift->shift_id, $hiddenShifts, true))
                            <span class="text-[9.5px] font-bold uppercase px-1.5 py-0.5 rounded bg-[var(--rs-warn-bg)] text-[var(--rs-warn)]" title="Tidak tampil di papan roster & cetakan">disembunyikan</span>
                        @endif
                        <span class="text-[11.5px] text-[var(--rs-muted)]">Kuota/unit <b class="text-[var(--rs-ink-2)]">{{ $shift->capacity() }}</b></span>
                        <span class="text-[13px] font-bold px-2.5 py-1 rounded bg-[var(--rs-ok-bg)] text-[var(--rs-ok)] whitespace-nowrap">{{ $shift->activeDayCount() }} hari · {{ \App\Models\Master\EmpShift::formatMinutes($shift->weekMinutes()) }}</span>
                        @if ($used)
                            <span class="text-[11px] text-[var(--rs-muted)]">dipakai {{ $used }} baris roster</span>
                        @endif

                        <span class="ml-auto flex gap-1.5 flex-wrap">
                            <button type="button" wire:click="openShiftModal({{ $shift->shift_id }})" class="px-2.5 py-1 rounded text-xs cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">✎ Ubah</button>
                            <button type="button" wire:click="toggleShiftVisibility({{ $shift->shift_id }})" class="px-2.5 py-1 rounded text-xs cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">{{ in_array($shift->shift_id, $hiddenShifts, true) ? '👁 Tampilkan' : '👁 Sembunyikan' }}</button>
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

    {{-- ===================== POPUP: TAMPIL / SEMBUNYI SHIFT ===================== --}}
    @if ($showVisibilityModal)
        <div class="fixed inset-0 bg-black/50 z-[70] p-5 overflow-auto" wire:click.self="$set('showVisibilityModal', false)">
            <div class="max-w-[520px] mx-auto rounded-xl border border-[var(--rs-border)] bg-[var(--rs-surface)] text-[var(--rs-ink)]">
                <div class="flex items-center gap-2.5 px-4 py-3 border-b border-[var(--rs-border)]">
                    <h3 class="m-0 text-base font-bold uppercase tracking-wide">Tampilkan / Sembunyikan Shift</h3>
                    <button type="button" wire:click="$set('showVisibilityModal', false)" class="ml-auto text-xl leading-none cursor-pointer text-[var(--rs-muted)]">×</button>
                </div>

                <div class="px-4 py-3">
                    <p class="mt-0 mb-3 text-[11.5px] text-[var(--rs-muted)]">
                        Centang = tampil di papan <b>dan</b> di hasil cetak. Setelan ini milik akun Anda sendiri.
                    </p>

                    <div class="flex flex-col gap-1 max-h-[340px] overflow-y-auto">
                        @foreach ($shifts as $shift)
                            @php
                                $isHidden = in_array($shift->shift_id, $hiddenShifts, true);
                                $placed = $this->shiftCount($shift->shift_id);
                            @endphp
                            <label wire:key="vis-{{ $shift->shift_id }}"
                                class="flex items-center gap-2.5 px-2.5 py-2 rounded-md border cursor-pointer {{ $isHidden ? 'border-[var(--rs-warn)] bg-[var(--rs-warn-bg)]' : 'border-[var(--rs-border)] bg-[var(--rs-surface-alt)]' }}">
                                <input type="checkbox" wire:click="toggleShiftVisibility({{ $shift->shift_id }})" @checked(! $isHidden)
                                    class="cursor-pointer accent-[var(--rs-accent)]">
                                <i class="w-2.5 h-2.5 rounded-sm shrink-0 inline-block" style="background: {{ $shift->color() }}"></i>
                                <span class="min-w-0 flex-1">
                                    <b class="block text-[12.5px] font-semibold truncate">{{ $shift->shift_name }}</b>
                                    <span class="text-[10px] font-mono text-[var(--rs-muted)]">{{ $shift->shift_code }} · {{ implode(', ', $shift->summaryLines()) }}</span>
                                </span>
                                @if ($isHidden && $placed)
                                    <span class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded text-white bg-[var(--rs-bad)]" title="Penempatan minggu ini di unit kerja yang sedang dibuka">⚠ {{ $placed }} orang</span>
                                @elseif ($placed)
                                    <span class="shrink-0 text-[10px] font-mono text-[var(--rs-muted)]">{{ $placed }} orang</span>
                                @endif
                                @if ($isHidden)
                                    <span class="shrink-0 text-[9.5px] font-bold uppercase text-[var(--rs-warn)]">disembunyikan</span>
                                @endif
                            </label>
                        @endforeach
                    </div>

                    @if ($this->hiddenWithPlacements())
                        <div class="mt-3 text-[11px] leading-snug rounded-md px-2.5 py-2 bg-[var(--rs-bad-bg)] border border-[var(--rs-bad)] text-[var(--rs-bad)]">
                            ⚠ Ada <b>{{ $this->hiddenWithPlacements() }} penempatan</b> minggu ini di shift yang disembunyikan — datanya tetap tersimpan, tapi tidak muncul di papan maupun cetakan.
                        </div>
                    @endif
                </div>

                <div class="flex gap-2 justify-end px-4 py-3 border-t border-[var(--rs-border)] bg-[var(--rs-surface-alt)] rounded-b-xl">
                    <button type="button" wire:click="showAllShifts" class="mr-auto px-3.5 py-1.5 rounded-md text-xs font-semibold cursor-pointer bg-[var(--rs-surface)] border border-[var(--rs-border-strong)] text-[var(--rs-ink)]">Tampilkan semua</button>
                    <button type="button" wire:click="$set('showVisibilityModal', false)" class="px-3.5 py-1.5 rounded-md text-xs font-semibold cursor-pointer bg-accent text-white">Tutup</button>
                </div>
            </div>
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
                                <x-form.checkbox value="{{ $department->department_id }}" wire:model="sched_depts"
                                    class="accent-[var(--rs-accent)]"
                                    labelClass="px-2 py-1.5 rounded-md border border-[var(--rs-border)] bg-[var(--rs-surface-alt)] cursor-pointer">
                                    {{ $department->department_name }}
                                </x-form.checkbox>
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
                        <div class="w-[130px]">
                            <x-form.color-picker label="Warna" name="shiftForm.color" wire:model="shiftForm.color" :value="$shiftForm['color'] ?? '#2563eb'" />
                        </div>
                        <div class="w-[120px]">
                            <x-form.input label="Kuota/Unit" name="shiftForm.cap" type="number" wire:model="shiftForm.cap" min="1" max="30" />
                        </div>
                        <x-form.checkbox wire:model="shiftForm.active" class="accent-[var(--rs-accent)] cursor-pointer"
                            labelClass="pb-1.5 cursor-pointer text-[var(--rs-ink-2)]">Aktif</x-form.checkbox>
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
                                    {{-- Sengaja TETAP raw <input> (audit componentization 2026-09-13): checkbox
                                         ini menumpang di dalam <label> yang SUDAH membungkus teks nama hari --
                                         x-form.checkbox selalu bikin <label> sendiri, nesting label-in-label
                                         invalid HTML & bisa toggle ganda. --}}
                                    <input type="checkbox" wire:model.live="shiftForm.days.{{ $i }}.on" class="ml-auto accent-[var(--rs-accent)] cursor-pointer">
                                </label>

                                @if ($shiftForm['days'][$i]['on'] ?? false)
                                    <x-form.input type="time" name="shiftForm.days.{{ $i }}.in" wire:model="shiftForm.days.{{ $i }}.in" />
                                    <x-form.input type="time" name="shiftForm.days.{{ $i }}.out" wire:model="shiftForm.days.{{ $i }}.out" />
                                    <div class="flex items-center gap-1 text-[10.5px] text-[var(--rs-muted)]">
                                        Ist.
                                        <div class="w-[52px]">
                                            <x-form.input type="number" name="shiftForm.days.{{ $i }}.brk" wire:model="shiftForm.days.{{ $i }}.brk" min="0" max="240" step="5" />
                                        </div>
                                        mnt
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

                    {{-- 3 opsi -> x-form.toggle-group (workaround resmi bug select morph
                         Livewire, [[feedback_livewire_select_morph_bug]]), bukan x-form.select
                         liveSync -- itu utk select BESAR (dinamis, banyak baris). --}}
                    <x-form.toggle-group label="Pola"
                        :hint="match ($rot_mode) {
                            'cycle' => 'Semua shift di minggu sumber maju satu urutan.',
                            'copy' => 'Salin apa adanya, tanpa rotasi.',
                            default => 'Tukar 2 shift tertentu (A ⇄ B).',
                        }">
                        <x-form.toggle-option wire:click="$set('rot_mode', 'swap')" :active="$rot_mode === 'swap'">Tukar</x-form.toggle-option>
                        <x-form.toggle-option wire:click="$set('rot_mode', 'cycle')" :active="$rot_mode === 'cycle'">Rotasi Berputar</x-form.toggle-option>
                        <x-form.toggle-option wire:click="$set('rot_mode', 'copy')" :active="$rot_mode === 'copy'">Salin</x-form.toggle-option>
                    </x-form.toggle-group>

                    @if ($rot_mode === 'swap')
                        <div class="flex gap-3 flex-wrap items-end">
                            <div class="flex-1 min-w-[170px]">
                                <x-form.select label="Shift A" wire:model.live="rot_a" liveSync :selected="$rot_a" :placeholder="false"
                                    :options="$shifts->pluck('shift_name', 'shift_id')" />
                            </div>
                            <span class="text-xl text-[var(--rs-accent)] pb-1.5">⇄</span>
                            <div class="flex-1 min-w-[170px]">
                                <x-form.select label="Shift B" wire:model.live="rot_b" liveSync :selected="$rot_b" :placeholder="false"
                                    :options="$shifts->pluck('shift_name', 'shift_id')" />
                            </div>
                        </div>
                    @endif

                    <x-form.toggle-group label="Cakupan">
                        <x-form.toggle-option wire:click="$set('rot_scope', 'all')" :active="$rot_scope === 'all'">Seluruh unit kerja di jadwal ini</x-form.toggle-option>
                        <x-form.toggle-option wire:click="$set('rot_scope', 'wc')" :active="$rot_scope === 'wc'">Hanya {{ $this->currentWorkCenter?->work_center_name }}</x-form.toggle-option>
                    </x-form.toggle-group>

                    <x-form.checkbox wire:model.live="rot_over" class="accent-[var(--rs-accent)] cursor-pointer"
                        labelClass="cursor-pointer text-[var(--rs-ink-2)]">Timpa data yang sudah ada di minggu tujuan</x-form.checkbox>

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
