<?php

use App\Models\Master\EmpEmployee;
use App\Models\Pyr\PyrEmployeeSalary;
use Illuminate\Support\Facades\Auth;
use App\Services\Payroll\PayrollAccess;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\OpensFromQuery;
use Livewire\Component;

// Master Gaji & Pajak Pegawai -- prasyarat pyr_employee_salary dipakai
// PayrollEngineService (project_payroll_foundation_built.md). bpjs_active/
// pph21_active adalah TOGGLE PER PEGAWAI (permintaan user 2026-09-07:
// "tidak semua aktif mengikuti aturan ini tetapi boleh dimudahkan agar
// kedepan tinggal di-set mana yang pakai mana yang tidak") -- default OFF,
// HR yang menyalakan per pegawai lewat form ini.
//
// VISIBILITY GATE (permintaan user 2026-09-07): "HR officer tidak semua dia
// bisa melihat gaji, terutama yang bulanan kecuali satpam (security). Jadi
// HR officer hanya jalankan proses dan hanya tahu total secara keseluruhan."
// -> nominal gaji individu di-mask ("Dilindungi") utk viewer yang BUKAN
// SecUser::canViewPayrollAmounts() (is_admin/DIREKSI/MGR_ACC), KECUALI
// posisi pegawainya Satpam/Security -- itu tetap kelihatan siapa pun yang
// akses layar ini. Guard diterapkan 2x: render (sembunyikan tombol edit)
// DAN di openForm()/save() (bertahan kalau dipanggil langsung, bukan cuma
// UI hidden).
new #[Title('Master Gaji & Pajak Pegawai — TELASIH MAKMUR ERP')] class extends Component
{
    use OpensFromQuery;

    protected function openFromQuery(int $id): void
    {
        $this->openForm($id);
    }

    private const PTKP_OPTIONS = [
        'TK/0' => 'TK/0 (Lajang)', 'TK/1' => 'TK/1 (1 Tanggungan)', 'TK/2' => 'TK/2 (2 Tanggungan)', 'TK/3' => 'TK/3 (3 Tanggungan)',
        'K/0' => 'K/0 (Menikah, 0 Anak)', 'K/1' => 'K/1 (Menikah, 1 Anak)', 'K/2' => 'K/2 (Menikah, 2 Anak)', 'K/3' => 'K/3 (Menikah, 3 Anak)',
    ];

    public string $search = '';

    public bool $showFormModal = false;

    public ?int $editingEmployeeId = null;

    public string $editingEmployeeName = '';

    public string $basicSalaryMonthly = '';

    public string $tjKeahlianMonthly = '0';

    public string $tjLoyalitasMonthly = '0';

    public string $tjPrestasiMonthly = '0';

    public string $bonusPagiAmount = '0';

    public string $bonusHadirAmount = '0';

    public string $ptkpStatus = '';

    public bool $bpjsActive = false;

    public bool $pph21Active = false;

    #[Computed]
    public function rows()
    {
        $employees = PayrollAccess::restrict(EmpEmployee::where('activate', true), auth()->user())
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('full_name', 'like', "%{$this->search}%")->orWhere('employee_code', 'like', "%{$this->search}%")))
            ->orderBy('full_name')
            ->get();

        $salaries = PyrEmployeeSalary::whereIn('employee_id', $employees->pluck('employee_id'))
            ->where('activate', true)
            ->get()
            ->keyBy('employee_id');

        return $employees->map(fn ($e) => ['employee' => $e, 'salary' => $salaries->get($e->employee_id)]);
    }

    #[Computed]
    public function stats(): array
    {
        $rows = $this->rows;

        return [
            'total' => $rows->count(),
            'belum_diatur' => $rows->whereNull('salary')->count(),
            'bpjs_aktif' => $rows->filter(fn ($r) => $r['salary']?->bpjs_active)->count(),
            'pph21_aktif' => $rows->filter(fn ($r) => $r['salary']?->pph21_active)->count(),
        ];
    }

    public function ptkpOptions(): array
    {
        return self::PTKP_OPTIONS;
    }

    public function canViewSalary(EmpEmployee $employee): bool
    {
        return PayrollAccess::canSeeEmployee(Auth::user(), (int) $employee->employee_id);
    }

    public function openForm(int $employeeId): void
    {
        $employee = EmpEmployee::with('position')->findOrFail($employeeId);

        if (! $this->canViewSalary($employee)) {
            session()->flash('error', 'Anda tidak punya akses melihat/mengatur gaji pegawai ini.');

            return;
        }

        $salary = PyrEmployeeSalary::activeFor($employeeId);

        $this->editingEmployeeId = $employeeId;
        $this->editingEmployeeName = $employee->full_name;
        $this->basicSalaryMonthly = $salary ? (string) $salary->basic_salary_monthly : '';
        $this->tjKeahlianMonthly = $salary ? (string) $salary->tj_keahlian_monthly : '0';
        $this->tjLoyalitasMonthly = $salary ? (string) $salary->tj_loyalitas_monthly : '0';
        $this->tjPrestasiMonthly = $salary ? (string) $salary->tj_prestasi_monthly : '0';
        $this->bonusPagiAmount = $salary ? (string) $salary->bonus_pagi_amount : '0';
        $this->bonusHadirAmount = $salary ? (string) $salary->bonus_hadir_amount : '0';
        $this->ptkpStatus = $salary->ptkp_status ?? '';
        $this->bpjsActive = (bool) ($salary->bpjs_active ?? false);
        $this->pph21Active = (bool) ($salary->pph21_active ?? false);
        $this->showFormModal = true;
    }

    // Prinsip rate versioned (CLAUDE.md §4): rate lama dinonaktifkan +
    // effective_to diisi, BUKAN di-overwrite -- histori gaji tetap tersimpan.
    public function save(): void
    {
        $data = $this->validate([
            'basicSalaryMonthly' => ['required', 'numeric', 'min:0'],
            'tjKeahlianMonthly' => ['numeric', 'min:0'],
            'tjLoyalitasMonthly' => ['numeric', 'min:0'],
            'tjPrestasiMonthly' => ['numeric', 'min:0'],
            'bonusPagiAmount' => ['numeric', 'min:0'],
            'bonusHadirAmount' => ['numeric', 'min:0'],
            'ptkpStatus' => ['nullable', 'string'],
        ]);

        if ($this->pph21Active && ! $data['ptkpStatus']) {
            $this->addError('ptkpStatus', 'Status PTKP wajib diisi kalau PPh21 diaktifkan.');

            return;
        }

        PyrEmployeeSalary::where('employee_id', $this->editingEmployeeId)->where('activate', true)
            ->update(['activate' => false, 'effective_to' => now()->toDateString()]);

        PyrEmployeeSalary::create([
            'employee_id' => $this->editingEmployeeId,
            'basic_salary_monthly' => $data['basicSalaryMonthly'],
            'tj_keahlian_monthly' => $data['tjKeahlianMonthly'],
            'tj_loyalitas_monthly' => $data['tjLoyalitasMonthly'],
            'tj_prestasi_monthly' => $data['tjPrestasiMonthly'],
            'bonus_pagi_amount' => $data['bonusPagiAmount'],
            'bonus_hadir_amount' => $data['bonusHadirAmount'],
            'ptkp_status' => $data['ptkpStatus'] ?: null,
            'bpjs_active' => $this->bpjsActive,
            'pph21_active' => $this->pph21Active,
            'effective_from' => now()->toDateString(),
            'activate' => true,
            'created_by' => Auth::user()->login,
        ]);

        $this->showFormModal = false;
        unset($this->rows, $this->stats);
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">

        <x-grid.stat-row :count="4">
            <x-grid.stat-box tone="info" label="Total Pegawai Aktif" :value="number_format($this->stats['total'])" />
            <x-grid.stat-box tone="warn" label="Belum Diatur Gajinya" :value="number_format($this->stats['belum_diatur'])" />
            <x-grid.stat-box tone="active" label="BPJS Aktif" :value="number_format($this->stats['bpjs_aktif'])" />
            <x-grid.stat-box tone="active" label="PPh21 Aktif" :value="number_format($this->stats['pph21_aktif'])" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 p-3">
            <div class="max-w-[320px]">
                <x-form.input :case-toggle="false" label="Cari Nama/Kode Pegawai" wire:model.live.debounce.400ms="search" name="search" placeholder="Ketik nama atau kode..." />
            </div>
        </div>

        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <x-grid.table-shell>
                <table class="w-full text-xs border-collapse">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Karyawan</th>
                            <th class="num px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Gaji Pokok/Bulan</th>
                            <th class="num px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">TJ Keahlian/Bulan</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">PTKP</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">BPJS</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">PPh21</th>
                            <th class="px-3 py-2 bg-[#fafbfd] border-b border-[#eef1f5] sticky top-0 z-[1]"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rows as $row)
                            <tr class="border-b border-[#eef1f5] align-top" wire:key="salary-{{ $row['employee']->employee_id }}">
                                <x-grid.identity-cell :id="$row['employee']->employee_id" :name="$row['employee']->full_name" :code="$row['employee']->employee_code" />
                                @if ($row['salary'])
                                    <td class="num px-3 py-2 font-semibold">Rp {{ number_format((float) $row['salary']->basic_salary_monthly, 0, ',', '.') }}</td>
                                    <td class="num px-3 py-2">Rp {{ number_format((float) $row['salary']->tj_keahlian_monthly, 0, ',', '.') }}</td>
                                    <td class="px-3 py-2">{{ $row['salary']->ptkp_status ?: '—' }}</td>
                                    <td class="px-3 py-2">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $row['salary']->bpjs_active ? 'bg-[#dcfce7] text-[#166534]' : 'bg-[#e2e8f0] text-[#334155]' }}">
                                            {{ $row['salary']->bpjs_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $row['salary']->pph21_active ? 'bg-[#dcfce7] text-[#166534]' : 'bg-[#e2e8f0] text-[#334155]' }}">
                                            {{ $row['salary']->pph21_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                @else
                                    <td class="px-3 py-2 text-muted" colspan="4">Belum diatur</td>
                                @endif
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-pen" title="Atur Gaji & Pajak" action="openForm({{ $row['employee']->employee_id }})" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="7" message="Tidak ada pegawai." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>

    @if ($showFormModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showFormModal', false)">
            <div class="bg-white rounded-xl w-[520px] max-w-[92vw] shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold">Atur Gaji & Pajak — {{ $editingEmployeeName }}</div>
                <form wire:submit="save" class="p-4 flex flex-col gap-3">
                    <div class="grid grid-cols-2 gap-3">
                        <x-form.input label="Gaji Pokok / Bulan (Rp) *" wire:model="basicSalaryMonthly" name="basicSalaryMonthly" type="number" step="1" />
                        <x-form.input label="TJ Keahlian / Bulan (Rp)" wire:model="tjKeahlianMonthly" name="tjKeahlianMonthly" type="number" step="1" />
                        <x-form.input label="TJ Loyalitas / Bulan (Rp)" wire:model="tjLoyalitasMonthly" name="tjLoyalitasMonthly" type="number" step="1" />
                        <x-form.input label="TJ Prestasi / Bulan (Rp)" wire:model="tjPrestasiMonthly" name="tjPrestasiMonthly" type="number" step="1" />
                        <x-form.input label="Bonus Pagi / Hari (Rp)" wire:model="bonusPagiAmount" name="bonusPagiAmount" type="number" step="1" />
                        <x-form.input label="Bonus Hadir / Hari (Rp)" wire:model="bonusHadirAmount" name="bonusHadirAmount" type="number" step="1" />
                    </div>

                    <x-form.select label="Status PTKP (wajib kalau PPh21 diaktifkan)" wire:model="ptkpStatus" name="ptkpStatus" :options="$this->ptkpOptions()" />

                    <div class="flex gap-4 pt-2 border-t border-[#eef1f5] mt-1">
                        <x-form.checkbox label="Potong BPJS (JHT/JP/Kes)" wire:model="bpjsActive" class="w-4 h-4" labelClass="font-semibold cursor-pointer" />
                        <x-form.checkbox label="Potong PPh21 (TER)" wire:model="pph21Active" class="w-4 h-4" labelClass="font-semibold cursor-pointer" />
                    </div>

                    <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                        <button type="button" wire:click="$set('showFormModal', false)" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-navy text-white cursor-pointer">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
