<?php

use App\Models\Master\OrgDivision;
use App\Models\Master\ProdMachine;
use App\Models\Prd\PrdWoOperation;
use App\Services\Scheduling\PdrEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Screen #3 / "MPS - Kanban Board" (nama lama "MPS - Cards", direname
// 2026-08-28 supaya sesuai isinya) -- REVISI 2026-08-28 (menggantikan versi
// kartu statis pertama, docs/production_scheduling_wo_generation_delta.md §6).
// User minta bentuk Kanban ala Trello: kolom = mesin/proses, card = task
// (1 prd_wo_operation, BUKAN 1 WO utuh -- 1 WO bisa py beberapa task di
// mesin berbeda). Direview via mockup HTML (Claude Artifact) sebelum
// dibangun di sini -- drag antar kolom = pindah/assign mesin.
//
// 3 kelompok kolom: "Antrean" (task MACHINE yg belum py mesin/jadwal, LEPAS
// dari filter tanggal -- backlog global), kolom per-mesin (ProdMachine aktif
// divisi OFFSET, di-filter tanggal $date, merah kalau tak ada task
// RUNNING/ASSIGNED), kolom "Manual" (task resource_type=MANUAL hari itu,
// tak ikut logika kosong/merah krn bukan mesin fisik).
//
// REVISI 2026-08-28 (lanjutan, permintaan user "engine MPS harus jalan di
// WO/Kanban/Gantt"): assign dari Antrean ke kolom mesin TIDAK LAGI pakai
// estimasi qty/rate sendiri ("quick-assign visual") -- sekarang panggil
// PdrEngine::backwardScheduleAndPersist() yang sama dgn Gantt Chart, lihat
// §10 delta doc. Fallback estimasi lama cuma jalan kalau engine gagal
// (RuntimeException -- rate band task manual/belum lengkap).
new #[Title('MPS - Kanban Board — TELASIH MAKMUR ERP')] class extends Component
{
    public string $date;

    public function mount(): void
    {
        $this->date = now()->toDateString();
    }

    private function offsetDivisionId(): ?int
    {
        return OrgDivision::where('division_code', 'OFFSET')->value('division_id');
    }

    #[Computed]
    public function machines()
    {
        return ProdMachine::where('division_id', $this->offsetDivisionId())
            ->where('activate', true)
            ->orderBy('machine_code')
            ->get();
    }

    // Backlog global -- task mesin yang belum dijadwalkan (target_start
    // NULL). SENGAJA BUKAN dicek dari machine_id null -- WoGenerationService
    // sudah pre-fill machine_id dari snapshot routing Inquiry saat generate,
    // tapi itu cuma SARAN awal, belum "live" di board sampai PPIC benar2
    // menaruhnya di kolom mesin (assign target_start). Tidak difilter
    // tanggal (belum ada target_start utk difilter).
    #[Computed]
    public function queueOperations()
    {
        return PrdWoOperation::with('wo.coOrder.quotation.customer')
            ->where('resource_type', 'MACHINE')
            ->whereNull('target_start')
            ->where('task_status', 'PENDING')
            ->orderBy('operation_id')
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function machineOperations()
    {
        return PrdWoOperation::with('wo.coOrder.quotation.customer')
            ->where('resource_type', 'MACHINE')
            ->whereNotNull('machine_id')
            ->whereDate('target_start', $this->date)
            ->get()
            ->groupBy('machine_id');
    }

    #[Computed]
    public function manualOperations()
    {
        return PrdWoOperation::with('wo.coOrder.quotation.customer')
            ->where('resource_type', 'MANUAL')
            ->whereDate('target_start', $this->date)
            ->get();
    }

    public function machineIsIdle(int $machineId): bool
    {
        $ops = $this->machineOperations->get($machineId, collect());

        return $ops->whereIn('task_status', ['RUNNING', 'ASSIGNED'])->isEmpty();
    }

    #[Computed]
    public function statTotalToday(): int
    {
        return $this->machineOperations->flatten()->count() + $this->manualOperations->count();
    }

    #[Computed]
    public function statIdleMachines(): int
    {
        return $this->machines->filter(fn ($m) => $this->machineIsIdle($m->machine_id))->count();
    }

    #[Computed]
    public function statRunning(): int
    {
        return $this->machineOperations->flatten()->where('task_status', 'RUNNING')->count()
            + $this->manualOperations->where('task_status', 'RUNNING')->count();
    }

    #[Computed]
    public function statAttention(): int
    {
        return $this->machineOperations->flatten()->whereIn('task_status', ['BREAKDOWN', 'QC_HOLD'])->count()
            + $this->manualOperations->whereIn('task_status', ['BREAKDOWN', 'QC_HOLD'])->count();
    }

    #[Computed]
    public function statQueue(): int
    {
        return $this->queueOperations->count();
    }

    // Drag kartu antar kolom -- $machineId null = kembali ke Antrean
    // (unassign). Cuma task PENDING/ASSIGNED yang boleh dipindah lewat drag
    // (task yg sudah RUNNING/selesai/breakdown harus lewat alur WO Detail
    // "Lapor Breakdown", bukan digeser sembarangan di board ini).
    public function moveOperation(int $operationId, ?int $machineId): void
    {
        $op = PrdWoOperation::with('wo')->find($operationId);

        if (! $op || $op->resource_type !== 'MACHINE') {
            $this->dispatch('wo-toast', message: t('Only machine tasks can be moved by dragging here.'));

            return;
        }

        if (! in_array($op->task_status, ['PENDING', 'ASSIGNED'], true)) {
            $this->dispatch('wo-toast', message: t('This task is already running/done -- use "Report Breakdown" in WO Detail if it needs rerouting.'));

            return;
        }

        if ($machineId === null) {
            // SENGAJA tidak menghapus machine_id -- WoGenerationService sudah
            // pre-fill itu sbg SARAN mesin dari snapshot routing Inquiry;
            // menghapusnya di sini akan menghilangkan saran itu dan bikin
            // RateBandResolver salah kira task ini "manual" (machine_id null
            // + resource_type MACHINE dianggap manual, lihat
            // BackwardSchedulingService::resolveRateForTask -- pernah
            // kejadian nyata, ketahuan dari test_hot_job_dialog gagal).
            // Antrean tetap dideteksi dari target_start null, bukan machine_id.
            $op->update([
                'target_start' => null, 'target_finish' => null,
                'task_status' => 'PENDING', 'updated_by' => Auth::user()->login,
            ]);
            $this->dispatch('wo-toast', message: t('Task returned to the Queue.'));

            return;
        }

        $op->machine_id = $machineId;
        $op->updated_by = Auth::user()->login;
        $op->save();

        // REVISI 2026-08-28 (permintaan user): Kanban kini ikut pakai engine
        // sungguhan (PdrEngine::backwardScheduleAndPersist), bukan lagi
        // estimasi qty/rate sendiri -- ini menghitung ULANG seluruh urutan
        // task WO ini dari due_date (bukan cuma task yg digeser), supaya WO
        // Detail/Gantt/Kanban selalu baca jadwal yang sama persis (1 data, 3
        // tampilan -- lihat [[project_kanban_gantt_single_source]]). Task lain
        // di WO yang sama BISA ikut pindah tanggal/kolom setelah drag ini --
        // itu memang tujuannya, bukan efek samping.
        $engineOk = false;
        try {
            app(PdrEngine::class)->backwardScheduleAndPersist($op->wo);
            $op->refresh();
            $engineOk = true;
        } catch (\RuntimeException) {
            // WO ini py task manual/rate band belum lengkap -- engine tak
            // bisa hitung backward penuh (sama guard yg dipakai
            // WoGenerationService). Fallback ke estimasi sederhana utk task
            // INI saja supaya drag tetap jalan, PPIC lengkapi rate band lewat
            // Master Data > Target Rate baru koreksi manual di WO Detail/Gantt.
            $rate = (float) ($op->rate_band_value ?? 0);
            $hours = $rate > 0 ? max(0.5, ((float) $op->qty) / $rate) : 1.0;
            $op->target_start = now();
            $op->target_finish = now()->copy()->addMinutes((int) round($hours * 60));
            $op->save();
        }

        if ($op->task_status === 'PENDING') {
            $op->transitionTo('ASSIGNED');
        }

        unset($this->machineOperations, $this->queueOperations);
        $note = $engineOk ? '' : ' '.t('(simple estimate -- this WO rate band is incomplete for a full calculation)');
        $this->dispatch('wo-toast', message: str_replace(':machine', $op->fresh()->machine?->machine_name ?? t('machine'), t('Task moved to :machine.')).$note);
    }

    private function statusBadgeClasses(string $status): string
    {
        return match ($status) {
            'PENDING' => 'bg-[#eef1f5] text-[#4b5568]',
            'ASSIGNED' => 'bg-[#e9f1fd] text-[#2c7be5]',
            'RUNNING' => 'bg-[#e4f8ec] text-[#0e7a44]',
            'DONE', 'QC_PASSED' => 'bg-[#e3f6f4] text-[#0f9488]',
            'BREAKDOWN' => 'bg-[#fdecec] text-[#a3241f]',
            'QC_HOLD' => 'bg-[#fef6e7] text-[#b45309]',
            'QC_REJECTED' => 'bg-[#fdecec] text-[#a3241f]',
            'SUBCONTRACT' => 'bg-[#e9f1fd] text-[#2c7be5]',
            default => 'bg-[#eef1f5] text-[#4b5568]',
        };
    }

    private function cardBorderClasses(string $status): string
    {
        return match ($status) {
            'PENDING' => 'border-l-[#9aa6bd]',
            'ASSIGNED' => 'border-l-[#2c7be5]',
            'RUNNING' => 'border-l-[#15a05a]',
            'DONE', 'QC_PASSED' => 'border-l-[#0f9488]',
            'BREAKDOWN' => 'border-l-[#dc2626]',
            'QC_HOLD' => 'border-l-[#b45309]',
            default => 'border-l-[#9aa6bd]',
        };
    }
};
?>

<div x-data="{ toastMsg: '', toastShow: false, toastTimer: null, draggedId: null }"
    x-on:wo-toast.window="toastMsg = $event.detail.message; toastShow = true; clearTimeout(toastTimer); toastTimer = setTimeout(() => toastShow = false, 2500)">
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">📌 {{ t('MPS - Kanban Board') }}</b>
        <x-form.date-picker wire:model.live="date" class="w-[120px] !bg-white/10 !text-white text-[11px] font-bold px-3 py-1.5 rounded-md border-none" />
        <a href="{{ route('production.scheduling-board') }}" class="ml-auto inline-flex items-center gap-2 pl-3.5 pr-3 py-1 bg-[#43508a] rounded-full hover:brightness-110 no-underline">
            <span class="text-[11px] font-bold uppercase tracking-wide text-white">📅 {{ t('MPS - Gantt Chart') }}</span>
        </a>
    </header>

    <div class="max-w-full mx-auto my-4 px-4">
        <div class="flex gap-2.5 flex-wrap mb-4">
            <div class="bg-white border border-[#d9dfe8] rounded-[10px] px-4 py-2 min-w-[130px]">
                <div class="text-[9px] uppercase font-bold text-muted tracking-wide">{{ t('Total Tasks Today') }}</div>
                <div class="text-lg font-extrabold text-navy">{{ $this->statTotalToday }}</div>
            </div>
            <div class="bg-white border border-[#d9dfe8] rounded-[10px] px-4 py-2 min-w-[130px]">
                <div class="text-[9px] uppercase font-bold text-muted tracking-wide">{{ t('Idle Machines') }}</div>
                <div class="text-lg font-extrabold text-[#a3241f]">{{ $this->statIdleMachines }}</div>
            </div>
            <div class="bg-white border border-[#d9dfe8] rounded-[10px] px-4 py-2 min-w-[130px]">
                <div class="text-[9px] uppercase font-bold text-muted tracking-wide">{{ t('Running Now') }}</div>
                <div class="text-lg font-extrabold text-navy">{{ $this->statRunning }}</div>
            </div>
            <div class="bg-white border border-[#d9dfe8] rounded-[10px] px-4 py-2 min-w-[130px]">
                <div class="text-[9px] uppercase font-bold text-muted tracking-wide">{{ t('Breakdown / QC Hold') }}</div>
                <div class="text-lg font-extrabold text-[#a3241f]">{{ $this->statAttention }}</div>
            </div>
            <div class="bg-white border border-[#d9dfe8] rounded-[10px] px-4 py-2 min-w-[130px]">
                <div class="text-[9px] uppercase font-bold text-muted tracking-wide">{{ t('Unscheduled Queue') }}</div>
                <div class="text-lg font-extrabold text-navy">{{ $this->statQueue }}</div>
            </div>
        </div>

        <div class="flex gap-3 overflow-x-auto pb-3 items-start">

            {{-- Kolom Antrean --}}
            <div class="bg-[#eef1f6] border-[1.5px] border-dashed border-[#c3cadb] rounded-[10px] w-[250px] shrink-0 flex flex-col max-h-[calc(100vh-230px)]">
                <div class="px-3 py-2.5 bg-[#dde1ea] rounded-t-[10px] flex items-center justify-between">
                    <div><div class="font-extrabold text-[12.5px] text-navy">📥 {{ t('Queue') }}</div><span class="block text-[8.6px] font-bold text-muted uppercase tracking-wide">{{ t('no machine/schedule yet') }}</span></div>
                    <span class="text-[9.5px] font-bold px-2 py-0.5 rounded-full bg-navy/10 text-navy">{{ $this->queueOperations->count() }}</span>
                </div>
                <div class="p-2 flex flex-col gap-2 overflow-y-auto min-h-[70px] flex-1"
                    x-on:dragover.prevent x-on:drop="$wire.moveOperation(draggedId, null); draggedId = null">
                    @forelse ($this->queueOperations as $op)
                        <div draggable="true" title="{{ t('Drag this card to another machine column to move its task') }}" x-on:dragstart="draggedId = {{ $op->operation_id }}"
                            class="bg-white rounded-lg shadow-sm border-l-4 {{ $this->cardBorderClasses($op->task_status) }} px-2.5 py-2 cursor-grab hover:shadow-md">
                            <div class="flex items-center justify-between gap-1.5 mb-1">
                                <span class="font-mono font-extrabold text-accent text-[11px]">{{ $op->wo?->wo_no }}</span>
                                <span class="text-[8.3px] font-bold px-1.5 py-0.5 rounded-full uppercase {{ $this->statusBadgeClasses($op->task_status) }}">{{ $op->task_status }}</span>
                            </div>
                            <div class="font-bold text-[12px] mb-0.5">{{ $op->process_name }}</div>
                            <div class="text-[9.8px] text-muted">{{ $op->wo?->coOrder?->quotation?->customer?->customer_name ?? '—' }}</div>
                        </div>
                    @empty
                        <div class="text-center text-[10.5px] text-muted py-4">{{ t('Queue is empty.') }}</div>
                    @endforelse
                </div>
            </div>

            {{-- Kolom per Mesin --}}
            @foreach ($this->machines as $machine)
                <div class="bg-[#e7eaf1] rounded-[10px] w-[250px] shrink-0 flex flex-col max-h-[calc(100vh-230px)]">
                    <div class="px-3 py-2.5 rounded-t-[10px] flex items-center justify-between {{ $this->machineIsIdle($machine->machine_id) ? 'bg-[#fdecec]' : 'bg-[#dde1ea]' }}">
                        <div><div class="font-extrabold text-[12.5px] text-navy">{{ $machine->machine_name }}</div><span class="block text-[8.6px] font-bold text-muted uppercase tracking-wide">{{ $machine->machine_code }}</span></div>
                        <span class="text-[9.5px] font-bold px-2 py-0.5 rounded-full {{ $this->machineIsIdle($machine->machine_id) ? 'bg-[#dc2626] text-white' : 'bg-navy/10 text-navy' }}">{{ $this->machineOperations->get($machine->machine_id, collect())->count() }}</span>
                    </div>
                    <div class="p-2 flex flex-col gap-2 overflow-y-auto min-h-[70px] flex-1"
                        x-on:dragover.prevent x-on:drop="$wire.moveOperation(draggedId, {{ $machine->machine_id }}); draggedId = null">
                        @forelse ($this->machineOperations->get($machine->machine_id, collect()) as $op)
                            <div draggable="true" title="{{ t('Drag this card to another machine column to move its task') }}" x-on:dragstart="draggedId = {{ $op->operation_id }}"
                                class="bg-white rounded-lg shadow-sm border-l-4 {{ $this->cardBorderClasses($op->task_status) }} px-2.5 py-2 cursor-grab hover:shadow-md">
                                <div class="flex items-center justify-between gap-1.5 mb-1">
                                    <span class="font-mono font-extrabold text-accent text-[11px]">{{ $op->wo?->wo_no }}</span>
                                    <span class="text-[8.3px] font-bold px-1.5 py-0.5 rounded-full uppercase {{ $this->statusBadgeClasses($op->task_status) }}">{{ $op->task_status }}</span>
                                </div>
                                <div class="font-bold text-[12px] mb-0.5">{{ $op->process_name }}</div>
                                <div class="text-[9.8px] text-muted mb-1.5">{{ $op->wo?->coOrder?->quotation?->customer?->customer_name ?? '—' }}</div>
                                @if (! $op->outputs->isEmpty() || $op->task_status === 'RUNNING')
                                    <div class="h-[5px] rounded-full bg-[#eef1f6] overflow-hidden mb-1">
                                        <div class="h-full bg-accent" style="width: {{ $op->outputProgressPercent() }}%"></div>
                                    </div>
                                    <div class="text-[9.5px] text-[#4b5568] flex justify-between"><span>{{ number_format($op->totalQtyGood(), 0, ',', '.') }} / {{ number_format((float) $op->qty, 0, ',', '.') }}</span><b>{{ $op->outputProgressPercent() }}%</b></div>
                                @endif
                            </div>
                        @empty
                            <div class="text-center text-[10.5px] text-[#a3241f] font-bold py-4">⚠ {{ t('Machine idle') }}<br>{{ t('no jobs') }}</div>
                        @endforelse
                    </div>
                </div>
            @endforeach

            {{-- Kolom Manual --}}
            <div class="bg-[#e7eaf1] rounded-[10px] w-[250px] shrink-0 flex flex-col max-h-[calc(100vh-230px)]">
                <div class="px-3 py-2.5 bg-[#dde1ea] rounded-t-[10px] flex items-center justify-between">
                    <div><div class="font-extrabold text-[12.5px] text-navy">{{ t('Manual') }}</div><span class="block text-[8.6px] font-bold text-muted uppercase tracking-wide">{{ t('non-machine') }}</span></div>
                    <span class="text-[9.5px] font-bold px-2 py-0.5 rounded-full bg-navy/10 text-navy">{{ $this->manualOperations->count() }}</span>
                </div>
                <div class="p-2 flex flex-col gap-2 overflow-y-auto min-h-[70px] flex-1">
                    @forelse ($this->manualOperations as $op)
                        <div class="bg-white rounded-lg shadow-sm border-l-4 {{ $this->cardBorderClasses($op->task_status) }} px-2.5 py-2">
                            <div class="flex items-center justify-between gap-1.5 mb-1">
                                <span class="font-mono font-extrabold text-accent text-[11px]">{{ $op->wo?->wo_no }}</span>
                                <span class="text-[8.3px] font-bold px-1.5 py-0.5 rounded-full uppercase {{ $this->statusBadgeClasses($op->task_status) }}">{{ $op->task_status }}</span>
                            </div>
                            <div class="font-bold text-[12px] mb-0.5">{{ $op->process_name }}</div>
                            <div class="text-[9.8px] text-muted mb-1.5">{{ $op->wo?->coOrder?->quotation?->customer?->customer_name ?? '—' }}</div>
                            @if (! $op->outputs->isEmpty() || $op->task_status === 'RUNNING')
                                <div class="h-[5px] rounded-full bg-[#eef1f6] overflow-hidden mb-1">
                                    <div class="h-full bg-accent" style="width: {{ $op->outputProgressPercent() }}%"></div>
                                </div>
                                <div class="text-[9.5px] text-[#4b5568] flex justify-between"><span>{{ number_format($op->totalQtyGood(), 0, ',', '.') }} / {{ number_format((float) $op->qty, 0, ',', '.') }}</span><b>{{ $op->outputProgressPercent() }}%</b></div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center text-[10.5px] text-muted py-4">{{ t('No manual tasks today.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="flex gap-4 flex-wrap text-[10.3px] text-[#556] mt-3.5">
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-[3px]" style="background:#9aa6bd"></span> {{ t('Pending') }}</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-[3px]" style="background:#2c7be5"></span> {{ t('Assigned') }}</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-[3px]" style="background:#15a05a"></span> {{ t('Running') }}</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-[3px]" style="background:#b45309"></span> {{ t('QC Hold') }}</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-[3px]" style="background:#dc2626"></span> {{ t('Breakdown') }}</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-[3px]" style="background:#0f9488"></span> {{ t('Done / QC Passed') }}</span>
        </div>
        <p class="text-[10.3px] text-muted italic mt-2">{{ t('Drag cards from the Queue to a machine column to assign (start time = now, duration from Target Speed).') }} {{ t('Drag between machine columns to reroute.') }} {{ t('Only Pending/Assigned tasks can be moved — tasks already running use "Report Breakdown" in WO Detail.') }}</p>
    </div>

    <div x-show="toastShow" x-transition x-cloak
        class="fixed bottom-5 left-1/2 -translate-x-1/2 bg-[#1f2328] text-white text-xs font-medium px-4 py-2.5 rounded-md shadow-lg z-[200]"
        x-text="toastMsg"></div>
</div>
