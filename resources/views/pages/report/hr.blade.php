<?php

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpLeaveRequest;
use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
use App\Support\DataOrigin\Visible;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// Laporan SDM & Payroll -- salah satu "macam laporan" tambahan (keputusan
// user 2026-09-13, lihat catatan lengkap di report/accounting.blade.php).
// Data nyata dari tm2mst.emp_employee + tm2pyr. total_gaji_bersih terenkripsi
// (App\Casts\PayrollEncrypted) -- dijumlah di PHP setelah decrypt otomatis
// lewat accessor Eloquent, BUKAN SUM() SQL (tidak bisa, nilainya ciphertext
// di kolom). Ini layar Direksi-only (module:MGMT), jadi menampilkan TOTAL
// agregat gaji perusahaan konsisten dengan aturan "HR officer cuma tahu
// total keseluruhan, Direksi boleh lihat" (project_payroll_foundation_built).
new #[Layout('layouts::report')] #[Title('Laporan SDM & Payroll — TELASIH MAKMUR ERP')] class extends Component
{
    #[Computed]
    public function headcount(): array
    {
        return [
            'active' => EmpEmployee::where('activate', true)->where('work_status', 'WORKING')->count(),
            'cuti' => EmpEmployee::where('activate', true)->where('work_status', 'CUTI')->count(),
            'dirumahkan' => EmpEmployee::where('activate', true)->where('work_status', 'DIRUMAHKAN')->count(),
            'total' => EmpEmployee::where('activate', true)->count(),
        ];
    }

    #[Computed]
    public function byDepartment(): array
    {
        return Visible::apply(DB::connection('tm2mst')->table('emp_employee as e')
            ->join('org_position as p', 'p.position_id', '=', 'e.position_id'), 'tm2mst', 'emp_employee', 'e')
            ->join('org_department as d', 'd.department_id', '=', 'p.department_id')
            ->where('e.activate', true)
            ->selectRaw('d.department_name, COUNT(*) as n')
            ->groupBy('d.department_name')
            ->orderByDesc('n')
            ->limit(8)
            ->get()
            ->map(fn ($r) => ['name' => $r->department_name, 'n' => (int) $r->n])
            ->all();
    }

    #[Computed]
    public function latestRun(): ?PyrPayrollRun
    {
        return PyrPayrollRun::orderByDesc('period_start')->first();
    }

    #[Computed]
    public function latestRunNet(): float
    {
        if (! $this->latestRun) {
            return 0;
        }

        // total_gaji_bersih terenkripsi -- jumlah di PHP, dataset 1 run
        // (ratusan payslip) cukup ringan utk di-load penuh.
        return PyrPayslip::where('id_run', $this->latestRun->id_run)
            ->get()
            ->sum(fn (PyrPayslip $p) => (float) $p->total_gaji_bersih);
    }

    #[Computed]
    public function pendingLeave(): int
    {
        return EmpLeaveRequest::where('status', 'PENDING')->count();
    }

    #[Computed]
    public function leaveByType(): array
    {
        return EmpLeaveRequest::where('status', 'APPROVED')
            ->whereYear('date_from', now()->year)
            ->selectRaw('leave_type, COUNT(*) as n')
            ->groupBy('leave_type')
            ->orderByDesc('n')
            ->get()
            ->map(fn ($r) => ['type' => $r->leave_type, 'n' => (int) $r->n])
            ->all();
    }

    #[Computed]
    public function runStatusHistory(): array
    {
        return PyrPayrollRun::orderByDesc('period_start')->limit(6)->get()
            ->map(fn ($r) => [
                'period' => $r->period_start->format('M Y').' ('.$r->period_type.')',
                'status' => $r->run_status,
            ])->all();
    }
};
?>

@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $miliar = fn ($v) => 'Rp ' . number_format((float) $v / 1_000_000_000, 2, ',', '.') . ' M';
    $statusBadge = fn ($s) => match ($s) {
        'CONFIRMED', 'PAID', 'CLOSED' => 'ok', 'VERIFIED' => 'info', 'DRAFT' => 'warn', default => 'info',
    };
@endphp

<div class="p-4 sm:p-6" style="background:var(--content-bg)">
    <div class="mb-5">
        <h1 class="text-[22px] font-extrabold -tracking-wide" style="color:var(--sh-ink-strong)">Laporan SDM &amp; Payroll</h1>
        <p class="text-[13px] mt-0.5" style="color:var(--sh-muted)">Headcount, cuti, dan ringkasan penggajian — CV. Telasih Makmur.</p>
    </div>

    <div class="[display:grid] grid-cols-2 lg:grid-cols-4 gap-3.5 mb-4">
        <x-report.kpi label="Pegawai Aktif Bekerja" icon="fa-solid fa-users" tone="blue" :value="number_format($this->headcount['active'])" :note="$this->headcount['total'].' total tercatat'" />
        <x-report.kpi label="Sedang Cuti / Dirumahkan" icon="fa-solid fa-house-user" tone="amber" :value="number_format($this->headcount['cuti'] + $this->headcount['dirumahkan'])" note="Tidak aktif bekerja sementara" />
        <x-report.kpi label="Payroll Terakhir (Net)" icon="fa-solid fa-money-check-dollar" tone="green" :value="$miliar($this->latestRunNet)" :note="$this->latestRun ? $this->latestRun->period_start->format('M Y').' · '.$this->latestRun->run_status : 'Belum ada run'" />
        <x-report.kpi label="Pengajuan Cuti Pending" icon="fa-solid fa-triangle-exclamation" tone="danger" :value="number_format($this->pendingLeave)" note="Menunggu approval HR" />
    </div>

    <div class="[display:grid] grid-cols-1 lg:grid-cols-[1.4fr_1fr] gap-3.5 mb-4">
        <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
            <h2 class="font-bold text-[14px] mb-3" style="color:var(--sh-ink-strong)">Pegawai Aktif per Departemen (Top 8)</h2>
            @foreach ($this->byDepartment as $d)
                <x-report.progress-row :label="$d['name']" :pct="$this->headcount['active'] > 0 ? round($d['n'] / $this->headcount['active'] * 100, 1) : 0" :value="$d['n'].' org'" color="var(--sh-accent)" />
            @endforeach
        </div>
        <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
            <h2 class="font-bold text-[14px] mb-3" style="color:var(--sh-ink-strong)">Riwayat Payroll Run</h2>
            <table class="w-full text-[12px]">
                <thead>
                    <tr class="text-left border-b" style="border-color:var(--sh-border)">
                        <th class="pb-2 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Periode</th>
                        <th class="pb-2 font-bold uppercase text-[10px] text-right" style="color:var(--sh-muted)">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->runStatusHistory as $r)
                        <tr class="border-b" style="border-color:var(--sh-border)">
                            <td class="py-2 font-semibold" style="color:var(--sh-ink-strong)">{{ $r['period'] }}</td>
                            <td class="py-2 text-right"><x-report.badge :tone="$statusBadge($r['status'])">{{ $r['status'] }}</x-report.badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="py-3 text-center" style="color:var(--sh-muted)">Belum ada payroll run.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
        <h2 class="font-bold text-[14px] mb-3" style="color:var(--sh-ink-strong)">Cuti/Izin Disetujui Tahun Ini per Jenis</h2>
        <div class="flex flex-wrap gap-2">
            @forelse ($this->leaveByType as $l)
                <span class="px-3 py-1.5 rounded-lg text-[11.5px] font-semibold" style="background:var(--sh-surface-alt, var(--content-bg));border:1px solid var(--sh-border);color:var(--sh-ink)">{{ $l['type'] }} <b style="color:var(--sh-accent)">{{ $l['n'] }}</b></span>
            @empty
                <p class="text-[12px]" style="color:var(--sh-muted)">Belum ada cuti/izin disetujui tahun ini.</p>
            @endforelse
        </div>
    </div>
</div>
