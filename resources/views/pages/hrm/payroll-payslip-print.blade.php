<?php

use App\Models\Master\AttDailyStatus;
use App\Models\Master\EmpBankAccount;
use App\Models\Master\EmpEmployee;
use App\Models\Master\OrgCompany;
use App\Models\Pyr\PyrPayslip;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Slip Gaji -- interface diadopsi dari docs/payroll_timeattendance/
// mockup_04_payslip_print.html (kop, identitas+metode bayar, 2 kolom
// Pendapatan/Potongan, kotak Net Salary, tanda tangan), kerangka teknis
// pakai components/print/* (printnotastandard.md) spt dokumen nota lain.
new #[Title('Payslip — TELASIH MAKMUR ERP')] class extends Component
{
    public int $payslipId;

    public function mount(int $payslip): void
    {
        $this->payslipId = $payslip;

        // Membuka slip = niat mencetaknya; stempel ini yang dipakai grid
        // Cetak & Kirim Slip Gaji untuk menghitung sisa "Belum Dicetak".
        if (! $this->payslip->printed_at) {
            $this->payslip->forceFill(['printed_at' => now()])->save();
        }
    }

    #[Computed]
    public function payslip(): PyrPayslip
    {
        return PyrPayslip::with('run')->findOrFail($this->payslipId);
    }

    #[Computed]
    public function employee(): ?EmpEmployee
    {
        return EmpEmployee::with('position.department')->find($this->payslip->employee_id);
    }

    #[Computed]
    public function bankAccount(): ?EmpBankAccount
    {
        return EmpBankAccount::where('employee_id', $this->payslip->employee_id)
            ->where('is_primary', true)->where('activate', true)->first();
    }

    #[Computed]
    public function attendanceSummary(): array
    {
        $run = $this->payslip->run;
        $rows = AttDailyStatus::where('employee_id', $this->payslip->employee_id)
            ->whereBetween('attendance_date', [$run->period_start->toDateString(), $run->period_end->toDateString()])
            ->get();

        return [
            'hadir' => $rows->where('status_code', 'H')->count(),
            'telat' => $rows->whereIn('status_code', ['T-5', 'T+5', 'T+15', 'PAT-5', 'PAT+5'])->count(),
            'alpha' => $rows->where('status_code', 'A')->count(),
        ];
    }

    #[Computed]
    public function company(): ?OrgCompany
    {
        return OrgCompany::where('activate', true)->first();
    }
};
?>

<x-print.document :theme="['accent' => '#1f6f4a', 'accent-soft' => '#e9f6ef']" info="{{ t('Payslip') }} #{{ $this->payslipId }} · {{ $this->payslip->run->run_status }}">
    <x-slot:toolbar>
        <a href="{{ route('hrm.payroll.payslip-list') }}" class="sc" data-back>Back</a>
    </x-slot:toolbar>

    @include('pages.hrm._payslip-sheet', [
        'payslip' => $this->payslip,
        'employee' => $this->employee,
        'bankAccount' => $this->bankAccount,
        'attendance' => $this->attendanceSummary,
        'company' => $this->company,
    ])
</x-print.document>
