<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Master\SupSupplier;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Master\WhZone;
use App\Models\Pur\PvLine;
use App\Models\Pur\PvReceipt;
use App\Services\Inventory\WarehouseStock;
use App\Support\V2\Src;

// Stock in Inspection: barang yang ADA di gudang tetapi BELUM / TIDAK boleh dipakai, keadaan sekarang (tanpa periode):
//   Waiting for inspection = baris penerimaan barang (SPB) yang menunggu QC (status QC_PENDING, belum dinilai) -- belum jadi stok;
//   Rejected, to return    = qty yang ditolak QC dan belum diretur ke supplier;
//   QC hold                = stok di bin area QC Hold;
//   Blocked bin            = stok di bin yang diblokir (tidak bisa dipakai).
// Padanan "quality inspection stock" dan "blocked stock" di ERP lain. Angka stok dari WarehouseStock (sama dengan peta gudang).
class StockInspectionReport extends AllStockReport
{
    public function slug(): string
    {
        return 'inv-rpt-inspection';
    }

    public function title(): string
    {
        return 'Stock in Inspection';
    }

    public function icon(): string
    {
        return 'fa-solid fa-magnifying-glass-chart';
    }

    public function search(): string
    {
        return 'Search item, receipt no, bin, supplier...';
    }

    public function guide(): ?string
    {
        return 'Goods in the warehouse now that cannot be used: waiting for quality inspection, rejected and not returned yet, in a QC Hold area, or in a blocked bin. Days = days since the receipt or inspection; for stock in a bin, since the item last moved.';
    }

    protected function defaultPeriod(): ?array
    {
        return null;
    }

    public function groupBy(): ?string
    {
        return 'state';
    }

    public function rowTone(): ?string
    {
        return 'tone';
    }

    protected function sourceText(): string
    {
        return Src::of('tm2pur.pv_receipt', 'tm2pur.pv_line', 'tm2inv.v_rm_onhand', 'tm2inv.v_sfg_onhand', 'tm2inv.v_fg_onhand', 'tm2mst.wh_loc', 'tm2mst.wh_zone');
    }

    public function rows(): array
    {
        return array_merge($this->receiptRows(), $this->heldStockRows());
    }

    // Penerimaan barang: menunggu QC, atau ditolak dan belum diretur.
    private function receiptRows(): array
    {
        $receipts = PvReceipt::query()->whereIn('status', ['QC_PENDING', 'POSTED'])->orderBy('i_doc_date')->get()->keyBy('pv_id');
        $lines = PvLine::query()->whereIn('pv_id', $receipts->keys()->all())->where(fn ($q) => $q
            ->where(fn ($w) => $w->whereNull('qc_quality_status')->orWhere('qc_quality_status', ''))
            ->orWhereRaw('IFNULL(c_qty_rejected, 0) > IFNULL(c_qty_returned, 0)'))->orderBy('pv_id')->orderBy('line_no')->get();
        // Lintas-DB (tm2pur -> tm2mst): nama supplier dalam satu query.
        $suppliers = SupSupplier::query()->whereKey($receipts->pluck('i_supplier_id')->filter()->unique()->all())->pluck('supplier_name', 'id_supplier');

        $rows = [];
        foreach ($lines as $l) {
            $pv = $receipts[$l->pv_id];
            $waiting = $pv->status === 'QC_PENDING' && ($l->qc_quality_status === null || $l->qc_quality_status === '');
            $rejected = (float) $l->c_qty_rejected - (float) $l->c_qty_returned;
            if (! $waiting && ($pv->status !== 'POSTED' || $rejected <= 0)) {
                continue;
            }
            $since = $waiting ? $pv->i_doc_date : ($l->qc_at ?? $pv->i_doc_date);
            $rows[] = ['id' => 'PV-'.$l->pv_line_id, 'state' => $waiting ? 'Waiting for inspection' : 'Rejected, to return', 'item' => $l->i_description, 'item_code' => $l->c_item_code,
                'class' => null, 'reference' => $pv->label(), 'place' => $suppliers[$pv->i_supplier_id] ?? null, 'since' => $since?->toDateString(),
                'days' => $since ? (int) now()->startOfDay()->diffInDays($since->copy()->startOfDay(), true) : null,
                'qty' => $waiting ? (float) $l->i_qty_stock : $rejected, 'unit' => $l->i_uom_stock, 'value' => null, 'note' => $l->qc_note, 'tone' => $waiting ? null : 'danger'];
        }

        return $rows;
    }

    // Stok di bin area QC Hold atau di bin yang diblokir.
    private function heldStockRows(): array
    {
        $holdZones = WhZone::query()->where('zone_type', 'QC_HOLD')->pluck('id_zone')->all();
        $bins = WhLoc::query()->where(fn ($q) => $q->where('is_usable', false)->orWhereIn('zone_id', $holdZones ?: [0]))->get()->keyBy('location_id');
        if ($bins->isEmpty()) {
            return [];
        }
        $buildings = WhWarehouse::query()->whereKey($bins->pluck('warehouse_id')->unique()->all())->get()->keyBy('id_warehouse');

        return WarehouseStock::positions($bins->keys()->all())->map(function (array $p) use ($bins, $buildings, $holdZones) {
            $bin = $bins[$p['location_id']];
            $hold = in_array($bin->zone_id, $holdZones, false);

            return ['id' => $p['key'], 'state' => $hold ? 'QC hold' : 'Blocked bin', 'item' => $p['name'], 'item_code' => $p['code'], 'class' => self::CLASSES[$p['class']],
                'reference' => $p['location_code'], 'place' => $buildings->get($bin->warehouse_id)?->warehouse_name, 'since' => $p['last_movement'], 'days' => $p['idle_days'],
                'qty' => $p['qty'], 'unit' => $p['uom'], 'value' => $p['value'], 'note' => $bin->block_reason, 'tone' => null];
        })->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'state', 'label' => 'State', 'type' => 'badge', 'tones' => ['Waiting for inspection' => 'warn', 'Rejected, to return' => 'danger', 'QC hold' => 'info', 'Blocked bin' => 'neutral']],
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'item_code', 'avatar' => false],
            ['key' => 'class', 'label' => 'Class', 'visible' => false],
            ['key' => 'reference', 'label' => 'Receipt No. / Bin'],
            ['key' => 'place', 'label' => 'Supplier / Warehouse'],
            ['key' => 'since', 'label' => 'Since', 'type' => 'date'],
            ['key' => 'days', 'label' => 'Days', 'type' => 'number'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'sum' => true],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'value', 'label' => 'Stock Value', 'type' => 'money', 'sum' => true],
            ['key' => 'note', 'label' => 'Notes'],
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Waiting for inspection', 'where' => ['state' => 'Waiting for inspection'], 'tone' => 'warn'],
            ['label' => 'Rejected, to return', 'where' => ['state' => 'Rejected, to return'], 'tone' => 'danger'],
            ['label' => 'QC hold', 'where' => ['state' => 'QC hold'], 'tone' => 'info'],
            ['label' => 'Blocked bin', 'where' => ['state' => 'Blocked bin'], 'tone' => 'neutral'],
        ];
    }

    public function filters(): array
    {
        return ['state' => 'State', 'class' => 'Class', 'place' => 'Supplier / Warehouse'];
    }

    public function groups(): array
    {
        return ['state' => 'State', 'place' => 'Supplier / Warehouse', 'item' => 'Item'];
    }

    public function printSignatures(): array
    {
        return [['Prepared By', 'Warehouse'], ['Checked By', 'Quality Control']];
    }
}
