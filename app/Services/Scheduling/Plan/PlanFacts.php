<?php

declare(strict_types=1);

namespace App\Services\Scheduling\Plan;

use App\Models\Prd\PrdWo;
use Illuminate\Support\Facades\DB;

/**
 * What scheduling must know about a Work Order, read from Product Development (part, layout, dieline, board, colours) and the
 * customer (tier). Read-only; nothing here changes ProDev data. NULL = not in the data, never guessed.
 */
final class PlanFacts
{
    /**
     * @return array{ups: ?int, sheet_l: ?float, sheet_w: ?float, gsm: ?int, colours: ?int, dieline: ?string, tier: int, layout: array}
     */
    public function forWo(PrdWo $wo): array
    {
        $sdi = DB::connection('tm2sdi');
        $mst = DB::connection('tm2mst');

        $part = $wo->source_iqp_part_id ? $sdi->table('iqp_part')->where('id_part', $wo->source_iqp_part_id)->first() : null;
        $prodPart = $part?->prod_part_id ? $mst->table('prod_part')->where('id_part', $part->prod_part_id)->first() : null;

        $layout = $part ? $sdi->table('iqp_layout')->where('part_id', $part->id_part)->first() : null;
        $group = null;
        if (! $layout && $prodPart) {
            $group = $mst->table('prod_layout_group as g')
                ->join('prod_layout_group_part as p', 'p.id_layout_group', '=', 'g.id_layout_group')
                ->where('p.part_id', $prodPart->id_part)->orderByDesc('g.id_layout_group')
                ->first(['g.plano_w_mm', 'g.plano_h_mm', 'g.up_count', 'g.knife_code', 'g.layout_detail']);
        }
        $src = $layout ?? $group;
        $detail = ($group?->layout_detail ?? $layout?->layout_detail) ? (json_decode((string) ($group?->layout_detail ?? $layout?->layout_detail), true) ?: []) : [];

        // The press sheet is the canvas of the layout when ProDev drew one, else the plano.
        $sheetL = isset($detail['canvas_l_cm']) ? (float) $detail['canvas_l_cm'] * 10 : ($src?->plano_w_mm ? (float) $src->plano_w_mm : null);
        $sheetW = isset($detail['canvas_w_cm']) ? (float) $detail['canvas_w_cm'] * 10 : ($src?->plano_h_mm ? (float) $src->plano_h_mm : null);

        $gsm = $part ? $sdi->table('iqp_part_material')->where('part_id', $part->id_part)->where('is_primary', 1)->value('gram_weight') : null;
        $cfg = $part->cfg_color ?? $prodPart->cfg_color ?? null;
        $customerId = $wo->source_co_id ? $sdi->table('co_order')->where('id_order', $wo->source_co_id)->value('customer_id') : null;
        $tier = $customerId ? $mst->table('cust_customer')->where('id_customer', $customerId)->value('customer_tier') : null;

        return [
            'ups' => $src?->up_count ? (int) $src->up_count : null,
            'sheet_l' => $sheetL,
            'sheet_w' => $sheetW,
            'gsm' => $gsm ? (int) round((float) $gsm) : null,
            'colours' => $this->colours($cfg),
            'dieline' => $prodPart->dieline_ref ?? $prodPart->die_code ?? $src?->knife_code ?? null,
            'tier' => max(1, min(3, (int) ($tier ?: 2))),
            'layout' => array_filter([
                'plano_w_mm' => $src?->plano_w_mm, 'plano_h_mm' => $src?->plano_h_mm, 'up_count' => $src?->up_count,
                'knife' => $src?->knife_code ?? null, 'part' => $part?->part_name, 'cfg_color' => $cfg,
            ], fn ($v) => $v !== null && $v !== ''),
        ];
    }

    /** "4/0" and "4/4" print 4 colours per pass, "1/0" one. */
    public function colours(?string $cfg): ?int
    {
        return $cfg !== null && preg_match('/^\s*(\d+)/', $cfg, $m) ? (int) $m[1] : null;
    }
}
