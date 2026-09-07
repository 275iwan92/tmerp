<?php

use App\Models\Master\AttDailyStatus;
use App\Models\Master\EmpEmployee;
use App\Services\Hrm\AttendanceCalculationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Grid Presensi Harian -- docs/attendancepayroll_tier2.md/tier3.md. Kolom &
// pola override diambil dari interface docs/payroll_timeattendance/
// tm2hrm_laravel_php83.zip (AttendanceDailyResource, mobile-gps-clock-in
// mockup) SESUAI PERMINTAAN USER 2026-09-07 "ambil interface nya saja" --
// logika/mesin di baliknya TETAP pakai AttendanceCalculationService kita
// sendiri (jauh lebih detail: T-5/T+5/PAT/KJK/F-FF/dst, bukan cuma H/T/PC/A
// generik di paket referensi).
new #[Title('Presensi Harian — TELASIH MAKMUR ERP')] class extends Component
{
    private const STATUS_LABELS = [
        'H' => 'Hadir', 'T-5' => 'Telat <5mnt', 'T+5' => 'Telat 6-15mnt', 'T+15' => 'Telat >15mnt',
        'PA' => 'Pulang Awal', 'PAT-5' => 'Pulang Awal + Telat<5', 'PAT+5' => 'Pulang Awal + Telat',
        'KJK' => 'Keluar Jam Kerja', 'I' => 'Izin', 'IS' => 'Izin Disetujui', 'S' => 'Sakit',
        'C' => 'Cuti', 'CL' => 'Cuti Melahirkan', 'A' => 'Alpha', 'F' => 'Tidak Finger 1x', 'FF' => 'Tidak Finger 2x',
    ];

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
        return self::STATUS_LABELS;
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
        session()->flash('message', 'Presensi berhasil dihitung ulang untuk rentang tanggal terpilih.');
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
            <x-grid.stat-box tone="active" label="Hadir" :value="number_format($this->stats['hadir'])" />
            <x-grid.stat-box tone="warn" label="Terlambat" :value="number_format($this->stats['terlambat'])" />
            <x-grid.stat-box tone="critical" label="Tidak Masuk" :value="number_format($this->stats['tidak_masuk'])" />
            <x-grid.stat-box tone="info" label="Lembur" :value="number_format($this->stats['lembur'])" />
        </x-grid.stat-row>

        @if (session('message'))
            <div class="mb-4 px-3 py-2 rounded-md bg-[#dcfce7] text-[#166534] text-xs font-semibold">{{ session('message') }}</div>
        @endif

        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 p-3 flex flex-wrap items-end gap-3">
            <x-form.date-picker label="Dari Tanggal" wire:model.live="dateFrom" name="dateFrom" />
            <x-form.date-picker label="Sampai Tanggal" wire:model.live="dateTo" name="dateTo" />
            <div class="flex-1 min-w-[180px]">
                <x-form.input label="Cari Nama/Kode Pegawai" wire:model.live.debounce.400ms="search" name="search" placeholder="Ketik nama atau kode..." />
            </div>
            <button type="button" wire:click="recalculate" wire:loading.attr="disabled"
                class="px-4 py-2 rounded-md text-xs font-semibold bg-navy text-white cursor-pointer disabled:opacity-50">
                <span wire:loading.remove wire:target="recalculate">Hitung Ulang Presensi</span>
                <span wire:loading wire:target="recalculate">Memproses...</span>
            </button>
        </div>

        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <x-grid.table-shell>
                <table class="w-full text-xs border-collapse">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Tanggal</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Karyawan</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Shift</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Masuk</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Pulang</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Telat (Mnt)</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Lembur</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Status</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Sumber</th>
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
                                        {{ $r->overtime_minutes }} mnt
                                        <span class="text-muted">({{ $r->overtime_type }})</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->statusBadgeClass($r->status_code) }}">
                                        {{ $r->status_code }} — {{ self::STATUS_LABELS[$r->status_code] ?? $r->status_code }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-[11px] {{ $r->source === 'MANUAL_OVERRIDE' ? 'text-[#92400e] font-semibold' : 'text-muted' }}">
                                    {{ $r->source === 'MANUAL_OVERRIDE' ? 'Manual' : 'Otomatis' }}
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="✏️" title="Override Status" action="openOverride({{ $r->id_daily }})" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="10" message="Tidak ada data presensi utk rentang tanggal ini." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>

    @if ($showOverrideModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showOverrideModal', false)">
            <div class="bg-white rounded-xl w-[420px] max-w-[92vw] shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold">Override Status Presensi Harian</div>
                <form wire:submit="saveOverride" class="p-4 flex flex-col gap-3">
                    <x-form.select label="Status Baru" wire:model="overrideStatus" name="overrideStatus" :options="$this->statusOptions()" :placeholder="false" />
                    <x-form.textarea label="Alasan Audit Trail (Min 10 Karakter) *" wire:model="overrideReason" rows="3" placeholder="Masukkan alasan resmi, mis. Form FDT No. ..." name="overrideReason" />
                    <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                        <button type="button" wire:click="$set('showOverrideModal', false)" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-navy text-white cursor-pointer">Simpan Override</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
