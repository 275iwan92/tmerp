<?php

namespace App\Support\V2\Data;

use App\Models\Master\EmpEmployee;
use App\Models\Master\MatRaw;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdMachineDowntime;
use App\Models\Prd\MntCode;
use App\Models\Prd\MntMeterReading;
use App\Models\Prd\MntPlan;
use App\Models\Prd\MntRequest;
use App\Models\Prd\MntWo;
use App\Services\Maintenance\MaintenanceService;
use Devlib\Locale;

// Data layar v2 Maintenance (Request / Work Order / Preventive Plan / My Work). Aturan tulis = MaintenanceService.
// Mesin, pegawai & bahan ada di tm2mst -> dicari sekali per halaman lalu dicocokkan di PHP (pola 1 CLAUDE.md §2).
final class Maintenance
{
    public const WORK_TONES = ['Draft' => 'neutral', 'Scheduled' => 'info', 'In Progress' => 'accent', 'Waiting Part' => 'warn', 'Done' => 'success',
        'Closed' => 'neutral', 'Cancelled' => 'danger'];

    public const TYPE_TONES = ['Breakdown' => 'danger', 'Preventive' => 'accent', 'Corrective' => 'warn', 'Inspection' => 'success'];

    public const PRIO_TONES = ['P1' => 'danger', 'P2' => 'warn', 'P3' => 'neutral', 'P4' => 'neutral'];

    public const REQ_TONES = ['Open' => 'warn', 'Accepted' => 'success', 'Rejected' => 'danger'];

    // [machine_id => "OFF-02 · Komori GL440 (Support)"] -- alat penunjang (is_process_machine=0) tetap bisa dirawat.
    public static function machines(): array
    {
        return ProdMachine::where('activate', true)->orderBy('machine_code')->get(['machine_id', 'machine_code', 'machine_name', 'is_process_machine'])
            ->mapWithKeys(fn ($m) => [$m->machine_id => $m->machine_code.' · '.$m->machine_name.($m->is_process_machine ? '' : ' ('.t('Support').')')])->all();
    }

    private static function machineMap(iterable $ids): array
    {
        return ProdMachine::whereIn('machine_id', collect($ids)->filter()->unique())->get(['machine_id', 'machine_code', 'machine_name', 'criticality', 'is_process_machine', 'machine_status'])
            ->keyBy('machine_id')->all();
    }

    public static function employeeNames(iterable $ids): array
    {
        return EmpEmployee::whereIn('employee_id', collect($ids)->filter()->unique())->pluck('full_name', 'employee_id')->all();
    }

    public static function employeeName(?int $id): string
    {
        return $id ? (self::employeeNames([$id])[$id] ?? '#'.$id) : '—';
    }

    public static function workLabel(MntWo $wo): string
    {
        return match ($wo->docStatus()) {
            'DRAFT' => 'Draft', 'CLOSED' => 'Closed', 'CANCELLED' => 'Cancelled', default => MntWo::WORK[$wo->work_status] ?? $wo->work_status,
        };
    }

    public static function stamp($at): string
    {
        return $at ? Locale::stamp((string) $at) : '—';
    }

    public static function money(float $v): string
    {
        return 'IDR '.Locale::number($v, 0);
    }

    // ------------------------------------------------------------ Work Order

    public static function woRows(?int $technicianId = null): array
    {
        $q = MntWo::query()->orderByDesc('id_wo')->limit(1000);
        if ($technicianId) {
            $q->where('assigned_to', $technicianId);
        }
        $wos = $q->with('parts')->get();
        $mach = self::machineMap($wos->pluck('machine_id'));
        // Baris anak grid (Expand): suku cadang WO, tanpa harga (tm2prd -> tm2mst, satu query nama bahan).
        $mats = MatRaw::whereIn('material_id', $wos->flatMap(fn ($w) => $w->parts->pluck('material_id'))->unique())->get(['material_id', 'material_code', 'material_name'])->keyBy('material_id');
        $emp = self::employeeNames($wos->pluck('assigned_to'));
        $src = MntRequest::whereIn('id_request', $wos->pluck('request_id')->filter())->pluck('doc_no', 'id_request');
        $plans = MntPlan::whereIn('id_plan', $wos->pluck('plan_id')->filter())->pluck('plan_code', 'id_plan');
        $down = ProdMachineDowntime::whereIn('downtime_id', $wos->pluck('downtime_id')->filter())->get()->keyBy('downtime_id');
        $today = now()->startOfDay();

        return $wos->map(function (MntWo $w) use ($mach, $emp, $src, $plans, $down, $today, $mats) {
            $m = $mach[$w->machine_id] ?? null;
            $dt = $w->downtime_id ? ($down[$w->downtime_id] ?? null) : null;
            $mins = $dt ? (int) $dt->start_at->diffInMinutes($dt->end_at ?? now(), true) : null;
            $open = in_array($w->docStatus(), ['DRAFT', 'OPEN'], true);

            return [
                'id' => $w->id_wo, 'no' => $w->doc_no ?: '(draft #'.$w->id_wo.')', 'desc' => $w->description,
                'machine' => $m ? $m->machine_code.' · '.$m->machine_name : '#'.$w->machine_id, 'support' => $m && ! $m->is_process_machine ? t('Support') : t('Production'),
                'type' => MntWo::TYPES[$w->wo_type] ?? $w->wo_type, 'priority' => $w->priority, 'work' => self::workLabel($w),
                'planned' => $w->planned_start?->toDateString(), 'planned_at' => self::stamp($w->planned_start),
                'technician' => $w->assigned_to ? ($emp[$w->assigned_to] ?? '#'.$w->assigned_to) : '—',
                'downtime' => $mins === null ? '—' : tf(':h h :m m', ['h' => intdiv($mins, 60), 'm' => $mins % 60]).($dt->end_at ? '' : ' ●'),
                'source' => $w->request_id ? ($src[$w->request_id] ?? t('Request')) : ($w->plan_id ? ($plans[$w->plan_id] ?? t('Plan')) : t('Manual')),
                'cost' => $w->totalCost(), 'open' => $open ? 'Open' : 'Finished',
                'items' => $w->parts->map(fn ($p) => ['code' => $mats[$p->material_id]->material_code ?? null, 'name' => $mats[$p->material_id]->material_name ?? '#'.$p->material_id,
                    'plan' => (float) $p->qty_plan, 'used' => $p->qty_used === null ? null : (float) $p->qty_used, 'unit' => $p->uom_code])->values()->all(),
                'overdue' => $open && $w->wo_type === 'PREVENTIVE' && $w->planned_start && $w->planned_start->lt($today) && ! $w->actual_start ? 'Overdue' : '',
                'done_month' => $w->actual_end && $w->actual_end->isSameMonth(now()) ? 'Yes' : '',
            ];
        })->all();
    }

    public static function woStats(): array
    {
        return [
            ['label' => 'All Open', 'where' => ['open' => 'Open']],
            ['label' => 'Breakdown', 'where' => ['type' => 'Breakdown', 'open' => 'Open'], 'tone' => 'danger'],
            ['label' => 'In Progress', 'where' => ['work' => 'In Progress'], 'tone' => 'info'],
            ['label' => 'Waiting Part', 'where' => ['work' => 'Waiting Part'], 'tone' => 'warn'],
            ['label' => 'PM Overdue', 'where' => ['overdue' => 'Overdue'], 'tone' => 'accent'],
            ['label' => 'Done This Month', 'where' => ['done_month' => 'Yes'], 'tone' => 'success'],
        ];
    }

    public static function tasks(MntWo $wo): array
    {
        return $wo->tasks->map(fn ($t) => [
            'id_wo_task' => $t->id_wo_task, 'seq_no' => $t->seq_no, 'task_text' => $t->task_text, 'kind' => t(ucfirst(strtolower($t->task_kind))),
            'range' => $t->task_kind === 'MEASURE' ? trim(Locale::decimal($t->min_value, 2).' – '.Locale::decimal($t->max_value, 2).' '.$t->measure_uom) : '—',
            'required' => $t->is_required ? t('Required') : '—', 'result' => $t->result ?? '', 'measured_value' => $t->measured_value, 'note' => $t->note,
            'done' => $t->done_at ? $t->done_by.' · '.self::stamp($t->done_at) : '—', 'editable' => ! $t->is_required,
        ])->values()->all();
    }

    public static function parts(MntWo $wo): array
    {
        $mats = MatRaw::whereIn('material_id', $wo->parts->pluck('material_id'))->get(['material_id', 'material_code', 'material_name', 'cur_stock'])->keyBy('material_id');

        return $wo->parts->map(function ($p) use ($mats) {
            $m = $mats[$p->material_id] ?? null;
            $stock = (float) ($m?->cur_stock ?? 0);
            $need = (float) ($p->qty_used ?? $p->qty_plan);

            return ['id_wo_part' => $p->id_wo_part, 'item' => $m ? $m->material_code.' · '.$m->material_name : '#'.$p->material_id,
                'qty_plan' => $p->qty_plan, 'qty_used' => $p->qty_used, 'uom' => $p->uom_code ?: '—', 'stock' => Locale::decimal($stock, 2),
                'unit_cost' => self::money((float) $p->unit_cost), 'amount' => self::money($need * (float) $p->unit_cost),
                'source' => $p->pr_line_id ? tf('PR line #:n', ['n' => $p->pr_line_id]) : ($stock >= $need ? t('Stock') : tf('Short :qty', ['qty' => Locale::decimal($need - $stock, 2)]))];
        })->values()->all();
    }

    public static function labor(MntWo $wo): array
    {
        $emp = self::employeeNames($wo->labor->pluck('employee_id'));

        return $wo->labor->map(fn ($l) => ['id_wo_labor' => $l->id_wo_labor, 'employee' => $emp[$l->employee_id] ?? '#'.$l->employee_id,
            'work_date' => $l->work_date?->toDateString(), 'minutes' => $l->minutes, 'rate' => self::money((float) $l->rate_snapshot),
            'amount' => self::money($l->minutes / 60 * (float) $l->rate_snapshot), 'note' => $l->note])->values()->all();
    }

    // Kotak "Machine impact" di form WO.
    public static function impact(MntWo $wo): array
    {
        $m = ProdMachine::find($wo->machine_id);
        $dt = $wo->downtime_id ? ProdMachineDowntime::find($wo->downtime_id) : null;
        $mins = $dt ? (int) $dt->start_at->diffInMinutes($dt->end_at ?? now(), true) : null;

        return ['status' => $m?->statusLabel() ?? '—', 'status_code' => $m?->machine_status, 'support' => $m && ! $m->is_process_machine,
            'downtime' => $mins === null ? null : tf(':h h :m m', ['h' => intdiv($mins, 60), 'm' => $mins % 60]).($dt->end_at ? '' : ' '.t('(running)')),
            'downtime_id' => $dt?->downtime_id, 'rel' => MaintenanceService::reliability((int) $wo->machine_id, 90)];
    }

    public static function codeOptions(string $type): array
    {
        return MntCode::options($type);
    }

    // ------------------------------------------------------------ Request

    public static function requestRows(): array
    {
        $rows = MntRequest::orderByDesc('id_request')->limit(1000)->get();
        $mach = self::machineMap($rows->pluck('machine_id'));
        $emp = self::employeeNames($rows->pluck('reported_by'));
        $wos = MntWo::whereIn('id_wo', $rows->pluck('wo_id')->filter())->pluck('doc_no', 'id_wo');

        return $rows->map(fn (MntRequest $r) => [
            'id' => $r->id_request, 'no' => $r->doc_no, 'date' => $r->request_date?->toDateString(), 'at' => self::stamp($r->request_date),
            'machine' => ($m = $mach[$r->machine_id] ?? null) ? $m->machine_code.' · '.$m->machine_name : '#'.$r->machine_id,
            'kind' => MntRequest::KINDS[$r->request_kind] ?? $r->request_kind, 'priority' => $r->priority,
            'stopped' => $r->machine_stopped ? 'Stopped' : 'Running', 'symptom' => $r->symptom,
            'reporter' => $r->reported_by ? ($emp[$r->reported_by] ?? '#'.$r->reported_by) : $r->created_by,
            'status' => ucfirst(strtolower($r->status)), 'wo' => $r->wo_id ? ($wos[$r->wo_id] ?? '#'.$r->wo_id) : '—',
            'age' => $r->status === 'OPEN' ? $r->request_date->diffForHumans(null, true) : '—',
        ])->all();
    }

    // ------------------------------------------------------------ Plan

    public static function planRows(): array
    {
        $plans = MntPlan::with('tasks')->orderBy('plan_code')->get();
        $mach = self::machineMap($plans->pluck('machine_id'));
        $today = now()->startOfDay();

        return $plans->map(fn (MntPlan $p) => [
            'id' => $p->id_plan, 'code' => $p->plan_code, 'name' => $p->plan_name,
            'machine' => ($m = $mach[$p->machine_id] ?? null) ? $m->machine_code.' · '.$m->machine_name : '#'.$p->machine_id,
            'type' => MntPlan::TYPES[$p->wo_type] ?? $p->wo_type, 'trigger' => self::triggerText($p), 'mode' => t(ucfirst(strtolower($p->schedule_mode))),
            'last' => $p->last_done_date?->toDateString(), 'next' => $p->next_due_date?->toDateString(),
            'due' => $p->next_due_date && $p->next_due_date->lt($today) ? 'Overdue' : ($p->next_due_date && $p->next_due_date->lte($today->copy()->addDays((int) $p->lead_days)) ? 'Due' : 'OK'),
            'tasks' => $p->tasks->count(), 'active' => (bool) $p->activate,
            // Baris anak grid (Expand): daftar tugas plan.
            'items' => $p->tasks->map(fn ($t) => ['seq' => $t->seq_no, 'task' => $t->task_text, 'kind' => $t->task_kind, 'uom' => $t->measure_uom,
                'min' => $t->min_value, 'max' => $t->max_value, 'required' => $t->is_required ? t('Yes') : t('No')])->values()->all(),
        ])->all();
    }

    public static function triggerText(MntPlan $p): string
    {
        $t = $p->usesTime() ? tf('every :n :unit', ['n' => $p->interval_value, 'unit' => t(strtolower(MntPlan::UNITS[$p->interval_unit] ?? $p->interval_unit).($p->interval_value > 1 ? 's' : ''))]) : null;
        $m = $p->usesMeter() ? tf('every :n :unit', ['n' => Locale::number((float) $p->meter_interval, 0), 'unit' => t(strtolower(MntPlan::METERS[$p->meter_type] ?? ''))]) : null;

        return implode(' '.t('OR').' ', array_filter([$t, $m])) ?: '—';
    }

    // Kotak "Next due" form plan: tanggal & meter berikut + WO berikutnya (yang lebih dulu).
    public static function nextDue(MntPlan $p): array
    {
        $cur = $p->meter_type ? MntMeterReading::latest($p->machine_id, $p->meter_type) : null;

        return ['current_meter' => $cur, 'next_date' => $p->next_due_date?->toDateString(), 'next_meter' => $p->next_due_meter,
            'meter_left' => $cur !== null && $p->next_due_meter !== null ? $p->next_due_meter - $cur : null];
    }

    // Kepatuhan PM 12 bulan: WO dari plan ini yang Closed, tepat waktu = selesai paling lambat 1 hari setelah rencana.
    public static function compliance(MntPlan $p): array
    {
        $wos = MntWo::where('plan_id', $p->id_plan)->where('created_at', '>=', now()->subYear())->where('stat_cancel', false)->get();
        $done = $wos->where('stat_close', true);
        $onTime = $done->filter(fn ($w) => $w->actual_end && $w->planned_start && $w->actual_end->lte($w->planned_start->copy()->addDay()->endOfDay()))->count();

        return ['generated' => $wos->count(), 'done' => $done->count(), 'on_time' => $onTime, 'late' => $done->count() - $onTime,
            'pct' => $done->count() ? round($onTime / $done->count() * 100) : null];
    }

    public static function planTasks(MntPlan $p): array
    {
        return $p->tasks->map(fn ($t) => ['id_plan_task' => $t->id_plan_task, 'seq_no' => $t->seq_no, 'task_text' => $t->task_text,
            'task_kind' => $t->task_kind, 'measure_uom' => $t->measure_uom, 'min_value' => $t->min_value, 'max_value' => $t->max_value,
            'is_required' => (bool) $t->is_required])->values()->all();
    }

    public static function planParts(MntPlan $p): array
    {
        $mats = MatRaw::whereIn('material_id', $p->parts->pluck('material_id'))->get(['material_id', 'material_code', 'material_name', 'avg_unitprice'])->keyBy('material_id');

        return $p->parts->map(fn ($x) => ['id_plan_part' => $x->id_plan_part,
            'item' => ($m = $mats[$x->material_id] ?? null) ? $m->material_code.' · '.$m->material_name : '#'.$x->material_id,
            'qty' => $x->qty, 'uom_code' => $x->uom_code, 'price' => (float) ($m?->avg_unitprice ?? 0)])->values()->all();
    }
}
