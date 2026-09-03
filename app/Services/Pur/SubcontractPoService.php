<?php

namespace App\Services\Pur;

use App\Models\Prd\PrdBreakdownEvent;
use App\Models\Prd\PrdWoOperation;
use App\Models\Pur\PoLine;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PrLine;
use App\Models\Pur\PrRequisition;
use Illuminate\Support\Facades\DB;

/**
 * Breakdown/Reroute -> Subkontrak -> PO resmi (production_scheduling_tier1.md
 * §9.3, tier2.md §7 langkah 2b). Entry SIMPLIFIED atas sepengetahuan & sesuai
 * instruksi user: skip FlowEngineService berjenjang -- approval cukup gate
 * role (Auth::user()->isOpsManagerOrAbove(), dicek di caller SEBELUM method
 * ini dipanggil) langsung di Livewire, PR/PO dibuat langsung berstatus
 * "siap jalan", jejak audit lewat i_notes (bukan flow_log -- itu punya
 * FlowEngineService, jangan dipakai setengah-setengah).
 *
 * REFACTOR 2026-09-03 (docs/BACKLOG.md §6 "Reroute-Subkontrak di dialog Sisip
 * Hot Job cuma tampil opsi, belum bisa di-apply"): logic pembuatan PR+PO
 * diekstrak ke createPo() generik (TIDAK terikat PrdBreakdownEvent) supaya
 * bisa dipakai 2 skenario -- Breakdown (mesin rusak, createFromBreakdown())
 * DAN Reroute Hot Job (tak ada breakdown fisik, cuma perlu geser task ke
 * subkontraktor krn slot mesin direbut hot job, createFromReroute() baru).
 * Keduanya transisi operation ke SUBCONTRACT; bedanya cuma createFromBreakdown
 * juga update PrdBreakdownEvent (link balik ke insiden kerusakan).
 */
class SubcontractPoService
{
    public function createFromBreakdown(
        PrdBreakdownEvent $event,
        int $supplierId,
        float $estimatedCost,
        \DateTimeInterface $dueOut,
        \DateTimeInterface $dueReturn,
        string $approverLogin,
        string $approverName,
    ): PoOrder {
        return DB::connection('tm2pur')->transaction(function () use ($event, $supplierId, $estimatedCost, $dueOut, $dueReturn, $approverLogin, $approverName) {
            $op = $event->operation()->with('wo')->firstOrFail();
            $machineName = \App\Models\Master\ProdMachine::find($event->machine_id)?->machine_name ?? "mesin #{$event->machine_id}";
            $description = "Subkontrak {$op->process_name} — {$op->wo->wo_no}, breakdown {$machineName}";
            $context = "breakdown darurat";

            $po = $this->createPo($op, $supplierId, $estimatedCost, $dueOut, $description, $context, $approverLogin, $approverName);

            $event->update([
                'reroute_decision' => 'SUBCONTRACT',
                'subcontract_vendor_id' => $supplierId,
                'subcontract_po_id' => $po->po_id,
                'subcontract_due_out' => $dueOut,
                'subcontract_due_return' => $dueReturn,
            ]);

            $op->transitionTo('SUBCONTRACT');

            return $po;
        });
    }

    /**
     * Reroute Hot Job (InterruptionOptionGenerator opsi ke-3, "tidak ada job
     * lain tergeser") -- HOT TASK itu sendiri dialihkan ke subkontraktor,
     * BUKAN menggeser task existing di antrean mesin (queue lain sama sekali
     * tak disentuh). Tak ada PrdBreakdownEvent yang perlu di-link.
     */
    public function createFromReroute(
        PrdWoOperation $hotOperation,
        int $supplierId,
        float $estimatedCost,
        \DateTimeInterface $dueOut,
        \DateTimeInterface $dueReturn,
        string $approverLogin,
        string $approverName,
    ): PoOrder {
        return DB::connection('tm2pur')->transaction(function () use ($hotOperation, $supplierId, $estimatedCost, $dueOut, $approverLogin, $approverName) {
            $op = $hotOperation->loadMissing('wo');
            $description = "Subkontrak {$op->process_name} — {$op->wo->wo_no}, reroute hot job (tak menggeser antrean)";
            $context = "reroute hot job";

            $po = $this->createPo($op, $supplierId, $estimatedCost, $dueOut, $description, $context, $approverLogin, $approverName);

            $op->transitionTo('SUBCONTRACT');

            return $po;
        });
    }

    private function createPo(
        PrdWoOperation $op,
        int $supplierId,
        float $estimatedCost,
        \DateTimeInterface $dueOut,
        string $description,
        string $context,
        string $approverLogin,
        string $approverName,
    ): PoOrder {
        $ppicDeptId = DB::connection('tm2mst')->table('org_department')->where('department_code', 'PPIC')->value('department_id');
        $subkonCoaId = DB::connection('tm2acc')->table('fin_account')->where('account_code', '513000')->value('account_id');

        $pr = PrRequisition::create([
            'doc_no' => PrRequisition::nextDocNo(),
            'i_doc_date' => now()->toDateString(),
            'i_source' => 'SUBCONTRACT',
            'i_department_id' => $ppicDeptId,
            'i_need_date' => $dueOut,
            'i_notes' => "Auto-approved: subkontrak {$context}, approver: {$approverName}",
            'c_est_total' => $estimatedCost,
            'status' => 'APPROVED',
            'created_by' => $approverLogin,
            'updated_by' => $approverLogin,
        ]);

        PrLine::create([
            'pr_id' => $pr->pr_id,
            'line_no' => 1,
            'i_item_source' => 'FREE_TEXT',
            'i_description' => $description,
            'i_qty' => 1,
            'i_uom' => 'JASA',
            'i_est_price' => $estimatedCost,
            'c_est_subtotal' => $estimatedCost,
            'i_department_id' => $ppicDeptId,
            'i_coa_account_id' => $subkonCoaId,
            'is_stock' => false,
            'i_need_date' => $dueOut,
            'created_by' => $approverLogin,
            'updated_by' => $approverLogin,
        ]);

        $po = PoOrder::create([
            'doc_no' => PoOrder::nextDocNo(),
            'i_doc_date' => now()->toDateString(),
            'i_supplier_id' => $supplierId,
            'i_po_type' => 'SUBCONTRACT',
            'i_procure_type' => 'ONE_TIME',
            'i_fx_rate' => 1,
            'i_delivery_date' => $dueOut,
            'i_notes' => "Auto-approved: subkontrak {$context} WO {$op->wo->wo_no}, approver: {$approverName}",
            'status' => 'SENT',
            'sent_at' => now(),
            'created_by' => $approverLogin,
            'updated_by' => $approverLogin,
        ]);

        $poLine = PoLine::create([
            'po_id' => $po->po_id,
            'line_no' => 1,
            'i_description' => $description,
            'i_line_class' => 'SERVICE',
            'i_qty' => 1,
            'i_uom' => 'JASA',
            'i_price' => $estimatedCost,
            'i_disc_pct' => 0,
            'is_stock' => false,
            'created_by' => $approverLogin,
            'updated_by' => $approverLogin,
        ]);
        $poLine->recalcNet();
        $poLine->save();

        $po->recalcTax();
        $po->save();

        return $po;
    }
}
