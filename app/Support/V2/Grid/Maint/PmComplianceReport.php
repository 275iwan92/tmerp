<?php

namespace App\Support\V2\Grid\Maint;

use App\Models\Mnt\MntOrder;
use App\Services\Maintenance\ComplianceService;
use App\Support\V2\Data\Maintenance;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// PM Compliance -- satu baris per PMO yang rencana mulainya sudah dikonfirmasi. Penilaian (on time / late / not done / pending) memakai
// ComplianceService, jadi angka sama dengan Dashboard. Grup per bulan atau aset untuk melihat tren dan titik lemah.
class PmComplianceReport extends DbGrid
{
    private ComplianceService $svc;

    private array $assets = [];

    public function __construct()
    {
        $this->svc = new ComplianceService;
    }

    public function slug(): string
    {
        return 'maintenance-pm-compliance';
    }

    public function title(): string
    {
        return 'PM Compliance';
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
        return 'One row = one preventive or inspection order. Compliance % = On time / (On time + Late + Not done). Group by Month or Asset and read the subtotals of the three count columns.';
    }

    protected function query(): Builder
    {
        return MntOrder::query()->with('plan')->whereIn('order_type', MntOrder::typesOf('PMO'))->where('stat_confirm', true)->where('stat_cancel', false)
            ->whereNotNull('planned_start')->orderByDesc('planned_start');
    }

    protected function prefetch(Collection $items): void
    {
        $this->assets = Maintenance::assetOptions();
    }

    protected function row(Model $m): array
    {
        $j = $this->svc->judge($m);

        return ['order' => $m->doc_no, 'month' => $m->planned_start->format('Y-m'), 'due' => $m->planned_start->toDateString(), 'asset' => $this->assets[$m->asset_id] ?? '#'.$m->asset_id,
            'plan' => $m->plan?->plan_code, 'type' => MntOrder::TYPES[$m->order_type] ?? $m->order_type, 'done' => $m->actual_end?->toDateString(),
            'ok' => $j === 'on_time' ? 1 : 0, 'late' => $j === 'late' ? 1 : 0, 'missed' => $j === 'not_done' ? 1 : 0,
            'result' => ['on_time' => 'On time', 'late' => 'Late', 'not_done' => 'Not done', 'pending' => 'Not yet due'][$j], 'status' => $m->statusLabel()];
    }

    public function columns(): array
    {
        return [
            ['key' => 'order', 'label' => 'Order'],
            ['key' => 'month', 'label' => 'Month'],
            ['key' => 'due', 'label' => 'Due', 'type' => 'date'],
            ['key' => 'asset', 'label' => 'Asset'],
            ['key' => 'plan', 'label' => 'Plan'],
            ['key' => 'type', 'label' => 'Type', 'type' => 'badge', 'tones' => MntOrder::TYPE_TONES],
            ['key' => 'done', 'label' => 'Done On', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => MntOrder::WORK_TONES],
            ['key' => 'ok', 'label' => 'On Time', 'type' => 'number', 'sum' => true],
            ['key' => 'late', 'label' => 'Late', 'type' => 'number', 'sum' => true],
            ['key' => 'missed', 'label' => 'Not Done', 'type' => 'number', 'sum' => true],
            ['key' => 'result', 'label' => 'Result', 'type' => 'badge', 'tones' => ['On time' => 'success', 'Late' => 'warn', 'Not done' => 'danger', 'Not yet due' => 'neutral']],
        ];
    }

    public function groups(): array
    {
        return ['month' => 'Month', 'asset' => 'Asset', 'result' => 'Result', 'type' => 'Type'];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'On time', 'where' => ['result' => 'On time'], 'tone' => 'success'], ['label' => 'Late', 'where' => ['result' => 'Late'], 'tone' => 'warn'],
            ['label' => 'Not done', 'where' => ['result' => 'Not done'], 'tone' => 'danger']];
    }

    public function filters(): array
    {
        return ['month' => 'Month', 'asset' => 'Asset', 'result' => 'Result', 'type' => 'Type'];
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'maintenance-pmo').'?id={id}';
    }
}
