<?php

/*
 * REMARK
 * Tujuan       : Membuat WO dari baris kirim CPDEL (1 part = 1 WO) beserta operasi dan baris bahan; regenerateBom untuk WO DRAFT.
 * Sumber hulu  : co_order_delivery, iqp_inquiry/iqp_part, SpecEngine::forWorkOrder.
 * Input        : CoOrderDelivery (generateFromDelivery) atau PrdWo DRAFT (regenerateBom).
 * Output       : prd_wo, prd_wo_operation (proses+mesin), prd_wo_material (process_id, qty_required, qty_standar); rebuildOperations = ENGINE BOP untuk PROCESS (operasi mengikuti BOP sekarang, penempatan/progres dipertahankan).
 * Dipakai oleh : PpicController::createWo, Cpdel::post.
 * Status       : Aktif di mockup. Override Required/Release milik officer dan process_id baris (bila engine tidak memberi) dipertahankan saat regenerateBom; engineBomCount = jumlah baris BOM engine sekarang (RESET BOM menolak bila 0).
 */

namespace App\Services\Scheduling;

use App\Models\Master\ProdPart;
use App\Models\Master\ProdPartProcessMachineRate;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoMaterial;
use App\Models\Prd\PrdWoOperation;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpPart;
use App\Services\ProDev\SpecEngine;
use App\Support\V2\Data\Ppic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * F1 Job Order Generation -- production_scheduling_tier1.md §5, DIPICU dari
 * Plan Delivery (bukan CO langsung -- lihat
 * docs/production_scheduling_wo_generation_delta.md §1). BELUM PERNAH
 * diimplementasikan sebelum sesi ini -- seluruh prd_wo yang ada sebelumnya
 * adalah dummy data manual.
 *
 * 1 iqp_part = 1 prd_wo (keputusan user, delta doc §2) -- konvergensi
 * antar-part SENGAJA belum dimodelkan.
 *
 * Syarat (user 2026-10-02, pedoman A.16): SEMUA jenis CO boleh di-Post to Production asal CO-nya sudah Confirmed
 * dan approved (doc_status CONFIRMED). Costing published TIDAK lagi disyaratkan. Product tanpa Inquiry / part
 * (CO direct percepatan) mendapat WO sederhana: satu WO per baris kirim, tanpa routing & bahan -- PPIC melengkapi.
 */
class WoGenerationService
{
    public function __construct(
        private readonly BackwardSchedulingService $backwardScheduler,
        private readonly SpecEngine $spec,
    ) {}

    /**
     * @return Collection<int, PrdWo>
     *
     * @throws \RuntimeException kalau delivery ini sudah pernah generate WO, CO belum Confirmed / approved,
     *                           atau baris CO tidak punya Master Product.
     */
    public function generateFromDelivery(CoOrderDelivery $delivery): Collection
    {
        if (PrdWo::where('source_delivery_id', $delivery->id_delivery)->where('wo_status', '!=', 'CANCELLED')->exists()) {
            throw new \RuntimeException(tf('Delivery #:id has already generated a WO -- it must not be done twice.', ['id' => $delivery->id_delivery]));
        }

        $delivery->loadMissing(['item.inquiry.parts.processes', 'item.inquiry.parts.materials']);
        $inquiry = $delivery->item?->inquiry;
        // CO INSTANT (direct, tanpa Quotation -- aturan A.2) menunjuk Master Product, bukan inquiry: pakai inquiry terakhir
        // yang melahirkan / menghargai product itu sebagai sumber BOM/BOP.
        if (! $inquiry && $delivery->item?->product_id) {
            $inquiry = IqpInquiry::with(['parts.processes', 'parts.materials'])->where('product_id', $delivery->item->product_id)
                ->where('stat_cancel', false)->orderByDesc('id_inquiry')->first();
        }

        // Satu-satunya syarat (user 2026-10-02): CO sudah Confirmed dan approved.
        if ($delivery->order?->doc_status !== 'CONFIRMED') {
            throw new \RuntimeException(tf('The CO of delivery #:id is not Confirmed / approved yet -- the WO cannot be created yet.', ['id' => $delivery->id_delivery]));
        }

        // Tanpa Inquiry / part (CO direct untuk product yang belum pernah lewat Inquiry): WO sederhana.
        if (! $inquiry || $inquiry->parts->isEmpty()) {
            return collect([$this->simpleWo($delivery, $inquiry)]);
        }

        return DB::connection('tm2prd')->transaction(function () use ($delivery, $inquiry) {
            $created = collect();

            foreach ($inquiry->parts as $part) {
                $qty = (float) $delivery->ship_qty * (float) ($part->qty_per_sku ?: 1);

                $wo = PrdWo::create([
                    'wo_no' => PrdWo::nextWoNo(),
                    'wo_type' => 'MTO',
                    'source_type' => 'CO_CONFIRMED',
                    'source_co_id' => $delivery->order_id,
                    'source_delivery_id' => $delivery->id_delivery,
                    'source_iqp_part_id' => $part->id_part,
                    'product_id' => $inquiry->product_id,
                    'part_code' => $part->part_code ?: $part->part_name,
                    'qty' => $qty,
                    'due_date' => $delivery->ship_date,
                    'wo_status' => 'DRAFT',
                    'schedule_layer' => 'SIMULATED',
                    'created_by' => 'system',
                ]);

                // BOP & BOM dari ProDev (hasil engine atau override officer) --
                // dihitung untuk qty WO ini, lengkap dgn waste/toleransi/target.
                $prodPart = $part->prod_part_id ? ProdPart::find($part->prod_part_id) : null;
                $spec = $prodPart ? $this->spec->forWorkOrder($prodPart, $part, $qty) : ['bop' => [], 'bom' => []];

                if ($spec['bop']) {
                    foreach ($spec['bop'] as $i => $step) {
                        PrdWoOperation::create([
                            'wo_id' => $wo->wo_id,
                            'seq_no' => ($i + 1) * 10,
                            'process_name' => $step['label'],
                            'process_id' => $step['process_id'] ?? null,
                            'resource_type' => $step['machine_id'] ? 'MACHINE' : 'MANUAL',
                            'machine_id' => $step['machine_id'],
                            'qty' => $step['qty_in'] ?? $qty,
                            'waste_qty' => $step['qty_waste'] ?? null,
                            'waste_uom' => $step['qty_waste'] !== null ? $step['uom'] : null,
                            'tolerance_qty' => $step['qty_tolerance'] ?? null,
                            'tolerance_uom' => $step['qty_tolerance'] !== null ? $step['uom'] : null,
                            'task_status' => 'PENDING',
                            'rate_band_value' => $step['target_qty_per_hour'] ?? null,
                            'created_by' => 'system',
                        ]);
                    }
                } else {
                    // Belum ada BOP dari ProDev -- pakai routing snapshot Inquiry apa adanya.
                    foreach ($part->processes->sortBy('step_seq') as $routingStep) {
                        $targetRate = $this->lookupDefaultMachineRate(
                            $inquiry->product_id, $wo->part_code, $routingStep->process_id, $routingStep->machine_id,
                        );

                        PrdWoOperation::create([
                            'wo_id' => $wo->wo_id,
                            'seq_no' => $routingStep->step_seq,
                            'process_name' => $routingStep->process_name,
                            'process_id' => $routingStep->process_id,
                            'resource_type' => $routingStep->machine_id ? 'MACHINE' : 'MANUAL',
                            'machine_id' => $routingStep->machine_id,
                            'qty' => $qty,
                            'task_status' => 'PENDING',
                            'rate_band_value' => $targetRate,
                            'created_by' => 'system',
                        ]);
                    }
                }

                $this->writeBom($wo, $spec['bom'] ?? [], $delivery->ship_date);

                // Backward-schedule & PERSIST saat generate (2026-08-28: schedule()
                // sebelumnya dipanggil di objek fresh() lalu hasilnya dibuang --
                // Est. Mulai/Selesai & material_needed_by tak pernah benar2 terisi
                // walau komentar lama bilang begitu, lihat CLAUDE.md/project memory
                // "engine MPS auto". WO tetap DRAFT (persist di sini cuma isi jadwal
                // awal, bukan approval) -- PPIC boleh override manual di WO
                // Detail/Gantt/Kanban kapan saja sesudahnya.
                if ($wo->operations()->exists()) {
                    try {
                        $this->backwardScheduler->scheduleAndPersist($wo);
                    } catch (\RuntimeException) {
                        // Rate band blm lengkap (task manual, dst) -- PPIC lengkapi
                        // manual di Screen #2, jangan gagalkan generate karena ini.
                    }
                }

                $created->push($wo);
            }

            return $created;
        });
    }

    /**
     * ENGINE BOP for an existing Draft Work Order (PROCESS): the operations follow the BOP that ProDev gives now. An operation of a process
     * that is still in the BOP stays (its id, officer placement, target and progress are kept; it only takes the new step order unless the
     * officer re-ordered the Work Order); a process that is new in the BOP gets a new operation; an operation whose process left the BOP is
     * removed ONLY when it is not confirmed, not started and has no output (otherwise it is kept and counted). Nothing is rebuilt when the
     * engine returns no BOP.
     *
     * @return array{created: int, renumbered: int, removed: int, kept: int}
     */
    public function rebuildOperations(PrdWo $wo): array
    {
        $none = ['created' => 0, 'renumbered' => 0, 'removed' => 0, 'kept' => 0];
        $part = $wo->source_iqp_part_id ? IqpPart::find($wo->source_iqp_part_id) : null;
        $prodPart = $part?->prod_part_id ? ProdPart::find($part->prod_part_id) : null;
        if (! $prodPart || ! in_array($wo->wo_status, ['DRAFT', 'SIMULATED'], true)) {
            return $none;
        }
        $steps = $this->spec->forWorkOrder($prodPart, $part, (float) $wo->qty)['bop'] ?? [];
        if ($steps === []) {
            return $none;
        }
        $officerOrder = Ppic::bopOrderChanged((int) $wo->wo_id);

        return DB::connection('tm2prd')->transaction(function () use ($wo, $steps, $officerOrder, $none) {
            $existing = $wo->operations()->orderBy('seq_no')->get();
            // Engine steps the officer deleted (with a reason) do not come back: one tombstone cancels one step of that process.
            $deleted = collect(Ppic::deletedRows((int) $wo->wo_id, 'OP'))->countBy()->all();
            $used = [];
            $r = $none;
            $next = (int) ($existing->max('seq_no') ?? 0);
            foreach ($steps as $i => $step) {
                $pid = $step['process_id'] ?? null;
                $op = $pid ? $existing->first(fn ($o) => (int) $o->process_id === (int) $pid && ! in_array($o->operation_id, $used, true)) : null;
                if ($op) {
                    $used[] = $op->operation_id;
                    $seq = ($i + 1) * 10;
                    if (! $officerOrder && $op->plan_status !== 'CONFIRMED' && (int) $op->seq_no !== $seq) {
                        $op->forceFill(['seq_no' => $seq, 'process_name' => $step['label']])->save();
                        $r['renumbered']++;
                    }

                    continue;
                }
                if ($pid && ($deleted[$pid] ?? 0) > 0) {
                    $deleted[$pid]--;

                    continue;
                }
                $next = $officerOrder ? $next + 10 : ($i + 1) * 10;
                $new = PrdWoOperation::create([
                    'wo_id' => $wo->wo_id, 'seq_no' => $next, 'process_name' => $step['label'], 'process_id' => $pid,
                    'resource_type' => ($step['machine_id'] ?? null) ? 'MACHINE' : 'MANUAL', 'machine_id' => $step['machine_id'] ?? null,
                    'qty' => $step['qty_in'] ?? $wo->qty, 'waste_qty' => $step['qty_waste'] ?? null,
                    'waste_uom' => ($step['qty_waste'] ?? null) !== null ? $step['uom'] : null,
                    'tolerance_qty' => $step['qty_tolerance'] ?? null, 'tolerance_uom' => ($step['qty_tolerance'] ?? null) !== null ? $step['uom'] : null,
                    'task_status' => 'PENDING', 'rate_band_value' => $step['target_qty_per_hour'] ?? null, 'created_by' => 'system',
                ]);
                $used[] = $new->operation_id;
                $r['created']++;
            }
            foreach ($existing as $op) {
                if (in_array($op->operation_id, $used, true)) {
                    continue;
                }
                if (in_array((int) $op->operation_id, Ppic::addedRows((int) $wo->wo_id, 'OP'), true)) {   // added by the officer: PROCESS never removes it
                    $r['kept']++;

                    continue;
                }
                $busy = $op->plan_status === 'CONFIRMED' || ! in_array($op->task_status, ['PENDING', 'ASSIGNED'], true)
                    || DB::connection('tm2prd')->table('prd_wo_operation_output')->where('operation_id', $op->operation_id)->exists();
                if ($busy) {
                    $r['kept']++;

                    continue;
                }
                DB::connection('tm2prd')->table('prd_wo_operation_machine')->where('operation_id', $op->operation_id)->delete();
                $op->delete();
                $r['removed']++;
            }

            return $r;
        });
    }

    /** Number of real BOM rows the engine gives now for this Work Order (0 = nothing to go back to; RESET refuses). */
    public function engineBomCount(PrdWo $wo): int
    {
        $part = $wo->source_iqp_part_id ? IqpPart::find($wo->source_iqp_part_id) : null;
        $prodPart = $part?->prod_part_id ? ProdPart::find($part->prod_part_id) : null;
        $bom = $prodPart ? ($this->spec->forWorkOrder($prodPart, $part, (float) $wo->qty)['bom'] ?? []) : [];

        return count(array_filter($bom, fn ($b) => ($b['name'] ?? null) !== 'None' && ($b['name'] ?? '') !== ''));
    }

    /**
     * Rebuild the material lines of an existing DRAFT Work Order from the ProDev engine (Inquiry -> BOM, need for the WO qty).
     * Operations are not touched. Returns the number of lines written.
     */
    public function regenerateBom(PrdWo $wo): int
    {
        if (! in_array($wo->wo_status, ['DRAFT', 'SIMULATED'], true)) {
            throw new \RuntimeException(t('Only a Draft Work Order can have its materials regenerated.'));
        }
        $part = $wo->source_iqp_part_id ? IqpPart::find($wo->source_iqp_part_id) : null;
        $prodPart = $part?->prod_part_id ? ProdPart::find($part->prod_part_id) : null;
        $spec = $prodPart ? $this->spec->forWorkOrder($prodPart, $part, (float) $wo->qty) : ['bom' => [], 'bop' => []];
        $needed = $wo->materials()->value('material_needed_by');
        // An officer's edited Required (with its reason) survives the regeneration when the engine produces the same line again.
        $kept = $wo->materials()->whereNotNull('override_reason')->get()->keyBy(fn ($m) => $m->material_role.'|'.$m->material_name);
        $subst = $wo->materials()->where('is_substituted', true)->get()->keyBy(fn ($m) => $m->material_role.'|'.$m->material_name);
        // The BOP process of a line is kept when the engine gives none now (ATTENTION-ENGINE-01): same role + name keeps the process it had.
        $process = $wo->materials()->whereNotNull('process_id')->get()->mapWithKeys(fn ($m) => [$m->material_role.'|'.$m->material_name => $m->process_id])->all();

        return DB::connection('tm2prd')->transaction(function () use ($wo, $spec, $needed, $kept, $subst, $process) {
            // only the engine's own lines are rebuilt; lines the officer added stay
            $wo->materials()->whereNotIn('detail_id', Ppic::addedRows((int) $wo->wo_id, 'MAT'))->delete();
            $n = $this->writeBom($wo, $spec['bom'], $needed);
            foreach ($wo->materials()->get() as $m) {
                if ($m->process_id === null && isset($process[$m->material_role.'|'.$m->material_name])) {
                    $m->forceFill(['process_id' => $process[$m->material_role.'|'.$m->material_name]])->save();
                }
                if ($k = $kept->get($m->material_role.'|'.$m->material_name)) {
                    $m->forceFill(['qty_required' => $k->qty_required, 'override_reason' => $k->override_reason])->save();
                }
                // a substitute chosen for this line survives the rebuild (the BOM engine re-checks it through the gates)
                if ($s = $subst->get($m->material_role.'|'.$m->material_name)) {
                    $m->forceFill(['is_substituted' => true, 'substitute_material_id' => $s->substitute_material_id, 'substitute_reason' => $s->substitute_reason,
                        'substitute_approved_by' => $s->substitute_approved_by, 'substitute_approved_at' => $s->substitute_approved_at])->save();
                }
            }

            return $n;
        });
    }

    /**
     * ATTENTION-ENGINE-01: lihat docs/ENGINE-ATTENTION.md (process_id / nama bahan bergantung pada patch SpecEngine yang diparkir).
     * One line per BOM row, read as the engine gave it (name, qty_need, uom; the BOP process of a line when the engine says so via
     * process_id). A row whose material is not chosen yet keeps its category / spec name. Never leaves a WO without lines.
     */
    private function writeBom(PrdWo $wo, array $bom, $neededBy): int
    {
        // engine lines the officer deleted (with a reason) are not written again: one tombstone cancels one line (role|name)
        $deleted = collect(Ppic::deletedRows((int) $wo->wo_id, 'MAT'))->countBy()->all();
        $rows = array_values(array_filter($bom, function ($b) use (&$deleted) {
            if (($b['name'] ?? null) === 'None') {
                return false;
            }
            $key = ($b['role'] ?? '').'|'.($b['name'] ?? '');
            if (($deleted[$key] ?? 0) > 0) {
                $deleted[$key]--;

                return false;
            }

            return true;
        }));
        if ($rows === []) {
            $rows = [['role' => 'MATERIAL', 'name' => 'Material belum ditentukan di Inquiry / ProDev', 'material_id' => null, 'uom' => null]];
        }
        foreach ($rows as $i => $b) {
            PrdWoMaterial::create([
                'wo_id' => $wo->wo_id,
                'seq_no' => ($i + 1) * 10,
                'material_id' => $b['material_id'],
                'material_role' => $b['role'],
                'process_id' => $b['process_id'] ?? null,
                'material_name' => $b['name'],
                // A row whose area cannot be computed yet (foil, UV varnish) keeps qty 0 so PPIC completes it.
                'qty_required' => $b['qty_need'] ?? 0,
                'qty_standar' => $b['qty_need'] ?? 0,
                'uom' => $b['uom'],
                'material_needed_by' => $neededBy,
                'created_by' => 'system',
            ]);
        }

        return count($rows);
    }

    // Satu WO untuk satu baris kirim, tanpa operasi dan bahan (tidak ada BOM / BOP sebagai sumber). Hasil produksi tetap
    // bisa dicatat (pedoman A.3); PPIC menambah proses di WO Detail bila perlu.
    private function simpleWo(CoOrderDelivery $delivery, ?IqpInquiry $inquiry): PrdWo
    {
        $productId = $delivery->item?->product_id ?: $inquiry?->product_id;
        if (! $productId) {
            throw new \RuntimeException(tf('Delivery #:id: the CO line has no Master Product yet -- choose the product on the CO line first.', ['id' => $delivery->id_delivery]));
        }

        return PrdWo::create([
            'wo_no' => PrdWo::nextWoNo(),
            'wo_type' => 'MTO',
            'source_type' => 'CO_CONFIRMED',
            'source_co_id' => $delivery->order_id,
            'source_delivery_id' => $delivery->id_delivery,
            'product_id' => $productId,
            'part_code' => mb_substr((string) $delivery->item?->description, 0, 50) ?: null,
            'qty' => (float) $delivery->ship_qty,
            'due_date' => $delivery->ship_date,
            'wo_status' => 'DRAFT',
            'schedule_layer' => 'SIMULATED',
            'created_by' => 'system',
        ]);
    }

    private function lookupDefaultMachineRate(?int $productId, ?string $partCode, ?int $processId, ?int $machineId): ?float
    {
        if (! $productId || ! $partCode || ! $processId || ! $machineId) {
            return null;
        }

        return ProdPartProcessMachineRate::active()
            ->where('product_id', $productId)
            ->where('part_code', $partCode)
            ->where('process_id', $processId)
            ->where('machine_id', $machineId)
            ->value('target_qty_per_hour');
    }
}
