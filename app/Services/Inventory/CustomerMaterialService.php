<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Master\WhLoc;
use App\Models\Pur\PvLine;
use App\Models\Pur\PvReceipt;
use Illuminate\Support\Collection;
use RuntimeException;

// Material titipan customer (consignment, v2.01, D3): penerimaan tipe CUSTOMER (SpecialReceiptService) yang barisnya menunjuk bahan baku
// (item_id) menjadi stok RM berpemilik CUSTOMER di bin area CONSIGN. Tidak dinilai (tanpa harga, tanpa nilai kartu) dan tidak dijurnal:
// StockPostingService sudah memperlakukan owner_type selain COMPANY begitu. Hanya bisa di-pick untuk pekerjaan customer itu (PickAllocator).
// Baris tanpa bahan (hanya keterangan) tetap bukan stok, seperti sebelumnya.
final class CustomerMaterialService
{
    // Bin pertama yang aktif dan bisa dipakai di area CONSIGN (bukan virtual); dibatasi ke gudang berkode $warehouseCode bila diisi.
    public function consignBin(?string $warehouseCode = null): ?WhLoc
    {
        return WhLoc::query()->whereHas('zone', fn ($z) => $z->where('zone_type', 'CONSIGN')->where('is_virtual', false)->where('activate', true))
            ->when($warehouseCode, fn ($q) => $q->whereHas('warehouse', fn ($w) => $w->where('warehouse_code', $warehouseCode)))
            ->where('activate', true)->where('is_usable', true)->where('count_lock', false)->orderBy('location_code')->first();
    }

    /** @return Collection<int, PvLine> baris yang menjadi stok */
    public function stockLines(PvReceipt $pv): Collection
    {
        return $pv->lines()->whereNotNull('item_id')->where('i_qty_purchase', '>', 0)->get();
    }

    // Stok masuk dibuat dan diposting; kembalian = id dokumen Stock In RM (null bila tidak ada baris bahan).
    public function receive(PvReceipt $pv, string $login): ?int
    {
        $lines = $this->stockLines($pv);
        if ($lines->isEmpty()) {
            return null;
        }
        $bin = $this->consignBin($pv->i_warehouse_code) ?? throw new RuntimeException(t('There is no usable bin in a Consignment area. Add one in Storage Areas and Bins first.'));
        $docs = new StockDocumentService;
        $id = $docs->saveDraft('RM', null, ['doc_no' => $docs->nextDocNo('RM', 'IN'), 'doc_date' => $pv->i_doc_date ?? now()->toDateString(), 'doc_type' => 1, 'doc_cat' => 'STOCK',
            'ref_doc_type' => 'PV', 'ref_doc_id' => $pv->pv_id, 'ref_doc_no' => $pv->doc_no, 'warehouse_id' => $bin->warehouse_id,
            'notes' => mb_substr('Customer material '.$pv->doc_no, 0, 255)],
            $lines->map(fn (PvLine $l) => ['material_id' => $l->item_id, 'qty' => (float) $l->i_qty_purchase, 'uom_code' => $l->i_uom_stock ?: $l->i_uom_purchase, 'unit_price' => 0,
                'lot_no' => $l->i_lot_no, 'location_id' => $bin->location_id, 'owner_type' => 'CUSTOMER', 'owner_id' => $pv->i_customer_id])->all(), $login);
        $res = (new StockPostingService)->postDocument('RM', $id, $login);
        if (! $res['posted']) {
            $docs->cancel('RM', $id, $login);
            throw new RuntimeException((string) $res['error']);
        }

        return $id;
    }
}
