<?php

/*
 * REMARK
 * Tujuan       : Model operasi Work Order (prd_wo_operation): satu langkah BOP di satu mesin dengan jadwal Draft/Live.
 * Sumber hulu  : Dibuat oleh WoGenerationService dari BOP ProDev; jadwal ditulis AutoPlanRunner / PlanConfirmService / PlanChangeService.
 * Input        : Kolom operasi, termasuk draft_*, target_*, is_locked, planned_by, pending_move/pending_from/pending_reason.
 * Output       : Baris operasi untuk Ppic::operations dan layanan penjadwalan.
 * Dipakai oleh : Seluruh layanan Scheduling/Plan, PpicController, Ppic.
 * Status       : Aktif; kolom pending_* baru hanya di ddt* sampai rilis.
 */

namespace App\Models\Prd;

use App\Models\Master\ProdMachine;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Task / Job Operation -- unit terkecil yang dijadwalkan di Scheduling Board
// (production_scheduling_tier1.md §4, §5A). machine() adalah relasi LOGICAL
// lintas-DB (tm2prd -> tm2mst), bukan FK fisik -- CLAUDE.md §2.
class PrdWoOperation extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'prd_wo_operation';

    protected $primaryKey = 'operation_id';

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'wo_id', 'seq_no', 'process_name', 'resource_type', 'machine_id', 'qty',
        'spoilage_qty', 'spoilage_uom', 'waste_qty', 'waste_uom',
        'tolerance_qty',
        'tolerance_uom',
        'target_start', 'target_finish', 'actual_start', 'actual_finish',
        'task_status', 'qc_gate_required', 'rejection_reason', 'is_hot_job',
        'batched_with_operation_id', 'rate_band_value', 'critical_ratio',
        'created_by', 'updated_by',
        'process_id', 'qty_uom', 'qty_out', 'setup_base_min', 'setup_change_min', 'setup_tool_min', 'run_minutes', 'plan_status',
        'draft_machine_id', 'draft_start', 'draft_finish', 'draft_run_id', 'is_locked', 'lock_reason', 'planned_by', 'priority_note',
        'pending_move', 'pending_from', 'pending_reason', 'crew_work_center_id', 'subcon_supplier_id',
        'gate_material', 'gate_tooling', 'gate_artwork', 'gate_note', 'material_ready_at', 'actual_setup_min',
    ];

    protected $casts = [
        'target_start' => 'datetime',
        'target_finish' => 'datetime',
        'actual_start' => 'datetime',
        'actual_finish' => 'datetime',
        'draft_start' => 'datetime',
        'draft_finish' => 'datetime',
        'material_ready_at' => 'datetime',
        'is_locked' => 'boolean',
        'qty_out' => 'decimal:2',
        'qc_gate_required' => 'boolean',
        'is_hot_job' => 'boolean',
        'qty' => 'decimal:4',
        'spoilage_qty' => 'decimal:4',
        'waste_qty' => 'decimal:4',
        'critical_ratio' => 'decimal:4',
    ];

    // Tier2 §2 — state machine Task. Guard wajib di service layer, bukan cuma UI.
    // PENDING/ASSIGNED -> SUBCONTRACT DITAMBAH 2026-09-03 (docs/BACKLOG.md §6
    // "Reroute-Subkontrak di dialog Sisip Hot Job cuma tampil opsi, belum bisa
    // di-apply") -- keputusan otonom, dicatat jelas krn ini perluasan state
    // machine (arsitektural): sebelumnya SUBCONTRACT cuma reachable dari
    // BREAKDOWN (mesin rusak fisik). Reroute Hot Job BEDA skenario -- task
    // belum jalan sama sekali (PENDING/ASSIGNED), cuma digeser krn slotnya
    // direbut hot job lain, PPIC putuskan alihkan ke subkontraktor drpd
    // tunggu slot kosong lagi. Tidak ada breakdown fisik, jadi butuh jalur
    // masuk SUBCONTRACT yang tak lewat BREAKDOWN. Rendah risiko/reversibel
    // (nambah transisi baru, tidak mengubah data/alur existing manapun).
    public const TRANSITIONS = [
        'PENDING' => ['ASSIGNED', 'SUBCONTRACT'],
        'ASSIGNED' => ['RUNNING', 'PENDING', 'SUBCONTRACT'],       // batal-assign kembali ke PENDING
        'RUNNING' => ['DONE', 'BREAKDOWN'],
        'BREAKDOWN' => ['PENDING', 'SUBCONTRACT'],   // reroute mesin lain / subkontrak
        'SUBCONTRACT' => ['QC_HOLD'],
        'DONE' => ['QC_HOLD', 'QC_PASSED'],     // lompat ke QC_PASSED bila qc_gate_required=false
        'QC_HOLD' => ['QC_PASSED', 'QC_REJECTED'],
        'QC_REJECTED' => ['PENDING', 'BREAKDOWN'],     // rework, atau eskalasi
        'QC_PASSED' => [],                            // final per task
    ];

    public function canTransitionTo(string $target): bool
    {
        return in_array($target, self::TRANSITIONS[$this->task_status] ?? [], true);
    }

    public function transitionTo(string $target): void
    {
        if (! $this->canTransitionTo($target)) {
            throw new \DomainException("Transisi status Task tidak sah: {$this->task_status} -> {$target}");
        }
        $this->task_status = $target;
        $this->save();
    }

    public function wo(): BelongsTo
    {
        return $this->belongsTo(PrdWo::class, 'wo_id', 'wo_id');
    }

    public function crew(): HasMany
    {
        return $this->hasMany(PrdCrewAssignment::class, 'operation_id', 'operation_id');
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(PrdWoOperationOutput::class, 'operation_id', 'operation_id')->orderByDesc('recorded_at');
    }

    // F15 Production Output Recording -- total hasil aktual = SUM seluruh log
    // (boleh dicatat berkali-kali per shift/giliran, bukan sekali di akhir).
    public function totalQtyGood(): float
    {
        return (float) $this->outputs->sum('qty_good');
    }

    public function totalQtyReject(): float
    {
        return (float) $this->outputs
            ->flatMap(fn ($o) => $o->defects)
            ->filter(fn ($d) => $d->scrapType?->category === 'REJECT')
            ->sum('qty');
    }

    public function totalQtyWaste(): float
    {
        return (float) $this->outputs
            ->flatMap(fn ($o) => $o->defects)
            ->filter(fn ($d) => $d->scrapType?->category === 'WASTE')
            ->sum('qty');
    }

    // Rincian per tipe (mis. "Cacat Cetak: 3, Warna Meleset: 2") -- dipakai WO
    // Detail supaya PPIC lihat KENAPA reject/waste-nya, bukan cuma angka total.
    // @return array<string, float> nama tipe -> total qty
    public function defectBreakdown(string $category): array
    {
        return $this->outputs
            ->flatMap(fn ($o) => $o->defects)
            ->filter(fn ($d) => $d->scrapType?->category === $category)
            ->groupBy(fn ($d) => $d->scrapType->type_name)
            ->map(fn ($rows) => (float) $rows->sum('qty'))
            ->all();
    }

    // Progress produksi SUNGGUHAN (qty baik / target) -- beda dari progressPercent()
    // di bawah yang cuma proporsi waktu berlalu. Dipakai Scheduling Board supaya
    // PPIC lihat kemajuan riil, bukan cuma "sudah berapa lama berjalan".
    public function outputProgressPercent(): int
    {
        if ((float) $this->qty <= 0) {
            return 0;
        }

        return (int) max(0, min(100, round($this->totalQtyGood() / (float) $this->qty * 100)));
    }

    // Lintas-DB (tm2prd -> tm2mst) -- query terpisah, bukan join fisik.
    public function machine(): BelongsTo
    {
        return $this->belongsTo(ProdMachine::class, 'machine_id', 'machine_id');
    }

    public function operatorUtama(): ?PrdCrewAssignment
    {
        return $this->crew->firstWhere('crew_role', 'OPERATOR_UTAMA');
    }

    // Progress kasar dari rentang waktu target (belum ada kolom qty actual per
    // Tier 3) -- DONE/QC_PASSED = 100%, RUNNING = proporsi waktu berlalu, sisanya 0.
    public function progressPercent(Carbon $now): int
    {
        if (in_array($this->task_status, ['DONE', 'QC_PASSED'], true)) {
            return 100;
        }

        if ($this->task_status !== 'RUNNING' || ! $this->target_start || ! $this->target_finish) {
            return 0;
        }

        $total = $this->target_start->diffInSeconds($this->target_finish);
        if ($total <= 0) {
            return 0;
        }

        $elapsed = max(0, $this->target_start->diffInSeconds($now, false));

        return (int) max(0, min(100, round($elapsed / $total * 100)));
    }

    /**
     * Who does this operation: 'C:<emp_work_center id>' (a crew), 'S:<sup_supplier id>' (a subcontract vendor) or '' (nobody chosen).
     * Exactly one of crew_work_center_id / subcon_supplier_id is set at a time.
     */
    public function assignActor(string $value): void
    {
        $id = (int) substr($value, 2);
        $this->forceFill([
            'crew_work_center_id' => str_starts_with($value, 'C:') && $id ? $id : null,
            'subcon_supplier_id' => str_starts_with($value, 'S:') && $id ? $id : null,
        ])->save();
    }

    /** The value assignActor() takes back: 'C:12', 'S:5' or ''. */
    public function actorValue(): string
    {
        return $this->crew_work_center_id ? 'C:'.$this->crew_work_center_id : ($this->subcon_supplier_id ? 'S:'.$this->subcon_supplier_id : '');
    }
}
