<?php

namespace App\Services\Production;

use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use App\Models\Prd\PrdMrpRequirement;
use App\Models\Prd\PrdMrpRun;
use App\Models\Prd\PrdWo;
use App\Models\Pur\PrLine;
use App\Models\Pur\PrRequisition;
use Illuminate\Support\Facades\DB;

/**
 * Engine MRP — production_scheduling_tier1.md §F10 "MRP Netting (lapisan
 * tipis)": dari WO -> hitung gross requirement RM (sudah snapshot di
 * prd_wo_material.qty_required, tak perlu explode BOM lagi) -> net terhadap
 * stok on-hand Inventory (tm2inv.v_rm_onhand) -> kalau kurang, generate SATU
 * PR draft ke Procurement. Lot sizing: lot-for-lot saja (v1, sesuai spec).
 *
 * PR yang dihasilkan berstatus DRAFT (bukan auto-approve seperti
 * SubcontractPoService yang untuk kasus darurat breakdown) -- PPIC/Procurement
 * tetap review & Submit lewat layar PR Entry biasa (pur/pr/entry.blade.php).
 *
 * Titik masuk TUNGGAL, sama pola PdrEngine/CostingEngine -- dipanggil dari
 * WoCreationService setiap kali WO dibuat.
 */
class MrpEngine
{
    public function runForWo(PrdWo $wo, string $triggeredBy): PrdMrpRun
    {
        $materials = $wo->materials;

        $onhandByMaterial = DB::connection('tm2inv')->table('v_rm_onhand')
            ->where('is_usable', true)
            ->whereIn('material_id', $materials->pluck('material_id')->unique())
            ->groupBy('material_id')
            ->selectRaw('material_id, SUM(qty_onhand) as qty_onhand')
            ->pluck('qty_onhand', 'material_id');

        $shortages = [];

        $run = DB::connection('tm2prd')->transaction(function () use ($wo, $triggeredBy, $materials, $onhandByMaterial, &$shortages) {
            $run = PrdMrpRun::create([
                'wo_id' => $wo->wo_id,
                'run_at' => now(),
                'triggered_by' => $triggeredBy,
                'status' => 'NO_SHORTAGE',
            ]);

            foreach ($materials as $material) {
                $qtyRequired = (float) $material->qty_required;
                $qtyOnhand = (float) ($onhandByMaterial[$material->material_id] ?? 0);
                $qtyShortage = max(0.0, $qtyRequired - $qtyOnhand);

                PrdMrpRequirement::create([
                    'mrp_run_id' => $run->mrp_run_id,
                    'material_id' => $material->material_id,
                    'qty_required' => $qtyRequired,
                    'qty_onhand_snapshot' => $qtyOnhand,
                    'qty_shortage' => $qtyShortage,
                ]);

                if ($qtyShortage > 0) {
                    $shortages[] = ['material_id' => $material->material_id, 'qty_shortage' => $qtyShortage];
                }
            }

            return $run;
        });

        if (empty($shortages)) {
            return $run;
        }

        $pr = $this->generatePrDraft($wo, $run, $shortages, $triggeredBy);

        $run->update(['pr_id' => $pr->pr_id, 'status' => 'PR_GENERATED']);

        return $run;
    }

    /**
     * @param  array<int, array{material_id: int, qty_shortage: float}>  $shortages
     */
    private function generatePrDraft(PrdWo $wo, PrdMrpRun $run, array $shortages, string $triggeredBy): PrRequisition
    {
        return DB::connection('tm2pur')->transaction(function () use ($wo, $run, $shortages, $triggeredBy) {
            $ppicDeptId = DB::connection('tm2mst')->table('org_department')->where('department_code', 'PPIC')->value('department_id');

            $pr = PrRequisition::create([
                'doc_no' => null, // digenerate saat Submit lewat layar PR Entry, pola sama alur manual
                'i_doc_date' => now()->toDateString(),
                'i_source' => 'MRP',
                'mrp_run_id' => $run->mrp_run_id,
                'i_department_id' => $ppicDeptId,
                'i_need_date' => $wo->material_needed_by?->toDateString(),
                'i_notes' => "Auto-generated Engine MRP -- kekurangan bahan WO {$wo->wo_no}.",
                'status' => 'DRAFT',
                'created_by' => $triggeredBy,
                'updated_by' => $triggeredBy,
            ]);

            $lineNo = 1;
            foreach ($shortages as $row) {
                $material = MatRaw::find($row['material_id']);
                if (! $material) {
                    continue;
                }

                $prefix1 = MatCategory::level(1)->where('category_name', $material->category1)->first();

                $line = PrLine::create([
                    'pr_id' => $pr->pr_id,
                    'line_no' => $lineNo++,
                    'i_item_source' => 'MASTER_ITEM',
                    'item_id' => $material->material_id,
                    'c_item_code' => $material->material_code,
                    'i_description' => $material->material_name,
                    'i_qty' => $row['qty_shortage'],
                    'i_uom' => $material->uom_purchase ?: 'PCS',
                    'i_est_price' => $material->last_unitprice,
                    'i_department_id' => $pr->i_department_id,
                    'i_coa_account_id' => $prefix1?->default_coa_account_id,
                    'is_stock' => $prefix1?->stock_class !== 'SERVICE',
                    'i_need_date' => $pr->i_need_date,
                    'created_by' => $triggeredBy,
                    'updated_by' => $triggeredBy,
                ]);
                $line->recalcSubtotal();
                $line->save();
            }

            $pr->recalcEstTotal();
            $pr->save();

            return $pr;
        });
    }
}
