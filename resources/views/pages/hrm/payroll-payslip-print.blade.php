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
new #[Title('Slip Gaji — TELASIH MAKMUR ERP')] class extends Component
{
    public int $payslipId;

    public function mount(int $payslip): void
    {
        $this->payslipId = $payslip;
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

<x-print.document :theme="['accent' => '#1f6f4a', 'accent-soft' => '#e9f6ef']" info="Payslip #{{ $this->payslipId }} · {{ $this->payslip->run->run_status }}">
    <x-slot:toolbar>
        <a href="{{ route('hrm.payroll.batch-run') }}" class="sc">← Kembali ke Payroll Run</a>
    </x-slot:toolbar>

    <x-print.sheet>
        <x-print.header :company="$this->company" doc-title="SLIP GAJI KARYAWAN" doc-no="{{ $this->payslip->run->period_type }}-{{ $this->payslipId }}">
            <x-slot:meta>
                Periode: {{ $this->payslip->run->period_start->translatedFormat('d F Y') }} — {{ $this->payslip->run->period_end->translatedFormat('d F Y') }}
            </x-slot:meta>
        </x-print.header>

        <x-print.parties columns="1fr 1fr">
            <x-print.party label="IDENTITAS PEGAWAI">
                <div class="nm">{{ $this->employee?->full_name ?? '—' }}</div>
                <div class="dt">
                    NIK: <b>{{ $this->employee?->employee_code ?? '—' }}</b><br>
                    Divisi/Posisi: {{ $this->employee?->position?->department?->department_name ?? '—' }} / {{ $this->employee?->position?->position_name ?? '—' }}
                </div>
            </x-print.party>
            <x-print.party label="PEMBAYARAN & KEHADIRAN">
                <div class="dt">
                    Metode:
                    @if ($this->bankAccount)
                        <b>{{ $this->bankAccount->bank_name }} ({{ $this->bankAccount->account_no }})</b>
                    @else
                        <b>CASH (TUNAI)</b>
                    @endif
                    <br>
                    Kehadiran: {{ $this->attendanceSummary['hadir'] }} Hadir, {{ $this->attendanceSummary['telat'] }} Telat, {{ $this->attendanceSummary['alpha'] }} Alpha
                </div>
            </x-print.party>
        </x-print.parties>

        <x-print.section number="1" title="Rincian Gaji">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;font-size:8.5pt">
                <div>
                    <h4 style="color:#1f6f4a;text-transform:uppercase;font-size:7.6pt;letter-spacing:.6px;border-bottom:1px solid #d4d8dd;padding-bottom:3px;margin-bottom:5px">(+) Pendapatan</h4>
                    <div style="display:flex;justify-content:space-between;padding:1.5px 0"><span>Gaji Pokok</span><span style="font-family:Consolas,monospace">Rp {{ number_format((float) $this->payslip->total_pokok, 0, ',', '.') }}</span></div>
                    <div style="display:flex;justify-content:space-between;padding:1.5px 0"><span>Tunjangan Keahlian</span><span style="font-family:Consolas,monospace">Rp {{ number_format((float) $this->payslip->total_tj_keahlian, 0, ',', '.') }}</span></div>
                    <div style="display:flex;justify-content:space-between;padding:1.5px 0"><span>Tunjangan Loyalitas</span><span style="font-family:Consolas,monospace">Rp {{ number_format((float) $this->payslip->total_tj_loyalitas, 0, ',', '.') }}</span></div>
                    <div style="display:flex;justify-content:space-between;padding:1.5px 0"><span>Tunjangan Prestasi</span><span style="font-family:Consolas,monospace">Rp {{ number_format((float) $this->payslip->total_tj_prestasi, 0, ',', '.') }}</span></div>
                    <div style="display:flex;justify-content:space-between;padding:1.5px 0"><span>Bonus Pagi + Hadir</span><span style="font-family:Consolas,monospace">Rp {{ number_format((float) $this->payslip->total_bonus_pagi + (float) $this->payslip->total_bonus_hadir, 0, ',', '.') }}</span></div>
                    <div style="display:flex;justify-content:space-between;padding:1.5px 0"><span>Uang Lembur</span><span style="font-family:Consolas,monospace">Rp {{ number_format((float) $this->payslip->total_overtime_pay, 0, ',', '.') }}</span></div>
                    <div style="display:flex;justify-content:space-between;padding:1.5px 0"><span>Uang Roti</span><span style="font-family:Consolas,monospace">Rp {{ number_format((float) $this->payslip->total_uang_roti, 0, ',', '.') }}</span></div>
                </div>
                <div>
                    <h4 style="color:#a3241f;text-transform:uppercase;font-size:7.6pt;letter-spacing:.6px;border-bottom:1px solid #d4d8dd;padding-bottom:3px;margin-bottom:5px">(-) Potongan & Denda</h4>
                    <div style="display:flex;justify-content:space-between;padding:1.5px 0"><span>Denda Presensi</span><span style="font-family:Consolas,monospace">Rp {{ number_format((float) $this->payslip->total_denda, 0, ',', '.') }}</span></div>
                    <div style="display:flex;justify-content:space-between;padding:1.5px 0"><span>BPJS Ketenagakerjaan+Kesehatan</span><span style="font-family:Consolas,monospace">Rp {{ number_format((float) $this->payslip->total_bpjs_employee, 0, ',', '.') }}</span></div>
                    <div style="display:flex;justify-content:space-between;padding:1.5px 0"><span>PPh 21</span><span style="font-family:Consolas,monospace">Rp {{ number_format((float) $this->payslip->total_pph21, 0, ',', '.') }}</span></div>
                </div>
            </div>
        </x-print.section>

        <div style="background:#e9f6ef;border:1px solid #a7e5bf;padding:10px 14px;border-radius:6px;display:flex;justify-content:space-between;align-items:center;margin-top:10px">
            <div>
                <div style="font-size:8pt;font-weight:700;color:#166534;text-transform:uppercase">Gaji Bersih Diterima (Net Salary)</div>
                @if ($this->bankAccount)
                    <div style="font-size:7pt;color:#166534">Ditransfer ke {{ $this->bankAccount->bank_name }} a.n {{ $this->bankAccount->account_holder_name }}</div>
                @endif
            </div>
            <div style="font-size:16pt;font-weight:800;font-family:Consolas,monospace;color:#14532d">Rp {{ number_format((float) $this->payslip->total_gaji_bersih, 0, ',', '.') }}</div>
        </div>

        <x-print.signatures :columns="'1fr 1fr'">
            <x-print.signature role="Penerima" :name="'( '.($this->employee?->full_name ?? '—').' )'" />
            <x-print.signature role="Finance / Payroll Dept" name="( Staff Finance )" />
        </x-print.signatures>

        <x-print.footer :page="'Slip #'.$this->payslipId">Dicetak {{ now()->translatedFormat('d F Y H:i') }} WIB</x-print.footer>
    </x-print.sheet>
</x-print.document>
