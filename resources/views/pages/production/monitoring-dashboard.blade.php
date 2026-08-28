<?php

use App\Models\Master\OrgDivision;
use App\Models\Master\ProdMachine;
use App\Models\Prd\PrdWoOperation;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Screen #3 -- docs/production_scheduling_wo_generation_delta.md §6. Dashboard
// KARTU (bukan Gantt) monitoring pencapaian produksi HARI INI, pelengkap
// Scheduling Board (bukan pengganti -- keputusan user 2026-08-28). 2 seksi:
// per-proses (target vs actual, agregat lintas-WO) dan per-mesin (job yang
// sedang jalan + achievement%, MERAH kalau mesin kosong tidak ada job aktif
// sekarang). Idle-check di sini independen dari idleGaps() milik Scheduling
// Board (beda konteks tampilan -- itu utk Gantt gap, ini "sedang kosong
// sekarang atau tidak") sesuai catatan riset di plan.
new #[Title('Monitoring Produksi — TELASIH MAKMUR ERP')] class extends Component
{
    private function offsetDivisionId(): ?int
    {
        return OrgDivision::where('division_code', 'OFFSET')->value('division_id');
    }

    private function todaysOperations()
    {
        return PrdWoOperation::with(['wo', 'outputs'])
            ->whereDate('target_start', Carbon::today())
            ->get();
    }

    #[Computed]
    public function processCards()
    {
        return $this->todaysOperations()
            ->groupBy('process_name')
            ->map(function ($ops, $processName) {
                $target = (float) $ops->sum('qty');
                $actual = $ops->sum(fn ($op) => $op->totalQtyGood());

                return [
                    'process_name' => $processName ?: '(tanpa nama proses)',
                    'target' => $target,
                    'actual' => $actual,
                    'percent' => $target > 0 ? (int) max(0, min(100, round($actual / $target * 100))) : 0,
                    'active_count' => $ops->whereIn('task_status', ['ASSIGNED', 'RUNNING'])->count(),
                    'task_count' => $ops->count(),
                ];
            })
            ->sortKeys()
            ->values();
    }

    #[Computed]
    public function machineCards()
    {
        $ops = $this->todaysOperations()->where('resource_type', 'MACHINE')->whereNotNull('machine_id');
        $now = Carbon::now();

        return ProdMachine::query()
            ->where('division_id', $this->offsetDivisionId())
            ->where('activate', true)
            ->orderBy('machine_code')
            ->get()
            ->map(function ($machine) use ($ops, $now) {
                $machineOps = $ops->where('machine_id', $machine->machine_id);

                $currentOp = $machineOps->first(fn ($op) => $op->task_status === 'RUNNING')
                    ?? $machineOps->first(fn ($op) => in_array($op->task_status, ['ASSIGNED', 'PENDING'], true)
                        && $op->target_start && $op->target_finish
                        && $now->between($op->target_start, $op->target_finish));

                return [
                    'machine' => $machine,
                    'current_op' => $currentOp,
                    'idle' => $currentOp === null,
                    'today_task_count' => $machineOps->count(),
                ];
            });
    }

    #[Computed]
    public function idleMachineCount(): int
    {
        return $this->machineCards->where('idle', true)->count();
    }
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">📟 Monitoring Produksi</b>
        <span class="text-xs text-white/75">{{ Carbon::today()->translatedFormat('d M Y') }}</span>
        <a href="{{ route('production.scheduling-board') }}" class="ml-auto inline-flex items-center gap-2 pl-3.5 pr-3 py-1 bg-[#43508a] rounded-full hover:brightness-110 no-underline">
            <span class="text-[11px] font-bold uppercase tracking-wide text-white">📅 Scheduling Board</span>
        </a>
    </header>

    <div class="max-w-[1300px] mx-auto my-5 px-4">
        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="total" label="Proses Aktif" :value="$this->processCards->count()" />
            <x-grid.stat-box tone="active" label="Mesin OFFSET" :value="$this->machineCards->count()" />
            <x-grid.stat-box tone="critical" label="Mesin Kosong" :value="$this->idleMachineCount" subtitle="tidak ada job aktif sekarang" />
        </x-grid.stat-row>

        <div class="mb-2 text-xs font-bold text-[#667] uppercase tracking-wide">Pencapaian per Proses</div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            @forelse ($this->processCards as $card)
                <div class="bg-white border border-[#d9dfe8] rounded-[10px] p-3.5">
                    <div class="font-semibold text-[13px] mb-1.5">{{ $card['process_name'] }}</div>
                    <div class="text-[10px] text-muted uppercase font-bold mb-1">{{ $card['active_count'] }} task aktif / {{ $card['task_count'] }} total</div>
                    <div class="h-2 rounded-full bg-[#eef1f5] overflow-hidden mb-1.5">
                        <div class="h-full {{ $card['percent'] >= 100 ? 'bg-[#15803d]' : 'bg-[#2f6fed]' }}" style="width: {{ $card['percent'] }}%"></div>
                    </div>
                    <div class="text-[11.5px] text-[#4b5568]">
                        <span class="font-bold text-[#1f2328]">{{ number_format($card['actual'], 0, ',', '.') }}</span>
                        / {{ number_format($card['target'], 0, ',', '.') }} ({{ $card['percent'] }}%)
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-6 text-muted text-xs">Belum ada task terjadwal hari ini.</div>
            @endforelse
        </div>

        <div class="mb-2 text-xs font-bold text-[#667] uppercase tracking-wide">Status per Mesin</div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ($this->machineCards as $card)
                <div class="rounded-[10px] p-3.5 border {{ $card['idle'] ? 'bg-[#fbeaea] border-[#f3c9c7]' : 'bg-white border-[#d9dfe8]' }}">
                    <div class="flex items-center justify-between mb-1.5">
                        <div class="font-semibold text-[13px]">{{ $card['machine']->machine_name }}</div>
                        @if ($card['idle'])
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#dc2626] text-white">KOSONG</span>
                        @else
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#dcfce7] text-[#15803d]">JALAN</span>
                        @endif
                    </div>
                    @if ($card['current_op'])
                        <div class="text-[11.5px] text-[#4b5568] mb-1">{{ $card['current_op']->wo?->wo_no }} — {{ $card['current_op']->process_name }}</div>
                        <div class="h-2 rounded-full bg-[#eef1f5] overflow-hidden mb-1.5">
                            <div class="h-full {{ $card['current_op']->outputProgressPercent() >= 100 ? 'bg-[#15803d]' : 'bg-[#2f6fed]' }}" style="width: {{ $card['current_op']->outputProgressPercent() }}%"></div>
                        </div>
                        <div class="text-[11.5px] text-[#4b5568]">
                            <span class="font-bold text-[#1f2328]">{{ number_format($card['current_op']->totalQtyGood(), 0, ',', '.') }}</span>
                            / {{ number_format((float) $card['current_op']->qty, 0, ',', '.') }} baik ({{ $card['current_op']->outputProgressPercent() }}%)
                        </div>
                    @else
                        <div class="text-[11.5px] text-[#a3241f]">Tidak ada job berjalan{{ $card['today_task_count'] > 0 ? ' saat ini' : ' hari ini' }}.</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
