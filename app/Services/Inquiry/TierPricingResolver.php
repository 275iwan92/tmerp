<?php

namespace App\Services\Inquiry;

use App\Models\Sdi\IqpInquiry;
use Illuminate\Support\Collection;

// Gabung iqp_tier (qty + target harga dari Sales) dengan iqp_costing_price_tier
// (harga jual terkalkulasi) per tier_id. Dipakai dokumen customer-facing
// (Nota Inquiry, Quotation) yang butuh baris per jenjang qty + harga.
class TierPricingResolver
{
    // $priced=false membuat semua kolom harga tetap null (Sisi A/B: costing belum
    // published_flag=1 -> jangan bocorkan harga jual, CLAUDE.md §4).
    public static function rows(IqpInquiry $inquiry, bool $priced): Collection
    {
        $costingTiers = $priced ? ($inquiry->costing?->priceTiers->keyBy('tier_id') ?? collect()) : collect();

        $rows = $inquiry->tiers->sortBy('tier_seq')->values()->map(function ($tier) use ($costingTiers) {
            $ct = $costingTiers->get($tier->id_tier);

            return (object) [
                'tier' => $tier,
                'costing' => $ct,
                'price_excl' => $ct?->price_per_pcs,
                'price_incl' => $ct?->c_after_ppn_pc,
                'value_total' => $ct ? $ct->price_per_pcs * $tier->qty : null,
                'is_best' => false,
            ];
        });

        $pricedRows = $rows->filter(fn ($r) => $r->price_excl !== null);

        if ($pricedRows->isEmpty()) {
            return $rows;
        }

        $bestTierId = $pricedRows->sortBy('price_excl')->first()->tier->id_tier;

        return $rows->map(function ($r) use ($bestTierId) {
            $r->is_best = $r->tier->id_tier === $bestTierId;

            return $r;
        });
    }

    public static function primary(Collection $rows)
    {
        return $rows->first(fn ($r) => $r->tier->is_primary) ?? $rows->first();
    }
}
