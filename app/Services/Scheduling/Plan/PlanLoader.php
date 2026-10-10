<?php

declare(strict_types=1);

namespace App\Services\Scheduling\Plan;

use App\Models\Master\SysHoliday;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoOperation;
use App\Services\Scheduling\WorkingCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Reads the database into the plain objects the solver works with (PlanWo, PlanOp, WorkingCalendar). Nothing is written here.
 */
final class PlanLoader
{
    public const ACTIVE_WO = ['DRAFT', 'SIMULATED', 'SCHEDULED', 'IN_PROGRESS'];

    private const DONE = ['DONE', 'QC_HOLD', 'QC_PASSED', 'QC_REJECTED', 'SUBCONTRACT'];

    /** Operations with this task status are being worked on: they are fixed in the plan. */
    private const WORKING = ['RUNNING'];

    /** @var array<string, int>|null */
    private ?array $waitRules = null;

    /**
     * @param  list<int>|null  $onlyWoIds  limit to some Work Orders (impact preview)
     * @return list<PlanWo>
     */
    public function wos(CarbonImmutable $now, ?array $onlyWoIds = null): array
    {
        $q = PrdWo::query()->whereIn('wo_status', self::ACTIVE_WO)->orderBy('wo_id');
        if ($onlyWoIds) {
            $q->whereIn('wo_id', $onlyWoIds);
        }
        $wos = $q->get();
        if ($wos->isEmpty()) {
            return [];
        }

        $ops = PrdWoOperation::query()->whereIn('wo_id', $wos->pluck('wo_id'))->orderBy('wo_id')->orderBy('seq_no')->get()->groupBy('wo_id');
        $cands = DB::connection('tm2prd')->table('prd_wo_operation_machine')->whereIn('operation_id', $ops->flatten()->pluck('operation_id'))
            ->where('eligible', 1)
            ->get()->groupBy('operation_id');
        // A machine that is down or in maintenance right now takes no new work: the plan goes around it (eligibility was stored when the Work Order was enriched).
        $down = DB::connection('tm2mst')->table('prod_machine')->whereIn('machine_status', ['BREAKDOWN', 'MAINTENANCE'])->pluck('machine_id')->map(fn ($m) => (int) $m)->all();
        $cands = $cands->map(fn ($list) => $list->reject(fn ($c) => in_array((int) $c->machine_id, $down, true))->values());
        $stage = DB::connection('tm2mst')->table('prod_process')->get(['id_process', 'process_stage', 'process_name', 'is_manual', 'inhouse', 'subcon_lead_days'])->keyBy('id_process');
        $tools = $this->toolMinutes($wos);
        $inks = $this->inksFor($wos);

        $out = [];
        foreach ($wos as $wo) {
            $planOps = [];
            foreach ($ops[$wo->wo_id] ?? [] as $op) {
                $planOps[] = $this->op($wo, $op, $cands[$op->operation_id] ?? collect(), $stage, $tools, $now, $inks, $down);
            }
            if (! $planOps) {
                continue;
            }
            $out[] = new PlanWo(
                id: (int) $wo->wo_id, no: (string) $wo->wo_no,
                due: $wo->due_date ? CarbonImmutable::parse($wo->due_date) : null,
                hot: (bool) $wo->is_hot_job, groupOff: (bool) ($wo->die_group_off ?? false), moveUp: ! $wo->is_hot_job && $wo->priority === 'URGENT', tier: (int) ($wo->customer_tier ?: 2),
                ageDays: (int) ($wo->created_at ? $wo->created_at->diffInDays($now) : 0), ops: $planOps,
            );
        }

        return $out;
    }

    /** Minutes to wait after a process (drying, curing), by process-name keyword: the Wait Rules master wins over the start values in config. @return array<string, int> */
    private function waitRules(): array
    {
        if ($this->waitRules === null) {
            $this->waitRules = (array) config('ppic.wait_after', []);
            $rows = DB::connection('tm2mst')->table('prod_wait_rule')->where('activate', 1)->get(['after_process_name', 'wait_minutes']);
            foreach ($rows as $r) {
                $this->waitRules[strtoupper(trim((string) $r->after_process_name))] = (int) $r->wait_minutes;
            }
        }

        return $this->waitRules;
    }

    public function calendar(CarbonImmutable $now, array $machineIds): WorkingCalendar
    {
        $m = DB::connection('tm2mst')->table('prod_machine')->whereIn('machine_id', array_filter($machineIds))
            ->get(['machine_id', 'shifts_per_day', 'hours_per_shift'])
            ->mapWithKeys(fn ($r) => [(int) $r->machine_id => ['shifts' => $r->shifts_per_day ?: null, 'hours' => $r->hours_per_shift ?: null]])->all();
        $holidays = SysHoliday::query()->where('activate', true)->whereBetween('holiday_date', [$now->startOfDay()->toDateString(), $now->addDays((int) PpicSettings::number('horizon_days'))->toDateString()])
            ->get()->map(fn ($h) => $h->holiday_date->toDateString())->all();

        return new WorkingCalendar($now, $m, $holidays, (string) config('ppic.day_start', '07:00'), (int) PpicSettings::number('default_shifts'), PpicSettings::number('default_hours_per_shift'));
    }

    /** @return list<int> every machine id the Work Orders can use */
    public function machineIds(array $wos): array
    {
        $ids = [];
        foreach ($wos as $wo) {
            foreach ($wo->ops as $op) {
                $ids = array_merge($ids, array_keys($op->candidates), $op->fixed ? [$op->fixed['machine']] : []);
            }
        }

        return array_values(array_unique(array_merge([0], $ids)));
    }

    private function op(PrdWo $wo, PrdWoOperation $op, $cands, $stage, array $tools, CarbonImmutable $now, array $inks = [], array $down = []): PlanOp
    {
        // Confirmed and locked on a machine that is down now: the engine proposes a new place in the Draft (the officer decides at Confirm).
        $wasDown = $op->plan_status === 'CONFIRMED' && $op->is_locked && in_array((int) $op->machine_id, $down, true)
            && ! in_array($op->task_status, array_merge(self::DONE, self::WORKING), true);
        $stageName = strtoupper((string) ($stage[$op->process_id]->process_stage ?? 'PRESS'));
        $isDie = str_starts_with(strtoupper((string) ($stage[$op->process_id]->process_name ?? $op->process_name)), 'PLONG');
        $candidates = [];
        foreach ($cands as $c) {
            $candidates[(int) $c->machine_id] = ['rank' => (int) ($c->preference_rank ?? 99), 'rate' => $c->target_qty_per_hour !== null ? (float) $c->target_qty_per_hour : ((int) $c->machine_id === (int) $op->machine_id && $op->rate_band_value ? (float) $op->rate_band_value : null),
                'setup_base' => $c->setup_minutes !== null ? (int) $c->setup_minutes : null];
        }
        $name = strtoupper((string) $op->process_name);
        $wait = 0;
        foreach ($this->waitRules() as $key => $min) {
            if (str_starts_with($name, (string) $key)) {
                $wait = (int) $min;
            }
        }
        $gate = in_array('BLOCK', [$op->gate_material, $op->gate_tooling, $op->gate_artwork], true) ? 'BLOCK'
            : (in_array('WAIT', [$op->gate_material, $op->gate_tooling, $op->gate_artwork], true) ? 'WAIT' : 'READY');

        $proc = $stage[$op->process_id] ?? null;
        $kind = $proc && ! $proc->inhouse ? 'SUBCON' : (($proc && $proc->is_manual) || (! $candidates && in_array($stageName, ['QC', 'PACKING', 'LOGISTIC'], true)) ? 'MANUAL' : 'MACHINE');

        $facts = [
            'stage' => $stageName,
            'colours' => $wo->colour_count, 'spots' => [], 'ink_type' => 'CONVENTIONAL', 'gsm' => $wo->board_gsm,
            'sheet_l' => $wo->sheet_length_mm !== null ? (float) $wo->sheet_length_mm : null, 'sheet_w' => $wo->sheet_width_mm !== null ? (float) $wo->sheet_width_mm : null,
            'die' => $isDie ? ($wo->dieline_ref ?: null) : null,
            'plate' => $stageName === 'PRESS' && str_starts_with($name, 'CETAK') ? 'PL-'.$wo->product_id : null,
            'tool_min' => $tools[$wo->wo_id] ?? null,
            'inks' => $inks[$wo->wo_id] ?? [],
        ];

        return new PlanOp(
            id: (int) $op->operation_id, woId: (int) $wo->wo_id, seq: (int) $op->seq_no, processName: (string) $op->process_name, stage: $stageName,
            qtyIn: (float) $op->qty, candidates: $candidates, facts: $facts, waitAfterMinutes: $wait,
            minStart: $op->material_ready_at ? CarbonImmutable::parse($op->material_ready_at) : null,
            gate: $gate, gateNote: $op->gate_note, fixed: $wasDown ? null : $this->fixed($op, $now), kind: $kind, leadDays: (int) ($stage[$op->process_id]->subcon_lead_days ?? 3), wasDown: $wasDown,
        );
    }

    /** @return array{machine: int, start: CarbonImmutable, finish: CarbonImmutable}|null */
    private function fixed(PrdWoOperation $op, CarbonImmutable $now): ?array
    {
        $finished = in_array($op->task_status, self::DONE, true);
        $working = in_array($op->task_status, self::WORKING, true);
        $locked = $op->plan_status === 'CONFIRMED' && $op->is_locked && $op->target_start && $op->target_finish;
        $officer = $op->plan_status === 'DRAFT' && $op->planned_by === 'OFFICER' && $op->draft_machine_id && $op->draft_start && $op->draft_finish;

        if ($officer) {
            return ['machine' => (int) $op->draft_machine_id, 'start' => CarbonImmutable::parse($op->draft_start), 'finish' => CarbonImmutable::parse($op->draft_finish)];
        }
        if ($finished || $working || $locked) {
            $start = $op->actual_start ?? $op->target_start ?? $now;
            $finish = $finished ? ($op->actual_finish ?? $op->target_finish ?? $now) : ($op->target_finish ?? $now->addHour());

            return ['machine' => (int) ($op->machine_id ?: 0), 'start' => CarbonImmutable::parse($start), 'finish' => CarbonImmutable::parse($finish)];
        }

        return null;
    }

    /** @return array<int, list<string>> wo id => ink names per unit from Product Development (empty = only the colour count is known) */
    private function inksFor($wos): array
    {
        $partIds = DB::connection('tm2sdi')->table('iqp_part')->whereIn('id_part', $wos->pluck('source_iqp_part_id')->filter()->unique())->pluck('prod_part_id', 'id_part');
        $rows = DB::connection('tm2mst')->table('prod_part_ink')->whereIn('part_id', $partIds->filter()->unique())->orderBy('pass_seq')->orderBy('unit_no')->get(['part_id', 'ink_name'])->groupBy('part_id');
        $out = [];
        foreach ($wos as $wo) {
            $pid = $partIds[$wo->source_iqp_part_id] ?? null;
            $out[$wo->wo_id] = $pid ? $rows->get($pid, collect())->pluck('ink_name')->map(fn ($n) => strtoupper(trim((string) $n)))->filter()->unique()->values()->all() : [];
        }

        return $out;
    }

    /** @return array<int, ?int> wo id => minutes to mount the die of its dieline (first run) from the tooling master */
    private function toolMinutes($wos): array
    {
        $out = [];
        foreach ($wos as $wo) {
            if (! $wo->dieline_ref) {
                continue;
            }
            $m = DB::connection('tm2mst')->table('prod_tooling_asset')->where('dieline_ref', $wo->dieline_ref)->where('activate', 1)->value('setup_first_min');
            $out[$wo->wo_id] = $m !== null ? (int) $m : null;
        }

        return $out;
    }
}
