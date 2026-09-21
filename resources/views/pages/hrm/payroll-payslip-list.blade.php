<?php

use App\Livewire\Concerns\HasGridPagination;
use App\Models\Master\EmpBankAccount;
use App\Models\Master\EmpEmployee;
use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// Pintu masuk cetak/kirim slip gaji per periode -- sebelum layar ini ada,
// route hrm.payroll.payslip-print tidak dipanggil dari mana pun sehingga slip
// gaji sama sekali tak bisa diakses officer.
//
// Baris diurut nama pegawai, yang berarti pengurutan harus terjadi di PHP:
// payslip ada di tm2pyr sedangkan namanya di tm2mst, dan Eloquent tak bisa
// join lintas koneksi (CLAUDE.md §2 pola 3). Jumlah slip per periode seukuran
// jumlah karyawan (ratusan), jadi ambil-semua lalu paginate koleksi masih
// murah dan menghindari 2 sumber kebenaran urutan.
new #[Title('Cetak & Kirim Slip Gaji — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridPagination;
    use WithPagination;

    public ?int $runId = null;

    public string $search = '';

    public function mount(?int $run = null): void
    {
        $this->runId = $run ?? PyrPayrollRun::whereIn('run_status', ['CALCULATED', 'VERIFIED', 'CONFIRMED', 'PAID', 'CLOSED'])
            ->orderByDesc('period_start')->value('id_run');
    }

    public function updatedRunId(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function availableRuns()
    {
        return PyrPayrollRun::whereIn('run_status', ['CALCULATED', 'VERIFIED', 'CONFIRMED', 'PAID', 'CLOSED'])
            ->orderByDesc('period_start')->get();
    }

    #[Computed]
    public function run(): ?PyrPayrollRun
    {
        return $this->runId ? PyrPayrollRun::find($this->runId) : null;
    }

    /** Semua slip periode ini + data pegawainya, sudah terurut nama. */
    #[Computed]
    public function allRows()
    {
        if (! $this->runId) {
            return collect();
        }

        $payslips = PyrPayslip::where('id_run', $this->runId)->get();
        $employees = EmpEmployee::with('position.department')
            ->whereIn('employee_id', $payslips->pluck('employee_id'))->get()->keyBy('employee_id');
        $banks = EmpBankAccount::whereIn('employee_id', $payslips->pluck('employee_id'))
            ->where('is_primary', true)->where('activate', true)->get()->keyBy('employee_id');

        return $payslips
            ->map(fn (PyrPayslip $p) => [
                'payslip' => $p,
                'employee' => $employees->get($p->employee_id),
                'bank' => $banks->get($p->employee_id),
            ])
            ->sortBy(fn ($r) => $r['employee']?->full_name ?? '')
            ->values();
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $filtered = $this->allRows;

        if ($term = trim($this->search)) {
            $filtered = $filtered->filter(function ($r) use ($term) {
                $haystack = ($r['employee']?->full_name ?? '').' '.($r['employee']?->employee_code ?? '');

                return mb_stripos($haystack, $term) !== false;
            })->values();
        }

        $page = $this->getPage();

        return new LengthAwarePaginator(
            $filtered->forPage($page, $this->perPage)->values(),
            $filtered->count(),
            $this->perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    #[Computed]
    public function stats(): array
    {
        $rows = $this->allRows;

        return [
            'total' => $rows->count(),
            'belum_cetak' => $rows->filter(fn ($r) => $r['payslip']->print_flag && ! $r['payslip']->printed_at)->count(),
            'belum_wa' => $rows->filter(fn ($r) => $r['payslip']->wa_flag && ! $r['payslip']->wa_sent_at)->count(),
            'wa_kosong' => $rows->filter(fn ($r) => blank($r['employee']?->phone))->count(),
            'anomali' => $rows->filter(fn ($r) => $r['payslip']->has_anomaly)->count(),
        ];
    }

    #[Computed]
    public function printableCount(): int
    {
        return $this->allRows->filter(fn ($r) => $r['payslip']->print_flag && ! $r['payslip']->has_anomaly)->count();
    }

    public function togglePrintFlag(int $payslipId): void
    {
        $slip = PyrPayslip::findOrFail($payslipId);
        $slip->print_flag = ! $slip->print_flag;
        $slip->save();

        unset($this->allRows, $this->rows, $this->stats, $this->printableCount);
    }

    // Kanal WA baru dinyalakan bertahap, dan hanya masuk akal kalau nomornya ada.
    public function toggleWaFlag(int $payslipId): void
    {
        $slip = PyrPayslip::findOrFail($payslipId);
        $phone = EmpEmployee::where('employee_id', $slip->employee_id)->value('phone');

        if (blank($phone)) {
            session()->flash('error', 'Pegawai ini belum punya nomor WhatsApp — lengkapi dulu di Master Employee.');

            return;
        }

        $slip->wa_flag = ! $slip->wa_flag;
        $slip->save();

        unset($this->allRows, $this->rows, $this->stats, $this->printableCount);
    }

    public function openPayslip(int $payslipId): void
    {
        $this->redirectRoute('hrm.payroll.payslip-print', ['payslip' => $payslipId], navigate: false);
    }

    public function printAll(): void
    {
        $this->redirectRoute('hrm.payroll.payslip-print-all', ['run' => $this->runId], navigate: false);
    }
};
?>

<div>
    <div class="max-w-[1480px] mx-auto my-5 px-4">

        <x-grid.stat-row :count="5">
            <x-grid.stat-box tone="total" label="Total Slip Periode Ini" :value="$this->stats['total'].' Slip'" />
            <x-grid.stat-box tone="warn" label="Belum Dicetak" :value="$this->stats['belum_cetak'].' Slip'" />
            <x-grid.stat-box tone="extra" label="Belum Dikirim WA" :value="$this->stats['belum_wa'].' Slip'" />
            <x-grid.stat-box tone="critical" label="No. WA Kosong" :value="$this->stats['wa_kosong'].' Pegawai'" />
            <x-grid.stat-box tone="critical" label="Anomali (Tak Bisa Cetak)" :value="$this->stats['anomali'].' Slip'" />
        </x-grid.stat-row>

        @if (session('error'))
            <div class="bg-[#fee2e2] border border-[#f5b5b5] text-[#991b1b] text-xs font-semibold px-3 py-2 rounded mb-3">{{ session('error') }}</div>
        @endif

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama / kode pegawai…"
                        class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <x-slot:tools>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <span class="text-[10px] font-bold uppercase text-muted">Periode</span>
                        <x-form.select wire:model.live="runId" :placeholder="false" class="shrink-0"
                            :options="$this->availableRuns->mapWithKeys(fn ($r) => [$r->id_run => $r->period_start->format('d M Y').' — '.$r->period_end->format('d M Y').' ('.$r->run_status.')'])" />
                    </div>
                </x-slot:tools>
                <x-slot:newRecord>
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button" wire:click="printAll"
                            class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">
                            <i class="fa-solid fa-print"></i> Cetak Semua ({{ $this->printableCount }})
                        </button>
                        <button type="button" disabled title="Kanal WhatsApp belum dibangun"
                            class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-white text-[#94a3b8] border border-[#d7dbe2] cursor-not-allowed">
                            Kirim WA Semua
                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-[#eef1f5] text-[#8a94a6]">NANTI</span>
                        </button>
                    </div>
                </x-slot:newRecord>
            </x-grid.toolbar>

            <div class="bg-[#fafbfd] border-b border-[#eef1f5] px-3 py-1.5 text-[11px] text-muted">
                Slip gaji lahir otomatis dari <span class="font-semibold text-navy">Perhitungan Gaji</span> saat periode dikalkulasi — tidak dibuat manual dari layar ini.
            </div>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Pegawai</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">Divisi / Posisi</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">No. WhatsApp</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[95px]">Metode Bayar</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[115px]">Gaji Bersih</th>
                            <th class="text-center px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[105px]">Printout</th>
                            <th class="text-center px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[105px]">Kirim WA</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[70px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rows as $r)
                            @php($slip = $r['payslip'])
                            @php($emp = $r['employee'])
                            <tr wire:key="slip-{{ $slip->id_payslip }}"
                                @if (! $slip->has_anomaly) wire:dblclick="openPayslip({{ $slip->id_payslip }})" @endif
                                class="{{ $slip->has_anomaly ? 'bg-[#fdecec]' : 'hover:bg-[#f7faff] cursor-pointer' }}"
                                title="Klik 2x untuk buka">
                                <x-grid.identity-cell :id="$slip->employee_id" :name="$emp?->full_name ?? '—'" :code="$emp?->employee_code" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    {{ $emp?->position?->department?->department_name ?? '—' }} / {{ $emp?->position?->position_name ?? '—' }}
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if (filled($emp?->phone))
                                        <span class="font-mono text-[11.5px]">{{ $emp->phone }}</span>
                                    @else
                                        <span class="text-[10.5px] text-[#c0392b] font-semibold">— belum diisi —</span>
                                        <a href="{{ route('hrm.employee') }}" class="block text-[10px] text-accent underline">Lengkapi di Master Employee</a>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if ($r['bank'])
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#dbeafe] text-[#1e40af]">BANK</span>
                                    @else
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#fef6e7] text-[#b45309]">CASH</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold font-mono {{ $slip->has_anomaly ? 'text-[#c0392b]' : '' }}">
                                    {{ number_format((float) $slip->total_gaji_bersih, 0, ',', '.') }}
                                </td>

                                @if ($slip->has_anomaly)
                                    <td class="px-3 py-2 border-b border-[#eef1f5] text-center" colspan="2">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#fdecec] text-[#c0392b]">ANOMALI</span>
                                        <div class="text-[10px] text-[#a3453f] mt-0.5">{{ $slip->anomaly_note ?: 'Rate gaji tidak aktif — slip belum sah' }}</div>
                                    </td>
                                @else
                                    <td class="px-3 py-2 border-b border-[#eef1f5] text-center">
                                        <x-grid.tickmark :on="$slip->print_flag" action="togglePrintFlag({{ $slip->id_payslip }})"
                                            title="{{ $slip->print_flag ? 'Slip ini dicetak' : 'Slip ini tidak dicetak' }}" />
                                        @if ($slip->printed_at)
                                            <div class="text-[10px] text-muted mt-0.5">{{ $slip->printed_at->translatedFormat('d M H:i') }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5] text-center">
                                        <x-grid.tickmark :on="$slip->wa_flag" :disabled="blank($emp?->phone)"
                                            action="toggleWaFlag({{ $slip->id_payslip }})"
                                            title="{{ blank($emp?->phone) ? 'Tidak ada nomor WA' : ($slip->wa_flag ? 'Slip ini dikirim via WA' : 'Slip ini tidak dikirim via WA') }}" />
                                        @if ($slip->wa_sent_at)
                                            <div class="text-[10px] text-muted mt-0.5">{{ $slip->wa_sent_at->translatedFormat('d M H:i') }}</div>
                                        @endif
                                    </td>
                                @endif

                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    @if ($slip->has_anomaly)
                                        <span title="Tidak bisa dicetak — perbaiki dulu di Master Gaji & Pajak Pegawai"
                                            class="inline-flex items-center justify-center w-7 h-7 rounded-md text-slate-300 cursor-not-allowed">↗</span>
                                    @else
                                        <x-grid.action-icon icon="↗" title="Buka / Cetak" action="openPayslip({{ $slip->id_payslip }})" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="8" message="Belum ada slip gaji untuk periode ini." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>

            @include('components.grid.pagination-footer', ['paginator' => $this->rows])
        </div>
    </div>
</div>
