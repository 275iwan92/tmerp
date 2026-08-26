<?php

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpShift;
use App\Models\Master\EmpShiftRoster;
use App\Models\Master\EmpShiftRosterBatch;
use App\Models\Master\OrgDivision;
use App\Models\Master\ProdMachine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// mockup_shift_roster_v2.html. masteremployee_tier2.md §A.7/A.8 + TIER_3A_HRM.sql
// §2.7a/b. Dua papan:
//  - PPIC (Offset Printing): drag-drop pegawai ke sel mesin x shift x tanggal,
//    lifecycle Save(DRAFT)->Confirm(CONFIRMED, terkunci+publish)->Unlock.
//  - Grid HR (Digital Printing/Indostry): tanpa mesin, klik sel siklus ganti
//    shift, tersimpan langsung tanpa alur draft/confirm (masteremployee_tier2.md §A.8).
// Drag & drop dieksekusi via Alpine ($wire) di Blade, state $assign disimpan
// di memori komponen sampai [Simpan Draft]/[Publish & Confirm] ditekan —
// mirror perilaku mockup asli (drag hanya mutasi in-memory, alert "tersimpan"
// baru di tombol Save). Bentrok HARD divalidasi saat Confirm (app-layer,
// masteremployee_tier2.md §A.7.4), bukan saat drop (drop tetap diterima + ditandai).
new #[Title('Shift Roster — TELASIH MAKMUR ERP')] class extends Component
{
    public string $tab = 'ppic'; // ppic | hr

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?int $currentMachineId = null;

    public ?int $hrDivisionId = null;

    public string $poolSearch = '';

    // $assign[machineId][date][shiftId] = [employeeId, ...]
    public array $assign = [];

    // $otAssign["machineId|date|shiftId|employeeId"] = bool
    public array $otAssign = [];

    public ?int $batchId = null;

    public string $batchStatus = 'DRAFT';

    public function mount(): void
    {
        $monday = Carbon::now()->startOfWeek();
        $this->dateFrom = $monday->toDateString();
        $this->dateTo = $monday->copy()->addDays(6)->toDateString();
        $this->hrDivisionId = OrgDivision::where('division_code', 'DIGITAL')->value('division_id');
        $this->loadPeriod();
    }

    private function offsetDivisionId(): int
    {
        return OrgDivision::where('division_code', 'OFFSET')->value('division_id');
    }

    public function updatedDateFrom(): void
    {
        $this->loadPeriod();
    }

    public function updatedDateTo(): void
    {
        $this->loadPeriod();
    }

    private function loadPeriod(): void
    {
        $offsetId = $this->offsetDivisionId();

        $batch = EmpShiftRosterBatch::where('division_id', $offsetId)
            ->where('date_from', $this->dateFrom)
            ->where('date_to', $this->dateTo)
            ->first();

        $this->assign = [];
        $this->otAssign = [];
        $this->batchId = $batch?->batch_id;
        $this->batchStatus = $batch?->status ?? 'DRAFT';

        $rows = EmpShiftRoster::query()
            ->where('division_id', $offsetId)
            ->whereBetween('roster_date', [$this->dateFrom, $this->dateTo])
            ->whereNotNull('machine_id')
            ->get();

        foreach ($rows as $r) {
            $date = $r->roster_date->format('Y-m-d');
            $this->assign[$r->machine_id][$date][$r->shift_id] ??= [];
            $this->assign[$r->machine_id][$date][$r->shift_id][] = $r->employee_id;

            if ($r->is_overtime) {
                $this->otAssign["{$r->machine_id}|{$date}|{$r->shift_id}|{$r->employee_id}"] = true;
            }
        }

        unset($this->conflictSet);
    }

    #[Computed]
    public function days()
    {
        $from = Carbon::parse($this->dateFrom);
        $to = Carbon::parse($this->dateTo);

        if ($to->lt($from)) {
            return collect();
        }

        $days = collect();
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $days->push($d->copy());
        }

        return $days;
    }

    #[Computed]
    public function machines()
    {
        return ProdMachine::query()->where('division_id', $this->offsetDivisionId())->where('is_active', true)->orderBy('machine_code')->get();
    }

    #[Computed]
    public function ppicShifts()
    {
        return EmpShift::query()
            ->whereHas('department', fn ($q) => $q->where('division_id', $this->offsetDivisionId()))
            ->where('is_active', true)
            ->orderBy('shift_name')
            ->get();
    }

    #[Computed]
    public function pool()
    {
        return EmpEmployee::query()
            ->whereHas('position.department', fn ($q) => $q->where('division_id', $this->offsetDivisionId()))
            ->where('is_active', true)
            ->when($this->poolSearch !== '', fn ($q) => $q->where('full_name', 'like', "%{$this->poolSearch}%"))
            ->orderBy('full_name')
            ->get();
    }

    #[Computed]
    public function employeeNames()
    {
        return EmpEmployee::query()
            ->whereHas('position.department', fn ($q) => $q->where('division_id', $this->offsetDivisionId()))
            ->pluck('full_name', 'employee_id');
    }

    #[Computed]
    public function metricAssigned(): int
    {
        $total = 0;
        foreach ($this->assign as $byDate) {
            foreach ($byDate as $byShift) {
                foreach ($byShift as $employeeIds) {
                    $total += count($employeeIds);
                }
            }
        }

        return $total;
    }

    #[Computed]
    public function metricOvertime(): int
    {
        return count(array_filter($this->otAssign));
    }

    #[Computed]
    public function conflictSet(): array
    {
        // key -> ['hard'=>bool,'soft'=>bool] per (employeeId, date)
        $result = [];

        foreach ($this->assign as $machineId => $byDate) {
            foreach ($byDate as $date => $byShift) {
                foreach ($byShift as $shiftId => $employeeIds) {
                    foreach ($employeeIds as $employeeId) {
                        $conflict = $this->cellConflict($employeeId, $date, $shiftId, $machineId);
                        if ($conflict['hard'] || $conflict['soft']) {
                            $result["{$employeeId}|{$date}"] = $conflict;
                        }
                    }
                }
            }
        }

        return $result;
    }

    #[Computed]
    public function metricConflicts(): int
    {
        return count($this->conflictSet);
    }

    private function cellConflict(int $employeeId, string $date, int $shiftId, int $excludeMachineId): array
    {
        $hard = false;
        $soft = false;

        foreach ($this->assign as $machineId => $byDate) {
            foreach (($byDate[$date] ?? []) as $sId => $employeeIds) {
                if ($machineId === $excludeMachineId && (int) $sId === $shiftId) {
                    continue;
                }
                if (! in_array($employeeId, $employeeIds, true)) {
                    continue;
                }
                if ((int) $sId === $shiftId) {
                    $hard = true;
                } else {
                    $soft = true;
                }
            }
        }

        return ['hard' => $hard, 'soft' => $soft];
    }

    public function chipClass(int $employeeId, string $date, int $shiftId, int $machineId): string
    {
        $conflict = $this->cellConflict($employeeId, $date, $shiftId, $machineId);
        if ($conflict['hard']) {
            return 'busy-badge';
        }
        if ($conflict['soft']) {
            return 'busy-badge-soft';
        }

        return '';
    }

    // ============== DRAG & DROP (dipanggil dari Alpine/$wire) ==============
    public function dropOnSlot(int $machineId, string $date, int $shiftId, array $payload): void
    {
        if ($this->batchStatus === 'CONFIRMED') {
            return;
        }

        $employeeId = (int) ($payload['employeeId'] ?? 0);
        if (! $employeeId) {
            return;
        }

        if (! empty($payload['fromKey'])) {
            $this->detachFromCell(
                (int) $payload['fromKey']['machineId'],
                $payload['fromKey']['date'],
                (int) $payload['fromKey']['shiftId'],
                $employeeId,
                carryOtTo: [$machineId, $date, $shiftId]
            );
        }

        $this->assign[$machineId][$date][$shiftId] ??= [];
        if (! in_array($employeeId, $this->assign[$machineId][$date][$shiftId], true)) {
            $this->assign[$machineId][$date][$shiftId][] = $employeeId;
        }

        unset($this->conflictSet);
    }

    public function dropOnPool(array $payload): void
    {
        if ($this->batchStatus === 'CONFIRMED' || empty($payload['fromKey'])) {
            return;
        }

        $employeeId = (int) ($payload['employeeId'] ?? 0);
        $this->detachFromCell(
            (int) $payload['fromKey']['machineId'],
            $payload['fromKey']['date'],
            (int) $payload['fromKey']['shiftId'],
            $employeeId
        );

        unset($this->conflictSet);
    }

    public function removeAssignment(int $machineId, string $date, int $shiftId, int $employeeId): void
    {
        if ($this->batchStatus === 'CONFIRMED') {
            return;
        }

        $this->detachFromCell($machineId, $date, $shiftId, $employeeId);
        unset($this->conflictSet);
    }

    private function detachFromCell(int $machineId, string $date, int $shiftId, int $employeeId, ?array $carryOtTo = null): void
    {
        $key = "{$machineId}|{$date}|{$shiftId}|{$employeeId}";
        $hadOt = (bool) ($this->otAssign[$key] ?? false);
        unset($this->otAssign[$key]);

        if (isset($this->assign[$machineId][$date][$shiftId])) {
            $this->assign[$machineId][$date][$shiftId] = array_values(array_filter(
                $this->assign[$machineId][$date][$shiftId],
                fn ($id) => $id !== $employeeId
            ));
        }

        if ($hadOt && $carryOtTo) {
            [$toMachine, $toDate, $toShift] = $carryOtTo;
            $this->otAssign["{$toMachine}|{$toDate}|{$toShift}|{$employeeId}"] = true;
        }
    }

    public function toggleOt(int $machineId, string $date, int $shiftId, int $employeeId): void
    {
        if ($this->batchStatus === 'CONFIRMED') {
            return;
        }

        $key = "{$machineId}|{$date}|{$shiftId}|{$employeeId}";
        $this->otAssign[$key] = ! ($this->otAssign[$key] ?? false);
    }

    public function switchMachine(int $machineId): void
    {
        $this->currentMachineId = $machineId;
    }

    // ============== LIFECYCLE BATCH ==============
    private function persist(string $status): void
    {
        $offsetId = $this->offsetDivisionId();

        $batch = EmpShiftRosterBatch::firstOrNew([
            'division_id' => $offsetId,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ]);

        $isNew = ! $batch->exists;
        $batch->status = $status;
        $batch->updated_by = Auth::user()->login;

        if ($isNew) {
            $batch->created_by = Auth::user()->login;
        }

        if ($status === 'CONFIRMED') {
            $batch->confirmed_by = Auth::user()->login;
            $batch->confirmed_at = now();
        } else {
            $batch->confirmed_by = null;
            $batch->confirmed_at = null;
        }

        $batch->save();

        EmpShiftRoster::where('batch_id', $batch->batch_id)->delete();

        $rows = [];
        foreach ($this->assign as $machineId => $byDate) {
            foreach ($byDate as $date => $byShift) {
                foreach ($byShift as $shiftId => $employeeIds) {
                    foreach ($employeeIds as $employeeId) {
                        $isOt = (bool) ($this->otAssign["{$machineId}|{$date}|{$shiftId}|{$employeeId}"] ?? false);
                        $rows[] = [
                            'batch_id' => $batch->batch_id,
                            'employee_id' => $employeeId,
                            'division_id' => $offsetId,
                            'machine_id' => $machineId,
                            'shift_id' => $shiftId,
                            'roster_date' => $date,
                            'assigned_by_role' => 'PPIC',
                            'is_overtime' => $isOt,
                            'overtime_type' => $isOt ? 'REGULER' : null,
                            'created_by' => Auth::user()->login,
                            'updated_by' => Auth::user()->login,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }
        }

        if ($rows) {
            EmpShiftRoster::insert($rows);
        }

        $this->batchId = $batch->batch_id;
        $this->batchStatus = $batch->status;
    }

    public function saveDraft(): void
    {
        $this->persist('DRAFT');
    }

    public function confirmBatch(): void
    {
        $this->resetErrorBag();
        $hardConflicts = collect($this->conflictSet)->filter(fn ($c) => $c['hard']);

        if ($hardConflicts->isNotEmpty()) {
            $names = $hardConflicts->keys()->map(function ($key) {
                [$employeeId, $date] = explode('|', $key);

                return ($this->employeeNames[(int) $employeeId] ?? '?')." ({$date})";
            })->unique()->implode(', ');

            $this->addError('confirm', "Ada bentrok keras (1 orang, mesin beda, shift+tanggal sama): {$names}. Perbaiki dulu sebelum publish.");

            return;
        }

        $this->persist('CONFIRMED');
    }

    public function unlockBatch(): void
    {
        if (! $this->batchId) {
            return;
        }

        $batch = EmpShiftRosterBatch::find($this->batchId);
        $batch?->update(['status' => 'DRAFT', 'confirmed_by' => null, 'confirmed_at' => null, 'updated_by' => Auth::user()->login]);
        $this->batchStatus = 'DRAFT';
    }

    // ============== GRID HR (Digital Printing / Indostry) ==============
    #[Computed]
    public function hrDivisions()
    {
        return OrgDivision::query()->where('division_code', '!=', 'OFFSET')->where('is_active', true)->orderBy('division_name')->get();
    }

    #[Computed]
    public function hrShiftOptions()
    {
        if (! $this->hrDivisionId) {
            return collect();
        }

        return EmpShift::query()
            ->whereHas('department', fn ($q) => $q->where('division_id', $this->hrDivisionId))
            ->where('is_active', true)
            ->orderBy('shift_name')
            ->get();
    }

    #[Computed]
    public function hrEmployees()
    {
        if (! $this->hrDivisionId) {
            return collect();
        }

        return EmpEmployee::query()
            ->whereHas('position.department', fn ($q) => $q->where('division_id', $this->hrDivisionId))
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get();
    }

    #[Computed]
    public function hrAssignments()
    {
        if (! $this->hrDivisionId) {
            return collect();
        }

        return EmpShiftRoster::query()
            ->where('division_id', $this->hrDivisionId)
            ->whereNull('machine_id')
            ->whereBetween('roster_date', [$this->dateFrom, $this->dateTo])
            ->get()
            ->keyBy(fn ($r) => $r->employee_id.'|'.$r->roster_date->format('Y-m-d'));
    }

    public function cycleHrCell(int $employeeId, string $date): void
    {
        $shifts = $this->hrShiftOptions->values();
        if ($shifts->isEmpty()) {
            return;
        }

        $existing = EmpShiftRoster::where('employee_id', $employeeId)
            ->where('roster_date', $date)
            ->whereNull('machine_id')
            ->where('division_id', $this->hrDivisionId)
            ->first();

        $currentIndex = $existing ? $shifts->search(fn ($s) => $s->shift_id === $existing->shift_id) : -1;
        $nextIndex = ($currentIndex === false ? -1 : $currentIndex) + 1;

        if ($existing) {
            $existing->delete();
        }

        if ($nextIndex < $shifts->count()) {
            EmpShiftRoster::create([
                'employee_id' => $employeeId,
                'division_id' => $this->hrDivisionId,
                'machine_id' => null,
                'shift_id' => $shifts[$nextIndex]->shift_id,
                'roster_date' => $date,
                'assigned_by_role' => 'HR',
                'is_overtime' => false,
                'created_by' => Auth::user()->login,
                'updated_by' => Auth::user()->login,
            ]);
        }

        unset($this->hrAssignments);
    }
};
?>

<div>
    <div class="w-full px-5 py-4.5">
        <div class="flex items-center gap-2 flex-wrap mb-3.5">
            <h1 class="text-lg font-bold text-navy">📅 Shift Roster &amp; Alokasi Mesin</h1>
            <span class="text-xs text-muted">tm2mst.emp_shift_roster</span>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-3.5">
            <div class="bg-white border border-[#d9dfe8] rounded-lg px-3.5 py-2.5">
                <div class="text-[11px] text-muted uppercase font-bold">Total Terjadwal</div>
                <div class="text-[17px] font-extrabold text-navy mt-0.5">{{ $this->metricAssigned }}</div>
            </div>
            <div class="bg-white border border-[#d9dfe8] rounded-lg px-3.5 py-2.5">
                <div class="text-[11px] text-muted uppercase font-bold">Jumlah Lembur (OT)</div>
                <div class="text-[17px] font-extrabold text-[#c0392b] mt-0.5">{{ $this->metricOvertime }}</div>
            </div>
            <div class="bg-white border border-[#d9dfe8] rounded-lg px-3.5 py-2.5">
                <div class="text-[11px] text-muted uppercase font-bold">Potensi Bentrok</div>
                <div class="text-[17px] font-extrabold text-[#b45309] mt-0.5">{{ $this->metricConflicts }}</div>
            </div>
            <div class="bg-white border border-[#d9dfe8] rounded-lg px-3.5 py-2.5">
                <div class="text-[11px] text-muted uppercase font-bold">Pegawai di Pool</div>
                <div class="text-[17px] font-extrabold text-[#2c7be5] mt-0.5">{{ $this->pool->count() }}</div>
            </div>
        </div>

        <div class="flex gap-1 border-b border-[#e2e7ee] mb-3.5">
            <button type="button" wire:click="$set('tab', 'ppic')" class="px-3.5 py-2 text-xs font-semibold cursor-pointer border-b-2 {{ $tab === 'ppic' ? 'text-accent border-accent' : 'text-muted border-transparent' }}">Papan PPIC — Offset Printing (Penugasan Mesin)</button>
            <button type="button" wire:click="$set('tab', 'hr')" class="px-3.5 py-2 text-xs font-semibold cursor-pointer border-b-2 {{ $tab === 'hr' ? 'text-accent border-accent' : 'text-muted border-transparent' }}">Grid HR — Digital Printing / Indostry (Shift Non-Mesin)</button>
        </div>

        @if ($tab === 'ppic')
            <div class="bg-white border border-[#d9dfe8] rounded-[10px] p-4">
                <div class="flex items-center gap-2 flex-wrap mb-3">
                    <span class="text-[10px] font-bold px-2.5 py-1 rounded-md uppercase bg-[#f1eefc] text-[#6c5dd3]">PPIC Mode</span>
                    <span class="text-[10px] font-bold px-2.5 py-1 rounded-md uppercase {{ $batchStatus === 'CONFIRMED' ? 'bg-[#e2f4e6] text-[#1e7d3a]' : 'bg-[#fff8e7] text-[#b45309]' }}">{{ $batchStatus === 'CONFIRMED' ? 'CONFIRMED & PUBLISHED' : 'DRAFT' }}</span>

                    <span class="text-xs text-muted ml-2">Periode:</span>
                    <input type="date" wire:model.live="dateFrom" class="px-2 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                    <span class="text-xs text-muted">s/d</span>
                    <input type="date" wire:model.live="dateTo" class="px-2 py-1.5 border border-[#c7cfda] rounded-md text-xs">

                    <span class="flex-1"></span>

                    @if ($batchStatus === 'CONFIRMED')
                        <button type="button" wire:click="unlockBatch" class="px-3 py-1.5 rounded-md text-[11px] font-semibold cursor-pointer bg-white border border-[#c0392b] text-[#c0392b]">🔓 Buka Kunci (Revisi)</button>
                    @else
                        <button type="button" wire:click="saveDraft" class="px-3 py-1.5 rounded-md text-[11px] font-semibold cursor-pointer bg-white border border-[#c7cfda]">💾 Simpan Draft</button>
                        <button type="button" wire:click="confirmBatch" class="px-3 py-1.5 rounded-md text-[11px] font-semibold cursor-pointer bg-[#1e7d3a] text-white">✓ Publish &amp; Confirm</button>
                    @endif
                </div>

                @error('confirm') <div class="text-xs px-3 py-2.5 rounded-md mb-3 bg-[#fdeceb] border border-[#f3b8b0] text-[#c0392b]">{{ $message }}</div> @enderror

                <div class="text-xs px-3 py-2.5 rounded-md mb-3 leading-relaxed bg-[#eef2ff] border border-[#c7d0f5] text-[#3f4a8a]">
                    Seret nama pegawai dari <b>Pool</b> ke sel jadwal. Seret kembali chip ke kotak Pool untuk membatalkan. Perubahan baru tersimpan permanen setelah <b>Simpan Draft</b> / <b>Publish &amp; Confirm</b>.
                </div>

                <div class="flex gap-2 flex-wrap mb-3">
                    @forelse ($this->machines as $m)
                        <button type="button" wire:click="switchMachine({{ $m->machine_id }})"
                            class="px-3.5 py-2 rounded-md text-xs font-semibold cursor-pointer border {{ ($currentMachineId ?? $this->machines->first()?->machine_id) === $m->machine_id ? 'bg-navy border-navy text-white' : 'bg-white border-[#c7cfda] text-[#556]' }}">
                            {{ $m->machine_code }} — {{ $m->machine_name }}
                        </button>
                    @empty
                        <div class="text-xs text-muted">Belum ada mesin aktif di Offset Printing.</div>
                    @endforelse
                </div>

                @php $machineId = $currentMachineId ?? $this->machines->first()?->machine_id; @endphp

                @if ($machineId)
                    <div class="overflow-x-auto border border-[#e9edf7] rounded-lg" wire:key="board-{{ $machineId }}">
                        <table class="w-full border-collapse text-xs" style="table-layout: fixed;">
                            <thead>
                                <tr>
                                    <th class="text-left px-2 py-2 bg-[#f8fafc] text-navy font-bold border border-[#e9edf7] w-[170px]">Shift / Jam</th>
                                    @foreach ($this->days as $day)
                                        <th class="px-2 py-2 bg-[#f8fafc] text-navy font-bold border border-[#e9edf7]">{{ $day->translatedFormat('D d/m') }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->ppicShifts as $shift)
                                    <tr>
                                        <td class="px-2 py-2 border border-[#e9edf7] font-semibold text-navy align-top">
                                            {{ $shift->shift_name }}
                                            <div class="text-[10px] text-muted font-normal">{{ $shift->scheduleSummary() }}</div>
                                        </td>
                                        @foreach ($this->days as $day)
                                            @php
                                                $date = $day->toDateString();
                                                $occupants = $this->assign[$machineId][$date][$shift->shift_id] ?? [];
                                                $slotKey = "{$machineId}|{$date}|{$shift->shift_id}";
                                            @endphp
                                            <td class="p-1 border border-[#e9edf7] align-top">
                                                <div wire:key="slot-{{ $slotKey }}"
                                                    class="min-h-[52px] rounded-md border border-dashed border-[#c7cfda] bg-[#fafbfc] p-1 flex flex-col gap-1 transition-colors"
                                                    x-data
                                                    x-on:dragover.prevent="$el.classList.add('!bg-[#eef2ff]','!border-solid','!border-accent')"
                                                    x-on:dragleave="$el.classList.remove('!bg-[#eef2ff]','!border-solid','!border-accent')"
                                                    x-on:drop.prevent="$el.classList.remove('!bg-[#eef2ff]','!border-solid','!border-accent'); $wire.dropOnSlot({{ $machineId }}, '{{ $date }}', {{ $shift->shift_id }}, JSON.parse($event.dataTransfer.getData('text/plain')))"
                                                >
                                                    @foreach ($occupants as $employeeId)
                                                        @php $cls = $this->chipClass($employeeId, $date, $shift->shift_id, $machineId); @endphp
                                                        <div wire:key="chip-{{ $slotKey }}-{{ $employeeId }}"
                                                            draggable="true"
                                                            x-on:dragstart="$event.dataTransfer.setData('text/plain', JSON.stringify({employeeId: {{ $employeeId }}, fromKey: {machineId: {{ $machineId }}, date: '{{ $date }}', shiftId: {{ $shift->shift_id }}}}))"
                                                            class="flex items-center gap-1 px-2 py-1 rounded text-[11px] font-semibold cursor-grab {{ $cls === 'busy-badge' ? 'bg-[#ffe8e8] text-[#ee5d50]' : ($cls === 'busy-badge-soft' ? 'bg-[#fff8e7] text-[#ffb800]' : 'bg-[#f4f7fe] text-[#4318ff]') }}">
                                                            <span class="flex-1 truncate">{{ $this->employeeNames[$employeeId] ?? '?' }}</span>
                                                            <button type="button" wire:click="toggleOt({{ $machineId }}, '{{ $date }}', {{ $shift->shift_id }}, {{ $employeeId }})"
                                                                title="Tandai lembur" class="w-4 h-4 rounded text-[9px] font-extrabold flex items-center justify-center shrink-0 {{ ($otAssign["{$slotKey}|{$employeeId}"] ?? false) ? 'bg-[#ee5d50] text-white' : 'bg-[#c7cfda] text-white' }}">L</button>
                                                            <button type="button" wire:click="removeAssignment({{ $machineId }}, '{{ $date }}', {{ $shift->shift_id }}, {{ $employeeId }})" title="Lepas" class="text-[11px] font-bold opacity-60 hover:opacity-100 shrink-0">✕</button>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ $this->days->count() + 1 }}" class="text-center py-6 text-muted text-xs">Belum ada shift aktif untuk Offset Printing.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 bg-[#f8fafc] border border-dashed border-[#c7cfda] rounded-lg p-3.5"
                        x-data
                        x-on:dragover.prevent="$el.classList.add('!bg-[#e2f4e6]','!border-[#1e7d3a]')"
                        x-on:dragleave="$el.classList.remove('!bg-[#e2f4e6]','!border-[#1e7d3a]')"
                        x-on:drop.prevent="$el.classList.remove('!bg-[#e2f4e6]','!border-[#1e7d3a]'); $wire.dropOnPool(JSON.parse($event.dataTransfer.getData('text/plain')))"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-[11px] font-bold text-navy uppercase">Pool Pegawai (Tersedia untuk Dialokasikan)</div>
                            <input type="text" wire:model.live.debounce.300ms="poolSearch" placeholder="🔍 Cari nama pegawai…" class="px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs w-[220px]">
                        </div>
                        <div class="flex gap-2 flex-wrap min-h-[38px]">
                            @forelse ($this->pool as $emp)
                                <div draggable="true"
                                    x-on:dragstart="$event.dataTransfer.setData('text/plain', JSON.stringify({employeeId: {{ $emp->employee_id }}}))"
                                    class="px-2.5 py-1.5 rounded-md text-[11px] font-semibold cursor-grab bg-[#f4f7fe] text-[#4318ff]">{{ $emp->full_name }}</div>
                            @empty
                                <div class="text-xs text-muted">Tidak ada pegawai di divisi ini.</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="flex gap-4 flex-wrap mt-3 text-[11px] text-muted">
                        <span>🟦 <b>Normal:</b> tidak ada bentrok</span>
                        <span>🟨 <b>Oranye:</b> dobel shift hari yang sama (soft, boleh lanjut)</span>
                        <span>🟥 <b>Merah:</b> 2 mesin berbeda, shift+tanggal sama (hard, wajib diperbaiki sebelum Confirm)</span>
                        <span><span class="inline-block w-3.5 h-3.5 rounded bg-[#ee5d50] text-white text-center leading-[14px] font-extrabold text-[9px]">L</span> = ditandai lembur</span>
                    </div>
                @endif
            </div>
        @else
            <div class="bg-white border border-[#d9dfe8] rounded-[10px] p-4">
                <div class="flex items-center gap-2 flex-wrap mb-3">
                    <span class="text-[10px] font-bold px-2.5 py-1 rounded-md uppercase bg-[#f4f7fe] text-[#4318ff]">HR Mode</span>
                    <select wire:model.live="hrDivisionId" class="px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                        @foreach ($this->hrDivisions as $d)
                            <option value="{{ $d->division_id }}">{{ $d->division_name }}</option>
                        @endforeach
                    </select>
                    <span class="text-xs text-muted">Klik sel untuk menyiklus shift ({{ $this->hrShiftOptions->pluck('shift_name')->implode(' → ') ?: 'belum ada shift aktif' }} → kosong). Tersimpan langsung, tanpa Draft/Confirm.</span>
                </div>

                <div class="overflow-x-auto border border-[#e9edf7] rounded-lg">
                    <table class="w-full border-collapse text-xs" style="table-layout: fixed;">
                        <thead>
                            <tr>
                                <th class="text-left px-2 py-2 bg-[#f8fafc] text-navy font-bold border border-[#e9edf7] w-[180px]">Nama Pegawai</th>
                                @foreach ($this->days as $day)
                                    <th class="px-2 py-2 bg-[#f8fafc] text-navy font-bold border border-[#e9edf7]">{{ $day->translatedFormat('D d/m') }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->hrEmployees as $emp)
                                <tr wire:key="hr-emp-{{ $emp->employee_id }}">
                                    <td class="px-2 py-2 border border-[#e9edf7] font-semibold text-navy">{{ $emp->full_name }}</td>
                                    @foreach ($this->days as $day)
                                        @php
                                            $date = $day->toDateString();
                                            $row = $this->hrAssignments->get($emp->employee_id.'|'.$date);
                                        @endphp
                                        <td class="p-1 border border-[#e9edf7] text-center">
                                            <button type="button" wire:click="cycleHrCell({{ $emp->employee_id }}, '{{ $date }}')"
                                                class="w-full min-h-[30px] rounded-md text-[11px] font-bold cursor-pointer {{ $row ? 'bg-[#f4f7fe] text-[#4318ff]' : 'text-muted hover:bg-[#f8fafc]' }}">
                                                {{ $row?->shift?->shift_name ? \Illuminate\Support\Str::limit($row->shift->shift_name, 14) : '–' }}
                                            </button>
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ $this->days->count() + 1 }}" class="text-center py-6 text-muted text-xs">Belum ada pegawai di divisi ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
