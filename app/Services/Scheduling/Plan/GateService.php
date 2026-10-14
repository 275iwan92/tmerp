<?php

/*
 * REMARK
 * Tujuan       : Gerbang material/tooling/artwork per operasi; material memakai baris BOM WO (Release dan Purchase).
 * Sumber hulu  : prd_wo_material, v_rm_available, MaterialPlanningEngine, prod_tooling_asset.
 * Input        : PrdWo (material), PrdWo + ProdProcess (tooling).
 * Output       : status READY/WAIT/BLOCK + catatan, ditulis ke kolom gate_* operasi (apply()).
 * Dipakai oleh : AutoPlanRunner, PlanConfirmService, PpicController (refresh/apply/bom), Ppic::applyBlockedReason.
 * materialShortages(): daftar bahan kurang per baris, satu sumber untuk gerbang bahan dan gerbang APPLY (Jo 2026-10-14).
 * Rumus BOM (user 2026-10-13): Release = Available (nilai officer menang, 0..Available); Purchase = max(0, Required - Release); minus ditampilkan 0.
 *               SEBELUM: Release NULL = min(Available, Required), Release dibatasi 0..Required, Purchase = Required - Release.
 * ATTENTION-ENGINE-03 / 05 / 06: lihat docs/ENGINE-ATTENTION.md (Release tidak mem-booking; Purchase belum membuat PR; Required 0, artwork, tooling tak terdaftar tidak menahan).
 * Status       : Aktif. Aturan: bahan belum dipilih = WAIT; Release > Available = BLOCK; Purchase > 0 = WAIT; baris bersubstitusi dicek terhadap stok material pengganti. Gate artwork belum dievaluasi (selalu READY).
 */

declare(strict_types=1);

namespace App\Services\Scheduling\Plan;

use App\Models\Master\ProdProcess;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use App\Services\Planning\MaterialPlanningEngine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Readiness gates of an operation: Ready, Wait (with an arrival time) or Block (with the reason).
 * Material = result of the Material Planning engine (stock, booking, incoming POs and PRs) for the Work Order's customer order and part;
 * Tooling = tooling master status of the die / plates; Artwork = always Ready until the proof approval is stored in a table.
 * Unknown data never blocks: it is Ready with a note.
 */
final class GateService
{
    public const READY = 'READY';

    public const WAIT = 'WAIT';

    public const BLOCK = 'BLOCK';

    /** @var list<array>|null */
    private ?array $lines = null;

    public function __construct(private readonly MaterialPlanningEngine $mrp) {}

    /** @return array{status: string, note: ?string, ready_at: ?CarbonImmutable} */
    public function material(PrdWo $wo): array
    {
        $partId = $wo->source_iqp_part_id
            ? DB::connection('tm2sdi')->table('iqp_part')->where('id_part', $wo->source_iqp_part_id)->value('prod_part_id') : null;
        // The Work Order's own material lines come first: a paper not chosen yet, or a line the stock cannot cover.
        $own = $this->ownLines($wo);
        if ($own) {
            return $own;
        }
        $this->lines ??= $this->mrp->plan()['lines'];

        $mine = array_values(array_filter($this->lines, fn ($l) => ($l['co_id'] ?? null) == $wo->source_co_id
            && ($l['role'] ?? '') !== 'FG' && (! $partId || ($l['part_id'] ?? null) == $partId)));
        if (! $mine) {
            return ['status' => self::READY, 'note' => 'Material need not calculated for this order.', 'ready_at' => null];
        }

        $worst = self::READY;
        $note = null;
        $readyAt = null;
        foreach ($mine as $l) {
            $status = (string) ($l['status'] ?? '');
            if ($status === 'SHORT') {
                return ['status' => self::BLOCK, 'note' => sprintf('Short %s %s: purchase request needed.', rtrim(rtrim(number_format((float) ($l['short'] ?? 0), 2, '.', ''), '0'), '.'), $l['material'] ?? 'material'), 'ready_at' => null];
            }
            if (in_array($status, ['PEGGED_PO', 'PEGGED_PR', 'LATE_PO', 'LATE_PR'], true)) {
                $worst = self::WAIT;
                foreach ($l['alloc'] ?? [] as $a) {
                    if (($a['type'] ?? '') !== 'STOCK' && ! empty($a['date'])) {
                        $d = CarbonImmutable::parse($a['date'])->setTime(8, 0);
                        if (! $readyAt || $d->greaterThan($readyAt)) {
                            $readyAt = $d;
                            $note = sprintf('%s %s arrives %s.', $a['type'], $a['no'] ?? '', $d->format('D j M'));
                        }
                    }
                }
            }
        }

        return ['status' => $worst, 'note' => $note, 'ready_at' => $readyAt];
    }

    /** BOM Release of a line: the officer's stored value, else everything that is Available (stock not found = 0). */
    public static function release(?float $stored, float $available): float
    {
        return $stored ?? $available;
    }

    /** BOM Purchase = Required - Release, never below 0 (a surplus of stock is not bought negative). */
    public static function purchase(float $required, float $release): float
    {
        return max(0.0, $required - $release);
    }

    /** @return ?array{status: string, note: string, ready_at: null} */
    private function ownLines(PrdWo $wo): ?array
    {
        $short = $this->materialShortages($wo);
        if (! $short) {
            return null;
        }
        $of = fn (string $state) => array_values(array_filter($short, fn ($s) => $s['state'] === $state));
        if ($of('NO_LINES')) {
            return ['status' => self::WAIT, 'note' => 'No material lines: run PROCESS or check the BOM in ProDev.', 'ready_at' => null];
        }
        if ($open = $of('NOT_CHOSEN')) {
            return ['status' => self::WAIT, 'note' => 'Material not chosen yet: '.implode('; ', array_slice(array_column($open, 'material'), 0, 2)).'.', 'ready_at' => null];
        }
        if ($over = $of('OVER_RELEASE')) {
            return ['status' => self::BLOCK, 'note' => 'Release exceeds available stock: '.$over[0]['material'].'.', 'ready_at' => null];
        }

        return ['status' => self::WAIT, 'note' => 'Buy '.implode('; ', array_slice(array_map(fn ($s) => self::qty($s['purchase']).' '.$s['uom'].' '.$s['material'], $short), 0, 2)).'.', 'ready_at' => null];
    }

    /**
     * Why the Work Order's material is not ready, line by line (empty = ready). The single source for the material gate AND for APPLY TO LIVE SCHEDULE.
     * state: NO_LINES (an Inquiry Work Order without BOM lines), NOT_CHOSEN (no material picked), OVER_RELEASE (Release > Available), SHORT (Purchase > 0).
     * Available = free of every open booking (AvailableStock), the same number the BOM sheet shows; Required 0 lines are ignored (ATTENTION-ENGINE-06).
     *
     * @return list<array{material: string, uom: ?string, required: float, release: float, available: float, purchase: float, state: string}>
     */
    public function materialShortages(PrdWo $wo): array
    {
        $row = fn (string $state, string $material, ?string $uom = null, float $req = 0, float $rel = 0, float $avail = 0, float $buy = 0) => compact('state', 'material', 'uom') + ['required' => $req, 'release' => $rel, 'available' => $avail, 'purchase' => $buy];
        $rows = DB::connection('tm2prd')->table('prd_wo_material')->where('wo_id', $wo->wo_id)->get(['material_id', 'material_name', 'qty_required', 'qty_release', 'uom', 'is_substituted', 'substitute_material_id']);
        // A Work Order that comes from an Inquiry part must have material lines: none = not ready (never "stock ok" by silence).
        if ($rows->isEmpty() && $wo->source_iqp_part_id) {
            return [$row('NO_LINES', 'No material lines')];
        }
        $out = [];
        foreach ($rows->whereNull('material_id')->where('qty_required', '>', 0) as $l) {
            $out[] = $row('NOT_CHOSEN', (string) $l->material_name, $l->uom, (float) $l->qty_required);
        }
        if ($out) {
            return $out;
        }
        // a substituted line is covered by its substitute material
        $lines = $rows->map(function ($l) {
            $l->material_id = $l->is_substituted && $l->substitute_material_id ? $l->substitute_material_id : $l->material_id;

            return $l;
        })->whereNotNull('material_id')->where('qty_required', '>', 0);
        if ($lines->isEmpty()) {
            return [];
        }
        $have = collect(AvailableStock::of($lines->pluck('material_id')->unique()->all()))->map(fn ($s) => $s['available']);
        foreach ($lines as $l) {
            $avail = (float) ($have[$l->material_id] ?? 0);
            $req = (float) $l->qty_required;
            $rel = self::release($l->qty_release !== null ? (float) $l->qty_release : null, $avail);
            $buy = self::purchase($req, $rel);
            if ($rel > $avail + 0.0001) {
                $out[] = $row('OVER_RELEASE', (string) $l->material_name, $l->uom, $req, $rel, $avail, $buy);
            } elseif ($buy > 0.0001) {
                $out[] = $row('SHORT', (string) $l->material_name, $l->uom, $req, $rel, $avail, $buy);
            }
        }

        return $out;
    }

    /** One readable sentence for APPLY: "Kertas: buy 120 LBR; Tinta: not chosen". */
    public static function shortageText(array $shortages): string
    {
        return implode('; ', array_map(fn ($s) => match ($s['state']) {
            'NO_LINES' => $s['material'].': run PROCESS or check the BOM in ProDev',
            'NOT_CHOSEN' => $s['material'].': material not chosen',
            'OVER_RELEASE' => $s['material'].': release '.self::qty($s['release']).' is above available '.self::qty($s['available']),
            default => $s['material'].': buy '.self::qty($s['purchase']).' '.$s['uom'],
        }, $shortages));
    }

    private static function qty(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    /** @return array{status: string, note: ?string} */
    public function tooling(PrdWo $wo, ProdProcess $process): array
    {
        $type = $process->requires_die ? 'CUTTING_DIE' : ($process->requires_plate ? 'PLATE_SET' : null);
        if (! $type) {
            return ['status' => self::READY, 'note' => null];
        }
        $q = DB::connection('tm2mst')->table('prod_tooling_asset')->where('tooling_type', $type)->where('activate', 1);
        $q->where(function ($w) use ($wo) {
            if ($wo->dieline_ref) {
                $w->orWhere('dieline_ref', $wo->dieline_ref);
            }
            $w->orWhere('product_id', $wo->product_id);
        });
        $asset = $q->orderByRaw("FIELD(status, 'READY', 'IN_USE', 'IN_MAKING', 'NEEDS_REPAIR', 'NOT_MADE')")->first();
        if (! $asset) {
            return ['status' => self::READY, 'note' => 'Tooling is not registered in the tooling master.'];
        }
        if (in_array($asset->status, ['READY', 'IN_USE'], true)) {
            return ['status' => self::READY, 'note' => null];
        }

        return ['status' => self::BLOCK, 'note' => sprintf('%s %s is %s.', $type === 'CUTTING_DIE' ? 'Die' : 'Plates', $asset->tooling_code, strtolower(str_replace('_', ' ', $asset->status)))];
    }

    /** Write the gate columns of every operation of the Work Order (material gate on the first operation only). */
    public function apply(PrdWo $wo): void
    {
        $material = $this->material($wo);
        $first = true;
        foreach ($wo->operations()->orderBy('seq_no')->get() as $op) {
            /** @var PrdWoOperation $op */
            $process = $op->process_id ? ProdProcess::find($op->process_id) : null;
            $tool = $process ? $this->tooling($wo, $process) : ['status' => self::READY, 'note' => null];
            $notes = array_filter([$first ? $material['note'] : null, $tool['note']]);
            $op->forceFill([
                'gate_material' => $first ? $material['status'] : self::READY,
                'gate_tooling' => $tool['status'],
                'gate_artwork' => self::READY,
                'gate_note' => $notes ? mb_substr(implode(' ', $notes), 0, 190) : null,
                'material_ready_at' => $first ? $material['ready_at'] : null,
            ])->save();
            $first = false;
        }
    }
}
