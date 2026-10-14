<?php

/*
 * REMARK
 * Tujuan       : Sumber data baca untuk layar PPIC: operasi, Gantt, simulasi per Process per Machine, Master Card otomatis, Bill of Material per WO.
 * Sumber hulu  : prd_wo, prd_wo_operation, prd_wo_material, prod_part*, iqp_part, prod_layout_group, prod_process_machine, v_rm_available.
 * Input        : Id WO atau daftar operasi; cache per request (Ppic::forget() bila data berubah).
 * Output       : Array siap tampil: operations(), gantt(), simulation()/linkedBoard(), live() (monitor Live Schedule), rowKey() (baris Gantt: mesin, atau SUBCON per proses; tanpa konsep crew), queue() (antrean per proses per mesin, dengan jeda "Idle before" dan alasannya dari MachineIdle), masterCard(), bomSheet(), bopSheet(), stockOf().
 * Dipakai oleh : PpicController, layar production-wo, ppic-color-sequence, production-scheduling.
 * Status       : Aktif di mockup (ddt*). Hanya membaca; tidak menulis database. pendingDeliveries(): Complete = tiap part punya dieline + artwork + ukuran (daftar yang kurang di "missing"). bomSheet(): kolom waste/spill (timpaan officer atau nilai ProDev). applyBlockedReason(): alasan APPLY mati (belum PROCESS, bahan kurang, gerbang BLOCK).
 */

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Master\ProdMasterCard;
use App\Models\Master\ProdPart;
use App\Models\Prd\PrdPlanRun;
use App\Models\Prd\PrdWo;
use App\Models\Sdi\IqpInquiry;
use App\Services\MasterCard\MasterCardShape;
use App\Services\Scheduling\Operator\OperatorTimerService;
use App\Services\Scheduling\Plan\AutoPlanRunner;
use App\Services\Scheduling\Plan\AvailableStock;
use App\Services\Scheduling\Plan\GateService;
use App\Services\Scheduling\Plan\PlanLoader;
use App\Services\Scheduling\Plan\PpicSettings;
use App\Services\Scheduling\Plan\SetupTimeEngine;
use App\Services\Scheduling\Sequence\SequenceSuggester;
use Carbon\CarbonImmutable;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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
                $jobs[] = ['n' => $i + 1, 'op_id' => $o['op_id'], 'machine_id' => $o['machine_id'], 'start' => $o['start'], 'wo_id' => $o['wo_id'], 'wo' => $o['wo_no'], 'product' => $o['product'], 'inks' => $inks, 'colours' => $w->colour_count ?? null,
                    'plan' => $o['plan_status'], 'wash' => $wash['minutes'], 'wash_label' => $wash['label'], 'known' => $inks !== [], 'unknown' => $unknown];
                $prev = $facts;
            }
            $out[] = ['machine' => (string) $list[0]['machine'], 'jobs' => $jobs, 'wash_minutes' => $total];
        }
        usort($out, fn ($a, $b) => strcmp($a['machine'], $b['machine']));

        return $out;
    }

    /**
     * Colour order requests from operators that the officer has not answered yet.
     *
     * @return list<array{id: int, op_id: int, before_op_id: int, wo: string, before_wo: string, actor: string, reason: string, at: string}>
     */
    public static function colorRequests(): array
    {
        $prd = DB::connection('tm2prd');
        $answered = $prd->table('prd_plan_decision')->whereIn('event', ['COLOR_ACCEPT', 'COLOR_REJECT'])->pluck('old_value')->map(fn ($v) => (int) $v)->all();
        $rows = $prd->table('prd_plan_decision')->where('event', 'COLOR_REQUEST')->whereNotIn('decision_id', $answered ?: [0])->orderBy('decision_id')->get();
        if ($rows->isEmpty()) {
            return [];
        }
        $opIds = $rows->pluck('operation_id')->merge($rows->pluck('new_value'))->filter()->map(fn ($v) => (int) $v)->unique()->all();
        $wo = $prd->table('prd_wo_operation as o')->join('prd_wo as w', 'w.wo_id', '=', 'o.wo_id')->whereIn('o.operation_id', $opIds)->pluck('w.wo_no', 'o.operation_id');

        return $rows->map(fn ($r) => ['id' => (int) $r->decision_id, 'op_id' => (int) $r->operation_id, 'before_op_id' => (int) $r->new_value,
            'wo' => (string) ($wo[$r->operation_id] ?? '#'.$r->operation_id), 'before_wo' => (string) ($wo[(int) $r->new_value] ?? '#'.$r->new_value),
            'actor' => (string) $r->actor, 'reason' => (string) $r->reason, 'at' => (string) $r->created_at])->all();
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
            $out[] = ['machine' => (string) $o['machine'], 'wo_id' => $o['wo_id'], 'wo' => $o['wo_no'], 'product' => $o['product'], 'process' => $o['process'], 'state' => $seg->kind, 'why' => $seg->kind === 'PAUSE' ? t($reasons[$seg->pause_reason] ?? 'Other') : null,
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
                'due' => $o->due_date ? CarbonImmutable::parse($o->due_date) : null, 'hot' => (bool) $o->is_hot_job, 'locked' => (bool) $o->is_locked, 'pending' => (bool) ($o->pending_move ?? false), 'tier' => (int) ($o->customer_tier ?: 2),
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

    /**
     * Row of the Gantt an operation sits on: 'm<machine id>' on a machine; a step without a machine (subcontract, QC, counting, packing -
     * "no machine = SUBCON", Jo 2026-10-13) gets one row per process, 'p:<PROCESS>', in the SUBCON group (a proposal Jo can undo).
     *
     * @param  array<string, mixed>  $op
     */
    public static function rowKey(array $op): string
    {
        return $op['machine_id'] ? 'm'.$op['machine_id'] : 'p:'.strtoupper((string) $op['process']);
    }

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
            $key = self::rowKey($r);
            if (isset($rowKeys[$key])) {
                continue;
            }
            $rowKeys[$key] = true;
            $load = $r['machine_id'] ? ($loads[$r['machine_id']]['pct'] ?? null) : null;
            $rows[$key] = ['key' => $key, 'label' => $r['machine_id'] ? $r['machine'] : $r['process'].' · subcon', 'section' => $r['machine_id'] ? strtoupper((string) $r['machine_type']) : 'SUBCON',
                'load' => $load !== null ? (int) round($load) : null, 'bottleneck' => $load !== null && $load >= $threshold];
        }
        $downs = self::downBands($nowH);
        foreach ($downs as $mid => $bands) {
            if (isset($rows['m'.$mid])) {
                $rows['m'.$mid]['down'] = $bands;
            }
        }
        // Rows of the same machine type stay together; the subcontract group goes last.
        uasort($rows, fn ($a, $b) => [$a['section'] === 'SUBCON', $a['section'], $a['label']] <=> [$b['section'] === 'SUBCON', $b['section'], $b['label']]);

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
                'id' => $r['op_id'], 'row' => self::rowKey($r), 'label' => $r['wo_no'], 'process' => $key,
                'start' => self::hm($s), 'end' => self::hm($e), 'progress' => $r['progress'], 'setup' => $r['setup'] ?: null, 'gates' => $gates,
                'state' => $r['hot'] ? 'hot' : null, 'risk' => $r['due'] && $r['finish']->greaterThan($r['due']) ? true : null,
                'locked' => $r['locked'] ?: null, 'queued' => $r['pending'] ?: null, 'forecastEnd' => $forecast, 'eligible' => $r['machine_id'] ? $elig : null,
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

    /**
     * Simulation for the Work Order form: the whole schedule in force and Drafts, every other Work Order greyed and locked, this one
     * as normal bars. Read only; nothing is written (the Draft the engines already prepared is what is shown).
     *
     * @return array<string, mixed>
     */
    public static function simulation(int $woId): array
    {
        // The Draft must reflect the schedule in force now: plan again when a plan was confirmed after the Draft was made.
        $draft = self::draftRun();
        $confirmed = self::lastConfirmedRun();
        if (! $draft || ($confirmed && $confirmed->run_id > $draft->run_id)) {
            app(AutoPlanRunner::class)->run('EVENT', 'ENGINE');
            self::forget();
        }
        $all = self::operations();
        $byOp = [];
        $infoByOp = [];
        foreach ($all as $r) {
            $byOp[$r['op_id']] = $r['wo_id'];
            $infoByOp[$r['op_id']] = $r;
        }
        $g = self::gantt($all);
        $woStatus = DB::connection('tm2prd')->table('prd_wo')->whereIn('wo_id', array_unique(array_values($byOp)))->pluck('wo_status', 'wo_id');
        foreach ($g['tasks'] as &$t) {
            // Simulation = every operation that is not CONFIRMED yet, all treated alike (Jo 2026-10-13): full colour, draggable (the server
            // checks again). CONFIRMED / running / finished / locked operations are a locked backdrop (marked live) so the officer knows
            // whose job is already fixed. The bars of the Work Order that is open carry their own marker; nobody is dimmed.
            $o = $infoByOp[$t['id']] ?? null;
            // A Work Order that was applied to the live schedule is fixed as a whole (even its operations that are still Draft).
            $applied = $o && ! in_array($woStatus[$o['wo_id']] ?? 'DRAFT', ['DRAFT', 'SIMULATED'], true);
            $fixed = ! $o || $applied || $o['plan_status'] === 'CONFIRMED' || $o['locked'] || ! empty($t['locked'])
                || in_array($o['task_status'], array_merge(['RUNNING'], self::DONE), true);
            if ($fixed) {
                $t['locked'] = true;
                $t['live'] = true;
            } else {
                $t['movable'] = true;
            }
            if (($byOp[$t['id']] ?? null) === $woId) {
                $t['mine'] = true;
            }
        }
        unset($t);

        return self::linkedBoard($woId, $g);
    }

    /**
     * Adds the cascading Process -> Machine choice to the Work Order simulation Gantt: Process = the BOP processes of this Work Order,
     * Machine = the machines able to run that process (prod_process_machine, active only), every capable machine gets a row even when
     * idle. Default machine = the one the engine placed the operation on. Also flags this Work Order's bars that overlap another
     * Work Order's bar on the same machine (clash). Read only.
     *
     * @param  array<string, mixed>  $g  result of gantt()
     * @return array<string, mixed>
     */
    public static function linkedBoard(int $woId, array $g): array
    {
        $all = self::operations();
        $mine = array_values(array_filter($all, fn ($r) => $r['wo_id'] === $woId));
        usort($mine, fn ($a, $b) => $a['seq'] <=> $b['seq']);
        $procs = [];
        $pid = [];
        $placedOn = [];
        foreach ($mine as $r) {
            $k = strtoupper($r['process']);
            $procs[$k] ??= $r['process'];
            if ($r['process_id']) {
                $pid[$k] = $r['process_id'];
            }
            if ($r['machine_id'] && ! isset($placedOn[$k])) {
                $placedOn[$k] = 'm'.$r['machine_id'];
            }
        }
        $caps = $pid ? DB::connection('tm2mst')->table('prod_process_machine as c')->join('prod_machine as m', 'm.machine_id', '=', 'c.machine_id')
            ->where('c.activate', 1)->where('m.activate', 1)->whereIn('c.process_id', array_values($pid))
            ->orderBy('c.preference_rank')->get(['c.process_id', 'm.machine_id', 'm.machine_code', 'm.machine_name', 'm.machine_type']) : collect();
        $rows = collect($g['rows'])->keyBy('key');
        $machines = [];
        foreach ($procs as $k => $label) {
            $machines[$k] = [];
            foreach ($caps->where('process_id', $pid[$k] ?? 0) as $c) {
                $rk = 'm'.$c->machine_id;
                if (! $rows->has($rk)) {
                    $rows[$rk] = ['key' => $rk, 'label' => $c->machine_code.' · '.$c->machine_name, 'section' => strtoupper((string) $c->machine_type)];
                }
                $machines[$k][] = $rk;
            }
            if (isset($placedOn[$k]) && ! in_array($placedOn[$k], $machines[$k], true)) {
                $machines[$k][] = $placedOn[$k];
            }
        }
        $g['rows'] = $rows->sortBy(fn ($r) => [$r['section'] === 'SUBCON', $r['section'], $r['label']])->values()->all();
        // Clash = this Work Order's bar overlaps another Work Order's bar on the same machine.
        $clash = [];
        foreach ($mine as $r) {
            if (! $r['machine_id'] || ! $r['start'] || ! $r['finish']) {
                continue;
            }
            foreach ($all as $o) {
                if ($o['wo_id'] !== $woId && $o['machine_id'] === $r['machine_id'] && $o['start'] && $o['finish'] && $o['start'] < $r['finish'] && $r['start'] < $o['finish']) {
                    $clash[$r['op_id']] = $clash[$o['op_id']] = true;
                }
            }
        }
        $info = [];
        foreach ($all as $r) {
            $info[$r['op_id']] = $r;
        }
        foreach ($g['tasks'] as &$t) {
            if (isset($clash[$t['id']])) {
                $t['clash'] = true;
            }
            // Every bar says which Work Order, product and quantity it is (and whether it is the live schedule or a Draft).
            if ($o = $info[$t['id']] ?? null) {
                $live = $o['plan_status'] === 'CONFIRMED' || $o['task_status'] === 'RUNNING';
                // Identity of every bar: Work Order, owner (customer), product, quantity, process, machine, status, time, progress.
                $t['label'] = $o['wo_no'].' · '.($o['product'] ?: '—').' · '.Locale::number($o['qty'], 0).' · '.($live ? 'Live' : 'Draft');
                $t['short'] = $o['wo_no'];
                $t['detail'] = implode(' · ', array_filter([$o['wo_no'], $o['customer'], $o['product'], trim((string) $o['part']),
                    Locale::number($o['qty'], 0).' '.$o['uom'], $o['process'], $o['machine'] ?: 'SUBCON', $live ? 'Live' : 'Draft',
                    Locale::dt($o['start']).' → '.Locale::dt($o['finish']), $o['progress'].'%']));
            }
        }
        unset($t);
        // The board is zoomed on what these machines do (every Work Order's bars on them), so short operations stay readable.
        $onBoard = collect($g['tasks'])->filter(fn ($t) => collect($machines)->flatten()->contains($t['row']));
        if ($onBoard->isNotEmpty()) {
            $hr = fn ($hm) => (int) explode(':', (string) $hm)[0] + ((int) (explode(':', (string) $hm)[1] ?? 0)) / 60;
            $g['start'] = (int) max(0, floor(($onBoard->map(fn ($t) => $hr($t['start']))->min() - 3) / 6) * 6);
            $g['end'] = (int) max($g['start'] + 24, ceil(($onBoard->map(fn ($t) => $hr($t['end']))->max() + 6) / 6) * 6);
            $g['pxPerHour'] = ($g['end'] - $g['start']) * 48 <= 6000 ? 48 : 24;
        }
        $first = array_key_first($procs);
        $g['linked'] = ['processes' => $procs, 'machines' => $machines, 'defaults' => $placedOn + array_map(fn ($m) => $m[0] ?? '', $machines),
            'process' => $first, 'machine' => $first !== null ? ($placedOn[$first] ?? ($machines[$first][0] ?? '')) : ''];
        $g['clashes'] = count(array_filter($mine, fn ($r) => isset($clash[$r['op_id']])));
        $dm = $g['linked']['machine'];
        $dp = $g['linked']['process'];
        $onDefault = array_filter($g['tasks'], fn ($t) => $t['row'] === $dm && $t['process'] === $dp);
        $g['shown'] = ['n' => count($onDefault), 'live' => count(array_filter($onDefault, fn ($t) => ! empty($t['live']))),
            'draft' => count(array_filter($onDefault, fn ($t) => empty($t['live']))), 'process' => $dp !== null ? ($procs[$dp] ?? '') : '',
            'machine' => (string) collect($g['rows'])->firstWhere('key', $dm)['label'] ?? ''];

        return $g;
    }

    /**
     * Job order learning panel of the Simulation (Jo 2026-10-14): the strip props for the machine the board shows, and, when $preview is true,
     * the shadow lane "Preview / suggested order" added to $g (dashed bars, nothing saved). Null = no machine to talk about.
     *
     * @param  array<string, mixed>  $g  result of simulation(); the shadow lane is added to it by reference
     * @return ?array<string, mixed>
     */
    public static function sequencePanel(array &$g, int $woId, bool $preview): ?array
    {
        $key = (string) ($g['linked']['machine'] ?? '');
        if (! preg_match('/^m([0-9]+)$/', $key, $m)) {
            return null;
        }
        $machine = (int) $m[1];
        $label = (string) (collect($g['rows'])->firstWhere('key', $key)['label'] ?? $key);
        $proc = (string) ($g['linked']['processes'][$g['linked']['process']] ?? '');
        $sub = $label.($proc !== '' ? ' · '.$proc : '');
        $st = SequenceSuggester::status();
        if (! $st['active']) {
            return ['mode' => 'learning', 'title' => 'Learning', 'subtitle' => $sub, 'value' => min($st['pairs'], $st['needed']), 'needed' => $st['needed'],
                'note' => 'The model needs '.$st['needed'].' officer order decisions (pairs of jobs you put in an order and applied) before it suggests an order.'];
        }
        $s = SequenceSuggester::suggest($machine);
        if (! $s || SequenceSuggester::dismissed($machine, $s['order'])) {
            return null;
        }
        $ops = collect(self::operations())->keyBy('op_id');
        $model = $s['model'];
        $url = fn (string $action) => route('v2.ppic.wo.sequence.'.$action, ['wo' => $woId, 'machine' => $machine]);
        $panel = ['mode' => 'ready', 'title' => 'Suggested order', 'subtitle' => $sub, 'items' => array_map(fn ($id) => (string) ($ops[$id]['wo_no'] ?? $id), $s['order']),
            'note' => 'Learned from '.(int) $model->arrangements_count.' officer arrangements · matches officer '.round((float) $model->holdout_accuracy_model * 100).'% vs engine '.round((float) $model->holdout_accuracy_engine * 100).'% (last 20% of APPLYs)',
            'previewing' => $preview, 'differs' => $s['differs'],
            'previewUrl' => route('v2.screen', 'production-wo').'?id='.$woId.'&open=sim'.($preview ? '' : '&preview=1'),
            'useUrl' => $s['differs'] ? $url('use') : null, 'dismissUrl' => $url('dismiss')];
        if (! $s['differs']) {
            $panel['note'] = 'The current order already matches the suggestion · '.$panel['note'];
        }
        if ($preview) {
            $lane = 'sg'.$machine;
            // The suggested jobs run back to back from the first slot of the current arrangement (durations are each job's own).
            $a = $ops[$s['current'][0]]['start'];
            foreach ($s['order'] as $k => $id) {
                $o = $ops[$id];
                $b = $a->addSeconds($o['finish']->getTimestamp() - $o['start']->getTimestamp());
                $g['tasks'][] = ['id' => 'g'.$id, 'row' => $lane, 'label' => $o['wo_no'], 'short' => $o['wo_no'], 'process' => strtoupper($o['process']), 'start' => self::hm(self::hours($a)), 'end' => self::hm(self::hours($b)),
                    'ghost' => true, 'locked' => true, 'detail' => 'Suggested #'.($k + 1).' · '.$o['wo_no'].' · '.Locale::dt($a).' → '.Locale::dt($b)];
                $a = $b;
            }
            $rows = [];
            foreach ($g['rows'] as $r) {
                $rows[] = $r;
                if ($r['key'] === $key) {
                    $rows[] = ['key' => $lane, 'label' => 'Preview / suggested order', 'section' => $r['section'], 'ghostOf' => $key];
                }
            }
            $g['rows'] = $rows;
            foreach ($g['linked']['machines'] as $p => $list) {
                $out = [];
                foreach ($list as $mk) {
                    $out[] = $mk;
                    if ($mk === $key) {
                        $out[] = $lane;
                    }
                }
                $g['linked']['machines'][$p] = $out;
            }
        }

        return $panel;
    }

    /**
     * Live Schedule monitor (read only): every operation that is CONFIRMED or running, on a Process -> Machine Gantt (All / All by default)
     * and as one detail table. Good = SUM of Production Output, Bad = SUM of its defect lines, operator from the time log.
     *
     * @return array{gantt: array<string, mixed>, rows: list<array<string, mixed>>, kpi: array<string, int>}
     */
    public static function live(): array
    {
        $prd = DB::connection('tm2prd');
        $ops = array_values(array_filter(self::operations(), fn ($r) => $r['plan_status'] === 'CONFIRMED' || $r['task_status'] === 'RUNNING'));
        $ids = array_column($ops, 'op_id');
        $bad = $prd->table('prd_wo_operation_output as o')->join('prd_wo_operation_output_defect as d', 'd.output_id', '=', 'o.output_id')
            ->whereIn('o.operation_id', $ids)->selectRaw('o.operation_id, SUM(d.qty) q')->groupBy('o.operation_id')->pluck('q', 'operation_id');
        $logs = $prd->table('prd_operation_time_log')->whereIn('operation_id', $ids)->orderBy('log_id')->get(['operation_id', 'operator', 'kind', 'ended_at'])->groupBy('operation_id')->map->last();
        $now = self::now();
        $status = function (array $o) use ($logs, $now): string {
            $seg = $logs[$o['op_id']] ?? null;

            return match (true) {
                $o['bucket'] === 'done' => 'Done',
                $seg && $seg->ended_at === null && $seg->kind === 'PAUSE' => 'Paused',
                $o['task_status'] === 'RUNNING' => 'Running',
                $o['finish'] && ($o['finish']->lessThan($now) || ($o['due'] && $o['finish']->greaterThan($o['due']))) => 'Late',
                $o['locked'] => 'Locked',
                default => 'Scheduled',
            };
        };

        $g = self::gantt($ops);
        $by = [];
        $rows = [];
        foreach ($ops as $o) {
            $st = $status($o);
            $by[$o['op_id']] = $o + ['live_status' => $st];
            $log = $logs[$o['op_id']] ?? null;
            $rows[] = ['id' => $o['op_id'], 'wo' => $o['wo_no'], 'customer' => (string) $o['customer'], 'product' => trim((string) $o['product'].' '.(string) $o['part']),
                'process' => $o['process'], 'machine' => $o['machine'] ?: 'SUBCON', 'target' => $o['qty_out'], 'uom' => $o['uom'], 'good' => $o['good'], 'bad' => (float) ($bad[$o['op_id']] ?? 0),
                'progress' => $o['progress'], 'plan_start' => $o['start'], 'plan_finish' => $o['finish'], 'actual_start' => $o['actual_start'], 'actual_finish' => $o['actual_finish'],
                'status' => $st, 'operator' => $log->operator ?? null, 'gates' => $o['gates'], 'due' => $o['due']];
        }
        usort($rows, fn ($a, $b) => [$a['status'] === 'Done', $a['plan_start']?->timestamp ?? PHP_INT_MAX] <=> [$b['status'] === 'Done', $b['plan_start']?->timestamp ?? PHP_INT_MAX]);

        $procs = [];
        $machines = [];
        foreach ($ops as $o) {
            $k = strtoupper($o['process']);
            $procs[$k] = $o['process'];
            $machines[$k][] = self::rowKey($o);
        }
        ksort($procs);
        foreach ($g['tasks'] as &$t) {
            $o = $by[$t['id']] ?? null;
            $t['locked'] = true;
            $t['live'] = true;
            if ($o) {
                $t['short'] = $o['wo_no'];
                $t['label'] = $o['wo_no'].' · '.($o['product'] ?: '—').' · '.Locale::number($o['qty'], 0).' · '.$o['live_status'];
                $t['detail'] = implode(' · ', array_filter([$o['wo_no'], $o['customer'], $o['product'], Locale::number($o['qty'], 0).' '.$o['uom'], $o['process'], $o['machine'] ?: 'SUBCON',
                    $o['live_status'], Locale::dt($o['start']).' → '.Locale::dt($o['finish']), $o['progress'].'%']));
            }
        }
        unset($t);
        $g['linked'] = ['processes' => $procs, 'machines' => array_map(fn ($m) => array_values(array_unique($m)), $machines), 'defaults' => [], 'process' => '', 'machine' => ''];
        $count = fn (string $st) => count(array_filter($rows, fn ($r) => $r['status'] === $st));

        return ['gantt' => $g, 'rows' => $rows, 'kpi' => ['total' => count($rows), 'running' => $count('Running'), 'paused' => $count('Paused'), 'late' => $count('Late'), 'scheduled' => $count('Scheduled') + $count('Locked'), 'done' => $count('Done')]];
    }

    /**
     * Engine check of one Work Order's operations: a blocked gate or an operation without a start (no machine slot) rejects. The same
     * result decides APPLY TO LIVE SCHEDULE; it is not shown as a table (engines work in the background).
     *
     * @param  list<array<string, mixed>>  $ops
     * @return array{ok: bool, reason: string}
     */
    public static function engineCheck(array $ops): array
    {
        $reasons = [];
        foreach ($ops as $o) {
            $why = in_array('BLOCK', $o['gates'], true) ? (string) ($o['gate_note'] ?: 'A gate is blocked.') : (! $o['start'] ? 'No machine slot found in the horizon.' : '');
            if ($why !== '') {
                $reasons[] = $o['process'].': '.$why;
            }
        }

        return ['ok' => $reasons === [] && $ops !== [], 'reason' => $ops === [] ? 'This Work Order has no operations.' : implode(' · ', array_unique($reasons))];
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

    /**
     * Choices of the Crew / Subcon column of the BOP sheet: crews = HRM work centers (their members are emp_employee_work_center_skill),
     * subcontract vendors = suppliers flagged is_subcon. Keys 'C:<id>' / 'S:<id>' (see PrdWoOperation::assignActor).
     *
     * @return array<string, string>
     */
    public static function actorOptions(): array
    {
        $m = DB::connection('tm2mst');
        $o = [];   // the sheet adds its own blank choice
        foreach ($m->table('emp_work_center')->where('activate', 1)->where('work_center_code', '!=', 'WC-LEGACY-UNMAPPED')->orderBy('work_center_code')->get(['work_center_id', 'work_center_code', 'work_center_name']) as $w) {
            $o['C:'.$w->work_center_id] = 'Crew · '.$w->work_center_code.' · '.$w->work_center_name;
        }
        foreach ($m->table('sup_supplier')->where('is_subcon', 1)->where('activate', 1)->orderBy('supplier_name')->get(['id_supplier', 'supplier_name']) as $s) {
            $o['S:'.$s->id_supplier] = 'Subcon · '.$s->supplier_name;
        }

        return $o;
    }

    /**
     * Engine rows the officer deleted from a Work Order (tombstones in prd_plan_decision, event ROW_DELETED, old_value JSON {wo, kind, key}):
     * kind OP = process id, kind MAT = "role|name". PROCESS must not write them again. @return list<string>
     */
    public static function deletedRows(int $woId, string $kind): array
    {
        return DB::connection('tm2prd')->table('prd_plan_decision')->where('event', 'ROW_DELETED')->where('old_value', 'like', '{"wo":'.$woId.',"kind":"'.$kind.'"%')
            ->pluck('old_value')->map(fn ($v) => (string) (json_decode((string) $v, true)['key'] ?? ''))->filter(fn ($k) => $k !== '')->values()->all();
    }

    /**
     * Rows the officer added to a Work Order (decision event ROW_ADDED, old_value JSON {wo, kind, key}): kind OP -> operation ids,
     * kind MAT -> material detail ids. PROCESS keeps them. @return list<int>
     */
    public static function addedRows(int $woId, string $kind): array
    {
        return DB::connection('tm2prd')->table('prd_plan_decision')->where('event', 'ROW_ADDED')->where('old_value', 'like', '{"wo":'.$woId.',"kind":"'.$kind.'"%')
            ->pluck('old_value')->map(fn ($v) => (int) (json_decode((string) $v, true)['key'] ?? 0))->filter()->values()->all();
    }

    /** Process choices of the BOP sheet (Add row): active master processes, id => name. @return array<string, string> */
    public static function processOptions(): array
    {
        return DB::connection('tm2mst')->table('prod_process')->where('activate', 1)->orderBy('routing_seq')->orderBy('process_name')->pluck('process_name', 'id_process')
            ->mapWithKeys(fn ($n, $id) => [(string) $id => (string) $n])->all();
    }

    /** Machine labels for the BOP sheet (id => "CODE · name") plus 'subcon' = no machine. @return array<string, string> */
    public static function machineOptions(): array
    {
        $o = ['subcon' => 'SUBCON'];
        foreach (DB::connection('tm2mst')->table('prod_machine')->orderBy('machine_code')->get(['machine_id', 'machine_code', 'machine_name']) as $m) {
            $o[(string) $m->machine_id] = $m->machine_code.' · '.$m->machine_name;
        }

        return $o;
    }

    /** Machines able to run each process (active capability and machine): process id => [machine id => label]. @return array<string, array<string, string>> */
    public static function machinesByProcess(): array
    {
        $label = self::machineOptions();
        // every process offers SUBCON (no machine) as a choice; machines follow in preference order
        $out = array_fill_keys(array_map('strval', array_keys(self::processOptions())), ['subcon' => 'SUBCON']);
        $rows = DB::connection('tm2mst')->table('prod_process_machine as c')->join('prod_machine as m', 'm.machine_id', '=', 'c.machine_id')
            ->where('c.activate', 1)->where('m.activate', 1)->orderBy('c.preference_rank')->get(['c.process_id', 'c.machine_id']);
        foreach ($rows as $r) {
            $out[(string) $r->process_id][(string) $r->machine_id] = $label[(string) $r->machine_id] ?? '#'.$r->machine_id;
        }

        return $out;
    }

    /** Material roles offered by BOM Add row (what the engine itself uses). @return list<string> */
    public static function materialRoles(): array
    {
        return ['KERTAS PLANO', 'SHEET', 'TINTA', 'PLASTIK LAMINASI', 'LEM LAMINASI', 'LEM LAMINASI BAHAN', 'VARNISH UV', 'FOIL HOT PRINT', 'TALI PACK', 'PLASTIK PACK'];
    }

    /** Rows of the BOP and schedule sheet of one Work Order. Target and Crew / Subcon are the editable cells; Source says whether the officer overrode the target. @return list<array<string, mixed>> */
    public static function bopSheet(int $woId): array
    {
        $d = self::workOrder($woId);
        if (! $d) {
            return [];
        }
        $last = DB::connection('tm2prd')->table('prd_plan_decision')->where('event', 'TARGET')->whereIn('operation_id', array_column($d['ops'], 'op_id'))
            ->orderBy('decision_id')->get(['operation_id', 'reason', 'new_value'])->keyBy('operation_id');
        $gate = ['READY' => 'Ready', 'WAIT' => 'Waiting', 'BLOCK' => 'Blocked'];
        $actor = DB::connection('tm2prd')->table('prd_wo_operation')->where('wo_id', $woId)->get(['operation_id', 'crew_work_center_id', 'subcon_supplier_id'])
            ->mapWithKeys(fn ($r) => [(int) $r->operation_id => $r->crew_work_center_id ? 'C:'.$r->crew_work_center_id : ($r->subcon_supplier_id ? 'S:'.$r->subcon_supplier_id : '')]);

        $ops = $d['ops'];
        usort($ops, fn ($a, $b) => $a['seq'] <=> $b['seq']);
        // A BOP order that waits for PROCESS is shown at once (the rows move), the schedule columns follow after PROCESS.
        if ($pending = self::pendingHeader($woId)['order']['ids'] ?? null) {
            $pos = array_flip(array_map('intval', $pending));
            usort($ops, fn ($a, $b) => ($pos[$a['op_id']] ?? 9999) <=> ($pos[$b['op_id']] ?? 9999));
        }

        return array_map(fn ($o) => [
            // process / machine hold master ids (the sheet shows their names); a name without id (unmatched BOP step) shows as text
            'id' => $o['op_id'], 'seq' => $o['seq'], 'process' => $o['process_id'] ? (string) $o['process_id'] : (string) $o['process'],
            'machine' => $o['machine_id'] ? (string) $o['machine_id'] : 'subcon',
            'qty_in' => Locale::number($o['qty'], 0).' '.$o['uom'], 'qty_out' => Locale::number($o['qty_out'], 0).' '.$o['uom'],
            'target' => $o['target'] !== null ? round((float) $o['target'], 2) : null,
            'source' => isset($last[$o['op_id']]) ? 'Override · '.$last[$o['op_id']]->reason : 'Engine',
            'actor' => $actor[$o['op_id']] ?? '',
            'setup' => $o['setup'] ?: '—', 'run' => $o['run'] !== null ? Locale::decimal($o['run'] / 60, 2) : '—',
            'start' => $o['start']?->format('Y-m-d H:i:s') ?? '', 'finish' => $o['finish']?->format('Y-m-d H:i:s') ?? '',
            'status' => $o['plan_status'] === 'DRAFT' ? 'Draft' : (string) $o['task_status'],
            'gates' => implode(' / ', array_map(fn ($x) => $gate[$x] ?? $x, $o['gates'])),
        ], $ops);
    }

    /**
     * Bill of Material of one Work Order, ordered by the BOP operation that uses each line ("which process needs which material").
     * ATTENTION-ENGINE-01 / 02 / 03 / 04 / 05: lihat docs/ENGINE-ATTENTION.md.
     * Formula (Jo 2026-10-13): Release = Available (an officer value wins, 0..Available), Purchase = max(0, Required - Release).
     * BEFORE: Release (untouched) = min(Available, Required), limited to 0..Required, Purchase = Required - Release.
     * Source: GateService::release / purchase. Required comes from the ProDev engine (SpecEngine::figures) and may be edited.
     * `edited` = differs from the engine defaults.
     *
     * @return list<array<string, mixed>>
     */
    public static function bomSheet(int $woId): array
    {
        $prd = DB::connection('tm2prd');
        $lines = $prd->table('prd_wo_material')->where('wo_id', $woId)->orderBy('seq_no')->get();
        if ($lines->isEmpty()) {
            return [];
        }
        $opSeq = $prd->table('prd_wo_operation')->where('wo_id', $woId)->selectRaw('process_id, MIN(seq_no) s')->groupBy('process_id')->pluck('s', 'process_id');
        $names = DB::connection('tm2mst')->table('prod_process')->whereIn('id_process', $lines->pluck('process_id')->filter()->unique())->pluck('process_name', 'id_process');
        $stock = self::stockOf($lines->pluck('material_id'));
        $added = self::addedRows($woId, 'MAT');

        return $lines->map(function ($m) use ($opSeq, $names, $stock, $added) {
            $req = (float) $m->qty_required;
            $avail = (float) ($stock[$m->material_id]['available'] ?? 0);
            $rel = GateService::release($m->qty_release !== null ? (float) $m->qty_release : null, $avail);
            // Waste / Spill: the officer's override, else the ProDev value (null = ProDev gave none in this unit).
            $waste = $m->waste_override_qty ?? $m->waste_engine_qty;
            $spill = $m->spill_override_qty ?? $m->spill_engine_qty;
            $wasteEdited = $m->waste_override_qty !== null && abs((float) $m->waste_override_qty - (float) $m->waste_engine_qty) > 0.00005;
            $spillEdited = $m->spill_override_qty !== null && abs((float) $m->spill_override_qty - (float) $m->spill_engine_qty) > 0.00005;
            $mark = ($wasteEdited || $spillEdited) ? ' ✎ waste/spill edited (ProDev: '.Locale::decimal((float) $m->waste_engine_qty, 2).' / '.Locale::decimal((float) $m->spill_engine_qty, 2).')' : '';

            return [
                'id' => (int) $m->detail_id,
                'order' => $m->process_id ? ($opSeq[$m->process_id] ?? 9999) : 99999,
                'process' => $m->process_id && isset($opSeq[$m->process_id]) ? (string) $m->process_id : ($m->process_id ? (string) ($names[$m->process_id] ?? '—').' (not in this BOP)' : ''),
                'material' => (string) $m->material_name.(in_array((int) $m->detail_id, $added, true) ? ' ✚ added' : ($m->override_reason !== null ? ' ✎ edited (engine: '.Locale::decimal((float) $m->qty_standar, 2).')' : '')).$mark,
                'role' => (string) $m->material_role,
                'required' => round($req, 2),
                'waste' => $waste !== null ? round((float) $waste, 2) : null,
                'spill' => $spill !== null ? round((float) $spill, 2) : null,
                'uom' => (string) $m->uom,
                'onhand' => isset($stock[$m->material_id]) ? Locale::decimal($stock[$m->material_id]['onhand'], 2) : '—',
                'available' => isset($stock[$m->material_id]) ? Locale::decimal($stock[$m->material_id]['available'], 2) : '—',
                'release' => round($rel, 2),
                'purchase' => round(GateService::purchase($req, $rel), 2),
                'avail_qty' => $avail,
                'edited' => $m->override_reason !== null,
            ];
        })->sortBy([['order', 'asc'], ['id', 'asc']])->values()->all();
    }

    /**
     * Queue of every process on every machine: what is being worked on or waits there, from all active Work Orders (Running, Confirmed,
     * Draft; cancelled ones are not loaded). Progress = good quantity recorded for the operation (prd_wo_operation_output, the table
     * OperatorTimerService::addQuantity writes) against the operation's output quantity. Start = actual start for a Running operation,
     * else the scheduled start; End = scheduled finish.
     *
     * @return array{processes: array<string, string>, machines: array<string, array<string, string>>, rows: list<array<string, mixed>>, process: ?string, machine: string, running: int, queued: int}
     */
    public static function queue(?int $currentWoId = null): array
    {
        // An operation without a machine is SUBCON (Jo's rule); it is queued under the machine "SUBCON" of its process.
        $ops = array_values(array_filter(self::operations(), fn ($r) => $r['start'] && $r['finish']
            && ! in_array($r['task_status'], self::DONE, true)));
        usort($ops, fn ($a, $b) => $a['start'] <=> $b['start']);
        // Idle time before each job on its machine, with the reason (the machine should run continuously; a gap needs a stated cause).
        $loader = app(PlanLoader::class);
        $all = self::operations();
        $idle = MachineIdle::explain($all, $loader->calendar(self::now(), array_values(array_unique(array_filter(array_column($all, 'machine_id'))))), $loader->waitRules(), self::now());

        $processes = [];
        $pid = [];
        $rows = [];
        foreach ($ops as $r) {
            $k = strtoupper($r['process']);
            $processes[$k] ??= $r['process'];
            if ($r['process_id']) {
                $pid[$k] = $r['process_id'];
            }
            $running = $r['task_status'] === 'RUNNING';
            $rows[] = [
                'process' => $k, 'machine' => $r['machine_id'] ? 'm'.$r['machine_id'] : 'subcon', 'machine_name' => $r['machine'] ?: 'SUBCON',
                'status' => $running ? 'Running' : ($r['plan_status'] === 'CONFIRMED' ? 'Confirmed' : 'Draft'),
                'wo' => $r['wo_no'], 'item' => trim(($r['product'] ?: '—').' · '.($r['part'] ?: '')), 'qty' => Locale::number($r['qty_out'], 0).' '.$r['uom'],
                // No machine = SUBCON: progress only when output was recorded for it, otherwise null (shown as a dash).
                'progress' => $r['machine_id'] ? ($running || $r['progress'] > 0 ? $r['progress'] : 0) : ($r['good'] > 0 || $running ? $r['progress'] : null),
                'start' => Locale::dt($running && $r['actual_start'] ? $r['actual_start'] : $r['start']), 'end' => Locale::dt($r['finish']),
                'mine' => $currentWoId !== null && $r['wo_id'] === $currentWoId, 'wo_id' => $r['wo_id'], 'seq' => $r['seq'],
                'idle' => isset($idle[$r['op_id']]) ? MachineIdle::duration($idle[$r['op_id']]['minutes']).' · '.$idle[$r['op_id']]['reason'] : '',
                'op_id' => $r['op_id'], 'machine_id' => $r['machine_id'],
            ];
        }
        // Job order learning: the Suggested column exists only while a model is ACTIVE; only Draft rows carry a value, an unchanged place shows nothing.
        $seqActive = SequenceSuggester::active() !== null;
        if ($seqActive) {
            $sug = [];
            foreach ($rows as &$row) {
                if ($row['status'] !== 'Draft' || ! $row['machine_id']) {
                    continue;
                }
                $sug[$row['machine_id']] ??= SequenceSuggester::suggest((int) $row['machine_id']);
                $rk = $sug[$row['machine_id']]['rank'][$row['op_id']] ?? null;
                if ($rk && $rk['suggested'] !== $rk['now']) {
                    $row['suggested'] = '#'.$rk['suggested'].' · now #'.$rk['now'].' · differs '.($rk['suggested'] < $rk['now'] ? '▲' : '▼');
                }
            }
            unset($row);
        }
        ksort($processes);
        $machines = [];
        $label = [];
        foreach ($rows as $r) {
            $label[$r['machine']] = $r['machine_name'];
        }
        $caps = $pid ? DB::connection('tm2mst')->table('prod_process_machine as c')->join('prod_machine as m', 'm.machine_id', '=', 'c.machine_id')
            ->where('c.activate', 1)->where('m.activate', 1)->whereIn('c.process_id', array_values($pid))->orderBy('m.machine_code')
            ->get(['c.process_id', 'm.machine_id', 'm.machine_code', 'm.machine_name']) : collect();
        foreach ($processes as $k => $name) {
            $machines[$k] = [];
            foreach ($caps->where('process_id', $pid[$k] ?? 0) as $c) {
                $machines[$k]['m'.$c->machine_id] = $c->machine_code.' · '.$c->machine_name;
            }
            foreach ($rows as $r) {
                if ($r['process'] === $k && ! isset($machines[$k][$r['machine']])) {
                    $machines[$k][$r['machine']] = (string) $r['machine_name'];
                }
            }
        }
        // Opens on the machine of the Work Order that is open (the same one the Simulation shows), else on the running job.
        $mine = $currentWoId !== null ? collect($rows)->where('wo_id', $currentWoId)->sortBy('seq')->first() : null;
        $first = $mine ?? collect($rows)->firstWhere('status', 'Running') ?? ($rows[0] ?? null);

        // Open WO: every process on its machine (what the Simulation row shows); otherwise the process and machine of the running job.
        return ['processes' => $processes, 'machines' => $machines, 'rows' => $rows, 'process' => $mine ? '' : ($first['process'] ?? array_key_first($processes)),
            'machine' => $first['machine'] ?? '', 'running' => count(array_filter($rows, fn ($r) => $r['status'] === 'Running')), 'queued' => count($rows), 'suggested' => $seqActive];
    }

    /**
     * Fingerprint of everything PROCESS depends on for one Work Order: the Work Order (qty, due), its operations (process, order, target,
     * quantities), its material lines (material, Required, Release, Purchase, substitute), the priority (Hot job / Move up) and the
     * Inquiry / ProDev part it came from (updated stamps of
     * the Inquiry part, the ProDev part and its BOP / BOM / ink / finishing rows). A moved bar is NOT part of it (a move is a pending change).
     */
    public static function fingerprint(int $woId): string
    {
        $prd = DB::connection('tm2prd');
        $wo = $prd->table('prd_wo')->where('wo_id', $woId)->first(['qty', 'due_date', 'earliest_start', 'source_iqp_part_id', 'is_hot_job', 'priority']);
        if (! $wo) {
            return '';
        }
        $ops = $prd->table('prd_wo_operation')->where('wo_id', $woId)->orderBy('seq_no')->get(['process_id', 'seq_no', 'rate_band_value', 'qty', 'waste_qty'])->map(fn ($o) => (array) $o)->all();
        $mats = $prd->table('prd_wo_material')->where('wo_id', $woId)->orderBy('detail_id')->get(['material_id', 'qty_required', 'qty_release', 'qty_purchase', 'is_substituted', 'substitute_material_id', 'waste_override_qty', 'spill_override_qty'])->map(fn ($o) => (array) $o)->all();
        $subs = $prd->table('prd_wo_material_substitution')->where('wo_id', $woId)->where('is_active', 1)->orderBy('id')
            ->get(['original_rawmat_id', 'substitute_rawmat_id', 'approval_status'])->map(fn ($o) => (array) $o)->all();
        $src = [];
        if ($wo->source_iqp_part_id) {
            $iqp = DB::connection('tm2sdi')->table('iqp_part')->where('id_part', $wo->source_iqp_part_id)->first(['updated_at', 'prod_part_id']);
            $src[] = $iqp?->updated_at;
            if ($iqp?->prod_part_id) {
                $m = DB::connection('tm2mst');
                $src[] = $m->table('prod_part')->where('id_part', $iqp->prod_part_id)->value('updated_at');
                foreach (['prod_part_process', 'prod_part_material', 'prod_part_ink', 'prod_part_finishing'] as $t) {
                    // tables without an updated stamp are fingerprinted by their row count
                    $src[] = Schema::connection('tm2mst')->hasColumn($t, 'updated_at')
                        ? $m->table($t)->where('part_id', $iqp->prod_part_id)->max('updated_at') : $m->table($t)->where('part_id', $iqp->prod_part_id)->count();
                }
            }
        }

        return sha1(json_encode([(string) $wo->qty, (string) $wo->due_date, (string) $wo->earliest_start, (int) $wo->is_hot_job, (string) $wo->priority, $ops, $mats, $subs, $src]));
    }

    /** PROCESS is needed: the inputs changed since the last PROCESS (or it never ran), or officer drags are waiting. */
    public static function processState(int $woId): array
    {
        $stored = DB::connection('tm2prd')->table('prd_wo')->where('wo_id', $woId)->value('process_hash');
        // pending = dragged bars (all) + this Work Order's new BOP order / priority waiting for PROCESS
        $pending = (int) DB::connection('tm2prd')->table('prd_wo_operation')->where('pending_move', 1)->count() + count(self::pendingHeader($woId));
        $changed = $stored !== self::fingerprint($woId);

        return ['pending' => $pending, 'changed' => $changed, 'n' => $pending + ($changed ? 1 : 0), 'needed' => $pending > 0 || $changed];
    }

    /**
     * Work Orders that APPLY ALL would apply: every Work Order that still has a Draft (not confirmed) operation on this process and
     * machine. $processKey '' = all processes; $machine '' = all machines; 'subcon' = operations without a machine.
     *
     * @return list<array{wo_id: int, wo_no: string}>
     */
    public static function applyCandidates(string $processKey, string $machine = ''): array
    {
        $out = [];
        foreach (self::operations() as $r) {
            $mk = $r['machine_id'] ? 'm'.$r['machine_id'] : 'subcon';
            if (($processKey !== '' && strtoupper($r['process']) !== strtoupper($processKey)) || ($machine !== '' && $mk !== $machine)) {
                continue;
            }
            if ($r['plan_status'] === 'CONFIRMED' || in_array($r['task_status'], array_merge(['RUNNING'], self::DONE), true)) {
                continue;
            }
            $out[$r['wo_id']] = ['wo_id' => $r['wo_id'], 'wo_no' => $r['wo_no']];
        }
        $status = DB::connection('tm2prd')->table('prd_wo')->whereIn('wo_id', array_keys($out))->pluck('wo_status', 'wo_id');

        return array_values(array_filter($out, fn ($x) => in_array($status[$x['wo_id']] ?? '', ['DRAFT', 'SIMULATED'], true)));
    }

    /**
     * Why APPLY TO LIVE SCHEDULE is not allowed yet (null = allowed): not PROCESSed (or data changed after it), raw material not ready (the
     * material gate's own shortage list), and, when $ops is given, a blocked gate or no machine slot (engineCheck). Rules from Jo 2026-10-14.
     */
    public static function applyBlockedReason(int $woId, ?array $ops = null): ?string
    {
        $state = self::processState($woId);
        if ($state['needed']) {
            $never = DB::connection('tm2prd')->table('prd_wo')->where('wo_id', $woId)->value('process_hash') === null;

            return t($never || $state['pending'] > 0 ? 'Click PROCESS first.' : 'Data changed, click PROCESS again.');
        }
        $wo = PrdWo::find($woId);
        if ($wo && ($short = app(GateService::class)->materialShortages($wo))) {
            return tf('Material is not ready, so it cannot be applied: :lines', ['lines' => raw(GateService::shortageText($short))]);
        }
        $check = $ops === null ? ['ok' => true] : self::engineCheck($ops);

        return $check['ok'] ? null : t('Rejected').': '.$check['reason'];
    }

    /** The order / priority change of a Work Order that waits for PROCESS: ['order' => ['ids', 'reason'], 'priority' => ['kind', 'reason']]. @return array<string, array<string, mixed>> */
    public static function pendingHeader(int $woId): array
    {
        $raw = DB::connection('tm2prd')->table('prd_wo')->where('wo_id', $woId)->value('pending_header');

        return (array) json_decode((string) $raw, true);
    }

    /** True when an officer changed the order of this Work Order's operations away from the ProDev order. */
    public static function bopOrderChanged(int $woId): bool
    {
        $ids = DB::connection('tm2prd')->table('prd_wo_operation')->where('wo_id', $woId)->pluck('operation_id')->all();
        $last = DB::connection('tm2prd')->table('prd_plan_decision')->where('event', 'RESEQUENCE')->whereIn('operation_id', $ids)->orderByDesc('decision_id')->first();
        $first = DB::connection('tm2prd')->table('prd_plan_decision')->where('event', 'RESEQUENCE')->whereIn('operation_id', $ids)->orderBy('decision_id')->first();

        return $last && $first && json_decode((string) $last->new_value, true) !== json_decode((string) $first->old_value, true);
    }

    /**
     * Usable stock per material straight from the available-stock view (one light query, no MRP run).
     * Available (Jo 2026-10-13: "bebas dari BOOKING atau RESERVE"; 06:31Z: Draft / PRE bookings count too) = On hand minus the open
     * bookings of EVERY stage (qty_book - qty_issued, not cancelled / closed; an expired hold does not count), computed in PPIC by
     * AvailableStock (the view v_rm_available and the booking tables are not changed). Reserve = booking in this schema. A BOM Release
     * does not book stock, so it does not lower Available. SEBELUM: v_rm_available.qty_available (confirmed bookings only).
     *
     * @return array<int, array{onhand: float, available: float}>
     */
    public static function stockOf(iterable $materialIds): array
    {
        return AvailableStock::of($materialIds);
    }

    /**
     * The Master Card of a Work Order, ASSEMBLED automatically from what the upstream modules hold (Inquiry part, ProDev part,
     * ProDev layout group). Nothing is typed here and nothing is read from a hand-imported card row. Read only.
     *
     * @return array<string, mixed>|null
     */
    public static function masterCard(object $wo): ?array
    {
        $pid = (int) ($wo->product_id ?? 0);
        $m = DB::connection('tm2mst');
        $product = $pid ? $m->table('prod_product')->where('id_product', $pid)->first() : null;
        $iqp = ! empty($wo->source_iqp_part_id) ? DB::connection('tm2sdi')->table('iqp_part')->where('id_part', $wo->source_iqp_part_id)->first() : null;
        $part = $iqp?->prod_part_id ? $m->table('prod_part')->where('id_part', $iqp->prod_part_id)->first() : null;
        if (! $product && ! $part) {
            return null;
        }
        $mm = fn ($v) => $v ? rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.') : null;
        $dims = (array) (json_decode((string) ($iqp->dieline_spec ?? ''), true)['dims'] ?? []);
        $l = $dims['product_length'] ?? ($product?->cm_length ? $product->cm_length * 10 : null);
        $w = $dims['product_width'] ?? ($product?->cm_width ? $product->cm_width * 10 : null);
        $h = $dims['product_height'] ?? ($product?->cm_height ? $product->cm_height * 10 : null);
        $flatW = $part->flat_w ?? $iqp->flat_w ?? null;
        $flatH = $part->flat_h ?? $iqp->flat_h ?? null;
        $layout = $pid ? $m->table('prod_layout_group')->where('product_id', $pid)->orderByDesc('id_layout_group')->first() : null;

        $mats = $part ? $m->table('prod_part_material as pm')->leftJoin('mat_category as c', 'c.id_category', '=', 'pm.category_id')->leftJoin('mat_raw as r', 'r.material_id', '=', 'pm.material_id')
            ->where('pm.part_id', $part->id_part)->orderBy('pm.layer_seq')->get(['c.category_name', 'r.material_name', 'pm.gram_weight', 'pm.size_p', 'pm.size_l']) : collect();
        $fin = $part ? $m->table('prod_part_finishing as f')->leftJoin('ref_finishing_type as t', 't.id_finishing_type', '=', 'f.finishing_type_id')->where('f.part_id', $part->id_part)->orderBy('f.seq')->get(['t.finishing_name', 'f.attribute']) : collect();
        $inks = $part ? $m->table('prod_part_ink')->where('part_id', $part->id_part)->orderBy('pass_seq')->pluck('ink_name')->unique()->all() : [];
        $proc = $part ? $m->table('prod_part_process')->where('part_id', $part->id_part)->orderBy('step_seq')->pluck('process_name')->all() : [];
        $art = $part ? $m->table('prod_part_artwork')->where('part_id', $part->id_part)->where('activate', 1)->orderByDesc('version_no')->value('artwork_path') : null;
        $art = $art ?: ($iqp->artwork_path ?? null);
        $url = fn (?string $path) => $path && Storage::disk('public')->exists($path) ? asset('storage/'.$path) : null;
        $customer = $product?->customer_id ? $m->table('cust_customer')->where('id_customer', $product->customer_id)->value('customer_name') : null;
        $legacy = $pid ? ProdMasterCard::where('product_id', $pid)->orderByDesc('id_master_card')->first() : null;
        $missing = [];
        foreach (['dieline' => ! ($iqp?->dieline_spec), 'artwork' => ! $art, 'size (L x W x H)' => ! $l, 'flat size' => ! $flatW, 'material' => $mats->isEmpty(), 'process (BOP)' => $proc === []] as $what => $absent) {
            if ($absent) {
                $missing[] = $what;
            }
        }

        return [
            'id' => $legacy?->id_master_card, 'name' => (string) ($product->product_name ?? ''), 'code' => trim(($product->design_no ?? '—').' · '.($product->product_code ?? '—')),
            'status' => $missing ? 'Incomplete' : 'Complete', 'missing' => $missing, 'customer' => (string) $customer,
            'dims' => $l ? $mm($l).' × '.$mm($w).' × '.$mm($h).' mm' : '—',
            'knife' => $layout && $layout->knife_w_cm ? $mm($layout->knife_w_cm * 10).' × '.$mm($layout->knife_h_cm * 10).' mm · '.$layout->up_count.' UP' : 'Layout not generated yet (ProDev)',
            'print' => $layout && $layout->plano_w_mm ? $mm($layout->plano_w_mm).' × '.$mm($layout->plano_h_mm).' mm' : '—',
            'flat' => $flatW ? $mm($flatW).' × '.$mm($flatH).' mm' : '—',
            'area' => $flatW && $flatH ? Locale::number((float) $flatW * (float) $flatH / 100, 0).' cm²' : '—',
            'flaps' => self::flapsText($dims),
            'hole' => self::holeText($dims),
            'join' => '—', 'pack' => $iqp?->pack_qty ? number_format((float) $iqp->pack_qty, 0) : '—',
            'materials' => $mats->isNotEmpty() ? $mats->map(fn ($x) => trim(($x->material_name ?: $x->category_name ?: '?').($x->gram_weight ? ' '.$mm($x->gram_weight).' gsm' : '').($x->size_p ? ' · '.$mm($x->size_p).' × '.$mm($x->size_l) : '')))->implode(' + ') : '—',
            'finishing' => $fin->isNotEmpty() ? $fin->map(fn ($x) => trim(($x->finishing_name ?: '?').($x->attribute ? ' ('.$x->attribute.')' : '')))->implode(', ') : '—',
            'process' => $proc ? implode(' → ', $proc) : '—', 'colours' => $inks ? implode(' ', $inks) : '—',
            'photo' => null, 'artwork' => $url($art), 'proof' => null, 'mockup' => null,
            'svg' => self::compactDieline((string) ($iqp->dieline_spec ?? '')),
        ];
    }

    /** @param array<string, mixed> $d dims of the Inquiry dieline_spec */
    private static function flapsText(array $d): string
    {
        $v = fn ($k) => isset($d[$k]) && $d[$k] !== '' ? rtrim(rtrim(number_format((float) $d[$k], 1, '.', ''), '0'), '.') : '0';

        return 'GF '.$v('glue_flap').' · Allowance '.$v('flap_extra').' · Hand hole '.$v('hand_hole_width').' · Bleed '.$v('overflow_margin').' mm';
    }

    /** @param array<string, mixed> $d */
    private static function holeText(array $d): string
    {
        $type = strtoupper((string) ($d['hole_type'] ?? 'NONE'));
        if ($type === 'NONE' || $type === '') {
            return 'None';
        }

        return ($type === 'ROUND' ? 'Round Ø '.($d['hole_w'] ?? '?') : 'Rectangle '.($d['hole_w'] ?? '?').' × '.($d['hole_h'] ?? '?')).' mm';
    }

    /** Small dieline drawing (SVG) from an Inquiry part dieline_spec; null when the spec has no known type or sizes. */
    private static function compactDieline(string $spec): ?string
    {
        $d = json_decode($spec, true);
        if (! is_array($d) || empty($d['type_code']) || empty($d['dims'])) {
            return null;
        }
        $svg = MasterCardShape::svg((string) $d['type_code'], (array) $d['dims']);

        return $svg ? str_replace('width="100%" height="100%"', 'width="210" height="135"', $svg) : null;
    }

    /** Product ids that have a Master Card (linked by product id; no name matching). @return array<int, true> */
    public static function cardProductIds(): array
    {
        $ids = ProdMasterCard::where('status', 'PRODUCT')->whereNotNull('product_id')->pluck('product_id')->map(fn ($i) => (int) $i)->all();

        return array_fill_keys($ids, true);
    }

    /** Delivery plan lines without a Work Order whose product has no Master Card: not offered for a Work Order until the card exists. */
    public static function linesWithoutCard(): int
    {
        return count(self::pendingDeliveries(500, false)) - count(self::pendingDeliveries(500));
    }

    /** Delivery plan lines waiting for a Work Order. Only products with a Master Card are offered (the Work Order follows the card). */
    public static function pendingDeliveries(int $limit = 200, bool $onlyWithCard = true): array
    {
        // Work Orders live in another database: ids with a Work Order are fetched apart, never joined (no cross-connection subquery).
        $hasWo = DB::connection('tm2prd')->table('prd_wo')->whereNotNull('source_delivery_id')->where('wo_status', '!=', 'CANCELLED')->distinct()->pluck('source_delivery_id')->all();
        $rows = Cpdel::query()->with(['item.inquiry.parts'])->where('status', 'PLANNED')->whereNull('posted_at')->whereNotIn('id_delivery', $hasWo)
            ->orderBy('ship_date')->limit($limit)->get();
        $customers = DB::connection('tm2mst')->table('cust_customer')->whereIn('id_customer', $rows->pluck('order.customer_id')->filter()->unique())->pluck('customer_name', 'id_customer');
        $products = $rows->pluck('item.product_id')->filter()->unique()->all();
        // CO INSTANT points to a Master Product: the latest live inquiry of that product is the source, as WoGenerationService does.
        $byProduct = $products
            ? IqpInquiry::with('parts')->whereIn('product_id', $products)->where('stat_cancel', false)->orderBy('id_inquiry')->get()->keyBy('product_id')
            : collect();

        $cards = $onlyWithCard ? self::cardProductIds() : [];
        if ($onlyWithCard) {
            $rows = $rows->filter(fn ($d) => isset($cards[(int) ($d->item?->product_id ?: ($d->item?->inquiry?->product_id ?? 0))]))->values();
        }

        return $rows->map(function ($d) use ($customers, $byProduct) {
            $inq = $d->item?->inquiry ?? ($d->item?->product_id ? $byProduct->get($d->item->product_id) : null);
            $parts = $inq?->parts?->count() ?? 0;
            // Complete = every part carries dieline, artwork and size (rule: no Work Order without a READY Master Card).
            $missing = $parts > 0 ? self::partsMissing($inq->parts) : ['part'];

            return [
                'id' => $d->id_delivery, 'co' => $d->order?->co_no, 'customer' => $customers[$d->order?->customer_id] ?? '—',
                'product' => $d->item?->description, 'qty' => (float) $d->ship_qty, 'ship' => $d->ship_date?->toDateString(),
                'parts' => $parts, 'complete' => $missing === [], 'missing' => $missing,
            ];
        })->all();
    }

    /**
     * What the Inquiry parts still lack for a READY Master Card: dieline, artwork, size (L x W x H). Body parts of an assembly hold
     * no artwork of their own, so only COMPONENT / single parts are checked.
     *
     * @param  iterable<object>  $parts
     * @return array<int, string>
     */
    private static function partsMissing(iterable $parts): array
    {
        $missing = [];
        foreach ($parts as $p) {
            if (($p->part_kind ?? null) === 'ASSEMBLY') {
                continue;
            }
            $spec = is_array($p->dieline_spec) ? $p->dieline_spec : (array) json_decode((string) $p->dieline_spec, true);
            $dims = (array) ($spec['dims'] ?? []);
            foreach (['dieline' => ! $p->dieline_spec, 'artwork' => ! $p->artwork_path, 'size' => empty($dims['product_length']) || empty($dims['product_width']) || empty($dims['product_height'])] as $what => $absent) {
                if ($absent) {
                    $missing[$what] = $what;
                }
            }
        }

        return array_values($missing);
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
