<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvQcHold;
use App\Models\Inventory\InvSupplierClaim;
use Illuminate\Support\Facades\DB;

// Telusur maju batch yang ditolak (Q-08, Q-09, bagian 7.5): batch -> dokumen keluar bahan (rm_stock_d.batch_id) -> WO -> CO / pengiriman.
// Baris keluar lama yang belum bertanda batch dicocokkan lewat item + lot supplier. WO terdampak ditahan (inv_qc_hold) dan satu klaim
// supplier dibuat (inv_supplier_claim). Hold dibebaskan lagi bila batch kemudian diputuskan bukan Rejected / Blocked.
final class QcTraceService
{
    /**
     * Pemakaian batch ini: satu elemen per baris dokumen keluar yang sudah Confirmed dan tidak dibatalkan.
     *
     * @return list<array{doc_no: string, doc_date: string|null, wo_id: int|null, wo_no: string|null, qty: float, uom: string|null, co_id: int|null, co_no: string|null, delivery_id: int|null, delivery_no: string|null, wo_status: string|null}>
     */
    public static function forward(InvBatch $batch): array
    {
        $issues = DB::connection('tm2inv')->table('rm_stock_d as d')->join('rm_stock as h', 'h.doc_id', '=', 'd.doc_id')
            ->where('h.doc_cat', 'STOCK')->where('h.doc_type', -1)->where('h.flow_status', 'POSTED')->where('h.stat_cancel', 0)->whereNotNull('h.wo_id')
            ->where(fn ($q) => $q->where('d.batch_id', $batch->id_batch)->orWhere(fn ($w) => $w->whereNull('d.batch_id')->where('d.material_id', $batch->item_id)
                ->when($batch->supplier_lot, fn ($x) => $x->whereRaw('UPPER(TRIM(d.lot_no)) = ?', [BatchService::normalizeLot($batch->supplier_lot)]), fn ($x) => $x->whereRaw('1 = 0'))))
            ->orderBy('h.doc_date')->get(['h.doc_no', 'h.doc_date', 'h.wo_id', 'h.wo_no', 'd.qty', 'd.uom_code']);

        $wos = DB::connection('tm2prd')->table('prd_wo')->whereIn('wo_id', $issues->pluck('wo_id')->filter()->unique())->get(['wo_id', 'wo_no', 'wo_status', 'source_co_id', 'source_delivery_id'])->keyBy('wo_id');
        $cos = DB::connection('tm2sdi')->table('co_order')->whereIn('id_order', $wos->pluck('source_co_id')->filter()->unique())->pluck('co_no', 'id_order');
        $deliveries = DB::connection('tm2sdi')->table('do_delivery')->whereIn('id_delivery', $wos->pluck('source_delivery_id')->filter()->unique())->pluck('do_no', 'id_delivery');

        return $issues->map(function ($i) use ($wos, $cos, $deliveries) {
            $wo = $i->wo_id ? $wos->get($i->wo_id) : null;

            return ['doc_no' => (string) $i->doc_no, 'doc_date' => $i->doc_date ? substr((string) $i->doc_date, 0, 10) : null, 'wo_id' => $i->wo_id ? (int) $i->wo_id : null,
                'wo_no' => $wo->wo_no ?? $i->wo_no, 'qty' => (float) $i->qty, 'uom' => $i->uom_code, 'wo_status' => $wo->wo_status ?? null,
                'co_id' => $wo?->source_co_id ? (int) $wo->source_co_id : null, 'co_no' => $wo?->source_co_id ? ($cos[$wo->source_co_id] ?? null) : null,
                'delivery_id' => $wo?->source_delivery_id ? (int) $wo->source_delivery_id : null, 'delivery_no' => $wo?->source_delivery_id ? ($deliveries[$wo->source_delivery_id] ?? null) : null];
        })->all();
    }

    /** Ditolak: tahan WO yang memakai batch dan buka klaim supplier. @return array{holds: int, claim: InvSupplierClaim|null} */
    public static function onReject(InvBatch $batch, string $reason, string $login): array
    {
        return ['holds' => self::holdWorkOrders($batch, $reason, $login), 'claim' => self::openClaim($batch, $reason, $login)];
    }

    /** Diblokir / ditolak: tandai WO yang memakai batch sebagai ditahan. @return int jumlah WO yang ditahan */
    public static function holdWorkOrders(InvBatch $batch, string $reason, string $login): int
    {
        $holds = 0;
        $byWo = collect(self::forward($batch))->filter(fn ($u) => $u['wo_id'])->groupBy('wo_id');
        foreach ($byWo as $woId => $uses) {
            $first = $uses->first();
            $hold = InvQcHold::query()->firstOrNew(['batch_id' => $batch->id_batch, 'wo_id' => (int) $woId]);
            $hold->forceFill(['wo_no' => $first['wo_no'], 'co_id' => $first['co_id'], 'delivery_id' => $first['delivery_id'], 'qty_issued' => $uses->sum('qty'), 'status' => 'HELD',
                'reason' => mb_substr($reason, 0, 255), 'held_by' => $login, 'held_at' => now(), 'cleared_by' => null, 'cleared_at' => null])->save();
            $holds++;
        }

        return $holds;
    }

    // Satu klaim terbuka per batch; qty = jumlah yang diterima untuk batch itu pada penerimaan barang.
    public static function openClaim(InvBatch $batch, string $reason, string $login): ?InvSupplierClaim
    {
        $open = InvSupplierClaim::query()->where('batch_id', $batch->id_batch)->whereIn('status', ['OPEN', 'SENT'])->first();
        if ($open) {
            return $open;
        }
        $lines = DB::connection('tm2pur')->table('pv_line')->where('i_batch_id', $batch->id_batch)->get(['pv_id', 'c_qty_accepted', 'i_uom_stock']);

        return InvSupplierClaim::query()->create(['claim_no' => self::nextClaimNo(), 'batch_id' => $batch->id_batch, 'supplier_id' => $batch->supplier_id, 'pv_id' => $lines->first()->pv_id ?? null,
            'material_id' => $batch->item_id, 'qty_claimed' => (float) $lines->sum('c_qty_accepted'), 'uom_code' => $lines->first()->i_uom_stock ?? null,
            'reason' => mb_substr($reason, 0, 255), 'status' => 'OPEN', 'created_by' => $login, 'updated_by' => $login]);
    }

    public static function clearHolds(InvBatch $batch, string $login): int
    {
        return InvQcHold::query()->where('batch_id', $batch->id_batch)->where('status', 'HELD')->update(['status' => 'CLEARED', 'cleared_by' => $login, 'cleared_at' => now()]);
    }

    public static function setClaimStatus(InvSupplierClaim $claim, string $status, string $login, ?string $notes = null): InvSupplierClaim
    {
        if (! in_array($status, ['OPEN', 'SENT', 'SETTLED', 'DROPPED'], true)) {
            throw new \InvalidArgumentException("Status klaim tidak dikenal: {$status}");
        }
        $claim->forceFill(['status' => $status, 'notes' => $notes !== null ? mb_substr($notes, 0, 255) : $claim->notes, 'updated_by' => $login])->save();

        return $claim;
    }

    // Nomor klaim: CL + 2 digit tahun + 5 digit urut (CL2600001).
    private static function nextClaimNo(): string
    {
        $prefix = 'CL'.now()->format('y');
        $last = InvSupplierClaim::query()->where('claim_no', 'like', $prefix.'%')->orderByDesc('claim_no')->value('claim_no');

        return $prefix.str_pad((string) (($last ? (int) substr($last, strlen($prefix)) : 0) + 1), 5, '0', STR_PAD_LEFT);
    }
}
