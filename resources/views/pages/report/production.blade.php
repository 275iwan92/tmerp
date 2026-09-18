<?php

use App\Support\DataOrigin\Visible;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// Laporan Produksi & Manufaktur -- salah satu "macam laporan" tambahan
// (keputusan user 2026-09-13, lihat catatan lengkap di report/accounting.blade.php).
// Data nyata dari tm2prd (prd_wo/prd_wo_operation), TIDAK ada angka mockup.
new #[Layout('layouts::report')] #[Title('Laporan Produksi — TELASIH MAKMUR ERP')] class extends Component
{
    #[Url(as: 'th')]
    public int $year = 0;

    public function mount(): void
    {
        if ($this->year === 0) {
            $this->year = (int) now()->year;
        }
    }

    #[Computed]
    public function woByStatus(): array
    {
        $rows = Visible::apply(DB::connection('tm2prd')->table('prd_wo'), 'tm2prd', 'prd_wo')
            ->whereYear('created_at', $this->year)
            ->selectRaw('wo_status, COUNT(*) as n')
            ->groupBy('wo_status')
            ->pluck('n', 'wo_status');

        return [
            'DRAFT' => (int) ($rows['DRAFT'] ?? 0),
            'SCHEDULED' => (int) ($rows['SCHEDULED'] ?? 0),
            'IN_PROGRESS' => (int) ($rows['IN_PROGRESS'] ?? 0),
            'ON_HOLD' => (int) ($rows['ON_HOLD'] ?? 0),
            'COMPLETED' => (int) ($rows['COMPLETED'] ?? 0),
            'CANCELLED' => (int) ($rows['CANCELLED'] ?? 0),
        ];
    }

    #[Computed]
    public function totalWo(): int
    {
        return array_sum($this->woByStatus);
    }

    #[Computed]
    public function overdueOpen(): int
    {
        return Visible::apply(DB::connection('tm2prd')->table('prd_wo'), 'tm2prd', 'prd_wo')
            ->whereIn('wo_status', ['SCHEDULED', 'IN_PROGRESS', 'ON_HOLD'])
            ->where('due_date', '<', now())
            ->count();
    }

    #[Computed]
    public function outputDefectRate(): ?float
    {
        $good = (float) Visible::apply(DB::connection('tm2prd')->table('prd_wo_operation_output'), 'tm2prd', 'prd_wo_operation_output')
            ->whereYear('created_at', $this->year)
            ->sum('qty_good');

        $reject = (float) Visible::apply(DB::connection('tm2prd')->table('prd_wo_operation_output_defect as d')
            ->join('prd_wo_operation_output as o', 'o.output_id', '=', 'd.output_id'), 'tm2prd', 'prd_wo_operation_output', 'o')
            ->whereYear('o.created_at', $this->year)
            ->sum('d.qty');

        if (($good + $reject) <= 0) {
            return null;
        }

        return round(($reject / ($good + $reject)) * 100, 2);
    }

    #[Computed]
    public function monthlyCompleted(): array
    {
        $rows = Visible::apply(DB::connection('tm2prd')->table('prd_wo'), 'tm2prd', 'prd_wo')
            ->where('wo_status', 'COMPLETED')
            ->whereYear('due_date', $this->year)
            ->selectRaw('MONTH(due_date) as m, COUNT(*) as n')
            ->groupBy('m')
            ->pluck('n', 'm');

        return collect(range(1, 12))->map(fn ($m) => (int) ($rows[$m] ?? 0))->all();
    }

    #[Computed]
    public function longestOpenWo(): array
    {
        return Visible::apply(DB::connection('tm2prd')->table('prd_wo'), 'tm2prd', 'prd_wo')
            ->whereIn('wo_status', ['SCHEDULED', 'IN_PROGRESS', 'ON_HOLD'])
            ->orderBy('due_date')
            ->limit(6)
            ->get(['wo_id', 'wo_no', 'wo_status', 'due_date'])
            ->map(fn ($r) => [
                'wo_no' => $r->wo_no,
                'status' => $r->wo_status,
                'due_date' => $r->due_date,
                'days_overdue' => now()->diffInDays($r->due_date, false) < 0 ? abs((int) now()->diffInDays($r->due_date, false)) : 0,
            ])->all();
    }
};
?>

@php
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $statusBadge = fn ($s) => match ($s) {
        'IN_PROGRESS' => 'info', 'ON_HOLD' => 'danger', 'SCHEDULED' => 'warn', 'COMPLETED' => 'ok', default => 'info',
    };
@endphp

<div class="p-4 sm:p-6" style="background:var(--content-bg)">
    <div class="flex flex-wrap items-end justify-between gap-4 mb-5">
        <div>
            <h1 class="text-[22px] font-extrabold -tracking-wide" style="color:var(--sh-ink-strong)">Laporan Produksi &amp; Manufaktur</h1>
            <p class="text-[13px] mt-0.5" style="color:var(--sh-muted)">Status Work Order dan hasil produksi — CV. Telasih Makmur.</p>
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide mb-1" style="color:var(--sh-muted)">Tahun</label>
            <select wire:model.live="year" class="border rounded-lg px-2.5 py-1.5 text-[12.5px]" style="background:var(--sh-surface);border-color:var(--sh-border);color:var(--sh-ink)">
                @for ($y = now()->year; $y >= now()->year - 4; $y--)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>
        </div>
    </div>

    <div class="[display:grid] grid-cols-2 lg:grid-cols-4 gap-3.5 mb-4">
        <x-report.kpi label="Total Work Order" icon="fa-solid fa-industry" tone="blue" :value="number_format($this->totalWo)" :note="$this->woByStatus['COMPLETED'].' selesai tahun ini'" />
        <x-report.kpi label="Sedang Berjalan" icon="fa-solid fa-gears" tone="violet" :value="number_format($this->woByStatus['IN_PROGRESS'])" note="Status IN_PROGRESS" />
        <x-report.kpi label="Tertahan (On Hold)" icon="fa-solid fa-triangle-exclamation" tone="amber" :value="number_format($this->woByStatus['ON_HOLD'])" note="Perlu tindak lanjut PPIC" />
        <x-report.kpi label="Lewat Jatuh Tempo" icon="fa-solid fa-clock" tone="danger" :value="number_format($this->overdueOpen)" note="WO aktif, due_date terlewat" />
    </div>

    <div class="[display:grid] grid-cols-1 lg:grid-cols-[1.55fr_1fr] gap-3.5 mb-4">
        <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
            <h2 class="font-bold text-[14px] mb-3" style="color:var(--sh-ink-strong)">WO Selesai per Bulan {{ $year }}</h2>
            <x-report.svg-bars :height="220" :labels="$months" :series="[
                ['label' => 'Selesai', 'color' => 'var(--sh-ok)', 'data' => $this->monthlyCompleted],
            ]" />
        </div>
        <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
            <h2 class="font-bold text-[14px] mb-2" style="color:var(--sh-ink-strong)">Reject Rate Output {{ $year }}</h2>
            @if ($this->outputDefectRate === null)
                <p class="text-[12px]" style="color:var(--sh-muted)">Belum ada data output produksi tahun ini.</p>
            @else
                <strong class="block text-[30px] font-extrabold mt-2" style="color:{{ $this->outputDefectRate > 5 ? 'var(--sh-danger)' : 'var(--sh-ok)' }}">{{ number_format($this->outputDefectRate, 2) }}%</strong>
                <p class="text-[11.5px] mt-1" style="color:var(--sh-muted)">Reject qty ÷ (Good + Reject qty), seluruh WO Operation Output tahun ini.</p>
            @endif
        </div>
    </div>

    <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
        <h2 class="font-bold text-[14px] mb-3" style="color:var(--sh-ink-strong)">WO Aktif — Jatuh Tempo Terdekat</h2>
        <table class="w-full text-[12px]">
            <thead>
                <tr class="text-left border-b" style="border-color:var(--sh-border)">
                    <th class="pb-2 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">No. WO</th>
                    <th class="pb-2 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Status</th>
                    <th class="pb-2 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Jatuh Tempo</th>
                    <th class="pb-2 font-bold uppercase text-[10px] text-right" style="color:var(--sh-muted)">Terlambat</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->longestOpenWo as $w)
                    <tr class="border-b" style="border-color:var(--sh-border)">
                        <td class="py-2 font-semibold" style="color:var(--sh-ink-strong)">{{ $w['wo_no'] }}</td>
                        <td class="py-2"><x-report.badge :tone="$statusBadge($w['status'])">{{ $w['status'] }}</x-report.badge></td>
                        <td class="py-2" style="color:var(--sh-muted)">{{ \Illuminate\Support\Carbon::parse($w['due_date'])->format('d/m/Y') }}</td>
                        <td class="py-2 text-right tabular-nums font-semibold" style="color:{{ $w['days_overdue'] > 0 ? 'var(--sh-danger)' : 'var(--sh-muted)' }}">{{ $w['days_overdue'] > 0 ? $w['days_overdue'].' hari' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-3 text-center" style="color:var(--sh-muted)">Tidak ada WO aktif.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
