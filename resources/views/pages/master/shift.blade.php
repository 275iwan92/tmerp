<?php

use App\Models\Master\EmpShift;
use App\Models\Master\EmpShiftDetail;
use App\Models\Master\OrgDepartment;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// mockup_master_produksi.html #page-shift. Direplikasi dari struktur legacy
// (m_shifts + m_shifts_details) -> tm2mst.emp_shift + emp_shift_detail:
// 1 header + jam kerja per hari (Senin-Minggu bisa beda), lengkap jam
// istirahat. department_id FK ke org_department (TIER_3A_HRM.sql), bukan lagi
// teks bebas. Hari dengan start_time/end_time kosong = "tidak beroperasi".
new #[Title('Master Shift — TELASIH MAKMUR ERP')] class extends Component
{
    public array $dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public array $dayLabels = ['Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'];

    public string $search = '';

    public string $panel = 'grid'; // grid | form

    public string $mode = 'new'; // new | edit

    public ?int $editingId = null;

    public string $shift_name = '';

    public ?int $department_id = null;

    public bool $shift_is_active = true;

    // index 0..6 -> dayOrder[index]
    public array $days = [];

    #[Computed]
    public function shifts()
    {
        return EmpShift::query()
            ->with(['details', 'department'])
            ->when($this->search !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('shift_name', 'like', "%{$this->search}%")
                ->orWhereHas('department', fn ($dq) => $dq->where('department_name', 'like', "%{$this->search}%"))))
            ->orderBy('shift_name')
            ->get();
    }

    #[Computed]
    public function departmentOptions()
    {
        return OrgDepartment::query()->where('is_active', true)->orderBy('department_name')->get();
    }

    protected function blankDays(): array
    {
        return collect($this->dayOrder)->map(fn ($d) => [
            'day_of_week' => $d,
            'start_time' => '',
            'break_out' => '',
            'break_in' => '',
            'end_time' => '',
        ])->all();
    }

    public function openModal(string $mode, ?int $id = null): void
    {
        $this->resetErrorBag();
        $this->mode = in_array($mode, ['new', 'edit'], true) ? $mode : 'new';
        $this->editingId = $id;
        $this->days = $this->blankDays();

        if ($this->mode === 'edit' && $id) {
            $s = EmpShift::with('details')->findOrFail($id);
            $this->shift_name = $s->shift_name;
            $this->department_id = $s->department_id;
            $this->shift_is_active = (bool) $s->is_active;

            foreach ($s->details as $detail) {
                $idx = array_search($detail->day_of_week, $this->dayOrder);
                if ($idx === false) {
                    continue;
                }
                $this->days[$idx]['start_time'] = $detail->start_time?->format('H:i') ?? '';
                $this->days[$idx]['break_out'] = $detail->break_out?->format('H:i') ?? '';
                $this->days[$idx]['break_in'] = $detail->break_in?->format('H:i') ?? '';
                $this->days[$idx]['end_time'] = $detail->end_time?->format('H:i') ?? '';
            }
        } else {
            $this->reset(['shift_name', 'department_id']);
            $this->shift_is_active = true;
        }

        $this->panel = 'form';
    }

    public function showGrid(): void
    {
        $this->panel = 'grid';
    }

    public function clearDay(int $index): void
    {
        if (! isset($this->days[$index])) {
            return;
        }
        $this->days[$index]['start_time'] = '';
        $this->days[$index]['break_out'] = '';
        $this->days[$index]['break_in'] = '';
        $this->days[$index]['end_time'] = '';
    }

    public function save(): void
    {
        $data = $this->validate([
            'shift_name' => ['required', 'string', 'max:150'],
            'department_id' => ['required', 'integer', 'exists:tm2mst.org_department,department_id'],
            'shift_is_active' => ['boolean'],
            'days' => ['array', 'size:7'],
            'days.*.day_of_week' => ['required', 'string', 'in:'.implode(',', $this->dayOrder)],
            'days.*.start_time' => ['nullable', 'date_format:H:i'],
            'days.*.break_out' => ['nullable', 'date_format:H:i'],
            'days.*.break_in' => ['nullable', 'date_format:H:i'],
            'days.*.end_time' => ['nullable', 'date_format:H:i'],
        ]);

        $payload = [
            'shift_name' => $data['shift_name'],
            'department_id' => $data['department_id'],
            'is_active' => $data['shift_is_active'],
            'updated_by' => Auth::user()->login,
        ];

        if ($this->mode === 'edit' && $this->editingId) {
            $shift = EmpShift::findOrFail($this->editingId);
            $shift->update($payload);
        } else {
            $payload['created_by'] = Auth::user()->login;
            $shift = EmpShift::create($payload);
        }

        foreach ($data['days'] as $day) {
            $isWorking = ! empty($day['start_time']) && ! empty($day['end_time']);

            if (! $isWorking) {
                EmpShiftDetail::where('shift_id', $shift->shift_id)
                    ->where('day_of_week', $day['day_of_week'])
                    ->delete();

                continue;
            }

            EmpShiftDetail::updateOrCreate(
                ['shift_id' => $shift->shift_id, 'day_of_week' => $day['day_of_week']],
                [
                    'start_time' => $day['start_time'],
                    'break_out' => $day['break_out'] ?: null,
                    'break_in' => $day['break_in'] ?: null,
                    'end_time' => $day['end_time'],
                    'updated_by' => Auth::user()->login,
                ]
            );
        }

        unset($this->shifts);
        $this->panel = 'grid';
    }

    public function toggleActive(int $id): void
    {
        $s = EmpShift::findOrFail($id);
        $s->is_active = ! $s->is_active;
        $s->updated_by = Auth::user()->login;
        $s->save();

        unset($this->shifts, $this->statTotal, $this->statActive, $this->statInactive);
    }

    #[Computed]
    public function statTotal(): int
    {
        return EmpShift::count();
    }

    #[Computed]
    public function statActive(): int
    {
        return EmpShift::where('is_active', true)->count();
    }

    #[Computed]
    public function statInactive(): int
    {
        return EmpShift::where('is_active', false)->count();
    }

    #[Computed]
    public function statDepartments(): int
    {
        return EmpShift::where('is_active', true)->distinct('department_id')->count('department_id');
    }

    // GRID_UI_STANDARD.md #3 -- avatar inisial+warna utk kolom identitas.
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
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">

        {{-- ============ GRID (LIST) ============ --}}
        @if ($panel === 'grid')
        <div class="grid gap-3 mb-3.5">
            <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
                <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Total Shift</p>
                <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->statTotal }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6]">
                <p class="text-[10.5px] font-bold uppercase text-[#2f7a52]">Aktif</p>
                <p class="text-xl font-extrabold text-[#16a34a] mt-0.5">{{ $this->statActive }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#f1ecfc] border border-[#ddd0f7]">
                <p class="text-[10.5px] font-bold uppercase text-[#6b4fa0]">Departemen Terpakai</p>
                <p class="text-xl font-extrabold text-[#6d28d9] mt-0.5">{{ $this->statDepartments }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#fdecec] border border-[#f5c6c6]">
                <p class="text-[10.5px] font-bold uppercase text-[#a3453f]">Nonaktif</p>
                <p class="text-xl font-extrabold text-[#c0392b] mt-0.5">{{ $this->statInactive }}</p>
            </div>
        </div>

        <div class="text-xs px-3 py-2.5 rounded-md mb-3.5 leading-relaxed bg-[#fff4e5] border border-[#ffd9a0] text-[#8a4b00]">
            Direplikasi dari struktur legacy (<code>m_shifts</code> + <code>m_shifts_details</code>): 1 shift = 1 header + jam kerja per hari (Senin–Minggu bisa beda), lengkap jam istirahat. Klik <b>✎</b> untuk buka detail jamnya.
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <div class="flex items-center gap-2 bg-white border-b border-[#e2e7ee] px-2.5 py-2 overflow-x-auto">
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama shift / departemen…"
                        class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <span class="flex-1"></span>
                <button type="button" wire:click="openModal('new')"
                    class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
            </div>

            <div class="max-h-[65vh] overflow-auto">
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Nama Shift</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Departemen</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Ringkasan Jam</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">Status</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->shifts as $s)
                            <tr wire:key="shift-{{ $s->shift_id }}" wire:dblclick="openModal('edit', {{ $s->shift_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka detail">
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($s->shift_id) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($s->shift_name) }}</div>
                                        <div class="font-semibold text-navy text-[13px]">{{ $s->shift_name }}</div>
                                    </div>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $s->department?->department_name ?: '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-[#667]">{{ $s->scheduleSummary() }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $s->is_active ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $s->is_active ? 'AKTIF' : 'NONAKTIF' }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <button type="button" wire:click.stop="openModal('edit', {{ $s->shift_id }})" title="Edit" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">✎</button>
                                    <button type="button" wire:click.stop="toggleActive({{ $s->shift_id }})" wire:confirm="{{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }} shift {{ $s->shift_name }}?"
                                        title="{{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }}" class="inline-flex items-center justify-center w-7 h-7 rounded-md cursor-pointer {{ $s->is_active ? 'text-[#c0392b] hover:bg-[#fdeceb]' : 'text-accent hover:bg-[#eef3fb]' }}">⏻</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-8 text-muted text-sm">Tidak ada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- ============ FORM (NEW/EDIT) ============ --}}
        @if ($panel === 'form')
            <div class="flex justify-between items-center bg-navy text-white px-4 py-3 rounded-t-md">
                <div class="text-sm font-bold">
                    {{ $mode === 'edit' ? 'Edit Shift' : 'Shift Baru' }}
                    @if ($mode === 'edit')<span class="text-xs font-normal opacity-75 ml-2">{{ $shift_name }}</span>@endif
                </div>
            </div>
            <form wire:submit="save" class="bg-white border border-t-0 border-[#d7dbe2] p-5 flex flex-col gap-3">
                    <div class="flex gap-2.5">
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Nama Shift *</label>
                            <input type="text" wire:model="shift_name" placeholder="mis. TM Production Shift 1" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('shift_name') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Departemen *</label>
                            <select wire:model="department_id" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                <option value="">— pilih —</option>
                                @foreach ($this->departmentOptions as $d)
                                    <option value="{{ $d->department_id }}">{{ $d->department_name }}</option>
                                @endforeach
                            </select>
                            @error('department_id') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">Jam Kerja per Hari</div>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-[12px]">
                            <thead>
                                <tr>
                                    <th class="text-left px-1.5 py-1 text-[10px] text-muted uppercase font-semibold w-[70px]">Hari</th>
                                    <th class="text-left px-1.5 py-1 text-[10px] text-muted uppercase font-semibold">Masuk</th>
                                    <th class="text-left px-1.5 py-1 text-[10px] text-muted uppercase font-semibold">Ist. Mulai</th>
                                    <th class="text-left px-1.5 py-1 text-[10px] text-muted uppercase font-semibold">Ist. Selesai</th>
                                    <th class="text-left px-1.5 py-1 text-[10px] text-muted uppercase font-semibold">Pulang</th>
                                    <th class="w-11"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($days as $i => $day)
                                    <tr wire:key="day-{{ $day['day_of_week'] }}" class="border-b border-[#eef1f5]">
                                        <td class="px-1.5 py-1 font-semibold {{ $day['day_of_week'] === 'Sunday' && empty($day['start_time']) ? 'text-muted' : '' }}">{{ $dayLabels[$day['day_of_week']] }}</td>
                                        <td class="px-1.5 py-1"><input type="time" wire:model="days.{{ $i }}.start_time" class="w-full px-1.5 py-1 border border-[#c7cfda] rounded text-[12px]"></td>
                                        <td class="px-1.5 py-1"><input type="time" wire:model="days.{{ $i }}.break_out" class="w-full px-1.5 py-1 border border-[#c7cfda] rounded text-[12px]"></td>
                                        <td class="px-1.5 py-1"><input type="time" wire:model="days.{{ $i }}.break_in" class="w-full px-1.5 py-1 border border-[#c7cfda] rounded text-[12px]"></td>
                                        <td class="px-1.5 py-1"><input type="time" wire:model="days.{{ $i }}.end_time" class="w-full px-1.5 py-1 border border-[#c7cfda] rounded text-[12px]"></td>
                                        <td class="px-1.5 py-1 text-center">
                                            <button type="button" wire:click="clearDay({{ $i }})" title="Kosongkan hari ini" class="w-7 h-7 rounded-md border border-[#c7cfda] text-[#c0392b] hover:bg-[#fdeceb] cursor-pointer">🗑</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @error('days') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div class="text-[11px] text-muted">Kosongkan Masuk &amp; Pulang untuk menandai hari tidak beroperasi.</div>

                    <label class="flex items-center gap-2 text-[13px] text-[#334] mt-1">
                        <input type="checkbox" wire:model="shift_is_active"> Aktif
                    </label>

                    <div class="flex gap-2 pt-3.5 -mx-5 -mb-5 px-4 py-3.5 bg-[#fafbfc] border-t border-[#d7dbe2] rounded-b-md">
                        <button type="submit" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
                        <button type="button" wire:click="showGrid" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">Batal</button>
                    </div>
            </form>
        @endif
    </div>
</div>
