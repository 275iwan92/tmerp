<?php

declare(strict_types=1);

namespace App\Services\Pur;

use App\Models\Inventory\InvShortageRequest;
use App\Models\Pur\DnDebitNote;
use App\Models\Pur\PrLine;
use App\Models\Pur\PrRequisition;
use App\Models\Pur\PvLine;
use App\Services\Inventory\QcAuthority;
use App\Services\Inventory\QcNotifier;
use Illuminate\Support\Facades\DB;

// Tindak lanjut dokumen untuk kurang kirim (D19, bagian 7.6) dan pemberitahuannya, dipanggil ShortageRequestService:
//   additionalPr  : permintaan pembelian TAMBAHAN (Draft) saat permintaan dikonfirmasi dan baris PO dibuka lagi; Pembelian memilih memakai PR ini
//                   atau backorder pada PO yang sama, jangan keduanya (tertulis di catatan PR).
//   debitNote     : draft debit note saat pemasok menolak / lewat jendela. Belum ada fitur debit note pembelian (prt_return = retur barang fisik),
//                   jadi dn_debit_note adalah dokumen draft sederhana; jurnalnya belum dibuat.
// Departemen dan akun PR diambil dari baris PO asal; tanpa keduanya PR tidak dibuat dan alasannya dikembalikan sebagai peringatan.
final class ShortageFollowUp
{
    /** @return string|null peringatan (null = beres atau tidak berlaku) */
    public static function additionalPr(InvShortageRequest $req, PvLine $line, float $ratio, string $login): ?string
    {
        if ($req->source_type !== 'SUPPLIER' || ! $req->material_id || ! $line->po_line_id) {
            return null;
        }
        $po = DB::connection('tm2pur')->table('po_line')->where('po_line_id', $line->po_line_id)->first(['i_department_id', 'i_coa_account_id', 'c_line_status']);
        if (! $po || ! $po->i_department_id || ! $po->i_coa_account_id) {
            return t('No additional purchase request was made: the PO line has no department or account.');
        }
        $stockShort = (float) $req->qty_short;
        $price = (float) $line->c_unit_price;
        $pr = DB::connection('tm2pur')->transaction(function () use ($req, $po, $stockShort, $price, $ratio, $login) {
            $pr = PrRequisition::query()->create(['i_doc_date' => now()->toDateString(), 'i_source' => 'MANUAL', 'i_department_id' => $po->i_department_id, 'i_need_date' => $req->need_date,
                'i_notes' => mb_substr("Additional request for shortage {$req->request_no}. Use this OR the reopened PO line (backorder), not both.", 0, 255), 'status' => 'DRAFT',
                'c_est_total' => round($stockShort * $price * $ratio, 4), 'created_by' => $login, 'updated_by' => $login]);
            PrLine::query()->create(['pr_id' => $pr->pr_id, 'line_no' => 1, 'i_item_source' => 'MASTER_ITEM', 'item_id' => $req->material_id, 'c_item_code' => $req->material_code,
                'i_description' => mb_substr((string) $req->material_name, 0, 255), 'i_qty' => $stockShort, 'i_uom' => (string) $req->uom_code, 'i_est_price' => round($price * $ratio, 4),
                'c_est_subtotal' => round($stockShort * $price * $ratio, 4), 'i_department_id' => $po->i_department_id, 'i_coa_account_id' => $po->i_coa_account_id, 'is_stock' => true,
                'i_need_date' => $req->need_date, 'i_notes' => mb_substr("WO {$req->wo_no}", 0, 255), 'c_line_status' => 'OPEN', 'created_by' => $login, 'updated_by' => $login]);

            return $pr;
        });
        $req->forceFill(['pr_id' => $pr->pr_id])->save();

        return null;
    }

    // Draft debit note atas qty yang tidak dikirim (satuan beli x harga baris penerimaan). Customer tidak ditagih: tidak ada debit note.
    public static function debitNote(InvShortageRequest $req, string $login): ?DnDebitNote
    {
        if ($req->source_type === 'CUSTOMER' || $req->debit_note_id) {
            return null;
        }
        $line = $req->pv_line_id ? PvLine::query()->find($req->pv_line_id) : null;
        $ratio = $line && (float) $line->i_qty_stock > 0 && (float) $line->i_qty_purchase > 0 ? (float) $line->i_qty_purchase / (float) $line->i_qty_stock : 1.0;
        $qty = round((float) $req->qty_short * $ratio, 4);
        $price = (float) ($line?->c_unit_price ?? 0);
        $dn = DnDebitNote::query()->create(['doc_no' => self::nextNo(), 'i_doc_date' => now()->toDateString(), 'i_supplier_id' => $req->supplier_id, 'pv_id' => $req->pv_id, 'po_id' => $req->po_id,
            'shortage_id' => $req->id_shortage, 'i_reason' => mb_substr("Shortage {$req->request_no} not delivered: WO {$req->wo_no}", 0, 255), 'i_qty' => $qty, 'i_uom' => $line?->i_uom_purchase ?? $req->uom_code,
            'i_unit_price' => $price, 'c_amount' => round($qty * $price, 4), 'status' => 'DRAFT', 'created_by' => $login, 'updated_by' => $login]);
        $req->forceFill(['debit_note_id' => $dn->dn_id])->save();

        return $dn;
    }

    // Kepala Gudang diminta menghitung ulang bila request baru lahir (DRAFT).
    public static function notifyCreated(InvShortageRequest $req): void
    {
        QcNotifier::toGroups(QcAuthority::RECOUNT, 'Recount needed', tf(':no · WO :wo · :item: a shortage is suspected. Recount or weigh the material.', ['no' => $req->request_no, 'wo' => $req->wo_no, 'item' => $req->material_code]));
    }

    // PPIC diminta mengonfirmasi atau menutup setelah hitung ulang.
    public static function notifyRecounted(InvShortageRequest $req): void
    {
        QcNotifier::toGroups(QcAuthority::SHORTAGE_CONFIRM, 'Shortage to confirm', tf(':no · WO :wo · :item: the recount proves a shortage. Confirm it or close it as usage variance.', ['no' => $req->request_no, 'wo' => $req->wo_no, 'item' => $req->material_code]));
    }

    // Pembelian dan PPIC diberi tahu bahwa kekurangan perlu diminta ke sumbernya (mendesak bila WO masih jalan).
    public static function notifyConfirmed(InvShortageRequest $req): void
    {
        $text = tf(':no · WO :wo · :item: confirmed short. Ask the source to send the shortfall.', ['no' => $req->request_no, 'wo' => $req->wo_no, 'item' => $req->material_code]);
        QcNotifier::toGroups(array_merge(QcAuthority::SHORTAGE_FOLLOW_UP, QcAuthority::SHORTAGE_CONFIRM), 'Shortage to follow up', $text, 'error');
    }

    private static function nextNo(): string
    {
        $prefix = 'DN'.now()->format('y');
        $last = DnDebitNote::query()->where('doc_no', 'like', $prefix.'%')->orderByDesc('doc_no')->value('doc_no');

        return $prefix.str_pad((string) (($last ? (int) substr($last, strlen($prefix)) : 0) + 1), 5, '0', STR_PAD_LEFT);
    }
}
