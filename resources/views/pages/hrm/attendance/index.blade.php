<?php

use App\Models\Master\EmpEmployee;
use App\Services\Master\AttendanceDayResolver;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// TIER_1_HRM.md §13 -- Time Attendance, dicabut dari status ditunda
// 2026-08-24 (lihat HRM_STATE.yaml -> time_attendance). Menu/worklist:
// daftar karyawan + ringkasan Hadir/Telat/Alpha bulan berjalan (dihitung
// app-layer, TIER_2_HRM.md Part D). Klik 2x baris -> Profil Kehadiran
// (hrm.attendance.profile). Sumber emp_attendance_log = import Excel,
// dikerjakan di sesi terpisah -- di sini baris tetap tampil kosong sampai
// data itu masuk.
new #[Title('Time Attendance — TELASIH MAKMUR ERP')] class extends Component
{
    public string $search = '';

    #[Computed]
    public function employees()
    {
        return EmpEmployee::query()
            ->with('position.department')
            ->where('is_active', true)
            ->when($this->search !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('full_name', 'like', "%{$this->search}%")
                ->orWhere('employee_code', 'like', "%{$this->search}%")))
            ->orderBy('full_name')
            ->get();
    }

    #[Computed]
    public function monthlyStats(): array
    {
        $from = now()->startOfMonth()->toDateString();
        $to = now()->endOfMonth()->toDateString();

        $rows = (new AttendanceDayResolver)->resolveForEmployees(
            $this->employees->pluck('employee_id')->all(),
            $from,
            $to,
        );

        $stats = [];
        foreach ($rows as $employeeId => $days) {
            $stats[$employeeId] = [
                'hadir' => collect($days)->where('status', 'HADIR')->count(),
                'telat' => collect($days)->whereNotNull('late_minutes')->count(),
                'alpha' => collect($days)->where('status', 'ALPHA')->count(),
            ];
        }

        return $stats;
    }

    #[Computed]
    public function metricTotal(): int
    {
        return $this->employees->count();
    }

    #[Computed]
    public function metricHadir(): int
    {
        return collect($this->monthlyStats)->sum('hadir');
    }

    #[Computed]
    public function metricTelat(): int
    {
        return collect($this->monthlyStats)->sum('telat');
    }

    #[Computed]
    public function metricAlpha(): int
    {
        return collect($this->monthlyStats)->sum('alpha');
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

    public function openRow(int $employeeId): void
    {
        $this->redirect(route('hrm.attendance.profile', ['employee' => $employeeId]));
    }
}; ?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <div class="grid gap-3 mb-3.5">
            <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
                <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Total Karyawan</p>
                <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->metricTotal }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6]">
                <p class="text-[10.5px] font-bold uppercase text-[#2f7a52]">Hadir Bulan Ini</p>
                <p class="text-xl font-extrabold text-[#16a34a] mt-0.5">{{ $this->metricHadir }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3]">
                <p class="text-[10.5px] font-bold uppercase text-[#8a6d00]">Telat Bulan Ini</p>
                <p class="text-xl font-extrabold text-[#b45309] mt-0.5">{{ $this->metricTelat }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#fdecec] border border-[#f5c6c6]">
                <p class="text-[10.5px] font-bold uppercase text-[#a3453f]">Alpha Bulan Ini</p>
                <p class="text-xl font-extrabold text-[#c0392b] mt-0.5">{{ $this->metricAlpha }}</p>
            </div>
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <div class="flex items-center gap-2 bg-white border-b border-[#e2e7ee] px-2.5 py-2 overflow-x-auto">
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari kode / nama…"
                        class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <span class="flex-1"></span>
                <span class="text-[11px] text-muted shrink-0">Periode: {{ now()->translatedFormat('F Y') }}</span>
            </div>

            <div class="max-h-[65vh] overflow-auto">
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Karyawan</th>
                            <th class="text-left px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Departemen</th>
                            <th class="text-center px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[90px]">Hadir</th>
                            <th class="text-center px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[90px]">Telat</th>
                            <th class="text-center px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[90px]">Alpha</th>
                            <th class="text-right px-3.5 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[70px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->employees as $e)
                            @php $stat = $this->monthlyStats[$e->employee_id] ?? ['hadir' => 0, 'telat' => 0, 'alpha' => 0]; @endphp
                            <tr wire:key="att-{{ $e->employee_id }}" wire:dblclick="openRow({{ $e->employee_id }})" class="cursor-pointer hover:bg-[#f7faff]" title="Klik 2x untuk buka profil kehadiran">
                                <td class="px-3.5 py-2 border-b border-[#eef1f5]">
                                    <div class="flex items-center gap-2.5">
                                        @if ($e->photo_path)
                                            <img src="{{ asset('storage/'.$e->photo_path) }}" class="w-8 h-8 rounded-md object-cover shrink-0 border border-[#d9dfe8]">
                                        @else
                                            <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($e->employee_id) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($e->full_name) }}</div>
                                        @endif
                                        <div>
                                            <div class="font-semibold text-[#0f172a] text-[13px]">{{ $e->full_name }}</div>
                                            <div class="text-[11px] text-muted">{{ $e->employee_code }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3.5 py-2 border-b border-[#eef1f5]">{{ $e->position?->department?->department_name ?: '—' }}</td>
                                <td class="px-3.5 py-2 border-b border-[#eef1f5] text-center font-semibold text-[#16a34a]">{{ $stat['hadir'] }}</td>
                                <td class="px-3.5 py-2 border-b border-[#eef1f5] text-center font-semibold text-[#b45309]">{{ $stat['telat'] }}</td>
                                <td class="px-3.5 py-2 border-b border-[#eef1f5] text-center font-semibold text-[#c0392b]">{{ $stat['alpha'] }}</td>
                                <td class="px-3.5 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <button type="button" wire:click.stop="openRow({{ $e->employee_id }})" title="Buka Profil Kehadiran" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">↗</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-8 text-muted text-sm">Tidak ada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
