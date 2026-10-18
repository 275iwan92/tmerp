<?php

/*
 * REMARK
 * Tujuan       : Komponen v1 untuk layar "Attendance" (dibuka dari grid v2 lewat Open / New). Teks tampilan (label, kolom, tab, tombol, pesan) English sesuai keputusan Jo 2026-10-18; nilai tersimpan dan kunci data tidak diubah.
 * Sumber hulu  : Tabel dan service yang dibaca komponen ini (lihat query di file); tidak ada logika hulu baru.
 * Input        : Isian form dan filter layar.
 * Output       : Simpan ke tabel modul terkait lewat service yang sama seperti sebelumnya.
 * Dipakai oleh : Grid v2 modul terkait (v1Route).
 * Status       : Aktif; teks tampilan English (Jo 2026-10-18).
 */


use App\Models\Master\AttDailyStatus;
use App\Models\Master\EmpEmployee;
use App\Services\Hrm\AttendanceCalculationService;
use App\Support\Localization\Translator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\OpensFromQuery;
use Livewire\Component;

// Grid Presensi Harian -- docs/attendancepayroll_tier2.md/tier3.md. Kolom &
// pola override diambil dari interface docs/payroll_timeattendance/
// tm2hrm_laravel_php83.zip (AttendanceDailyResource, mobile-gps-clock-in
// mockup) SESUAI PERMINTAAN USER 2026-09-07 "ambil interface nya saja" --
// logika/mesin di baliknya TETAP pakai AttendanceCalculationService kita
// sendiri (jauh lebih detail: T-5/T+5/PAT/KJK/F-FF/dst, bukan cuma H/T/PC/A
// generik di paket referensi).
new #[Title('Daily Attendance — TELASIH MAKMUR ERP')] class extends Component
{
    use OpensFromQuery;

    protected function openFromQuery(int $id): void
    {
        $date = \App\Models\Master\AttDailyStatus::whereKey($id)->value('attendance_date');
        if ($date) {
            $this->dateFrom = $this->dateTo = substr((string) $date, 0, 10);
            $this->openOverride($id);
        }
    }

    private const STATUS_LABEL_DEFAULTS = [
        'H' => 'Present', 'T-5' => 'Late <5min', 'T+5' => 'Late 6-15min', 'T+15' => 'Late >15min',
        'PA' => 'Early Leave', 'PAT-5' => 'Early Leave + Late<5', 'PAT+5' => 'Early Leave + Late',
        'KJK' => 'Out During Work Hours', 'I' => 'Permission', 'IS' => 'Permission Approved', 'S' => 'Sick',
        'C' => 'Leave', 'CL' => 'Maternity Leave', 'A' => 'Absent', 'F' => 'No Fingerprint 1x', 'FF' => 'No Fingerprint 2x',
    ];

    private const STATUS_LABEL_KEYS = [
        'H' => 'hrm_att_status_h', 'T-5' => 'hrm_att_status_t5', 'T+5' => 'hrm_att_status_t6_15', 'T+15' => 'hrm_att_status_t15',
        'PA' => 'hrm_att_status_pa', 'PAT-5' => 'hrm_att_status_pat5', 'PAT+5' => 'hrm_att_status_pat_plus',
        'KJK' => 'hrm_att_status_kjk', 'I' => 'hrm_att_status_i', 'IS' => 'hrm_att_status_is', 'S' => 'hrm_att_status_s',
        'C' => 'hrm_att_status_c', 'CL' => 'hrm_att_status_cl', 'A' => 'hrm_att_status_a', 'F' => 'hrm_att_status_f', 'FF' => 'hrm_att_status_ff',
    ];

    public function statusLabel(string $code): string
    {
        $key = self::STATUS_LABEL_KEYS[$code] ?? null;

        return $key ? Translator::t($key, self::STATUS_LABEL_DEFAULTS[$code]) : $code;
    }

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $search = '';

    public bool $showOverrideModal = false;

    public ?int $overridingId = null;

    public string $overrideStatus = '';

    public string $overrideReason = '';

    public function mount(): void
    {
        $this->dateFrom = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->dateTo = now()->toDateString();
    }

    #[Computed]
    public function rows()
    {
        return AttDailyStatus::with('employee')
            ->whereBetween('attendance_date', [$this->dateFrom, $this->dateTo])
            ->when($this->search, fn ($q) => $q->whereHas('employee', fn ($q2) => $q2
                ->where('full_name', 'like', "%{$this->search}%")
                ->orWhere('employee_code', 'like', "%{$this->search}%")))
            ->orderByDesc('attendance_date')
            ->orderBy('employee_id')
            ->get();
    }

    #[Computed]
    public function stats(): array
    {
        $rows = $this->rows;

        return [
            'hadir' => $rows->where('status_code', 'H')->count(),
            'terlambat' => $rows->whereIn('status_code', ['T-5', 'T+5', 'T+15', 'PAT-5', 'PAT+5'])->count(),
            'tidak_masuk' => $rows->whereIn('status_code', ['A', 'I', 'S', 'C'])->count(),
            'lembur' => $rows->where('overtime_minutes', '>', 0)->count(),
        ];
    }

    public function statusOptions(): array
    {
        return collect(self::STATUS_LABEL_DEFAULTS)->mapWithKeys(fn ($default, $code) => [$code => $this->statusLabel($code)])->all();
    }

    // Hitung ulang seluruh pegawai aktif utk rentang tanggal terpilih --
    // membaca att_finger_raw/emp_leave_request/dst yang sudah ada (belum ada
    // layar upload Excel terpisah, itu iterasi berikutnya).
    public function recalculate(): void
    {
        $service = app(AttendanceCalculationService::class);
        $periodStart = Carbon::parse($this->dateFrom);
        $periodEnd = Carbon::parse($this->dateTo);

        EmpEmployee::where('activate', true)->pluck('employee_id')->each(function (int $employeeId) use ($service, $periodStart, $periodEnd) {
            $cursor = $periodStart->copy();
            while ($cursor->lte($periodEnd)) {
                $service->calculateForDate($employeeId, $cursor->copy(), $periodStart);
                $cursor->addDay();
            }
        });

        unset($this->rows, $this->stats);
        session()->flash('message', 'Attendance was recalculated successfully for the selected date range.');
    }

    public function openOverride(int $id): void
    {
        $row = AttDailyStatus::findOrFail($id);
        $this->overridingId = $id;
        $this->overrideStatus = $row->status_code;
        $this->overrideReason = '';
        $this->showOverrideModal = true;
    }

    // Tier 3 §3 att_daily_status.source/override_by/override_reason -- status
    // "tidak masuk" bisa berubah manual (mis. Alpha -> Izin/Cuti/Sakit) sesuai
    // alur yang ditulis user 2026-09-05, jejak audit wajib diisi.
    public function saveOverride(): void
    {
        $data = $this->validate([
            'overrideStatus' => ['required', 'string'],
            'overrideReason' => ['required', 'string', 'min:10'],
        ]);

        AttDailyStatus::findOrFail($this->overridingId)->update([
            'status_code' => $data['overrideStatus'],
            'source' => 'MANUAL_OVERRIDE',
            'override_by' => Auth::user()->login,
            'override_reason' => $data['overrideReason'],
        ]);

        $this->showOverrideModal = false;
        unset($this->rows, $this->stats);
    }

    public function statusBadgeClass(string $code): string
    {
        return match (true) {
            $code === 'H' => 'bg-[#dcfce7] text-[#166534]',
            in_array($code, ['T-5', 'T+5', 'T+15', 'PA', 'PAT-5', 'PAT+5', 'KJK'], true) => 'bg-[#fef3c7] text-[#92400e]',
            in_array($code, ['S', 'C', 'CL', 'I', 'IS'], true) => 'bg-[#dbeafe] text-[#1e40af]',
            in_array($code, ['A', 'F', 'FF'], true) => 'bg-[#fee2e2] text-[#991b1b]',
            default => 'bg-[#e2e8f0] text-[#334155]',
        };
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">

        <x-grid.stat-row :count="4">
            <x-grid.stat-box tone="active" label="{{ 'Present' }}" :value="number_format($this->stats['hadir'])" />
            <x-grid.stat-box tone="warn" label="{{ 'Late' }}" :value="number_format($this->stats['terlambat'])" />
            <x-grid.stat-box tone="critical" label="{{ 'Absent' }}" :value="number_format($this->stats['tidak_masuk'])" />
            <x-grid.stat-box tone="info" label="{{ 'Overtime' }}" :value="number_format($this->stats['lembur'])" />
        </x-grid.stat-row>

        @if (session('message'))
            <div class="mb-4 px-3 py-2 rounded-md bg-[#dcfce7] text-[#166534] text-xs font-semibold">{{ session('message') }}</div>
        @endif

        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 p-3 flex flex-wrap items-end gap-3">
            <x-form.date-picker label="{{ 'From Date' }}" wire:model.live="dateFrom" name="dateFrom" />
            <x-form.date-picker label="{{ 'To Date' }}" wire:model.live="dateTo" name="dateTo" />
            <div class="flex-1 min-w-[180px]">
                <x-form.input :case-toggle="false" label="{{ 'Search Employee Name/Code' }}" wire:model.live.debounce.400ms="search" name="search" placeholder="{{ 'Type a name or code...' }}" />
            </div>
            <button type="button" wire:click="recalculate" wire:loading.attr="disabled"
                class="px-4 py-2 rounded-md text-xs font-semibold bg-navy text-white cursor-pointer disabled:opacity-50">
                <span wire:loading.remove wire:target="recalculate">{{ 'Recalculate Attendance' }}</span>
                <span wire:loading wire:target="recalculate">{{ 'Processing...' }}</span>
            </button>
        </div>

        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <x-grid.table-shell>
                <table class="w-full text-xs border-collapse">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ 'Date' }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ 'Employee' }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ Translator::t('hrm_att_col_shift', 'Shift') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ 'Clock In' }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ 'Clock Out' }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ 'Late (Min)' }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ 'Overtime' }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ Translator::t('proc_col_status', 'Status') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ 'Source' }}</th>
                            <th class="px-3 py-2 bg-[#fafbfd] border-b border-[#eef1f5] sticky top-0 z-[1]"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rows as $r)
                            <tr class="border-b border-[#eef1f5] align-top" wire:key="ads-{{ $r->id_daily }}">
                                <td class="px-3 py-2 whitespace-nowrap">{{ $r->attendance_date->format('d M Y') }}</td>
                                <x-grid.identity-cell :id="$r->employee_id" :name="$r->employee?->full_name ?? '—'" :code="$r->employee?->employee_code" />
                                <td class="px-3 py-2 whitespace-nowrap text-[11px]">{{ $r->shift_start ? $r->shift_start->format('H:i') : '—' }}–{{ $r->shift_end ? $r->shift_end->format('H:i') : '—' }}</td>
                                <td class="px-3 py-2 font-mono text-[11px]">{{ $r->finger_in?->format('H:i:s') ?? '—' }}</td>
                                <td class="px-3 py-2 font-mono text-[11px]">{{ $r->finger_out?->format('H:i:s') ?? '—' }}</td>
                                <td class="px-3 py-2 {{ $r->minutes_late > 0 ? 'text-[#c0392b] font-bold' : '' }}">{{ $r->minutes_late }}</td>
                                <td class="px-3 py-2 text-[11px]">
                                    @if ($r->overtime_minutes > 0)
                                        {{ $r->overtime_minutes }} {{ 'min' }}
                                        <span class="text-muted">({{ $r->overtime_type }})</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->statusBadgeClass($r->status_code) }}">
                                        {{ $r->status_code }} — {{ $this->statusLabel($r->status_code) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-[11px] {{ $r->source === 'MANUAL_OVERRIDE' ? 'text-[#92400e] font-semibold' : 'text-muted' }}">
                                    {{ $r->source === 'MANUAL_OVERRIDE' ? Translator::t('hrm_att_source_manual', 'Manual') : 'Automatic' }}
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-pen" title="{{ Translator::t('hrm_att_action_override', 'Override Status') }}" action="openOverride({{ $r->id_daily }})" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="10" message="{{ 'No attendance data for this date range.' }}" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>

    @if ($showOverrideModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showOverrideModal', false)">
            <div class="bg-white rounded-xl w-[420px] max-w-[92vw] shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold">{{ 'Override Daily Attendance Status' }}</div>
                <form wire:submit="saveOverride" class="p-4 flex flex-col gap-3">
                    <x-form.select label="{{ 'New Status' }}" wire:model="overrideStatus" name="overrideStatus" :options="$this->statusOptions()" :placeholder="false" />
                    <x-form.textarea label="{{ 'Audit Trail Reason (Min 10 Characters) *' }}" wire:model="overrideReason" rows="3" placeholder="{{ 'Enter the official reason, e.g. FDT Form No. ...' }}" name="overrideReason" />
                    <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                        <button type="button" wire:click="$set('showOverrideModal', false)" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">{{ 'Cancel' }}</button>
                        <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-navy text-white cursor-pointer">{{ 'Save Override' }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
