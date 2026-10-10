<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Master\ProdPart;
use App\Models\Prd\PrdPlanRun;
use App\Models\Sdi\IqpInquiry;
use App\Services\Scheduling\Operator\OperatorTimerService;
use App\Services\Scheduling\Plan\PlanLoader;
use App\Services\Scheduling\Plan\PpicSettings;
use App\Services\Scheduling\Plan\SetupTimeEngine;
use Carbon\CarbonImmutable;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;

/**
 * Data of the PPIC screens (Production Scheduling with its four views, Monitoring, Work Order). Only reads and shapes data for the
 * DevLib objects; the planning rules live in App\Services\Scheduling\Plan\*. Cross-database lookups are fetched once and matched in PHP.
 */
final class Ppic
{
    public const STAGE_TONE = ['PRESS' => 'accent', 'POSTPRESS' => 'info', 'FINISHING' => 'success', 'PREPRESS' => 'warn', 'QC' => 'violet', 'PACKING' => 'violet'];

    public const DONE = ['DONE', 'QC_HOLD', 'QC_PASSED', 'QC_REJECTED', 'SUBCONTRACT'];

    private static ?array $cache = null;

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfMinute();
    }

    // Midnight of yesterday: the Gantt clock starts there so work of the last day is still on the board and no hour is negative.
    public static function origin(): CarbonImmutable
    {
        return self::now()->subDay()->startOfDay();
    }

    /** Hours from the origin midnight, the continuous clock the Gantt uses. */
    public static function hours(CarbonImmutable $t): float
    {
        return round(self::origin()->diffInMinutes($t, false) / 60, 4);
    }

    public static function hm(float $h): string
    {
        $m = (int) round($h * 60);

        return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    }

    public static function at(float $h): CarbonImmutable
    {
        return self::origin()->addMinutes((int) round($h * 60));
    }

    public static function latestRun(): ?PrdPlanRun
    {
        return PrdPlanRun::query()->whereIn('status', ['DRAFT', 'CONFIRMED'])->orderByDesc('run_id')->first();
    }

    /** The plan in force: the last confirmed run that was not undone. */
    public static function lastConfirmedRun(): ?PrdPlanRun
    {
        return PrdPlanRun::query()->where('status', 'CONFIRMED')->orderByDesc('run_id')->first();
    }

    /**
     * Before and after for the Draft: the numbers of the plan in force next to the numbers of the Draft.
     *
     * @return list<array{label: string, now: string, draft: string, better: ?bool}>
     */
    public static function compare(): array
    {
        $draft = self::draftRun();
        if (! $draft) {
            return [];
        }
        $a = (array) ($draft->summary['baseline'] ?? []);   // the schedule in force, measured when the Draft was made
        $b = (array) ($draft->summary['metrics'] ?? []);
        $rows = [['late', 'Work Orders forecast late', true], ['attention', 'Operations that need attention', true], ['setup_minutes', 'Setup minutes in total', true],
            ['die_mounts', 'Die mounts', true], ['die_saved_minutes', 'Die setup minutes saved by grouping', false], ['washes', 'Ink washes', true], ['moved', 'Operations that move from the plan in force', null], ['load_avg', 'Average machine load (percent)', null]];
        $out = [];
        // Setup and die mounts: the Draft is measured on the same operations the plan in force has, so new Work Orders do not look like a loss.
        $same = ['setup_minutes' => 'setup_minutes_same_ops', 'die_mounts' => 'die_mounts_same_ops'];
        foreach ($rows as [$k, $label, $lowerIsBetter]) {
            $now = $a[$k] ?? null;
            $new = $b[$same[$k] ?? $k] ?? null;
            $out[] = ['label' => $label, 'now' => $now === null ? '—' : (string) $now, 'draft' => $new === null ? '—' : (string) $new,
                'better' => ($lowerIsBetter === null || $now === null || $new === null || $now == $new) ? null : ($lowerIsBetter ? $new < $now : $new > $now)];
        }

        return $out;
    }

    /**
     * Colour sequence of the print process: per press machine the jobs in planned order (Draft first, else confirmed) with their inks and the wash
     * the machine needs before each job. Inks come from Product Development (prod_part_ink); a job without them shows only its colour count.
     *
     * @return list<array{machine: string, jobs: list<array<string, mixed>>, wash_minutes: int}>
     */
    public static function colorSequence(): array
    {
        $ops = array_values(array_filter(self::operations(), fn ($o) => $o['stage'] === 'PRESS' && str_starts_with(strtoupper($o['process']), 'CETAK') && $o['machine_id'] && $o['start']));
        if (! $ops) {
            return [];
        }
        $wos = DB::connection('tm2prd')->table('prd_wo')->whereIn('wo_id', array_unique(array_column($ops, 'wo_id')))->get(['wo_id', 'source_iqp_part_id', 'colour_count', 'board_gsm', 'sheet_length_mm', 'sheet_width_mm'])->keyBy('wo_id');
        $partOf = DB::connection('tm2sdi')->table('iqp_part')->whereIn('id_part', $wos->pluck('source_iqp_part_id')->filter()->unique())->pluck('prod_part_id', 'id_part');
        $inkRows = DB::connection('tm2mst')->table('prod_part_ink')->whereIn('part_id', $partOf->filter()->unique())->orderBy('pass_seq')->orderBy('unit_no')->get(['part_id', 'ink_name', 'unit_no'])->groupBy('part_id');
        $engine = new SetupTimeEngine;
        $rules = PpicSettings::setupRules();

        $byMachine = [];
        foreach ($ops as $o) {
            $byMachine[$o['machine_id']][] = $o;
        }
        $out = [];
        foreach ($byMachine as $list) {
            usort($list, fn ($a, $b) => $a['start'] <=> $b['start']);
            $prev = null;
            $jobs = [];
            $total = 0;
            foreach ($list as $i => $o) {
                $w = $wos[$o['wo_id']] ?? null;
                $inks = $w && ($pid = $partOf[$w->source_iqp_part_id] ?? null) ? ($inkRows[$pid] ?? collect())->pluck('ink_name')->map(fn ($n) => strtoupper(trim((string) $n)))->filter()->unique()->values()->all() : [];
                $facts = ['colours' => $w->colour_count ?? null, 'inks' => $inks, 'spots' => [], 'ink_type' => 'CONVENTIONAL', 'gsm' => $w->board_gsm ?? null];
                $wash = ['minutes' => 0, 'label' => $prev === null ? '—' : t('Same inks')];
                $unknown = $prev !== null && ((empty($prev['inks']) && empty($prev['colours'])) || ($inks === [] && empty($facts['colours'])));
                if ($unknown) {
                    $wash = ['minutes' => 0, 'label' => t('Inks not known')];
                }
                if ($prev !== null && ! $unknown) {
                    foreach ($engine->between($prev, $facts, $rules, 'PRESS')['parts'] as $part) {
                        if (in_array($part['kind'], ['colour', 'ink_type'], true)) {
                            $wash = ['minutes' => $part['minutes'], 'label' => $part['label']];
                        }
                    }
                }
                $total += $wash['minutes'];
                $jobs[] = ['n' => $i + 1, 'start' => $o['start'], 'wo' => $o['wo_no'], 'product' => $o['product'], 'inks' => $inks, 'colours' => $w->colour_count ?? null,
                    'plan' => $o['plan_status'], 'wash' => $wash['minutes'], 'wash_label' => $wash['label'], 'known' => $inks !== [], 'unknown' => $unknown];
                $prev = $facts;
            }
            $out[] = ['machine' => (string) $list[0]['machine'], 'jobs' => $jobs, 'wash_minutes' => $total];
        }
        usort($out, fn ($a, $b) => strcmp($a['machine'], $b['machine']));

        return $out;
    }

    /**
     * Quantity that should have been made by now: target per hour times the run time so far (setup and pauses do not count), never above the planned good output.
     *
     * @param  array<string, mixed>  $op  a row of operations()
     * @param  array{run: int, good: float}  $sum  OperatorTimerService::summary()
     */
    public static function expectedQty(array $op, array $sum): ?float
    {
        if (! $op['target']) {
            return null;
        }

        return round(min((float) $op['qty_out'], (float) $op['target'] * $sum['run'] / 60), 0);
    }

    /**
     * What every machine with a running timer is doing right now, for the PPIC mirror of the operator screens.
     *
     * @return list<array<string, mixed>>
     */
    public static function shopFloor(): array
    {
        $open = DB::connection('tm2prd')->table('prd_operation_time_log')->whereNull('ended_at')->orderBy('machine_id')->get();
        if ($open->isEmpty()) {
            return [];
        }
        $svc = app(OperatorTimerService::class);
        $ops = collect(self::operations())->keyBy('op_id');
        $queue = collect(self::operations())->filter(fn ($o) => $o['plan_status'] === 'CONFIRMED' && $o['machine_id'] && $o['bucket'] !== 'done')->groupBy('machine_id');
        $reasons = OperatorTimerService::PAUSE_REASONS;
        $out = [];
        foreach ($open as $seg) {
            $o = $ops[(int) $seg->operation_id] ?? null;
            if (! $o) {
                continue;
            }
            $sum = $svc->summary((int) $seg->operation_id);
            $next = ($queue[$o['machine_id']] ?? collect())->where('op_id', '!=', $o['op_id'])->sortBy(fn ($x) => $x['start']?->timestamp ?? PHP_INT_MAX)->take(3);
            $out[] = ['machine' => (string) $o['machine'], 'wo' => $o['wo_no'], 'product' => $o['product'], 'process' => $o['process'], 'state' => $seg->kind, 'why' => $seg->kind === 'PAUSE' ? t($reasons[$seg->pause_reason] ?? 'Other') : null,
                'seconds' => $svc->elapsed($seg), 'planned' => $o['qty_out'], 'good' => $sum['good'], 'expected' => self::expectedQty($o, $sum), 'uom' => $o['uom'], 'operator' => $seg->operator,
                'next' => $next->map(fn ($x) => ['wo' => $x['wo_no'], 'product' => $x['product'], 'qty' => $x['qty'], 'start' => $x['start']])->values()->all()];
        }

        return $out;
    }

    /** Dieline, artwork and spec of the part a Work Order makes (Product Development), for the operator and PPIC screens. null = no part linked. @return array<string, mixed>|null */
    public static function jobVisual(int $woId): ?array
    {
        $wo = DB::connection('tm2prd')->table('prd_wo')->where('wo_id', $woId)->first();

        return $wo ? self::dielinePart($wo) : null;
    }

    public static function draftRun(): ?PrdPlanRun
    {
        return PrdPlanRun::query()->where('status', 'DRAFT')->orderByDesc('run_id')->first();
    }

    // ------------------------------------------------------------------ operations

    /**
     * Every operation of the active Work Orders with its effective placement (Draft first, else confirmed) as plain rows.
     *
     * @return list<array<string, mixed>>
     */
    public static function operations(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $prd = DB::connection('tm2prd');
        $rows = $prd->table('prd_wo_operation as o')->join('prd_wo as w', 'w.wo_id', '=', 'o.wo_id')
            ->whereIn('w.wo_status', PlanLoader::ACTIVE_WO)->orderBy('w.wo_id')->orderBy('o.seq_no')
            ->get(['o.*', 'w.wo_no', 'w.product_id', 'w.part_code', 'w.due_date', 'w.is_hot_job', 'w.source_co_id', 'w.customer_tier', 'w.qty as wo_qty', 'w.wo_status', 'w.ups_per_sheet']);
        if ($rows->isEmpty()) {
            return self::$cache = [];
        }

        $good = $prd->table('prd_wo_operation_output')->whereIn('operation_id', $rows->pluck('operation_id'))->selectRaw('operation_id, SUM(qty_good) q')->groupBy('operation_id')->pluck('q', 'operation_id');
        $products = DB::connection('tm2mst')->table('prod_product')->whereIn('id_product', $rows->pluck('product_id')->filter()->unique())->pluck('product_name', 'id_product');
        $orders = DB::connection('tm2sdi')->table('co_order')->whereIn('id_order', $rows->pluck('source_co_id')->filter()->unique())->pluck('customer_id', 'id_order');
        $customers = DB::connection('tm2mst')->table('cust_customer')->whereIn('id_customer', $orders->filter()->unique())->pluck('customer_name', 'id_customer');
        $machines = DB::connection('tm2mst')->table('prod_machine')->get(['machine_id', 'machine_code', 'machine_name', 'machine_type', 'machine_status'])->keyBy('machine_id');
        $stages = DB::connection('tm2mst')->table('prod_process')->pluck('process_stage', 'id_process');
        $cands = $prd->table('prd_wo_operation_machine')->whereIn('operation_id', $rows->pluck('operation_id'))->where('eligible', 1)->get()->groupBy('operation_id');

        $out = [];
        foreach ($rows as $o) {
            $draft = $o->plan_status === 'DRAFT';
            $m = $draft ? $o->draft_machine_id : $o->machine_id;
            $start = $draft ? $o->draft_start : $o->target_start;
            $finish = $draft ? $o->draft_finish : $o->target_finish;
            $qtyOut = (float) ($o->qty_out ?: $o->qty);
            $done = (float) ($good[$o->operation_id] ?? 0);
            $custId = $orders[$o->source_co_id] ?? null;
            $gates = [$o->gate_material ?: 'READY', $o->gate_tooling ?: 'READY', $o->gate_artwork ?: 'READY'];
            $out[] = [
                'op_id' => (int) $o->operation_id, 'wo_id' => (int) $o->wo_id, 'wo_no' => $o->wo_no, 'seq' => (int) $o->seq_no, 'process' => (string) $o->process_name,
                'process_id' => $o->process_id ? (int) $o->process_id : null, 'stage' => strtoupper((string) ($stages[$o->process_id] ?? 'PRESS')),
                'machine_id' => $m !== null ? (int) $m : null, 'machine' => $m ? ($machines[$m]->machine_code ?? '#'.$m).' · '.($machines[$m]->machine_name ?? '') : null,
                'machine_status' => $m ? ($machines[$m]->machine_status ?? null) : null, 'machine_type' => $m ? ($machines[$m]->machine_type ?? null) : null,
                'start' => $start ? CarbonImmutable::parse($start) : null, 'finish' => $finish ? CarbonImmutable::parse($finish) : null,
                'task_status' => $o->task_status, 'plan_status' => $o->plan_status ?: 'NONE', 'planned_by' => $o->planned_by,
                'qty' => (float) $o->qty, 'uom' => $o->qty_uom ?: $o->waste_uom, 'qty_out' => $qtyOut, 'target' => self::targetOn($m, $cands[$o->operation_id] ?? null, $o->rate_band_value),
                'waste' => $o->waste_qty !== null ? (float) $o->waste_qty : null, 'spoilage' => $o->tolerance_qty !== null ? (float) $o->tolerance_qty : null,
                'setup_base' => $o->setup_base_min, 'setup_change' => $o->setup_change_min, 'setup_tool' => $o->setup_tool_min,
                'setup' => (int) ($o->setup_base_min ?? 0) + (int) ($o->setup_change_min ?? 0) + (int) ($o->setup_tool_min ?? 0), 'run' => $o->run_minutes,
                'gates' => $gates, 'gate_note' => $o->gate_note, 'material_ready_at' => $o->material_ready_at ? CarbonImmutable::parse($o->material_ready_at) : null,
                'due' => $o->due_date ? CarbonImmutable::parse($o->due_date) : null, 'hot' => (bool) $o->is_hot_job, 'locked' => (bool) $o->is_locked, 'tier' => (int) ($o->customer_tier ?: 2),
                'customer' => $custId ? ($customers[$custId] ?? null) : null, 'product' => $products[$o->product_id] ?? null, 'part' => $o->part_code,
                'good' => $done, 'progress' => $qtyOut > 0 ? (int) min(100, round($done / $qtyOut * 100)) : 0,
                'actual_start' => $o->actual_start ? CarbonImmutable::parse($o->actual_start) : null, 'actual_finish' => $o->actual_finish ? CarbonImmutable::parse($o->actual_finish) : null,
                'actual_setup' => $o->actual_setup_min, 'candidates' => ($cands[$o->operation_id] ?? collect())->pluck('machine_id')->map(fn ($x) => (int) $x)->all(),
                'bucket' => self::bucket($o, $gates, $start !== null),
            ];
        }

        return self::$cache = $out;
    }

    /** Target per hour on the machine the operation is placed on (the machine rate wins over the value stored when the Work Order was made). */
    private static function targetOn(?int $machineId, ?iterable $candidates, mixed $stored): ?float
    {
        foreach ($candidates ?? [] as $c) {
            if ($machineId !== null && (int) $c->machine_id === (int) $machineId && $c->target_qty_per_hour !== null) {
                return (float) $c->target_qty_per_hour;
            }
        }

        return $stored !== null ? (float) $stored : null;
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    private static function bucket(object $o, array $gates, bool $placed): string
    {
        return match (true) {
            in_array($o->task_status, self::DONE, true) => 'done',
            $o->task_status === 'RUNNING' => 'running',
            in_array('BLOCK', $gates, true) || in_array('WAIT', $gates, true) && ! $placed => 'not_ready',
            $placed => 'queued',
            default => 'ready',
        };
    }

    /** Filter by process name, machine id and a search text. @return list<array<string, mixed>> */
    public static function filtered(array $f = []): array
    {
        $q = mb_strtolower(trim((string) ($f['q'] ?? '')));

        return array_values(array_filter(self::operations(), function ($r) use ($f, $q) {
            if (! empty($f['process']) && strtoupper($r['process']) !== strtoupper((string) $f['process'])) {
                return false;
            }
            if (! empty($f['machine']) && (int) $r['machine_id'] !== (int) $f['machine']) {
                return false;
            }

            return $q === '' || str_contains(mb_strtolower($r['wo_no'].' '.$r['customer'].' '.$r['product'].' '.$r['process']), $q);
        }));
    }

    /** @return array{processes: array<string, string>, machines: array<int, string>} filter choices */
    public static function filterOptions(): array
    {
        $procs = [];
        $mach = [];
        foreach (self::operations() as $r) {
            $procs[strtoupper($r['process'])] = $r['process'];
            if ($r['machine_id']) {
                $mach[$r['machine_id']] = $r['machine'];
            }
        }
        ksort($procs);
        asort($mach);

        return ['processes' => $procs, 'machines' => $mach];
    }

    // ------------------------------------------------------------------ Gantt

    /** Props for x-dl::gantt. @param list<array<string, mixed>> $ops */
    public static function gantt(array $ops): array
    {
        $now = self::now();
        $nowH = self::hours($now);
        $loads = (self::latestRun()?->summary['loads'] ?? []);
        $threshold = PpicSettings::number('bottleneck_load_pct');

        $placed = array_values(array_filter($ops, fn ($r) => $r['start'] && $r['finish'] && self::hours($r['finish']) > 0));
        $rowKeys = [];
        $rows = [];
        foreach ($placed as $r) {
            $key = $r['machine_id'] ? 'm'.$r['machine_id'] : 'm0';
            if (isset($rowKeys[$key])) {
                continue;
            }
            $rowKeys[$key] = true;
            $load = $r['machine_id'] ? ($loads[$r['machine_id']]['pct'] ?? null) : null;
            $rows[$key] = ['key' => $key, 'label' => $r['machine_id'] ? $r['machine'] : 'Crew and subcontract', 'section' => $r['machine_id'] ? strtoupper((string) $r['machine_type']) : 'NO MACHINE',
                'load' => $load !== null ? (int) round($load) : null, 'bottleneck' => $load !== null && $load >= $threshold];
        }
        $downs = self::downBands($nowH);
        foreach ($downs as $mid => $bands) {
            if (isset($rows['m'.$mid])) {
                $rows['m'.$mid]['down'] = $bands;
            }
        }
        // Rows of the same machine type stay together; the crew row goes last.
        uasort($rows, fn ($a, $b) => [$a['section'] === 'NO MACHINE', $a['section'], $a['label']] <=> [$b['section'] === 'NO MACHINE', $b['section'], $b['label']]);

        $cats = [];
        $tasks = [];
        foreach ($placed as $r) {
            $key = strtoupper($r['process']);
            $cats[$key] ??= ['label' => $r['process'], 'tone' => self::STAGE_TONE[$r['stage']] ?? 'accent'];
            $s = self::hours($r['start']);
            $e = self::hours($r['finish']);
            $forecast = self::forecastEnd($r, $e, $nowH);
            $gates = array_map(fn ($g) => strtolower($g) === 'ready' ? 'ok' : (strtolower($g) === 'wait' ? 'wait' : 'block'), $r['gates']);
            $elig = array_map(fn ($m) => 'm'.$m, $r['candidates']);
            $tasks[] = array_filter([
                'id' => $r['op_id'], 'row' => $r['machine_id'] ? 'm'.$r['machine_id'] : 'm0', 'label' => $r['wo_no'], 'process' => $key,
                'start' => self::hm($s), 'end' => self::hm($e), 'progress' => $r['progress'], 'setup' => $r['setup'] ?: null, 'gates' => $gates,
                'state' => $r['hot'] ? 'hot' : null, 'risk' => $r['due'] && $r['finish']->greaterThan($r['due']) ? true : null,
                'locked' => $r['locked'] ?: null, 'forecastEnd' => $forecast, 'eligible' => $r['machine_id'] ? $elig : null,
                'status' => $r['plan_status'] === 'DRAFT' ? 'Draft' : ($r['task_status'] === 'RUNNING' ? 'Running' : null),
            ], fn ($v) => $v !== null && $v !== [] && $v !== false);
            // Running / finished work is not draggable.
            if (in_array($r['task_status'], array_merge(['RUNNING'], self::DONE), true)) {
                $tasks[array_key_last($tasks)]['locked'] = true;
            }
        }

        $minH = $placed ? min(array_map(fn ($r) => self::hours($r['start']), $placed)) : $nowH;
        $maxH = $placed ? max(array_map(fn ($r) => self::hours($r['finish']), $placed)) : $nowH + 24;
        $start = (int) max(0, floor(min($minH, $nowH - 6) / 6) * 6);
        $end = (int) max($start + 24, min($start + 24 * 8, ceil($maxH / 6) * 6 + 6));
        $span = $end - $start;
        $lock = self::lockUntilHours($nowH);

        return [
            'rows' => array_values($rows), 'tasks' => $tasks, 'categories' => $cats,
            'originDate' => self::origin()->toDateString(), 'start' => $start, 'end' => $end, 'pxPerHour' => $span <= 48 ? 26 : ($span <= 96 ? 16 : 10), 'snap' => 15,
            'nowAt' => $nowH, 'lockUntil' => $lock, 'simulation' => true, 'submitLabel' => null,
            'draftLabel' => self::draftRun() ? 'DRAFT' : null, 'resourceLabel' => 'Machine',
            'unscheduled' => count(array_filter($ops, fn ($r) => ! $r['start'] && $r['bucket'] !== 'done')),
        ];
    }

    private static function lockUntilHours(float $nowH): float
    {
        $shifts = max(1, (int) PpicSettings::number('lock_shifts'));
        $hours = PpicSettings::number('default_hours_per_shift');

        return round($nowH + $shifts * $hours, 2);
    }

    /** @return array<int, list<array{from: float, to: float, label: string}>> */
    private static function downBands(float $nowH): array
    {
        $out = [];
        $rows = DB::connection('tm2mst')->table('prod_machine_downtime')->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', self::now()))->get(['machine_id', 'start_at', 'end_at', 'downtime_kind']);
        foreach ($rows as $d) {
            $from = max(0.0, self::hours(CarbonImmutable::parse($d->start_at)));
            $to = $d->end_at ? self::hours(CarbonImmutable::parse($d->end_at)) : $nowH + 8;
            $out[(int) $d->machine_id][] = ['from' => $from, 'to' => $to, 'label' => $d->end_at ? 'Down' : 'Down (open)'];
        }

        return $out;
    }

    /** Where the finish is heading when the work runs slower than planned (only for a running operation with progress). */
    private static function forecastEnd(array $r, float $plannedEnd, float $nowH): ?float
    {
        if ($r['task_status'] !== 'RUNNING' || ! $r['actual_start'] || $r['progress'] < 5 || $r['progress'] >= 100) {
            return null;
        }
        $elapsed = max(0.01, $nowH - self::hours($r['actual_start']));
        $forecast = self::hours($r['actual_start']) + $elapsed / ($r['progress'] / 100);

        return $forecast > $plannedEnd + 0.1 ? round($forecast, 3) : null;
    }

    // ------------------------------------------------------------------ Kanban / Calendar / Sheet

    /** @return array{columns: list<array>, cards: list<array>} */
    public static function kanban(array $ops): array
    {
        $cols = [['key' => 'not_ready', 'label' => 'Not ready', 'tone' => 'warn'], ['key' => 'ready', 'label' => 'Ready, not scheduled', 'tone' => 'success'],
            ['key' => 'queued', 'label' => 'Queued', 'tone' => 'accent'], ['key' => 'running', 'label' => 'Running', 'tone' => 'info'], ['key' => 'done', 'label' => 'Done', 'tone' => 'neutral']];
        $cards = [];
        foreach ($ops as $r) {
            $tags = array_values(array_filter([
                $r['machine'] ? ['label' => $r['machine'], 'tone' => 'neutral'] : null,
                $r['setup'] ? ['label' => 'Setup '.$r['setup'].' min', 'tone' => 'warn'] : null,
                $r['hot'] ? ['label' => 'HOT', 'tone' => 'danger'] : null,
                $r['plan_status'] === 'DRAFT' ? ['label' => 'Draft', 'tone' => 'info'] : null,
            ]));
            $cards[] = array_filter([
                'id' => $r['op_id'], 'col' => $r['bucket'], 'title' => $r['wo_no'].' · '.$r['process'], 'desc' => trim(($r['customer'] ?? '').' · '.($r['product'] ?? '').($r['gate_note'] ? ' · '.$r['gate_note'] : ''), ' ·'),
                'tags' => $tags, 'priority' => $r['hot'] ? 'High' : 'Medium', 'due' => $r['due']?->format('Y-m-d H:i'),
            ], fn ($v) => $v !== null && $v !== '');
        }

        return ['columns' => $cols, 'cards' => $cards];
    }

    /** @return list<array<string, mixed>> */
    public static function calendarEvents(array $ops): array
    {
        $ev = [];
        foreach ($ops as $r) {
            if (! $r['start'] || ! $r['finish']) {
                continue;
            }
            $ev[] = ['id' => (string) $r['op_id'], 'title' => $r['wo_no'].' · '.$r['process'].($r['machine'] ? ' · '.$r['machine'] : ''),
                'start' => $r['start']->format('Y-m-d H:i'), 'end' => $r['finish']->format('Y-m-d H:i'),
                'tone' => $r['hot'] ? 'danger' : (in_array('BLOCK', $r['gates'], true) ? 'danger' : (in_array('WAIT', $r['gates'], true) ? 'warn' : ($r['plan_status'] === 'DRAFT' ? 'info' : 'accent'))),
                'desc' => 'Setup '.$r['setup'].' min · '.($r['customer'] ?? '')];
        }

        return $ev;
    }

    /** Rows of the spreadsheet view. target / waste / spoilage are typed by the officer (override); the rest is read-only. */
    public static function sheetRows(array $ops): array
    {
        return array_map(fn ($r) => [
            'id' => $r['op_id'], 'wo' => $r['wo_no'], 'customer' => $r['customer'], 'process' => $r['process'], 'machine' => $r['machine'], 'qty' => $r['qty'], 'uom' => $r['uom'],
            'target' => $r['target'], 'waste' => $r['waste'], 'spoilage' => $r['spoilage'], 'setup' => $r['setup'], 'run' => $r['run'] !== null ? round($r['run'] / 60, 2) : null,
            'start' => $r['start'] ? Locale::dt($r['start']) : null, 'finish' => $r['finish'] ? Locale::dt($r['finish']) : null, 'due' => $r['due'] ? Locale::d($r['due']) : null,
            'slack' => ($r['finish'] && $r['due']) ? round($r['finish']->diffInMinutes($r['due'], false) / 60, 1) : null,
            'gates' => implode(' ', array_map(fn ($g) => $g === 'READY' ? 'OK' : $g, $r['gates'])), 'status' => $r['plan_status'] === 'DRAFT' ? 'DRAFT' : ($r['task_status']),
        ], $ops);
    }

    // ------------------------------------------------------------------ exceptions and stats

    /** What needs a person: from the last run plus live gate states. @return list<array{kind: string, wo: string, text: string, options: string}> */
    public static function exceptions(): array
    {
        $run = self::latestRun();
        $nos = DB::connection('tm2prd')->table('prd_wo')->pluck('wo_no', 'wo_id');
        $labels = ['LATE_RISK' => 'Late risk', 'WAITING' => 'Waiting', 'BLOCKED' => 'Blocked', 'CHAIN_BLOCKED' => 'Blocked', 'NO_MACHINE' => 'No machine', 'MOVED_FROM_DOWN' => 'Moved (machine down)'];
        $options = ['LATE_RISK' => 'Re-plan, move to another machine, overtime, split the lot, subcontract or ask the customer to move the date',
            'WAITING' => 'Follow up the purchase, or the job waits for its arrival', 'BLOCKED' => 'Clear the gate (material, tooling) then Re-plan',
            'CHAIN_BLOCKED' => 'Resolves when the earlier operation is unblocked', 'NO_MACHINE' => 'Check the process-machine master: no machine fits this job',
            'MOVED_FROM_DOWN' => 'Confirm the Draft to accept the move, or give the job priority and place it yourself'];
        $out = [];
        foreach ($run?->summary['exceptions'] ?? [] as $e) {
            $out[] = ['kind' => $labels[$e['kind']] ?? $e['kind'], 'wo' => $nos[$e['wo']] ?? '#'.$e['wo'], 'text' => (string) $e['message'], 'options' => $options[$e['kind']] ?? ''];
        }
        foreach ($run?->summary['loads'] ?? [] as $mid => $l) {
            if (($l['pct'] ?? 0) >= PpicSettings::number('bottleneck_load_pct')) {
                $out[] = ['kind' => 'Overload', 'wo' => '#'.$mid, 'text' => sprintf('Machine #%d load %s percent.', $mid, $l['pct']), 'options' => 'Move jobs to another eligible machine, overtime or subcontract'];
            }
        }

        $now = self::now();
        foreach (self::operations() as $o) {
            if ($o['bucket'] === 'done' || $o['plan_status'] === 'NONE') {
                continue;
            }
            if (in_array($o['machine_status'], ['BREAKDOWN', 'MAINTENANCE'], true)) {
                $out[] = ['kind' => 'Machine down', 'wo' => $o['wo_no'], 'text' => sprintf('%s on %s: the machine is %s.', $o['process'], $o['machine'], strtolower((string) $o['machine_status'])),
                    'options' => 'Re-plan: the engine plans around the downtime, or move the job to another eligible machine'];
            } elseif ($o['plan_status'] === 'CONFIRMED' && $o['finish'] && $o['finish']->lessThan($now) && $o['task_status'] !== 'DONE') {
                $out[] = ['kind' => 'Behind plan', 'wo' => $o['wo_no'], 'text' => sprintf('%s on %s should have finished %s and is not done (%d percent output).', $o['process'], $o['machine'], Locale::dt($o['finish']), $o['progress']),
                    'options' => 'Check with the operator, then Re-plan around locked jobs'];
            }
        }

        return $out;
    }

    /** @return array{planned: int, exceptions: int, run: ?PrdPlanRun, draft: bool, ops: int, ready: int, not_ready: int, queued: int, running: int} */
    public static function stats(array $ops): array
    {
        $by = array_count_values(array_column($ops, 'bucket'));
        $run = self::latestRun();

        return ['planned' => count(array_filter($ops, fn ($r) => $r['start'])), 'exceptions' => count(self::exceptions()), 'run' => $run, 'draft' => $run?->status === 'DRAFT',
            'ops' => count($ops), 'ready' => $by['ready'] ?? 0, 'not_ready' => $by['not_ready'] ?? 0, 'queued' => $by['queued'] ?? 0, 'running' => $by['running'] ?? 0];
    }

    // ------------------------------------------------------------------ incoming purchases

    /**
     * Open PO and PR lines for the materials the active Work Orders use, with the date the first operation needs them.
     *
     * @return list<array{type: string, doc: string, material: string, qty: float, arrives: ?string, needed: ?string, effect: string, wo: string}>
     */
    public static function incoming(): array
    {
        $prd = DB::connection('tm2prd');
        $lines = $prd->table('prd_wo_material as m')->join('prd_wo as w', 'w.wo_id', '=', 'm.wo_id')->whereIn('w.wo_status', PlanLoader::ACTIVE_WO)->whereNotNull('m.material_id')
            ->get(['m.material_id', 'w.wo_id', 'w.wo_no', 'w.due_date']);
        if ($lines->isEmpty()) {
            return [];
        }
        $ids = $lines->pluck('material_id')->unique()->values();
        $names = DB::connection('tm2mst')->table('mat_raw')->whereIn('material_id', $ids)->pluck('material_name', 'material_id');
        $firstStart = [];
        foreach (self::operations() as $r) {
            $t = $r['start'];
            if ($t && (! isset($firstStart[$r['wo_id']]) || $t->lessThan($firstStart[$r['wo_id']]))) {
                $firstStart[$r['wo_id']] = $t;
            }
        }
        $need = [];
        foreach ($lines as $l) {
            $when = $firstStart[$l->wo_id] ?? ($l->due_date ? CarbonImmutable::parse($l->due_date) : null);
            if ($when && (! isset($need[$l->material_id]) || $when->lessThan($need[$l->material_id]['at']))) {
                $need[$l->material_id] = ['at' => $when, 'wo' => $l->wo_no];
            }
        }

        $pur = DB::connection('tm2pur');
        $po = $pur->table('po_line as l')->join('po_order as o', 'o.po_id', '=', 'l.po_id')->whereIn('l.item_id', $ids)->where('o.stat_cancel', 0)
            ->whereNotIn('o.status', ['DRAFT', 'CANCELLED', 'CLOSED', 'RECEIVED', 'REJECTED'])->where('l.c_qty_outstanding', '>', 0)
            ->get(['l.item_id', 'o.doc_no', 'o.i_delivery_date as date', 'l.c_qty_outstanding as qty'])->map(fn ($r) => (array) $r + ['type' => 'PO']);
        $pr = $pur->table('pr_line as l')->join('pr_requisition as r', 'r.pr_id', '=', 'l.pr_id')->whereIn('l.item_id', $ids)->where('r.stat_cancel', 0)
            ->whereNotIn('r.status', ['REJECTED', 'CANCELLED', 'CLOSED', 'FULLY_ORDERED'])->whereRaw('l.i_qty > IFNULL(l.c_qty_ordered, 0)')
            ->get(['l.item_id', 'r.doc_no', DB::raw('COALESCE(l.i_need_date, r.i_need_date) as date'), DB::raw('l.i_qty - IFNULL(l.c_qty_ordered, 0) as qty')])->map(fn ($r) => (array) $r + ['type' => 'PR']);

        $out = [];
        foreach ($po->concat($pr) as $x) {
            $n = $need[$x['item_id']] ?? null;
            $arr = $x['date'] ? CarbonImmutable::parse($x['date']) : null;
            $late = $arr && $n && $arr->greaterThan($n['at']);
            $out[] = ['type' => $x['type'], 'doc' => (string) $x['doc_no'], 'material' => (string) ($names[$x['item_id']] ?? '#'.$x['item_id']), 'qty' => (float) $x['qty'],
                'arrives' => $arr ? Locale::d($arr) : null, 'needed' => $n ? Locale::dt($n['at']) : null, 'wo' => $n['wo'] ?? '',
                'effect' => $x['type'] === 'PR' ? 'Approve and order' : ($late ? 'Late for the job' : 'On time')];
        }
        usort($out, fn ($a, $b) => [$a['effect'] === 'On time', $a['arrives']] <=> [$b['effect'] === 'On time', $b['arrives']]);

        return $out;
    }

    // ------------------------------------------------------------------ monitoring

    /** Planned against actual for operations that started in the last days. @return array{kpi: array<string, mixed>, rows: list<array<string, mixed>>} */
    public static function monitoring(int $days = 14): array
    {
        $since = self::now()->subDays($days);
        $rows = [];
        $onTime = 0;
        $finished = 0;
        $setupPlan = 0;
        $setupAct = 0;
        foreach (self::operations() as $r) {
            if (! $r['actual_start'] || $r['actual_start']->lessThan($since)) {
                continue;
            }
            $late = $r['actual_finish'] && $r['finish'] && $r['actual_finish']->greaterThan($r['finish']);
            if ($r['actual_finish']) {
                $finished++;
                $onTime += $late ? 0 : 1;
            }
            if ($r['actual_setup'] !== null) {
                $setupPlan += $r['setup'];
                $setupAct += (int) $r['actual_setup'];
            }
            $rows[] = ['wo' => $r['wo_no'], 'process' => $r['process'], 'machine' => $r['machine'], 'planned_qty' => $r['qty_out'], 'good' => $r['good'],
                'allowance' => (float) ($r['waste'] ?? 0) + (float) ($r['spoilage'] ?? 0), 'setup' => $r['setup'].' / '.($r['actual_setup'] ?? '-'),
                'planned_finish' => $r['finish'] ? Locale::dt($r['finish']) : '—', 'actual_finish' => $r['actual_finish'] ? Locale::dt($r['actual_finish']) : 'running',
                'result' => $r['actual_finish'] ? ($late ? 'Late' : 'On time') : $r['progress'].'%'];
        }

        return ['kpi' => ['on_time' => $finished ? round($onTime / $finished * 100) : null, 'finished' => $finished, 'setup_plan' => $setupPlan, 'setup_actual' => $setupAct, 'rows' => count($rows)], 'rows' => $rows];
    }

    // ------------------------------------------------------------------ one Work Order

    /** Everything the Work Order screen shows. @return array<string, mixed>|null */
    public static function workOrder(int $id): ?array
    {
        $wo = DB::connection('tm2prd')->table('prd_wo')->where('wo_id', $id)->first();
        if (! $wo) {
            return null;
        }
        $ops = array_values(array_filter(self::operations(), fn ($r) => $r['wo_id'] === $id));
        $mats = DB::connection('tm2prd')->table('prd_wo_material')->where('wo_id', $id)->orderBy('seq_no')->get();
        $cands = DB::connection('tm2prd')->table('prd_wo_operation_machine as c')->whereIn('c.operation_id', array_column($ops, 'op_id'))->get()->groupBy('operation_id');
        $machines = DB::connection('tm2mst')->table('prod_machine')->pluck('machine_name', 'machine_id');
        $codes = DB::connection('tm2mst')->table('prod_machine')->pluck('machine_code', 'machine_id');

        foreach ($ops as &$o) {
            $o['cands'] = ($cands[$o['op_id']] ?? collect())->map(fn ($c) => ['machine' => ($codes[$c->machine_id] ?? '#'.$c->machine_id).' · '.($machines[$c->machine_id] ?? ''), 'eligible' => (bool) $c->eligible,
                'reason' => $c->reason, 'rank' => $c->preference_rank, 'target' => $c->target_qty_per_hour !== null ? (float) $c->target_qty_per_hour : null, 'setup' => $c->setup_minutes])->sortBy([['eligible', 'desc'], ['rank', 'asc']])->values()->all();
        }
        unset($o);

        return ['wo' => $wo, 'ops' => $ops, 'materials' => $mats, 'first' => $ops[0] ?? null, 'part' => self::dielinePart($wo)];
    }

    // ------------------------------------------------------------------ Create Work Order

    /**
     * Delivery plan lines of Confirmed Customer Orders that have no Work Order yet, with how complete their Product Development data is:
     * "full" = an inquiry with parts (BOP, routing, BOM come across), "simple" = a Work Order without operations is created.
     *
     * @return list<array<string, mixed>>
     */
    public static function pendingDeliveries(int $limit = 200): array
    {
        // Work Orders live in another database: ids with a Work Order are fetched apart, never joined (no cross-connection subquery).
        $hasWo = DB::connection('tm2prd')->table('prd_wo')->whereNotNull('source_delivery_id')->distinct()->pluck('source_delivery_id')->all();
        $rows = Cpdel::query()->with(['item.inquiry.parts'])->where('status', 'PLANNED')->whereNull('posted_at')->whereNotIn('id_delivery', $hasWo)
            ->orderBy('ship_date')->limit($limit)->get();
        $customers = DB::connection('tm2mst')->table('cust_customer')->whereIn('id_customer', $rows->pluck('order.customer_id')->filter()->unique())->pluck('customer_name', 'id_customer');
        $products = $rows->pluck('item.product_id')->filter()->unique()->all();
        // CO INSTANT points to a Master Product: the latest live inquiry of that product is the source, as WoGenerationService does.
        $byProduct = $products
            ? IqpInquiry::with('parts')->whereIn('product_id', $products)->where('stat_cancel', false)->orderBy('id_inquiry')->get()->keyBy('product_id')
            : collect();

        return $rows->map(function ($d) use ($customers, $byProduct) {
            $inq = $d->item?->inquiry ?? ($d->item?->product_id ? $byProduct->get($d->item->product_id) : null);
            $parts = $inq?->parts?->count() ?? 0;

            return [
                'id' => $d->id_delivery, 'co' => $d->order?->co_no, 'customer' => $customers[$d->order?->customer_id] ?? '—',
                'product' => $d->item?->description, 'qty' => (float) $d->ship_qty, 'ship' => $d->ship_date?->toDateString(),
                'parts' => $parts, 'complete' => $parts > 0,
            ];
        })->all();
    }

    /** Dieline, artwork and spec of the part this Work Order makes, read from Product Development (TMDieline). null = no part linked. @return array<string, mixed>|null */
    private static function dielinePart(object $wo): ?array
    {
        $partId = $wo->source_iqp_part_id
            ? DB::connection('tm2sdi')->table('iqp_part')->where('id_part', $wo->source_iqp_part_id)->value('prod_part_id')
            : null;
        $part = $partId ? ProdPart::find($partId) : null;

        if (! $part) {
            return null;
        }
        $visual = ProductMaster::part($part);
        // A part that has a dieline type and dimensions is drawn from the type even when no drawing elements were stored on the part.
        $visual['has_spec'] = $visual['has_spec'] || isset(Sales::dielineTypes()[$visual['type_code'] ?? '']);

        return $visual;
    }
}
