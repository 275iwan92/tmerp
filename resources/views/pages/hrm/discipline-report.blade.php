<?php

use App\Models\Master\AttDailyStatus;
use App\Models\Master\EmpShiftDetail;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Laporan Kedisiplinan Harian -- interface diadopsi dari docs/payroll_timeattendance/
// mockup_08_daily_discipline_report.html (4 stat widget + 2 tabel: Terlambat,
// Tidak Masuk). Data dari att_daily_status yang sudah dihitung
// AttendanceCalculationService, bukan tabel/summary baru.
new #[Title('Laporan Kedisiplinan Harian — TELASIH MAKMUR ERP')] class extends Component
{
    private const STATUS_LABELS = [
        'T-5' => 'T-5 (< 5 min)', 'T+5' => 'T+5 (6-15 min)', 'T+15' => 'T+15 (> 15 min)',
        'PAT-5' => 'PAT-5', 'PAT+5' => 'PAT+5',
        'A' => 'A (Absent)', 'S' => 'S (Sick Approved)', 'I' => 'I (Permission)', 'IS' => 'IS (Permission Approved)',
        'C' => 'C (Leave)', 'CL' => 'CL (Maternity Leave)',
    ];

    public string $date = '';

    public function mount(): void
    {
        $this->date = now()->toDateString();
    }

    #[Computed]
    public function rows()
    {
        return AttDailyStatus::with('employee.position.department')
            ->whereDate('attendance_date', $this->date)
            ->get();
    }

    #[Computed]
    public function stats(): array
    {
        $rows = $this->rows;

        return [
            'hadir' => $rows->where('status_code', 'H')->count(),
            'terlambat' => $rows->whereIn('status_code', ['T-5', 'T+5', 'T+15', 'PAT-5', 'PAT+5'])->count(),
            'alpha' => $rows->where('status_code', 'A')->count(),
            'izin_sakit_cuti' => $rows->whereIn('status_code', ['I', 'IS', 'S', 'C', 'CL'])->count(),
        ];
    }

    #[Computed]
    public function lateRows()
    {
        return $this->rows->whereIn('status_code', ['T-5', 'T+5', 'T+15', 'PAT-5', 'PAT+5'])->sortByDesc('minutes_late');
    }

    #[Computed]
    public function absentRows()
    {
        return $this->rows->whereIn('status_code', ['A', 'S', 'I', 'IS', 'C', 'CL']);
    }

    public function shiftStartFor(int $employeeId): ?string
    {
        $row = $this->rows->firstWhere('employee_id', $employeeId);

        return $row?->shift_start?->format('H:i');
    }

    public function statusLabel(string $code): string
    {
        return isset(self::STATUS_LABELS[$code]) ? t(self::STATUS_LABELS[$code]) : $code;
    }

    public function absentBadgeClass(string $code): string
    {
        return match ($code) {
            'A' => 'bg-[#fee2e2] text-[#991b1b]',
            'S' => 'bg-[#f3e8ff] text-[#6b21a8]',
            'I', 'IS' => 'bg-[#dbeafe] text-[#1e40af]',
            default => 'bg-[#e2e8f0] text-[#334155]',
        };
    }
};
?>

<div>
    <div class="max-w-[1300px] mx-auto my-5 px-4">

        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 p-3 flex justify-between items-center flex-wrap gap-3">
            <div class="max-w-[220px]">
                <x-form.date-picker :label="t('Report Date')" wire:model.live="date" name="date" />
            </div>
            <button type="button" onclick="window.print()" class="bg-navy text-white text-xs px-3 py-2 rounded font-semibold">🖨️ {{ t('Print Report') }}</button>
        </div>

        <x-grid.stat-row :count="4" class="mb-4">
            <x-grid.stat-box tone="active" :label="t('Present On Time')" :value="$this->stats['hadir'].' '.t('Employee')" />
            <x-grid.stat-box tone="warn" :label="t('Late')" :value="$this->stats['terlambat'].' '.t('Employee')" />
            <x-grid.stat-box tone="critical" :label="t('Absent (Alpha)')" :value="$this->stats['alpha'].' '.t('Employee')" />
            <x-grid.stat-box tone="extra" :label="t('Permission/Sick/Leave')" :value="$this->stats['izin_sakit_cuti'].' '.t('Employee')" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 p-4">
            <h2 class="text-xs font-bold text-[#92400e] mb-3">⚠️ {{ t('Late Employees List') }} ({{ \Carbon\Carbon::parse($date)->translatedFormat('d F Y') }})</h2>
            <x-grid.table-shell>
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr>
                        <th class="p-2 border-b border-[#eef1f5] bg-[#fef3c7] uppercase text-[11px] text-[#92400e]">{{ t('Code') }}</th>
                        <th class="p-2 border-b border-[#eef1f5] bg-[#fef3c7] uppercase text-[11px] text-[#92400e]">{{ t('Name') }}</th>
                        <th class="p-2 border-b border-[#eef1f5] bg-[#fef3c7] uppercase text-[11px] text-[#92400e]">{{ t('Shift Time') }}</th>
                        <th class="p-2 border-b border-[#eef1f5] bg-[#fef3c7] uppercase text-[11px] text-[#92400e]">{{ t('Clock-In Time') }}</th>
                        <th class="p-2 border-b border-[#eef1f5] bg-[#fef3c7] uppercase text-[11px] text-[#92400e]">{{ t('Late Duration') }}</th>
                        <th class="p-2 border-b border-[#eef1f5] bg-[#fef3c7] uppercase text-[11px] text-[#92400e]">{{ t('Category') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->lateRows as $r)
                        <tr class="border-b border-[#eef1f5]" wire:key="late-{{ $r->id_daily }}">
                            <td class="p-2 font-mono">{{ $r->employee?->employee_code }}</td>
                            <td class="p-2 font-medium">{{ $r->employee?->full_name }}</td>
                            <td class="p-2 font-mono">{{ $r->shift_start?->format('H:i') ?? '—' }}</td>
                            <td class="p-2 font-mono text-[#991b1b]">{{ $r->finger_in?->format('H:i:s') ?? '—' }}</td>
                            <td class="p-2 font-bold text-[#991b1b]">{{ $r->minutes_late }} {{ t('Minutes') }}</td>
                            <td class="p-2"><span class="bg-[#fee2e2] text-[#991b1b] px-2 py-0.5 rounded font-bold text-[10px]">{{ $this->statusLabel($r->status_code) }}</span></td>
                        </tr>
                    @empty
                        <x-grid.empty-row :colspan="6" :message="t('No late employees on this date.')" />
                    @endforelse
                </tbody>
            </table>
            </x-grid.table-shell>
        </div>

        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 p-4">
            <h2 class="text-xs font-bold text-[#991b1b] mb-3">🚫 {{ t('Employees Absent from Work (Alpha/Sick/Permission/Leave)') }}</h2>
            <x-grid.table-shell>
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr>
                        <th class="p-2 border-b border-[#eef1f5] bg-[#fee2e2] uppercase text-[11px] text-[#991b1b]">{{ t('Code') }}</th>
                        <th class="p-2 border-b border-[#eef1f5] bg-[#fee2e2] uppercase text-[11px] text-[#991b1b]">{{ t('Name') }}</th>
                        <th class="p-2 border-b border-[#eef1f5] bg-[#fee2e2] uppercase text-[11px] text-[#991b1b]">{{ t('Division') }}</th>
                        <th class="p-2 border-b border-[#eef1f5] bg-[#fee2e2] uppercase text-[11px] text-[#991b1b]">{{ t('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->absentRows as $r)
                        <tr class="border-b border-[#eef1f5]" wire:key="absent-{{ $r->id_daily }}">
                            <td class="p-2 font-mono">{{ $r->employee?->employee_code }}</td>
                            <td class="p-2 font-medium">{{ $r->employee?->full_name }}</td>
                            <td class="p-2">{{ $r->employee?->position?->department?->department_name ?? '—' }}</td>
                            <td class="p-2"><span class="px-2 py-0.5 rounded font-bold text-[10px] {{ $this->absentBadgeClass($r->status_code) }}">{{ $this->statusLabel($r->status_code) }}</span></td>
                        </tr>
                    @empty
                        <x-grid.empty-row :colspan="4" :message="t('No absent employees on this date.')" />
                    @endforelse
                </tbody>
            </table>
            </x-grid.table-shell>
        </div>
    </div>
</div>
