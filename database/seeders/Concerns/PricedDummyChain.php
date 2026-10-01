<?php

namespace Database\Seeders\Concerns;

use App\Models\Sdi\CoOrder;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\QuoQuotation;
use App\Services\Costing\CostingEngine;
use App\Support\V2\Data\Quotation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Aturan A.1 (user 2026-10-01): Inquiry -> Quotation WAJIB integrated, termasuk data dummy. Sampel dummy tidak lagi
// menyisipkan quotation dengan "harga contoh": inquiry dihitung ProCost Mode #2 (data_source MANUAL, CostingEngine
// sungguhan), di-publish, lalu quotation dibuat QuotationBuilder dari harga costing itu, dan WIN lewat CoOrderBuilder.
trait PricedDummyChain
{
    // Costing INTEGRATED untuk inquiry dummy: 1 baris costing per part COMPONENT, tier dari iqp_tier, lalu publish.
    protected function priceAndPublish(int $inquiryId, string $by = 'uat'): int
    {
        $sdi = DB::connection('tm2sdi');
        $inq = $sdi->table('iqp_inquiry')->where('id_inquiry', $inquiryId)->first() ?? throw new RuntimeException("Inquiry {$inquiryId} tidak ada");
        $grammage = DB::connection('tm2mst')->table('mat_grammage')->where('activate', true)->orderBy('id_grammage')->value('id_grammage')
            ?? throw new RuntimeException('mat_grammage kosong');
        $parts = $sdi->table('iqp_part')->where('inquiry_id', $inquiryId)->where(fn ($q) => $q->whereNull('part_kind')->orWhere('part_kind', '<>', 'ASSEMBLY'))->orderBy('part_seq')->get();
        $now = now();
        $stamp = ['created_by' => $by, 'updated_by' => $by, 'created_at' => $now, 'updated_at' => $now, 'data_origin' => 'DUMMY'];
        $first = $parts->first();

        $costingId = $sdi->table('iqp_costing')->insertGetId([
            'inquiry_id' => $inquiryId, 'mode' => IqpCosting::MODE_INTEGRATED, 'customer_id' => $inq->customer_id, 'product_name_draft' => $inq->product_draft_name,
            'calc_type' => 'NEW', 'doc_status' => 'DRAFT', 'i_dim_panjang_mm' => $first->flat_w ?? 100, 'i_dim_lebar_mm' => $first->flat_h ?? 100, 'i_dim_tinggi_mm' => 0,
            'ppn_mode' => 'PPN', 'ppn_pct' => 11,
        ] + $stamp, 'id_costing');
        foreach ($sdi->table('iqp_tier')->where('inquiry_id', $inquiryId)->orderBy('tier_seq')->get() as $t) {
            $sdi->table('iqp_costing_price_tier')->insert(['costing_id' => $costingId, 'tier_id' => $t->id_tier, 'qty_pcs' => $t->qty, 'is_primary' => $t->is_primary,
                'tolerance_pct' => 5, 'margin_pct' => 15, 'rounding_mode' => 'NEAREST', 'rounding_step' => 1] + $stamp);
        }
        foreach ($parts as $i => $p) {
            // Plano 65 x 90 cm; isi per plano dari luas bentangan part (mm) -- minimal 1.
            $cut = max(1, min(24, (int) floor((650 * 900) / max(1, (float) $p->flat_w * (float) $p->flat_h))));
            $sdi->table('iqp_costing_part')->insert(['costing_id' => $costingId, 'part_id' => $p->id_part, 'no_urut' => $i + 1, 'part_name' => $p->part_name,
                'part_type_code' => 'DC', 'data_source' => 'MANUAL', 'qty_per_set' => $p->qty_per_sku ?: 1, 'grammage_id' => $grammage,
                'i_panjang_plano_cm' => 65, 'i_lebar_plano_cm' => 90, 'i_cut_per_plano' => $cut, 'i_isi_per_lb' => 1, 'i_jumlah_warna' => 4,
                'i_ongkos_cetak_rim' => 450000, 'i_ongkos_plong_lb' => 70, 'i_ongkos_lem_pc' => 25, 'i_film_faktor' => 0.25, 'i_pisau_faktor' => 0.05, 'i_pisau_rate_rp' => 550000] + $stamp);
        }
        (new CostingEngine)->run(IqpCosting::findOrFail($costingId));
        $sdi->table('iqp_costing')->where('id_costing', $costingId)->update(['doc_status' => 'PUBLISHED', 'published_flag' => true, 'is_approved' => true,
            'approved_by' => $by, 'approved_at' => $now, 'updated_by' => $by]);
        $sdi->table('iqp_inquiry')->where('id_inquiry', $inquiryId)->update(['sub_state' => 'PRICE_CALCULATED']);

        return $costingId;
    }

    // Quotation dari inquiry ber-costing (harga = tier ProCost) -> PUBLISHED -> WIN (CO DRAFT + Master Product).
    protected function winViaQuotation(int $inquiryId, array $header): CoOrder
    {
        $q = Quotation::create([$inquiryId]);
        $q->update($header);
        Quotation::confirm($q->refresh());
        $coId = Quotation::win(QuoQuotation::with('items.inquiry')->findOrFail($q->id_quotation)) ?? throw new RuntimeException("WIN inquiry {$inquiryId} gagal");

        return CoOrder::with('items')->findOrFail($coId);
    }

    protected function dropCosting(int $inquiryId): void
    {
        $sdi = DB::connection('tm2sdi');
        $ids = $sdi->table('iqp_costing')->where('inquiry_id', $inquiryId)->pluck('id_costing');
        $parts = $sdi->table('iqp_costing_part')->whereIn('costing_id', $ids)->pluck('id_costing_part');
        $sdi->table('iqp_costing_part_coating')->whereIn('costing_part_id', $parts)->delete();
        $sdi->table('iqp_costing_part')->whereIn('costing_id', $ids)->delete();
        $sdi->table('iqp_costing_price_tier')->whereIn('costing_id', $ids)->delete();
        $sdi->table('iqp_costing')->whereIn('id_costing', $ids)->delete();
    }
}
