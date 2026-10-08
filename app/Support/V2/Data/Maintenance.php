<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Master\EmpEmployee;
use App\Models\Master\MatRaw;
use App\Models\Master\ProdMachine;
use App\Models\Mnt\MntAsset;
use App\Models\Mnt\MntCode;
use App\Models\Mnt\MntDailyCheck;
use App\Models\Mnt\MntDocument;
use App\Models\Mnt\MntOrder;
use App\Models\Mnt\MntOrderTask;
use App\Models\Mnt\MntPlan;
use App\Models\Mnt\MntPlanPart;
use App\Models\Mnt\MntPlanTask;
use App\Services\Maintenance\AssetProfileService;
use Devlib\Locale;

// Data layar v2 Maintenance Fase 1 (profil aset, kelengkapan, dokumen). Aturan tulis = AssetProfileService.
// Mesin dan bahan ada di tm2mst -> dicari sekali per halaman lalu dicocokkan di PHP (pola 1 CLAUDE.md bagian 2).
final class Maintenance
{
    public const STATUS_LABELS = ['DRAFT' => 'Draft', 'APPROVED' => 'Approved'];

    /** [id_asset => "OFF-02 · Komori GL440"] aset aktif untuk dropdown. */
    public static function assetOptions(): array
    {
        return MntAsset::where('activate', true)->orderBy('asset_code')->get(['id_asset', 'asset_code', 'asset_name'])
            ->mapWithKeys(fn ($a) => [$a->id_asset => $a->asset_code.' · '.$a->asset_name])->all();
    }

    /** [machine_id => criticality] untuk aset yang tertaut ke Master Mesin. */
    private static function machineCriticality(iterable $machineIds): array
    {
        return ProdMachine::whereIn('machine_id', collect($machineIds)->filter()->unique())->pluck('criticality', 'machine_id')->all();
    }

    public static function criticalityOf(MntAsset $a): ?string
    {
        return $a->isLinkedMachine() ? (self::machineCriticality([$a->machine_id])[$a->machine_id] ?? null) : $a->criticality;
    }

    private static function counted()
    {
        return MntAsset::query()->withCount([
            'watchPoints as watch_count' => fn ($q) => $q->where('activate', true),
            'parts as parts_count' => fn ($q) => $q->where('activate', true),
            'documents as docs_count',
        ]);
    }

    /** Baris daftar profil aset. */
    public static function assetRows(): array
    {
        $assets = self::counted()->orderBy('asset_code')->get();
        $crit = self::machineCriticality($assets->pluck('machine_id'));

        return $assets->map(function (MntAsset $a) use ($crit) {
            $c = AssetProfileService::completeness(['watch' => $a->watch_count, 'parts' => $a->parts_count, 'docs' => $a->docs_count], (string) $a->meter_type);

            return ['id' => $a->id_asset, 'code' => $a->asset_code, 'name' => $a->asset_name, 'class' => MntAsset::CLASSES[$a->asset_class] ?? $a->asset_class,
                'criticality' => $a->isLinkedMachine() ? ($crit[$a->machine_id] ?? null) : $a->criticality, 'location' => $a->location,
                'meter' => MntAsset::METERS[$a->meter_type] ?? $a->meter_type, 'watch' => $a->watch_count, 'parts' => $a->parts_count, 'docs' => $a->docs_count,
                'complete' => $c['done'].' / '.$c['total'], 'pct' => $c['pct'], 'status' => $a->activate ? 'Active' : 'Inactive'];
        })->all();
    }

    /** Baris x-dl::lines tab Watch Points. */
    public static function watchRows(MntAsset $asset): array
    {
        return $asset->watchPoints->map(fn ($w) => ['id_watch_point' => $w->id_watch_point, 'component_code' => $w->component_code, 'check_text' => $w->check_text,
            'method_text' => $w->method_text, 'normal_min' => $w->normal_min, 'normal_max' => $w->normal_max, 'measure_uom' => $w->measure_uom,
            'frequency' => $w->frequency, 'checked_by_role' => $w->checked_by_role, 'activate' => $w->activate])->values()->all();
    }

    /** Baris x-dl::lines tab Spare Parts (nama bahan dari tm2mst, satu query). */
    public static function partRows(MntAsset $asset): array
    {
        $mats = MatRaw::whereIn('material_id', $asset->parts->pluck('material_id'))->get(['material_id', 'material_code', 'material_name'])->keyBy('material_id');

        return $asset->parts->map(fn ($p) => ['id_asset_part' => $p->id_asset_part,
            'item' => ($mats[$p->material_id]->material_code ?? '#'.$p->material_id).' · '.($mats[$p->material_id]->material_name ?? ''),
            'component_code' => $p->component_code, 'qty_per_use' => $p->qty_per_use, 'min_stock_suggested' => $p->min_stock_suggested,
            'expected_life_days' => $p->expected_life_days, 'is_critical' => $p->is_critical])->values()->all();
    }

    /** Dokumen terhubung ke aset (tampil saja; dibuka di layar Documents). */
    public static function documentRows(MntAsset $asset): array
    {
        return $asset->documents->map(fn ($d) => ['id_document' => $d->id_document, 'doc_no' => $d->doc_no, 'kind' => MntDocument::KINDS[$d->kind] ?? $d->kind,
            'title' => $d->title, 'revision' => $d->revision, 'status' => self::STATUS_LABELS[$d->status] ?? $d->status])->values()->all();
    }

    /** [id_document => "WI-MNT-OFF-01 · Daily check of ..."] dokumen yang belum terhubung ke aset ini. */
    public static function linkableDocuments(MntAsset $asset): array
    {
        $linked = $asset->documents->pluck('id_document');

        return MntDocument::where('activate', true)->whereNotIn('id_document', $linked)->orderBy('doc_no')->get(['id_document', 'doc_no', 'title'])
            ->mapWithKeys(fn ($d) => [$d->id_document => $d->doc_no.' · '.$d->title])->all();
    }

    public static function componentOptions(): array
    {
        return ['' => '—'] + array_map('t', MntCode::options('COMPONENT'));
    }

    /** Baris daftar dokumen SOP / WI. */
    public static function documentListRows(): array
    {
        return MntDocument::query()->withCount('lines')->orderBy('doc_no')->get()->map(fn ($d) => ['id' => $d->id_document, 'code' => $d->doc_no,
            'title' => $d->title, 'kind' => MntDocument::KINDS[$d->kind] ?? $d->kind, 'set' => $d->doc_set, 'revision' => $d->revision,
            'status' => self::STATUS_LABELS[$d->status] ?? $d->status, 'lines' => $d->lines_count, 'approved_at' => $d->approved_at?->toDateString(),
            'items' => $d->lines()->orderBy('seq_no')->get()->map(fn ($l) => ['seq' => $l->seq_no, 'section' => $l->section, 'text' => $l->line_text])->all()])->all();
    }

    // ------------------------------------------------------------ Fase 2: Preventive Plan, Maintenance Order, Daily Check

    /** [employee_id => nama] pegawai aktif untuk pilihan teknisi. */
    public static function technicianOptions(): array
    {
        return ['' => '—'] + EmpEmployee::where('activate', true)->orderBy('full_name')->pluck('full_name', 'employee_id')->all();
    }

    public static function employeeNames(iterable $ids): array
    {
        return EmpEmployee::whereIn('employee_id', collect($ids)->filter()->unique())->pluck('full_name', 'employee_id')->all();
    }

    private static function assetLabels(iterable $ids): array
    {
        return MntAsset::whereIn('id_asset', collect($ids)->filter()->unique())->get(['id_asset', 'asset_code', 'asset_name'])
            ->mapWithKeys(fn ($a) => [$a->id_asset => $a->asset_code.' · '.$a->asset_name])->all();
    }

    public static function stamp($at): string
    {
        return $at ? Locale::stamp((string) $at) : '—';
    }

    public static function money(float $v): string
    {
        return 'IDR '.Locale::number($v, 0);
    }

    /** Ringkas pemicu plan: "1 Month", "250 Run hours", atau gabungan keduanya. */
    public static function triggerText(MntPlan $p): string
    {
        $time = $p->usesTime() ? $p->interval_value.' '.t(MntPlan::UNITS[$p->interval_unit] ?? $p->interval_unit) : null;
        $meter = $p->usesMeter() ? Locale::number((float) $p->meter_interval, 0).' '.t(MntPlan::METERS[$p->meter_type] ?? $p->meter_type) : null;

        return implode(' '.t('OR').' ', array_filter([$time, $meter])) ?: '—';
    }

    public static function planDue(MntPlan $p): string
    {
        if (! $p->activate || ! $p->next_due_date) {
            return $p->activate ? 'OK' : 'Inactive';
        }

        return $p->next_due_date->lt(today()) ? 'Overdue' : ($p->next_due_date->lte(today()->addDays((int) $p->lead_days)) ? 'Due' : 'OK');
    }

    /** Baris daftar Preventive Plan; anak (Expand) = checklist plan. */
    public static function planRows(): array
    {
        $plans = MntPlan::with('tasks')->orderBy('plan_code')->get();
        $assets = self::assetLabels($plans->pluck('asset_id'));

        return $plans->map(fn (MntPlan $p) => ['id' => $p->id_plan, 'code' => $p->plan_code, 'asset' => $assets[$p->asset_id] ?? '#'.$p->asset_id, 'name' => $p->plan_name,
            'type' => MntPlan::TYPES[$p->order_type] ?? $p->order_type, 'trigger' => self::triggerText($p), 'mode' => MntPlan::MODES[$p->schedule_mode] ?? $p->schedule_mode,
            'last' => $p->last_done_date?->toDateString(), 'next' => $p->next_due_date?->toDateString(), 'due' => self::planDue($p),
            'items' => $p->tasks->map(fn ($t) => ['seq' => $t->seq_no, 'task' => $t->task_text, 'kind' => MntPlanTask::KINDS[$t->task_kind] ?? $t->task_kind,
                'uom' => $t->measure_uom, 'min' => $t->min_value, 'max' => $t->max_value, 'required' => $t->is_required ? 'Yes' : 'No'])->all()])->all();
    }

    public static function planTaskRows(MntPlan $plan): array
    {
        return $plan->tasks->map(fn ($t) => ['id_plan_task' => $t->id_plan_task, 'seq_no' => $t->seq_no, 'task_text' => $t->task_text, 'task_kind' => $t->task_kind,
            'measure_uom' => $t->measure_uom, 'min_value' => $t->min_value, 'max_value' => $t->max_value, 'is_required' => $t->is_required])->values()->all();
    }

    public static function planPartRows(MntPlan $plan): array
    {
        $mats = MatRaw::whereIn('material_id', $plan->parts->pluck('material_id'))->get(['material_id', 'material_code', 'material_name'])->keyBy('material_id');

        return $plan->parts->map(fn (MntPlanPart $p) => ['id_plan_part' => $p->id_plan_part, 'item' => ($mats[$p->material_id]->material_code ?? '#'.$p->material_id).' · '.($mats[$p->material_id]->material_name ?? ''),
            'qty' => $p->qty, 'uom_code' => $p->uom_code])->values()->all();
    }

    /** Baris daftar PMO (series PMO) atau BMO (series BMO). Anak (Expand) = checklist order. */
    public static function orderRows(string $series): array
    {
        $orders = MntOrder::with('tasks')->whereIn('order_type', MntOrder::typesOf($series))->orderByDesc('id_order')->limit(1000)->get();
        $assets = self::assetLabels($orders->pluck('asset_id'));
        $emp = self::employeeNames($orders->pluck('assigned_to'));
        $today = today();

        return $orders->map(function (MntOrder $o) use ($assets, $emp, $today) {
            $open = in_array($o->docStatus(), ['DRAFT', 'OPEN'], true);

            return ['id' => $o->id_order, 'no' => $o->doc_no ?: '(draft #'.$o->id_order.')', 'desc' => $o->description, 'asset' => $assets[$o->asset_id] ?? '#'.$o->asset_id,
                'type' => MntOrder::TYPES[$o->order_type] ?? $o->order_type, 'priority' => $o->priority, 'work' => $o->statusLabel(),
                'planned' => $o->planned_start?->toDateString(), 'planned_at' => self::stamp($o->planned_start), 'technician' => $o->assigned_to ? ($emp[$o->assigned_to] ?? '#'.$o->assigned_to) : '—',
                'source' => $o->plan_id ? t('Plan') : ($o->request_id ? t('Request') : t('Manual')), 'cost' => $o->totalCost(), 'open' => $open ? 'Open' : 'Finished',
                'overdue' => $open && $o->planned_start && $o->planned_start->lt($today) && ! $o->actual_start ? 'Overdue' : '',
                'items' => $o->tasks->map(fn ($t) => ['seq' => $t->seq_no, 'task' => $t->task_text, 'kind' => MntPlanTask::KINDS[$t->task_kind] ?? $t->task_kind,
                    'result' => $t->result ? (MntOrderTask::RESULTS[$t->result] ?? $t->result) : null])->all()];
        })->all();
    }

    public static function orderTaskRows(MntOrder $o): array
    {
        return $o->tasks->map(fn ($t) => ['id_order_task' => $t->id_order_task, 'seq_no' => $t->seq_no, 'task_text' => $t->task_text, 'task_kind' => MntPlanTask::KINDS[$t->task_kind] ?? $t->task_kind,
            'range' => $t->min_value !== null || $t->max_value !== null ? ($t->min_value ?? '…').' – '.($t->max_value ?? '…').' '.$t->measure_uom : '—', 'result' => $t->result,
            'measured_value' => $t->measured_value, 'note' => $t->note])->values()->all();
    }

    public static function orderPartRows(MntOrder $o): array
    {
        $mats = MatRaw::whereIn('material_id', $o->parts->pluck('material_id'))->get(['material_id', 'material_code', 'material_name'])->keyBy('material_id');

        return $o->parts->map(fn ($p) => ['id_order_part' => $p->id_order_part, 'item' => ($mats[$p->material_id]->material_code ?? '#'.$p->material_id).' · '.($mats[$p->material_id]->material_name ?? ''),
            'qty_plan' => $p->qty_plan, 'qty_used' => $p->qty_used, 'uom_code' => $p->uom_code, 'unit_cost' => $p->unit_cost])->values()->all();
    }

    public static function orderLaborRows(MntOrder $o): array
    {
        $emp = self::employeeNames($o->labor->pluck('employee_id'));

        return $o->labor->map(fn ($l) => ['id_order_labor' => $l->id_order_labor, 'employee' => $emp[$l->employee_id] ?? '#'.$l->employee_id, 'work_date' => $l->work_date?->toDateString(),
            'minutes' => $l->minutes, 'note' => $l->note])->values()->all();
    }

    /** Acara kalender PM: PMO Draft dan Confirmed yang punya rencana mulai. */
    public static function calendarEvents(): array
    {
        $orders = MntOrder::whereIn('order_type', MntOrder::typesOf('PMO'))->where('stat_cancel', false)->whereNotNull('planned_start')->get();
        $assets = self::assetLabels($orders->pluck('asset_id'));
        $tone = ['Draft' => 'neutral', 'Scheduled' => 'info', 'In Progress' => 'accent', 'Waiting Part' => 'warn', 'Done' => 'success', 'Closed' => 'success'];

        return $orders->map(fn (MntOrder $o) => ['id' => $o->id_order, 'title' => ($o->doc_no ?: t('Draft')).' · '.($assets[$o->asset_id] ?? ''), 'start' => $o->planned_start->format('Y-m-d H:i'),
            'end' => ($o->planned_end ?? $o->planned_start->addHour())->format('Y-m-d H:i'), 'allDay' => false, 'tone' => $tone[$o->statusLabel()] ?? 'neutral', 'desc' => $o->description])->values()->all();
    }

    /** Baris daftar lembar Daily Check. */
    public static function dailyCheckRows(): array
    {
        $sheets = MntDailyCheck::withCount(['lines', 'lines as notok_count' => fn ($q) => $q->where('result', 'NOT_OK')])->orderByDesc('check_date')->orderByDesc('id_daily_check')->limit(1000)->get();
        $assets = self::assetLabels($sheets->pluck('asset_id'));

        return $sheets->map(fn ($c) => ['id' => $c->id_daily_check, 'code' => $c->doc_no, 'asset' => $assets[$c->asset_id] ?? '#'.$c->asset_id, 'date' => $c->check_date->toDateString(),
            'shift' => MntDailyCheck::SHIFTS[$c->shift_code] ?? $c->shift_code, 'by' => $c->checked_by, 'lines' => $c->lines_count, 'notok' => $c->notok_count,
            'status' => $c->isSubmitted() ? 'Submitted' : 'Open'])->all();
    }
}
