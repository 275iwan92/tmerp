<?php

/*
 * REMARK
 * Tujuan       : RESET sheet Form WO ke OUTPUT ENGINE: baris engine yang dihapus officer muncul lagi, nilai yang diubah kembali ke angka engine, baris tambahan officer dibuang.
 * Sumber hulu  : WoGenerationService (rebuildOperations / regenerateBom, setelah penanda dibuang), prd_plan_decision (ROW_ADDED, ROW_DELETED, TARGET, RESEQUENCE), prd_wo_operation, prd_wo_material.
 * Input        : PrdWo DRAFT, jenis sheet 'OP' (Bill of Process) atau 'MAT' (Bill of Material), login.
 * Output       : preview() = jumlah yang akan terjadi; run() = lakukan + satu keputusan SHEET_RESET (siapa, jumlah). Operasi yang sudah mulai / CONFIRMED / ber-output tidak disentuh.
 * Dipakai oleh : PpicController::resetOps / resetMaterials (tombol RESET di sheet BOP dan BOM).
 * Status       : Aktif di mockup (ddt*). BOM: bila engine tidak menghasilkan baris, RESET ditolak dan tidak ada yang dihapus (ATTENTION-ENGINE-01).
 */

declare(strict_types=1);

namespace App\Services\Scheduling\Plan;

use App\Models\Prd\PrdPlanDecision;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoMaterial;
use App\Models\Prd\PrdWoOperation;
use App\Services\Scheduling\WoGenerationService;
use App\Support\V2\Data\Ppic;
use Illuminate\Support\Facades\DB;

final class SheetReset
{
    public function __construct(private readonly WoGenerationService $gen) {}

    /** An operation the officer must keep as it is: confirmed, started, or with output. */
    private function untouchable(PrdWoOperation $o): bool
    {
        return $o->plan_status === 'CONFIRMED' || ! in_array($o->task_status, ['PENDING', 'ASSIGNED'], true)
            || DB::connection('tm2prd')->table('prd_wo_operation_output')->where('operation_id', $o->operation_id)->exists();
    }

    /** A decision that no longer counts keeps its row (audit) under the name EVENT_RESET; the readers only look for the live names. */
    private function retire(array $events, \Closure $scope): void
    {
        foreach ($events as $e) {
            $scope(DB::connection('tm2prd')->table('prd_plan_decision')->where('event', $e))->update(['event' => $e.'_RESET']);
        }
    }

    /**
     * What RESET would do, in numbers (shown in the confirmation).
     *
     * @return array{restored: int, values: int, dropped: int, untouched: int, engine_rows: int}
     */
    public function preview(PrdWo $wo, string $kind): array
    {
        $id = (int) $wo->wo_id;
        $restored = count(Ppic::deletedRows($id, $kind));
        if ($kind === 'MAT') {
            $added = Ppic::addedRows($id, 'MAT');
            $lines = PrdWoMaterial::where('wo_id', $id)->get();

            return ['restored' => $restored, 'dropped' => $lines->whereIn('detail_id', $added)->count(), 'untouched' => 0, 'engine_rows' => $this->gen->engineBomCount($wo),
                'values' => $lines->whereNotIn('detail_id', $added)->whereNotNull('override_reason')->count()];
        }
        $ops = PrdWoOperation::where('wo_id', $id)->get();
        $added = Ppic::addedRows($id, 'OP');
        $free = $ops->reject(fn ($o) => $this->untouchable($o));
        $targeted = DB::connection('tm2prd')->table('prd_plan_decision')->where('event', 'TARGET')->whereIn('operation_id', $free->pluck('operation_id'))->distinct()->count('operation_id');

        return ['restored' => $restored, 'dropped' => $free->whereIn('operation_id', $added)->count(), 'untouched' => $ops->count() - $free->count(), 'engine_rows' => 0,
            'values' => $targeted + $free->filter(fn ($o) => $o->crew_work_center_id || $o->subcon_supplier_id)->count()];
    }

    /**
     * RESET one sheet. The engines (planning facts, gates, schedule) are asked again by the caller, as after PROCESS.
     *
     * @throws \DomainException when the engine gives nothing to go back to (BOM) or the Work Order is applied
     */
    public function run(PrdWo $wo, string $kind, string $login): array
    {
        if (! in_array($wo->wo_status, ['DRAFT', 'SIMULATED'], true)) {
            throw new \DomainException('Applied WO is locked; Undo Confirm first.');
        }
        $before = $this->preview($wo, $kind);
        $id = (int) $wo->wo_id;
        $prd = DB::connection('tm2prd');

        if ($kind === 'MAT') {
            if ($before['engine_rows'] === 0) {
                throw new \DomainException('The engine gives no rows; the BOM is not changed.');
            }
            $prd->transaction(function () use ($wo, $id) {
                $this->retire(['ROW_ADDED', 'ROW_DELETED'], fn ($q) => $q->where('old_value', 'like', '{"wo":'.$id.',"kind":"MAT"%'));
                // edited Required / Release go back to the engine's numbers; the regeneration then writes every line again
                PrdWoMaterial::where('wo_id', $id)->update(['override_reason' => null, 'qty_release' => null, 'qty_purchase' => null]);
                $this->gen->regenerateBom($wo);
            });
        } else {
            $prd->transaction(function () use ($wo, $id, $prd) {
                $ops = PrdWoOperation::where('wo_id', $id)->get();
                $free = $ops->reject(fn ($o) => $this->untouchable($o));
                $freeIds = $free->pluck('operation_id');
                $dropped = $free->whereIn('operation_id', Ppic::addedRows($id, 'OP'));
                $this->retire(['ROW_DELETED'], fn ($q) => $q->where('old_value', 'like', '{"wo":'.$id.',"kind":"OP"%'));
                $this->retire(['ROW_ADDED'], fn ($q) => $q->whereIn('operation_id', $dropped->pluck('operation_id')));
                $this->retire(['RESEQUENCE', 'RESEQUENCE_PENDING'], fn ($q) => $q->whereIn('operation_id', $ops->pluck('operation_id')));
                $this->retire(['TARGET'], fn ($q) => $q->whereIn('operation_id', $freeIds));
                // target back to the engine's (the planning facts fill it again), crew / subcontract back to none
                PrdWoOperation::whereIn('operation_id', $freeIds)->update(['rate_band_value' => null, 'crew_work_center_id' => null, 'subcon_supplier_id' => null]);
                foreach ($dropped as $o) {
                    $prd->table('prd_wo_operation_machine')->where('operation_id', $o->operation_id)->delete();
                    $prd->table('prd_crew_assignment')->where('operation_id', $o->operation_id)->delete();
                    $o->delete();
                }
                $header = (array) ($wo->pending_header ?? []);
                unset($header['order']);
                $wo->forceFill(['pending_header' => $header ?: null])->save();
                $this->gen->rebuildOperations($wo->fresh());
            });
        }
        PrdPlanDecision::create(['actor' => $login, 'event' => 'SHEET_RESET', 'old_value' => json_encode(['wo' => $id, 'kind' => $kind]),
            'new_value' => mb_substr(sprintf('restored %d, values %d, dropped %d', $before['restored'], $before['values'], $before['dropped']), 0, 190), 'reason' => 'RESET to the engine output']);

        return $before;
    }
}
