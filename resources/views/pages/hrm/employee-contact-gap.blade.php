<?php

use App\Models\Master\EmpEmployee;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Daftar kerja untuk officer HR: karyawan aktif yang nomor WhatsApp-nya masih
// kosong. Nomor itu dipakai 2 hal sekaligus -- kunci pemulihan akun (4
// pertanyaan lupa password) dan alamat kirim slip gaji -- jadi barisnya di
// sini artinya orang tsb belum bisa memulihkan akunnya sendiri DAN belum bisa
// menerima slip via WA. Lihat docs/usersecuritystandard.md §2a & §5.
//
// Dikelompokkan per departemen karena pengumpulan nomor jalannya lewat kepala
// bagian masing-masing, bukan satu daftar panjang.
new #[Title('Karyawan Tanpa No. WhatsApp — TELASIH MAKMUR ERP')] class extends Component
{
    // Sengaja TANPA pagination: daftar ini dicetak & diserahkan ke officer HR
    // sebagai satu lembar kerja, jadi semua baris harus ikut tercetak.
    public string $search = '';

    #[Computed]
    public function missing()
    {
        return EmpEmployee::with('position.department')
            ->where('activate', true)
            ->where(fn ($q) => $q->whereNull('phone')->orWhere('phone', ''))
            ->when(trim($this->search), fn ($q, $term) => $q->where(
                fn ($w) => $w->where('full_name', 'like', "%{$term}%")->orWhere('employee_code', 'like', "%{$term}%")
            ))
            ->get()
            ->sortBy([
                fn ($a, $b) => strcmp($a->position?->department?->department_name ?? 'zzz', $b->position?->department?->department_name ?? 'zzz'),
                fn ($a, $b) => strcmp($a->full_name ?? '', $b->full_name ?? ''),
            ])
            ->values();
    }

    #[Computed]
    public function stats(): array
    {
        $aktif = EmpEmployee::where('activate', true)->count();
        $kosong = EmpEmployee::where('activate', true)
            ->where(fn ($q) => $q->whereNull('phone')->orWhere('phone', ''))->count();

        return [
            'aktif' => $aktif,
            'kosong' => $kosong,
            'terisi' => $aktif - $kosong,
            'departemen' => $this->missing->map(fn ($e) => $e->position?->department?->department_name ?? '—')->unique()->count(),
        ];
    }

    public function openEmployee(): void
    {
        $this->redirectRoute('hrm.employee', navigate: false);
    }
};
?>

<div>
    <div class="max-w-[1200px] mx-auto my-5 px-4">

        <x-grid.stat-row :count="4">
            <x-grid.stat-box tone="total" label="Karyawan Aktif" :value="$this->stats['aktif'].' Orang'" />
            <x-grid.stat-box tone="critical" label="Tanpa No. WhatsApp" :value="$this->stats['kosong'].' Orang'" />
            <x-grid.stat-box tone="active" label="Sudah Ada Nomor" :value="$this->stats['terisi'].' Orang'" />
            <x-grid.stat-box tone="warn" label="Departemen Terdampak" :value="$this->stats['departemen'].' Dept'" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama / kode pegawai…"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <x-slot:tools>
                    <button type="button" onclick="window.print()"
                        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">
                        <i class="fa-solid fa-print"></i> Cetak Daftar
                    </button>
                </x-slot:tools>
            </x-grid.toolbar>

            <div class="bg-[#fafbfd] border-b border-[#eef1f5] px-3 py-1.5 text-[11px] text-muted">
                Nomor ini dipakai untuk pemulihan akun (pertanyaan "No HP terdaftar") <span class="font-semibold text-navy">dan</span> pengiriman slip gaji —
                selama kosong, karyawan bersangkutan tidak bisa keduanya. Pengisian dilakukan di <span class="font-semibold text-navy">Master Employee</span>.
            </div>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Karyawan</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[220px]">Posisi</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">No. WhatsApp</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">Tgl Lahir</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[70px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php($currentDept = null)
                        @forelse ($this->missing as $emp)
                            @php($dept = $emp->position?->department?->department_name ?? 'Tanpa Departemen')
                            @if ($dept !== $currentDept)
                                @php($currentDept = $dept)
                                <x-grid.group-header-row :label="$dept"
                                    :value="$this->missing->filter(fn ($e) => ($e->position?->department?->department_name ?? 'Tanpa Departemen') === $dept)->count().' orang'"
                                    :colspan="5" />
                            @endif
                            <tr wire:key="gap-{{ $emp->employee_id }}" class="hover:bg-[#f7faff]">
                                <x-grid.identity-cell :id="$emp->employee_id" :name="$emp->full_name" :code="$emp->employee_code" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $emp->position?->position_name ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#fdecec] text-[#c0392b]">KOSONG</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if ($emp->birth_date)
                                        <span class="font-mono text-[11.5px]">{{ $emp->birth_date->format('d M Y') }}</span>
                                    @else
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#fef6e7] text-[#b45309]">KOSONG JUGA</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-up-right-from-square" title="Buka Master Employee" action="openEmployee" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="5" message="Semua karyawan aktif sudah punya nomor WhatsApp." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>
</div>
