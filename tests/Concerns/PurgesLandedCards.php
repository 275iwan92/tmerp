<?php

namespace Tests\Concerns;

use App\Models\Inventory\InvLandedCostLine;
use App\Models\Inventory\RmStockcard;
use App\Services\Inventory\StockCardChain;
use Illuminate\Support\Facades\DB;

// Pembersih uji landed cost: baris kartu khusus nilai (doc_type VA) milik dokumen uji dihapus, lalu rantai posisi dan item dihitung ulang
// dari baris sesudahnya supaya kartu bahan milik data dummy kembali persis seperti sebelum uji. $docIds null = semua baris VA landed cost.
trait PurgesLandedCards
{
    // Jurnal dan catatan Repost yang ditulis posting stok uji (jurnal pemakaian, penilaian ulang) atas nama $by.
    protected function purgeTestJournals(string $by): void
    {
        $vouchers = DB::connection('tm2acc')->table('fin_voucher')->where('created_by', $by)->pluck('voucher_id');
        DB::connection('tm2acc')->table('fin_voucher')->whereIn('voucher_id', $vouchers)->update(['reversal_of_id' => null, 'reversed_by_id' => null]);
        DB::connection('tm2acc')->table('fin_voucher_line')->whereIn('voucher_id', $vouchers)->delete();
        DB::connection('tm2acc')->table('fin_voucher')->whereIn('voucher_id', $vouchers)->delete();
        DB::connection('tm2inv')->table('inv_repost_log')->where('created_by', $by)->delete();
    }

    protected function purgeLandedCards(?array $docIds = null): void
    {
        $cards = RmStockcard::query()->where('doc_type', StockCardChain::VALUE_ADJUSTMENT)->where('reference_name', 'like', 'tm2inv.inv_landed_cost_line%');
        if ($docIds !== null) {
            $cards->whereIn('reference_detail_id', InvLandedCostLine::query()->whereIn('id_landed_cost', $docIds ?: [0])->pluck('id_landed_cost_line'));
        }
        foreach ($cards->orderByDesc('doc_id')->get() as $card) {
            $card->delete();
            $after = fn ($q) => $q->where(fn ($w) => $w->where('doc_date', '>', $card->doc_date)->orWhere(fn ($t) => $t->where('doc_date', $card->doc_date)->where('doc_id', '>', $card->doc_id)))
                ->orderBy('doc_date')->orderBy('doc_id');
            $item = RmStockcard::query()->where('material_id', $card->material_id);
            $next = [$after((clone $item)->where('location_id', $card->location_id))->first(), $after($item)->first()];
            foreach (array_unique(array_filter(array_map(fn ($r) => $r?->doc_id, $next))) as $id) {
                DB::connection('tm2inv')->transaction(fn () => StockCardChain::recalculate('RM', RmStockcard::query()->findOrFail($id)));
            }
        }
    }
}
