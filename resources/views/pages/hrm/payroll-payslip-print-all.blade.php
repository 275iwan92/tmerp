<?php

use App\Models\Master\AttDailyStatus;
use App\Models\Master\EmpBankAccount;
use App\Models\Master\EmpEmployee;
use App\Models\Master\OrgCompany;
use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Cetak seluruh slip 1 periode jadi satu dokumen. Isi tiap lembar memakai
// partial yang sama dengan cetak 1 slip supaya tidak pernah bisa berbeda.
//
// Hanya slip dengan print_flag menyala yang ikut -- kanal WA dinyalakan
// bertahap, dan begitu seorang pegawai pindah ke WA slipnya tidak boleh lagi
// ikut tumpukan cetakan. Slip anomali dikecualikan (nilainya belum sah).
new #[Title('Cetak Semua Slip Gaji — TELASIH MAKMUR ERP')] class extends Component
{
    public int $runId;

    public function mount(int $run): void
    {
        $this->runId = $run;
    }

    #[Computed]
    public function run(): PyrPayrollRun
    {
        return PyrPayrollRun::findOrFail($this->runId);
    }

    #[Computed]
    public function company(): ?OrgCompany
    {
        return OrgCompany::where('activate', true)->first();
    }

    #[Computed]
    public function sheets()
    {
        $payslips = PyrPayslip::with('run')
            ->where('id_run', $this->runId)
            ->where('print_flag', true)
            ->where('has_anomaly', false)
            ->get();

        $employeeIds = $payslips->pluck('employee_id');
        $employees = EmpEmployee::with('position.department')->whereIn('employee_id', $employeeIds)->get()->keyBy('employee_id');
        $banks = EmpBankAccount::whereIn('employee_id', $employeeIds)
            ->where('is_primary', true)->where('activate', true)->get()->keyBy('employee_id');

        $attendance = AttDailyStatus::whereIn('employee_id', $employeeIds)
            ->whereBetween('attendance_date', [$this->run->period_start->toDateString(), $this->run->period_end->toDateString()])
            ->get()->groupBy('employee_id');

        return $payslips
            ->map(fn (PyrPayslip $p) => [
                'payslip' => $p,
                'employee' => $employees->get($p->employee_id),
                'bankAccount' => $banks->get($p->employee_id),
                'attendance' => $this->summarise($attendance->get($p->employee_id) ?? collect()),
            ])
            ->sortBy(fn ($r) => $r['employee']?->full_name ?? '')
            ->values();
    }

    private function summarise($rows): array
    {
        return [
            'hadir' => $rows->where('status_code', 'H')->count(),
            'telat' => $rows->whereIn('status_code', ['T-5', 'T+5', 'T+15', 'PAT-5', 'PAT+5'])->count(),
            'alpha' => $rows->where('status_code', 'A')->count(),
        ];
    }

    // Stempel "sudah dicetak" ditulis saat tumpukan ini dibuka, bukan saat
    // dialog printer benar-benar selesai -- browser tidak memberi tahu itu.
    public function markPrinted(): void
    {
        PyrPayslip::where('id_run', $this->runId)
            ->where('print_flag', true)
            ->where('has_anomaly', false)
            ->update(['printed_at' => now()]);

        $this->redirectRoute('hrm.payroll.payslip-list', ['run' => $this->runId], navigate: false);
    }
};
?>

<x-print.document :theme="['accent' => '#1f6f4a', 'accent-soft' => '#e9f6ef']"
    info="{{ $this->sheets->count() }} {{ t('slip(s)') }} · {{ $this->run->period_start->format('d M Y') }} — {{ $this->run->period_end->format('d M Y') }}">
    <x-slot:toolbar>
        <a href="{{ route('hrm.payroll.payslip-list', ['run' => $this->runId]) }}" class="sc" data-back>Back</a>
        <button type="button" wire:click="markPrinted" class="sc">{{ t('Mark as Printed') }}</button>
    </x-slot:toolbar>

    @forelse ($this->sheets as $s)
        @include('pages.hrm._payslip-sheet', [
            'payslip' => $s['payslip'],
            'employee' => $s['employee'],
            'bankAccount' => $s['bankAccount'],
            'attendance' => $s['attendance'],
            'company' => $this->company,
        ])
    @empty
        <x-print.sheet>
            <p class="text-center text-sm py-10">{{ t('No payslips are marked for printing in this period.') }}</p>
        </x-print.sheet>
    @endforelse
</x-print.document>
