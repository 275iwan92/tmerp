<?php

namespace App\Services\Pur;

use App\Models\Pur\PoLine;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PvReceipt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// PvPostingService -- gerbang POST (PROC_TIER_2 §A-4.5) + rekalkulasi PER PO
// yang tersentuh (PV-08, DELTA_PROC_PV_MULTIPO.md), krn satu SPB kini boleh
// menyentuh banyak PO sekaligus.
//
// TIDAK LENGKAP SAMPAI DI SINI: kontrak "tulis rm_stock/rm_stock_d" (PROC_TIER_3
// §5.1) BELUM diimplementasi -- dump legacy tminv_20260722_2018_sd.sql belum
// pernah diberikan (lihat PROC_STATE.yaml). Status PV/PO tetap direkalkulasi
// benar; yang tertunda HANYA baris rm_stock_doc_id (tetap NULL) & pergerakan
// stok fisik. Begitu dump tersedia, tambahkan pemanggilan insert rm_stock di
// checkAndPost() persis di titik yang ditandai TODO.
class PvPostingService
{
    public static function blockers(PvReceipt $pv): array
    {
        $blockers = [];

        foreach ($pv->lines as $line) {
            if ($line->qc_qty_status !== 'DONE') {
                $blockers[] = "Baris {$line->line_no} belum di-QC qty.";

                continue;
            }

            if ($line->qc_quality_status === 'PENDING') {
                $blockers[] = "Baris {$line->line_no} belum di-QC kualitas.";

                continue;
            }

            if ($line->is_tol_exceeded && ! $line->tol_override_by) {
                $blockers[] = "Baris {$line->line_no} melampaui toleransi, belum di-override.";
            }
        }

        return $blockers;
    }

    public static function post(PvReceipt $pv): array
    {
        if ($pv->status !== 'QC_PENDING' && $pv->status !== 'DRAFT') {
            return ['ok' => false, 'message' => 'Dokumen sudah tidak berstatus DRAFT/QC_PENDING.'];
        }

        $blockers = static::blockers($pv);

        if (! empty($blockers)) {
            return ['ok' => false, 'message' => implode(' ', $blockers)];
        }

        $login = Auth::user()->login;

        DB::connection('tm2pur')->transaction(function () use ($pv, $login) {
            // TODO(tminv): begitu rm_stock/rm_stock_d tersedia, INSERT satu rm_stock
            // (doc_no=$pv->doc_no, doc_type=1, doc_cat='STOCK', warehouse_id=...)
            // + N rm_stock_d per baris is_stock=1 (material_id, qty=c_qty_accepted,
            // uom_code=i_uom_stock, unit_price=c_unit_price, lot_no, reference_id=
            // pv_line_id, reference_name='tm2pur.pv_line'), lalu isi rm_stock_doc_id.

            $pv->update(['status' => 'POSTED', 'posted_at' => now(), 'posted_by' => $login, 'updated_by' => $login]);

            // PV-08: rekalkulasi SETIAP PO yang tersentuh, bukan asumsi satu PO.
            $poIds = DB::connection('tm2pur')->table('pv_line')->where('pv_id', $pv->pv_id)->select('c_po_id')->distinct()->pluck('c_po_id');

            foreach ($poIds as $poId) {
                static::recalcPo($poId);
            }
        });

        return ['ok' => true, 'message' => null];
    }

    protected static function recalcPo(int $poId): void
    {
        $po = PoOrder::with('lines')->find($poId);

        if (! $po) {
            return;
        }

        foreach ($po->lines as $line) {
            $received = (float) DB::connection('tm2pur')->table('pv_line')
                ->join('pv_receipt', 'pv_receipt.pv_id', '=', 'pv_line.pv_id')
                ->where('pv_line.po_line_id', $line->po_line_id)
                ->where('pv_receipt.status', 'POSTED')
                ->sum('pv_line.i_qty_purchase');

            $outstanding = max((float) $line->i_qty - $received, 0);
            $tolPct = (float) ($line->c_tol_pct ?? 5);
            $variancePct = $line->i_qty > 0 ? ($outstanding / $line->i_qty * 100) : 0;

            $status = match (true) {
                $received >= (float) $line->i_qty => 'RECEIVED',
                $variancePct <= $tolPct && $received > 0 => 'CLOSED_BY_TOLERANCE',
                $received > 0 => 'PARTIALLY_RECEIVED',
                default => 'OPEN',
            };

            $line->update(['c_qty_received' => $received, 'c_qty_outstanding' => $outstanding, 'c_line_status' => $status]);
        }

        $receivableLines = $po->lines->where('is_receivable', true);
        $allClosed = $receivableLines->every(fn ($l) => in_array($l->fresh()->c_line_status, ['RECEIVED', 'CLOSED_BY_TOLERANCE']));
        $anyReceived = $receivableLines->sum(fn ($l) => (float) $l->fresh()->c_qty_received) > 0;

        $newStatus = match (true) {
            $allClosed => 'RECEIVED',
            $anyReceived => 'PARTIALLY_RECEIVED',
            default => $po->status,
        };

        if ($newStatus !== $po->status) {
            $po->update(['status' => $newStatus]);
        }
    }
}
