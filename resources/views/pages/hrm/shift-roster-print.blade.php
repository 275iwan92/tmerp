<?php

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpRosterSchedule;
use App\Models\Master\EmpRosterScheduleDepartment;
use App\Models\Master\EmpShift;
use App\Models\Master\EmpShiftRoster;
use App\Models\Master\EmpWorkCenter;
use App\Models\Master\OrgCompany;
use App\Models\Master\OrgDepartment;
use App\Models\Sys\SysGridPreference;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Cetakan Roster Shift Mingguan + Master Definisi Shift (mockup_roster_shift.html
// bagian printSheet()/printShiftDefs(), A4 landscape). Kerangka pakai
// components/print/* sesuai docs/printnotastandard.md, bukan <style> sendiri.
new #[Title('Cetak Roster Shift — TELASIH MAKMUR ERP')] class extends Component
{
    public string $mode = 'roster'; // roster | shift | formasi

    public ?int $scheduleId = null;

    public int $weekOffset = 0;

    public function mount(): void
    {
        $this->mode = in_array(request('mode'), ['roster', 'shift', 'formasi'], true) ? request('mode') : 'roster';
        $this->weekOffset = (int) request('week', 0);
        $this->scheduleId = request('schedule')
            ? (int) request('schedule')
            : EmpRosterSchedule::where('activate', true)->orderBy('schedule_id')->value('schedule_id');
    }

    #[Computed]
    public function company(): ?OrgCompany
    {
        return OrgCompany::where('activate', true)->first();
    }

    #[Computed]
    public function schedule(): ?EmpRosterSchedule
    {
        return $this->scheduleId ? EmpRosterSchedule::find($this->scheduleId) : null;
    }

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

    // Ikut setelan hide/show shift milik user yang mencetak (permintaan user
    // 2026-09-12). Sumbernya sama dengan layar roster: preferensi per user di
    // tm2sys.sys_grid_preference, jadi cetakan tidak mungkin melenceng dari
    // apa yang dilihat user di papan.
    #[Computed]
    public function hiddenShifts(): array
    {
        return SysGridPreference::where('user_login', Auth::user()->login)
            ->where('grid_key', 'hrm.shift-roster.hidden-shifts')
            ->value('visible_columns') ?? [];
    }

    #[Computed]
    public function shifts()
    {
        $hidden = collect($this->hiddenShifts)->map(fn ($v) => (int) $v)->all();

        return EmpShift::with('details')
            ->where('activate', true)
            ->when($hidden, fn ($q) => $q->whereNotIn('shift_id', $hidden))
            ->orderBy('shift_code')
            ->orderBy('shift_name')
            ->get();
    }

    #[Computed]
    public function departments()
    {
        $ids = EmpRosterScheduleDepartment::where('schedule_id', $this->scheduleId)->pluck('department_id');

        return OrgDepartment::whereIn('department_id', $ids)->orderBy('department_name')->get();
    }

    // work_center_id => shift_id => [EmpEmployee, ...]
    #[Computed]
    public function placements(): array
    {
        $rows = EmpShiftRoster::query()
            ->where('schedule_id', $this->scheduleId)
            ->whereBetween('roster_date', [$this->weekStart->toDateString(), $this->weekEnd->toDateString()])
            ->get()
            ->unique(fn ($row) => $row->employee_id.'|'.$row->work_center_id.'|'.$row->shift_id);

        $employees = EmpEmployee::with('position')
            ->whereIn('employee_id', $rows->pluck('employee_id')->unique()->all() ?: [0])
            ->get()
            ->keyBy('employee_id');

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row->work_center_id][(int) $row->shift_id][] = $employees[$row->employee_id] ?? null;
        }

        return $out;
    }

    #[Computed]
    public function workCenters()
    {
        return EmpWorkCenter::whereIn('work_center_id', array_keys($this->placements) ?: [0])
            ->orderBy('sort_order')
            ->orderBy('work_center_name')
            ->get();
    }

    // Bentuk sheet formasi HR (lihat RosterFormationService) -- 1 baris per unit+shift.
    #[Computed]
    public function formasi(): array
    {
        return $this->scheduleId
            ? app(\App\Services\Hrm\RosterFormationService::class)->matrix($this->scheduleId, $this->weekStart, false)
            : [];
    }

    public function totalAssigned(): int
    {
        return collect($this->placements)->flatten(1)->flatten()->filter()->unique('employee_id')->count();
    }
};
?>

@php
    $dayLabels = collect(\App\Models\Master\EmpShift::DAY_ORDER)->map(fn ($d) => \App\Models\Master\EmpShift::DAY_LABELS[$d])->all();
@endphp

<x-print.document :theme="['accent' => '#2c3e69', 'accent-soft' => '#eaf0ff']"
    info="{{ $mode === 'shift' ? 'Master Definisi Shift' : ($mode === 'formasi' ? 'Formasi Minggu ' : 'Roster Minggu ').$this->weekStart->isoWeek() }}">

    <x-print.sheet>
        <x-print.header :company="$this->company"
            doc-title="{{ match ($mode) { 'shift' => 'MASTER DEFINISI SHIFT', 'formasi' => 'FORMASI MINGGU KE-'.$this->weekStart->isoWeek(), default => 'ROSTER SHIFT MINGGUAN' } }}"
            doc-no="{{ match ($mode) { 'shift' => 'FRM-HR-SHF-01 Rev.0', 'formasi' => 'FRM-HR-FRM-01 Rev.0', default => 'FRM-HR-ROS-01 Rev.0' } }}">
            @if ($mode !== 'shift')
                <div>{{ $this->schedule?->schedule_name }}</div>
                <div>Minggu {{ $this->weekStart->isoWeek() }} · {{ $this->weekStart->translatedFormat('d M Y') }} – {{ $this->weekEnd->translatedFormat('d M Y') }}</div>
            @else
                <div>Berlaku mulai {{ now()->translatedFormat('d F Y') }}</div>
            @endif
        </x-print.header>

        @if ($mode === 'formasi')
            <x-print.section number="1" title="Formasi per Posisi, Jam Kerja &amp; Tanggal">
                <table class="w-full border-collapse text-[9.5px]">
                    <thead>
                        <tr>
                            <th class="border border-[#999] px-1 py-1 bg-[#eceff5] w-[24px]">NO</th>
                            <th class="border border-[#999] px-1 py-1 bg-[#eceff5] w-[90px] text-left">POSISI</th>
                            <th class="border border-[#999] px-1 py-1 bg-[#eceff5] w-[90px] text-left">JAM KERJA</th>
                            @foreach ($this->weekStart->copy()->daysUntil($this->weekEnd->copy()->subDay()) as $d)
                                <th class="border border-[#999] px-1 py-1 bg-[#eceff5]">{{ $d->format('d/m/Y') }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->formasi as $i => $row)
                            <tr>
                                @if ($row['first'])
                                    <td rowspan="{{ $row['span'] }}" class="border border-[#999] px-1 py-0.5 text-center">{{ collect($this->formasi)->take($i + 1)->where('first', true)->count() }}</td>
                                    <td rowspan="{{ $row['span'] }}" class="border border-[#999] px-1 py-0.5 font-bold uppercase">{{ $row['wc']->work_center_name }}</td>
                                @endif
                                <td class="border border-[#999] px-1 py-0.5 uppercase">{{ $row['shift']?->shift_name ?? '—' }}</td>
                                @foreach ($this->weekStart->copy()->daysUntil($this->weekEnd->copy()->subDay()) as $d)
                                    <td class="border border-[#999] px-1 py-0.5 text-center uppercase">{{ collect($row['cells'][$d->toDateString()] ?? [])->pluck('label')->implode(', ') ?: '' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-print.section>
        @endif

        @if ($mode === 'roster')
            <x-print.parties columns="1fr 1fr">
                <x-print.party label="CAKUPAN JADWAL">
                    <div><b>{{ $this->schedule?->schedule_name ?? '—' }}</b></div>
                    <div>{{ $this->departments->pluck('department_name')->implode(', ') ?: '—' }}</div>
                </x-print.party>
                <x-print.party label="RINGKASAN">
                    <div>Total terjadwal: <b>{{ $this->totalAssigned() }} orang</b></div>
                    <div>Unit kerja terisi: <b>{{ $this->workCenters->count() }}</b></div>
                    <div>Shift aktif: <b>{{ $this->shifts->count() }}</b></div>
                </x-print.party>
            </x-print.parties>
        @endif

        @if ($mode !== 'formasi')
        <x-print.section number="1" title="Definisi Jam Shift yang Berlaku">
            <table class="w-full border-collapse text-[10.5px]">
                <thead>
                    <tr>
                        <th class="border border-[#999] px-1.5 py-1 text-left bg-[#eceff5] w-[150px] uppercase">Shift</th>
                        @foreach ($dayLabels as $label)
                            <th class="border border-[#999] px-1.5 py-1 text-center bg-[#eceff5] uppercase">{{ $label }}</th>
                        @endforeach
                        <th class="border border-[#999] px-1.5 py-1 text-center bg-[#eceff5] w-[74px] uppercase">Jam/mg</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->shifts as $shift)
                        @php $pattern = $shift->dayPattern(); @endphp
                        <tr>
                            <td class="border border-[#999] px-1.5 py-1 font-semibold">
                                {{ $shift->shift_name }}
                                <small class="block font-normal font-mono text-[9px] text-[#555]">{{ $shift->shift_code }}</small>
                            </td>
                            @foreach ($pattern as $day)
                                <td class="border border-[#999] px-1.5 py-1 text-center font-mono text-[9.5px] whitespace-nowrap">
                                    @if ($day['on'])
                                        {{ $day['in'] }}<br>{{ $day['out'] }}{{ \App\Models\Master\EmpShift::isNight($day) ? ' +1' : '' }}
                                    @else
                                        —
                                    @endif
                                </td>
                            @endforeach
                            <td class="border border-[#999] px-1.5 py-1 text-center font-mono text-[9.5px]">{{ \App\Models\Master\EmpShift::formatMinutes($shift->weekMinutes()) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="text-[9.5px] text-[#555] mt-1.5 mb-0">
                Angka pada kolom hari = jam masuk (atas) dan jam pulang (bawah). Tanda "+1" berarti jam pulang jatuh di hari berikutnya. Jam/minggu sudah dikurangi waktu istirahat.
                @if (count($this->hiddenShifts))
                    <br><b>Catatan:</b> {{ count($this->hiddenShifts) }} shift disembunyikan dari dokumen ini sesuai setelan tampilan pencetak.
                @endif
            </p>
        </x-print.section>
        @endif

        @if ($mode === 'roster')
            <x-print.section number="2" title="Penempatan Pegawai per Unit Kerja">
                @forelse ($this->workCenters as $center)
                    <h4 class="text-[12px] font-bold uppercase tracking-wide mt-3 mb-1 pb-0.5 border-b border-[#999]">{{ $center->work_center_name }}</h4>
                    <table class="w-full border-collapse text-[10.5px] mb-1.5">
                        <thead>
                            <tr>
                                <th class="border border-[#999] px-1.5 py-1 text-left bg-[#eceff5] w-[150px] uppercase">Shift &amp; pola jam</th>
                                <th class="border border-[#999] px-1.5 py-1 text-center bg-[#eceff5] w-[40px] uppercase">Jml</th>
                                <th class="border border-[#999] px-1.5 py-1 text-left bg-[#eceff5] uppercase">Nama pegawai</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->shifts as $shift)
                                @php $members = collect($this->placements[$center->work_center_id][$shift->shift_id] ?? [])->filter(); @endphp
                                @if ($members->isNotEmpty())
                                    <tr>
                                        <td class="border border-[#999] px-1.5 py-1 font-semibold align-top">
                                            {{ $shift->shift_name }}
                                            <small class="block font-normal font-mono text-[9px] text-[#555] leading-snug">
                                                @foreach ($shift->summaryLines() as $line)
                                                    {{ $line }}<br>
                                                @endforeach
                                            </small>
                                        </td>
                                        <td class="border border-[#999] px-1.5 py-1 text-center font-mono align-top">{{ $members->count() }}</td>
                                        <td class="border border-[#999] px-1.5 py-1 align-top">
                                            <div class="flex flex-wrap gap-x-2.5 gap-y-0.5">
                                                @foreach ($members as $employee)
                                                    <span class="whitespace-nowrap">{{ $employee->full_name }}
                                                        <i class="not-italic font-mono text-[9.5px] text-[#666]">({{ $employee->employee_code }} · {{ $employee->position?->position_name ?? '—' }})</i>
                                                    </span>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                @empty
                    <p class="text-[11px] text-[#777] m-0">Jadwal ini belum memiliki penempatan pada minggu tersebut.</p>
                @endforelse
            </x-print.section>
        @endif

        <x-print.signatures :columns="'1fr 1fr 1fr'">
            <x-print.signature role="Dibuat oleh" name="( PPIC / HR )" />
            <x-print.signature role="Diperiksa oleh" name="( Kepala Produksi )" />
            <x-print.signature role="Disetujui oleh" name="( General Manager )" />
        </x-print.signatures>

        <x-print.footer :page="$mode === 'shift' ? 'Master Shift' : 'Roster Minggu '.$this->weekStart->isoWeek()">
            Dicetak {{ now()->translatedFormat('d F Y H:i') }} WIB · Jam kerja mengikuti Master Shift versi berlaku; perubahan di luar jadwal ini harus melalui revisi tertulis.
        </x-print.footer>
    </x-print.sheet>
</x-print.document>
