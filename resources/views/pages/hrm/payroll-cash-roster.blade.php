<?php

use App\Models\Master\EmpBankAccount;
use App\Models\Master\EmpEmployee;
use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Roster Cash + Pecahan Uang -- interface diadopsi dari docs/payroll_timeattendance/
// mockup_06_cash_disbursement.html (kalkulator pecahan uang + tabel serah
// terima dgn kolom tanda tangan). Pegawai "cash" = TIDAK punya
// emp_bank_account aktif+primary (kebalikan dari payroll-bank-transfer).
new #[Title('Roster Cash & Pecahan Uang — TELASIH MAKMUR ERP')] class extends Component
{
    private const DENOMINATIONS = [100000, 50000, 20000, 10000, 5000, 2000, 1000];

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
        $employees = EmpEmployee::with('position.department')->whereIn('employee_id', $payslips->pluck('employee_id'))->get()->keyBy('employee_id');
        $bankedIds = EmpBankAccount::whereIn('employee_id', $payslips->pluck('employee_id'))
            ->where('is_primary', true)->where('activate', true)->pluck('employee_id')->all();

        return $payslips
            ->reject(fn (PyrPayslip $p) => in_array($p->employee_id, $bankedIds, true))
            ->map(fn (PyrPayslip $p) => [
                'employee' => $employees->get($p->employee_id),
                'net' => (float) $p->total_gaji_bersih,
                'breakdown' => $this->denominationBreakdown((int) $p->total_gaji_bersih),
            ])
            ->sortBy(fn ($r) => $r['employee']?->full_name);
    }

    #[Computed]
    public function totalCash(): float
    {
        return $this->rows->sum('net');
    }

    #[Computed]
    public function denominationTotals(): array
    {
        $totals = array_fill_keys(self::DENOMINATIONS, 0);
        foreach ($this->rows as $r) {
            foreach ($r['breakdown'] as $denom => $count) {
                $totals[$denom] += $count;
            }
        }

        return $totals;
    }

    private function denominationBreakdown(int $amount): array
    {
        $breakdown = [];
        foreach (self::DENOMINATIONS as $denom) {
            $count = intdiv($amount, $denom);
            if ($count > 0) {
                $breakdown[$denom] = $count;
                $amount -= $count * $denom;
            }
        }

        return $breakdown;
    }

    public function formatBreakdown(array $breakdown): string
    {
        return collect($breakdown)->map(fn ($count, $denom) => $count.'x '.number_format($denom / 1000).'k')->implode(', ');
    }
};
?>

<div>
    <div class="max-w-[1200px] mx-auto my-5 px-4">
        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 p-4">
            <div class="flex justify-between items-center pb-3 border-b border-[#eef1f5] mb-4 flex-wrap gap-3">
                <div>
                    <h1 class="text-sm font-bold text-[#1a1d21]">Pembagian Gaji Tunai / Cash ({{ $this->rows->count() }} Pegawai)</h1>
                    <p class="text-[11px] text-muted mt-0.5">Pegawai tanpa rekening bank aktif + rincian pecahan uang</p>
                </div>
                <div class="flex gap-2 items-center">
                    <select wire:model.live="runId" class="border border-[#d7dbe2] rounded text-xs px-2 py-1.5">
                        @foreach ($this->availableRuns as $r)
                            <option value="{{ $r->id_run }}">{{ $r->period_start->format('d M Y') }} — {{ $r->period_end->format('d M Y') }}</option>
                        @endforeach
                    </select>
                    <span class="text-[10px] font-bold px-3 py-1.5 rounded-full bg-[#fef3c7] text-[#92400e] border border-[#f5d98a]">Total Cash: Rp {{ number_format($this->totalCash, 0, ',', '.') }}</span>
                    <button type="button" onclick="window.print()" class="bg-navy text-white text-xs px-3 py-2 rounded font-semibold">🖨️ Cetak Serah Terima</button>
                </div>
            </div>

            <div class="bg-[#fef3c7] border border-[#f5d98a] p-3 rounded-lg mb-4">
                <h3 class="font-bold text-[11px] text-[#92400e] mb-2 uppercase">Kebutuhan Pecahan Lembar Uang</h3>
                <div class="grid grid-cols-7 gap-2 text-[11px] font-mono">
                    @foreach ($this->denominationTotals as $denom => $count)
                        <div class="bg-white p-2 rounded border border-[#eef1f5]">Rp {{ number_format($denom) }}: <strong class="text-[#92400e]">{{ $count }} Lbr</strong></div>
                    @endforeach
                </div>
            </div>

            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr>
                        <th class="p-2 border-b border-[#eef1f5] bg-[#fafbfd] uppercase text-[11px] text-[#667]">Kode / Nama</th>
                        <th class="p-2 border-b border-[#eef1f5] bg-[#fafbfd] uppercase text-[11px] text-[#667]">Divisi</th>
                        <th class="p-2 border-b border-[#eef1f5] bg-[#fafbfd] uppercase text-[11px] text-[#667]">Gaji Bersih</th>
                        <th class="p-2 border-b border-[#eef1f5] bg-[#fafbfd] uppercase text-[11px] text-[#667]">Rincian Amplop</th>
                        <th class="p-2 border-b border-[#eef1f5] bg-[#fafbfd] uppercase text-[11px] text-[#667] text-center">Tanda Tangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->rows as $r)
                        <tr class="border-b border-[#eef1f5]" wire:key="cr-{{ $r['employee']?->employee_id }}">
                            <td class="p-2 font-medium">{{ $r['employee']?->employee_code }} / {{ $r['employee']?->full_name }}</td>
                            <td class="p-2">{{ $r['employee']?->position?->department?->department_name ?? '—' }}</td>
                            <td class="p-2 font-mono font-bold text-[#166534]">Rp {{ number_format($r['net'], 0, ',', '.') }}</td>
                            <td class="p-2 font-mono text-[11px]">{{ $this->formatBreakdown($r['breakdown']) }}</td>
                            <td class="p-2 text-center text-muted">___________________</td>
                        </tr>
                    @empty
                        <x-grid.empty-row :colspan="5" message="Tidak ada pegawai cash utk periode ini." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
