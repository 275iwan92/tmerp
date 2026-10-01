<?php

namespace App\Services\Costing;

use App\Models\Master\ProdLayoutGroup;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpPart;
use App\Models\Sdi\IqpTier;
use Illuminate\Support\Facades\DB;

/**
 * Tombol "Reverse" (keputusan user 2026-09-18, lihat diskusi
 * docs/inquiry_product_costing/procost_formula_library.md §V-VI): balik dari
 * costing INSTANT (kalkulator standalone) MENJADI costing INTEGRATED, dengan
 * auto-generate seluruh silsilah yang normalnya lahir SEBELUM Costing
 * (Inquiry BARU + ProDev) -- bukan sebaliknya.
 *
 * 3 keputusan eksplisit user (jangan tanya ulang):
 * 1. Inquiry hasil reverse SELALU tipe BARU (instant costing pasti belum py
 *    Master Product).
 * 2. ProDev (prod_layout_group) SELALU lahir status DRAFT -- Confirmed cuma
 *    lewat klik tombol manusia di layar ProDev, tidak pernah otomatis.
 * 3. Angka costing yang SUDAH ADA tidak boleh berubah sama sekali -- part
 *    tetap data_source=MANUAL pasca-reverse (TIDAK dipindah ke PRODEV),
 *    Reverse cuma menambah silsilah/tautan, bukan menghitung ulang apa pun.
 *
 * Temuan teknis yang MEMAKSA Reverse juga bikin Master Product (prod_product):
 * `prod_part`/`prod_layout_group` (tm2mst) keduanya `product_id NOT NULL` --
 * tak ada jalan bikin ProDev tanpa Master Product lebih dulu. Dibuat dengan
 * `card_status=DRAFT` (persis makna "belum di-approve", sejalan dgn ProDev-nya
 * yang juga DRAFT) -- proposal ini SUDAH disampaikan ke user, belum ada
 * penolakan eksplisit saat instruksi "continue to execute" diberikan.
 *
 * Cross-DB (tm2sdi + tm2mst, FK logical CLAUDE.md SS2): dual-transaction
 * best-effort (2 koneksi terpisah, bukan 1 XA transaction) -- kalau sisi
 * tm2sdi gagal setelah tm2mst berhasil commit, exception dilempar dgn baris
 * mst yang sudah terlanjur ada (jarang terjadi, tapi dicatat sbg batasan).
 */
class ReverseCostingService
{
    public function reverse(IqpCosting $costing, string $actingLogin): IqpInquiry
    {
        if (! $costing->isInstant()) {
            throw new \RuntimeException('Reverse cuma berlaku utk costing mode INSTANT.');
        }

        if (! $costing->customer_id) {
            throw new \RuntimeException('Isi Customer dulu sebelum Reverse -- Inquiry butuh customer.');
        }

        if (! $costing->product_name_draft) {
            throw new \RuntimeException('Isi Nama Produk dulu sebelum Reverse.');
        }

        $parts = $costing->parts()->get();

        if ($parts->isEmpty()) {
            throw new \RuntimeException('Tambah minimal 1 part dulu sebelum Reverse -- tidak ada apa pun utk ditautkan ke Inquiry/ProDev.');
        }

        $mst = DB::connection('tm2mst');
        $sdi = DB::connection('tm2sdi');

        $mst->beginTransaction();
        $sdi->beginTransaction();

        try {
            $inquiryNo = IqpInquiry::nextInquiryNo();

            // ── Master Product draft (tm2mst) — wajib, product_id NOT NULL di prod_part/prod_layout_group ──
            $product = ProdProduct::create([
                'product_code' => 'REV-'.$inquiryNo,
                'product_name' => $costing->product_name_draft,
                'customer_id' => $costing->customer_id,
                'card_status' => 'DRAFT',
                'activate' => true,
                'created_by' => $actingLogin,
                'updated_by' => $actingLogin,
            ]);

            // ── Inquiry BARU (tm2sdi) — sub_state PRICE_CALCULATED krn HPP memang sudah ada ──
            $inquiry = IqpInquiry::create([
                'inquiry_no' => $inquiryNo,
                'inquiry_date' => now()->toDateString(),
                'source' => 'REVERSE',
                'customer_id' => $costing->customer_id,
                'is_prospect' => false,
                'product_draft_name' => $costing->product_name_draft,
                'inquiry_type' => 'NEW',
                'sub_state' => 'PRICE_CALCULATED',
                'stat_confirm_sales' => true,
                'stat_confirm_prodev' => true,
                'created_by' => $actingLogin,
                'updated_by' => $actingLogin,
            ]);

            // ── Per part: ProdPart + ProdLayoutGroup (DRAFT) di tm2mst, lalu IqpPart di tm2sdi ──
            foreach ($parts as $part) {
                $planoP = (float) $part->i_panjang_plano_cm;
                $planoL = (float) $part->i_lebar_plano_cm;
                $dimP = (float) ($part->i_dim_panjang_mm ?: $costing->i_dim_panjang_mm);
                $dimL = (float) ($part->i_dim_lebar_mm ?: $costing->i_dim_lebar_mm);

                $prodPart = ProdPart::create([
                    'product_id' => $product->id_product,
                    'part_code' => 'P'.$part->no_urut,
                    'part_name' => $part->part_name ?: 'Part '.$part->no_urut,
                    'part_type' => $part->part_type_code,
                    'qty_per_sku' => $part->qty_per_set ?: 1,
                    'cm_length' => $dimP > 0 ? round($dimP / 10, 4) : 0,
                    'cm_width' => $dimL > 0 ? round($dimL / 10, 4) : 0,
                    'activate' => true,
                    'created_by' => $actingLogin,
                    'updated_by' => $actingLogin,
                ]);

                $layoutGroup = ProdLayoutGroup::create([
                    'product_id' => $product->id_product,
                    'mastercard_code' => $product->product_code.'-'.$part->no_urut,
                    'plano_w_mm' => $planoP > 0 ? $planoP * 10 : null,
                    'plano_h_mm' => $planoL > 0 ? $planoL * 10 : null,
                    'up_count' => max(1, (int) round((float) $part->i_cut_per_plano)),
                    'status' => 'DRAFT',
                    'created_by' => $actingLogin,
                    'updated_by' => $actingLogin,
                ]);

                $mst->table('prod_layout_group_part')->insert([
                    'id_layout_group' => $layoutGroup->id_layout_group,
                    'part_id' => $prodPart->id_part,
                    'qty_up_in_group' => 1,
                    'created_at' => now(),
                ]);

                $iqpPart = IqpPart::create([
                    'inquiry_id' => $inquiry->id_inquiry,
                    'prod_part_id' => $prodPart->id_part,
                    'part_seq' => $part->no_urut,
                    'part_code' => 'P'.$part->no_urut,
                    'part_name' => $part->part_name ?: 'Part '.$part->no_urut,
                    'part_type' => $part->part_type_code,
                    'qty_per_sku' => $part->qty_per_set ?: 1,
                    'flat_w' => $dimP ?: null,
                    'flat_h' => $dimL ?: null,
                    'flat_area' => ($dimP && $dimL) ? $dimP * $dimL : null,
                    'created_by' => $actingLogin,
                    'updated_by' => $actingLogin,
                ]);

                // Keputusan #3: data_source TETAP MANUAL -- cuma tautan silsilah
                // ditambahkan (part_id/prod_layout_group_id/prodev_part_id),
                // angka i_*/c_* yang sudah ada TIDAK disentuh sama sekali.
                $part->update([
                    'part_id' => $iqpPart->id_part,
                    'prod_layout_group_id' => $layoutGroup->id_layout_group,
                    'prodev_part_id' => $prodPart->id_part,
                ]);
            }

            // ── Tier qty (iqp_tier) dari costing_price_tier yang sudah ada ──
            foreach ($costing->priceTiers as $tierSeq => $tier) {
                $iqpTier = IqpTier::create([
                    'inquiry_id' => $inquiry->id_inquiry,
                    'tier_seq' => $tierSeq + 1,
                    'qty' => $tier->qty_pcs,
                    'is_primary' => $tier->is_primary,
                    'price_excl' => $tier->price_per_pcs,
                    'price_incl' => $tier->c_after_ppn_pc,
                    'created_by' => $actingLogin,
                    'updated_by' => $actingLogin,
                ]);

                $tier->update(['tier_id' => $iqpTier->id_tier]);
            }

            // ── Flip costing: INSTANT -> INTEGRATED, tautkan ke inquiry baru ──
            $costing->update([
                'inquiry_id' => $inquiry->id_inquiry,
                'mode' => IqpCosting::MODE_INTEGRATED,
                'reversed_at' => now(),
                'reversed_by' => $actingLogin,
            ]);

            $mst->commit();
            $sdi->commit();

            return $inquiry;
        } catch (\Throwable $e) {
            $mst->rollBack();
            $sdi->rollBack();

            throw $e;
        }
    }
}
