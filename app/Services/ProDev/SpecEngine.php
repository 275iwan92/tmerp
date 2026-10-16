<?php

/*
 * REMARK
 * Tujuan       : Mesin ProDev: menurunkan BOP dan BOM dari data Inquiry/ProDev; processOfBom menautkan tiap bahan ke proses BOP-nya.
 * Sumber hulu  : iqp_part dan prod_part (Inquiry sync, termasuk join_type_id), mat_join_type, prod_bop_rule, prod_layout_group, master bahan (mat_raw, mat_category).
 * Input        : ProdPart + IqpPart + jumlah order WO (forWorkOrder).
 * Output       : bop[] dan bom[] (nama dari jalur kategori bila material belum dipilih, qty_need dan uom), warnings.
 * Dipakai oleh : WoGenerationService (membuat WO), ProDev canvas, Ppic.
 * Status       : Aktif; engine beku untuk ProDev: jangan ubah alur tanpa izin Jo. Izin Jo 2026-10-16: NEEDS_GLUE juga dari Joint Type GLUE_* (bukan rakitan).
 */

namespace App\Services\ProDev;

use App\Models\Master\ProdMachine;
use App\Models\Master\ProdPart;
use App\Models\Sdi\IqpPart;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Engine spesifikasi produksi: Bill of Process, Bill of Material, Ink
// Configuration (BACKLOG §18). Semua hasil diturunkan dari input Inquiry
// (finishing, bahan, notasi warna, isi per pack, qty order, artwork) +
// keputusan layout ProDev (lembar cetak, plano, mesin cetak, mesin pond, up)
// + aturan admin di prod_bop_rule.
//
// "Engine suggests, officer confirms": generate() tidak menulis apa pun.
// Officer boleh override tiap section di ProDev; override disimpan dengan
// source=OVERRIDE dan tidak pernah ditimpa engine (sampai officer memilih
// "kembali ke hasil engine"). Baris source=MANUAL = data lama/input manual,
// diperlakukan sama: tidak ditimpa.
class SpecEngine
{
    public const SECTIONS = ['bop', 'bom', 'inks'];

    private const PROCESS_INKS = [
        ['K', 'BLACK', '#1a1a1a'],
        ['C', 'CYAN', '#00a3e0'],
        ['M', 'MAGENTA', '#e2338c'],
        ['Y', 'YELLOW', '#f2c500'],
    ];

    private const GLUE_JOINT = '/glue|lem|lock\s*bottom|crash|auto\s*bottom/i';

    private const BASIS_UOM = [
        'PLANO' => 'LBR',
        'SHEET_LENGTH' => 'M',
        'SHEET_AREA' => 'KG',
        'INK_COVERAGE' => 'KG',
        'PER_PCS' => 'KG',
        'PER_PACK' => 'M',
    ];

    /**
     * @param  array  $ctx  sheetL, sheetW, planoL, planoW, sheetsPerPlano, printMachineId,
     *                      diecutMachineId, upCount, orderQty, artworkColors
     * @param  array  $overrides  ['bop' => rows|null, 'bom' => rows|null, 'inks' => rows|null]
     */
    public function generate(ProdPart $part, ?IqpPart $iqp, array $ctx, array $overrides = []): array
    {
        $warnings = ['bop' => [], 'bom' => [], 'ink' => []];
        $finishings = $this->finishings($part, $iqp);
        $materials = $this->inputMaterials($part, $iqp);
        $rules = $this->rules($part);
        $sheetL = max((float) ($ctx['sheetL'] ?? 0), (float) ($ctx['sheetW'] ?? 0));
        $sheetW = min((float) ($ctx['sheetL'] ?? 0), (float) ($ctx['sheetW'] ?? 0));
        $packQty = $iqp?->pack_qty ?: $part->pack_qty;
        $up = max(0, (int) ($ctx['upCount'] ?? 0));
        $orderQty = (int) ($ctx['orderQty'] ?? 0) ?: $this->tierQty($iqp);

        $fig = [
            'sheetL' => $sheetL,
            'sheetW' => $sheetW,
            'planoL' => (float) ($ctx['planoL'] ?? 0) ?: $sheetL,
            'planoW' => (float) ($ctx['planoW'] ?? 0) ?: $sheetW,
            'perPlano' => max(1, (int) ($ctx['sheetsPerPlano'] ?? 1)),
            'up' => $up,
            'orderQty' => $orderQty,
            'packQty' => (int) $packQty,
            'partType' => $part->part_type,
            'planoName' => (string) ($ctx['planoName'] ?? ''),
            'printSheets' => null,
        ];

        if (! $orderQty) {
            $warnings['bom'][] = 'Qty order belum ada (isi di kalkulator PCS → PLANO atau tier Inquiry) -- qty kebutuhan belum bisa dihitung.';
        }
        if (! $packQty) {
            $warnings['bop'][] = 'Isi per pack belum diisi di Inquiry -- langkah Pack tanpa jumlah.';
        }

        $facts = [
            'PLANO_NEEDS_CUT' => $sheetL > 0 && (abs($fig['planoL'] - $sheetL) > 0.5 || abs($fig['planoW'] - $sheetW) > 0.5),
            'MULTI_LAYER' => $materials->count() > 1 || in_array($part->part_type, ['DC+SF', 'DC+SHEET'], true),
            'NEEDS_GLUE' => $this->needsGlue($part, $iqp),
        ];

        $area = $this->blankArea($part, $iqp);
        $ink = $this->inks($part, $iqp, $ctx['artworkColors'] ?? null, $area, $up, $warnings['ink']);
        $inkRows = isset($overrides['inks']) ? $this->normalizeInks($overrides['inks'], $area, $up) : $ink['rows'];

        $bomRows = $this->bom($rules, $facts, $materials, $finishings, $fig, $inkRows, $warnings['bom']);
        $subcon = $this->subconMap($part, $iqp);
        $bop = $this->withSubcon($this->chain($this->bop($part, $rules, $facts, $finishings, $ctx, $packQty, $bomRows, $warnings['bop']), $fig), $subcon);
        $bopOverride = isset($overrides['bop'])
            ? $this->withSubcon($this->chain($this->normalizeBop($part, $overrides['bop']), $fig), $subcon) : null;

        // Lembar cetak yang dibutuhkan = qty masuk langkah pertama BOP (sudah
        // termasuk waste & toleransi semua langkah) -> dasar kebutuhan BOM.
        $fig['printSheets'] = $this->firstSheets($bopOverride ?? $bop);
        $bom = $this->figures($bomRows, $fig);

        return [
            'inputs' => [
                'finishings' => $finishings->map(fn ($f) => $f['name'].($f['attribute'] ? ' ('.$f['attribute'].')' : ''))->values()->all(),
                'color' => $part->cfg_color ?: $iqp?->cfg_color,
                'packQty' => $packQty,
                'orderQty' => $orderQty ?: null,
                'printSheets' => $fig['printSheets'],
                'sheet' => $sheetL && $sheetW ? [$sheetL, $sheetW] : null,
            ],
            'bop' => $bop,
            'bom' => $bom,
            'inks' => $ink['rows'],
            'colors' => $ink['colors'],
            'override' => [
                'bop' => $bopOverride,
                'bom' => isset($overrides['bom']) ? $this->figures($this->normalizeBom($overrides['bom']), $fig) : null,
                'inks' => isset($overrides['inks']) ? $inkRows : null,
            ],
            'warnings' => array_map(fn ($w) => array_values(array_unique($w)), $warnings),
        ];
    }

    // Langkah Lem + bahan LEM BOX: teks joint_type (cara lama) ATAU Joint Type GLUE_* dari Inquiry (cadangan: master part).
    // Rakitan (ASSEMBLY) ditahan: join-nya sambungan antar part anak (Jo 2026-10-16). STITCHING_* tidak melem.
    private function needsGlue(ProdPart $part, ?IqpPart $iqp): bool
    {
        if (preg_match(self::GLUE_JOINT, (string) $part->joint_type)) {
            return true;
        }

        $joinId = $iqp?->join_type_id ?: $part->join_type_id;
        if (! $joinId || ($iqp?->part_kind ?? $part->part_kind) === 'ASSEMBLY') {
            return false;
        }

        $code = DB::connection('tm2mst')->table('mat_join_type')->where('id_join_type', $joinId)->value('code');

        return str_starts_with((string) $code, 'GLUE_');
    }

    // ===================== INPUT =====================

    private function finishings(ProdPart $part, ?IqpPart $iqp): Collection
    {
        if ($iqp) {
            return $iqp->finishings()->get()->map(fn ($f) => [
                'type_id' => (int) $f->finishing_type_id,
                'name' => $f->finishing_name,
                'attribute' => $f->attribute,
            ]);
        }

        // Mode #2 (tanpa Inquiry): finishing yang diisi langsung di master part.
        return DB::connection('tm2mst')->table('prod_part_finishing as pf')
            ->join('ref_finishing_type as t', 't.id_finishing_type', '=', 'pf.finishing_type_id')
            ->where('pf.part_id', $part->id_part)
            ->orderBy('pf.seq')
            ->get(['pf.finishing_type_id', 't.finishing_name', 'pf.attribute'])
            ->map(fn ($f) => [
                'type_id' => (int) $f->finishing_type_id,
                'name' => $f->finishing_name,
                'attribute' => $f->attribute,
            ]);
    }

    // Bahan pilihan Sales (IqpPart::materials() = source MANUAL saja).
    private function inputMaterials(ProdPart $part, ?IqpPart $iqp): Collection
    {
        if ($iqp) {
            return $iqp->materials()->orderByDesc('is_primary')->orderBy('layer_seq')->get()
                ->map(fn ($m) => [
                    'material_id' => $m->rawmat_id,
                    'name' => $m->rawmat_name,
                    'gsm' => $m->gram_weight ?: $this->gsmFromName($m->rawmat_name),
                    'primary' => (bool) $m->is_primary,
                ]);
        }

        return DB::connection('tm2mst')->table('prod_part_material as pm')
            ->leftJoin('mat_raw as m', 'm.material_id', '=', 'pm.material_id')
            ->where('pm.part_id', $part->id_part)
            ->where('pm.source', 'MANUAL')
            ->whereIn('pm.rawmat_type', ['KERTAS PLANO', 'KERTAS', 'SHEET', 'SF'])
            ->orderByDesc('pm.is_primary')->orderBy('pm.layer_seq')
            ->get(['pm.material_id', 'm.material_name', 'pm.gram_weight', 'pm.is_primary'])
            ->map(fn ($m) => [
                'material_id' => $m->material_id,
                'name' => $m->material_name,
                'gsm' => $m->gram_weight ?: $this->gsmFromName($m->material_name),
                'primary' => (bool) $m->is_primary,
            ]);
    }

    private function rules(ProdPart $part): Collection
    {
        return DB::connection('tm2mst')->table('prod_bop_rule as r')
            ->leftJoin('prod_process as p', 'p.id_process', '=', 'r.process_id')
            ->where('r.activate', 1)
            ->where(fn ($q) => $q->whereNull('r.part_type')->orWhere('r.part_type', $part->part_type))
            ->orderBy('r.step_seq')->orderBy('r.id_bop_rule')
            ->get(['r.*', 'p.process_name']);
    }

    public function tierQty(?IqpPart $iqp): int
    {
        if (! $iqp) {
            return 0;
        }

        return (int) DB::connection('tm2sdi')->table('iqp_tier')
            ->where('inquiry_id', $iqp->inquiry_id)
            ->orderByDesc('is_primary')->orderBy('tier_seq')
            ->value('qty');
    }

    private function blankArea(ProdPart $part, ?IqpPart $iqp): float
    {
        // Hasil = cm2. die_*_cm sudah cm; flat_w/flat_h Inquiry = milimeter -> mm2 dibagi 100.
        return ((float) $part->die_w_cm * (float) $part->die_h_cm) ?: ((float) $iqp?->flat_w * (float) $iqp?->flat_h / 100);
    }

    private function gsmFromName(?string $name): ?float
    {
        return $name && preg_match('/(\d{2,4})\s*(?:GR|GSM|G\b)/i', $name, $m) ? (float) $m[1] : null;
    }

    private function applies(object $r, array $facts): bool
    {
        return ! $r->condition_code || ! empty($facts[$r->condition_code]);
    }

    // ===================== BILL OF PROCESS =====================

    private function bop(ProdPart $part, Collection $rules, array $facts, Collection $finishings, array $ctx, $packQty, array $bom, array &$warnings): array
    {
        $machines = ProdMachine::whereIn('machine_id', array_filter([
            $ctx['printMachineId'] ?? null, $ctx['diecutMachineId'] ?? null,
        ]))->pluck('machine_name', 'machine_id');

        $rolls = collect($bom)->whereNotNull('roll_cm')->keyBy('finishing_type_id');
        $steps = [];

        foreach ($rules->whereNotNull('process_id') as $r) {
            if ($r->rule_scope === 'STANDARD') {
                if ($this->applies($r, $facts)) {
                    $steps[] = $this->step($r, null, $ctx, $machines, $packQty, null);
                }

                continue;
            }

            foreach ($finishings->where('type_id', (int) $r->finishing_type_id) as $fin) {
                $steps[] = $this->step($r, $fin['attribute'], $ctx, $machines, $packQty,
                    $rolls->get((int) $r->finishing_type_id)['roll_cm'] ?? null);
            }
        }

        $ruled = $rules->where('rule_scope', 'FINISHING')->whereNotNull('process_id')
            ->pluck('finishing_type_id')->map(fn ($v) => (int) $v)->all();
        foreach ($finishings as $fin) {
            if (! in_array($fin['type_id'], $ruled, true)) {
                $warnings[] = "Finishing \"{$fin['name']}\" belum punya aturan proses -- tambahkan di Setting Aturan BOP.";
            }
        }

        // Langkah bersebelahan dengan merge_group sama digabung jadi 1 baris
        // (mis. "Plong Depan + Emboss"). Tidak bersebelahan = tetap terpisah.
        $out = [];
        foreach ($steps as $s) {
            $prev = $out ? $out[count($out) - 1] : null;
            if ($prev && $s['merge_group'] && $prev['merge_group'] === $s['merge_group']) {
                $k = count($out) - 1;
                $out[$k]['label'] .= ' '.($s['merge_label'] ?: '+ '.$s['label']);
                $out[$k]['merged'][] = $s['process_name'];
                // Dikerjakan dalam satu jalan mesin -> waste & toleransi dijumlah.
                $out[$k]['waste_pct'] = (float) $out[$k]['waste_pct'] + (float) $s['waste_pct'];
                $out[$k]['tolerance_pct'] = (float) $out[$k]['tolerance_pct'] + (float) $s['tolerance_pct'];

                continue;
            }
            $out[] = $s;
        }

        return $this->decorate($part, array_map(fn ($s) => [
            'process_id' => $s['process_id'],
            'label' => $s['label'],
            'machine_id' => $s['machine_id'],
            'machine' => $s['machine'],
            'waste_pct' => $s['waste_pct'] !== null ? (float) $s['waste_pct'] : null,
            'tolerance_pct' => $s['tolerance_pct'] !== null ? (float) $s['tolerance_pct'] : null,
            'target_qty_per_hour' => null,
            'note' => $s['merged'] ? 'Gabungan: '.implode(' + ', array_merge([$s['process_name']], $s['merged'])) : null,
        ], $out));
    }

    // Nama proses master, status in-house (tombol Subkon), dan target default
    // (qty/jam): Target Rate Proses x Mesin part ini -> kecepatan maks mesin.
    private function decorate(ProdPart $part, array $rows): array
    {
        $procs = DB::connection('tm2mst')->table('prod_process')
            ->whereIn('id_process', array_filter(array_column($rows, 'process_id')))
            ->get(['id_process', 'process_name', 'inhouse'])->keyBy('id_process');
        $speeds = ProdMachine::whereIn('machine_id', array_filter(array_column($rows, 'machine_id')))->pluck('max_speed_per_hour', 'machine_id');
        $rates = DB::connection('tm2mst')->table('prod_part_process_machine_rate')
            ->where('product_id', $part->product_id)->where('part_code', $part->part_code)->where('activate', 1)
            ->get(['process_id', 'machine_id', 'target_qty_per_hour']);

        return array_map(function ($r) use ($procs, $speeds, $rates) {
            $p = $r['process_id'] ? $procs->get($r['process_id']) : null;
            $target = $r['target_qty_per_hour'] ?? null;
            $targetSrc = $target !== null ? 'officer' : null;

            if ($target === null && $r['process_id']) {
                $hit = $rates->first(fn ($x) => (int) $x->process_id === (int) $r['process_id']
                    && (! $r['machine_id'] || (int) $x->machine_id === (int) $r['machine_id']));
                if ($hit) {
                    [$target, $targetSrc] = [(float) $hit->target_qty_per_hour, 'target rate'];
                } elseif ($r['machine_id'] && ($speeds[$r['machine_id']] ?? null)) {
                    [$target, $targetSrc] = [(float) $speeds[$r['machine_id']], 'kecepatan maks mesin'];
                }
            }

            return array_merge($r, [
                'process_name' => $p->process_name ?? null,
                'inhouse' => $p ? (bool) $p->inhouse : true,
                'is_diecut' => $p && str_starts_with(strtoupper($p->process_name), 'PLONG'),
                'target_qty_per_hour' => $target,
                'target_source' => $targetSrc,
            ]);
        }, $rows);
    }

    // Qty dihitung MUNDUR dari qty order: tiap langkah harus menerima
    // keluaran langkah berikutnya + waste + toleransinya. Langkah sampai Plong
    // dihitung dalam lembar (pcs / up), sesudahnya dalam pcs.
    private function chain(array $rows, array $fig): array
    {
        if (! $rows) {
            return $rows;
        }

        $cut = null;
        foreach ($rows as $i => $r) {
            if ($r['is_diecut']) {
                $cut = $i;
            }
        }
        $cut ??= count($rows) - 1;

        $need = $fig['orderQty'] ?: null;
        $up = $fig['up'] ?: null;

        for ($i = count($rows) - 1; $i >= 0; $i--) {
            $sheet = $i <= $cut;
            $unit = $sheet ? 'LBR' : 'PCS';
            $out = $need === null ? null : ($sheet ? ($up ? ceil($need / $up) : null) : $need);
            $w = (float) ($rows[$i]['waste_pct'] ?? 0);
            $t = (float) ($rows[$i]['tolerance_pct'] ?? 0);
            $qw = $out !== null ? ceil($out * $w / 100) : null;
            $qt = $out !== null ? ceil($out * $t / 100) : null;
            $in = $out !== null ? $out + $qw + $qt : null;

            $rows[$i] += [
                'uom' => $unit,
                'qty_out' => $out,
                'qty_waste' => $qw,
                'qty_tolerance' => $qt,
                'qty_in' => $in,
                'est_hours' => $in && ! empty($rows[$i]['target_qty_per_hour'])
                    ? round($in / (float) $rows[$i]['target_qty_per_hour'], 1) : null,
            ];

            // Kembali ke pcs untuk langkah sebelumnya.
            $need = $in === null ? null : ($sheet ? $in * ($up ?: 1) : $in);
        }

        return $rows;
    }

    // Rencana & dokumen subkon yang sudah ada, per proses. Sisi Inquiry
    // didahulukan karena di sanalah PR & surat jalan tercatat.
    private function subconMap(ProdPart $part, ?IqpPart $iqp): array
    {
        $rows = $iqp
            ? DB::connection('tm2sdi')->table('iqp_part_process')->where('part_id', $iqp->id_part)
                ->where(fn ($q) => $q->whereNotNull('subcon_supplier_id')->orWhereNotNull('subcon_pr_id'))
                ->get(['process_id', 'subcon_supplier_id', 'subcon_pr_id', 'subcon_do_id'])
            : DB::connection('tm2mst')->table('prod_part_process')->where('part_id', $part->id_part)
                ->whereNotNull('subcon_supplier_id')->get(['process_id', 'subcon_supplier_id']);

        if ($rows->isEmpty()) {
            return [];
        }

        $suppliers = DB::connection('tm2mst')->table('sup_supplier')->whereIn('id_supplier', $rows->pluck('subcon_supplier_id')->filter())->pluck('supplier_name', 'id_supplier');
        $prs = DB::connection('tm2pur')->table('pr_requisition')->whereIn('pr_id', $rows->pluck('subcon_pr_id')->filter())->get(['pr_id', 'doc_no', 'status'])->keyBy('pr_id');
        $dos = DB::connection('tm2sdi')->table('do_delivery')->whereIn('id_delivery', $rows->pluck('subcon_do_id')->filter())->get(['id_delivery', 'do_no', 'status'])->keyBy('id_delivery');

        $map = [];
        foreach ($rows as $r) {
            $pr = isset($r->subcon_pr_id) ? $prs->get($r->subcon_pr_id) : null;
            $do = isset($r->subcon_do_id) ? $dos->get($r->subcon_do_id) : null;
            $map[(int) $r->process_id] = [
                'subcon_supplier_id' => $r->subcon_supplier_id,
                'subcon_supplier' => $suppliers[$r->subcon_supplier_id] ?? null,
                'subcon_pr_id' => $pr?->pr_id,
                'subcon_pr' => $pr ? ($pr->doc_no ?: 'PR draft #'.$pr->pr_id).' · '.$pr->status : null,
                'subcon_do_id' => $do?->id_delivery,
                'subcon_do' => $do ? ($do->do_no ?: 'SJ draft #'.$do->id_delivery).' · '.$do->status : null,
            ];
        }

        return $map;
    }

    private function withSubcon(array $rows, array $map): array
    {
        $blank = ['subcon_supplier_id' => null, 'subcon_supplier' => null, 'subcon_pr_id' => null,
            'subcon_pr' => null, 'subcon_do_id' => null, 'subcon_do' => null];

        return array_map(function ($r) use ($map, $blank) {
            $known = $map[(int) $r['process_id']] ?? $blank;
            // Supplier pilihan officer (override) mengalahkan yang tersimpan.
            if (! empty($r['subcon_supplier_id']) && (int) $r['subcon_supplier_id'] !== (int) $known['subcon_supplier_id']) {
                $known['subcon_supplier_id'] = (int) $r['subcon_supplier_id'];
                $known['subcon_supplier'] = DB::connection('tm2mst')->table('sup_supplier')->where('id_supplier', $known['subcon_supplier_id'])->value('supplier_name');
            }

            return array_merge($r, $known);
        }, $rows);
    }

    private function firstSheets(array $bop): ?int
    {
        foreach ($bop as $r) {
            if (($r['uom'] ?? null) === 'LBR' && $r['qty_in'] !== null) {
                return (int) $r['qty_in'];
            }
        }

        return null;
    }

    private function step(object $r, ?string $attr, array $ctx, Collection $machines, $packQty, $roll): array
    {
        $machineId = match ($r->machine_role) {
            'PRINT' => $ctx['printMachineId'] ?? null,
            'DIECUT' => $ctx['diecutMachineId'] ?? null,
            default => null,
        };
        $machine = $machineId ? ($machines[$machineId] ?? null) : null;

        $label = (string) ($r->step_label ?: $r->process_name);
        $label = $packQty ? str_replace('{pack}', (string) $packQty, $label) : preg_replace('/\s*\/?\{pack\}\s*pcs/i', '', $label);
        $label = $roll ? str_replace('{roll}', $this->num($roll), $label)
            : preg_replace('/\s*\([^)]*\{roll\}[^)]*\)/', '', $label);
        $label = str_replace(['{machine}', '{attr}'], [$machine ?? '(pilih mesin)', (string) $attr], $label);

        return [
            'process_id' => (int) $r->process_id,
            'process_name' => $r->process_name,
            'label' => trim(preg_replace('/\s{2,}/', ' ', $label)),
            'merge_group' => $r->merge_group,
            'merge_label' => $r->merge_label,
            'machine_id' => $machineId ? (int) $machineId : null,
            'machine' => $machine,
            'waste_pct' => $r->waste_pct,
            'tolerance_pct' => $r->tolerance_pct,
            'merged' => [],
        ];
    }

    private function normalizeBop(ProdPart $part, array $rows): array
    {
        $machines = ProdMachine::pluck('machine_name', 'machine_id');
        $pct = fn ($v) => is_numeric($v) ? max(0, min(100, (float) $v)) : null;

        return $this->decorate($part, collect($rows)
            ->filter(fn ($r) => ! empty($r['process_id']) || trim((string) ($r['label'] ?? '')) !== '')
            ->map(fn ($r) => [
                'process_id' => ! empty($r['process_id']) ? (int) $r['process_id'] : null,
                'label' => mb_substr(trim((string) ($r['label'] ?? '')), 0, 80),
                'machine_id' => ! empty($r['machine_id']) ? (int) $r['machine_id'] : null,
                'machine' => ! empty($r['machine_id']) ? ($machines[(int) $r['machine_id']] ?? null) : null,
                'waste_pct' => $pct($r['waste_pct'] ?? null),
                'tolerance_pct' => $pct($r['tolerance_pct'] ?? null),
                'target_qty_per_hour' => is_numeric($r['target_qty_per_hour'] ?? null) && $r['target_qty_per_hour'] > 0
                    ? (float) $r['target_qty_per_hour'] : null,
                'subcon_supplier_id' => ! empty($r['subcon_supplier_id']) ? (int) $r['subcon_supplier_id'] : null,
                'note' => ($n = trim((string) ($r['note'] ?? ''))) !== '' ? mb_substr($n, 0, 255) : null,
            ])
            ->values()->all());
    }

    // ===================== BILL OF MATERIAL =====================

    private function bom(Collection $rules, array $facts, Collection $materials, Collection $finishings,
        array $fig, array $inkRows, array &$warnings): array
    {
        $rows = [];

        // Baris KERTAS PLANO & SHEET/SF SELALU ada (user 2026-09-17): ukuran &
        // jumlah plano = keputusan officer di Plano Configuration; SHEET/SF
        // yang tidak dipakai tetap tampil sebagai "None".
        $brands = $this->brands($materials->pluck('material_id')->filter()->all());
        $plano = trim(($fig['planoName'] ?? '') ?: ($fig['planoL'] ? $this->num($fig['planoL']).' x '.$this->num($fig['planoW']).' cm' : ''));
        $planoNote = 'Plano '.($plano ?: '—').($fig['perPlano'] > 1 ? " · 1 plano = {$fig['perPlano']} lembar cetak" : ' · 1 plano = 1 lembar cetak');
        $paper = $materials->first();

        if (! $paper) {
            $warnings[] = 'Bahan kertas belum dipilih di Inquiry -- baris KERTAS PLANO belum punya item.';
        }

        $rows[] = $this->bomRow([
            'role' => 'KERTAS PLANO',
            'material_id' => $paper['material_id'] ?? null,
            'name' => $paper['name'] ?? '(kertas belum dipilih di Inquiry)',
            'brand' => $paper ? ($brands[$paper['material_id']] ?? null) : null,
            'gsm' => $paper['gsm'] ?? null,
            'size_p' => $fig['planoL'] ?: null,
            'size_l' => $fig['planoW'] ?: null,
            'basis' => 'PLANO',
            'primary' => true,
            'note' => $planoNote,
        ]);

        $layers = $materials->slice(1)->values();
        $layerRole = $fig['partType'] === 'DC+SF' ? 'SF' : 'SHEET';

        foreach ($layers as $m) {
            $rows[] = $this->bomRow([
                'role' => $layerRole,
                'material_id' => $m['material_id'],
                'name' => $m['name'],
                'brand' => $brands[$m['material_id']] ?? null,
                'gsm' => $m['gsm'],
                'size_p' => $fig['planoL'] ?: null,
                'size_l' => $fig['planoW'] ?: null,
                'basis' => 'PLANO',
                'note' => $planoNote,
            ]);
        }

        if ($layers->isEmpty()) {
            $rows[] = $this->bomRow(['role' => 'SHEET / SF', 'name' => 'None', 'note' => 'Tidak memakai sheet / SF']);
        }

        foreach ($rules->whereNotNull('material_pattern') as $r) {
            $targets = $r->rule_scope === 'STANDARD'
                ? ($this->applies($r, $facts) ? [null] : [])
                : $finishings->where('type_id', (int) $r->finishing_type_id)->values()->all();

            foreach ($targets as $fin) {
                if ($r->material_rule === 'INK_BY_NAME') {
                    foreach ($inkRows as $ink) {
                        $patterns = str_replace('{ink}', $ink['ink_name'], $r->material_pattern);
                        $pick = $this->bestStock($this->materialCandidates($patterns));
                        $rows[] = $this->bomRow([
                            'role' => 'TINTA',
                            'material_id' => $pick['material_id'] ?? null,
                            'name' => $pick['material_name'] ?? 'TINTA '.$ink['ink_name'].' (belum ada di Master Bahan)',
                            'basis' => $r->usage_basis,
                            'rate' => $r->usage_rate,
                            'coverage_cm2_sheet' => $ink['coverage_cm2_sheet'],
                            'note' => 'Unit '.$ink['unit_no'].' · '.$ink['ink_name'].$this->rateNote($r),
                        ]);
                    }

                    continue;
                }

                $row = $this->pickMaterial($r, $fin, $fig, $warnings);
                if ($row) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    private function pickMaterial(object $r, ?array $fin, array $fig, array &$warnings): ?array
    {
        $role = $r->material_role ?: strtoupper($fin['name'] ?? 'BAHAN');
        $candidates = $this->materialCandidates($r->material_pattern);
        $pick = null;
        $roll = null;
        $note = null;

        if ($r->material_rule === 'ROLL_WIDTH_GE_SHEET') {
            $pick = $candidates
                ->map(fn ($c) => $c + ['width' => $this->rollWidth($c['material_name'])])
                ->filter(fn ($c) => $c['width'] && $c['width'] >= $fig['sheetL'])
                ->sortBy('width')->first();
            $roll = $pick['width'] ?? null;
            $note = $pick ? "Lebar roll {$this->num($roll)} cm >= lembar {$this->num($fig['sheetL'])} cm" : null;
        } elseif ($r->material_rule === 'MATCH_ATTRIBUTE') {
            // Mis. "Gold Puyeh" -> FOIL HOT PRINT GOLD.
            $words = collect(preg_split('/[\s,\/]+/', strtoupper((string) ($fin['attribute'] ?? ''))))->filter(fn ($w) => strlen($w) >= 3);
            $pick = $candidates
                ->map(fn ($c) => $c + ['score' => $words->filter(fn ($w) => str_contains(strtoupper($c['material_name']), $w))->count()])
                ->sortByDesc('score')->first();
            if ($pick && $words->isNotEmpty() && $pick['score'] === 0) {
                $warnings[] = "Bahan {$role} \"{$fin['attribute']}\" tidak ditemukan persis -- dipilih {$pick['material_name']}, mohon dicek.";
            }
        } else {
            $pick = $this->bestStock($candidates);
        }

        if (! $pick) {
            $warnings[] = "Bahan {$role} tidak ditemukan di Master Bahan (pola: {$r->material_pattern}).";

            return $this->bomRow([
                'role' => $role, 'name' => $role.' (belum ada di Master Bahan)',
                'basis' => $r->usage_basis, 'rate' => $r->usage_rate,
                'finishing_type_id' => $fin['type_id'] ?? null, 'note' => $this->rateNote($r, true),
            ]);
        }

        return $this->bomRow([
            'role' => $role,
            'material_id' => $pick['material_id'],
            'name' => $pick['material_name'],
            'brand' => $this->brands([$pick['material_id']])[$pick['material_id']] ?? null,
            'size_p' => $roll,
            'basis' => $r->usage_basis,
            'rate' => $r->usage_rate,
            'finishing_type_id' => $fin['type_id'] ?? null,
            'roll_cm' => $roll,
            'note' => implode(' · ', array_filter([$note, ltrim($this->rateNote($r), ' ·')])) ?: null,
        ]);
    }

    private function rateNote(object $r, bool $missing = false): string
    {
        return match ($r->usage_basis) {
            'SHEET_AREA' => " · {$this->num($r->usage_rate)} g/m² lembar",
            'INK_COVERAGE' => " · {$this->num($r->usage_rate)} g/m² tutupan",
            'PER_PCS' => " · {$this->num($r->usage_rate)} g/pcs",
            'PER_PACK' => " · {$this->num($r->usage_rate)} m/pack",
            'AREA_UNKNOWN' => ' · luas pakai belum bisa dihitung, isi manual',
            default => $missing ? '' : '',
        };
    }

    private function bomRow(array $r): array
    {
        return $r + [
            'role' => null, 'material_id' => null, 'name' => null, 'brand' => null, 'gsm' => null,
            'size_p' => null, 'size_l' => null, 'basis' => null, 'rate' => null,
            'qty_per_sheet' => null, 'uom' => self::BASIS_UOM[$r['basis'] ?? ''] ?? null,
            'primary' => false, 'finishing_type_id' => null, 'roll_cm' => null,
            'coverage_cm2_sheet' => null, 'note' => null,
        ];
    }

    private function normalizeBom(array $rows): array
    {
        return collect($rows)
            ->filter(fn ($r) => ! empty($r['material_id']) || trim((string) ($r['name'] ?? '')) !== '')
            ->map(fn ($r) => $this->bomRow([
                'role' => mb_substr(strtoupper(trim((string) ($r['role'] ?? ''))) ?: 'BAHAN', 0, 20),
                'material_id' => ! empty($r['material_id']) ? (int) $r['material_id'] : null,
                'name' => mb_substr(trim((string) ($r['name'] ?? '')), 0, 200),
                'brand' => ($b = trim((string) ($r['brand'] ?? ''))) !== '' ? mb_substr($b, 0, 60) : null,
                'gsm' => is_numeric($r['gsm'] ?? null) ? (float) $r['gsm'] : null,
                'size_p' => is_numeric($r['size_p'] ?? null) ? (float) $r['size_p'] : null,
                'size_l' => is_numeric($r['size_l'] ?? null) ? (float) $r['size_l'] : null,
                'qty_per_sheet' => is_numeric($r['qty_per_sheet'] ?? null) ? (float) $r['qty_per_sheet'] : null,
                'uom' => mb_substr(strtoupper(trim((string) ($r['uom'] ?? ''))), 0, 10) ?: null,
                'basis' => $r['basis'] ?? (strtoupper((string) ($r['role'] ?? '')) === 'KERTAS PLANO' ? 'PLANO' : null),
                'rate' => is_numeric($r['rate'] ?? null) ? (float) $r['rate'] : null,
                'primary' => ! empty($r['primary']),
                'note' => ($n = trim((string) ($r['note'] ?? ''))) !== '' ? mb_substr($n, 0, 255) : null,
            ]))
            ->values()->all();
    }

    // Qty per lembar cetak, qty kebutuhan order, dan ketersediaan stok gudang.
    // Override officer: qty per lembar yang diketik officer dipakai apa adanya.
    private function figures(array $rows, array $fig): array
    {
        $ids = array_filter(array_column($rows, 'material_id'));
        $stock = $this->stockFor($ids);
        $brands = $this->brands($ids);

        return array_map(function ($r) use ($fig, $stock, $brands) {
            $r['brand'] ??= $r['material_id'] ? ($brands[$r['material_id']] ?? null) : null;

            if ($r['name'] === 'None') {
                return array_merge($r, ['qty_per_sheet' => null, 'qty_need' => null, 'stock_available' => null,
                    'stock_uom' => null, 'qty_need_stock_uom' => null, 'shortage' => null, 'stock_status' => null]);
            }

            $per = $r['qty_per_sheet'] ?? match ($r['basis']) {
                'PLANO' => 1 / $fig['perPlano'],
                'SHEET_LENGTH' => $fig['sheetW'] / 100 * ((float) ($r['rate'] ?? 1) ?: 1),
                'SHEET_AREA' => $r['rate'] !== null ? $fig['sheetL'] * $fig['sheetW'] / 10000 * $r['rate'] / 1000 : null,
                'INK_COVERAGE' => $r['rate'] !== null && $r['coverage_cm2_sheet'] !== null
                    ? $r['coverage_cm2_sheet'] / 10000 * $r['rate'] / 1000 : null,
                'PER_PCS' => $r['rate'] !== null && $fig['up'] ? $r['rate'] * $fig['up'] / 1000 : null,
                'PER_PACK' => $r['rate'] !== null && $fig['up'] && $fig['packQty'] ? $r['rate'] * $fig['up'] / $fig['packQty'] : null,
                default => null,
            };

            $need = $per !== null && $fig['printSheets'] ? $per * $fig['printSheets'] : null;
            // Bahan pack dihitung dari jumlah pack order, bukan lembar cetak.
            if ($r['basis'] === 'PER_PACK') {
                $need = $r['rate'] !== null && $fig['orderQty'] && $fig['packQty']
                    ? ceil($fig['orderQty'] / $fig['packQty']) * $r['rate'] : null;
            }
            if ($need !== null && $r['uom'] === 'LBR') {
                $need = ceil($need);
            }

            $s = $r['material_id'] ? ($stock[$r['material_id']] ?? null) : null;
            $avail = $s['qty'] ?? null;
            $stockUom = $s['uom'] ?? null;
            $needStock = $this->toStockUom($need, $r['uom'], $stockUom, (string) $r['name']);

            $status = match (true) {
                $need === null => null,
                ! $r['material_id'] => 'BELUM ADA ITEM',
                $avail === null || $avail <= 0 => 'TIDAK ADA',
                $needStock === null => 'CEK SATUAN',
                $avail >= $needStock => 'CUKUP',
                default => 'KURANG',
            };

            return array_merge($r, [
                'qty_per_sheet' => $per !== null ? round($per, 6) : null,
                'qty_need' => $need !== null ? round($need, 4) : null,
                'stock_available' => $avail,
                'stock_uom' => $stockUom,
                'qty_need_stock_uom' => $needStock,
                'shortage' => in_array($status, ['KURANG', 'TIDAK ADA'], true) && $needStock !== null
                    ? round($needStock - max(0, (float) $avail), 4) : null,
                'stock_status' => $status,
            ]);
        }, $rows);
    }

    private function toStockUom(?float $qty, ?string $uom, ?string $stockUom, string $name = ''): ?float
    {
        if ($qty === null || ! $uom || ! $stockUom) {
            return null;
        }

        $uom = strtoupper($uom);
        $stockUom = strtoupper($stockUom);

        if ($uom === $stockUom) {
            return round($qty, 4);
        }

        $rim = (float) (DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'RIM_SHEETS')->value('constant_number') ?: 500);

        // Roll: panjang roll diambil dari nama item ("85X400000 CM" = 4.000 m).
        if ($uom === 'M' && $stockUom === 'ROLL') {
            if (preg_match('/\d+(?:[.,]\d+)?\s*X\s*(\d{4,})\s*CM/i', $name, $m)) {
                return round($qty / ((float) $m[1] / 100), 4);
            }
            if (preg_match('/X\s*(\d+(?:[.,]\d+)?)\s*M\b/i', $name, $m)) {
                return round($qty / (float) str_replace(',', '.', $m[1]), 4);
            }
        }

        return match ("{$uom}>{$stockUom}") {
            'LBR>RIM' => round($qty / $rim, 4),
            'KG>G', 'KG>GR' => round($qty * 1000, 4),
            'M>CM' => round($qty * 100, 4),
            default => null,
        };
    }

    /** @return array<int, array{qty: float, uom: ?string}> */
    public function stockFor(array $materialIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $materialIds)));
        if (! $ids) {
            return [];
        }

        $qty = DB::connection('tm2inv')->table('v_rm_available')
            ->whereIn('material_id', $ids)->where('is_usable', 1)
            ->groupBy('material_id')
            ->selectRaw('material_id, SUM(qty_available) as qty')
            ->pluck('qty', 'material_id');

        $uoms = DB::connection('tm2mst')->table('mat_raw')->whereIn('material_id', $ids)->pluck('uom_stock', 'material_id');

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ['qty' => isset($qty[$id]) ? (float) $qty[$id] : 0.0, 'uom' => $uoms[$id] ?? null];
        }

        return $out;
    }

    // Merek = kolom brand item, atau kategori level 5 (mis. "IKA DP").
    private function brands(array $ids): array
    {
        return $ids ? DB::connection('tm2mst')->table('mat_raw')->whereIn('material_id', $ids)
            ->get(['material_id', 'brand', 'category5'])
            ->mapWithKeys(fn ($r) => [$r->material_id => ($r->brand ?: $r->category5) ?: null])->all() : [];
    }

    private function materialCandidates(string $patterns): Collection
    {
        return DB::connection('tm2mst')->table('mat_raw')
            ->where('activate', 1)
            ->where(function ($q) use ($patterns) {
                foreach (explode('|', $patterns) as $p) {
                    $q->orWhere('material_name', 'like', trim($p));
                }
            })
            ->orderBy('material_id')
            ->limit(200)
            ->get(['material_id', 'material_name', 'uom_stock'])
            ->map(fn ($r) => (array) $r);
    }

    // Kandidat dengan stok tersedia terbanyak; seri -> id terkecil.
    private function bestStock(Collection $candidates): ?array
    {
        if ($candidates->isEmpty()) {
            return null;
        }

        $stock = $this->stockFor($candidates->pluck('material_id')->all());

        return $candidates->sortBy([
            fn ($a, $b) => ($stock[$b['material_id']]['qty'] ?? 0) <=> ($stock[$a['material_id']]['qty'] ?? 0),
            fn ($a, $b) => $a['material_id'] <=> $b['material_id'],
        ])->first();
    }

    // "PLASTIKLAMDOFF 0.17 85X400000 CM" -> 85
    private function rollWidth(string $name): ?float
    {
        return preg_match('/(\d+(?:[.,]\d+)?)\s*X\s*\d{4,}/i', $name, $m) ? (float) str_replace(',', '.', $m[1]) : null;
    }

    private function num($v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }

    // ===================== INK CONFIGURATION =====================

    private function inks(ProdPart $part, ?IqpPart $iqp, ?array $art, float $area, int $up, array &$warnings): array
    {
        $notation = $part->cfg_color ?: $iqp?->cfg_color;
        $art = $art ?: $iqp?->artwork_colors;
        $channels = $art['channels'] ?? null;
        $rows = [];

        $colors = collect($art['colors'] ?? [])->map(fn ($c) => $c + [
            'cm2' => $area ? round($c['pct'] / 100 * $area, 1) : null,
        ])->values()->all();

        if (! $notation || ! preg_match('/^(\d)\s*\/\s*(\d)$/', $notation, $m)) {
            $warnings[] = 'Notasi warna (mis. 4/0) belum diisi -- Ink Configuration tidak bisa dibuat.';

            return ['rows' => [], 'colors' => $colors];
        }

        if (! $channels) {
            $warnings[] = 'Artwork belum dianalisa -- luas tutupan tinta belum ada.';
        }

        $spots = collect(preg_split('/[,;]+/', (string) ($part->cfg_ink ?: $iqp?->cfg_ink)))->map(fn ($s) => trim($s))->filter()->values();
        $dominant = collect($art['colors'] ?? [])->reject(fn ($c) => in_array($c['name'] ?? '', ['Putih', 'Hitam', 'Abu-abu'], true))->values();

        foreach ([1 => (int) $m[1], 2 => (int) $m[2]] as $pass => $n) {
            if ($n === 0) {
                continue;
            }

            // Tutupan hanya untuk sisi depan: artwork yang diupload = sisi cetak depan.
            $cov = $pass === 1 ? $channels : null;
            $unit = 0;

            if ($n >= 4) {
                foreach (self::PROCESS_INKS as [$ch, $name, $hex]) {
                    $rows[] = $this->inkRow($pass, 'CS', ++$unit, $name, $hex, $cov[$ch] ?? null, $area, $up);
                }
            } else {
                $rows[] = $this->inkRow($pass, 'SPOT', ++$unit, 'BLACK', '#1a1a1a', $cov['K'] ?? null, $area, $up);
            }

            for ($i = 0; $unit < $n; $i++) {
                $spot = $spots[$i] ?? null;
                $dom = $dominant[$i] ?? null;
                $rows[] = $this->inkRow($pass, 'SPOT', ++$unit,
                    strtoupper($spot ?: ($dom['name'] ?? 'WARNA KHUSUS')), $dom['hex'] ?? null,
                    $pass === 1 ? ($dom['pct'] ?? null) : null, $area, $up);
            }
        }

        return ['rows' => $rows, 'colors' => $colors];
    }

    private function inkRow(int $pass, string $label, int $unit, string $name, ?string $hex, $pct, float $area, int $up): array
    {
        $cm2 = $pct !== null && $pct !== '' && $area ? round((float) $pct / 100 * $area, 2) : null;

        return [
            'pass_seq' => $pass,
            'pass_label' => mb_substr($label, 0, 5),
            'unit_no' => $unit,
            'ink_name' => mb_substr($name, 0, 40),
            'color_hex' => $hex,
            'coverage_pct' => $pct !== null && $pct !== '' ? round((float) $pct, 2) : null,
            'coverage_cm2' => $cm2,
            'coverage_cm2_sheet' => $cm2 !== null && $up ? round($cm2 * $up, 1) : null,
        ];
    }

    private function normalizeInks(array $rows, float $area, int $up): array
    {
        return collect($rows)
            ->filter(fn ($r) => trim((string) ($r['ink_name'] ?? '')) !== '')
            ->map(fn ($r) => $this->inkRow(
                in_array((int) ($r['pass_seq'] ?? 1), [1, 2], true) ? (int) $r['pass_seq'] : 1,
                strtoupper(trim((string) ($r['pass_label'] ?? 'CS'))) ?: 'CS',
                max(1, min(12, (int) ($r['unit_no'] ?? 1))),
                strtoupper(trim((string) $r['ink_name'])),
                preg_match('/^#[0-9a-f]{6}$/i', (string) ($r['color_hex'] ?? '')) ? strtolower($r['color_hex']) : null,
                is_numeric($r['coverage_pct'] ?? null) ? max(0, min(100, (float) $r['coverage_pct'])) : null,
                $area, $up,
            ))
            // Satu slot unit per pass (sama dgn unique key prod_part_ink).
            ->unique(fn ($r) => $r['pass_seq'].'-'.$r['unit_no'])
            ->values()->all();
    }

    // ===================== DATA TERSIMPAN (override / manual) =====================

    // Baris selain ENGINE yang sudah tersimpan di master part, per section
    // (null = section itu masih mengikuti engine).
    public function saved(ProdPart $part): array
    {
        $mst = DB::connection('tm2mst');

        $bop = $mst->table('prod_part_process as pp')
            ->leftJoin('prod_process as p', 'p.id_process', '=', 'pp.process_id')
            ->leftJoin('prod_machine as m', 'm.machine_id', '=', 'pp.machine_id')
            ->where('pp.part_id', $part->id_part)->where('pp.source', '<>', 'ENGINE')
            ->orderBy('pp.step_seq')
            ->get(['pp.process_id', 'pp.process_name', 'p.process_name as master_name', 'pp.machine_id', 'm.machine_name',
                'pp.waste_pct', 'pp.tolerance_pct', 'pp.target_qty_per_hour', 'pp.subcon_supplier_id', 'pp.note', 'pp.source']);

        $bom = $mst->table('prod_part_material as pm')
            ->leftJoin('mat_raw as r', 'r.material_id', '=', 'pm.material_id')
            ->where('pm.part_id', $part->id_part)->whereNotIn('pm.source', ['ENGINE', 'INQUIRY'])
            ->orderBy('pm.layer_seq')
            ->get(['pm.rawmat_type', 'pm.material_id', 'pm.category_id', 'pm.role', 'r.material_name', 'pm.gram_weight', 'pm.size_p', 'pm.size_l',
                'pm.qty_per_sheet', 'pm.uom', 'pm.is_primary', 'pm.note', 'pm.source']);
        // Material not chosen yet (only category + gsm + size from the Inquiry): the row is named by its category path, so it is never lost.
        $cat = $mst->table('mat_category')->get(['id_category', 'parent_id', 'category_name'])->keyBy('id_category');
        $path = function (?int $id) use ($cat): ?string {
            $names = [];
            for ($n = $id ? $cat->get($id) : null; $n; $n = $n->parent_id ? $cat->get($n->parent_id) : null) {
                array_unshift($names, $n->category_name);
            }

            return $names ? implode(' › ', $names) : null;
        };

        $inks = $mst->table('prod_part_ink')
            ->where('part_id', $part->id_part)->where('source', '<>', 'ENGINE')
            ->orderBy('pass_seq')->orderBy('unit_no')
            ->get(['pass_seq', 'pass_label', 'unit_no', 'ink_name', 'coverage_pct', 'color_hex', 'source']);

        return [
            'bop' => $bop->isEmpty() ? null : [
                'source' => $bop->contains('source', 'OVERRIDE') ? 'OVERRIDE' : 'MANUAL',
                'rows' => $bop->map(fn ($r) => [
                    'process_id' => $r->process_id, 'label' => $r->process_name ?: $r->master_name,
                    'machine_id' => $r->machine_id, 'machine' => $r->machine_name,
                    'waste_pct' => $r->waste_pct, 'tolerance_pct' => $r->tolerance_pct,
                    'target_qty_per_hour' => $r->target_qty_per_hour, 'subcon_supplier_id' => $r->subcon_supplier_id, 'note' => $r->note,
                ])->all(),
            ],
            'bom' => $bom->isEmpty() ? null : [
                'source' => $bom->contains('source', 'OVERRIDE') ? 'OVERRIDE' : 'MANUAL',
                'rows' => $bom->map(fn ($r) => [
                    'role' => $r->rawmat_type ?: ($r->is_primary ? 'KERTAS PLANO' : 'SHEET'), 'material_id' => $r->material_id,
                    'name' => $r->material_name ?: ($path($r->category_id ? (int) $r->category_id : null) ?? ''),
                    'gsm' => $r->gram_weight, 'size_p' => $r->size_p, 'size_l' => $r->size_l,
                    'qty_per_sheet' => $r->qty_per_sheet, 'uom' => $r->uom, 'primary' => (bool) $r->is_primary, 'note' => $r->note,
                ])->all(),
            ],
            'inks' => $inks->isEmpty() ? null : [
                'source' => $inks->contains('source', 'OVERRIDE') ? 'OVERRIDE' : 'MANUAL',
                'rows' => $inks->map(fn ($r) => (array) $r)->all(),
            ],
        ];
    }

    // ===================== UNTUK WORK ORDER =====================

    /**
     * The BOP process that uses each BOM line, derived from the same BOP rules that produced the lines:
     * a material rule belongs to the nearest process rule above it (same finishing type). Paper plano goes to the first BOP
     * step, the laminated sheet to the step that carries "LEM LAMINASI BAHAN". Unknown = null (shown as a dash).
     *
     * @param  list<array>  $bom
     * @param  list<array>  $bop
     * @return array<int, ?int> bom index => process id
     */
    public function processOfBom(array $bom, array $bop): array
    {
        $rules = DB::connection('tm2mst')->table('prod_bop_rule')->orderBy('step_seq')->get(['step_seq', 'process_id', 'material_role', 'finishing_type_id']);
        $steps = $rules->whereNotNull('process_id');
        $ownerOf = function ($rule) use ($steps) {
            return $steps->filter(fn ($s) => $s->step_seq <= $rule->step_seq
                && ($rule->finishing_type_id === null ? $s->finishing_type_id === null : $s->finishing_type_id == $rule->finishing_type_id))->last()?->process_id;
        };
        $first = $bop[0]['process_id'] ?? null;
        $out = [];
        foreach ($bom as $i => $b) {
            $role = (string) ($b['role'] ?? '');
            $ft = $b['finishing_type_id'] ?? null;
            $rule = $rules->first(fn ($r) => $r->material_role === $role && ($ft === null || $r->finishing_type_id == $ft))
                ?? $rules->first(fn ($r) => $r->material_role === $role);
            $out[$i] = match (true) {
                (bool) $rule => $ownerOf($rule) ? (int) $ownerOf($rule) : null,
                in_array($role, ['KERTAS PLANO', 'SUBSTRATE'], true) => $first ? (int) $first : null,
                $role === 'SHEET' => ($lam = $rules->first(fn ($r) => $r->material_role === 'LEM LAMINASI BAHAN')) && $ownerOf($lam) ? (int) $ownerOf($lam) : null,
                default => null,
            };
        }

        return $out;
    }

    /**
     * BOP & BOM siap pakai untuk Work Order dengan qty order WO itu (BACKLOG
     * §19.3). Konteks layout diambil dari layout yang SUDAH disimpan ProDev
     * (iqp_layout, atau prod_layout_group untuk job tanpa Inquiry) -- jadi WO
     * memakai keputusan officer, bukan menghitung ulang canvas sendiri.
     * Override officer diutamakan atas hasil engine.
     *
     * @return array{bop: array, bom: array, warnings: array, layout: ?array}
     */
    public function forWorkOrder(ProdPart $part, ?IqpPart $iqp, float $orderQty): array
    {
        $layout = $iqp
            ? DB::connection('tm2sdi')->table('iqp_layout')->where('part_id', $iqp->id_part)->first()
            : null;
        $group = DB::connection('tm2mst')->table('prod_layout_group as g')
            ->join('prod_layout_group_part as p', 'p.id_layout_group', '=', 'g.id_layout_group')
            ->where('p.part_id', $part->id_part)
            ->orderByDesc('g.id_layout_group')
            ->first(['g.plano_w_mm', 'g.plano_h_mm', 'g.up_count', 'g.layout_detail']);

        $detail = $group && $group->layout_detail ? json_decode($group->layout_detail, true) : [];
        $ctx = [
            'sheetL' => $detail['canvas_l_cm'] ?? (($layout->flat_w_mm ?? 0) / 10),
            'sheetW' => $detail['canvas_w_cm'] ?? (($layout->flat_h_mm ?? 0) / 10),
            'planoL' => ($layout->plano_w_mm ?? $group->plano_w_mm ?? 0) / 10,
            'planoW' => ($layout->plano_h_mm ?? $group->plano_h_mm ?? 0) / 10,
            'sheetsPerPlano' => 1,
            'printMachineId' => $detail['machine']['id'] ?? null,
            'diecutMachineId' => $detail['diecut_machine_id'] ?? null,
            'planoName' => $detail['plano_name'] ?? '',
            'upCount' => (int) ($layout->up_count ?? $group->up_count ?? 0),
            'orderQty' => (int) ceil($orderQty),
        ];
        $upKnown = $ctx['upCount'] > 0;
        if (! $upKnown) {
            $ctx['upCount'] = 1;
        }

        $saved = $this->saved($part);
        $overrides = collect(self::SECTIONS)
            ->filter(fn ($k) => $saved[$k] !== null)
            ->mapWithKeys(fn ($k) => [$k => $saved[$k]['rows']])
            ->all();

        $r = $this->generate($part, $iqp, $ctx, $overrides);

        $bop = $r['override']['bop'] ?? $r['bop'];
        $bom = ($r['override']['bom'] ?: null) ?? $r['bom'];
        // Each BOM line carries the BOP process that consumes it (WoGenerationService::writeBom reads process_id as given).
        $of = $this->processOfBom($bom, $bop);
        foreach ($bom as $i => $line) {
            $bom[$i]['process_id'] = $line['process_id'] ?? ($of[$i] ?? null);
        }

        return [
            'bop' => $bop,
            'bom' => $bom,
            'warnings' => array_merge(...array_values($r['warnings'])) + ($upKnown ? [] : ['ups' => 'Layout ProDev belum ada: 1 up per lembar diasumsikan untuk kebutuhan bahan.']),
            'layout' => $upKnown ? $ctx : null,
        ];
    }

    // ===================== SIMPAN 2 ARAH =====================

    /**
     * @param  array  $result  hasil generate() -- override yang dikirim officer sudah
     *                         dinormalisasi di $result['override'].
     * @param  array  $reset  section yang dikembalikan ke hasil engine.
     * @return array daftar section yang TIDAK ditulis karena ada baris manual.
     */
    public function persist(ProdPart $part, ?IqpPart $iqp, array $result, string $login, array $reset = []): array
    {
        $skipped = [];
        $now = now();
        $mst = DB::connection('tm2mst');
        $sdi = DB::connection('tm2sdi');
        $audit = ['created_at' => $now, 'updated_at' => $now, 'created_by' => $login, 'updated_by' => $login];

        foreach (self::SECTIONS as $sec) {
            $override = $result['override'][$sec] ?? null;
            $rows = $override ?? $result[$sec];
            $source = $override !== null ? 'OVERRIDE' : 'ENGINE';

            if ($override === null && in_array($sec, $reset, true)) {
                $this->deleteNonEngine($mst, $sdi, $sec, $part, $iqp);
            }

            $mstTable = ['bop' => 'prod_part_process', 'bom' => 'prod_part_material', 'inks' => 'prod_part_ink'][$sec];

            if ($source === 'ENGINE' && $mst->table($mstTable)->where('part_id', $part->id_part)->where('source', '<>', 'ENGINE')->exists()) {
                $skipped[] = ['bop' => 'Bill of Process', 'bom' => 'Bill of Material', 'inks' => 'Ink Configuration'][$sec];

                continue;
            }

            $mst->transaction(fn () => $sdi->transaction(function () use ($sec, $rows, $source, $part, $iqp, $mst, $sdi, $audit, $now, $login) {
                match ($sec) {
                    'bop' => $this->writeBop($mst, $sdi, $rows, $source, $part, $iqp, $audit),
                    'bom' => $this->writeBom($mst, $sdi, $rows, $source, $part, $iqp, $audit, $now, $login),
                    'inks' => $this->writeInks($mst, $sdi, $rows, $source, $part, $iqp, $audit),
                };
            }));
        }

        return $skipped;
    }

    private function deleteNonEngine($mst, $sdi, string $sec, ProdPart $part, ?IqpPart $iqp): void
    {
        [$m, $s] = ['bop' => ['prod_part_process', 'iqp_part_process'], 'bom' => ['prod_part_material', 'iqp_part_material'], 'inks' => ['prod_part_ink', 'iqp_part_ink']][$sec];

        $mst->table($m)->where('part_id', $part->id_part)->where('source', '<>', 'ENGINE')->delete();

        if ($iqp) {
            // Bahan MANUAL di Inquiry = pilihan Sales, bukan override -> tetap.
            $sdi->table($s)->where('part_id', $iqp->id_part)
                ->where('source', $sec === 'bom' ? 'OVERRIDE' : '<>', $sec === 'bom' ? 'OVERRIDE' : 'ENGINE')->delete();
        }
    }

    private function writeBop($mst, $sdi, array $rows, string $source, ProdPart $part, ?IqpPart $iqp, array $audit): void
    {
        $base = fn ($s, $i) => [
            'branch_code' => 'MAIN', 'step_seq' => ($i + 1) * 10, 'flow_type' => 'LINEAR',
            'process_id' => $s['process_id'], 'machine_id' => $s['machine_id'], 'qty_factor' => 1,
            'waste_pct' => $s['waste_pct'], 'tolerance_pct' => $s['tolerance_pct'],
            'target_qty_per_hour' => $s['target_qty_per_hour'],
            'note' => $s['note'], 'source' => $source,
        ];

        $mst->table('prod_part_process')->where('part_id', $part->id_part)->delete();
        $mst->table('prod_part_process')->insert(array_map(fn ($s, $i) => $base($s, $i) + [
            'part_id' => $part->id_part, 'process_name' => mb_substr($s['label'], 0, 80),
            'subcon_supplier_id' => $s['subcon_supplier_id'] ?? null,
        ] + $audit, $rows, array_keys($rows)));

        if ($iqp) {
            $sdi->table('iqp_part_process')->where('part_id', $iqp->id_part)->delete();
            $sdi->table('iqp_part_process')->insert(array_map(fn ($s, $i) => $base($s, $i) + [
                'part_id' => $iqp->id_part, 'process_name' => mb_substr($s['label'], 0, 120),
                'subcon_supplier_id' => $s['subcon_supplier_id'] ?? null,
                'subcon_pr_id' => $s['subcon_pr_id'] ?? null,
                'subcon_do_id' => $s['subcon_do_id'] ?? null,
            ] + $audit, $rows, array_keys($rows)));
        }
    }

    private function writeBom($mst, $sdi, array $rows, string $source, ProdPart $part, ?IqpPart $iqp, array $audit, $now, string $login): void
    {
        $mst->table('prod_part_material')->where('part_id', $part->id_part)->delete();
        $mst->table('prod_part_material')->insert(array_map(fn ($b, $i) => [
            'part_id' => $part->id_part, 'layer_seq' => $i + 1, 'material_id' => $b['material_id'],
            'rawmat_type' => mb_substr((string) $b['role'], 0, 20), 'gram_weight' => $b['gsm'],
            'size_p' => $b['size_p'], 'size_l' => $b['size_l'],
            'qty_per_sheet' => $b['qty_per_sheet'], 'uom' => $b['uom'],
            'is_primary' => $b['primary'] ? 1 : 0,
            'note' => $b['note'] ? mb_substr($b['note'], 0, 255) : null,
            'source' => $source,
        ] + $audit, $rows, array_keys($rows)));

        if (! $iqp) {
            return;
        }

        // Inquiry: baris KERTAS/SHEET = bahan pilihan Sales (MANUAL) -> diperbarui
        // di tempat (urutan sama); sisanya (finishing/tinta/lem) ditulis ulang.
        $sdi->table('iqp_part_material')->where('part_id', $iqp->id_part)->where('source', '<>', 'MANUAL')->delete();
        $inputs = $sdi->table('iqp_part_material')->where('part_id', $iqp->id_part)
            ->orderByDesc('is_primary')->orderBy('layer_seq')->pluck('id_part_material')->all();
        $seq = (int) $sdi->table('iqp_part_material')->where('part_id', $iqp->id_part)->max('layer_seq');
        $k = 0;

        foreach ($rows as $b) {
            if ($b['name'] === 'None' || ! $b['material_id'] && $b['basis'] === 'PLANO') {
                continue;
            }

            $fields = [
                'rawmat_id' => $b['material_id'], 'rawmat_name' => $b['name'] ? mb_substr($b['name'], 0, 120) : null,
                'gram_weight' => $b['gsm'], 'size_p' => $b['size_p'], 'size_l' => $b['size_l'],
                'qty_per_sheet' => $b['qty_per_sheet'], 'uom' => $b['uom'],
            ];

            if (in_array($b['role'], ['KERTAS PLANO', 'SHEET', 'SF'], true) && isset($inputs[$k])) {
                $sdi->table('iqp_part_material')->where('id_part_material', $inputs[$k++])
                    ->update($fields + ['updated_at' => $now, 'updated_by' => $login]);

                continue;
            }

            $sdi->table('iqp_part_material')->insert($fields + [
                'part_id' => $iqp->id_part, 'layer_seq' => ++$seq, 'is_primary' => 0,
                'material_source' => $iqp->inquiry?->material_source, 'source' => $source,
            ] + $audit);
        }
    }

    private function writeInks($mst, $sdi, array $rows, string $source, ProdPart $part, ?IqpPart $iqp, array $audit): void
    {
        $base = fn ($k) => [
            'pass_seq' => $k['pass_seq'], 'pass_label' => $k['pass_label'], 'unit_no' => $k['unit_no'],
            'ink_name' => $k['ink_name'], 'coverage_pct' => $k['coverage_pct'], 'coverage_cm2' => $k['coverage_cm2'],
            'color_hex' => $k['color_hex'], 'source' => $source,
        ];

        $mst->table('prod_part_ink')->where('part_id', $part->id_part)->delete();
        $mst->table('prod_part_ink')->insert(array_map(fn ($k) => $base($k) + [
            'part_id' => $part->id_part, 'created_at' => $audit['created_at'], 'created_by' => $audit['created_by'],
        ], $rows));

        if ($iqp) {
            $sdi->table('iqp_part_ink')->where('part_id', $iqp->id_part)->delete();
            $sdi->table('iqp_part_ink')->insert(array_map(fn ($k) => $base($k) + ['part_id' => $iqp->id_part] + $audit, $rows));
        }
    }
}
