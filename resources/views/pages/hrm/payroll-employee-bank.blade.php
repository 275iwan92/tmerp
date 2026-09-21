<?php

use App\Models\Master\EmpBankAccount;
use App\Models\Master\EmpEmployee;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Master Karyawan & Rekening Bank (Modul 07) -- interface diadopsi dari
// docs/payroll_timeattendance/mockup_07_master_employee_bank.html (tabel
// NIK/Nama/Divisi-Posisi/Status Kerja/Metode Gaji/Detail Rekening/Aksi).
// "Metode Gaji" TIDAK disimpan sbg kolom terpisah -- diturunkan dari ada/
// tidaknya emp_bank_account aktif+primary (konsisten dgn payroll-bank-transfer.
// blade.php/payroll-cash-roster.blade.php yang sudah pakai pola sama).
new #[Title('Master Karyawan & Rekening Bank — TELASIH MAKMUR ERP')] class extends Component
{
    public string $search = '';

    public string $filterMethod = '';

    public bool $showFormModal = false;

    public ?int $editingEmployeeId = null;

    public string $editingEmployeeName = '';

    public ?int $editingBankAccountId = null;

    public string $bankName = '';

    public string $accountNo = '';

    public string $accountHolderName = '';

    #[Computed]
    public function rows()
    {
        $employees = EmpEmployee::with('position.department')
            ->where('activate', true)
            ->when($this->search, fn ($q) => $q->where('full_name', 'like', "%{$this->search}%")->orWhere('employee_code', 'like', "%{$this->search}%"))
            ->orderBy('full_name')
            ->get();

        $bankAccounts = EmpBankAccount::whereIn('employee_id', $employees->pluck('employee_id'))
            ->where('is_primary', true)->where('activate', true)->get()->keyBy('employee_id');

        return $employees
            ->map(fn ($e) => ['employee' => $e, 'bank' => $bankAccounts->get($e->employee_id)])
            ->when($this->filterMethod === 'BANK', fn ($c) => $c->filter(fn ($r) => $r['bank']))
            ->when($this->filterMethod === 'CASH', fn ($c) => $c->filter(fn ($r) => ! $r['bank']))
            ->values();
    }

    #[Computed]
    public function stats(): array
    {
        $rows = $this->rows;

        return [
            'total' => $rows->count(),
            'bank' => $rows->filter(fn ($r) => $r['bank'])->count(),
            'cash' => $rows->filter(fn ($r) => ! $r['bank'])->count(),
        ];
    }

    public function openForm(int $employeeId): void
    {
        $employee = EmpEmployee::findOrFail($employeeId);
        $bank = EmpBankAccount::where('employee_id', $employeeId)->where('is_primary', true)->where('activate', true)->first();

        $this->editingEmployeeId = $employeeId;
        $this->editingEmployeeName = $employee->full_name;
        $this->editingBankAccountId = $bank?->bank_account_id;
        $this->bankName = $bank->bank_name ?? '';
        $this->accountNo = $bank->account_no ?? '';
        $this->accountHolderName = $bank->account_holder_name ?? '';
        $this->showFormModal = true;
    }

    public function save(): void
    {
        // bankName kosong = pegawai dibayar CASH -- kalau ada rekening lama,
        // nonaktifkan (bukan hapus, jejak histori tetap ada).
        if (! $this->bankName) {
            if ($this->editingBankAccountId) {
                EmpBankAccount::where('bank_account_id', $this->editingBankAccountId)->update(['activate' => false]);
            }
            $this->showFormModal = false;
            unset($this->rows, $this->stats);

            return;
        }

        $data = $this->validate([
            'bankName' => ['required', 'string', 'max:100'],
            'accountNo' => ['required', 'string', 'max:50'],
            'accountHolderName' => ['required', 'string', 'max:150'],
        ]);

        EmpBankAccount::updateOrCreate(
            ['bank_account_id' => $this->editingBankAccountId],
            [
                'employee_id' => $this->editingEmployeeId,
                'bank_name' => $data['bankName'],
                'account_no' => $data['accountNo'],
                'account_holder_name' => $data['accountHolderName'],
                'is_primary' => true,
                'activate' => true,
                'updated_by' => Auth::user()->login,
            ] + ($this->editingBankAccountId ? [] : ['created_by' => Auth::user()->login]),
        );

        $this->showFormModal = false;
        unset($this->rows, $this->stats);
    }
};
?>

<div>
    <div class="max-w-[1300px] mx-auto my-5 px-4">

        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="info" label="Total Pegawai Aktif" :value="number_format($this->stats['total'])" />
            <x-grid.stat-box tone="active" label="Bank Transfer" :value="number_format($this->stats['bank'])" />
            <x-grid.stat-box tone="warn" label="Cash (Tunai)" :value="number_format($this->stats['cash'])" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 p-3 flex flex-wrap gap-3 items-end">
            <div class="max-w-[280px] flex-1">
                <x-form.input :case-toggle="false" label="Cari NIK / Nama" wire:model.live.debounce.400ms="search" name="search" placeholder="Ketik nama atau kode..." />
            </div>
            <x-form.select label="Metode Gaji" wire:model.live="filterMethod" name="filterMethod"
                :options="['BANK' => 'Bank Transfer', 'CASH' => 'Cash (Tunai)']" placeholder="Semua Metode" />
        </div>

        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <x-grid.table-shell>
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Karyawan</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Divisi / Posisi</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Status Kerja</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Metode Gaji</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Detail Rekening</th>
                            <th class="px-3 py-2 bg-[#fafbfd] border-b border-[#eef1f5] sticky top-0 z-[1]"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rows as $row)
                            <tr class="border-b border-[#eef1f5] align-top" wire:key="bank-{{ $row['employee']->employee_id }}">
                                <x-grid.identity-cell :id="$row['employee']->employee_id" :name="$row['employee']->full_name" :code="$row['employee']->employee_code" />
                                <td class="px-3 py-2">{{ $row['employee']->position?->department?->department_name ?? '—' }} / {{ $row['employee']->position?->position_name ?? '—' }}</td>
                                <td class="px-3 py-2"><span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#dcfce7] text-[#166534]">{{ $row['employee']->work_status }}</span></td>
                                <td class="px-3 py-2">
                                    @if ($row['bank'])
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#dbeafe] text-[#1e40af]">BANK TRANSFER</span>
                                    @else
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#fef3c7] text-[#92400e]">CASH (TUNAI)</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    @if ($row['bank'])
                                        <div class="font-semibold">{{ $row['bank']->bank_name }} - {{ $row['bank']->account_no }}</div>
                                        <div class="text-[10.5px] text-muted">a.n. {{ $row['bank']->account_holder_name }}</div>
                                    @else
                                        <span class="text-muted italic">Tidak Ada Rekening Bank</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-pen" title="Edit Rekening" action="openForm({{ $row['employee']->employee_id }})" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="6" message="Tidak ada pegawai." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>

    @if ($showFormModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showFormModal', false)">
            <div class="bg-white rounded-xl w-[440px] max-w-[92vw] shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold">Rekening Bank — {{ $editingEmployeeName }}</div>
                <form wire:submit="save" class="p-4 flex flex-col gap-3">
                    <x-form.input label="Nama Bank (kosongkan jika Cash/Tunai)" wire:model="bankName" name="bankName" placeholder="mis. BCA, Mandiri, BRI" />
                    <x-form.input label="Nomor Rekening" wire:model="accountNo" name="accountNo" />
                    <x-form.input label="Atas Nama Rekening" wire:model="accountHolderName" name="accountHolderName" />
                    <p class="text-[11px] text-muted">Nama Bank dikosongkan berarti pegawai ini dibayar tunai (Cash) — rekening lama otomatis dinonaktifkan, bukan dihapus.</p>
                    <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                        <button type="button" wire:click="$set('showFormModal', false)" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-navy text-white cursor-pointer">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
