<?php

use App\Models\Master\ProdMachine;
use App\Models\Prd\PrdWoOperation;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// MPS Kanban Board -- pasangan tampilan MPS Gantt (production.scheduling-board,
// sudah ada). Sama-sama membaca tm2prd.prd_wo_operation, jadi "linked" secara
// alami dengan Gantt (satu diubah, yang lain ikut berubah begitu reload/poll)
// TANPA butuh infra realtime -- app ini belum punya broadcasting/websocket.
// wire:poll di sini & di scheduling-board.blade.php menjaga keduanya tetap
// segar tanpa perlu klik refresh manual.
//
// SENGAJA read-only (bukan drag-drop ubah status) -- transisi status task
// (QC pass/reject, breakdown, dst) sudah punya layar sendiri dengan guard
// lengkap di production.wo-entry (WO Detail); Kanban ini murni tampilan
// alternatif dari Engine MPS yang sama, bukan re-implementasi alur transisi.
new #[Title('MPS Kanban Board — TELASIH MAKMUR ERP')] class extends Component
{
    const COLUMNS = [
        'PENDING' => 'Menunggu',
        'ASSIGNED' => 'Ditugaskan',
        'RUNNING' => 'Berjalan',
        'BREAKDOWN' => 'Breakdown',
        'SUBCONTRACT' => 'Subkontrak',
        'DONE' => 'Selesai',
        'QC_HOLD' => 'QC Hold',
        'QC_REJECTED' => 'QC Ditolak',
        'QC_PASSED' => 'QC Lulus',
    ];

    public string $filterMachine = 'all';

    #[Computed]
    public function machines()
    {
        return ProdMachine::query()->where('activate', true)->orderBy('machine_code')->get();
    }

    #[Computed]
    public function operations()
    {
        return PrdWoOperation::query()
            ->with(['wo', 'machine', 'crew.employee'])
            ->when($this->filterMachine !== 'all', fn ($q) => $q->where('machine_id', $this->filterMachine))
            ->orderBy('target_start')
            ->get()
            ->groupBy('task_status');
    }

    private function statusBadgeClasses(string $status): string
    {
        return match ($status) {
            'PENDING' => 'bg-[#eef1f5] text-[#4b5568]',
            'ASSIGNED' => 'bg-[#eff6ff] text-[#1d4ed8]',
            'RUNNING' => 'bg-[#eaf7ee] text-[#1f8a4c]',
            'DONE', 'QC_PASSED' => 'bg-[#dcfce7] text-[#15803d]',
            'BREAKDOWN' => 'bg-[#fbeaea] text-[#a3241f]',
            'SUBCONTRACT' => 'bg-[#eef3fb] text-[#2f6fed]',
            'QC_HOLD' => 'bg-[#fffbeb] text-[#92400e]',
            'QC_REJECTED' => 'bg-[#fbeaea] text-[#a3241f]',
            default => 'bg-[#eef1f5] text-[#4b5568]',
        };
    }
};
?>

<div wire:poll.10s class="w-full px-5 py-4.5">
    <div class="flex items-center gap-2 flex-wrap mb-3.5">
        <h1 class="text-lg font-bold text-navy">🗂️ MPS Kanban Board</h1>
        <span class="text-xs text-muted">tm2prd.prd_wo_operation — sama data dengan Scheduling Board</span>
    </div>

    <div class="flex items-center gap-2 flex-wrap mb-4">
        <select wire:model.live="filterMachine" class="px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
            <option value="all">Semua Mesin</option>
            @foreach ($this->machines as $m)
                <option value="{{ $m->machine_id }}">{{ $m->machine_name }}</option>
            @endforeach
        </select>
        <a href="{{ route('production.scheduling-board') }}" class="text-xs text-[#2f6fed] underline">📅 Lihat di Gantt</a>
    </div>

    <div class="flex gap-3 overflow-x-auto pb-2">
        @foreach (self::COLUMNS as $status => $columnLabel)
            @php($ops = $this->operations->get($status, collect()))
            <div class="flex-none w-[260px] bg-[#f7f8fa] border border-[#e5e7eb] rounded-[10px] p-2.5">
                <div class="flex items-center justify-between mb-2.5 px-1">
                    <span class="text-[11px] font-bold uppercase tracking-wide text-[#4b5568]">{{ $columnLabel }}</span>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->statusBadgeClasses($status) }}">{{ $ops->count() }}</span>
                </div>

                <div class="flex flex-col gap-2 min-h-[40px]">
                    @forelse ($ops as $op)
                        <div wire:key="kanban-op-{{ $op->operation_id }}" class="bg-white border border-[#e5e7eb] rounded-lg p-2.5 text-[11.5px] shadow-sm">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-semibold">{{ $op->wo?->wo_no }}</span>
                                @if ($op->is_hot_job)
                                    <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded-full bg-[#dc2626] text-white">🔥</span>
                                @endif
                            </div>
                            <div class="text-muted mb-1">{{ $op->process_name }} — {{ $op->resource_type === 'MACHINE' ? ($op->machine?->machine_name ?? '—') : 'Manual' }}</div>
                            <div class="text-[10.5px] text-muted">
                                {{ $op->target_start && $op->target_finish ? $op->target_start->format('d/m H:i').'–'.$op->target_finish->format('H:i') : 'Belum terjadwal' }}
                            </div>
                            <a href="{{ route('production.wo.entry', ['wo' => $op->wo_id]) }}" class="text-[10.5px] text-[#2f6fed] underline">Buka WO</a>
                        </div>
                    @empty
                        <div class="text-[10.5px] text-muted italic px-1">Kosong</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
