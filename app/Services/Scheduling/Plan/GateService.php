<?php

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
