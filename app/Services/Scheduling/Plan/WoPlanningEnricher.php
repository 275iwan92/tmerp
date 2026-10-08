<?php

declare(strict_types=1);

namespace App\Services\Scheduling\Plan;

use App\Models\Master\ProdProcess;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use App\Services\Production\MachineCapabilityService;
use Illuminate\Support\Facades\DB;

/**
 * After a Work Order is created from ProDev (WoGenerationService) this fills in what scheduling needs on every operation:
 * the process id, unit and quantity out, the machines that can run it with their target per hour and setup, and the
 * Work Order facts (ups, sheet, board, colours, dieline, customer tier). Machine eligibility and the 3-layer parameter
 * resolution (pair, process, machine) come from MachineCapabilityService, the one place that rule is written.
 */
final class WoPlanningEnricher
{
    public function __construct(
        private readonly MachineCapabilityService $capabilities,
        private readonly PlanFacts $facts,
    ) {}

    /** @return array{operations: int, without_machine: int} */
    public function enrich(PrdWo $wo): array
    {
        $facts = $this->facts->forWo($wo);
        $wo->forceFill([
            'ups_per_sheet' => $wo->ups_per_sheet ?? $facts['ups'],
            'sheet_length_mm' => $wo->sheet_length_mm ?? $facts['sheet_l'],
            'sheet_width_mm' => $wo->sheet_width_mm ?? $facts['sheet_w'],
            'board_gsm' => $wo->board_gsm ?? $facts['gsm'],
            'colour_count' => $wo->colour_count ?? $facts['colours'],
            'dieline_ref' => $wo->dieline_ref ?? $facts['dieline'],
            'customer_tier' => $facts['tier'],
            'layout_json' => $facts['layout'] ?: null,
        ])->save();

        $ops = $wo->operations()->orderBy('seq_no')->get();
        $withoutMachine = 0;

        foreach ($ops as $i => $op) {
            $process = $this->process($op);
            $next = $ops[$i + 1] ?? null;

            $op->process_id = $process?->id_process;
            $op->qty_uom ??= $op->waste_uom ?: $this->uomOf($process, (bool) $wo->ups_per_sheet);
            $op->qty_out = $this->qtyOut($op, $next, $wo);

            $eligible = $process ? $this->candidates($op, $process, $wo, $facts) : 0;
            if ($eligible === 0) {
                $withoutMachine++;
            }
            $op->save();
        }

        return ['operations' => $ops->count(), 'without_machine' => $withoutMachine];
    }

    private function process(PrdWoOperation $op): ?ProdProcess
    {
        if ($op->process_id) {
            return ProdProcess::find($op->process_id);
        }
        $name = trim((string) $op->process_name);

        return ProdProcess::query()->whereRaw('UPPER(process_name) = ?', [strtoupper($name)])->first()
            ?? ProdProcess::query()->whereRaw('UPPER(process_name) LIKE ?', [strtoupper($name).'%'])->orderBy('id_process')->first();
    }

    /** Sheets up to die-cutting, pieces after it (same rule as the ProDev BOP chain). */
    private function uomOf(?ProdProcess $process, bool $hasUps): string
    {
        $stage = strtoupper((string) $process?->process_stage);

        return in_array($stage, ['PRESS', 'POSTPRESS', 'PREPRESS'], true) || ($hasUps && str_starts_with(strtoupper((string) $process?->process_name), 'PLONG')) ? 'LBR' : 'PCS';
    }

    private function qtyOut(PrdWoOperation $op, ?PrdWoOperation $next, PrdWo $wo): float
    {
        if (! $next) {
            return (float) $wo->qty;
        }
        $unitNow = $op->qty_uom ?: $op->waste_uom;
        $unitNext = $next->qty_uom ?: $next->waste_uom;
        if ($unitNow && $unitNext && $unitNow !== $unitNext && $wo->ups_per_sheet) {
            return (float) ceil((float) $next->qty / $wo->ups_per_sheet);
        }

        return (float) $next->qty;
    }

    /** @return int number of eligible machines */
    private function candidates(PrdWoOperation $op, ProdProcess $process, PrdWo $wo, array $facts): int
    {
        $constraints = ['sheet_length_mm' => $facts['sheet_l'], 'sheet_width_mm' => $facts['sheet_w'], 'gsm' => $facts['gsm'], 'qty' => (float) $op->qty];
        $rows = $this->capabilities->english()->machinesForProcess((int) $process->id_process, $constraints);
        $this->capabilities->english(false);
        $isPrint = strtoupper((string) $process->process_stage) === 'PRESS' && str_starts_with(strtoupper((string) $process->process_name), 'CETAK');

        DB::connection('tm2prd')->table('prd_wo_operation_machine')->where('operation_id', $op->operation_id)->delete();
        $insert = [];
        $first = null;
        foreach ($rows as $r) {
            $reasons = $r['reasons'];
            $m = $r['machine'];
            if ($isPrint && $facts['colours'] && (int) $m->max_colors_per_pass > 0 && $facts['colours'] > (int) $m->max_colors_per_pass) {
                $reasons[] = sprintf('Needs %d colours; the machine prints %d per pass', $facts['colours'], $m->max_colors_per_pass);
            }
            $eligible = $reasons === [];
            $rate = $r['params']['speed_per_hour']['value'];
            $setup = $r['params']['setup_minutes']['value'];
            $insert[] = [
                'operation_id' => $op->operation_id, 'machine_id' => $m->machine_id, 'eligible' => $eligible ? 1 : 0,
                'reason' => $reasons ? mb_substr(implode('; ', $reasons), 0, 190) : null,
                'preference_rank' => $r['capability']->preference_rank, 'target_qty_per_hour' => $rate !== null ? (float) $rate : null,
                'setup_minutes' => $setup !== null ? (int) $setup : null, 'created_at' => now(),
            ];
            if ($eligible && $first === null) {
                $first = ['rate' => $rate, 'setup' => $setup];
            }
        }
        if ($insert) {
            DB::connection('tm2prd')->table('prd_wo_operation_machine')->insert($insert);
        }

        if ($first) {
            $op->rate_band_value ??= $first['rate'];
            $op->setup_base_min = $first['setup'] !== null ? (int) $first['setup'] : $op->setup_base_min;
            if ($op->rate_band_value) {
                $op->run_minutes = (int) ceil((float) $op->qty / (float) $op->rate_band_value * 60);
            }
        }

        return count(array_filter($insert, fn ($x) => $x['eligible']));
    }
}
