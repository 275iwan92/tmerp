<?php

/*
 * REMARK
 * Tujuan       : Satu tempat angka stok bahan untuk PPIC (kolom On hand dan Available di BOM, gate material): Available = bebas dari BOOKING atau RESERVE.
 * Sumber hulu  : tm2inv.v_rm_available (kolom qty_onhand, lokasi usable) dan tm2inv.rm_booking / rm_booking_d (hanya dibaca).
 * Input        : daftar material_id.
 * Output       : [material_id => ['onhand' => float, 'available' => float]]; Available = max(0, on hand - sisa booking terbuka SEMUA tahap).
 * Dipakai oleh : Ppic::stockOf (layar BOM) dan GateService::ownLines (gate material).
 * Status       : Aktif di mockup (ddt*). View v_rm_available dan tabel booking tidak diubah (wilayah ProCost / Material Booking).
 * ATTENTION-ENGINE-02 / 03 / 04: lihat docs/ENGINE-ATTENTION.md (pembaca lama masih memakai angka view; Release tidak mem-booking; satuan tanpa konversi).
 */

declare(strict_types=1);

namespace App\Services\Scheduling\Plan;

use Illuminate\Support\Facades\DB;

/**
 * Jo 2026-10-13: "Available artinya bebas dari BOOKING atau RESERVE", dan booking Draft / PRE ikut mengurangi (keputusan 06:31Z).
 *
 * Open booking left = qty_book - qty_issued (> 0) of every booking line that is not cancelled, whatever its stage (Draft, PRE B0 / B1,
 * B2, Held, Confirmed), header not cancelled / closed. A Held pre-booking whose hold_until has passed no longer holds (the hold ends by
 * itself, see BookingMaintenance). On hand is taken from the view (usable locations) and the bookings are subtracted here, ONCE, so a
 * Confirmed booking the view already counts is not subtracted twice. A negative result shows as 0.
 * SEBELUM: Available = v_rm_available.qty_available (on hand minus CONFIRMED open bookings only).
 */
final class AvailableStock
{
    /**
     * @param  iterable<int|string|null>  $materialIds
     * @return array<int, array{onhand: float, available: float}>
     */
    public static function of(iterable $materialIds): array
    {
        $ids = collect($materialIds)->filter()->unique()->values()->all();
        if ($ids === []) {
            return [];
        }
        $inv = DB::connection('tm2inv');
        $onhand = $inv->table('v_rm_available')->where('is_usable', 1)->whereIn('material_id', $ids)
            ->selectRaw('material_id, SUM(qty_onhand) oh')->groupBy('material_id')->pluck('oh', 'material_id');
        $booked = $inv->table('rm_booking_d as d')->join('rm_booking as h', 'h.booking_id', '=', 'd.booking_id')
            ->whereIn('d.material_id', $ids)->where('d.stat_cancel', 0)->where('h.stat_cancel', 0)->where('h.stat_closed', 0)
            ->where('h.doc_status', '!=', 'CANCELLED')->whereColumn('d.qty_book', '>', 'd.qty_issued')
            ->where(fn ($q) => $q->where('h.doc_status', '!=', 'HELD')->orWhereNull('h.hold_until')->orWhere('h.hold_until', '>=', now()->toDateString()))
            ->selectRaw('d.material_id, SUM(d.qty_book - d.qty_issued) b')->groupBy('d.material_id')->pluck('b', 'd.material_id');

        $out = [];
        foreach ($onhand as $id => $oh) {
            $out[(int) $id] = ['onhand' => (float) $oh, 'available' => max(0.0, (float) $oh - (float) ($booked[$id] ?? 0))];
        }

        return $out;
    }
}
