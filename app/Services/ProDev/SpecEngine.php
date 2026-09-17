<?php

namespace App\Services\ProDev;

use App\Models\Master\ProdMachine;
use App\Models\Master\ProdPart;
use App\Models\Sdi\IqpPart;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Engine spesifikasi produksi: Bill of Process, Bill of Material, Ink
// Configuration (BACKLOG §18). Semua hasil diturunkan dari input Inquiry
// (finishing, bahan, notasi warna, isi per pack, artwork) + keputusan layout
// ProDev (lembar cetak, plano, mesin cetak, mesin pond, jumlah up).
//
// "Engine suggests, officer confirms": generate() tidak menulis apa pun;
// persist() dipanggil saat Save/Confirm ProDev dan HANYA mengganti baris
// ber-source ENGINE -- baris MANUAL tidak pernah ditimpa.
class SpecEngine
{
    private const PROCESS_INKS = [
        ['K', 'BLACK', '#1a1a1a'],
        ['C', 'CYAN', '#00a3e0'],
        ['M', 'MAGENTA', '#e2338c'],
        ['Y', 'YELLOW', '#f2c500'],
    ];

    private const GLUE_JOINT = '/glue|lem|lock\s*bottom|crash|auto\s*bottom/i';

    /**
     * @param  array{sheetL?:float,sheetW?:float,planoL?:float,planoW?:float,sheetsPerPlano?:int,
     *               printMachineId?:int,diecutMachineId?:int,upCount?:int,artworkColors?:array}  $ctx
     */
    public function generate(ProdPart $part, ?IqpPart $iqp, array $ctx): array
    {
        $warnings = ['bop' => [], 'bom' => [], 'ink' => []];
        $finishings = $this->finishings($part, $iqp);
        $materials = $this->inputMaterials($part, $iqp);
        $sheetL = max((float) ($ctx['sheetL'] ?? 0), (float) ($ctx['sheetW'] ?? 0));
        $sheetW = min((float) ($ctx['sheetL'] ?? 0), (float) ($ctx['sheetW'] ?? 0));
        $packQty = $iqp?->pack_qty ?: $part->pack_qty;

        $bom = $this->bom($materials, $finishings, $ctx, $sheetL, $sheetW, $warnings['bom']);
        $bop = $this->bop($part, $finishings, $materials, $ctx, $sheetL, $sheetW, $packQty, $bom, $warnings['bop']);
        $ink = $this->inks($part, $iqp, $ctx['artworkColors'] ?? null, (int) ($ctx['upCount'] ?? 0), $warnings['ink']);

        if (! $packQty) {
            $warnings['bop'][] = 'Isi per pack belum diisi di Inquiry -- langkah Pack tanpa jumlah.';
        }

        return [
            'inputs' => [
                'finishings' => $finishings->map(fn ($f) => $f['name'].($f['attribute'] ? ' ('.$f['attribute'].')' : ''))->values()->all(),
                'color' => $part->cfg_color ?: $iqp?->cfg_color,
                'packQty' => $packQty,
                'sheet' => $sheetL && $sheetW ? [$sheetL, $sheetW] : null,
            ],
            'bop' => $bop,
            'bom' => $bom,
            'inks' => $ink['rows'],
            'colors' => $ink['colors'],
            'warnings' => array_map(fn ($w) => array_values(array_unique($w)), $warnings),
        ];
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

    private function inputMaterials(ProdPart $part, ?IqpPart $iqp): Collection
    {
        // Hanya bahan pilihan Sales (MANUAL); baris ENGINE adalah hasil engine
        // sebelumnya dan tidak boleh ikut jadi input (bikin BOP berubah tiap save).
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
            ->orderByDesc('pm.is_primary')->orderBy('pm.layer_seq')
            ->get(['pm.material_id', 'm.material_name', 'pm.gram_weight', 'pm.is_primary'])
            ->map(fn ($m) => [
                'material_id' => $m->material_id,
                'name' => $m->material_name,
                'gsm' => $m->gram_weight ?: $this->gsmFromName($m->material_name),
                'primary' => (bool) $m->is_primary,
            ]);
    }

    private function gsmFromName(?string $name): ?float
    {
        return $name && preg_match('/(\d{2,4})\s*(?:GR|GSM|G\b)/i', $name, $m) ? (float) $m[1] : null;
    }

    // ===================== BILL OF PROCESS =====================

    private function bop(ProdPart $part, Collection $finishings, Collection $materials, array $ctx,
        float $sheetL, float $sheetW, $packQty, array $bom, array &$warnings): array
    {
        $rules = DB::connection('tm2mst')->table('prod_bop_rule as r')
            ->join('prod_process as p', 'p.id_process', '=', 'r.process_id')
            ->where('r.activate', 1)
            ->where(fn ($q) => $q->whereNull('r.part_type')->orWhere('r.part_type', $part->part_type))
            ->orderBy('r.step_seq')->orderBy('r.id_bop_rule')
            ->get(['r.*', 'p.process_name']);

        $machines = ProdMachine::whereIn('machine_id', array_filter([
            $ctx['printMachineId'] ?? null, $ctx['diecutMachineId'] ?? null,
        ]))->pluck('machine_name', 'machine_id');

        $facts = [
            'PLANO_NEEDS_CUT' => ! empty($ctx['planoL']) && $sheetL > 0
                && (abs((float) $ctx['planoL'] - $sheetL) > 0.5 || abs((float) ($ctx['planoW'] ?? 0) - $sheetW) > 0.5),
            'MULTI_LAYER' => $materials->count() > 1 || in_array($part->part_type, ['DC+SF', 'DC+SHEET'], true),
            'NEEDS_GLUE' => (bool) preg_match(self::GLUE_JOINT, (string) $part->joint_type),
        ];

        $rolls = collect($bom)->whereNotNull('roll_cm')->keyBy('finishing_type_id');
        $steps = [];

        foreach ($rules as $r) {
            if ($r->rule_scope === 'STANDARD') {
                if ($r->condition_code && empty($facts[$r->condition_code])) {
                    continue;
                }
                $steps[] = $this->step($r, null, $ctx, $machines, $packQty, null);

                continue;
            }

            foreach ($finishings->where('type_id', (int) $r->finishing_type_id) as $fin) {
                $steps[] = $this->step($r, $fin['attribute'], $ctx, $machines, $packQty,
                    $rolls->get((int) $r->finishing_type_id)['roll_cm'] ?? null);
            }
        }

        $ruled = $rules->where('rule_scope', 'FINISHING')->pluck('finishing_type_id')->map(fn ($v) => (int) $v)->all();
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
                $out[count($out) - 1]['label'] .= ' '.($s['merge_label'] ?: '+ '.$s['label']);
                $out[count($out) - 1]['merged'][] = $s['process_name'];

                continue;
            }
            $out[] = $s;
        }

        return array_map(fn ($s, $i) => [
            'seq' => ($i + 1) * 10,
            'process_id' => $s['process_id'],
            'label' => $s['label'],
            'machine_id' => $s['machine_id'],
            'machine' => $s['machine'],
            'note' => $s['merged'] ? 'Gabungan: '.implode(' + ', array_merge([$s['process_name']], $s['merged'])) : null,
        ], $out, array_keys($out));
    }

    private function step(object $r, ?string $attr, array $ctx, Collection $machines, $packQty, $roll): array
    {
        $machineId = match ($r->machine_role) {
            'PRINT' => $ctx['printMachineId'] ?? null,
            'DIECUT' => $ctx['diecutMachineId'] ?? null,
            default => null,
        };
        $machine = $machineId ? ($machines[$machineId] ?? null) : null;

        $label = $r->step_label;
        $label = $packQty ? str_replace('{pack}', (string) $packQty, $label) : preg_replace('/\s*\/?\{pack\}\s*pcs/i', '', $label);
        $label = $roll ? str_replace('{roll}', rtrim(rtrim(number_format($roll, 1, '.', ''), '0'), '.'), $label)
            : preg_replace('/\s*\([^)]*\{roll\}[^)]*\)/', '', $label);
        $label = str_replace(['{machine}', '{attr}'], [$machine ?? '(pilih mesin)', (string) $attr], $label);

        return [
            'process_id' => (int) $r->process_id,
            'process_name' => $r->process_name,
            'label' => trim(preg_replace('/\s{2,}/', ' ', $label)),
            'merge_group' => $r->merge_group,
            'merge_label' => $r->merge_label,
            'machine_id' => $machineId,
            'machine' => $machine,
            'merged' => [],
        ];
    }

    // ===================== BILL OF MATERIAL =====================

    private function bom(Collection $materials, Collection $finishings, array $ctx, float $sheetL, float $sheetW, array &$warnings): array
    {
        $rows = [];
        $planoL = (float) ($ctx['planoL'] ?? 0) ?: $sheetL;
        $planoW = (float) ($ctx['planoW'] ?? 0) ?: $sheetW;
        $perPlano = max(1, (int) ($ctx['sheetsPerPlano'] ?? 1));

        if ($materials->isEmpty()) {
            $warnings[] = 'Bahan baku belum dipilih di Inquiry -- BOM tanpa bahan utama.';
        }

        foreach ($materials->values() as $i => $m) {
            $rows[] = [
                'role' => $i === 0 ? 'BAHAN UTAMA' : 'LAPISAN',
                'material_id' => $m['material_id'],
                'name' => $m['name'],
                'gsm' => $m['gsm'],
                'size_p' => $planoL ?: null,
                'size_l' => $planoW ?: null,
                'qty_per_sheet' => round(1 / $perPlano, 4),
                'uom' => 'LBR',
                'primary' => $m['primary'],
                'finishing_type_id' => null,
                'roll_cm' => null,
                'note' => $perPlano > 1 ? "1 plano = {$perPlano} lembar cetak" : null,
            ];
        }

        $rules = DB::connection('tm2mst')->table('prod_bop_rule')
            ->where('activate', 1)->where('rule_scope', 'FINISHING')->whereNotNull('material_pattern')
            ->get()->keyBy('finishing_type_id');

        foreach ($finishings as $fin) {
            $rule = $rules->get($fin['type_id']);
            if (! $rule) {
                continue;
            }

            $candidates = $this->materialCandidates($rule->material_pattern);

            if ($rule->material_rule === 'ROLL_WIDTH_GE_SHEET') {
                $need = $sheetL;
                $pick = $candidates
                    ->map(fn ($c) => $c + ['width' => $this->rollWidth($c['material_name'])])
                    ->filter(fn ($c) => $c['width'] && $c['width'] >= $need)
                    ->sortBy('width')->first();

                if (! $pick) {
                    $warnings[] = "Tidak ada bahan {$fin['name']} dengan lebar roll >= {$need} cm di Master Bahan.";

                    continue;
                }

                $rows[] = [
                    'role' => strtoupper($fin['name']),
                    'material_id' => $pick['material_id'],
                    'name' => $pick['material_name'],
                    'gsm' => null,
                    'size_p' => $pick['width'],
                    'size_l' => null,
                    // Roll selebar sisi panjang lembar -> yang terpakai sepanjang sisi pendek.
                    'qty_per_sheet' => round($sheetW / 100, 4),
                    'uom' => 'M',
                    'primary' => false,
                    'finishing_type_id' => $fin['type_id'],
                    'roll_cm' => $pick['width'],
                    'note' => "Lebar roll {$pick['width']} cm >= lembar {$need} cm",
                ];

                continue;
            }

            // MATCH_ATTRIBUTE: pilih bahan yang namanya paling cocok dgn atribut
            // (mis. "Gold Puyeh" -> FOIL HOT PRINT GOLD). Luas pemakaian foil
            // tergantung area desain -- belum bisa dihitung, dikosongkan.
            $words = collect(preg_split('/[\s,\/]+/', strtoupper((string) $fin['attribute'])))->filter(fn ($w) => strlen($w) >= 3);
            $pick = $candidates
                ->map(fn ($c) => $c + ['score' => $words->filter(fn ($w) => str_contains(strtoupper($c['material_name']), $w))->count()])
                ->sortByDesc('score')->first();

            if (! $pick) {
                $warnings[] = "Tidak ada bahan untuk {$fin['name']} di Master Bahan.";

                continue;
            }

            if ($words->isNotEmpty() && $pick['score'] === 0) {
                $warnings[] = "Bahan {$fin['name']} \"{$fin['attribute']}\" tidak ditemukan persis -- dipilih {$pick['material_name']}, mohon dicek.";
            }

            $rows[] = [
                'role' => strtoupper($fin['name']),
                'material_id' => $pick['material_id'],
                'name' => $pick['material_name'],
                'gsm' => null,
                'size_p' => null,
                'size_l' => null,
                'qty_per_sheet' => null,
                'uom' => $pick['uom_usage'] ?: null,
                'primary' => false,
                'finishing_type_id' => $fin['type_id'],
                'roll_cm' => null,
                'note' => 'Qty menunggu luas area '.$fin['name'],
            ];
        }

        return $rows;
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
            ->get(['material_id', 'material_name', 'uom_usage'])
            ->map(fn ($r) => (array) $r);
    }

    // "PLASTIKLAMDOFF 0.17 85X400000 CM" -> 85
    private function rollWidth(string $name): ?float
    {
        return preg_match('/(\d+(?:[.,]\d+)?)\s*X\s*\d{4,}/i', $name, $m) ? (float) str_replace(',', '.', $m[1]) : null;
    }

    // ===================== INK CONFIGURATION =====================

    private function inks(ProdPart $part, ?IqpPart $iqp, ?array $art, int $up, array &$warnings): array
    {
        $notation = $part->cfg_color ?: $iqp?->cfg_color;
        $area = (float) $part->die_w_cm * (float) $part->die_h_cm ?: (float) ($iqp?->flat_w) * (float) ($iqp?->flat_h);
        $art = $art ?: $iqp?->artwork_colors;
        $channels = $art['channels'] ?? null;
        $rows = [];

        if (! $notation || ! preg_match('/^(\d)\s*\/\s*(\d)$/', $notation, $m)) {
            $warnings[] = 'Notasi warna (mis. 4/0) belum diisi -- Ink Configuration tidak bisa dibuat.';

            return ['rows' => [], 'colors' => []];
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

            // Coverage hanya untuk sisi depan: artwork yang diupload = sisi cetak depan.
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

        $colors = collect($art['colors'] ?? [])->map(fn ($c) => $c + [
            'cm2' => $area ? round($c['pct'] / 100 * $area, 1) : null,
        ])->values()->all();

        return ['rows' => $rows, 'colors' => $colors];
    }

    private function inkRow(int $pass, string $label, int $unit, string $name, ?string $hex, $pct, float $area, int $up): array
    {
        $cm2 = $pct !== null && $area ? round((float) $pct / 100 * $area, 2) : null;

        return [
            'pass_seq' => $pass,
            'pass_label' => $label,
            'unit_no' => $unit,
            'ink_name' => mb_substr($name, 0, 40),
            'color_hex' => $hex,
            'coverage_pct' => $pct !== null ? round((float) $pct, 2) : null,
            'coverage_cm2' => $cm2,
            'coverage_cm2_sheet' => $cm2 !== null && $up ? round($cm2 * $up, 1) : null,
        ];
    }

    // ===================== SIMPAN 2 ARAH =====================

    public function persist(ProdPart $part, ?IqpPart $iqp, array $result, string $login): array
    {
        $skipped = [];
        $now = now();
        $mst = DB::connection('tm2mst');
        $sdi = DB::connection('tm2sdi');
        $audit = ['created_at' => $now, 'updated_at' => $now, 'created_by' => $login, 'updated_by' => $login];

        // ---- BOP
        $procRows = array_map(fn ($s) => [
            'branch_code' => 'MAIN', 'step_seq' => $s['seq'], 'flow_type' => 'LINEAR',
            'process_id' => $s['process_id'], 'process_name' => mb_substr($s['label'], 0, 80),
            'machine_id' => $s['machine_id'], 'qty_factor' => 1, 'note' => $s['note'], 'source' => 'ENGINE',
        ], $result['bop']);

        if ($this->replaceEngineRows($mst, 'prod_part_process', $part->id_part,
            array_map(fn ($r) => $r + ['part_id' => $part->id_part] + $audit, $procRows))) {
            $skipped[] = 'Bill of Process master (sudah ada routing MANUAL)';
        }

        if ($iqp && $this->replaceEngineRows($sdi, 'iqp_part_process', $iqp->id_part,
            array_map(fn ($r) => ['part_id' => $iqp->id_part, 'process_name' => mb_substr($r['process_name'], 0, 120)] + $r + $audit, $procRows))) {
            $skipped[] = 'Bill of Process Inquiry (sudah ada routing MANUAL)';
        }

        // ---- BOM (master: semua baris hasil engine)
        $matRows = [];
        foreach ($result['bom'] as $i => $b) {
            $matRows[] = [
                'part_id' => $part->id_part, 'layer_seq' => $i + 1, 'material_id' => $b['material_id'],
                'rawmat_type' => mb_substr($b['role'], 0, 20), 'gram_weight' => $b['gsm'],
                'size_p' => $b['size_p'], 'size_l' => $b['size_l'],
                'qty_per_sheet' => $b['qty_per_sheet'], 'uom' => $b['uom'],
                'is_primary' => $b['primary'] ? 1 : 0, 'note' => $b['note'] ? mb_substr($b['note'], 0, 255) : null,
                'source' => 'ENGINE',
            ] + $audit;
        }

        if ($this->replaceEngineRows($mst, 'prod_part_material', $part->id_part, $matRows)) {
            $skipped[] = 'Bill of Material master (sudah ada BOM MANUAL)';
        }

        // BOM Inquiry: bahan pilihan Sales (MANUAL) = input, tidak dihapus --
        // cukup dilengkapi ukuran/qty. Bahan finishing ditambahkan sbg ENGINE.
        if ($iqp) {
            $sdi->table('iqp_part_material')->where('part_id', $iqp->id_part)->where('source', 'ENGINE')->delete();
            $seq = (int) $sdi->table('iqp_part_material')->where('part_id', $iqp->id_part)->max('layer_seq');

            foreach ($result['bom'] as $b) {
                if ($b['finishing_type_id'] === null) {
                    $sdi->table('iqp_part_material')
                        ->where('part_id', $iqp->id_part)->where('source', 'MANUAL')
                        ->where('rawmat_id', $b['material_id'])
                        ->update(['size_p' => $b['size_p'], 'size_l' => $b['size_l'],
                            'qty_per_sheet' => $b['qty_per_sheet'], 'uom' => $b['uom'],
                            'updated_at' => $now, 'updated_by' => $login]);

                    continue;
                }

                $sdi->table('iqp_part_material')->insert([
                    'part_id' => $iqp->id_part, 'layer_seq' => ++$seq, 'rawmat_id' => $b['material_id'],
                    'rawmat_name' => mb_substr($b['name'], 0, 120), 'size_p' => $b['size_p'], 'size_l' => $b['size_l'],
                    'qty_per_sheet' => $b['qty_per_sheet'], 'uom' => $b['uom'], 'is_primary' => 0,
                    'material_source' => $iqp->inquiry?->material_source, 'source' => 'ENGINE',
                ] + $audit);
            }
        }

        // ---- INK
        $inkRows = array_map(fn ($k) => [
            'pass_seq' => $k['pass_seq'], 'pass_label' => $k['pass_label'], 'unit_no' => $k['unit_no'],
            'ink_name' => $k['ink_name'], 'coverage_pct' => $k['coverage_pct'], 'coverage_cm2' => $k['coverage_cm2'],
            'color_hex' => $k['color_hex'], 'source' => 'ENGINE',
        ], $result['inks']);

        if ($this->replaceEngineRows($mst, 'prod_part_ink', $part->id_part,
            array_map(fn ($r) => $r + ['part_id' => $part->id_part, 'created_at' => $now, 'created_by' => $login], $inkRows))) {
            $skipped[] = 'Ink master (sudah ada susunan tinta MANUAL)';
        }

        if ($iqp && $this->replaceEngineRows($sdi, 'iqp_part_ink', $iqp->id_part,
            array_map(fn ($r) => $r + ['part_id' => $iqp->id_part] + $audit, $inkRows))) {
            $skipped[] = 'Ink Inquiry (sudah ada susunan tinta MANUAL)';
        }

        return $skipped;
    }

    // true = dilewati karena ada baris MANUAL (tidak pernah ditimpa engine).
    private function replaceEngineRows($conn, string $table, int $partId, array $rows): bool
    {
        if ($conn->table($table)->where('part_id', $partId)->where('source', '<>', 'ENGINE')->exists()) {
            return true;
        }

        $conn->transaction(function () use ($conn, $table, $partId, $rows) {
            $conn->table($table)->where('part_id', $partId)->where('source', 'ENGINE')->delete();
            if ($rows) {
                $conn->table($table)->insert($rows);
            }
        });

        return false;
    }
}
