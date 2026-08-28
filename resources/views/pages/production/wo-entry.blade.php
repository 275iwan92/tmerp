<?php

use App\Models\Master\EmpEmployee;
use App\Models\Master\MatRaw;
use App\Models\Master\OrgDivision;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdPartProcessEmployeeRate;
use App\Models\Master\ProdPartProcessMachineRate;
use App\Models\Master\ProdProcess;
use App\Models\Prd\PrdBreakdownEvent;
use App\Models\Prd\PrdCrewAssignment;
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
use Illuminate\Support\Facades\DB;
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
    // Nullable -- layar ini SEKARANG juga jadi entry point "Add New WO"
    // (route production.wo.new, tanpa {wo} di URL). Permintaan user
    // 2026-08-28: hapus Screen #1 (wo-generate.blade.php) yang berdiri
    // sendiri, gabung ke sini -- WO Detail = tempat mulai (belum ada WO,
    // modal Plan Delivery kebuka otomatis) SEKALIGUS tempat edit (WO sudah
    // ada, tab Master Card/BOP/BOM terisi).
    public ?int $wo = null;

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

    // Modal "Pilih Plan Delivery Lain" (Part #1, permintaan user 2026-08-28:
    // picklist Screen #1 lama TIDAK lagi grid permanen di layar, jadi
    // tombol+popup di dalam WO Detail ini). Query sama persis
    // wo-generate.blade.php (eligibleDeliveries) -- sengaja diduplikasi
    // (bukan diekstrak ke trait/service) supaya masing2 layar tetap berdiri
    // sendiri, pola sama beberapa computed lain di codebase ini.
    public bool $showDeliveryModal = false;

    public function mount(?int $wo = null): void
    {
        $this->wo = $wo;

        if ($this->wo === null) {
            // "Add New WO" -- belum ada WO dipilih, langsung buka modal
            // picklist Plan Delivery supaya PPIC tidak lihat layar kosong.
            $this->showDeliveryModal = true;
        }
    }

    public function openDeliveryModal(): void
    {
        $this->showDeliveryModal = true;
    }

    public function closeDeliveryModal(): void
    {
        $this->showDeliveryModal = false;
    }

    #[Computed]
    public function eligibleDeliveries()
    {
        $alreadyGeneratedIds = \App\Models\Prd\PrdWo::whereNotNull('source_delivery_id')->pluck('source_delivery_id');

        return \App\Models\Sdi\CoOrderDelivery::with(['order.quotation.customer', 'item.inquiry.parts'])
            ->where('status', 'PLANNED')
            ->whereHas('order', fn ($q) => $q->where('doc_status', 'CONFIRMED'))
            ->whereNotIn('id_delivery', $alreadyGeneratedIds)
            ->orderBy('ship_date')
            ->limit(100)
            ->get();
    }

    public function pickPlanDelivery(int $deliveryId)
    {
        $delivery = \App\Models\Sdi\CoOrderDelivery::findOrFail($deliveryId);

        try {
            $wos = app(\App\Services\Scheduling\WoGenerationService::class)->generateFromDelivery($delivery);
        } catch (\RuntimeException $e) {
            $this->dispatch('wo-toast', message: $e->getMessage());

            return;
        }

        $this->showDeliveryModal = false;

        if ($wos->count() === 1) {
            return $this->redirect(route('production.wo.entry', ['wo' => $wos->first()->wo_id]));
        }

        return $this->redirect(route('production.wo.list'));
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

    // Master Card / Kartu Induk -- docs/production_scheduling_wo_generation_delta.md
    // §5.3: vw_master_card SUDAH ADA di tm2mst (dibangun jauh sebelum sesi ini,
    // bukan bikinan baru), tinggal dikonsumsi. Diambil per product_id --
    // KETERBATASAN SADAR: kalau 1 produk py >1 prod_part, view ini bisa balikin
    // >1 baris (kode_pisau/ukuran_cetak/dst beda per part) dan kita ambil
    // baris PERTAMA saja, karena prd_wo.part_code cuma teks snapshot dari
    // iqp_part -- tak ada FK balik ke prod_part spesifik mana yang cocok
    // (gap yang sama dicatat di WoGenerationService/delta doc §3). Cukup utk
    // produk 1-part (dummy data sekarang), tidak precise utk multi-part.
    #[Computed]
    public function masterCard()
    {
        if (! $this->workOrder->product_id) {
            return null;
        }

        return DB::connection('tm2mst')->table('vw_master_card')
            ->where('id_product', $this->workOrder->product_id)
            ->first();
    }

    #[Computed]
    public function masterCardSignoff()
    {
        if (! $this->workOrder->product_id) {
            return collect();
        }

        return DB::connection('tm2mst')->table('prod_mastercard_signoff')
            ->where('product_id', $this->workOrder->product_id)
            ->get()
            ->keyBy('role_name');
    }

    // Bill of Material -- versi grid (Material&Spec di kiri, lalu Qty
    // Kebutuhan/Tersedia/Harus Dibeli) per instruksi user 2026-08-28.
    // Qty Tersedia diambil LIVE dari v_rm_available (tm2inv, sudah ada sejak
    // modul Inventory dibangun -- bukan skema baru). Qty Kebutuhan SENGAJA
    // masih simplifikasi (= qty WO, bukan qty per lembar/plano hasil
    // Imposition) -- perhitungan presisi butuh qty_factor per proses yang
    // masih jadi gap terbuka (delta doc §3), dicatat bukan disembunyikan.
    // "Qty Harus Dibeli" = selisih kebutuhan-tersedia (shortfall sederhana,
    // BUKAN MRP netting penuh lintas-WO -- itu F10 yang sengaja dideferred).
    #[Computed]
    public function billOfMaterialRows()
    {
        return $this->billOfMaterial->map(function ($mat) {
            $rawmat = $mat->rawmat_id ? MatRaw::find($mat->rawmat_id) : null;
            $unit = $rawmat?->uom_stock ?: 'Pcs';

            $qtyKebutuhan = (float) $this->workOrder->qty;
            $qtyTersedia = $mat->rawmat_id
                ? (float) DB::connection('tm2inv')->table('v_rm_available')->where('material_id', $mat->rawmat_id)->sum('qty_available')
                : 0.0;
            $qtyHarusDibeli = max(0, $qtyKebutuhan - $qtyTersedia);

            return (object) [
                'name' => $mat->rawmat_name,
                'spec' => trim(collect([
                    $mat->gram_weight ? number_format((float) $mat->gram_weight, 0, ',', '.').' gsm' : null,
                    $mat->flute_type ?: null,
                    $mat->is_primary ? 'Primer' : null,
                ])->filter()->implode(' · ')),
                'unit' => $unit,
                'qty_kebutuhan' => $qtyKebutuhan,
                'qty_tersedia' => $qtyTersedia,
                'qty_harus_dibeli' => $qtyHarusDibeli,
            ];
        });
    }

    public function updatePriority(string $value): void
    {
        if (! in_array($value, ['NORMAL', 'URGENT', 'SUPER_URGENT'], true)) {
            return;
        }

        $this->workOrder->update(['priority' => $value, 'updated_by' => Auth::user()->login]);
        unset($this->workOrder);
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

    // "Operator" default (dblclick-edit di grid Bill of Process, permintaan
    // user 2026-08-28) -- diikat ke crew_role OPERATOR_UTAMA yang sudah ada
    // (dipakai jadwal manual/Scheduling Board), bukan kolom baru.
    public function updateOperator(int $operationId, $employeeId): void
    {
        $op = PrdWoOperation::findOrFail($operationId);
        PrdCrewAssignment::where('operation_id', $operationId)->where('crew_role', 'OPERATOR_UTAMA')->delete();

        if ($employeeId) {
            PrdCrewAssignment::create([
                'operation_id' => $operationId,
                'employee_id' => $employeeId,
                'crew_role' => 'OPERATOR_UTAMA',
                'created_by' => Auth::user()->login,
            ]);
        }

        unset($this->workOrder);
    }

    // Override manual cepat via dblclick (permintaan user 2026-08-28) --
    // TIDAK lewat validasi kalender/konflik mesin (itu tanggung jawab
    // MachineCalendarService/BackwardSchedulingService via drag-resize di
    // Scheduling Board, tombol "📅 Gantt" per baris). Dipakai utk koreksi
    // cepat kecil, digated sama seperti field routing lain (canEditRouting).
    public function updateOperationDateTime(int $operationId, string $field, string $value): void
    {
        if (! in_array($field, ['target_start', 'target_finish'], true) || $value === '') {
            return;
        }

        $op = PrdWoOperation::findOrFail($operationId);
        $op->update([$field => Carbon::parse($value), 'updated_by' => Auth::user()->login]);
        unset($this->workOrder);
    }

    #[Computed]
    public function employeeOptions()
    {
        return EmpEmployee::where('activate', true)->orderBy('full_name')->limit(200)->get();
    }

    public function openInSchedulingBoard()
    {
        $date = $this->firstScheduledDate ?? $this->workOrder->due_date?->toDateString() ?? now()->toDateString();

        return $this->redirect(route('production.scheduling-board', ['date' => $date]));
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

<div x-data="{ toastMsg: '', toastShow: false, toastTimer: null, tab: 'mc' }"
    x-on:wo-toast.window="toastMsg = $event.detail.message; toastShow = true; clearTimeout(toastTimer); toastTimer = setTimeout(() => toastShow = false, 2500)">
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        @if ($this->wo)
            <b class="text-[15px]">📋 WO Detail</b>
            <span class="text-xs text-white/75">{{ $this->workOrder->wo_no }}</span>
            @if ($this->workOrder->is_hot_job)
                <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full bg-[#dc2626] text-white">🔥 HOT</span>
            @endif
            <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full bg-white/15 text-white">{{ $this->workOrder->wo_status }}</span>
            <a href="{{ route('production.wo.print', ['wo' => $this->wo]) }}" target="_blank" class="ml-auto inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-[#43508a] hover:brightness-110 text-[11px] font-bold uppercase tracking-wide text-white no-underline">🖨 Cetak</a>
        @else
            <b class="text-[15px]">📋 Work Order Baru</b>
            <span class="text-xs text-white/75">belum ada WO dipilih</span>
        @endif
        <button type="button" wire:click="backToList" title="Kembali ke Worklist WO"
            class="ml-auto inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full hover:brightness-110 cursor-pointer">
            <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-xs font-bold">←</span>
        </button>
    </header>

    <div class="max-w-[1300px] mx-auto my-5 px-4">
    @if ($this->wo)

        {{-- PART #1: strip sumber Plan Delivery + tombol buka modal picklist
             (bukan grid permanen -- docs/production_scheduling_wo_generation_delta.md §5). --}}
        <div class="bg-white border border-[#d9dfe8] rounded-[10px] px-3.5 py-2.5 mb-3 flex items-center gap-3 flex-wrap text-xs">
            <div>
                <div class="text-[9px] text-muted uppercase font-bold tracking-wide">Sumber Plan Delivery</div>
                <div class="font-semibold">
                    {{ $this->workOrder->coOrder?->co_no ?? '—' }}
                    <span class="text-[#c9cfd9] mx-1.5">·</span>{{ $this->workOrder->coOrder?->quotation?->customer?->customer_name ?? 'GENERAL' }}
                    <span class="text-[#c9cfd9] mx-1.5">·</span>Part {{ $this->workOrder->part_code ?? '—' }}
                </div>
            </div>
            <span class="text-[9.5px] font-bold px-2.5 py-1 rounded-full bg-[#fef6e7] text-[#b45309]">🔒 Sedang Diproses ({{ $this->workOrder->wo_no }})</span>
            <span class="ml-auto"></span>
            <button type="button" wire:click="openDeliveryModal" class="px-3.5 py-1.5 rounded-md text-[11px] font-bold border border-[#d9dfe8] text-navy cursor-pointer">🔀 Pilih Plan Delivery Lain</button>
        </div>

        {{-- Tab nav: Master Card / Bill of Process / Bill of Material --}}
        <div class="bg-white border border-[#d9dfe8] rounded-t-[10px] flex gap-0.5 px-2 -mb-px">
            <button type="button" @click="tab = 'mc'" :class="tab === 'mc' ? 'text-navy border-accent' : 'text-muted border-transparent'" class="font-bold text-[11.5px] px-4 py-2.5 border-b-[2.5px] cursor-pointer">📇 Master Card</button>
            <button type="button" @click="tab = 'bop'" :class="tab === 'bop' ? 'text-navy border-accent' : 'text-muted border-transparent'" class="font-bold text-[11.5px] px-4 py-2.5 border-b-[2.5px] cursor-pointer">⚙ Bill of Process <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded-full bg-[#eef1f5]">{{ $this->workOrder->operations->count() }}</span></button>
            <button type="button" @click="tab = 'bom'" :class="tab === 'bom' ? 'text-navy border-accent' : 'text-muted border-transparent'" class="font-bold text-[11.5px] px-4 py-2.5 border-b-[2.5px] cursor-pointer">📦 Bill of Material <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded-full bg-[#eef1f5]">{{ $this->billOfMaterialRows->count() }}</span></button>
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-b-[10px] rounded-tr-[10px] overflow-hidden mb-4">

            {{-- ================= TAB: MASTER CARD ================= --}}
            <div x-show="tab === 'mc'" x-cloak>
                <div class="px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide font-bold border-b border-[#eef1f5] flex items-center justify-between">
                    <span>Master Card / Kartu Induk</span>
                    <span class="normal-case font-semibold text-[10.5px]">{{ $this->masterCard?->kode_item ?? $this->workOrder->product?->product_code ?? '—' }}</span>
                </div>

                <div class="grid md:grid-cols-[280px_1fr]">
                    {{-- PART #5: artwork + dieline (potong/lipat) + anotasi dimensi --}}
                    <div class="p-3 border-b md:border-b-0 md:border-r border-[#eef1f5] bg-[#fbfcfe]">
                        <div class="relative bg-white border border-[#e2e6ee] rounded-lg overflow-hidden aspect-[4/5] flex items-center justify-center p-5">
                            <div class="relative w-full h-full border-2 border-navy rounded-sm bg-[repeating-linear-gradient(115deg,#f4f6fa_0_10px,#eef1f6_10px_20px)]">
                                <div class="absolute border-b-[1.5px] border-dashed border-[#2c7be5]" style="left:0;right:0;top:24%"></div>
                                <div class="absolute border-b-[1.5px] border-dashed border-[#2c7be5]" style="left:0;right:0;bottom:22%"></div>
                                <div class="absolute inset-0 border-2 border-[#dc2626] rounded-sm pointer-events-none"></div>
                                <div class="absolute inset-0 flex items-center justify-center text-center px-3">
                                    <span class="text-[10.5px] font-semibold text-[#8a94a6]">Artwork belum tersimpan<br><span class="font-mono text-[9px]">prod_part_artwork</span></span>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-3 text-[9.5px] text-[#556] mt-2 flex-wrap">
                            <span class="flex items-center gap-1"><span class="w-3.5 h-0 border-t-2 border-[#dc2626] inline-block"></span> Potong (putus)</span>
                            <span class="flex items-center gap-1"><span class="w-3.5 h-0 border-t-2 border-dashed border-[#2c7be5] inline-block"></span> Lipat (tidak putus)</span>
                        </div>
                        <div class="text-[9.7px] text-muted mt-1.5">Dimensi: {{ $this->masterCard?->dimensi ?? '—' }} · Kode Pisau: {{ $this->masterCard?->kode_pisau ?: '— belum diisi' }}</div>
                    </div>

                    <div class="p-3.5">
                        {{-- Identitas Order --}}
                        <div class="mb-3.5 pb-3.5 border-b border-dashed border-[#e2e6ee]">
                            <div class="text-[9px] uppercase tracking-wide font-bold text-accent mb-1.5">Identitas Order</div>
                            <div class="grid grid-cols-4 gap-2.5 text-[11.5px]">
                                <div><div class="text-[8.7px] text-muted uppercase font-bold">No. CO</div><div class="font-bold font-mono text-accent">{{ $this->workOrder->coOrder?->co_no ?? '—' }}</div></div>
                                <div><div class="text-[8.7px] text-muted uppercase font-bold">No. Desain</div><div class="font-bold">{{ $this->masterCard?->no_desain ?: '— belum diisi' }}</div></div>
                                <div><div class="text-[8.7px] text-muted uppercase font-bold">Due Date</div><div class="font-bold">{{ $this->workOrder->due_date?->translatedFormat('d M Y') ?? '—' }}</div></div>
                                <div>
                                    <div class="text-[8.7px] text-muted uppercase font-bold mb-0.5">Priority</div>
                                    <div class="flex gap-1">
                                        <button type="button" wire:click="updatePriority('NORMAL')" class="px-2 py-0.5 rounded-full text-[9.5px] font-bold border cursor-pointer {{ $this->workOrder->priority === 'NORMAL' ? 'bg-[#eef1f5] border-[#9aa6bd] text-[#495066]' : 'border-[#d9dfe8] text-muted' }}">Normal</button>
                                        <button type="button" wire:click="updatePriority('URGENT')" class="px-2 py-0.5 rounded-full text-[9.5px] font-bold border cursor-pointer {{ $this->workOrder->priority === 'URGENT' ? 'bg-[#fef6e7] border-[#f3c27a] text-[#b45309]' : 'border-[#d9dfe8] text-muted' }}">Urgent</button>
                                        <button type="button" wire:click="updatePriority('SUPER_URGENT')" class="px-2 py-0.5 rounded-full text-[9.5px] font-bold border cursor-pointer {{ $this->workOrder->priority === 'SUPER_URGENT' ? 'bg-[#fdecec] border-[#f0a6a3] text-[#dc2626]' : 'border-[#d9dfe8] text-muted' }}">Super Urgent</button>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2.5 text-[11.5px] mt-2.5">
                                <div><div class="text-[8.7px] text-muted uppercase font-bold">Nama Produk</div><div class="font-bold">{{ $this->workOrder->product?->product_name ?? '—' }}</div></div>
                                <div><div class="text-[8.7px] text-muted uppercase font-bold">Customer</div><div class="font-bold">{{ $this->workOrder->coOrder?->quotation?->customer?->customer_name ?? '—' }}</div></div>
                            </div>
                        </div>

                        {{-- Spesifikasi Produk --}}
                        <div class="mb-3.5 pb-3.5 border-b border-dashed border-[#e2e6ee]">
                            <div class="text-[9px] uppercase tracking-wide font-bold text-accent mb-1.5">Spesifikasi Produk</div>
                            <div class="grid grid-cols-4 gap-2.5 text-[11.5px]">
                                <div><div class="text-[8.7px] text-muted uppercase font-bold">Qty Order</div><div class="font-bold">{{ number_format((float) $this->workOrder->qty, 0, ',', '.') }} Pcs</div></div>
                                <div><div class="text-[8.7px] text-muted uppercase font-bold">Ukuran Cetak</div><div class="font-bold">{{ $this->masterCard?->ukuran_cetak ?: '— belum diisi' }}</div></div>
                                <div><div class="text-[8.7px] text-muted uppercase font-bold">Jenis Finishing</div><div class="font-bold">{{ $this->masterCard?->jenis_finishing ?: '— belum diisi' }}</div></div>
                                <div><div class="text-[8.7px] text-muted uppercase font-bold">Bentuk Sambungan</div><div class="font-bold">{{ $this->masterCard?->bentuk_sambungan ?: '— belum diisi' }}</div></div>
                                <div><div class="text-[8.7px] text-muted uppercase font-bold">Isi Satu Cetakan</div><div class="font-bold">{{ $this->masterCard?->isi_satu_cetakan ?: '— belum diisi' }}</div></div>
                                <div><div class="text-[8.7px] text-muted uppercase font-bold">Pack Qty</div><div class="font-bold">{{ $this->masterCard?->pack_qty ? number_format((float) $this->masterCard->pack_qty, 0, ',', '.') : '— belum diisi' }}</div></div>
                                <div class="col-span-2"><div class="text-[8.7px] text-muted uppercase font-bold">Urutan Pekerjaan</div><div class="font-bold text-[10.5px]">{{ $this->masterCard?->urutan_pekerjaan ?: '— belum diisi' }}</div></div>
                            </div>
                        </div>

                        {{-- Warna Cetak --}}
                        <div>
                            <div class="text-[9px] uppercase tracking-wide font-bold text-accent mb-1.5">Warna Cetak</div>
                            <div class="grid grid-cols-2 gap-2.5">
                                <div class="bg-[#fbfcfe] border border-[#eef1f6] rounded-md px-2.5 py-2">
                                    <div class="text-[8.8px] uppercase font-bold text-muted mb-1">Warna Pertama (CS)</div>
                                    <div class="text-[10.8px] font-semibold">{{ $this->masterCard?->warna_cs ?: '— belum diisi' }}</div>
                                </div>
                                <div class="bg-[#fbfcfe] border border-[#eef1f6] rounded-md px-2.5 py-2">
                                    <div class="text-[8.8px] uppercase font-bold text-muted mb-1">Warna Kedua (KS)</div>
                                    <div class="text-[10.8px] font-semibold {{ $this->masterCard?->warna_ks ? '' : 'text-[#b7bec9] italic font-normal' }}">{{ $this->masterCard?->warna_ks ?: '— tidak dipakai' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mx-3.5 mb-3.5 text-[10px] font-bold px-2.5 py-2 rounded-md bg-[#fdecec] border border-[#f0b8b5] text-[#a3241f]">⚠ Semua proof print (digital + letak) wajib dilampirkan bersama Master Card sebelum cetak massal.</div>

                <div class="grid grid-cols-5 border-t border-[#eef1f5]">
                    @foreach (['Desainer', 'Marketing', 'PPIC', 'QA', 'Plant Manager'] as $role)
                        @php($so = $this->masterCardSignoff->get($role))
                        <div class="px-3 py-2.5 {{ ! $loop->last ? 'border-r border-[#eef1f5]' : '' }}">
                            <div class="text-[9.3px] uppercase font-bold text-muted tracking-wide mb-1">{{ $role }}</div>
                            @if ($so?->signed_at)
                                <div class="text-[10.5px] font-bold text-[#0e7a44]">✓ Signed<span class="block text-[8.7px] font-semibold text-muted mt-0.5">{{ $so->signed_by }} · {{ \Carbon\Carbon::parse($so->signed_at)->format('d/m H:i') }}</span></div>
                            @else
                                <div class="text-[10px] text-[#a3aabb] italic">Menunggu tanda tangan</div>
                            @endif
                        </div>
                    @endforeach
                </div>
                <p class="px-3.5 py-2.5 text-[10px] text-muted italic border-t border-[#eef1f5]">"— belum diisi" = kolom sudah ada di skema (<code>vw_master_card</code>/<code>prod_part</code>) tapi datanya kosong utk produk ini.</p>
            </div>

            {{-- ================= TAB: BILL OF PROCESS ================= --}}
            <div x-show="tab === 'bop'" x-cloak>
                <div class="px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide font-bold border-b border-[#eef1f5] flex items-center justify-between">
                    <span>Bill of Process</span>
                    @if ($this->canEditRouting)
                        <button type="button" wire:click="addProcessRow" class="normal-case font-semibold text-[11px] px-2.5 py-1 rounded bg-[#eef3fb] text-[#2f6fed] cursor-pointer">+ Tambah Proses</button>
                    @endif
                </div>
                <div class="overflow-x-auto">
                <table class="w-full border-collapse text-[12px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-1.5 bg-[#fafbfd] text-[#667] text-[10.5px] uppercase tracking-wide border-b border-[#eef1f5] w-[22%]">Proses &amp; Mesin</th>
                            <th class="text-left px-3 py-1.5 bg-[#fafbfd] text-[#667] text-[10.5px] uppercase tracking-wide border-b border-[#eef1f5] w-[13%]">Operator</th>
                            <th class="text-left px-3 py-1.5 bg-[#fafbfd] text-[#667] text-[10.5px] uppercase tracking-wide border-b border-[#eef1f5] w-[13%]">Est. Mulai</th>
                            <th class="text-left px-3 py-1.5 bg-[#fafbfd] text-[#667] text-[10.5px] uppercase tracking-wide border-b border-[#eef1f5] w-[13%]">Est. Selesai</th>
                            <th class="text-left px-3 py-1.5 bg-[#fafbfd] text-[#667] text-[10.5px] uppercase tracking-wide border-b border-[#eef1f5] w-[12%]">Target Kecepatan</th>
                            <th class="text-left px-3 py-1.5 bg-[#fafbfd] text-[#667] text-[10.5px] uppercase tracking-wide border-b border-[#eef1f5] w-[9%]">Status</th>
                            <th class="text-right px-3 py-1.5 bg-[#fafbfd] text-[#667] text-[10.5px] uppercase tracking-wide border-b border-[#eef1f5]">Link &amp; Act</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->workOrder->operations as $op)
                            <tr wire:key="op-{{ $op->operation_id }}">
                                {{-- Proses & Mesin: identity box, dblclick utk edit selagi PENDING+DRAFT/SIMULATED --}}
                                <td class="px-3 py-1.5 border-b border-[#eef1f5]" x-data="{ editing: false }">
                                    <template x-if="!editing">
                                        <div @dblclick="editing = @js($this->canEditRouting && $op->task_status === 'PENDING')" class="bg-[#fbfcfe] border border-[#eef1f6] rounded-md px-2 py-1 cursor-pointer">
                                            <div class="flex items-center gap-1.5">
                                                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-[#eef1f5] text-muted text-[9px] font-bold shrink-0">{{ $op->seq_no }}</span>
                                                <span class="font-bold text-[11.5px]">{{ $op->process_name ?: '—' }}</span>
                                            </div>
                                            <div class="text-[9.7px] text-muted mt-0.5">{{ $op->resource_type === 'MACHINE' ? ($op->machine?->machine_name ?? '—') : 'Manual' }}</div>
                                        </div>
                                    </template>
                                    <template x-if="editing">
                                        <div class="space-y-1">
                                            <select wire:change="updateOperationField({{ $op->operation_id }}, 'process_name', $event.target.value)" @change="editing = false" class="w-full px-1.5 py-1 border border-accent rounded text-[11px]">
                                                <option value="">— pilih proses —</option>
                                                @foreach ($this->processOptions as $proc)
                                                    <option value="{{ $proc->process_name }}" @selected($op->process_name === $proc->process_name)>{{ $proc->process_name }}</option>
                                                @endforeach
                                            </select>
                                            <div class="flex gap-1">
                                                <select wire:change="updateOperationField({{ $op->operation_id }}, 'resource_type', $event.target.value)" class="px-1.5 py-1 border border-accent rounded text-[11px]">
                                                    <option value="MACHINE" @selected($op->resource_type === 'MACHINE')>Mesin</option>
                                                    <option value="MANUAL" @selected($op->resource_type === 'MANUAL')>Manual</option>
                                                </select>
                                                @if ($op->resource_type === 'MACHINE')
                                                    <select wire:change="updateOperationField({{ $op->operation_id }}, 'machine_id', $event.target.value)" @change="editing = false" class="px-1.5 py-1 border border-accent rounded text-[11px]">
                                                        <option value="">— mesin —</option>
                                                        @foreach ($this->machineOptions as $mc)
                                                            <option value="{{ $mc->machine_id }}" @selected($op->machine_id === $mc->machine_id)>{{ $mc->machine_name }}</option>
                                                        @endforeach
                                                    </select>
                                                @endif
                                            </div>
                                        </div>
                                    </template>
                                </td>

                                {{-- Operator: dblclick -> dropdown --}}
                                <td class="px-3 py-1.5 border-b border-[#eef1f5]" x-data="{ editing: false }">
                                    <template x-if="!editing">
                                        <span @dblclick="editing = true" class="cursor-pointer border-b border-dashed border-transparent hover:border-[#c7cfda]">{{ $op->operatorUtama()?->employee?->full_name ?? '—' }}</span>
                                    </template>
                                    <template x-if="editing">
                                        <select autofocus wire:change="updateOperator({{ $op->operation_id }}, $event.target.value)" @change="editing = false" @blur="editing = false" class="w-full px-1.5 py-1 border border-accent rounded text-[11px]">
                                            <option value="">— tidak ada —</option>
                                            @foreach ($this->employeeOptions as $e)
                                                <option value="{{ $e->employee_id }}" @selected($op->operatorUtama()?->employee_id === $e->employee_id)>{{ $e->full_name }}</option>
                                            @endforeach
                                        </select>
                                    </template>
                                </td>

                                {{-- Est. Mulai --}}
                                <td class="px-3 py-1.5 border-b border-[#eef1f5]" x-data="{ editing: false }">
                                    <template x-if="!editing">
                                        <div @dblclick="editing = @js($this->canEditRouting)" class="cursor-pointer">
                                            <span class="border-b border-dashed border-transparent hover:border-[#c7cfda]">{{ $op->target_start?->format('d/m H:i') ?? '—' }}</span>
                                            <span class="block text-[8.5px] uppercase font-bold text-muted mt-0.5">⚙ dari engine</span>
                                        </div>
                                    </template>
                                    <template x-if="editing">
                                        <input type="datetime-local" autofocus value="{{ $op->target_start?->format('Y-m-d\TH:i') }}" wire:change="updateOperationDateTime({{ $op->operation_id }}, 'target_start', $event.target.value)" @change="editing = false" @blur="editing = false" class="w-full px-1.5 py-1 border border-accent rounded text-[11px]">
                                    </template>
                                </td>

                                {{-- Est. Selesai --}}
                                <td class="px-3 py-1.5 border-b border-[#eef1f5]" x-data="{ editing: false }">
                                    <template x-if="!editing">
                                        <div @dblclick="editing = @js($this->canEditRouting)" class="cursor-pointer">
                                            <span class="border-b border-dashed border-transparent hover:border-[#c7cfda]">{{ $op->target_finish?->format('d/m H:i') ?? '—' }}</span>
                                            <span class="block text-[8.5px] uppercase font-bold text-muted mt-0.5">⚙ dari engine</span>
                                        </div>
                                    </template>
                                    <template x-if="editing">
                                        <input type="datetime-local" autofocus value="{{ $op->target_finish?->format('Y-m-d\TH:i') }}" wire:change="updateOperationDateTime({{ $op->operation_id }}, 'target_finish', $event.target.value)" @change="editing = false" @blur="editing = false" class="w-full px-1.5 py-1 border border-accent rounded text-[11px]">
                                    </template>
                                </td>

                                {{-- Target Kecepatan --}}
                                <td class="px-3 py-1.5 border-b border-[#eef1f5]" x-data="{ editing: false }">
                                    <template x-if="!editing">
                                        <span @dblclick="editing = @js($this->canEditRouting)" class="cursor-pointer border-b border-dashed border-transparent hover:border-[#c7cfda]">{{ $op->rate_band_value ? number_format((float) $op->rate_band_value, 0, ',', '.') : '—' }}</span>
                                    </template>
                                    <template x-if="editing">
                                        <div class="flex items-center gap-1">
                                            <input type="number" step="0.01" autofocus value="{{ $op->rate_band_value }}" wire:change="updateOperationField({{ $op->operation_id }}, 'rate_band_value', $event.target.value)" @change="editing = false" @blur="editing = false" class="w-16 px-1.5 py-1 border border-accent rounded text-[11px]">
                                            <button type="button" wire:click="refillTargetDefault({{ $op->operation_id }})" title="Ambil target default" class="text-[12px] cursor-pointer">↻</button>
                                        </div>
                                    </template>
                                </td>

                                <td class="px-3 py-1.5 border-b border-[#eef1f5]">
                                    <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded-full {{ $this->statusBadgeClasses($op->task_status) }}">{{ $op->task_status }}</span>
                                    @if (! $op->outputs->isEmpty())
                                        <div class="text-[9.5px] text-muted mt-0.5">{{ number_format($op->totalQtyGood(), 0, ',', '.') }}/{{ number_format((float) $op->qty, 0, ',', '.') }} ({{ $op->outputProgressPercent() }}%)</div>
                                    @endif
                                </td>

                                <td class="px-2 py-1.5 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1 flex-wrap justify-end">
                                        <button type="button" wire:click="openInSchedulingBoard" title="Ubah jadwal di Gantt Chart" class="w-6 h-6 rounded-md hover:bg-[#eef1f5] cursor-pointer">📅</button>
                                        <a href="{{ route('production.process-rate') }}" target="_blank" title="Kelola target rate proses×mesin/pegawai" class="w-6 h-6 inline-flex items-center justify-center rounded-md hover:bg-[#eef1f5] cursor-pointer">⚙</a>
                                        @if (in_array($op->task_status, ['RUNNING', 'DONE'], true))
                                            <button type="button" wire:click="openOutputEntry({{ $op->operation_id }})" title="Catat Output" class="w-6 h-6 rounded-md hover:bg-[#eef3fb] cursor-pointer">📊</button>
                                        @endif
                                        @if ($op->task_status === 'RUNNING')
                                            <button type="button" wire:click="openBreakdown({{ $op->operation_id }})" title="Lapor Breakdown" class="w-6 h-6 rounded-md hover:bg-[#fbeaea] cursor-pointer">⚠</button>
                                        @elseif ($op->task_status === 'QC_HOLD')
                                            <button type="button" wire:click="qcPass({{ $op->operation_id }})" title="Lulus" class="w-6 h-6 rounded-md hover:bg-[#dcfce7] cursor-pointer">✓</button>
                                            <button type="button" wire:click="openQcReject({{ $op->operation_id }})" title="Tolak" class="w-6 h-6 rounded-md hover:bg-[#fbeaea] cursor-pointer">✕</button>
                                        @elseif ($op->task_status === 'QC_REJECTED')
                                            <button type="button" wire:click="reworkFromReject({{ $op->operation_id }})" title="Rework" class="w-6 h-6 rounded-md hover:bg-[#eef3fb] cursor-pointer">↻</button>
                                        @elseif ($op->task_status === 'SUBCONTRACT')
                                            <button type="button" wire:click="receiveFromSubcontract({{ $op->operation_id }})" title="Terima dari Subkontrak" class="w-6 h-6 rounded-md hover:bg-[#eef3fb] cursor-pointer">📦</button>
                                        @endif
                                        @if ($this->canEditRouting && $op->task_status === 'PENDING')
                                            <button type="button" wire:click="removeProcessRow({{ $op->operation_id }})" title="Hapus baris proses" class="w-6 h-6 rounded-md text-[#a3241f] hover:bg-[#fbeaea] cursor-pointer">✕</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-8 text-muted">Belum ada task/operation.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
                <p class="px-3.5 py-2.5 text-[10px] text-muted italic border-t border-[#eef1f5]">Est. Mulai/Selesai diisi otomatis Scheduling Engine — dblclick utk koreksi cepat (tanpa validasi kalender), atau klik "📅" utk ubah visual di Gantt Chart. Kolom lain (Operator, Target Kecepatan, Proses/Mesin selagi belum berjalan) juga dblclick utk ubah.</p>
            </div>

            {{-- ================= TAB: BILL OF MATERIAL ================= --}}
            <div x-show="tab === 'bom'" x-cloak>
                <div class="px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide font-bold border-b border-[#eef1f5] flex items-center justify-between">
                    <span>Bill of Material</span>
                    <span class="normal-case font-semibold text-[10px] text-muted">dari Product Costing + stok gudang live</span>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full border-collapse text-[12px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-1.5 bg-[#fafbfd] text-[#667] text-[10.5px] uppercase tracking-wide border-b border-[#eef1f5] w-[32%]">Material &amp; Spesifikasi</th>
                            <th class="text-right px-3 py-1.5 bg-[#fafbfd] text-[#667] text-[10.5px] uppercase tracking-wide border-b border-[#eef1f5] w-[18%]">Qty Kebutuhan</th>
                            <th class="text-right px-3 py-1.5 bg-[#fafbfd] text-[#667] text-[10.5px] uppercase tracking-wide border-b border-[#eef1f5] w-[20%]">Qty Tersedia (Gudang)</th>
                            <th class="text-right px-3 py-1.5 bg-[#fafbfd] text-[#667] text-[10.5px] uppercase tracking-wide border-b border-[#eef1f5] w-[18%]">Qty Harus Dibeli</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->billOfMaterialRows as $i => $row)
                            <tr wire:key="bom-{{ $i }}">
                                <td class="px-3 py-1.5 border-b border-[#eef1f5]">
                                    <div class="bg-[#fbfcfe] border border-[#eef1f6] rounded-md px-2 py-1">
                                        <div class="font-bold text-[11.5px]">{{ $row->name }}</div>
                                        @if ($row->spec)
                                            <div class="text-[9.7px] text-muted">{{ $row->spec }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-1.5 border-b border-[#eef1f5] text-right"><span class="font-bold">{{ number_format($row->qty_kebutuhan, 0, ',', '.') }}</span> <span class="text-[9.5px] text-muted font-bold">{{ $row->unit }}</span></td>
                                <td class="px-3 py-1.5 border-b border-[#eef1f5] text-right"><span class="font-bold {{ $row->qty_tersedia < $row->qty_kebutuhan ? 'text-[#a3241f]' : 'text-[#0e7a44]' }}">{{ number_format($row->qty_tersedia, 0, ',', '.') }}</span> <span class="text-[9.5px] text-muted font-bold">{{ $row->unit }}</span></td>
                                <td class="px-3 py-1.5 border-b border-[#eef1f5] text-right"><span class="font-bold {{ $row->qty_harus_dibeli > 0 ? 'text-[#a3241f]' : 'text-[#a3aabb]' }}">{{ number_format($row->qty_harus_dibeli, 0, ',', '.') }}</span> <span class="text-[9.5px] text-muted font-bold">{{ $row->unit }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center py-8 text-muted">Belum ada Bill of Material.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
                <p class="px-3.5 py-2.5 text-[10px] text-muted italic border-t border-[#eef1f5]">Qty Tersedia diambil live dari stok gudang (v_rm_available). Qty Kebutuhan masih simplifikasi = qty WO (belum ikut faktor lembar/plano hasil Imposition). "Qty Harus Dibeli" = selisih sederhana, bukan MRP netting penuh lintas-WO.</p>
            </div>
        </div>

    @else
        {{-- "Add New WO" -- belum ada WO, ajak PPIC pilih Plan Delivery dulu
             (modal otomatis kebuka via mount(), tombol ini cuma jaga-jaga
             kalau ditutup manual). --}}
        <div class="bg-white border border-[#d9dfe8] rounded-[10px] py-16 text-center">
            <div class="text-4xl mb-3">📋</div>
            <div class="font-bold text-navy text-[15px] mb-1.5">Belum Ada Work Order Dipilih</div>
            <p class="text-muted text-xs max-w-[420px] mx-auto mb-4">Pilih 1 baris Plan Delivery yang sudah Confirmed untuk digenerate jadi Work Order baru — Bill of Process &amp; Bill of Material otomatis diambil dari Inquiry.</p>
            <button type="button" wire:click="openDeliveryModal" class="px-5 py-2.5 rounded-md text-xs font-bold bg-[#16a34a] text-white cursor-pointer">🔍 Pilih Plan Delivery</button>
        </div>
    @endif
    </div>

    {{-- Modal: Pilih Plan Delivery Lain (Part #1) --}}
    @if ($showDeliveryModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.5)] z-[100] flex items-center justify-center p-6" wire:click.self="closeDeliveryModal">
            <div class="bg-white rounded-xl w-[820px] max-w-full max-h-[82vh] flex flex-col shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center justify-between rounded-t-xl">
                    <span>Pilih Plan Delivery yang akan Diproses</span>
                    <button type="button" wire:click="closeDeliveryModal" class="text-white/70 hover:text-white cursor-pointer">✕</button>
                </div>
                <div class="overflow-y-auto py-1">
                    @forelse ($this->eligibleDeliveries as $d)
                        <div wire:key="pdrow-{{ $d->id_delivery }}" class="px-4 py-2.5 border-b border-[#eef1f5] hover:bg-[#f7f9fd]">
                            <div class="flex items-center gap-3">
                                <div>
                                    <div class="font-mono font-bold text-accent text-[11.5px]">{{ $d->order?->co_no ?? '—' }}</div>
                                    <div class="font-bold text-[11.5px]">{{ $d->order?->quotation?->customer?->customer_name ?? 'GENERAL' }}</div>
                                    <div class="text-[10.3px] text-muted">{{ $d->item?->description ?? '—' }}</div>
                                </div>
                                <div class="ml-auto text-right text-[10.8px] text-muted flex gap-4">
                                    <span>Tgl Kirim<b class="block text-[12px] text-ink">{{ $d->ship_date?->translatedFormat('d M Y') }}</b></span>
                                    <span>Qty<b class="block text-[12px] text-ink">{{ number_format((float) $d->ship_qty, 0, ',', '.') }}</b></span>
                                </div>
                                <button type="button" wire:click="pickPlanDelivery({{ $d->id_delivery }})" wire:confirm="Generate WO dari baris ini? BOM/BOP diambil dari Inquiry, tak bisa dibatalkan (bukan dihapus, WO tetap tercatat)."
                                    class="px-3.5 py-1.5 rounded text-[11px] font-bold bg-[#16a34a] text-white cursor-pointer shrink-0">OK · Proses jadi WO</button>
                            </div>
                            @if ($d->item?->inquiry?->parts?->count() > 1)
                                <div class="text-[10px] text-muted mt-1">↳ {{ $d->item->inquiry->parts->count() }} part — akan jadi {{ $d->item->inquiry->parts->count() }} WO sekaligus</div>
                            @endif
                        </div>
                    @empty
                        <div class="px-4 py-8 text-center text-muted text-xs">Tidak ada Plan Delivery lain yang siap diproses.</div>
                    @endforelse
                </div>
                <div class="px-4 py-3 border-t border-[#eef1f5] flex justify-end">
                    <button type="button" wire:click="closeDeliveryModal" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                </div>
            </div>
        </div>
    @endif

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
