<?php

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpLeaveBalance;
use App\Services\Master\AttendanceDayResolver;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// TIER_1_HRM.md §13 -- Profil Kehadiran per karyawan, referensi tampilan V1
// ScriptCase "Data Profile" tapi mengikuti pola pastel-stat-box V2 (bukan
// replikasi 1:1 boks ganda V1). Business logic tabel detail: TIER_2_HRM.md
// Part D. clock_in_at/clock_out_at kosong sampai import Excel (sesi terpisah)
// mengisi emp_attendance_log -- baris tanggal tetap tampil (status Alpha/
// Hari Libur/Cuti dst tetap terhitung dari roster & leave request).
new #[Title('Profil Kehadiran — TELASIH MAKMUR ERP')] class extends Component
{
    public int $employee;

    public string $dateFrom;

    public string $dateTo;

    public function mount(int $employee): void
    {
        $this->employee = $employee;
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->endOfMonth()->toDateString();
    }

    #[Computed]
    public function emp(): EmpEmployee
    {
        return EmpEmployee::with('position.department')->findOrFail($this->employee);
    }

    #[Computed]
    public function days(): array
    {
        return (new AttendanceDayResolver)->resolve($this->employee, $this->dateFrom, $this->dateTo);
    }

    #[Computed]
    public function leaveBalance(): ?EmpLeaveBalance
    {
        return EmpLeaveBalance::where('employee_id', $this->employee)->where('year', now()->year)->first();
    }

    #[Computed]
    public function summary(): array
    {
        $days = collect($this->days);

        return [
            'sisa_cuti' => $this->leaveBalance?->remainingDays() ?? 0,
            'cuti' => $days->where('status', 'CUTI')->count(),
            'sakit' => $days->where('status', 'SAKIT')->count(),
            'izin' => $days->where('status', 'IZIN')->count(),
            'telat' => $days->whereNotNull('late_minutes')->count(),
            'lembur' => $days->where('is_overtime', true)->count(),
        ];
    }

    public function applyFilter(): void
    {
        $this->validate([
            'dateFrom' => ['required', 'date'],
            'dateTo' => ['required', 'date', 'after_or_equal:dateFrom'],
        ]);
    }

    public function avatarInitials(string $name): string
    {
        $words = array_values(array_filter(explode(' ', trim($name))));
        if (count($words) >= 2) {
            return mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($name, 0, 2));
    }

    private const AVATAR_PALETTE = [
        'from-[#2563eb] to-[#1d4ed8]', 'from-[#10b981] to-[#059669]', 'from-[#ef4444] to-[#dc2626]',
        'from-[#8b5cf6] to-[#6d28d9]', 'from-[#f59e0b] to-[#d97706]', 'from-[#0ea5e9] to-[#0284c7]',
    ];

    public function avatarGradient(int $id): string
    {
        return self::AVATAR_PALETTE[$id % count(self::AVATAR_PALETTE)];
    }
}; ?>

<div>
    <div class="max-w-[1300px] mx-auto my-5 px-4">
        <div class="flex items-center gap-3 mb-3.5">
            <span class="text-sm font-bold text-navy">Profil Kehadiran</span>
            <a href="{{ route('hrm.attendance.index') }}" title="Kembali ke menu Time Attendance"
                class="ml-auto inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full no-underline hover:brightness-110">
                <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-xs font-bold">←</span>
            </a>
        </div>

        <div class="flex items-center gap-3.5 bg-white border border-[#d9dfe8] rounded-lg px-3.5 py-3 mb-3.5">
            @if ($this->emp->photo_path)
                <img src="{{ asset('storage/'.$this->emp->photo_path) }}" class="w-[64px] h-[64px] rounded-lg object-cover border-2 border-accent shrink-0">
            @else
                <div class="w-[64px] h-[64px] rounded-lg bg-gradient-to-br {{ $this->avatarGradient($this->emp->employee_id) }} text-white text-lg font-bold flex items-center justify-center shrink-0">
                    {{ $this->avatarInitials($this->emp->full_name) }}
                </div>
            @endif
            <div class="flex-1 min-w-[180px]">
                <div class="text-[15px] font-bold text-navy">{{ $this->emp->full_name }}</div>
                <div class="text-xs text-muted mt-0.5">{{ $this->emp->employee_code }} @if($this->emp->position?->position_name) · {{ $this->emp->position->position_name }} @endif</div>
                <div class="text-xs text-muted">{{ $this->emp->position?->department?->department_name ?: '—' }} @if($this->emp->join_date) · Bergabung {{ $this->emp->join_date->format('d M Y') }} @endif</div>
            </div>
            @if ($this->emp->phone)
                <div class="text-xs text-muted shrink-0">📱 {{ $this->emp->phone }}</div>
            @endif
        </div>

        <div class="flex items-end gap-2.5 bg-white border border-[#d9dfe8] rounded-lg px-3.5 py-2.5 mb-3.5">
            <div class="w-[150px] shrink-0">
                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Dari Tanggal</label>
                <input type="date" wire:model="dateFrom" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
            </div>
            <div class="w-[150px] shrink-0">
                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Sampai Tanggal</label>
                <input type="date" wire:model="dateTo" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
            </div>
            <button type="button" wire:click="applyFilter" class="px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">Terapkan</button>
            @error('dateTo') <span class="text-[11px] text-red-600 ml-1">{{ $message }}</span> @enderror
        </div>

        <div class="[display:grid] grid-cols-6 gap-3 mb-3.5">
            <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
                <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Sisa Cuti</p>
                <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->summary['sisa_cuti'] }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#eef3fb] border border-[#c9d9ee]">
                <p class="text-[10.5px] font-bold uppercase text-[#2c5aa0]">Cuti Diambil</p>
                <p class="text-xl font-extrabold text-[#2c5aa0] mt-0.5">{{ $this->summary['cuti'] }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#eef3fb] border border-[#c9d9ee]">
                <p class="text-[10.5px] font-bold uppercase text-[#2c5aa0]">Sakit</p>
                <p class="text-xl font-extrabold text-[#2c5aa0] mt-0.5">{{ $this->summary['sakit'] }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#eef3fb] border border-[#c9d9ee]">
                <p class="text-[10.5px] font-bold uppercase text-[#2c5aa0]">Izin</p>
                <p class="text-xl font-extrabold text-[#2c5aa0] mt-0.5">{{ $this->summary['izin'] }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3]">
                <p class="text-[10.5px] font-bold uppercase text-[#8a6d00]">Telat</p>
                <p class="text-xl font-extrabold text-[#b45309] mt-0.5">{{ $this->summary['telat'] }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#f3eefb] border border-[#ddc9ee]">
                <p class="text-[10.5px] font-bold uppercase text-[#6d3b99]">Lembur</p>
                <p class="text-xl font-extrabold text-[#6d3b99] mt-0.5">{{ $this->summary['lembur'] }}</p>
            </div>
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <div class="max-h-[55vh] overflow-auto">
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Tanggal</th>
                            <th class="text-center px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Jam Masuk</th>
                            <th class="text-center px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Jam Keluar</th>
                            <th class="text-center px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Jadwal Masuk</th>
                            <th class="text-center px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Jadwal Keluar</th>
                            <th class="text-center px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Telat</th>
                            <th class="text-center px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Pulang Cepat</th>
                            <th class="text-left px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->days as $row)
                            <tr class="hover:bg-[#f7faff]">
                                <td class="px-3.5 py-2 border-b border-[#eef1f5] whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($row['date'])->translatedFormat('d M Y (D)') }}</td>
                                <td class="px-3.5 py-2 border-b border-[#eef1f5] text-center">{{ $row['log']?->clock_in_at?->format('H:i') ?: '—' }}</td>
                                <td class="px-3.5 py-2 border-b border-[#eef1f5] text-center">{{ $row['log']?->clock_out_at?->format('H:i') ?: '—' }}</td>
                                <td class="px-3.5 py-2 border-b border-[#eef1f5] text-center text-muted">{{ $row['scheduled_in'] ?: '—' }}</td>
                                <td class="px-3.5 py-2 border-b border-[#eef1f5] text-center text-muted">{{ $row['scheduled_out'] ?: '—' }}</td>
                                <td class="px-3.5 py-2 border-b border-[#eef1f5] text-center {{ $row['late_minutes'] ? 'text-[#b45309] font-semibold' : 'text-muted' }}">{{ $row['late_minutes'] ? $row['late_minutes'].'m' : '—' }}</td>
                                <td class="px-3.5 py-2 border-b border-[#eef1f5] text-center {{ $row['early_leave_minutes'] ? 'text-[#b45309] font-semibold' : 'text-muted' }}">{{ $row['early_leave_minutes'] ? $row['early_leave_minutes'].'m' : '—' }}</td>
                                <td class="px-3.5 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ \App\Services\Master\AttendanceDayResolver::STATUS_BADGE[$row['status']] ?? '' }}">{{ \App\Services\Master\AttendanceDayResolver::STATUS_LABELS[$row['status']] ?? $row['status'] }}</span>
                                    @if ($row['is_overtime'])
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block bg-[#f3eefb] text-[#6d3b99] ml-1">Lembur</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center py-8 text-muted text-sm">Tidak ada data pada rentang tanggal ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
