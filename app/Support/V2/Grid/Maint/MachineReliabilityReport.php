<?php

namespace App\Support\V2\Grid\Maint;

use App\Models\Master\ProdMachine;
use App\Models\Mnt\MntAsset;
use App\Models\Mnt\MntOrder;
use App\Services\Production\MachineOeeService;
use App\Support\V2\Grid\DbGrid;
use Devlib\Locale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Machine Reliability -- satu baris per mesin: kerusakan, MTTR, MTBF, jam downtime terencana dan tidak, availability dan OEE (dibaca dari Production),
// plus biaya maintenance. Rumus = MachineOeeService (tidak dihitung ulang). Inherent availability = MTBF / (MTBF + MTTR), berbeda dari Availability OEE.
class MachineReliabilityReport extends DbGrid
{
    private MachineOeeService $oee;

    /** @var array<int, float> biaya order per aset mesin (machine_id => IDR) */
    private array $cost = [];

    public function __construct()
    {
        $this->oee = new MachineOeeService;
    }

    public function slug(): string
    {
        return 'maintenance-reliability';
    }

    public function title(): string
    {
        return 'Machine Reliability';
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
        return 'One row = one machine in the period. MTTR = repair hours ÷ breakdowns; MTBF = (period hours − repair hours) ÷ breakdowns. OEE and availability come from Production; they are blank when the machine calendar or output is missing.';
    }

    protected function defaultPeriod(): ?array
    {
        return [now()->toImmutable()->subDays(90)->startOfDay(), now()->toImmutable()->startOfDay()];
    }

    protected function query(): Builder
    {
        return ProdMachine::query()->whereIn('machine_id', MntAsset::whereNotNull('machine_id')->where('activate', true)->pluck('machine_id'))->orderBy('machine_code');
    }

    protected function prefetch(Collection $items): void
    {
        [$from, $to] = $this->period();
        $assets = MntAsset::whereIn('machine_id', $items->pluck('machine_id'))->pluck('machine_id', 'id_asset');
        foreach (MntOrder::whereIn('asset_id', $assets->keys())->where('stat_confirm', true)->where('stat_cancel', false)->whereBetween('planned_start', [$from, $to->endOfDay()])->get() as $o) {
            $this->cost[$assets[$o->asset_id]] = ($this->cost[$assets[$o->asset_id]] ?? 0) + $o->totalCost();
        }
    }

    protected function row(Model $m): array
    {
        [$from, $to] = $this->period();
        $o = $this->oee->calculate($m->machine_id, $from, $to);
        $r = $this->oee->reliability($m->machine_id, $from, $to);
        $inherent = $r['mtbf_hours'] !== null && $r['mttr_hours'] !== null && ($r['mtbf_hours'] + $r['mttr_hours']) > 0 ? round($r['mtbf_hours'] / ($r['mtbf_hours'] + $r['mttr_hours']) * 100, 1) : null;

        // Angka yang tidak bisa dihitung tampil "—", bukan 0: nol berarti "dihitung dan hasilnya nol".
        $show = fn (?float $v) => $v === null ? '—' : Locale::decimal($v, 1);

        return ['id' => $m->machine_id, 'machine' => $m->machine_code, 'name' => $m->machine_name, 'failures' => $r['failures'], 'mttr' => $show($r['mttr_hours']), 'mtbf' => $show($r['mtbf_hours']),
            'planned_h' => round(($o['downtime']['planned'] ?? 0) / 60, 1), 'unplanned_h' => round(($o['downtime']['unplanned'] ?? 0) / 60, 1), 'inherent' => $show($inherent),
            'availability' => $show($o['factors']['availability']['value'] ?? null), 'oee' => $show($o['oee']), 'cost' => round($this->cost[$m->machine_id] ?? 0, 2)];
    }

    public function columns(): array
    {
        return [
            ['key' => 'machine', 'label' => 'Machine'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'failures', 'label' => 'Breakdowns', 'type' => 'number', 'sum' => true],
            ['key' => 'mttr', 'label' => 'MTTR (h)'],
            ['key' => 'mtbf', 'label' => 'MTBF (h)'],
            ['key' => 'inherent', 'label' => 'Inherent Availability %'],
            ['key' => 'unplanned_h', 'label' => 'Unplanned Downtime (h)', 'type' => 'number', 'sum' => true],
            ['key' => 'planned_h', 'label' => 'Planned Downtime (h)', 'type' => 'number', 'sum' => true],
            ['key' => 'availability', 'label' => 'Availability % (OEE)'],
            ['key' => 'oee', 'label' => 'OEE %'],
            ['key' => 'cost', 'label' => 'Maintenance Cost (IDR)', 'type' => 'money', 'sum' => true],
        ];
    }

    public function stats(): array
    {
        return [['label' => 'All']];
    }

    public function filters(): array
    {
        return ['machine' => 'Machine'];
    }
}
