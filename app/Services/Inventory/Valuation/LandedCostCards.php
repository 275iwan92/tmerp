<?php

declare(strict_types=1);

namespace App\Services\Inventory\Valuation;

use App\Models\Inventory\InvLandedCost;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\StockOnhand;
use App\Services\Inventory\StockCardChain;

// Nilai landed cost masuk KARTU STOK (menutup "Landed cost not in cards"): per baris landed cost satu baris kartu khusus nilai
// (doc_type VA, qty 0, value_in = nilai) di bin yang paling banyak isinya. StockCardChain menerimanya di mode BIN maupun ITEM, jadi
// saldo tetap bersambung (BB = EB) dan Average item naik.
//   Tanggal    : saat Confirm / Revise / Cancel (atau tanggal dokumen bila lebih baru), BUKAN tanggal dokumen yang mundur: bagian yang sudah keluar
//                sudah masuk Selisih Landed Cost, jadi pengeluaran lama tidak boleh ikut dihargai ulang; hanya baris keluar bertanggal sesudahnya yang ikut.
//   Idempoten  : yang ditulis hanya SELISIH antara nilai yang seharusnya (to_stock bila Confirmed, 0 bila Cancelled) dan card_value
//                yang sudah ada di kartu. Dipanggil dua kali = tidak ada baris baru. Kunci unik kartu: referensi + '#' + urutan (card_rev).
//   Revise     : to_stock berubah -> baris selisih (value_in naik / turun). Cancel -> baris pembalik (value_in = -card_value).
//   Penilaian ulang pemakaian: bila baris nilai bertanggal mundur dan Average pengeluaran yang sudah diposting berubah, selisihnya dijurnal
//                RepostService::revalueFromChain (satu kali per baris kartu yang ditulis).
// Dipanggil LandedCostService di dalam transaksi tm2inv-nya.
final class LandedCostCards
{
    public const REFERENCE = 'tm2inv.inv_landed_cost_line';

    public function __construct(private RepostService $reposts) {}

    /** @return list<string> peringatan (penilaian ulang pemakaian yang tidak bisa dijurnal); kartu tetap tertulis */
    public function sync(InvLandedCost $doc, string $login): array
    {
        $live = (bool) $doc->stat_confirm && ! $doc->stat_cancel;
        $warnings = [];
        foreach ($doc->lines()->lockForUpdate()->get() as $line) {
            $delta = round(($live ? (float) $line->to_stock : 0.0) - (float) $line->card_value, 4);
            if (abs($delta) < 0.005) {
                continue;
            }
            $bin = $this->bin($line);
            if (! $bin) {
                continue;
            }
            $card = RmStockcard::create(['doc_date' => max(now(), $doc->doc_date->copy()->startOfDay())->toDateTimeString(), 'material_id' => $line->item_id, 'doc_type' => StockCardChain::VALUE_ADJUSTMENT,
                'location_id' => $bin->location_id, 'warehouse_id' => $bin->warehouse_id, 'qty' => 0, 'uom_code' => $bin->uom_code ?: $line->uom_code,
                'reference_name' => self::REFERENCE.'#'.($line->card_rev + 1), 'reference_detail_id' => $line->id_landed_cost_line,
                'qty_bb' => 0, 'qty_in' => 0, 'qty_out' => 0, 'qty_adj' => 0, 'qty_eb' => 0, 'value_in' => $delta, 'owner_type' => 'COMPANY', 'period_id' => null,
                'create_by' => $login, 'update_by' => $login]);
            $chain = StockCardChain::recalculate('RM', $card, true);
            $line->forceFill(['card_value' => round((float) $line->card_value + $delta, 4), 'card_rev' => $line->card_rev + 1, 'card_location_id' => $bin->location_id])->save();

            try {
                $this->reposts->revalueFromChain((int) $line->item_id, $chain['revalued_docs'], (string) $doc->doc_no, $login);
            } catch (ValuationRefused|\DomainException $e) {
                $warnings[] = $e->getMessage();
            }
        }
        $doc->forceFill(['card_synced' => $doc->lines()->get()->every(fn ($l) => abs(($live ? (float) $l->to_stock : 0.0) - (float) $l->card_value) < 0.005)])->save();

        return $warnings;
    }

    // Bin yang memikul nilai: yang sudah dipakai baris ini; baru -> bin dengan saldo terbesar. Item tanpa saldo di mana pun = tidak ada kartu (to_stock pasti 0).
    private function bin(object $line): ?object
    {
        $q = StockOnhand::of('RM')->where('material_id', $line->item_id);
        if ($line->card_location_id) {
            return $q->where('location_id', $line->card_location_id)->first();
        }

        return $q->where('qty_onhand', '>', 0)->orderByDesc('qty_onhand')->first();
    }
}
