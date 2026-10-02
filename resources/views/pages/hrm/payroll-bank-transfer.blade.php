<?php

use App\Models\Master\EmpBankAccount;
use App\Models\Master\EmpEmployee;
use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Generator Transfer Bank -- interface diadopsi dari docs/payroll_timeattendance/
// mockup_05_bank_transfer.html (tabel NIK/Bank/Rekening/Nama/Net/Status +
// tombol download CSV). Bank ditentukan dari emp_bank_account aktif+primary
// (bukan kolom payment_method tm2hrm yang tidak ada di skema kita). Format
// CSV generik (belum format kliring resmi BCA/Mandiri -- itu perlu spek
// resmi dari masing-masing bank, dicatat sbg open item).
new #[Title('Bank Transfer Generator — TELASIH MAKMUR ERP')] class extends Component
{
    public ?int $runId = null;

    public function mount(?int $run = null): void
    {
        $this->runId = $run ?? PyrPayrollRun::whereIn('run_status', ['CONFIRMED', 'PAID', 'CLOSED'])->orderByDesc('period_start')->value('id_run');
    }

    #[Computed]
    public function availableRuns()
    {
        return PyrPayrollRun::whereIn('run_status', ['CONFIRMED', 'PAID', 'CLOSED'])->orderByDesc('period_start')->get();
    }

    #[Computed]
    public function run(): ?PyrPayrollRun
    {
        return $this->runId ? PyrPayrollRun::find($this->runId) : null;
    }

    #[Computed]
    public function rows()
    {
        if (! $this->run) {
            return collect();
        }

        $payslips = PyrPayslip::where('id_run', $this->runId)->where('has_anomaly', false)->get();
        $employees = EmpEmployee::whereIn('employee_id', $payslips->pluck('employee_id'))->get()->keyBy('employee_id');
        $bankAccounts = EmpBankAccount::whereIn('employee_id', $payslips->pluck('employee_id'))
            ->where('is_primary', true)->where('activate', true)->get()->keyBy('employee_id');

        return $payslips
            ->filter(fn (PyrPayslip $p) => $bankAccounts->has($p->employee_id))
            ->map(fn (PyrPayslip $p) => [
                'employee' => $employees->get($p->employee_id),
                'bank' => $bankAccounts->get($p->employee_id),
                'net' => (float) $p->total_gaji_bersih,
            ])
            ->sortBy(fn ($r) => $r['employee']?->full_name);
    }

    #[Computed]
    public function totalTransfer(): float
    {
        return $this->rows->sum('net');
    }

    public function downloadCsv()
    {
        $rows = $this->rows;
        $filename = 'transfer-bank-'.($this->run?->period_start->toDateString() ?? 'periode').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [t('Employee Code'), t('Name'), t('Bank'), t('Account No'), t('Account Holder'), t('Amount')]);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['employee']?->employee_code,
                    $r['employee']?->full_name,
                    $r['bank']?->bank_name,
                    $r['bank']?->account_no,
                    $r['bank']?->account_holder_name,
                    number_format($r['net'], 0, '', ''),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
};
?>

<div>
    <div class="max-w-[1200px] mx-auto my-5 px-4">
        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 p-4">
            <div class="flex justify-between items-center pb-3 border-b border-[#eef1f5] mb-4 flex-wrap gap-3">
                <div>
                    <h1 class="text-sm font-bold text-[#1a1d21]">{{ t('Payroll Transfer to Bank Account') }} ({{ $this->rows->count() }} {{ t('Employees') }})</h1>
                    <p class="text-[11px] text-muted mt-0.5">{{ t('Employees with an active bank account for the selected period') }}</p>
                </div>
                <div class="flex gap-2 items-center">
                    <x-form.select wire:model.live="runId" :placeholder="false"
                        :options="$this->availableRuns->mapWithKeys(fn ($r) => [$r->id_run => $r->period_start->format('d M Y').' — '.$r->period_end->format('d M Y')])" class="shrink-0" />
                    <button type="button" wire:click="downloadCsv" class="bg-navy text-white text-xs px-3 py-2 rounded font-semibold">📥 {{ t('Download CSV Batch') }}</button>
                </div>
            </div>

            <div class="bg-[#eaf0ff] border border-[#c7d7fb] p-3 rounded-lg mb-4 flex justify-between items-center">
                <span class="text-xs text-[#1e3a8a] font-semibold">{{ t('Total Transfer Value of This Batch') }}:</span>
                <span class="text-lg font-extrabold font-mono text-[#1e3a8a]">Rp {{ number_format($this->totalTransfer, 0, ',', '.') }}</span>
            </div>

            <x-grid.table-shell>
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr>
                        <th class="p-2 border-b border-[#eef1f5] bg-[#fafbfd] uppercase text-[11px] text-[#667]">{{ t('Code / Name') }}</th>
                        <th class="p-2 border-b border-[#eef1f5] bg-[#fafbfd] uppercase text-[11px] text-[#667]">{{ t('Bank') }}</th>
                        <th class="p-2 border-b border-[#eef1f5] bg-[#fafbfd] uppercase text-[11px] text-[#667]">{{ t('Account No') }}</th>
                        <th class="p-2 border-b border-[#eef1f5] bg-[#fafbfd] uppercase text-[11px] text-[#667]">{{ t('Account Holder') }}</th>
                        <th class="num p-2 border-b border-[#eef1f5] bg-[#fafbfd] uppercase text-[11px] text-[#667]">{{ t('Net Salary') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->rows as $r)
                        <tr class="border-b border-[#eef1f5]" wire:key="bt-{{ $r['employee']?->employee_id }}">
                            <td class="p-2 font-medium">{{ $r['employee']?->employee_code }} / {{ $r['employee']?->full_name }}</td>
                            <td class="p-2"><span class="bg-[#dbeafe] text-[#1e40af] font-bold px-2 py-0.5 rounded text-[10px]">{{ $r['bank']?->bank_name }}</span></td>
                            <td class="p-2 font-mono">{{ $r['bank']?->account_no }}</td>
                            <td class="p-2">{{ $r['bank']?->account_holder_name }}</td>
                            <td class="num p-2 font-mono font-bold text-[#166534]">Rp {{ number_format($r['net'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <x-grid.empty-row :colspan="5" :message="t('No employees with a bank account for this period.')" />
                    @endforelse
                </tbody>
            </table>
            </x-grid.table-shell>
        </div>
    </div>
</div>
