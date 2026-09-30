<?php

namespace App\Support\V2\Grid\Maint;

use App\Models\Master\EmpEmployee;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdMachineDowntime;
use App\Models\Prd\MntCode;
use App\Models\Prd\MntWo;
use App\Support\V2\Data\Maintenance;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Maintenance WO Report -- satu baris per WO (jenis, mesin, durasi, downtime, biaya, kode kerusakan). Grup per Mesin / Bulan /
// Jenis / Problem = dasar Pareto kerusakan & biaya per mesin (aturan "sedikit laporan, banyak kolom opsional").
class MaintenanceWoReport extends DbGrid
{
    private array $machines = [];

    private array $techs = [];

    private array $downtime = [];

    private array $codes = [];

    public function slug(): string
    {
        return 'maintenance-wo-report';
    }

    public function title(): string
    {
        return 'Maintenance WO Report';
    }

    public function module(): string
    {
        return 'MAINT';
    }

    public function moduleLabel(): string
    {
        return 'Maintenance';
    }

    public function family(): string
    {
        return self::REPORT;
    }

    public function guide(): ?string
    {
        return 'One row = one Work Order. Group by Machine / Month / Type / Problem to see where breakdowns and cost come from. Use Columns for Cause, Action, Findings, cost split.';
    }

    protected function query(): Builder
    {
        return MntWo::query()->where('stat_confirm', true)->orderByDesc('planned_start');
    }

    protected function prefetch(Collection $items): void
    {
        $this->machines = ProdMachine::whereIn('machine_id', $items->pluck('machine_id')->unique())->get(['machine_id', 'machine_code', 'machine_name', 'machine_type'])->keyBy('machine_id')->all();
        $this->techs = EmpEmployee::whereIn('employee_id', $items->pluck('assigned_to')->filter()->unique())->pluck('full_name', 'employee_id')->all();
        $this->downtime = ProdMachineDowntime::whereIn('downtime_id', $items->pluck('downtime_id')->filter())->get()->keyBy('downtime_id')->all();
        $this->codes = MntCode::query()->get(['code_type', 'code', 'name'])->mapWithKeys(fn ($c) => [$c->code_type.':'.$c->code => $c->name])->all();
    }

    protected function row(Model $m): array
    {
        $mc = $this->machines[$m->machine_id] ?? null;
        $dt = $m->downtime_id ? ($this->downtime[$m->downtime_id] ?? null) : null;
        $work = $m->actual_start && $m->actual_end ? (int) $m->actual_start->diffInMinutes($m->actual_end, true) : null;
        $code = fn (string $t, ?string $c) => $c ? ($this->codes[$t.':'.$c] ?? $c) : null;

        return [
            'wo' => $m->doc_no, 'month' => $m->planned_start?->format('Y-m'), 'planned' => $m->planned_start?->toDateString(),
            'machine' => $mc ? $mc->machine_code.' · '.$mc->machine_name : '#'.$m->machine_id, 'machine_type' => $mc?->machine_type,
            'type' => MntWo::TYPES[$m->wo_type] ?? $m->wo_type, 'priority' => $m->priority, 'status' => Maintenance::workLabel($m),
            'technician' => $m->assigned_to ? ($this->techs[$m->assigned_to] ?? '#'.$m->assigned_to) : null, 'description' => $m->description,
            'component' => $code('COMPONENT', $m->component_code), 'problem' => $code('PROBLEM', $m->problem_code),
            'cause' => $code('CAUSE', $m->cause_code), 'action' => $code('ACTION', $m->action_code), 'findings' => $m->findings,
            'work_hours' => $work === null ? null : round($work / 60, 2),
            'downtime_hours' => $dt ? round($dt->start_at->diffInMinutes($dt->end_at ?? now(), true) / 60, 2) : null,
            'cost_part' => (float) $m->cost_part, 'cost_labor' => (float) $m->cost_labor, 'cost_vendor' => (float) $m->cost_vendor, 'cost' => $m->totalCost(),
        ];
    }

    public function columns(): array
    {
        $hidden = ['visible' => false];

        return [
            ['key' => 'wo', 'label' => 'WO'],
            ['key' => 'planned', 'label' => 'Planned', 'type' => 'date'],
            ['key' => 'month', 'label' => 'Month'] + $hidden,
            ['key' => 'machine', 'label' => 'Machine'],
            ['key' => 'machine_type', 'label' => 'Machine Type'] + $hidden,
            ['key' => 'type', 'label' => 'Type', 'type' => 'badge', 'tones' => Maintenance::TYPE_TONES],
            ['key' => 'priority', 'label' => 'Priority'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => Maintenance::WORK_TONES],
            ['key' => 'technician', 'label' => 'Technician'],
            ['key' => 'description', 'label' => 'Description'] + $hidden,
            ['key' => 'component', 'label' => 'Component'] + $hidden,
            ['key' => 'problem', 'label' => 'Problem'],
            ['key' => 'cause', 'label' => 'Cause'] + $hidden,
            ['key' => 'action', 'label' => 'Action'] + $hidden,
            ['key' => 'findings', 'label' => 'Findings'] + $hidden,
            ['key' => 'work_hours', 'label' => 'Work (h)', 'type' => 'number', 'sum' => true],
            ['key' => 'downtime_hours', 'label' => 'Downtime (h)', 'type' => 'number', 'sum' => true],
            ['key' => 'cost_part', 'label' => 'Spare Parts', 'type' => 'money', 'sum' => true] + $hidden,
            ['key' => 'cost_labor', 'label' => 'Labor', 'type' => 'money', 'sum' => true] + $hidden,
            ['key' => 'cost_vendor', 'label' => 'Vendor', 'type' => 'money', 'sum' => true] + $hidden,
            ['key' => 'cost', 'label' => 'Total Cost', 'type' => 'money', 'sum' => true],
        ];
    }

    public function groups(): array
    {
        return ['machine' => 'Machine', 'month' => 'Month', 'type' => 'Type', 'problem' => 'Problem', 'technician' => 'Technician'];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Breakdown', 'where' => ['type' => 'Breakdown'], 'tone' => 'danger'],
            ['label' => 'Preventive', 'where' => ['type' => 'Preventive'], 'tone' => 'accent'], ['label' => 'Closed', 'where' => ['status' => 'Closed'], 'tone' => 'success']];
    }

    public function filters(): array
    {
        return ['machine' => 'Machine', 'type' => 'Type', 'problem' => 'Problem', 'technician' => 'Technician', 'month' => 'Month'];
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'maintenance-wo').'?id={id}';
    }
}
