<?php

use App\Support\DataOrigin\Visible;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// Laporan Estimating & Costing -- pengganti angka mockup ("Estimating &
// Job Costing") dgn data nyata tm2sdi.iqp_costing/quo_quotation/co_order
// (ProCost/CostingEngine, lihat project_inqprod_engine_goal). TIDAK
// menampilkan breakdown formula HPP per komponen (itu digated
// johiwan-only di layar ProCost sendiri, feedback_procost_formula_hide_admin_only)
// -- di sini cuma agregat manajerial: volume Costing, rate publish,
// rata-rata HPP/pc, dan konversi Quotation->CO (win rate).
new #[Layout('layouts::report')] #[Title('Laporan Estimating & Costing — TELASIH MAKMUR ERP')] class extends Component
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
    public function costingVolume(): array
    {
        $rows = Visible::apply(DB::connection('tm2sdi')->table('iqp_costing'), 'tm2sdi', 'iqp_costing')
            ->whereYear('created_at', $this->year)
            ->selectRaw('COUNT(*) as total, SUM(published_flag) as published, SUM(is_approved) as approved')
            ->first();

        return [
            'total' => (int) $rows->total,
            'published' => (int) $rows->published,
            'approved' => (int) $rows->approved,
        ];
    }

    #[Computed]
    public function avgHppPc(): float
    {
        return (float) Visible::apply(DB::connection('tm2sdi')->table('iqp_costing'), 'tm2sdi', 'iqp_costing')
            ->whereYear('created_at', $this->year)
            ->selectRaw('AVG(total_material_cost_pc + total_process_cost_pc + total_finishing_cost_pc) as avg_hpp')
            ->value('avg_hpp') ?: 0;
    }

    #[Computed]
    public function quotationConversion(): array
    {
        $issued = Visible::apply(DB::connection('tm2sdi')->table('quo_quotation'), 'tm2sdi', 'quo_quotation')
            ->whereYear('issued_date', $this->year)->count();
        $won = Visible::apply(DB::connection('tm2sdi')->table('co_order')
            ->join('quo_quotation as q', 'q.id_quotation', '=', 'co_order.quotation_id'), 'tm2sdi', 'co_order')
            ->whereYear('q.issued_date', $this->year)
            ->distinct('co_order.quotation_id')
            ->count('co_order.quotation_id');

        return [
            'issued' => $issued,
            'won' => $won,
            'rate' => $issued > 0 ? round($won / $issued * 100, 1) : null,
        ];
    }

    #[Computed]
    public function costTrend(): array
    {
        $rows = Visible::apply(DB::connection('tm2sdi')->table('iqp_costing'), 'tm2sdi', 'iqp_costing')
            ->whereYear('created_at', $this->year)
            ->selectRaw('MONTH(created_at) as m, COUNT(*) as n')
            ->groupBy('m')
            ->pluck('n', 'm');

        return collect(range(1, 12))->map(fn ($m) => (int) ($rows[$m] ?? 0))->all();
    }

    #[Computed]
    public function recentCosting(): array
    {
        return Visible::apply(DB::connection('tm2sdi')->table('iqp_costing'), 'tm2sdi', 'iqp_costing')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['doc_no', 'doc_date', 'doc_status', 'published_flag', 'total_material_cost_pc', 'total_process_cost_pc', 'total_finishing_cost_pc'])
            ->map(fn ($r) => [
                'doc_no' => $r->doc_no,
                'doc_date' => $r->doc_date,
                'status' => $r->doc_status,
                'published' => (bool) $r->published_flag,
                'hpp_pc' => (float) $r->total_material_cost_pc + (float) $r->total_process_cost_pc + (float) $r->total_finishing_cost_pc,
            ])->all();
    }
};
?>

@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $months = array_map(fn ($m) => t($m, 'date.month_short'), ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']);
    $statusBadge = fn ($s) => match ($s) {
        'PUBLISHED', 'APPROVED' => 'ok', 'DRAFT' => 'warn', default => 'info',
    };
@endphp

<div class="p-4 sm:p-6" style="background:var(--content-bg)">
    <div class="flex flex-wrap items-end justify-between gap-4 mb-5">
        <div>
            <h1 class="text-[22px] font-extrabold -tracking-wide" style="color:var(--sh-ink-strong)">{{ t('Estimating & Costing Report') }}</h1>
            <p class="text-[13px] mt-0.5" style="color:var(--sh-muted)">{{ t('Cost (HPP) calculation volume (ProCost) and Quotation to Customer Order conversion') }} — CV. Telasih Makmur.</p>
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide mb-1" style="color:var(--sh-muted)">{{ t('Year') }}</label>
            <select wire:model.live="year" class="border rounded-lg px-2.5 py-1.5 text-[12.5px]" style="background:var(--sh-surface);border-color:var(--sh-border);color:var(--sh-ink)">
                @for ($y = now()->year; $y >= now()->year - 4; $y--)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>
        </div>
    </div>

    <div class="[display:grid] grid-cols-2 lg:grid-cols-4 gap-3.5 mb-4">
        <x-report.kpi :label="t('Costings Created')" icon="fa-solid fa-calculator" tone="blue" :value="number_format($this->costingVolume['total'])" :note="str_replace(':n', $this->costingVolume['published'], t(':n already published'))" />
        <x-report.kpi :label="t('Average HPP/pc')" icon="fa-solid fa-coins" tone="violet" :value="$rp($this->avgHppPc)" :note="t('Material + Process + Finishing')" />
        <x-report.kpi :label="t('Quotations Issued')" icon="fa-solid fa-file-invoice" tone="amber" :value="number_format($this->quotationConversion['issued'])" :note="t('Issued this year')" />
        <x-report.kpi :label="t('Conversion to CO')" icon="fa-solid fa-check-double" tone="green" :value="$this->quotationConversion['rate'] !== null ? $this->quotationConversion['rate'].'%' : '—'" :note="str_replace([':won', ':issued'], [$this->quotationConversion['won'], $this->quotationConversion['issued']], t(':won of :issued quotations'))" />
    </div>

    <div class="rounded-2xl border p-4 mb-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
        <h2 class="font-bold text-[14px] mb-3" style="color:var(--sh-ink-strong)">{{ t('Costing Volume per Month') }} {{ $year }}</h2>
        <x-report.svg-bars :height="200" :labels="$months" :series="[
            ['label' => t('Costing documents created'), 'color' => 'var(--sh-accent)', 'data' => $this->costTrend],
        ]" />
    </div>

    <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
        <h2 class="font-bold text-[14px] mb-3" style="color:var(--sh-ink-strong)">{{ t('Latest Costing Documents') }}</h2>
        <table class="w-full text-[12px]">
            <thead>
                <tr class="text-left border-b" style="border-color:var(--sh-border)">
                    <th class="pb-2 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">{{ t('Document No.') }}</th>
                    <th class="pb-2 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">{{ t('Date') }}</th>
                    <th class="pb-2 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">{{ t('Status') }}</th>
                    <th class="pb-2 font-bold uppercase text-[10px] text-right" style="color:var(--sh-muted)">{{ t('HPP/pc') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->recentCosting as $c)
                    <tr class="border-b" style="border-color:var(--sh-border)">
                        <td class="py-2 font-semibold" style="color:var(--sh-ink-strong)">{{ $c['doc_no'] }}</td>
                        <td class="py-2" style="color:var(--sh-muted)">{{ \Illuminate\Support\Carbon::parse($c['doc_date'])->format('d/m/Y') }}</td>
                        <td class="py-2"><x-report.badge :tone="$statusBadge($c['status'])">{{ $c['status'] }}{{ $c['published'] ? ' · '.t('Published') : '' }}</x-report.badge></td>
                        <td class="py-2 text-right tabular-nums font-semibold" style="color:var(--sh-ink-strong)">{{ $rp($c['hpp_pc']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-3 text-center" style="color:var(--sh-muted)">{{ t('No costing documents yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
