<?php

use App\Models\Master\OrgDivision;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdPartProcessEmployeeRate;
use App\Models\Master\ProdPartProcessMachineRate;
use App\Models\Master\ProdProcess;
use App\Models\Prd\PrdBreakdownEvent;
use App\Models\Prd\PrdScrapType;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use App\Models\Prd\PrdWoOperationOutput;
use App\Models\Prd\PrdWoOperationOutputDefect;
use App\Models\Master\SupSupplier;
use App\Models\Sdi\IqpPartMaterial;
use App\Services\Pur\SubcontractPoService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// WO Detail -- production_scheduling_tier4.md §2 ("Buka" dari Worklist WO).
// DEVIASI SADAR dari teks Tier4 (dicatat, bukan diam-diam): Tier4 bilang
// "Buka" langsung ke Scheduling Board -- itu cuma masuk akal kalau WO sudah
// terjadwal, dan Simulation Layer penuh belum dibangun (di luar scope sesi
// ini). Jadi "Buka" selalu ke sini dulu (pola sama semua grid lain: PR->
// pr.entry), dengan tombol "Lihat di Scheduling Board" yang aktif kalau ada
// operation terjadwal. Halaman ini juga rumah QC Gate (Lulus/Tolak,
// production_scheduling_tier2.md §2/§3.1), pemicu Breakdown & Subcontract
// Form (Tier1 §9.3, Tier2 §7), dan Production Output Recording (F15, Tier1
// §4 istilah baku -- TIDAK ADA spec Tier2/3/4 sama sekali untuk F15, skema
// & layar didesain dari nol di sesi ini, lihat migration
// 2026_08_28_000010_create_prd_wo_operation_output).
//
// Diperluas 2026-08-28 (Screen #2, docs/production_scheduling_wo_generation_delta.md
// §5): header CO/produk/part, panel Bill of Material (read-only, dari
// iqp_part_material via source_iqp_part_id), Bill of Process jadi interaktif
// (tambah/edit proses+mesin+target) -- HANYA selagi status DRAFT/SIMULATED
// (canEditRouting(), konsisten prinsip snapshot/beku begitu commit ke jadwal
// real). Target default diambil dari prod_part_process_machine_rate/
// _employee_rate (§4.2/4.3 delta doc), selalu bisa di-override manual.
new #[Title('WO Detail — TELASIH MAKMUR ERP')] class extends Component
{
    public int $wo;

    // QC reject modal
    public ?int $qcRejectOperationId = null;

    public string $qcRejectReason = '';

    // Breakdown & Subcontract modal
    public ?int $breakdownOperationId = null;

    public string $breakdownStep = 'report'; // report|reroute

    public int $breakdownDowntimeMinutes = 60;

    public ?int $breakdownEventId = null;

    public string $rerouteChoice = 'MACHINE_ALT'; // MACHINE_ALT|SUBCONTRACT

    public ?int $altMachineId = null;

    public ?int $subVendorId = null;

    public string $subVendorName = '';

    public string $subVendorQuery = '';

    public string $subDueOut = '';

    public string $subDueReturn = '';

    public float $subEstimatedCost = 0;

    // Catat Output (F15) -- qty_good header + baris rinci reject/waste
    // ber-tipe, boleh beberapa tipe sekaligus dalam 1 entri (permintaan user
    // 2026-08-28: "banyak tipe dalam 1 entri", bukan 1 tipe per entri).
    public ?int $outputOperationId = null;

    public float $outputQtyGood = 0;

    public array $outputDefectRows = []; // [['category'=>'REJECT'|'WASTE', 'scrap_type_id'=>int|null, 'qty'=>float], ...]

    public string $outputShiftNote = '';

    public string $outputNotes = '';

    public function mount(int $wo): void
    {
        $this->wo = $wo;
    }

    #[Computed]
    public function workOrder(): PrdWo
    {
        return PrdWo::with(['coOrder', 'product', 'operations.crew.employee', 'operations.outputs.defects.scrapType'])->findOrFail($this->wo);
    }

    // Lintas-DB (tm2prd -> tm2sdi) -- query terpisah, bukan join fisik.
    // Bill of Material: source_iqp_part_id jembatan balik ke snapshot Inquiry.
    #[Computed]
    public function billOfMaterial()
    {
        if (! $this->workOrder->source_iqp_part_id) {
            return collect();
        }

        return IqpPartMaterial::where('part_id', $this->workOrder->source_iqp_part_id)->orderBy('layer_seq')->get();
    }

    // Bill of Process cuma boleh diedit selagi belum commit ke jadwal real
    // (prinsip snapshot/beku, CLAUDE.md §4) -- konsisten guard lain di layar ini.
    #[Computed]
    public function canEditRouting(): bool
    {
        return in_array($this->workOrder->wo_status, ['DRAFT', 'SIMULATED'], true);
    }

    #[Computed]
    public function processOptions()
    {
        return ProdProcess::where('activate', true)->orderBy('process_name')->get();
    }

    #[Computed]
    public function machineOptions()
    {
        $offsetDivisionId = OrgDivision::where('division_code', 'OFFSET')->value('division_id');

        return ProdMachine::where('division_id', $offsetDivisionId)->where('activate', true)->orderBy('machine_name')->get();
    }

    #[Computed]
    public function rejectTypes()
    {
        return PrdScrapType::reject()->active()->orderBy('sort_order')->get();
    }

    #[Computed]
    public function wasteTypes()
    {
        return PrdScrapType::waste()->active()->orderBy('sort_order')->get();
    }

    #[Computed]
    public function hasScheduledOperation(): bool
    {
        return $this->workOrder->operations->contains(fn ($o) => $o->target_start !== null);
    }

    #[Computed]
    public function firstScheduledDate(): ?string
    {
        return $this->workOrder->operations->first(fn ($o) => $o->target_start !== null)?->target_start->toDateString();
    }

    #[Computed]
    public function altMachines()
    {
        if (! $this->breakdownOperationId) {
            return collect();
        }
        $op = PrdWoOperation::find($this->breakdownOperationId);

        return ProdMachine::where('division_id', $op?->machine?->division_id)
            ->where('activate', true)
            ->where('machine_id', '!=', $op?->machine_id)
            ->orderBy('machine_name')
            ->get();
    }

    #[Computed]
    public function vendorResults()
    {
        if ($this->subVendorId || mb_strlen($this->subVendorQuery) < 2) {
            return collect();
        }

        return SupSupplier::query()
            ->where('is_subcon', true)
            ->where('activate', true)
            ->where(function ($q) {
                $q->where('supplier_name', 'like', "%{$this->subVendorQuery}%")
                    ->orWhere('supplier_code', 'like', "%{$this->subVendorQuery}%");
            })
            ->orderBy('supplier_name')
            ->limit(8)
            ->get();
    }

    public function backToList()
    {
        return $this->redirect(route('production.wo.list'));
    }

    // ---- Bill of Process interaktif (Screen #2) ----

    public function addProcessRow(): void
    {
        if (! $this->canEditRouting) {
            return;
        }

        $wo = $this->workOrder;
        $nextSeq = ((int) $wo->operations->max('seq_no')) + 1;

        PrdWoOperation::create([
            'wo_id' => $wo->wo_id,
            'seq_no' => $nextSeq,
            'process_name' => '',
            'resource_type' => 'MACHINE',
            'qty' => $wo->qty,
            'task_status' => 'PENDING',
            'created_by' => Auth::user()->login,
        ]);

        unset($this->workOrder);
    }

    public function removeProcessRow(int $operationId): void
    {
        if (! $this->canEditRouting) {
            return;
        }

        $op = PrdWoOperation::find($operationId);
        if ($op && $op->task_status === 'PENDING') {
            $op->delete();
            unset($this->workOrder);
        }
    }

    public function updateOperationField(int $operationId, string $field, $value): void
    {
        if (! $this->canEditRouting || ! in_array($field, ['process_name', 'machine_id', 'resource_type', 'rate_band_value'], true)) {
            return;
        }

        $op = PrdWoOperation::findOrFail($operationId);

        if ($field === 'resource_type') {
            $op->resource_type = $value;
            if ($value === 'MANUAL') {
                $op->machine_id = null;
            }
        } elseif ($field === 'machine_id') {
            $op->machine_id = $value ?: null;
        } elseif ($field === 'rate_band_value') {
            $op->rate_band_value = $value !== '' ? $value : null;
        } else {
            $op->process_name = $value;
        }

        $op->updated_by = Auth::user()->login;
        $op->save();
        unset($this->workOrder);
    }

    // Ambil ulang target default dari prod_part_process_machine_rate/
    // _employee_rate (delta doc §4.2/4.3) -- dipanggil manual (tombol "↻
    // Ambil Default"), BUKAN otomatis tiap ganti mesin, supaya tak diam-diam
    // menimpa angka yang sudah di-override PPIC.
    public function refillTargetDefault(int $operationId): void
    {
        if (! $this->canEditRouting) {
            return;
        }

        $op = PrdWoOperation::findOrFail($operationId);
        $wo = $this->workOrder;
        $processId = ProdProcess::where('process_name', $op->process_name)->value('id_process');

        if (! $processId) {
            $this->dispatch('wo-toast', message: 'Nama proses tidak cocok dengan master Proses -- tak bisa ambil default.');

            return;
        }

        $rate = null;
        if ($op->resource_type === 'MACHINE' && $op->machine_id) {
            $rate = ProdPartProcessMachineRate::active()
                ->where('product_id', $wo->product_id)->where('part_code', $wo->part_code)
                ->where('process_id', $processId)->where('machine_id', $op->machine_id)
                ->value('target_qty_per_hour');
        } elseif ($op->resource_type === 'MANUAL') {
            $employeeId = $op->operatorUtama()?->employee_id;
            if ($employeeId) {
                $rate = ProdPartProcessEmployeeRate::active()
                    ->where('product_id', $wo->product_id)->where('part_code', $wo->part_code)
                    ->where('process_id', $processId)->where('employee_id', $employeeId)
                    ->value('target_qty_per_hour');
            }
        }

        if ($rate === null) {
            $this->dispatch('wo-toast', message: 'Belum ada target default tersimpan utk kombinasi ini -- isi manual.');

            return;
        }

        $op->update(['rate_band_value' => $rate, 'updated_by' => Auth::user()->login]);
        unset($this->workOrder);
        $this->dispatch('wo-toast', message: "Target diisi dari default: {$rate}/jam.");
    }

    public function openInSchedulingBoard()
    {
        return $this->redirect(route('production.scheduling-board', ['date' => $this->firstScheduledDate]));
    }

    // ---- QC Gate (Tier2 §2/§3.1) ----

    public function qcPass(int $operationId): void
    {
        if (! Auth::user()->isQa()) {
            $this->dispatch('wo-toast', message: 'Hanya QA yang bisa memutuskan QC.');

            return;
        }

        $op = PrdWoOperation::with('wo')->findOrFail($operationId);
        $op->transitionTo('QC_PASSED');

        $next = PrdWoOperation::where('wo_id', $op->wo_id)->where('seq_no', '>', $op->seq_no)->orderBy('seq_no')->first();
        if ($next && $next->task_status === 'PENDING') {
            // Sudah PENDING dari awal (routing), tak perlu diubah -- baris ini
            // jaga-jaga kalau suatu saat next task mulai di status lain.
        }

        $allDone = PrdWoOperation::where('wo_id', $op->wo_id)
            ->whereNotIn('task_status', ['DONE', 'QC_PASSED'])
            ->doesntExist();

        if ($allDone && $op->wo->canTransitionTo('COMPLETED')) {
            $op->wo->transitionTo('COMPLETED');
        }

        $this->dispatch('wo-toast', message: "Task {$op->process_name} lulus QC.");
    }

    public function openQcReject(int $operationId): void
    {
        $this->qcRejectOperationId = $operationId;
        $this->qcRejectReason = '';
    }

    public function submitQcReject(): void
    {
        if (! Auth::user()->isQa()) {
            $this->dispatch('wo-toast', message: 'Hanya QA yang bisa memutuskan QC.');

            return;
        }
        if (mb_strlen(trim($this->qcRejectReason)) < 5) {
            $this->dispatch('wo-toast', message: 'Alasan tolak wajib diisi (minimal 5 karakter).');

            return;
        }

        $op = PrdWoOperation::findOrFail($this->qcRejectOperationId);
        $op->rejection_reason = $this->qcRejectReason;
        $op->transitionTo('QC_REJECTED');

        $this->qcRejectOperationId = null;
        $this->dispatch('wo-toast', message: "Task {$op->process_name} ditolak QC: {$this->qcRejectReason}");
    }

    public function reworkFromReject(int $operationId): void
    {
        $op = PrdWoOperation::findOrFail($operationId);
        $op->transitionTo('PENDING');
        $this->dispatch('wo-toast', message: "Task {$op->process_name} dikembalikan untuk rework.");
    }

    // ---- Breakdown & Subcontract (Tier1 §9.3, Tier2 §7) ----

    public function openBreakdown(int $operationId): void
    {
        $this->breakdownOperationId = $operationId;
        $this->breakdownStep = 'report';
        $this->breakdownDowntimeMinutes = 60;
        $this->breakdownEventId = null;
        $this->rerouteChoice = 'MACHINE_ALT';
        $this->altMachineId = null;
        $this->subVendorId = null;
        $this->subVendorName = '';
        $this->subVendorQuery = '';
        $this->subDueOut = now()->addDay()->toDateString();
        $this->subDueReturn = now()->addDays(3)->toDateString();
        $this->subEstimatedCost = 0;
    }

    public function closeBreakdown(): void
    {
        $this->breakdownOperationId = null;
    }

    public function submitBreakdownReport(): void
    {
        $op = PrdWoOperation::findOrFail($this->breakdownOperationId);

        $event = PrdBreakdownEvent::create([
            'operation_id' => $op->operation_id,
            'machine_id' => $op->machine_id,
            'reported_by' => Auth::user()->login,
            'reported_at' => now(),
            'downtime_estimate_minutes' => $this->breakdownDowntimeMinutes,
        ]);

        $op->transitionTo('BREAKDOWN');

        $this->breakdownEventId = $event->event_id;
        $this->breakdownStep = 'reroute';
    }

    public function rerouteToMachine(): void
    {
        if (! $this->altMachineId) {
            $this->dispatch('wo-toast', message: 'Pilih mesin alternatif dulu.');

            return;
        }

        $op = PrdWoOperation::findOrFail($this->breakdownOperationId);
        $op->machine_id = $this->altMachineId;
        $op->updated_by = Auth::user()->login;
        $op->transitionTo('PENDING');

        PrdBreakdownEvent::whereKey($this->breakdownEventId)->update([
            'reroute_decision' => 'MACHINE_ALT',
            'alt_machine_id' => $this->altMachineId,
        ]);

        $this->closeBreakdown();
        $this->dispatch('wo-toast', message: 'Task dipindah ke mesin alternatif, kembali ke antrean.');
    }

    public function selectVendor(int $id): void
    {
        $v = SupSupplier::find($id);
        if ($v) {
            $this->subVendorId = $v->id_supplier;
            $this->subVendorName = $v->supplier_name;
            $this->subVendorQuery = '';
        }
    }

    public function clearVendor(): void
    {
        $this->subVendorId = null;
        $this->subVendorName = '';
    }

    // Tier1 §9.3: approval subkontrak->PO minimal Manager Operasional.
    public function createSubcontractPo(): void
    {
        if (! Auth::user()->isOpsManagerOrAbove()) {
            $this->dispatch('wo-toast', message: 'Hanya Manager Operasional ke atas yang bisa menyetujui PO Subkontrak.');

            return;
        }
        if (! $this->subVendorId || $this->subEstimatedCost <= 0 || ! $this->subDueOut || ! $this->subDueReturn) {
            $this->dispatch('wo-toast', message: 'Lengkapi vendor, estimasi biaya, dan tanggal kirim/kembali dulu.');

            return;
        }

        $event = PrdBreakdownEvent::findOrFail($this->breakdownEventId);

        $po = app(SubcontractPoService::class)->createFromBreakdown(
            $event,
            $this->subVendorId,
            $this->subEstimatedCost,
            Carbon::parse($this->subDueOut),
            Carbon::parse($this->subDueReturn),
            Auth::user()->login,
            Auth::user()->name ?? Auth::user()->login,
        );

        $this->closeBreakdown();
        $this->dispatch('wo-toast', message: "PO Subkontrak {$po->doc_no} terbit ke {$this->subVendorName}.");
    }

    public function receiveFromSubcontract(int $operationId): void
    {
        $op = PrdWoOperation::findOrFail($operationId);
        $op->transitionTo('QC_HOLD');
        $this->dispatch('wo-toast', message: "Task {$op->process_name} kembali dari subkontrak, wajib QC.");
    }

    // ---- Production Output Recording (F15) ----

    public function openOutputEntry(int $operationId): void
    {
        $this->outputOperationId = $operationId;
        $this->outputQtyGood = 0;
        $this->outputDefectRows = [];
        $this->outputShiftNote = '';
        $this->outputNotes = '';
    }

    public function closeOutputEntry(): void
    {
        $this->outputOperationId = null;
    }

    public function addDefectRow(string $category): void
    {
        $this->outputDefectRows[] = ['category' => $category, 'scrap_type_id' => null, 'qty' => 0];
    }

    public function removeDefectRow(int $index): void
    {
        unset($this->outputDefectRows[$index]);
        $this->outputDefectRows = array_values($this->outputDefectRows);
    }

    public function saveOutputEntry(): void
    {
        $validRows = collect($this->outputDefectRows)->filter(fn ($r) => ! empty($r['scrap_type_id']) && (float) $r['qty'] > 0);

        if ($this->outputQtyGood <= 0 && $validRows->isEmpty()) {
            $this->dispatch('wo-toast', message: 'Isi qty baik, atau minimal 1 baris reject/waste (tipe + qty).');

            return;
        }

        $output = PrdWoOperationOutput::create([
            'operation_id' => $this->outputOperationId,
            'qty_good' => $this->outputQtyGood,
            'shift_note' => $this->outputShiftNote ?: null,
            'notes' => $this->outputNotes ?: null,
            'recorded_by' => Auth::user()->login,
            'recorded_at' => now(),
        ]);

        foreach ($validRows as $row) {
            PrdWoOperationOutputDefect::create([
                'output_id' => $output->output_id,
                'scrap_type_id' => $row['scrap_type_id'],
                'qty' => $row['qty'],
            ]);
        }

        $this->outputOperationId = null;
        unset($this->workOrder);
        $this->dispatch('wo-toast', message: 'Output tercatat.');
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

<div x-data="{ toastMsg: '', toastShow: false, toastTimer: null }"
    x-on:wo-toast.window="toastMsg = $event.detail.message; toastShow = true; clearTimeout(toastTimer); toastTimer = setTimeout(() => toastShow = false, 2500)">
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">📋 WO Detail</b>
        <span class="text-xs text-white/75">{{ $this->workOrder->wo_no }}</span>
        @if ($this->workOrder->is_hot_job)
            <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full bg-[#dc2626] text-white">🔥 HOT</span>
        @endif
        <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full bg-white/15 text-white">{{ $this->workOrder->wo_status }}</span>
        <a href="{{ route('production.wo.print', ['wo' => $this->wo]) }}" target="_blank" class="ml-auto inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-[#43508a] hover:brightness-110 text-[11px] font-bold uppercase tracking-wide text-white no-underline">🖨 Cetak</a>
        <button type="button" wire:click="backToList" title="Kembali ke Worklist WO"
            class="ml-auto inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full hover:brightness-110 cursor-pointer">
            <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-xs font-bold">←</span>
        </button>
    </header>

    <div class="max-w-[1100px] mx-auto my-5 px-4">
        <div class="bg-white border border-[#d9dfe8] rounded-[10px] p-4 mb-4 grid grid-cols-3 gap-4 text-xs">
            <div><div class="text-[10px] text-muted uppercase font-bold mb-0.5">No. CO</div><div class="font-semibold">{{ $this->workOrder->coOrder?->co_no ?? '—' }}</div></div>
            <div><div class="text-[10px] text-muted uppercase font-bold mb-0.5">Produk</div><div class="font-semibold">{{ $this->workOrder->product?->product_name ?? '—' }}</div></div>
            <div><div class="text-[10px] text-muted uppercase font-bold mb-0.5">Part</div><div class="font-semibold">{{ $this->workOrder->part_code ?? '—' }}</div></div>
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] p-4 mb-4 grid grid-cols-4 gap-4 text-xs">
            <div><div class="text-[10px] text-muted uppercase font-bold mb-0.5">Tipe</div><div class="font-semibold">{{ $this->workOrder->wo_type }}</div></div>
            <div><div class="text-[10px] text-muted uppercase font-bold mb-0.5">Qty</div><div class="font-semibold">{{ number_format((float) $this->workOrder->qty, 0, ',', '.') }}</div></div>
            <div><div class="text-[10px] text-muted uppercase font-bold mb-0.5">Due Date</div><div class="font-semibold">{{ $this->workOrder->due_date?->translatedFormat('d M Y H:i') }}</div></div>
            <div><div class="text-[10px] text-muted uppercase font-bold mb-0.5">Bahan Dibutuhkan</div><div class="font-semibold">{{ $this->workOrder->material_needed_by?->translatedFormat('d M Y H:i') ?? '—' }}</div></div>
        </div>

        @if ($this->billOfMaterial->isNotEmpty())
            <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden mb-4">
                <div class="px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide font-bold border-b border-[#eef1f5]">Bill of Material</div>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-1.5 text-[#667] text-[10.5px] uppercase tracking-wide border-b border-[#eef1f5]">Material</th>
                            <th class="text-left px-3 py-1.5 text-[#667] text-[10.5px] uppercase tracking-wide border-b border-[#eef1f5]">Gramatur</th>
                            <th class="text-left px-3 py-1.5 text-[#667] text-[10.5px] uppercase tracking-wide border-b border-[#eef1f5]">Ukuran</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->billOfMaterial as $mat)
                            <tr wire:key="bom-{{ $mat->id_part_material }}">
                                <td class="px-3 py-1.5 border-b border-[#eef1f5]">{{ $mat->rawmat_name }}</td>
                                <td class="px-3 py-1.5 border-b border-[#eef1f5]">{{ $mat->gram_weight ? number_format((float) $mat->gram_weight, 0, ',', '.').' gsm' : '—' }}</td>
                                <td class="px-3 py-1.5 border-b border-[#eef1f5]">{{ $mat->size_p && $mat->size_l ? number_format((float) $mat->size_p, 1, ',', '.').' x '.number_format((float) $mat->size_l, 1, ',', '.').' cm' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($this->hasScheduledOperation)
            <button type="button" wire:click="openInSchedulingBoard" class="mb-4 px-4 py-2 rounded-md text-xs font-semibold bg-[#2c3e69] text-white cursor-pointer">📅 Lihat di Scheduling Board</button>
        @endif

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <div class="px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide font-bold border-b border-[#eef1f5] flex items-center justify-between">
                <span>Bill of Process</span>
                @if ($this->canEditRouting)
                    <button type="button" wire:click="addProcessRow" class="normal-case font-semibold text-[11px] px-2.5 py-1 rounded bg-[#eef3fb] text-[#2f6fed] cursor-pointer">+ Tambah Proses</button>
                @endif
            </div>
            <table class="w-full border-collapse text-[12.5px]">
                <thead>
                    <tr>
                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Seq</th>
                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Proses</th>
                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Mesin / Crew</th>
                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Target/Jam</th>
                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Jendela Waktu</th>
                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Status</th>
                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Output Aktual</th>
                        <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->workOrder->operations as $op)
                        <tr wire:key="op-{{ $op->operation_id }}">
                            <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $op->seq_no }}</td>
                            <td class="px-3 py-2 border-b border-[#eef1f5] font-semibold">
                                @if ($this->canEditRouting && $op->task_status === 'PENDING')
                                    <select wire:change="updateOperationField({{ $op->operation_id }}, 'process_name', $event.target.value)" class="w-full px-1.5 py-1 border border-[#c7cfda] rounded text-[11.5px]">
                                        <option value="">— pilih proses —</option>
                                        @foreach ($this->processOptions as $proc)
                                            <option value="{{ $proc->process_name }}" @selected($op->process_name === $proc->process_name)>{{ $proc->process_name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    {{ $op->process_name ?: '—' }}
                                @endif
                            </td>
                            <td class="px-3 py-2 border-b border-[#eef1f5]">
                                @if ($this->canEditRouting && $op->task_status === 'PENDING')
                                    <div class="flex items-center gap-1.5">
                                        <select wire:change="updateOperationField({{ $op->operation_id }}, 'resource_type', $event.target.value)" class="px-1.5 py-1 border border-[#c7cfda] rounded text-[11.5px]">
                                            <option value="MACHINE" @selected($op->resource_type === 'MACHINE')>Mesin</option>
                                            <option value="MANUAL" @selected($op->resource_type === 'MANUAL')>Manual</option>
                                        </select>
                                        @if ($op->resource_type === 'MACHINE')
                                            <select wire:change="updateOperationField({{ $op->operation_id }}, 'machine_id', $event.target.value)" class="px-1.5 py-1 border border-[#c7cfda] rounded text-[11.5px]">
                                                <option value="">— pilih mesin —</option>
                                                @foreach ($this->machineOptions as $mc)
                                                    <option value="{{ $mc->machine_id }}" @selected($op->machine_id === $mc->machine_id)>{{ $mc->machine_name }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                    </div>
                                @else
                                    {{ $op->resource_type === 'MACHINE' ? ($op->machine?->machine_name ?? '—') : 'Manual' }}
                                @endif
                                <div class="text-[10.5px] text-muted">{{ $op->crew->map(fn ($c) => $c->employee?->full_name)->filter()->implode(' + ') ?: '—' }}</div>
                            </td>
                            <td class="px-3 py-2 border-b border-[#eef1f5]">
                                @if ($this->canEditRouting && $op->task_status === 'PENDING')
                                    <div class="flex items-center gap-1">
                                        <input type="number" step="0.01" value="{{ $op->rate_band_value }}"
                                            wire:change="updateOperationField({{ $op->operation_id }}, 'rate_band_value', $event.target.value)"
                                            class="w-20 px-1.5 py-1 border border-[#c7cfda] rounded text-[11.5px]" placeholder="—">
                                        <button type="button" wire:click="refillTargetDefault({{ $op->operation_id }})" title="Ambil target default" class="text-[13px] cursor-pointer">↻</button>
                                    </div>
                                @else
                                    {{ $op->rate_band_value ? number_format((float) $op->rate_band_value, 0, ',', '.') : '—' }}
                                @endif
                            </td>
                            <td class="px-3 py-2 border-b border-[#eef1f5]">
                                {{ $op->target_start ? $op->target_start->format('d/m H:i').'–'.$op->target_finish->format('H:i') : '—' }}
                            </td>
                            <td class="px-3 py-2 border-b border-[#eef1f5]">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->statusBadgeClasses($op->task_status) }}">{{ $op->task_status }}</span>
                            </td>
                            <td class="px-3 py-2 border-b border-[#eef1f5] text-[11px]">
                                @if ($op->outputs->isEmpty())
                                    <span class="text-muted">belum dicatat</span>
                                @else
                                    <span class="font-semibold {{ $op->outputProgressPercent() >= 100 ? 'text-[#15803d]' : 'text-[#1f2328]' }}">{{ number_format($op->totalQtyGood(), 0, ',', '.') }}</span>
                                    / {{ number_format((float) $op->qty, 0, ',', '.') }} baik ({{ $op->outputProgressPercent() }}%)
                                    @php($rejectBreakdown = $op->defectBreakdown('REJECT'))
                                    @php($wasteBreakdown = $op->defectBreakdown('WASTE'))
                                    @if (! empty($rejectBreakdown))
                                        <div class="text-[#a3241f]">reject {{ number_format($op->totalQtyReject(), 0, ',', '.') }}
                                            — {{ collect($rejectBreakdown)->map(fn ($qty, $name) => "{$name}: ".number_format($qty, 0, ',', '.'))->implode(', ') }}</div>
                                    @endif
                                    @if (! empty($wasteBreakdown))
                                        <div class="text-[#92400e]">waste {{ number_format($op->totalQtyWaste(), 0, ',', '.') }}
                                            — {{ collect($wasteBreakdown)->map(fn ($qty, $name) => "{$name}: ".number_format($qty, 0, ',', '.'))->implode(', ') }}</div>
                                    @endif
                                @endif
                            </td>
                            <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                @if (in_array($op->task_status, ['RUNNING', 'DONE'], true))
                                    <button type="button" wire:click="openOutputEntry({{ $op->operation_id }})" class="px-3 py-1.5 rounded text-[11px] font-semibold bg-[#eef3fb] text-[#2f6fed] cursor-pointer mr-1">📊 Catat Output</button>
                                @endif
                                @if ($op->task_status === 'RUNNING')
                                    <button type="button" wire:click="openBreakdown({{ $op->operation_id }})" class="px-3 py-1.5 rounded text-[11px] font-semibold bg-[#fbeaea] text-[#a3241f] cursor-pointer">⚠ Lapor Breakdown</button>
                                @elseif ($op->task_status === 'QC_HOLD')
                                    <button type="button" wire:click="qcPass({{ $op->operation_id }})" class="px-3 py-1.5 rounded text-[11px] font-semibold bg-[#dcfce7] text-[#15803d] cursor-pointer mr-1">✓ Lulus</button>
                                    <button type="button" wire:click="openQcReject({{ $op->operation_id }})" class="px-3 py-1.5 rounded text-[11px] font-semibold bg-[#fbeaea] text-[#a3241f] cursor-pointer">✕ Tolak</button>
                                @elseif ($op->task_status === 'QC_REJECTED')
                                    <button type="button" wire:click="reworkFromReject({{ $op->operation_id }})" class="px-3 py-1.5 rounded text-[11px] font-semibold bg-[#eef3fb] text-[#2f6fed] cursor-pointer">↻ Rework</button>
                                @elseif ($op->task_status === 'SUBCONTRACT')
                                    <button type="button" wire:click="receiveFromSubcontract({{ $op->operation_id }})" class="px-3 py-1.5 rounded text-[11px] font-semibold bg-[#eef3fb] text-[#2f6fed] cursor-pointer">📦 Terima dari Subkontrak</button>
                                @elseif ($op->task_status !== 'DONE')
                                    <span class="text-muted text-[11px]">—</span>
                                @endif
                                @if ($this->canEditRouting && $op->task_status === 'PENDING')
                                    <button type="button" wire:click="removeProcessRow({{ $op->operation_id }})" title="Hapus baris proses" class="ml-1 px-2 py-1.5 rounded text-[11px] font-semibold bg-[#fbeaea] text-[#a3241f] cursor-pointer">✕</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-8 text-muted">Belum ada task/operation.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- QC Reject modal --}}
    @if ($qcRejectOperationId)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('qcRejectOperationId', null)">
            <div class="bg-white rounded-xl w-[420px] max-w-[95vw] shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center justify-between">
                    <span>Tolak QC</span>
                    <button type="button" wire:click="$set('qcRejectOperationId', null)" class="text-white/70 hover:text-white cursor-pointer">✕</button>
                </div>
                <div class="p-4">
                    <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Alasan Penolakan</label>
                    <textarea wire:model="qcRejectReason" rows="3" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs" placeholder="mis. Warna tidak sesuai Delta E toleransi…"></textarea>
                    <div class="flex justify-end gap-2 pt-3 mt-2 border-t border-[#eef1f5]">
                        <button type="button" wire:click="$set('qcRejectOperationId', null)" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                        <button type="button" wire:click="submitQcReject" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#dc2626] text-white cursor-pointer">Tolak</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Breakdown & Subcontract modal --}}
    @if ($breakdownOperationId)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="closeBreakdown">
            <div class="bg-white rounded-xl w-[560px] max-w-[95vw] max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="bg-[#dc2626] text-white px-4 py-3.5 text-sm font-semibold flex items-center justify-between">
                    <span>⚠ Breakdown &amp; Subkontrak</span>
                    <button type="button" wire:click="closeBreakdown" class="text-white/70 hover:text-white cursor-pointer">✕</button>
                </div>

                <div class="p-4 space-y-4">
                    @if ($breakdownStep === 'report')
                        <div>
                            <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Estimasi Downtime (menit)</label>
                            <input type="number" wire:model="breakdownDowntimeMinutes" min="1" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" wire:click="closeBreakdown" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                            <button type="button" wire:click="submitBreakdownReport" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#dc2626] text-white cursor-pointer">Lapor Breakdown</button>
                        </div>
                    @else
                        <div class="flex gap-2">
                            <button type="button" wire:click="$set('rerouteChoice', 'MACHINE_ALT')" class="flex-1 px-3 py-2 rounded-md text-xs font-semibold cursor-pointer {{ $rerouteChoice === 'MACHINE_ALT' ? 'bg-[#2c3e69] text-white' : 'bg-[#eef1f5] text-[#4b5568]' }}">Mesin Lain</button>
                            <button type="button" wire:click="$set('rerouteChoice', 'SUBCONTRACT')" class="flex-1 px-3 py-2 rounded-md text-xs font-semibold cursor-pointer {{ $rerouteChoice === 'SUBCONTRACT' ? 'bg-[#2c3e69] text-white' : 'bg-[#eef1f5] text-[#4b5568]' }}">Subkontrak</button>
                        </div>

                        @if ($rerouteChoice === 'MACHINE_ALT')
                            <div>
                                <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Mesin Alternatif</label>
                                <select wire:model="altMachineId" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                                    <option value="">-- pilih mesin --</option>
                                    @foreach ($this->altMachines as $m)
                                        <option value="{{ $m->machine_id }}">{{ $m->machine_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" wire:click="closeBreakdown" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                                <button type="button" wire:click="rerouteToMachine" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#2c3e69] text-white cursor-pointer">Pindah Mesin</button>
                            </div>
                        @else
                            <div class="relative">
                                <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Vendor Subkontrak</label>
                                @if ($subVendorId)
                                    <div class="flex items-center gap-1.5">
                                        <input type="text" value="{{ $subVendorName }}" readonly class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs bg-[#f7f7f5]">
                                        <button type="button" wire:click="clearVendor" class="text-[11px] text-muted underline cursor-pointer shrink-0">Ganti</button>
                                    </div>
                                @else
                                    <input type="text" wire:model.live.debounce.400ms="subVendorQuery" autocomplete="off" placeholder="Ketik nama/kode vendor subkon…" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                                    @if ($subVendorQuery && mb_strlen($subVendorQuery) >= 2)
                                        <div class="absolute left-0 right-0 mt-1 bg-white border border-[#e5e7eb] rounded-md shadow-lg z-10 max-h-40 overflow-y-auto">
                                            @forelse ($this->vendorResults as $v)
                                                <div wire:click="selectVendor({{ $v->id_supplier }})" class="px-2.5 py-1.5 text-xs cursor-pointer hover:bg-[#f4f5f8] flex items-center justify-between">
                                                    <span>{{ $v->supplier_name }}</span>
                                                    <span class="text-muted text-[10px]">{{ $v->supplier_code }}</span>
                                                </div>
                                            @empty
                                                <div class="px-2.5 py-1.5 text-xs text-muted">Tidak ditemukan (vendor harus ditandai Subkontraktor).</div>
                                            @endforelse
                                        </div>
                                    @endif
                                @endif
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Kirim Ke Vendor</label>
                                    <input type="date" wire:model="subDueOut" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Estimasi Kembali</label>
                                    <input type="date" wire:model="subDueReturn" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Estimasi Biaya (Rp)</label>
                                <input type="number" wire:model="subEstimatedCost" min="0" step="1000" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                            </div>

                            @unless (Auth::user()->isOpsManagerOrAbove())
                                <p class="text-[11px] text-[#92400e] bg-[#fffbeb] border border-[#fde68a] rounded-md px-3 py-2">⚠ Hanya Manager Operasional ke atas yang bisa menerbitkan PO Subkontrak. Form ini bisa diisi, tapi tombol Terbitkan nonaktif.</p>
                            @endunless

                            <div class="flex justify-end gap-2">
                                <button type="button" wire:click="closeBreakdown" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                                <button type="button" wire:click="createSubcontractPo" @unless (Auth::user()->isOpsManagerOrAbove()) disabled @endunless
                                    class="px-4 py-2 rounded-md text-xs font-semibold text-white {{ Auth::user()->isOpsManagerOrAbove() ? 'bg-[#dc2626] hover:bg-[#b91c1c] cursor-pointer' : 'bg-[#9ca3af] cursor-not-allowed' }}">
                                    Terbitkan PO Subkontrak
                                </button>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Catat Output modal (F15) -- qty baik + baris rinci reject/waste
         ber-tipe, boleh beberapa tipe sekaligus (permintaan user 2026-08-28). --}}
    @if ($outputOperationId)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="closeOutputEntry">
            <div class="bg-white rounded-xl w-[520px] max-w-[95vw] max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center justify-between">
                    <span>📊 Catat Output</span>
                    <button type="button" wire:click="closeOutputEntry" class="text-white/70 hover:text-white cursor-pointer">✕</button>
                </div>
                <div class="p-4 space-y-4">
                    <p class="text-[11px] text-muted">Boleh dicatat berkali-kali (mis. per shift/giliran) — angka di grid otomatis dijumlah dari semua entri.
                        Tipe reject/waste tidak lengkap? <a href="{{ route('production.scrap-type') }}" target="_blank" class="text-accent underline">Kelola Tipe Scrap →</a></p>

                    <div>
                        <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Qty Baik</label>
                        <input type="number" wire:model="outputQtyGood" min="0" step="1" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="text-[10px] font-bold text-[#a3241f] uppercase">Reject (opsional, boleh lebih dari 1 tipe)</label>
                            <button type="button" wire:click="addDefectRow('REJECT')" class="text-[11px] font-semibold text-[#a3241f] hover:underline cursor-pointer">+ Tambah</button>
                        </div>
                        <div class="space-y-1.5">
                            @foreach ($outputDefectRows as $i => $row)
                                @continue($row['category'] !== 'REJECT')
                                <div wire:key="defect-row-{{ $i }}" class="flex gap-1.5">
                                    <select wire:model="outputDefectRows.{{ $i }}.scrap_type_id" class="flex-1 px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                                        <option value="">-- pilih tipe --</option>
                                        @foreach ($this->rejectTypes as $t)
                                            <option value="{{ $t->type_id }}">{{ $t->type_name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" wire:model="outputDefectRows.{{ $i }}.qty" min="0" step="1" placeholder="qty" class="w-20 px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                                    <button type="button" wire:click="removeDefectRow({{ $i }})" class="px-2 text-[#a3241f] cursor-pointer">✕</button>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="text-[10px] font-bold text-[#92400e] uppercase">Waste/Scrap (opsional, boleh lebih dari 1 tipe)</label>
                            <button type="button" wire:click="addDefectRow('WASTE')" class="text-[11px] font-semibold text-[#92400e] hover:underline cursor-pointer">+ Tambah</button>
                        </div>
                        <div class="space-y-1.5">
                            @foreach ($outputDefectRows as $i => $row)
                                @continue($row['category'] !== 'WASTE')
                                <div wire:key="defect-row-{{ $i }}" class="flex gap-1.5">
                                    <select wire:model="outputDefectRows.{{ $i }}.scrap_type_id" class="flex-1 px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                                        <option value="">-- pilih tipe --</option>
                                        @foreach ($this->wasteTypes as $t)
                                            <option value="{{ $t->type_id }}">{{ $t->type_name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" wire:model="outputDefectRows.{{ $i }}.qty" min="0" step="1" placeholder="qty" class="w-20 px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                                    <button type="button" wire:click="removeDefectRow({{ $i }})" class="px-2 text-[#92400e] cursor-pointer">✕</button>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Shift (opsional)</label>
                            <input type="text" wire:model="outputShiftNote" placeholder="mis. Shift 1" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">Catatan (opsional)</label>
                            <input type="text" wire:model="outputNotes" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 mt-2 border-t border-[#eef1f5]">
                        <button type="button" wire:click="closeOutputEntry" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                        <button type="button" wire:click="saveOutputEntry" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#2c3e69] text-white cursor-pointer">Simpan</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div x-show="toastShow" x-transition x-cloak
        class="fixed bottom-5 left-1/2 -translate-x-1/2 bg-[#1f2328] text-white text-xs font-medium px-4 py-2.5 rounded-md shadow-lg z-[200]"
        x-text="toastMsg"></div>
</div>
