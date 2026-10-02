<?php

use App\Models\Master\AttFingerRaw;
use App\Models\Master\AttMobileGpsLog;
use App\Services\Hrm\AttendanceCalculationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\OpensFromQuery;
use Livewire\Component;

// Approval Lokasi GPS (pelengkap Modul 09) -- interface diadopsi dari
// docs/payroll_timeattendance/mockup_11_hr_approval_workflow.html (khusus
// bagian approval presensi GPS luar radius). Persetujuan HR di sini yang
// BARU menuliskan att_finger_raw + memicu AttendanceCalculationService --
// sebelum disetujui, presensi TIDAK tercatat sama sekali (Tier1 doc:
// "PENDING_HR_LOCATION_APPROVAL").
new #[Title('GPS Location Approval — TELASIH MAKMUR ERP')] class extends Component
{
    use OpensFromQuery;

    public bool $showRejectModal = false;

    public ?int $rejectingId = null;

    public string $rejectReason = '';

    #[Computed]
    public function logs()
    {
        return AttMobileGpsLog::with('employee')
            ->when($this->focusId, fn ($q) => $q->whereKey($this->focusId))
            ->orderByRaw("FIELD(hr_approval_status, 'PENDING', 'APPROVED', 'REJECTED', 'AUTO_APPROVED')")
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    #[Computed]
    public function pendingCount(): int
    {
        return AttMobileGpsLog::where('hr_approval_status', 'PENDING')->count();
    }

    public function approve(int $logId): void
    {
        $log = AttMobileGpsLog::findOrFail($logId);
        if ($log->hr_approval_status !== 'PENDING') {
            return;
        }

        $log->update(['hr_approval_status' => 'APPROVED', 'hr_approved_by' => Auth::user()->login, 'hr_approved_at' => now()]);

        AttFingerRaw::create([
            'id_import' => null,
            'employee_id' => $log->employee_id,
            'scan_datetime' => $log->created_at,
            'scan_type' => $log->clock_type,
            'raw_device_code' => 'MOBILE-GPS',
        ]);

        $date = $log->created_at->copy()->startOfDay();
        app(AttendanceCalculationService::class)->calculateForDate($log->employee_id, $date, $date->copy()->startOfWeek(Carbon::MONDAY));

        unset($this->logs, $this->pendingCount);
    }

    public function openReject(int $logId): void
    {
        $this->rejectingId = $logId;
        $this->rejectReason = '';
        $this->showRejectModal = true;
    }

    public function reject(): void
    {
        $data = $this->validate(['rejectReason' => ['required', 'string', 'max:255']]);

        AttMobileGpsLog::where('id_gps_log', $this->rejectingId)->where('hr_approval_status', 'PENDING')->update([
            'hr_approval_status' => 'REJECTED',
            'hr_approved_by' => Auth::user()->login,
            'hr_approved_at' => now(),
            'hr_approval_note' => $data['rejectReason'],
        ]);

        $this->showRejectModal = false;
        unset($this->logs, $this->pendingCount);
    }

    public function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'PENDING' => 'bg-[#fef3c7] text-[#92400e]',
            'APPROVED', 'AUTO_APPROVED' => 'bg-[#dcfce7] text-[#166534]',
            'REJECTED' => 'bg-[#fee2e2] text-[#991b1b]',
            default => 'bg-[#e2e8f0] text-[#334155]',
        };
    }
};
?>

<div>
    <div class="max-w-[1200px] mx-auto my-5 px-4">

        <x-grid.stat-row :count="1">
            <x-grid.stat-box tone="warn" :label="t('Awaiting Approval')" :value="number_format($this->pendingCount)" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <x-grid.table-shell>
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Employee') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Date/Time') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Type') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Distance') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Status') }}</th>
                            <th class="px-3 py-2 bg-[#fafbfd] border-b border-[#eef1f5] sticky top-0 z-[1]"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->logs as $log)
                            <tr class="border-b border-[#eef1f5]" wire:key="gps-{{ $log->id_gps_log }}">
                                <x-grid.identity-cell :id="$log->employee_id" :name="$log->employee?->full_name ?? '—'" :code="$log->employee?->employee_code" />
                                <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                                <td class="px-3 py-2">{{ $log->clock_type === 'IN' ? t('Clock In') : t('Clock Out') }}</td>
                                <td class="px-3 py-2 font-mono {{ $log->geofence_status === 'OUT_OF_RADIUS' ? 'text-[#c0392b] font-bold' : '' }}">{{ $log->distance_meters }} m</td>
                                <td class="px-3 py-2"><span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->statusBadgeClass($log->hr_approval_status) }}">{{ $log->hr_approval_status }}</span></td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    @if ($log->hr_approval_status === 'PENDING')
                                        <x-grid.action-icon icon="fa-check" :title="t('Approve')" action="approve({{ $log->id_gps_log }})" :confirm="t('Approve this attendance even though it is outside the radius?')" variant="success" />
                                        <x-grid.action-icon icon="fa-xmark" :title="t('Reject')" action="openReject({{ $log->id_gps_log }})" variant="danger" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="6" :message="t('No GPS attendance logs yet.')" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>

    @if ($showRejectModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showRejectModal', false)">
            <div class="bg-white rounded-xl w-[420px] max-w-[92vw] shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold">{{ t('Reject GPS Attendance') }}</div>
                <form wire:submit="reject" class="p-4 flex flex-col gap-3">
                    <x-form.textarea :label="t('Rejection Reason').' *'" wire:model="rejectReason" rows="3" name="rejectReason" />
                    <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                        <button type="button" wire:click="$set('showRejectModal', false)" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">{{ t('Cancel') }}</button>
                        <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#c0392b] text-white cursor-pointer">{{ t('Reject') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
