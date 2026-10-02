<?php

namespace App\Services\ProDev;

use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use App\Models\Master\ProdPart;
use App\Models\Pur\PrLine;
use App\Models\Pur\PrRequisition;
use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\DoLine;
use App\Models\Sdi\IqpPart;
use Illuminate\Support\Facades\DB;

// Dari ProDev ke Procurement (user 2026-09-17):
// - baris BOM yang stoknya kurang -> Purchase Requisition bahan (source MRP);
// - langkah BOP yang tidak dikerjakan sendiri -> PR subkon (source
//   SUBCONTRACT) + Surat Jalan ke subkon (do_delivery SUBCON) yang WAJIB
//   menunjuk CO asal supaya biaya subkon terlacak ke costing order itu.
// PR dibuat DRAFT -- tetap lewat approval PR yang sudah ada, tidak dilompati.
class ProcurementBridge
{
    private function tag(ProdPart $part): string
    {
        return "[PRODEV part={$part->id_part}]";
    }

    private function department(): int
    {
        return (int) (DB::connection('tm2mst')->table('org_department')->where('department_code', 'PPIC')->value('department_id')
            ?: DB::connection('tm2mst')->table('org_department')->where('activate', 1)->orderBy('department_id')->value('department_id'));
    }

    private function context(ProdPart $part, ?IqpPart $iqp): string
    {
        $inq = $iqp?->inquiry?->inquiry_no;

        return trim(($part->product?->product_name ?: $part->part_name).($inq ? " · Inquiry {$inq}" : ''));
    }

    // Satu PR DRAFT per part dipakai ulang; baris bahan yang sama ditambah qty-nya.
    public function purchaseMaterial(ProdPart $part, ?IqpPart $iqp, int $materialId, float $qty, string $login, ?string $uom = null): array
    {
        $material = MatRaw::find($materialId);

        if (! $material || $qty <= 0) {
            return ['ok' => false, 'message' => t('The material or the shortage qty is not valid.')];
        }

        $category = MatCategory::level(1)->where('category_name', $material->category1)->first();
        $coa = $category?->default_coa_account_id ?: $material->cogs_account_id;

        if (! $coa) {
            return ['ok' => false, 'message' => tf('There is no COA account for :name yet (material category / COGS account of the item). Complete it in Material Master first.', ['name' => $material->material_name])];
        }

        return DB::connection('tm2pur')->transaction(function () use ($part, $iqp, $material, $category, $qty, $coa, $login, $uom) {
            $dept = $this->department();
            $pr = PrRequisition::where('status', 'DRAFT')->where('i_source', 'MRP')
                ->where('i_notes', 'like', '%'.$this->tag($part).'%')->first();

            if (! $pr) {
                $pr = PrRequisition::create([
                    'i_doc_date' => now()->toDateString(),
                    'i_source' => 'MRP',
                    'i_department_id' => $dept,
                    'i_notes' => 'Kekurangan bahan dari BOM ProDev: '.$this->context($part, $iqp).' '.$this->tag($part),
                    'status' => 'DRAFT',
                    'created_by' => $login,
                    'updated_by' => $login,
                ]);
            }

            $uom = $uom ?: ($material->uom_stock ?: ($material->uom_purchase ?: 'PCS'));
            $line = PrLine::where('pr_id', $pr->pr_id)->where('item_id', $material->material_id)->where('i_uom', $uom)->first();

            if ($line) {
                $line->i_qty = round((float) $line->i_qty + $qty, 4);
            } else {
                $line = new PrLine([
                    'pr_id' => $pr->pr_id,
                    'line_no' => ((int) PrLine::where('pr_id', $pr->pr_id)->max('line_no')) + 1,
                    'i_item_source' => 'MASTER_ITEM',
                    'item_id' => $material->material_id,
                    'c_item_code' => $material->material_code,
                    'i_description' => $material->material_name,
                    'i_qty' => round($qty, 4),
                    'i_uom' => $uom,
                    'i_est_price' => $material->last_unitprice,
                    'i_department_id' => $dept,
                    'i_coa_account_id' => $coa,
                    'is_stock' => $category?->stock_class !== 'SERVICE',
                    'i_notes' => 'Kebutuhan BOM '.$this->context($part, $iqp),
                    'created_by' => $login,
                ]);
            }

            $line->updated_by = $login;
            $line->recalcSubtotal();
            $line->save();

            $pr->recalcEstTotal();
            $pr->updated_by = $login;
            $pr->save();

            return [
                'ok' => true,
                'message' => tf(':name :qty :uom added to draft PR #:id.', ['name' => $material->material_name,
                    'qty' => rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.'), 'uom' => $uom, 'id' => $pr->pr_id]),
                'url' => route('pur.pr.entry', ['pr' => $pr->pr_id]),
            ];
        });
    }

    // CO yang memuat Inquiry ini -- pilihan tujuan biaya surat jalan subkon.
    public function coOptions(?IqpPart $iqp): array
    {
        if (! $iqp) {
            return [];
        }

        return DB::connection('tm2sdi')->table('co_order_item as i')
            ->join('co_order as o', 'o.id_order', '=', 'i.order_id')
            ->where('i.inquiry_id', $iqp->inquiry_id)
            ->where('o.doc_status', '<>', 'CANCELLED')
            ->orderByDesc('o.id_order')
            ->get(['i.id_item', 'o.id_order', 'o.co_no', 'o.doc_status', 'o.customer_po_no', 'i.qty', 'i.qty_unit'])
            ->map(fn ($r) => [
                'id' => (int) $r->id_item,
                'label' => ($r->co_no ?: 'CO draft #'.$r->id_order).' · '.$r->doc_status
                    .($r->customer_po_no ? ' · PO '.$r->customer_po_no : '').' · '.(float) $r->qty.' '.$r->qty_unit,
            ])->all();
    }

    /**
     * @param  array  $step  baris BOP (process_id, label, qty_in, uom)
     */
    public function subcontract(ProdPart $part, IqpPart $iqp, array $step, int $supplierId, ?int $coItemId,
        ?string $needDate, ?float $estCost, string $login): array
    {
        $supplier = DB::connection('tm2mst')->table('sup_supplier')->where('id_supplier', $supplierId)->where('activate', 1)->first();

        if (! $supplier) {
            return ['ok' => false, 'message' => t('Choose an active subcontract supplier.')];
        }

        $row = DB::connection('tm2sdi')->table('iqp_part_process')
            ->where('part_id', $iqp->id_part)->where('process_id', $step['process_id'] ?? 0)->first();

        if (! $row) {
            return ['ok' => false, 'message' => t('Save the layout first (Save button) so this step is recorded in the Inquiry, then click Subcontract again.')];
        }

        $coItem = $coItemId
            ? DB::connection('tm2sdi')->table('co_order_item')->where('id_item', $coItemId)->where('inquiry_id', $iqp->inquiry_id)->first()
            : null;

        if ($coItemId && ! $coItem) {
            return ['ok' => false, 'message' => t('The chosen CO does not belong to this Inquiry.')];
        }

        $label = (string) ($step['label'] ?? 'Proses subkon');
        $qty = (float) ($step['qty_in'] ?? 0);
        $uom = (string) ($step['uom'] ?? 'LBR');
        $what = $this->context($part, $iqp);
        $messages = [];
        $prId = $row->subcon_pr_id;
        $doId = $row->subcon_do_id;

        DB::connection('tm2pur')->transaction(function () use (&$prId, &$messages, $label, $qty, $uom, $what, $supplier, $needDate, $estCost, $login) {
            if ($prId) {
                $messages[] = tf('The subcontract PR already exists (#:id).', ['id' => $prId]);

                return;
            }

            $dept = $this->department();
            $coa = DB::connection('tm2acc')->table('fin_account')->where('account_code', '513000')->value('account_id');

            $pr = PrRequisition::create([
                'i_doc_date' => now()->toDateString(),
                'i_source' => 'SUBCONTRACT',
                'i_department_id' => $dept,
                'i_need_date' => $needDate,
                'i_notes' => "Subkon {$label} ke {$supplier->supplier_name}: {$what}",
                'status' => 'DRAFT',
                'created_by' => $login,
                'updated_by' => $login,
            ]);

            $line = new PrLine([
                'pr_id' => $pr->pr_id,
                'line_no' => 1,
                'i_item_source' => 'FREE_TEXT',
                'i_description' => mb_substr("Jasa {$label} — {$what}", 0, 255),
                'i_qty' => $qty > 0 ? $qty : 1,
                'i_uom' => $qty > 0 ? $uom : 'JASA',
                'i_est_price' => $estCost && $qty > 0 ? round($estCost / $qty, 4) : $estCost,
                'i_department_id' => $dept,
                'i_coa_account_id' => $coa,
                'is_stock' => false,
                'i_need_date' => $needDate,
                'i_notes' => "Supplier usulan: {$supplier->supplier_name}",
                'created_by' => $login,
                'updated_by' => $login,
            ]);
            $line->recalcSubtotal();
            $line->save();

            $pr->recalcEstTotal();
            $pr->save();

            $prId = $pr->pr_id;
            $messages[] = tf('Subcontract PR draft #:id created.', ['id' => $pr->pr_id]);
        });

        if (! $doId && $coItem) {
            $do = DB::connection('tm2sdi')->transaction(function () use ($iqp, $part, $supplier, $coItem, $prId, $step, $label, $qty, $uom, $what, $needDate, $login) {
                $do = DoDelivery::create([
                    'do_type' => 'SUBCON',
                    'do_date' => now()->toDateString(),
                    'supplier_id' => $supplier->id_supplier,
                    'pr_id' => $prId,
                    'ref_part_id' => $part->id_part,
                    'subcon_process' => mb_substr($label, 0, 80),
                    'ship_address_label' => $supplier->supplier_name,
                    'ship_address_detail' => $supplier->default_address,
                    'ship_postal_code' => $supplier->postal_code,
                    'scheduled_ship_date' => $needDate,
                    'status' => 'DRAFT',
                    'notes' => mb_substr("Kirim ke subkon untuk {$label}", 0, 255),
                    'created_by' => $login,
                    'updated_by' => $login,
                ]);

                DoLine::create([
                    'delivery_id' => $do->id_delivery,
                    'line_no' => 1,
                    'co_order_id' => $coItem->order_id,
                    'co_order_item_id' => $coItem->id_item,
                    'item_description' => mb_substr("Setengah jadi {$what} — untuk {$label}", 0, 255),
                    'qty_do' => $qty > 0 ? $qty : 1,
                    'qty_unit' => $uom,
                    'unit_price' => 0,
                    'subtotal' => 0,
                    'price_overridden' => 0,
                    'qty_invoiced' => 0,
                    'status' => 'PLANNED',
                    'created_by' => $login,
                    'updated_by' => $login,
                ]);

                return $do;
            });

            $doId = $do->id_delivery;
            $messages[] = tf('Subcontract Delivery Note draft #:id created (CO linked).', ['id' => $do->id_delivery]);
        } elseif (! $doId) {
            $messages[] = t('The Subcontract Delivery Note waits for the CO — click Subcontract again after the CO for this Inquiry is issued.');
        } else {
            $messages[] = tf('The Subcontract Delivery Note already exists (#:id).', ['id' => $doId]);
        }

        DB::connection('tm2sdi')->table('iqp_part_process')->where('id_part_process', $row->id_part_process)->update([
            'subcon_supplier_id' => $supplier->id_supplier,
            'subcon_pr_id' => $prId,
            'subcon_do_id' => $doId,
            'updated_by' => $login,
            'updated_at' => now(),
        ]);
        DB::connection('tm2mst')->table('prod_part_process')
            ->where('part_id', $part->id_part)->where('process_id', $step['process_id'])
            ->update(['subcon_supplier_id' => $supplier->id_supplier, 'updated_by' => $login, 'updated_at' => now()]);

        return [
            'ok' => true,
            'message' => implode(' ', $messages),
            'prUrl' => $prId ? route('pur.pr.entry', ['pr' => $prId]) : null,
        ];
    }
}
