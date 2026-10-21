<?php

/*
 * REMARK
 * Tujuan       : Aturan entri Penerimaan Barang (SPB / Goods Receipt) dipindah apa adanya dari komponen v1 pages::pur.pv.entry: ubah header, ubah baris (qty beli/stok, konversi, variansi toleransi), QC qty selesai, QC mutu PASS/FAIL/PARTIAL, qty diterima/ditolak manual, override toleransi dengan alasan. Header dan baris hanya berubah saat DRAFT / QC_PENDING; QC dan override tidak mengecek status (sama seperti v1).
 * Sumber hulu  : PvReceipt, PvLine, PoLine (sisa PO untuk variansi), Translator (pesan alasan override).
 * Input        : PvReceipt + nama field / id baris / nilai.
 * Output       : pv_receipt, pv_line (tm2pur). Setiap metode memuat ulang model dari database.
 * Dipakai oleh : App\Support\V2\Data\PurchaseReceive::save, tests/Feature/PurchaseReceiveEntryTest.
 * Status       : Aktif (T10 tahap 7b, 2026-10-22). Posting, hapus, dan tambah baris PO tetap di PvPostingService / PvBuilderService lewat Data\PurchaseReceive.
 */

namespace App\Services\Pur;

use App\Models\Pur\PvLine;
use App\Models\Pur\PvReceipt;
use App\Support\Localization\Translator;
use Illuminate\Support\Facades\Auth;

final class PurchaseReceiveEntryService
{
    public const HEADER_FIELDS = ['i_doc_date', 'i_supplier_do_no', 'i_vehicle_no', 'i_notes'];

    public const LINE_FIELDS = ['i_qty_purchase', 'i_qty_stock', 'i_lot_no', 'i_location_code', 'i_supplier_do_no', 'is_conv_manual'];

    public static function updateHeader(PvReceipt $pv, string $field, mixed $value): void
    {
        $pv = self::fresh($pv);

        if (! in_array($pv->status, ['DRAFT', 'QC_PENDING'], true) || ! in_array($field, self::HEADER_FIELDS, true)) {
            return;
        }

        $pv->update([$field => $value === '' ? null : $value, 'updated_by' => Auth::user()->login]);
    }

    public static function updateLine(PvReceipt $pv, int $lineId, string $field, mixed $value): void
    {
        if (! in_array(self::fresh($pv)->status, ['DRAFT', 'QC_PENDING'], true) || ! in_array($field, self::LINE_FIELDS, true)) {
            return;
        }

        $line = self::line($pv, $lineId);
        $line->{$field} = $value === '' ? null : $value;

        if (in_array($field, ['i_qty_purchase', 'i_qty_stock'], true)) {
            $line->c_conv_used = (float) $line->i_qty_purchase > 0 ? (float) $line->i_qty_stock / (float) $line->i_qty_purchase : null;
            $poLine = $line->poLine;
            $outstandingBefore = $poLine ? ((float) $poLine->i_qty - (float) $poLine->c_qty_received) : 0;
            $line->recalcVariance($outstandingBefore);
        }

        $line->updated_by = Auth::user()->login;
        $line->save();
    }

    public static function markQtyDone(PvReceipt $pv, int $lineId): void
    {
        $line = self::line($pv, $lineId);
        $line->update(['qc_qty_status' => 'DONE', 'updated_by' => Auth::user()->login]);

        $fresh = self::fresh($pv);
        if ($fresh->status === 'DRAFT') {
            $fresh->update(['status' => 'QC_PENDING']);
        }
    }

    public static function setQuality(PvReceipt $pv, int $lineId, string $value): void
    {
        $line = self::line($pv, $lineId);
        $qtyStock = (float) $line->i_qty_stock;

        $update = ['qc_quality_status' => $value, 'qc_by' => Auth::user()->login, 'qc_at' => now(), 'updated_by' => Auth::user()->login];

        if ($value === 'PASS') {
            $update['c_qty_accepted'] = $qtyStock;
            $update['c_qty_rejected'] = 0;
        } elseif ($value === 'FAIL') {
            $update['c_qty_accepted'] = 0;
            $update['c_qty_rejected'] = $qtyStock;
        }
        // PARTIAL: officer isi manual via updateQcQty()

        $line->update($update);

        if ($value !== 'PENDING') {
            $line->update(['c_line_value' => (float) ($update['c_qty_accepted'] ?? $line->c_qty_accepted) * (float) $line->c_unit_price]);
        }
    }

    public static function updateQcQty(PvReceipt $pv, int $lineId, string $field, mixed $value): void
    {
        if (! in_array($field, ['c_qty_accepted', 'c_qty_rejected'], true)) {
            return;
        }

        $line = self::line($pv, $lineId);
        $line->{$field} = $value === '' ? 0 : $value;
        $line->c_line_value = (float) $line->c_qty_accepted * (float) $line->c_unit_price;
        $line->updated_by = Auth::user()->login;
        $line->save();
    }

    // Balasan = pesan kesalahan ('' = berhasil), pengganti properti postMessage komponen v1.
    public static function overrideTolerance(PvReceipt $pv, int $lineId, string $reason): string
    {
        if (trim($reason) === '') {
            return Translator::t('proc_pv_entry_override_reason_required', 'Alasan override wajib diisi.');
        }

        self::line($pv, $lineId)->update([
            'tol_override_by' => Auth::user()->login,
            'tol_override_at' => now(),
            'tol_override_reason' => $reason,
            'updated_by' => Auth::user()->login,
        ]);

        return '';
    }

    private static function fresh(PvReceipt $pv): PvReceipt
    {
        return PvReceipt::with('lines')->findOrFail($pv->pv_id);
    }

    private static function line(PvReceipt $pv, int $lineId): PvLine
    {
        return PvLine::where('pv_id', $pv->pv_id)->findOrFail($lineId);
    }
}
