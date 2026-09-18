<?php

namespace App\Services\Costing;

use App\Models\Master\MatRaw;
use App\Models\Master\ProdLayoutGroup;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdPartFinishing;
use App\Models\Master\ProdPartProcess;
use App\Models\Master\RefFinishingType;
use App\Models\Master\SupSupplier;
use App\Models\Sdi\IqpCostingPart;
use App\Models\Sdi\IqpPart;

/**
 * Konteks READ-ONLY InqProd + ProDev utk 1 part costing mode Integrasi
 * (arahan user 2026-09-18: "semua informasi dari inquiry product dan prodev
 * harus masuk ke procost sebagai read only ... terutama BOP dan BOM, karena
 * harga ditentukan banyaknya proses, subkon atau tidak, bahan baku, ukuran,
 * merk, ink, dll"). Lintas-DB (tm2sdi <-> tm2mst) = query terpisah, cocokkan
 * di PHP (CLAUDE.md §2 pola 1).
 */
class ProCostContextService
{
    public function forPart(IqpCostingPart $part): array
    {
        $iqpPart = $part->part_id ? IqpPart::with(['bomMaterials', 'processes', 'finishings', 'inks'])->find($part->part_id) : null;
        $prodPartId = $part->prodev_part_id ?: $iqpPart?->prod_part_id;
        $prodPart = $prodPartId ? ProdPart::with(['processes', 'finishings'])->find($prodPartId) : null;
        $group = $part->prod_layout_group_id
            ? ProdLayoutGroup::with(['parts', 'planoSize'])->find($part->prod_layout_group_id)
            : ($prodPart ? $prodPart->layoutGroups()->with(['parts', 'planoSize'])->orderByRaw("status = 'CONFIRMED' desc")->orderByDesc('id_layout_group')->first() : null);

        return [
            'iqpPart' => $iqpPart,
            'prodPart' => $prodPart,
            'group' => $group,
            'geometry' => $group ? $this->layoutGeometry($group, $prodPart) : null,
            'bom' => $this->bom($iqpPart),
            'bop' => $this->bop($prodPart, $iqpPart),
            'inks' => $iqpPart?->inks ?? collect(),
            'finishings' => $this->finishings($iqpPart, $prodPart),
            'artworkUrl' => $iqpPart?->artwork_path ? asset('storage/'.$iqpPart->artwork_path) : null,
        ];
    }

    /** BOM dari Inquiry (+ merk/ukuran dari master mat_raw). */
    private function bom(?IqpPart $iqpPart): array
    {
        if (! $iqpPart) {
            return [];
        }

        $raws = MatRaw::whereIn('material_id', $iqpPart->bomMaterials->pluck('rawmat_id')->filter())->get()->keyBy('material_id');

        return $iqpPart->bomMaterials->map(function ($m) use ($raws) {
            $raw = $raws[$m->rawmat_id] ?? null;

            return [
                'layer' => $m->layer_seq,
                'material' => $m->rawmat_name ?: $raw?->material_name,
                'brand' => $raw?->brand,
                'spec' => trim(($m->gram_weight ? (float) $m->gram_weight.' gsm ' : '').($m->flute_type ?? '')),
                'size' => ($m->size_p || $m->size_l) ? (float) $m->size_p.' × '.(float) $m->size_l : ($raw?->cm_length ? (float) $raw->cm_length.' × '.(float) $raw->cm_width : null),
                'qty' => $m->qty_per_sheet !== null ? (float) $m->qty_per_sheet.' '.($m->uom ?? '') : null,
                'owner' => $m->material_source,
                'source' => $m->source,
                'primary' => (bool) $m->is_primary,
            ];
        })->all();
    }

    /** BOP: routing ProDev (master) bila ada, fallback routing Inquiry. */
    private function bop(?ProdPart $prodPart, ?IqpPart $iqpPart): array
    {
        $rows = $prodPart && $prodPart->processes->isNotEmpty() ? $prodPart->processes : ($iqpPart?->processes ?? collect());
        $origin = $prodPart && $prodPart->processes->isNotEmpty() ? 'ProDev' : 'InqProd';

        $machines = ProdMachine::whereIn('machine_id', $rows->pluck('machine_id')->filter())->get()->keyBy('machine_id');
        $subcons = SupSupplier::whereIn('id_supplier', $rows->pluck('subcon_supplier_id')->filter())->pluck('supplier_name', 'id_supplier');

        return $rows->sortBy(['branch_code', 'step_seq'])->map(fn ($r) => [
            'step' => $r->step_seq,
            'branch' => $r->branch_code,
            'process' => $r->process_name,
            'machine' => $machines[$r->machine_id]->machine_name ?? null,
            'subcon' => $r->subcon_supplier_id ? ($subcons[$r->subcon_supplier_id] ?? 'Subkon #'.$r->subcon_supplier_id) : null,
            'target' => $r->target_qty_per_hour ?? ($machines[$r->machine_id]->max_speed_per_hour ?? null),
            'factor' => $r->qty_factor,
            'waste' => $r->waste_pct,
            'note' => $r->note,
            'origin' => $origin,
        ])->values()->all();
    }

    private function finishings(?IqpPart $iqpPart, ?ProdPart $prodPart): array
    {
        $rows = collect($iqpPart?->finishings ?? [])->map(fn ($f) => ['name' => $f->finishing_name, 'attr' => $f->attribute, 'notes' => $f->notes, 'cost' => null, 'origin' => 'InqProd']);

        if ($prodPart) {
            $names = RefFinishingType::whereIn('id_finishing_type', $prodPart->finishings->pluck('finishing_type_id'))->pluck('finishing_name', 'id_finishing_type');
            $rows = $rows->merge($prodPart->finishings->map(fn (ProdPartFinishing $f) => ['name' => $names[$f->finishing_type_id] ?? '#'.$f->finishing_type_id, 'attr' => $f->attribute, 'notes' => $f->notes, 'cost' => $f->cost_estimate, 'origin' => 'ProDev']));
        }

        return $rows->values()->all();
    }

    /**
     * Geometri cetak dari layout ProDev -> parameter legacy:
     * "1 plano dipotong jadi" (cut) = jumlah lembar cetak per plano,
     * "isi dalam 1 LB" (isi) = UP per lembar cetak, ukuran cetak = print size.
     * 2 format layout_detail: optimizer (kanvas + objects, satuan cm) & engine grid (layout.cols x rows, mm).
     */
    public function layoutGeometry(ProdLayoutGroup $group, ?ProdPart $prodPart = null): array
    {
        $d = $group->layout_detail ?? [];
        $planoL = (float) $group->plano_w_mm / 10;
        $planoW = (float) $group->plano_h_mm / 10;

        if (! empty($d['objects'])) {
            $cl = (float) ($d['canvas_l_cm'] ?? 0);
            $cw = (float) ($d['canvas_w_cm'] ?? 0);
            $isi = max(1, count($d['objects']));
            $cut = ($cl > 0 && $cw > 0) ? max(1, max(floor($planoL / $cl) * floor($planoW / $cw), floor($planoL / $cw) * floor($planoW / $cl))) : 1;
            $printL = $cl;
            $printW = $cw;
        } else {
            $g = $d['layout'] ?? [];
            $isi = max(1, (int) ($g['cols'] ?? 0) * (int) ($g['rows'] ?? 0)) ?: max(1, (int) $group->up_count);
            $cut = 1;
            $printL = isset($g['iw']) ? round((float) $g['iw'] * (int) $g['cols'] / 10, 2) : $planoL;
            $printW = isset($g['ih']) ? round((float) $g['ih'] * (int) $g['rows'] / 10, 2) : $planoW;
        }

        // Group multi-part (Tool #10): alokasi proporsional by luas lewat effectiveCutFor()
        // -- perkalian cut x isi mengikuti "cut efektif", isi tetap UP fisik part ini.
        if ($prodPart && $group->parts->count() > 1) {
            $cut = max(0.0001, $group->effectiveCutFor($prodPart) / $isi);
        }

        return [
            'plano_l' => $planoL, 'plano_w' => $planoW, 'plano_name' => $d['plano_name'] ?? $group->planoSize?->plano_name,
            'print_l' => $printL, 'print_w' => $printW, 'cut' => $cut, 'isi' => $isi,
            'method' => $group->method, 'up' => $group->up_count, 'efficiency' => $group->efficiency_pct, 'waste' => $group->waste_pct,
            'knife' => $group->knife_code, 'knife_size' => $group->knife_w_cm ? (float) $group->knife_w_cm.' × '.(float) $group->knife_h_cm : null,
            'die_meter' => $group->die_rule_meter, 'status' => $group->status, 'mc' => $group->mastercard_code,
            'machine' => $d['machine']['name'] ?? null,
        ];
    }
}
