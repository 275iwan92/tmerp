<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\InvSubconIssue;
use App\Models\Inventory\InvSubconIssueLine;
use App\Models\Inventory\InvTransfer;
use App\Models\Inventory\InvTransferLine;
use App\Models\Inventory\RmStockcard;
use App\Models\Master\MatRaw;
use App\Models\Master\WhLoc;
use App\Models\Pur\PoLine;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PvReceipt;
use Illuminate\Support\Facades\DB;

// Bahan di tangan subkon (v2.01, D2, D22). Kirim bahan merujuk PO Subkon (po_type SUBCONTRACT, belum selesai); stok pindah dari bin gudang ke
// bin SUBCON milik vendor itu (VirtualLocationService::vendor) lewat dokumen Stock Transfer yang sudah ada, jadi kartu stok, nilai (Average
// bin asal ikut pindah) dan palet berjalan seperti transfer biasa. Kepemilikan tetap COMPANY dan bernilai; tanpa jurnal.
//   send()          kirim satu bahan (RM) dari satu bin
//   returnUnused()  bahan belum terpakai kembali ke bin gudang (Stock Transfer vendor -> gudang)
//   consume()       bahan terpakai di vendor: Stock Out RM dari bin vendor (menjadi pemakaian biasa, jurnal pemakaian standar StockPostingService)
//   settleReceipt() dipanggil SpecialReceiptService::post untuk penerimaan SUBCON: terpakai sebanding qty yang diterima dari baris PO
//                   (diterima / qty baris PO x qty kirim), sehingga penerimaan hasil subkon mengurangi saldo vendor
//   cancel()        batal kirim selama belum ada yang terpakai / kembali (stok balik ke bin asal)
// Hanya bahan baku (RM); semi jadi / jadi ke subkon belum dibangun.
final class SubconStockService
{
    private const EPS = 0.00005;

    public function send(int $poId, int $fromLocationId, int $materialId, float $qty, ?int $poLineId, ?string $dueReturn, ?string $notes, string $login): InvSubconIssue
    {
        $po = PoOrder::query()->find($poId);
        if (! $po || $po->i_po_type !== 'SUBCONTRACT' || $po->stat_cancel || ! in_array($po->status, ['SENT', 'PARTIALLY_RECEIVED'], true)) {
            throw new SubconStockRefused(t('Choose a Subcon PO that is still open.'));
        }
        $material = MatRaw::withoutGlobalScopes()->find($materialId) ?? throw new SubconStockRefused(t('The material was not found.'));
        $bin = WhLoc::query()->find($fromLocationId) ?? throw new SubconStockRefused(t('Choose the bin the material comes from.'));
        if ($qty <= 0) {
            throw new SubconStockRefused(t('Fill in the quantity to send.'));
        }
        $owner = PickAllocator::ownerOf($materialId, $fromLocationId, null);
        if ($owner['type'] !== 'COMPANY') {
            throw new SubconStockRefused(t('Only stock owned by the company can be sent to a subcontractor.'));
        }
        $line = $poLineId ? PoLine::query()->where('po_id', $poId)->find($poLineId) : PoLine::query()->where('po_id', $poId)->orderBy('line_no')->first();
        $vendor = (new VirtualLocationService($login))->vendor((int) $po->i_supplier_id);
        $transfers = new StockTransferService;
        $uom = RmStockcard::query()->where('material_id', $materialId)->where('location_id', $fromLocationId)->orderByDesc('doc_date')->orderByDesc('doc_id')->value('uom_code') ?: null;

        return DB::connection('tm2inv')->transaction(function () use ($po, $bin, $material, $qty, $line, $vendor, $dueReturn, $notes, $login, $uom) {
            $issue = InvSubconIssue::create(['issue_no' => InvSubconIssue::nextNo(), 'issue_date' => now()->toDateString(), 'po_id' => $po->po_id, 'po_no' => $po->doc_no,
                'supplier_id' => $po->i_supplier_id, 'due_return_date' => $dueReturn ?: null, 'status' => 'SENT', 'notes' => $notes ? mb_substr($notes, 0, 255) : null,
                'created_by' => $login, 'updated_by' => $login]);
            $doc = $this->transfer($bin, $vendor, $material, $qty, $uom, 'Subcon send '.$issue->issue_no.' / PO '.$po->doc_no, $login);
            $cost = InvTransferLine::query()->where('transfer_id', $doc->id_transfer)->value('unit_cost');
            $issue->forceFill(['transfer_id' => $doc->id_transfer])->save();
            InvSubconIssueLine::create(['issue_id' => $issue->id_subcon_issue, 'line_no' => 1, 'po_line_id' => $line?->po_line_id, 'stock_class' => 'RM', 'item_id' => $material->material_id,
                'item_code' => $material->material_code, 'item_name' => $material->material_name, 'from_location_id' => $bin->location_id, 'vendor_location_id' => $vendor->location_id,
                'uom_code' => $uom, 'qty_sent' => $qty, 'unit_cost' => $cost, 'created_by' => $login, 'updated_by' => $login]);

            return $issue->load('lines');
        });
    }

    // Bahan belum terpakai kembali ke bin gudang.
    public function returnUnused(InvSubconIssueLine $line, float $qty, int $toLocationId, string $login): void
    {
        $this->settle($line, $qty, $login, function (InvSubconIssueLine $l, InvSubconIssue $issue) use ($qty, $toLocationId, $login) {
            $to = WhLoc::query()->find($toLocationId) ?? throw new SubconStockRefused(t('Choose the bin the material goes back to.'));
            $from = WhLoc::query()->findOrFail($l->vendor_location_id);
            $this->transfer($from, $to, MatRaw::withoutGlobalScopes()->findOrFail($l->item_id), $qty, $l->uom_code, 'Subcon return '.$issue->issue_no, $login);
            $l->qty_returned = round($l->qty_returned + $qty, 4);
        });
    }

    // Bahan terpakai di vendor: Stock Out RM dari bin vendor.
    public function consume(InvSubconIssueLine $line, float $qty, string $login): void
    {
        $this->settle($line, $qty, $login, function (InvSubconIssueLine $l, InvSubconIssue $issue) use ($qty, $login) {
            $vendorBin = WhLoc::query()->findOrFail($l->vendor_location_id);
            $docs = new StockDocumentService;
            $no = $docs->nextDocNo('RM', 'OUT');
            $id = $docs->saveDraft('RM', null, ['doc_no' => $no, 'doc_date' => now(), 'doc_type' => -1, 'doc_cat' => 'STOCK', 'ref_doc_type' => 'SUBCON_ISSUE',
                'ref_doc_id' => $issue->id_subcon_issue, 'ref_doc_no' => $issue->issue_no, 'warehouse_id' => $vendorBin->warehouse_id,
                'notes' => mb_substr('Used at subcontractor, '.$issue->issue_no.' / PO '.$issue->po_no, 0, 255)],
                [['material_id' => $l->item_id, 'qty' => $qty, 'uom_code' => $l->uom_code, 'location_id' => $l->vendor_location_id, 'action_type' => 'ISSUE']], $login);
            $res = (new StockPostingService)->postDocument('RM', $id, $login);
            if (! $res['posted']) {
                $docs->cancel('RM', $id, $login);
                throw new SubconStockRefused((string) $res['error']);
            }
            $l->qty_consumed = round($l->qty_consumed + $qty, 4);
        });
    }

    // Penerimaan SUBCON terposting: bahan terpakai sebanding bagian PO yang diterima. Kembalian = [nomor kirim => qty terpakai].
    /** @return array<string, float> */
    public function settleReceipt(PvReceipt $pv, string $login): array
    {
        $done = [];
        foreach ($pv->lines()->whereNotNull('po_line_id')->get() as $received) {
            $po = PoLine::query()->find($received->po_line_id);
            if (! $po || (float) $po->i_qty <= 0) {
                continue;
            }
            $share = min(1.0, (float) $received->i_qty_purchase / (float) $po->i_qty);
            $lines = InvSubconIssueLine::query()->where('po_line_id', $po->po_line_id)->whereHas('issue', fn ($q) => $q->where('status', 'SENT'))->orderBy('id_subcon_issue_line')->get();
            foreach ($lines as $l) {
                $take = min($l->atVendor(), round($l->qty_sent * $share, 4));
                if ($take > self::EPS) {
                    $this->consume($l, $take, $login);
                    $key = (string) $l->issue->issue_no;
                    $done[$key] = ($done[$key] ?? 0.0) + $take;
                }
            }
        }

        return $done;
    }

    // Batal kirim: hanya selama belum ada yang terpakai atau kembali.
    public function cancel(InvSubconIssue $issue, string $reason, string $login): void
    {
        DB::connection('tm2inv')->transaction(function () use ($issue, $reason, $login) {
            $i = InvSubconIssue::query()->with('lines')->lockForUpdate()->findOrFail($issue->id_subcon_issue);
            if ($i->status !== 'SENT' || $i->lines->contains(fn (InvSubconIssueLine $l) => $l->qty_consumed > 0 || $l->qty_returned > 0)) {
                throw new SubconStockRefused(t('This send can no longer be cancelled: material was already used or returned.'));
            }
            if (trim($reason) === '') {
                throw new SubconStockRefused(t('Fill in the reason for cancelling.'));
            }
            $doc = InvTransfer::query()->find($i->transfer_id);
            if ($doc && ($error = (new StockTransferService)->cancel($doc, 'Subcon send '.$i->issue_no.' cancelled: '.$reason, $login))) {
                throw new SubconStockRefused($error);
            }
            $i->forceFill(['status' => 'CANCELLED', 'cancel_reason' => mb_substr(trim($reason), 0, 255), 'updated_by' => $login])->save();
        });
    }

    // Satu pintu untuk return / consume: kunci baris, periksa sisa, jalankan, simpan, tutup kirim bila habis.
    private function settle(InvSubconIssueLine $line, float $qty, string $login, callable $apply): void
    {
        DB::connection('tm2inv')->transaction(function () use ($line, $qty, $login, $apply) {
            $l = InvSubconIssueLine::query()->lockForUpdate()->findOrFail($line->id_subcon_issue_line);
            $issue = InvSubconIssue::query()->lockForUpdate()->findOrFail($l->issue_id);
            if ($issue->status !== 'SENT') {
                throw new SubconStockRefused(t('This send is already closed or cancelled.'));
            }
            if ($qty <= 0 || $qty > $l->atVendor() + self::EPS) {
                throw new SubconStockRefused(tf('The quantity must be more than 0 and not more than what is left at the vendor (:qty).', ['qty' => $l->atVendor()]));
            }
            $apply($l, $issue);
            $l->forceFill(['updated_by' => $login])->save();
            $issue->load('lines');
            if ($issue->lines->every(fn (InvSubconIssueLine $x) => $x->atVendor() <= self::EPS)) {
                $issue->forceFill(['status' => 'CLOSED', 'updated_by' => $login])->save();
            }
        });
    }

    // Dokumen Stock Transfer satu baris dan langsung diposting (tanpa approval, seperti putaway).
    private function transfer(WhLoc $from, WhLoc $to, MatRaw $material, float $qty, ?string $uom, string $notes, string $login): InvTransfer
    {
        $doc = InvTransfer::create(['transfer_date' => now()->toDateString(), 'from_warehouse_id' => $from->warehouse_id, 'to_warehouse_id' => $to->warehouse_id, 'reason' => 'OTHER',
            'notes' => mb_substr($notes, 0, 255), 'created_by' => $login, 'updated_by' => $login]);
        InvTransferLine::create(['transfer_id' => $doc->id_transfer, 'line_no' => 1, 'stock_class' => 'RM', 'item_id' => $material->material_id, 'item_code' => $material->material_code,
            'item_name' => $material->material_name, 'from_location_id' => $from->location_id, 'to_location_id' => $to->location_id, 'qty' => $qty, 'uom_code' => $uom,
            'created_by' => $login, 'updated_by' => $login]);
        if ($error = (new StockTransferService)->post($doc, $login)) {
            throw new SubconStockRefused($error);
        }

        return $doc->refresh();
    }
}
