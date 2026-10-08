<?php

namespace App\Support\V2\Grid\Maint;

use App\Models\Mnt\MntOrder;
use App\Support\V2\Data\Maintenance;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Maintenance Order Report -- satu baris per order (PMO, BMO, IMO) yang sudah Confirmed. Jam downtime = mulai sampai selesai kerja; biaya = part + teknisi + vendor.
// Kelompokkan per bulan, aset, jenis atau teknisi untuk melihat di mana waktu dan uang habis. Open membuka order.
class MaintenanceOrderReport extends DbGrid
{
    private array $assets = [];

    private array $emp = [];

    public function slug(): string
    {
        return 'maintenance-order-report';
    }

    public function title(): string
    {
        return 'Maintenance Order Report';
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
        return 'One row = one confirmed Maintenance Order. Hours = actual start to actual end. Group by Month, Asset, Type or Technician and read the subtotals of hours and cost.';
    }

    protected function query(): Builder
    {
        return MntOrder::query()->where('stat_confirm', true)->where('stat_cancel', false)->orderByDesc('planned_start');
    }

    protected function prefetch(Collection $items): void
    {
        $this->assets = Maintenance::assetOptions();
        $this->emp = Maintenance::employeeNames($items->pluck('assigned_to'));
    }

    protected function row(Model $m): array
    {
        return ['id' => $m->id_order, 'order' => $m->doc_no, 'month' => $m->planned_start?->format('Y-m'), 'asset' => $this->assets[$m->asset_id] ?? '#'.$m->asset_id,
            'type' => MntOrder::TYPES[$m->order_type] ?? $m->order_type, 'priority' => $m->priority, 'status' => $m->statusLabel(), 'technician' => $m->assigned_to ? ($this->emp[$m->assigned_to] ?? '#'.$m->assigned_to) : '—',
            'planned' => $m->planned_start?->toDateString(), 'hours' => $m->actual_start && $m->actual_end ? round($m->actual_start->diffInMinutes($m->actual_end) / 60, 1) : 0,
            'part' => (float) $m->cost_part, 'labor' => (float) $m->cost_labor, 'vendor' => (float) $m->cost_vendor, 'cost' => $m->totalCost()];
    }

    public function columns(): array
    {
        return [
            ['key' => 'order', 'label' => 'Order'],
            ['key' => 'month', 'label' => 'Month'],
            ['key' => 'asset', 'label' => 'Asset'],
            ['key' => 'type', 'label' => 'Type', 'type' => 'badge', 'tones' => MntOrder::TYPE_TONES],
            ['key' => 'priority', 'label' => 'Priority'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => MntOrder::WORK_TONES],
            ['key' => 'technician', 'label' => 'Technician'],
            ['key' => 'planned', 'label' => 'Planned', 'type' => 'date'],
            ['key' => 'hours', 'label' => 'Hours', 'type' => 'number', 'sum' => true],
            ['key' => 'part', 'label' => 'Spare Parts (IDR)', 'type' => 'money', 'sum' => true],
            ['key' => 'labor', 'label' => 'Labor (IDR)', 'type' => 'money', 'sum' => true],
            ['key' => 'vendor', 'label' => 'Vendor (IDR)', 'type' => 'money', 'sum' => true],
            ['key' => 'cost', 'label' => 'Total (IDR)', 'type' => 'money', 'sum' => true],
        ];
    }

    public function groups(): array
    {
        return ['month' => 'Month', 'asset' => 'Asset', 'type' => 'Type', 'technician' => 'Technician', 'status' => 'Status'];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Breakdown', 'where' => ['type' => 'Breakdown'], 'tone' => 'danger'], ['label' => 'Preventive', 'where' => ['type' => 'Preventive'], 'tone' => 'info']];
    }

    public function filters(): array
    {
        return ['month' => 'Month', 'asset' => 'Asset', 'type' => 'Type', 'status' => 'Status', 'technician' => 'Technician'];
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'maintenance-bmo').'?id={id}';
    }
}
