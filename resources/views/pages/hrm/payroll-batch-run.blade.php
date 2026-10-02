<?php

use App\Models\Master\EmpBankAccount;
use App\Models\Master\EmpEmployee;
use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
use App\Services\Payroll\PayrollEngineService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\OpensFromQuery;
use Livewire\Component;

// Payroll Batch Run -- interface diadopsi dari docs/payroll_timeattendance/
// mockup_03_payroll_batch.html (badge status, tombol Run Calculation,
// ringkasan Bank vs Cash, Finalize & Lock) TAPI siklus statusnya pakai
// PayrollEngineService kita (DRAFT->CALCULATED->VERIFIED->CONFIRMED->PAID->
// CLOSED, project_payroll_foundation_built.md) -- lebih panjang dari mockup
// yang cuma DRAFT->locked. Bank vs Cash ditentukan dari emp_bank_account
// aktif+primary (mockup 05/06), bukan kolom payment_method terpisah.
new #[Title('Payroll Batch Run — TELASIH MAKMUR ERP')] class extends Component
{
    use OpensFromQuery;

    protected function openFromQuery(int $id): void
    {
        if ($run = PyrPayrollRun::find($id)) {
            $this->periodType = $run->period_type;
            $this->selectedRunId = $run->id_run;
        }
    }

    // Layar ini tidak punya form terpisah: "new" = belum ada periode terpilih, siap Calculate periode baru.
    protected function newFromQuery(): void
    {
        $this->selectedRunId = null;
    }

    public string $periodType = 'MONTHLY';

    public string $periodStart = '';

    public string $periodEnd = '';

    public ?int $selectedRunId = null;

    public bool $showVerifyModal = false;

    public string $verifyMethod = 'FULL';

    public function mount(): void
    {
        $this->periodStart = now()->startOfMonth()->toDateString();
        $this->periodEnd = now()->endOfMonth()->toDateString();
        $this->selectedRunId = PyrPayrollRun::where('period_type', $this->periodType)
            ->orderByDesc('period_start')
            ->value('id_run');
    }

    #[Computed]
    public function runs()
    {
        return PyrPayrollRun::where('period_type', $this->periodType)->orderByDesc('period_start')->get();
    }

    #[Computed]
    public function run(): ?PyrPayrollRun
    {
        return $this->selectedRunId ? PyrPayrollRun::find($this->selectedRunId) : null;
    }

    // Rp tidak bisa di-SUM langsung di SQL (basic_salary/net terenkripsi,
    // docs/payroll_encryption_decision.md §4) -- decrypt via Eloquent lalu
    // jumlah di PHP.
    #[Computed]
    public function summary(): ?array
    {
        $run = $this->run;
        if (! $run || ! in_array($run->run_status, ['CALCULATED', 'VERIFIED', 'CONFIRMED', 'PAID', 'CLOSED'], true)) {
            return null;
        }

        $payslips = PyrPayslip::where('id_run', $run->id_run)->get();
        $bankEmployeeIds = EmpBankAccount::whereIn('employee_id', $payslips->pluck('employee_id'))
            ->where('is_primary', true)->where('activate', true)->pluck('employee_id')->all();

        $bank = $payslips->filter(fn (PyrPayslip $p) => in_array($p->employee_id, $bankEmployeeIds, true) && ! $p->has_anomaly);
        $cash = $payslips->filter(fn (PyrPayslip $p) => ! in_array($p->employee_id, $bankEmployeeIds, true) && ! $p->has_anomaly);
        $anomalies = $payslips->where('has_anomaly', true);

        return [
            'total_pegawai' => $payslips->count(),
            'bank_count' => $bank->count(),
            'bank_total' => $bank->sum(fn (PyrPayslip $p) => (float) $p->total_gaji_bersih),
            'cash_count' => $cash->count(),
            'cash_total' => $cash->sum(fn (PyrPayslip $p) => (float) $p->total_gaji_bersih),
            'grand_total' => $payslips->where('has_anomaly', false)->sum(fn (PyrPayslip $p) => (float) $p->total_gaji_bersih),
            'anomaly_count' => $anomalies->count(),
        ];
    }

    public function selectPeriodType(string $type): void
    {
        $this->periodType = $type;
        $this->selectedRunId = PyrPayrollRun::where('period_type', $type)->orderByDesc('period_start')->value('id_run');
        unset($this->runs, $this->run, $this->summary);
    }

    public function calculate(): void
    {
        $engine = app(PayrollEngineService::class);
        $run = $engine->calculate($this->periodType, Carbon::parse($this->periodStart), Carbon::parse($this->periodEnd), Auth::user()->login);
        $this->selectedRunId = $run->id_run;
        unset($this->runs, $this->run, $this->summary);
    }

    public function openVerify(): void
    {
        $this->verifyMethod = 'FULL';
        $this->showVerifyModal = true;
    }

    public function verify(): void
    {
        app(PayrollEngineService::class)->verify($this->run, $this->verifyMethod, null, Auth::user()->login);
        $this->showVerifyModal = false;
        unset($this->run, $this->summary);
    }

    public function confirm(): void
    {
        try {
            app(PayrollEngineService::class)->confirm($this->run, Auth::user()->login);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
        unset($this->run, $this->summary);
    }

    public function markPaid(): void
    {
        app(PayrollEngineService::class)->markPaid($this->run, Auth::user()->login);
        unset($this->run, $this->summary);
    }

    public function markPayslipsSent(): void
    {
        app(PayrollEngineService::class)->markPayslipsSent($this->run);
        unset($this->run);
    }

    public function close(): void
    {
        $nextRun = app(PayrollEngineService::class)->close($this->run, Auth::user()->login);
        $this->selectedRunId = $nextRun->id_run;
        unset($this->runs, $this->run, $this->summary);
    }

    public function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'DRAFT' => 'bg-[#e2e8f0] text-[#334155]',
            'CALCULATED' => 'bg-[#fef3c7] text-[#92400e]',
            'VERIFIED' => 'bg-[#dbeafe] text-[#1e40af]',
            'CONFIRMED' => 'bg-[#f3e8ff] text-[#6b21a8]',
            'PAID' => 'bg-[#dcfce7] text-[#166534]',
            'CLOSED' => 'bg-[#111827] text-white',
            default => 'bg-[#e2e8f0] text-[#334155]',
        };
    }
};
?>

<div>
    <div class="max-w-[1100px] mx-auto my-5 px-4">

        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 p-4">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h1 class="text-sm font-bold text-[#1a1d21]">{{ t('Batch Payroll Calculation Run') }}</h1>
                    <p class="text-[11px] text-muted mt-0.5">{{ t('Evaluates daily attendance against the rule matrix & splits the payment method (Bank vs Cash)') }}</p>
                </div>
                @if ($this->run)
                    <span class="text-[10px] font-bold px-3 py-1 rounded-full {{ $this->statusBadgeClass($this->run->run_status) }}">{{ t('Status') }}: {{ $this->run->run_status }}</span>
                @endif
            </div>

            <div class="flex gap-2 mb-4">
                <button type="button" wire:click="selectPeriodType('MONTHLY')" class="px-3 py-1.5 rounded text-xs font-semibold {{ $periodType === 'MONTHLY' ? 'bg-navy text-white' : 'bg-[#e6eaf0] text-[#333]' }}">{{ t('Monthly') }}</button>
                <button type="button" wire:click="selectPeriodType('WEEKLY')" class="px-3 py-1.5 rounded text-xs font-semibold {{ $periodType === 'WEEKLY' ? 'bg-navy text-white' : 'bg-[#e6eaf0] text-[#333]' }}">{{ t('Weekly') }}</button>
            </div>

            @if ($this->runs->isNotEmpty())
                <div class="mb-4">
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ t('Select Period') }}</label>
                    <x-form.select wire:model.live="selectedRunId" :placeholder="false"
                        :options="$this->runs->mapWithKeys(fn ($r) => [$r->id_run => $r->period_start->format('d M Y').' — '.$r->period_end->format('d M Y').' ('.$r->run_status.')'])" />
                </div>
            @endif

            <div class="bg-[#fafbfd] border border-[#eef1f5] p-4 rounded-lg mb-4">
                <div class="flex justify-between items-center gap-4 flex-wrap">
                    <div>
                        <h2 class="font-bold text-xs">{{ t('Run Payroll Calculation') }}</h2>
                        <p class="text-[11px] text-muted mt-0.5">{{ t('Calculates Base Salary, Allowances, Overtime, Meal Allowance, BPJS/PPh21 (if active), and Penalties') }}</p>
                    </div>
                    <div class="flex gap-2">
                        <x-form.date-picker wire:model="periodStart" name="periodStart" />
                        <x-form.date-picker wire:model="periodEnd" name="periodEnd" />
                        @if (! $this->run || in_array($this->run->run_status, ['DRAFT', 'CALCULATED'], true))
                            <button type="button" wire:click="calculate" wire:loading.attr="disabled" class="bg-navy text-white text-xs px-4 py-2 rounded font-semibold">⚡ {{ t('Run Calculation') }}</button>
                        @endif
                    </div>
                </div>
            </div>

            @if ($this->summary)
                <x-grid.stat-row :count="4" class="mb-4">
                    <x-grid.stat-box tone="total" :label="t('Total Employees')" :value="$this->summary['total_pegawai'].' '.t('ppl')" />
                    <x-grid.stat-box tone="active" :label="t('Salary via Bank Transfer')" :value="$this->summary['bank_count'].' '.t('ppl')" :subtitle="'Rp '.number_format($this->summary['bank_total'], 0, ',', '.')" />
                    <x-grid.stat-box tone="warn" :label="t('Salary via Cash')" :value="$this->summary['cash_count'].' '.t('ppl')" :subtitle="'Rp '.number_format($this->summary['cash_total'], 0, ',', '.')" />
                    <x-grid.stat-box tone="extra" :label="t('Total Net Salary')" :value="'Rp '.number_format($this->summary['grand_total'], 0, ',', '.')" />
                </x-grid.stat-row>

                @if ($this->summary['anomaly_count'] > 0)
                    <div class="bg-[#fee2e2] border border-[#f5b5b5] text-[#991b1b] text-xs font-semibold px-3 py-2 rounded mb-4">
                        ⚠ {{ str_replace(':n', $this->summary['anomaly_count'], t(':n anomalous employee(s) (no active salary rate yet) — excluded from the total, check Employee Salary & Tax Master.')) }}
                    </div>
                @endif
            @endif

            @if (session('error'))
                <div class="bg-[#fee2e2] border border-[#f5b5b5] text-[#991b1b] text-xs font-semibold px-3 py-2 rounded mb-4">{{ session('error') }}</div>
            @endif

            @if ($this->run)
                <div class="flex justify-end gap-2 pt-3 border-t border-[#eef1f5]">
                    @if ($this->run->run_status === 'CALCULATED')
                        <button type="button" wire:click="openVerify" class="bg-[#2f6fed] text-white text-xs px-4 py-2 rounded font-semibold">✓ {{ t('Verify Attendance') }}</button>
                    @endif
                    @if ($this->run->run_status === 'VERIFIED')
                        <button type="button" wire:click="confirm" class="bg-[#6b21a8] text-white text-xs px-4 py-2 rounded font-semibold">🔒 {{ t('Confirm (Lock Attendance)') }}</button>
                    @endif
                    @if ($this->run->run_status === 'CONFIRMED')
                        <button type="button" wire:click="markPaid" wire:confirm="{{ t('Confirm the payment has been executed (bank transfer/cash)?') }}" class="bg-[#166534] text-white text-xs px-4 py-2 rounded font-semibold">💰 {{ t('Mark as Paid') }}</button>
                    @endif
                    @if ($this->run->run_status === 'PAID')
                        @if (! $this->run->payslip_sent_at)
                            <button type="button" wire:click="markPayslipsSent" class="bg-[#0369a1] text-white text-xs px-4 py-2 rounded font-semibold">📧 {{ t('Mark Payslips Sent') }}</button>
                        @endif
                        <button type="button" wire:click="close" wire:confirm="{{ t('Close this period? The next period will be created automatically.') }}" class="bg-[#111827] text-white text-xs px-4 py-2 rounded font-semibold">✅ {{ t('Close Period') }}</button>
                    @endif
                    @if ($this->run->run_status === 'CLOSED')
                        <span class="text-xs text-muted italic py-2">{{ t('This period has been closed') }}{{ $this->run->closed_at ? ' '.t('on').' '.$this->run->closed_at->format('d M Y H:i') : '' }}.</span>
                    @endif
                </div>
            @endif
        </div>
    </div>

    @if ($showVerifyModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showVerifyModal', false)">
            <div class="bg-white rounded-xl w-[420px] max-w-[92vw] shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold">{{ t('Verify Clock In/Out') }}</div>
                <div class="p-4 flex flex-col gap-3">
                    <label class="flex items-center gap-2 text-xs cursor-pointer">
                        <input type="radio" wire:model="verifyMethod" value="FULL"> {{ t('Check all employees') }} (FULL)
                    </label>
                    <label class="flex items-center gap-2 text-xs cursor-pointer">
                        <input type="radio" wire:model="verifyMethod" value="RANDOM_SAMPLE"> {{ t('Check a random sample') }} (RANDOM_SAMPLE)
                    </label>
                    <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                        <button type="button" wire:click="$set('showVerifyModal', false)" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">{{ t('Cancel') }}</button>
                        <button type="button" wire:click="verify" class="px-4 py-2 rounded-md text-xs font-semibold bg-navy text-white cursor-pointer">{{ t('Confirm Checked') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
