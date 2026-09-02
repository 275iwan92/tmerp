<?php

use App\Models\Master\CustCustomer;
use App\Models\Master\EmpShiftRoster;
use App\Models\Master\OrgDivision;
use App\Models\Master\ProdMachine;
use App\Models\Prd\PrdCrewAssignment;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use App\Services\Scheduling\InterruptionOptionGenerator;
use App\Services\Scheduling\PdrEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// mockup_scheduling_board.html + production_scheduling_tier4.md §3. Iterasi
// pertama (2026-08-28): Board + data nyata dari tm2prd, drag/resize/reassign
// PERSIST ke DB sungguhan. Gantt-nya sendiri diekstrak jadi komponen reusable
// resources/views/components/gantt/{board,row,task-block}.blade.php (2026-08-28,
// permintaan user -- akan ada Gantt serupa lagi ke depan, mis. Schedule
// Maintenance). Halaman ini HANYA bertanggung jawab meresolve data domain
// (WO/mesin/proses) ke bentuk row/block generik -- lihat operationVm()/
// idleGapsVm() di bawah -- komponen sendiri tidak tahu apa itu WO/proses.
//
// Interruption Dialog (§4 Tier4, "Sisip Hot Job") disambung 2026-08-28 (sesi
// lanjutan) ke App\Services\Scheduling\PdrEngine/InterruptionOptionGenerator
// (app/Services/Scheduling) -- scope disepakati user: pilih WO EXISTING yang
// belum terjadwal (bukan form bikin WO baru on-the-spot ala mockup, supaya
// tak perlu ALTER enum prd_wo.source_type yang cuma punya CO_CONFIRMED/
// MTS_FORECAST). Opsi "Reroute Subkontrak" di dialog sekarang BISA diterapkan
// (PO Subkontrak nyata via App\Services\Pur\SubcontractPoService, dibangun
// sesi berikutnya bersama WO Detail -- lihat wo-entry.blade.php).
//
// Production Output Recording (F15, TIDAK ADA spec Tier2/3/4 -- didesain
// dari nol) disambung ke board 2026-08-28: operationVm() pakai
// outputProgressPercent() (qty baik aktual / target) utk progress blok begitu
// ada catatan output, fallback ke progressPercent() berbasis waktu selama
// belum ada catatan -- PPIC lihat kemajuan produksi SUNGGUHAN langsung dari
// board, tanpa buka WO Detail. Catat output-nya sendiri ada di WO Detail
// (tombol "📊 Catat Output" per task RUNNING/DONE), bukan di board ini.
//
// SENGAJA masih belum ada: backward-scheduling otomatis/PDR auto-assign saat
// board dibuka (baru jalan lewat Interruption Dialog di atas), Simulation
// Layer penuh (§5A.1 Tier1 -- toggle Mode di bawah ini murni tampilan, belum
// ada sandbox terpisah, tombol "Submit ke Master Schedule" masih placeholder
// karena itu butuh Worklist WO/pull-CO yang belum dibangun) -- dicatat di
// project memory.
//
// REVISI 2026-08-28 (permintaan user, "engine MPS harus jalan di WO/Kanban/
// Gantt"): moveOperation()/resizeOperation()/reassignOperation() sekarang
// WAJIB lewat PdrEngine::validateManualReschedule() sebelum commit (konflik
// crew, durasi minimum, lock QC_HOLD) -- sebelumnya method itu ada tapi cuma
// dipanggil dari Hot Job dialog, drag/resize biasa lolos tanpa validasi sama
// sekali. Ditemukan bareng bug WoGenerationService yang menghitung backward-
// schedule tapi tak pernah menyimpannya (lihat WoGenerationService.php).
new #[Title('Scheduling Board — TELASIH MAKMUR ERP')] class extends Component
{
    const TIMELINE_START = 8;

    const TIMELINE_END = 20;

    const PX_PER_HOUR = 70;

    // #[Url] -- WO Detail (wo-entry.blade.php) deep-link "Lihat di Scheduling
    // Board" lewat ?date=YYYY-MM-DD, bukan cuma bisa dibuka polos.
    #[Url]
    public string $date = '2026-08-28';

    public string $filterMachine = 'all';

    public string $filterProcess = 'all';

    public string $mode = 'simulasi'; // simulasi|live -- lihat catatan class di atas

    // ---- Interruption Dialog state (Tier4 §4) ----
    public bool $showHotJobDialog = false;

    public ?int $hotWoId = null;

    public ?int $hotOperationId = null;

    public string $hotSource = 'DIREKSI'; // DIREKSI|CUSTOMER|INTERNAL_REWORK

    public ?int $hotCustomerId = null;

    public string $hotCustomerName = '';

    public string $hotCustQuery = '';

    public array $hotOptions = [];

    public bool $hotOptionsLoaded = false;

    public ?int $hotSelectedOption = null;

    private function offsetDivisionId(): ?int
    {
        return OrgDivision::where('division_code', 'OFFSET')->value('division_id');
    }

    #[Computed]
    public function machines()
    {
        return ProdMachine::query()
            ->where('division_id', $this->offsetDivisionId())
            ->where('activate', true)
            ->orderBy('machine_code')
            ->get();
    }

    #[Computed]
    public function processOptions()
    {
        return PrdWoOperation::query()->select('process_name')->distinct()->orderBy('process_name')->pluck('process_name');
    }

    // Kandidat "Sisip Hot Job" -- WO yang sudah punya task mesin tapi belum
    // terjadwal sama sekali (task_status PENDING, target_start NULL) dan
    // belum ditandai hot job. Task manual belum didukung RateBandResolver
    // (Tier2 §4.1 catatan), jadi difilter di sini juga -- bukan cuma dicek
    // belakangan supaya PPIC tak pilih WO yang bakal gagal.
    #[Computed]
    public function unscheduledHotJobCandidates()
    {
        return PrdWo::query()
            ->where('is_hot_job', false)
            ->whereIn('wo_status', ['DRAFT', 'SIMULATED', 'SCHEDULED'])
            ->whereHas('operations', fn ($q) => $q->where('task_status', 'PENDING')
                ->whereNull('target_start')
                ->where('resource_type', 'MACHINE'))
            ->orderBy('due_date')
            ->limit(20)
            ->get();
    }

    // Live-search customer (Sumber=CUSTOMER saja) -- pola sama inquiry/form.blade.php.
    #[Computed]
    public function hotCustomerResults()
    {
        if ($this->hotCustomerId || mb_strlen($this->hotCustQuery) < 2) {
            return collect();
        }

        return CustCustomer::query()
            ->where(function ($q) {
                $q->where('customer_name', 'like', "%{$this->hotCustQuery}%")
                    ->orWhere('customer_code', 'like', "%{$this->hotCustQuery}%");
            })
            ->orderBy('customer_name')
            ->limit(8)
            ->get();
    }

    // Tier4 §6 (wajib): dropdown operator manual HANYA boleh berisi orang yang
    // ter-roster (emp_shift_roster) di tanggal papan ini -- bukan siapa saja
    // yang kebetulan sudah pegang task manual hari itu, apalagi seluruh
    // karyawan. Query ulang tiap kali board dibuka/tanggal berganti (bukan
    // dihitung sekali lalu dianggap tetap berlaku).
    public function resourceOptions(): array
    {
        $options = $this->machines->map(fn ($m) => ['value' => 'm'.$m->machine_id, 'label' => $m->machine_name])->all();

        $rostered = EmpShiftRoster::with('employee')
            ->where('roster_date', $this->date)
            ->whereNull('machine_id') // roster umum -- bukan operator mesin tertentu
            ->get();

        foreach ($rostered->unique('employee_id') as $roster) {
            if ($roster->employee) {
                $options[] = ['value' => 'e'.$roster->employee_id, 'label' => $roster->employee->full_name.' (Manual)'];
            }
        }

        return $options;
    }

    private function operationsForDay()
    {
        return PrdWoOperation::with(['wo', 'crew.employee', 'outputs.defects.scrapType'])
            ->whereDate('target_start', $this->date)
            ->orderBy('target_start')
            ->get();
    }

    private function hourOf(?Carbon $dt): float
    {
        return $dt ? $dt->hour + $dt->minute / 60 : (float) self::TIMELINE_START;
    }

    private function hourLabel(float $dec): string
    {
        $h = (int) floor($dec);
        $m = (int) round(($dec - $h) * 60);
        if ($m === 60) {
            $h++;
            $m = 0;
        }

        return sprintf('%02d:%02d', $h, $m);
    }

    private function idleGaps($ops): array
    {
        $sorted = $ops->sortBy(fn ($o) => $this->hourOf($o->target_start))->values();
        $gaps = [];
        $cursor = (float) self::TIMELINE_START;

        foreach ($sorted as $op) {
            $start = $this->hourOf($op->target_start);
            $finish = $this->hourOf($op->target_finish);
            if ($start > $cursor) {
                $gaps[] = ['start' => $cursor, 'duration' => $start - $cursor];
            }
            $cursor = max($cursor, $finish);
        }

        if ($cursor < self::TIMELINE_END) {
            $gaps[] = ['start' => $cursor, 'duration' => self::TIMELINE_END - $cursor];
        }

        return $gaps;
    }

    // Gap mentah (jam) -> VM generik siap-render (px + flag "kosong >=2 jam"),
    // kontrak yang dipahami komponen gantt.row.
    private function idleGapsVm($ops): array
    {
        return collect($this->idleGaps($ops))
            ->filter(fn ($g) => $g['duration'] > 0)
            ->map(fn ($g) => [
                'left' => ($g['start'] - self::TIMELINE_START) * self::PX_PER_HOUR,
                'width' => $g['duration'] * self::PX_PER_HOUR,
                'flag' => $g['duration'] >= 2,
            ])
            ->values()
            ->all();
    }

    private function crewLabel(?PrdWoOperation $op): string
    {
        if (! $op) {
            return '—';
        }

        if ($op->task_status === 'BREAKDOWN' && $op->crew->isEmpty()) {
            return '— menunggu reroute';
        }

        $names = $op->crew->map(function ($c) {
            $name = $c->employee?->full_name ?? '?';

            return $c->crew_role === 'OPERATOR_UTAMA' ? "{$name} (utama)" : $name;
        });

        return $names->isEmpty() ? '— menunggu penugasan' : $names->implode(' + ');
    }

    // Peta warna proses & label status = pengetahuan domain Production
    // Scheduling, sengaja TIDAK ditaruh di komponen Gantt generik.
    private function processColor(string $processName, string $taskStatus): array
    {
        if ($taskStatus === 'BREAKDOWN') {
            return ['bg' => '#fca5a5', 'text' => '#7f1d1d'];
        }

        return match ($processName) {
            'Cetak' => ['bg' => '#bfdbfe', 'text' => '#1e3a8a'],
            'Laminasi' => ['bg' => '#ddd6fe', 'text' => '#5b21b6'],
            'Plong' => ['bg' => '#fed7aa', 'text' => '#9a3412'],
            'Packing' => ['bg' => '#99f6e4', 'text' => '#115e59'],
            default => ['bg' => '#e5e7eb', 'text' => '#374151'],
        };
    }

    private function statusLabel(string $taskStatus): string
    {
        return match ($taskStatus) {
            'PENDING' => 'Menunggu', 'ASSIGNED' => 'Ditugaskan', 'RUNNING' => 'Berjalan',
            'DONE' => 'Selesai', 'BREAKDOWN' => 'Breakdown', 'SUBCONTRACT' => 'Subkontrak',
            'QC_HOLD' => 'QC Hold', 'QC_PASSED' => 'QC Lulus', 'QC_REJECTED' => 'QC Ditolak',
            default => $taskStatus,
        };
    }

    // Operation (domain) -> block VM generik, kontrak yang dipahami komponen gantt.task-block.
    private function operationVm(PrdWoOperation $op): array
    {
        $start = $this->hourOf($op->target_start);
        $finish = $this->hourOf($op->target_finish);
        $duration = max(0.25, $finish - $start);
        $color = $this->processColor($op->process_name, $op->task_status);

        $icon = match (true) {
            (bool) $op->is_hot_job => '🔥 ',
            $op->task_status === 'BREAKDOWN' => '⚠ ',
            $op->task_status === 'QC_HOLD' => '🔍 ',
            default => '',
        };

        // F15 Production Output Recording -- begitu ada catatan output nyata,
        // progress blok pakai itu (qty baik/target) alih-alih proporsi waktu
        // berlalu, supaya PPIC lihat kemajuan produksi SUNGGUHAN dari board
        // langsung, bukan cuma "sudah berapa lama berjalan". Belum ada
        // catatan -> tetap fallback ke progressPercent() waktu (perilaku lama).
        $hasOutput = $op->outputs->isNotEmpty();
        $progress = $hasOutput ? $op->outputProgressPercent() : $op->progressPercent(now());
        $timeLabel = $this->hourLabel($start).'–'.$this->hourLabel($start + $duration);
        if ($hasOutput) {
            $timeLabel .= ' · '.number_format($op->totalQtyGood(), 0, ',', '.').'/'.number_format((float) $op->qty, 0, ',', '.');
        }

        return [
            'id' => $op->operation_id,
            'resource' => $op->resource_type === 'MACHINE' ? 'm'.$op->machine_id : 'e'.($op->crew->firstWhere('crew_role', 'OPERATOR_UTAMA')?->employee_id ?? 0),
            'title' => $icon.($op->wo->wo_no ?? '?').' · '.$op->process_name,
            'subtitle' => $op->process_name,
            'timeLabel' => $timeLabel,
            'statusLabel' => $this->statusLabel($op->task_status),
            'left' => ($start - self::TIMELINE_START) * self::PX_PER_HOUR,
            'width' => $duration * self::PX_PER_HOUR,
            'progress' => $progress,
            'bg' => $color['bg'],
            'text' => $color['text'],
            'urgent' => (bool) $op->is_hot_job,
            'dashed' => $op->task_status === 'QC_HOLD',
            'risk' => $op->critical_ratio !== null && (float) $op->critical_ratio < 1,
            'locked' => $op->task_status === 'QC_HOLD',
        ];
    }

    // Baris board (mesin + manual job) -- Tier4 §3.1. $filterMachine/$filterProcess
    // override eksplisit supaya bisa dipanggil "tanpa filter" utk hitung stat global.
    private function buildRows(string $filterMachine, string $filterProcess): array
    {
        $ops = $this->operationsForDay();
        $machineOps = $ops->where('resource_type', 'MACHINE');
        $manualOps = $ops->where('resource_type', 'MANUAL');

        $machineRows = [];
        foreach ($this->machines as $m) {
            if ($filterMachine !== 'all' && $filterMachine !== (string) $m->machine_id) {
                continue;
            }

            $rowOps = $machineOps->where('machine_id', $m->machine_id)->values();
            $activeOp = $rowOps->first(fn ($o) => $o->task_status === 'RUNNING') ?? $rowOps->first();
            $visibleOps = $filterProcess === 'all' ? $rowOps : $rowOps->where('process_name', $filterProcess)->values();

            $machineRows[] = [
                'resource' => 'm'.$m->machine_id,
                'label' => $m->machine_name,
                'sublabel' => '👤 '.$this->crewLabel($activeOp),
                'blocks' => $visibleOps->map(fn ($o) => $this->operationVm($o))->values()->all(),
                'idle' => $this->idleGapsVm($rowOps),
            ];
        }

        $manualRows = [];
        if ($filterMachine === 'all') {
            $grouped = [];
            foreach ($manualOps as $op) {
                $utama = $op->crew->firstWhere('crew_role', 'OPERATOR_UTAMA');
                $empId = $utama?->employee_id ?? 0;
                $grouped[$empId]['label'] ??= $utama?->employee?->full_name ?? 'Belum Ditugaskan';
                $grouped[$empId]['ops'][] = $op;
            }

            foreach ($grouped as $empId => $data) {
                $rowOps = collect($data['ops']);
                $visibleOps = $filterProcess === 'all' ? $rowOps : $rowOps->where('process_name', $filterProcess)->values();

                $manualRows[] = [
                    'resource' => 'e'.$empId,
                    'label' => $data['label'],
                    'sublabel' => 'Manual',
                    'blocks' => $visibleOps->map(fn ($o) => $this->operationVm($o))->values()->all(),
                    'idle' => [],
                ];
            }
        }

        return compact('machineRows', 'manualRows');
    }

    private function stats(array $unfilteredRows, $opsForDay): array
    {
        $totalActive = $opsForDay->whereNotIn('task_status', ['DONE', 'QC_PASSED'])->count();
        $atRisk = $opsForDay->filter(fn ($o) => $o->critical_ratio !== null && (float) $o->critical_ratio < 1)->count();
        $unscheduled = PrdWoOperation::whereNull('target_start')->whereNotIn('task_status', ['DONE', 'QC_PASSED'])->count();

        $idleMachineCount = 0;
        foreach ($unfilteredRows['machineRows'] as $row) {
            foreach ($row['idle'] as $gap) {
                if (! empty($gap['flag'])) {
                    $idleMachineCount++;
                    break;
                }
            }
        }

        return compact('totalActive', 'atRisk', 'unscheduled', 'idleMachineCount');
    }

    // Satu titik akses tunggal dari Blade -- hindari operationsForDay()/buildRows()
    // terpanggil berkali-kali per render.
    public function board(): array
    {
        $opsForDay = $this->operationsForDay();
        $displayRows = $this->buildRows($this->filterMachine, $this->filterProcess);
        $unfilteredRows = ($this->filterMachine === 'all' && $this->filterProcess === 'all')
            ? $displayRows
            : $this->buildRows('all', 'all');

        return [
            'machineRows' => $displayRows['machineRows'],
            'manualRows' => $displayRows['manualRows'],
            'stats' => $this->stats($unfilteredRows, $opsForDay),
        ];
    }

    public function shiftDate(int $deltaDays): void
    {
        $this->date = Carbon::parse($this->date)->addDays($deltaDays)->toDateString();
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode;
    }

    // Validasi PdrEngine (Tier1 §5A.9) WAJIB server-side sebelum commit drag/
    // resize/reassign manual -- lihat CrewAvailabilityChecker docblock. Sampai
    // 2026-08-28 method ini ada tapi tak pernah dipanggil dari board (cuma
    // dipanggil dari Hot Job dialog); sekarang di-wire ke 3 mutator di bawah.
    public function moveOperation(int $operationId, float $newStartHour): void
    {
        $op = PrdWoOperation::with(['wo', 'crew'])->find($operationId);
        if (! $op || ! $op->target_start) {
            $this->dispatch('board-toast', message: 'Task belum terjadwal, tidak bisa digeser.');

            return;
        }

        $durationMinutes = $op->target_start->diffInMinutes($op->target_finish);
        $newStartHour = max(self::TIMELINE_START, min($newStartHour, self::TIMELINE_END - 0.25));
        $newStart = Carbon::parse($this->date)->startOfDay()->addMinutes((int) round($newStartHour * 60));
        $newFinish = $newStart->copy()->addMinutes($durationMinutes);

        $errors = app(PdrEngine::class)->validateManualReschedule($op, $newStart, $newFinish, $op->crew->pluck('employee_id')->all());
        if (! empty($errors)) {
            $this->dispatch('board-toast', message: implode(' ', $errors));

            return;
        }

        $op->update([
            'target_start' => $newStart,
            'target_finish' => $newFinish,
            'updated_by' => Auth::user()->login,
        ]);

        $this->dispatch('board-toast', message: "{$op->wo->wo_no} dipindah ke ".$this->hourLabel($newStartHour));
    }

    public function resizeOperation(int $operationId, float $newDurationHour): void
    {
        $op = PrdWoOperation::with(['wo', 'crew'])->find($operationId);
        if (! $op || ! $op->target_start) {
            $this->dispatch('board-toast', message: 'Task belum terjadwal, tidak bisa diubah durasinya.');

            return;
        }

        $newDurationHour = max(0.25, $newDurationHour);
        $newFinish = $op->target_start->copy()->addMinutes((int) round($newDurationHour * 60));

        $errors = app(PdrEngine::class)->validateManualReschedule($op, $op->target_start, $newFinish, $op->crew->pluck('employee_id')->all());
        if (! empty($errors)) {
            $this->dispatch('board-toast', message: implode(' ', $errors));

            return;
        }

        $op->update([
            'target_finish' => $newFinish,
            'updated_by' => Auth::user()->login,
        ]);

        $this->dispatch('board-toast', message: "{$op->wo->wo_no} durasi jadi ".number_format($newDurationHour, 1).' jam');
    }

    public function reassignOperation(int $operationId, string $resourceKey, string $processName): void
    {
        $op = PrdWoOperation::with(['wo', 'crew'])->find($operationId);
        if (! $op) {
            return;
        }

        if ($op->task_status === 'QC_HOLD') {
            $this->dispatch('board-toast', message: 'Task QC Hold terkunci, tidak bisa dipindah mesin/orang.');

            return;
        }

        if ($op->target_start && $op->target_finish) {
            $proposedCrewIds = str_starts_with($resourceKey, 'e')
                ? [(int) substr($resourceKey, 1)]
                : $op->crew->pluck('employee_id')->all();

            $errors = app(PdrEngine::class)->validateManualReschedule($op, $op->target_start, $op->target_finish, $proposedCrewIds);
            if (! empty($errors)) {
                $this->dispatch('board-toast', message: implode(' ', $errors));

                return;
            }
        }

        $op->process_name = $processName;

        if (str_starts_with($resourceKey, 'm')) {
            $op->resource_type = 'MACHINE';
            $op->machine_id = (int) substr($resourceKey, 1);
        } elseif (str_starts_with($resourceKey, 'e')) {
            $op->resource_type = 'MANUAL';
            $op->machine_id = null;
        }

        $op->updated_by = Auth::user()->login;
        $op->save();

        if (str_starts_with($resourceKey, 'e')) {
            $employeeId = (int) substr($resourceKey, 1);
            PrdCrewAssignment::updateOrCreate(
                ['operation_id' => $op->operation_id, 'crew_role' => 'OPERATOR_UTAMA'],
                ['employee_id' => $employeeId, 'created_by' => Auth::user()->login]
            );
        }

        $this->dispatch('board-toast', message: "Task {$op->wo->wo_no} diperbarui");
    }

    // Palet pastel baku griduistandard -- aman=hijau, berisiko=kuning, subkontrak=biru (Tier4 §4).
    private function optionAccentClasses(array $option): string
    {
        if ($option['requires_subcontract_po']) {
            return 'border-[#bfdbfe] bg-[#eff6ff]';
        }

        return $option['is_safe'] ? 'border-[#bbf7d0] bg-[#f0fdf4]' : 'border-[#fde68a] bg-[#fffbeb]';
    }

    public function notYetAvailable(string $feature): void
    {
        $this->dispatch('board-toast', message: "{$feature} menyusul sesi berikutnya -- belum aktif di iterasi ini.");
    }

    // ---- Interruption Dialog (Tier4 §4) ----

    public function openHotJobDialog(): void
    {
        $this->reset([
            'hotWoId', 'hotOperationId', 'hotCustomerId', 'hotCustomerName',
            'hotCustQuery', 'hotOptions', 'hotOptionsLoaded', 'hotSelectedOption',
        ]);
        $this->hotSource = 'DIREKSI';
        $this->showHotJobDialog = true;
    }

    public function closeHotJobDialog(): void
    {
        $this->showHotJobDialog = false;
    }

    // Livewire lifecycle hook -- otomatis terpanggil saat wire:model.live="hotWoId" berubah.
    public function updatedHotWoId($value): void
    {
        $this->hotOptions = [];
        $this->hotOptionsLoaded = false;
        $this->hotSelectedOption = null;

        if (! $value) {
            $this->hotOperationId = null;

            return;
        }

        $wo = PrdWo::with('operations')->find((int) $value);
        $op = $wo?->operations->first(fn ($o) => $o->task_status === 'PENDING' && ! $o->target_start && $o->resource_type === 'MACHINE');

        if (! $op) {
            $this->dispatch('board-toast', message: 'WO ini tidak lagi punya task mesin yang belum terjadwal.');
            $this->hotWoId = null;
            $this->hotOperationId = null;

            return;
        }

        $this->hotOperationId = $op->operation_id;
    }

    public function selectHotCustomer(int $customerId): void
    {
        $c = CustCustomer::find($customerId);
        if ($c) {
            $this->hotCustomerId = $c->id_customer;
            $this->hotCustomerName = $c->customer_name;
            $this->hotCustQuery = '';
        }
    }

    public function clearHotCustomer(): void
    {
        $this->hotCustomerId = null;
        $this->hotCustomerName = '';
    }

    // Backward-schedule dulu (belum di-persist) supaya task hot job ini punya
    // target_start/target_finish sendiri -- dipakai InterruptionOptionGenerator
    // buat estimasi berapa jam task lain harus digeser (Tier1 §5A.2), baru
    // bangun antrean & opsi penyisipan yang sebenarnya.
    public function generateHotJobOptions(): void
    {
        $wo = PrdWo::with('operations')->find($this->hotWoId);

        if (! $wo) {
            $this->dispatch('board-toast', message: 'Pilih WO dulu.');

            return;
        }

        try {
            // schedule() query ulang operations() sendiri (instance BARU, bukan
            // $this->hotOperationId yang di-fetch terpisah) -- wajib pakai
            // instance hasil ini, bukan fetch ulang, supaya target_start/finish
            // hasil hitungnya benar-benar terbawa ke generateOptions() di bawah.
            $tasks = app(PdrEngine::class)->backwardSchedule($wo);
        } catch (\RuntimeException $e) {
            $this->dispatch('board-toast', message: 'Gagal hitung jadwal: '.$e->getMessage());

            return;
        }

        $op = $tasks->firstWhere('operation_id', $this->hotOperationId);

        if (! $op) {
            $this->dispatch('board-toast', message: 'Task tidak ditemukan lagi, pilih ulang.');

            return;
        }

        $options = app(InterruptionOptionGenerator::class)->generateOptions($op, (int) $op->machine_id, $op->process_name);

        $this->hotOptions = collect($options)->map(function ($o) {
            $arr = $o->toArray();
            if ($arr['displaced_operation_id']) {
                $d = PrdWoOperation::with('wo')->find($arr['displaced_operation_id']);
                $arr['displaced_wo_no'] = $d?->wo?->wo_no;
                $arr['displaced_process'] = $d?->process_name;
                $arr['displaced_old_start'] = $d?->target_start?->format('H:i');
                $arr['displaced_old_finish'] = $d?->target_finish?->format('H:i');
            }

            return $arr;
        })->values()->all();

        $this->hotOptionsLoaded = true;
        $this->hotSelectedOption = null;
    }

    // Terapkan opsi terpilih -- hot job mengambil alih slot task yang digeser,
    // task yang digeser mundur sejumlah durasi hot job (right-shift lokal,
    // Tier2 §5 langkah 4 -- backward schedule TIDAK dihitung ulang total).
    public function applyHotJobOption(): void
    {
        if ($this->hotSelectedOption === null || ! isset($this->hotOptions[$this->hotSelectedOption])) {
            return;
        }

        $option = $this->hotOptions[$this->hotSelectedOption];

        if ($option['requires_subcontract_po']) {
            $this->dispatch('board-toast', message: 'Reroute subkontrak belum aktif -- perlu fitur PO Subkontrak (Tier1 §9.3), belum dibangun.');

            return;
        }

        $wo = PrdWo::find($this->hotWoId);
        $op = PrdWoOperation::find($this->hotOperationId);
        $displaced = PrdWoOperation::with('wo')->find($option['displaced_operation_id']);

        if (! $wo || ! $op || ! $displaced || ! $displaced->target_start || ! $displaced->target_finish) {
            $this->dispatch('board-toast', message: 'Data sudah berubah, cari opsi ulang.');
            $this->hotOptionsLoaded = false;

            return;
        }

        $oldStart = $displaced->target_start->copy();
        $oldFinish = $displaced->target_finish->copy();

        $wo->is_hot_job = true;
        $wo->hot_job_source = $this->hotSource;
        $wo->hot_job_customer_id = $this->hotSource === 'CUSTOMER' ? $this->hotCustomerId : null;
        $wo->save();

        $op->is_hot_job = true;
        $op->target_start = $oldStart;
        $op->target_finish = $oldFinish;
        $op->updated_by = Auth::user()->login;
        $op->transitionTo('ASSIGNED');

        $displaced->target_start = $oldStart->copy()->addHours($option['impact_hours']);
        $displaced->target_finish = $oldFinish->copy()->addHours($option['impact_hours']);
        $displaced->updated_by = Auth::user()->login;
        $displaced->save();

        app(InterruptionOptionGenerator::class)->recordDecision(
            eventType: 'INTERRUPTION',
            woId: $wo->wo_id,
            customerId: $wo->hot_job_customer_id,
            machineId: $op->machine_id,
            optionsPresented: $this->hotOptions,
            optionChosen: $option['label'],
            chosenBy: Auth::user()->login,
        );

        $this->showHotJobDialog = false;
        $this->dispatch('board-toast', message: "🔥 {$wo->wo_no} disisip -- {$displaced->wo->wo_no} digeser ".number_format($option['impact_hours'], 1).' jam.');
    }
};
?>

<div x-data="{}">
    <div class="w-full px-5 py-4.5">

        @php($board = $this->board())

        <div class="flex items-center gap-2 flex-wrap mb-3.5">
            <h1 class="text-lg font-bold text-navy">🗓️ Scheduling Board</h1>
            <span class="text-xs text-muted">tm2prd.prd_wo_operation</span>

            <div class="flex bg-white border border-[#e5e7eb] rounded-full p-1 text-xs font-medium ml-2">
                <button type="button" wire:click="setMode('simulasi')" class="px-3 py-1.5 rounded-full {{ $mode === 'simulasi' ? 'bg-[#dbeafe] text-[#1d4ed8]' : 'text-muted' }}">● Simulasi</button>
                <button type="button" wire:click="setMode('live')" class="px-3 py-1.5 rounded-full {{ $mode === 'live' ? 'bg-[#dcfce7] text-[#15803d]' : 'text-muted' }}">○ Live</button>
            </div>
            <span class="text-[10px] text-muted italic">Mode saat ini tampilan saja -- Simulation Layer &amp; Submit sungguhan menyusul sesi berikutnya.</span>
        </div>

        <div class="flex items-center gap-2 flex-wrap mb-3.5">
            <button type="button" wire:click="shiftDate(-1)" class="px-2.5 py-1.5 rounded-md text-xs font-semibold border border-[#c7cfda] bg-white cursor-pointer">‹</button>
            <x-form.date-picker wire:model.live="date" class="w-[130px]" />
            <button type="button" wire:click="shiftDate(1)" class="px-2.5 py-1.5 rounded-md text-xs font-semibold border border-[#c7cfda] bg-white cursor-pointer">›</button>

            <select wire:model.live="filterMachine" class="px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                <option value="all">Semua Mesin</option>
                @foreach ($this->machines as $m)
                    <option value="{{ $m->machine_id }}">{{ $m->machine_name }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterProcess" class="px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                <option value="all">Semua Proses</option>
                @foreach ($this->processOptions as $p)
                    <option value="{{ $p }}">{{ $p }}</option>
                @endforeach
            </select>

            @if ($filterMachine !== 'all' || $filterProcess !== 'all')
                <button type="button" wire:click="$set('filterMachine','all'); $set('filterProcess','all')" class="text-[11px] text-muted underline cursor-pointer">Reset semua filter</button>
            @endif

            <span class="flex-1"></span>

            <button type="button" wire:click="openHotJobDialog" class="px-4 py-2 rounded-md text-xs font-semibold text-white bg-[#dc2626] hover:bg-[#b91c1c] cursor-pointer inline-flex items-center gap-1.5">🔥 Sisip Hot Job</button>
            <button type="button" wire:click="notYetAvailable('Submit ke Master Schedule')" class="px-4 py-2 rounded-md text-xs font-semibold text-white bg-[#16a34a] hover:bg-[#15803d] cursor-pointer" title="Simulation Layer penuh belum dibangun">Submit ke Master Schedule</button>
        </div>

        {{-- griduistandard.md §2 -- kotak statistik pastel lewat komponen bersama.
             stat-box tidak forward $attributes ke root-nya, jadi interaksi klik-
             untuk-sorot dibungkus div terpisah di LUAR komponen. Highlight lewat
             $store.gantt (global, didaftarkan oleh komponen gantt.board di bawah)
             supaya kartu di sini bisa menyorot blok di DALAM komponen Gantt. --}}
        <x-grid.stat-row :count="4">
            <div @click="$store.gantt.clear()" class="cursor-pointer">
                <x-grid.stat-box tone="total" label="Total Task Aktif" :value="$board['stats']['totalActive']" subtitle="klik untuk reset sorotan" />
            </div>
            <x-grid.stat-box tone="critical" label="⚠ Belum Terjadwal" :value="$board['stats']['unscheduled']" />
            <div @click="$store.gantt.toggle('risk')" class="cursor-pointer rounded-lg" :class="{ 'ring-2 ring-[#92400e]': $store.gantt.mode === 'risk' }">
                <x-grid.stat-box tone="warn" label="Berisiko Telat (CR<1)" :value="$board['stats']['atRisk']" subtitle="klik untuk sorot di board" />
            </div>
            <div @click="$store.gantt.toggle('idle')" class="cursor-pointer rounded-lg" :class="{ 'ring-2 ring-[#6d28d9]': $store.gantt.mode === 'idle' }">
                <x-grid.stat-box tone="extra" label="Mesin Kosong >2 jam" :value="$board['stats']['idleMachineCount']" subtitle="klik untuk sorot di board" />
            </div>
        </x-grid.stat-row>

        <div class="flex items-center gap-3.5 mb-4 text-[11px] text-muted flex-wrap">
            <div class="flex items-center gap-2.5 pr-3.5 border-r border-[#e5e7eb]">
                <span class="flex items-center gap-1"><span style="width:11px;height:11px;border-radius:3px;display:inline-block;background:#bfdbfe;"></span>Cetak</span>
                <span class="flex items-center gap-1"><span style="width:11px;height:11px;border-radius:3px;display:inline-block;background:#ddd6fe;"></span>Laminasi</span>
                <span class="flex items-center gap-1"><span style="width:11px;height:11px;border-radius:3px;display:inline-block;background:#fed7aa;"></span>Plong</span>
                <span class="flex items-center gap-1"><span style="width:11px;height:11px;border-radius:3px;display:inline-block;background:#99f6e4;"></span>Packing</span>
            </div>
            <div class="flex items-center gap-2.5 pr-3.5 border-r border-[#e5e7eb]">
                <span class="flex items-center gap-1"><span style="width:11px;height:11px;border-radius:3px;display:inline-block;background:#fca5a5;"></span>Breakdown</span>
                <span class="flex items-center gap-1"><span style="width:11px;height:11px;border-radius:3px;display:inline-block;background:#fff;border:2px dashed #d97706;"></span>QC Hold (terkunci)</span>
                <span class="flex items-center gap-1"><span style="width:11px;height:11px;border-radius:3px;display:inline-block;background:#fff;border:2px solid #dc2626;"></span>🔥 Hot Job</span>
            </div>
            <div>▬ garis dalam blok = progress pengerjaan</div>
        </div>

        <x-gantt.board
            :rows="$board['machineRows']"
            :secondary-rows="$board['manualRows']"
            secondary-label="MANUAL JOB (per orang — bukan mesin, §5A.9)"
            resource-header-label="Mesin / Crew"
            :timeline-start="8"
            :timeline-end="20"
            :px-per-hour="70"
            move-method="moveOperation"
            resize-method="resizeOperation"
            reassign-method="reassignOperation"
            :resource-options="$this->resourceOptions()"
            :label-options="$this->processOptions->all()"
            label-field-name="Proses"
            empty-message="Tidak ada mesin aktif di divisi Offset Printing."
        />

        <p class="footnote text-[11px] text-[#9ca3af] mt-3">
            Drag blok = ubah waktu mulai · tarik tepi kanan = ubah durasi · klik = detail singkat ·
            double-klik = ubah mesin/orang &amp; proses. Perubahan langsung tersimpan ke database
            (belum lewat Simulation Layer -- lihat catatan Mode di atas). Blok <b>QC Hold</b> terkunci,
            tidak bisa digeser (Tier4 §6).
        </p>
    </div>

    {{-- Interruption Dialog -- Tier4 §4. Shell modal sama dgn x-grid.sort-modal
         (overlay + panel putih rounded-xl), disesuaikan warna header merah
         (§brand hot job) & lebih lebar utk kartu opsi. --}}
    @if ($showHotJobDialog)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="closeHotJobDialog">
            <div class="bg-white rounded-xl w-[640px] max-w-[95vw] max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="bg-[#dc2626] text-white px-4 py-3.5 text-sm font-semibold flex items-center justify-between">
                    <span>🔥 Sisipkan Pekerjaan Mendadak</span>
                    <button type="button" wire:click="closeHotJobDialog" class="text-white/70 hover:text-white cursor-pointer">✕</button>
                </div>

                <div class="p-4 space-y-4">
                    <div>
                        <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">WO yang disisip</label>
                        <select wire:model.live="hotWoId" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                            <option value="">-- pilih WO belum terjadwal --</option>
                            @foreach ($this->unscheduledHotJobCandidates as $c)
                                <option value="{{ $c->wo_id }}">{{ $c->wo_no }} · qty {{ number_format($c->qty, 0) }} · due {{ $c->due_date?->format('d M Y H:i') }}</option>
                            @endforeach
                        </select>
                        @if ($this->unscheduledHotJobCandidates->isEmpty())
                            <p class="text-[11px] text-muted mt-1">Tidak ada WO yang bisa disisip sekarang (butuh task mesin yang belum terjadwal).</p>
                        @endif
                    </div>

                    @if ($hotWoId && $hotOperationId)
                        <div class="grid two gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Sumber</label>
                                <select wire:model.live="hotSource" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                                    <option value="DIREKSI">Direksi</option>
                                    <option value="CUSTOMER">Customer</option>
                                    <option value="INTERNAL_REWORK">Internal Rework</option>
                                </select>
                            </div>
                            <div class="relative">
                                <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Customer <span class="normal-case font-normal">(opsional)</span></label>
                                @if ($hotSource === 'CUSTOMER')
                                    @if ($hotCustomerId)
                                        <div class="flex items-center gap-1.5">
                                            <input type="text" value="{{ $hotCustomerName }}" readonly class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs bg-[#f7f7f5]">
                                            <button type="button" wire:click="clearHotCustomer" class="text-[11px] text-muted underline cursor-pointer shrink-0">Ganti</button>
                                        </div>
                                    @else
                                        <input type="text" wire:model.live.debounce.400ms="hotCustQuery" autocomplete="off" placeholder="Ketik nama/kode customer…" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                                        @if ($hotCustQuery && mb_strlen($hotCustQuery) >= 2)
                                            <div class="absolute left-0 right-0 mt-1 bg-white border border-[#e5e7eb] rounded-md shadow-lg z-10 max-h-40 overflow-y-auto">
                                                @forelse ($this->hotCustomerResults as $c)
                                                    <div wire:click="selectHotCustomer({{ $c->id_customer }})" class="px-2.5 py-1.5 text-xs cursor-pointer hover:bg-[#f4f5f8] flex items-center justify-between">
                                                        <span>{{ $c->customer_name }}</span>
                                                        <span class="text-muted text-[10px]">{{ $c->customer_code }}</span>
                                                    </div>
                                                @empty
                                                    <div class="px-2.5 py-1.5 text-xs text-muted">Tidak ditemukan.</div>
                                                @endforelse
                                            </div>
                                        @endif
                                    @endif
                                @else
                                    <input type="text" disabled placeholder="— tidak relevan —" class="w-full px-2.5 py-1.5 border border-[#e5e7eb] rounded-md text-xs bg-[#f7f7f5] text-muted">
                                @endif
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="button" wire:click="generateHotJobOptions" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#2c3e69] text-white cursor-pointer">Cari Opsi Penyisipan</button>
                        </div>
                    @endif

                    @if ($hotOptionsLoaded)
                        <div>
                            @if (empty($hotOptions))
                                <p class="text-xs text-muted">Tidak ada opsi penyisipan valid ditemukan untuk mesin/proses task ini.</p>
                            @else
                                <p class="text-[11px] text-muted mb-2">Sistem menemukan {{ count($hotOptions) }} opsi penyisipan:</p>
                                <div class="space-y-2">
                                    @foreach ($hotOptions as $i => $opt)
                                        <label wire:key="hot-option-{{ $i }}" class="flex items-start gap-2.5 border rounded-lg p-3 cursor-pointer {{ $this->optionAccentClasses($opt) }} {{ (int) $hotSelectedOption === $i ? 'ring-2 ring-[#2c3e69]' : '' }}">
                                            <input type="radio" name="hotOption" wire:model.live="hotSelectedOption" value="{{ $i }}" class="mt-0.5">
                                            <div class="flex-1 text-xs">
                                                <div class="font-semibold mb-0.5">
                                                    {{ $opt['label'] }}
                                                    @if ($i === 0 && ! $opt['requires_subcontract_po'])
                                                        <span class="text-[9px] font-bold uppercase text-[#15803d]">Rank tertinggi</span>
                                                    @endif
                                                </div>
                                                @if ($opt['displaced_wo_no'])
                                                    <div class="text-muted">Sisip di slot {{ $opt['displaced_old_start'] }}–{{ $opt['displaced_old_finish'] }} · menggeser {{ $opt['displaced_wo_no'] }} ({{ $opt['displaced_process'] }})</div>
                                                    <div class="mt-0.5 {{ $opt['is_safe'] ? 'text-[#15803d]' : 'text-[#92400e]' }}">
                                                        {{ $opt['is_safe'] ? '✓' : '⚠' }} Dampak: {{ $opt['displaced_wo_no'] }} mundur {{ number_format($opt['impact_hours'], 1) }} jam {{ $opt['is_safe'] ? '— masih aman dari due date' : '— BERISIKO TELAT dari due date' }}
                                                    </div>
                                                @else
                                                    <div class="text-muted">Tidak ada job lain tergeser.</div>
                                                    <div class="mt-0.5 text-[#1d4ed8]">⚠ Perlu PO Subkontrak (biaya belum dihitung otomatis) -- belum bisa diterapkan di iterasi ini</div>
                                                @endif
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="flex justify-end gap-2 px-4 py-3 border-t border-[#eef1f5]">
                    <button type="button" wire:click="closeHotJobDialog" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                    <button type="button" wire:click="applyHotJobOption" @if ($hotSelectedOption === null) disabled @endif
                        class="px-4 py-2 rounded-md text-xs font-semibold text-white {{ $hotSelectedOption === null ? 'bg-[#9ca3af] cursor-not-allowed' : 'bg-[#dc2626] hover:bg-[#b91c1c] cursor-pointer' }}">
                        Terapkan Opsi Terpilih
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
