<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Master\MatRaw;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdMachineDowntime;
use App\Models\Mnt\MntAsset;
use App\Models\Mnt\MntAssetPart;
use App\Models\Mnt\MntCode;
use App\Models\Mnt\MntOrder;
use App\Models\Mnt\MntRequest;
use App\Models\Sys\SysConstant;
use App\Services\Production\MachineOeeService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

// Angka Dashboard dan laporan Maintenance. Tidak menghitung ulang OEE / MTBF / MTTR: memakai MachineOeeService milik Production, lalu menggabungkan
// per mesin. Maintenance hanya menyumbang catatan downtime (lewat Maintenance Order); OEE di sini hanya ditampilkan.
final class KpiService
{
    private MachineOeeService $oee;

    public function __construct(?MachineOeeService $oee = null)
    {
        $this->oee = $oee ?? new MachineOeeService;
    }

    /** Target dari Settings > Constants (kunci MNT_TARGET_*), dengan usulan awal bila belum diisi. */
    public static function targets(): array
    {
        return ['availability' => SysConstant::number('MNT_TARGET_AVAILABILITY', 97), 'mtbf' => SysConstant::number('MNT_TARGET_MTBF_HOURS', 120),
            'mttr' => SysConstant::number('MNT_TARGET_MTTR_HOURS', 4), 'pm_compliance' => SysConstant::number('MNT_TARGET_PM_COMPLIANCE', 90),
            'planned_work' => SysConstant::number('MNT_TARGET_PLANNED_WORK', 85), 'backlog_max' => SysConstant::number('MNT_TARGET_BACKLOG_WEEKS_MAX', 5)];
    }

    /** @return Collection<int, ProdMachine> mesin aktif yang punya aset Maintenance (satu mesin bila $machineId diberi) */
    private function machines(?int $machineId): Collection
    {
        $ids = MntAsset::whereNotNull('machine_id')->where('activate', true)->when($machineId, fn ($q) => $q->where('machine_id', $machineId))->pluck('machine_id');

        return ProdMachine::whereIn('machine_id', $ids)->orderBy('machine_code')->get();
    }

    /**
     * Indikator utama periode: availability, MTBF, MTTR, OEE (dibaca), kepatuhan PM, planned work, backlog.
     *
     * @return array<string, mixed>
     */
    public function indicators(CarbonImmutable $from, CarbonImmutable $to, ?int $machineId = null): array
    {
        $avail = [];
        $oees = [];
        $factors = ['availability' => [], 'performance' => [], 'quality' => []];
        $failures = 0;
        $repairHours = 0.0;
        $machines = $this->machines($machineId);
        foreach ($machines as $m) {
            $o = $this->oee->calculate($m->machine_id, $from, $to);
            foreach (array_keys($factors) as $f) {
                if (($o['factors'][$f]['value'] ?? null) !== null) {
                    $factors[$f][] = (float) $o['factors'][$f]['value'];
                }
            }
            if (($o['factors']['availability']['value'] ?? null) !== null) {
                $avail[] = (float) $o['factors']['availability']['value'];
            }
            if ($o['oee'] !== null) {
                $oees[] = (float) $o['oee'];
            }
            $r = $this->oee->reliability($m->machine_id, $from, $to);
            $failures += $r['failures'];
            $repairHours += ($r['mttr_hours'] ?? 0) * $r['failures'];
        }
        $periodHours = $from->diffInHours($to, true);
        $uptime = max(0.0, $machines->count() * $periodHours - $repairHours);

        $compliance = (new ComplianceService)->summarize((new ComplianceService)->orders($from, $to));
        $orders = MntOrder::where('stat_confirm', true)->where('stat_cancel', false)->whereBetween('planned_start', [$from->startOfDay(), $to->endOfDay()])
            ->when($machineId, fn ($q) => $q->whereIn('asset_id', MntAsset::where('machine_id', $machineId)->pluck('id_asset')))->get();
        $avg = fn (array $a) => $a === [] ? null : round(array_sum($a) / count($a), 1);

        return [
            'machines' => $machines->count(), 'availability' => $avg($avail), 'oee' => $avg($oees), 'factors' => array_map($avg, $factors),
            'failures' => $failures, 'mttr' => $failures ? round($repairHours / $failures, 1) : null, 'mtbf' => $failures ? round($uptime / $failures, 0) : null,
            'pm_compliance' => $compliance['pct'], 'pm' => $compliance,
            'planned_work' => $orders->isEmpty() ? null : round($orders->where('order_type', '!=', 'BREAKDOWN')->count() / $orders->count() * 100, 1),
            'backlog_weeks' => $this->backlogWeeks(),
        ];
    }

    /** Jam perkiraan order terbuka dibagi jam teknisi per minggu (jumlah teknisi = yang mengerjakan order 90 hari terakhir, minimal satu). */
    public function backlogWeeks(): ?float
    {
        $hours = MntOrder::where('stat_confirm', true)->where('stat_cancel', false)->where('stat_close', false)->sum('est_minutes') / 60;
        $techs = max(1, MntOrder::whereNotNull('assigned_to')->where('created_at', '>=', now()->subDays(90))->distinct()->count('assigned_to'));
        $perWeek = SysConstant::number('MNT_TECHNICIAN_HOURS_WEEK', 40) * $techs;

        return $hours > 0 ? round($hours / $perWeek, 1) : 0.0;
    }

    /** Angka tindakan hari ini (tanpa target). @return array<string, array{n:int, hint:string}> */
    public function actions(): array
    {
        $down = ProdMachineDowntime::whereNull('end_at')->where('downtime_kind', 'BREAKDOWN')->orderBy('start_at')->get();
        $names = ProdMachine::whereIn('machine_id', $down->pluck('machine_id'))->pluck('machine_code', 'machine_id');
        $open = MntRequest::where('status', 'OPEN')->orderBy('request_date')->get();
        $overdue = MntOrder::whereIn('order_type', MntOrder::typesOf('PMO'))->where('stat_confirm', true)->where('stat_cancel', false)->where('stat_close', false)
            ->whereNull('actual_start')->where('planned_start', '<', today())->orderBy('planned_start')->get();
        $working = MntOrder::where('work_status', 'IN_PROGRESS')->where('stat_close', false)->where('stat_cancel', false)->get();
        $waiting = MntOrder::where('work_status', 'WAITING_PART')->where('stat_close', false)->where('stat_cancel', false)->get();
        $below = $this->criticalPartsBelowMin();

        return [
            'down' => ['n' => $down->count(), 'hint' => $down->isEmpty() ? '—' : ($names[$down[0]->machine_id] ?? '#'.$down[0]->machine_id).' · '.$down[0]->start_at->diffForHumans(null, true)],
            'requests' => ['n' => $open->count(), 'hint' => $open->isEmpty() ? '—' : $open->where('priority', 'P1')->count().' P1 · '.$open->where('priority', 'P2')->count().' P2'],
            'overdue_pm' => ['n' => $overdue->count(), 'hint' => $overdue->isEmpty() ? '—' : ($overdue[0]->doc_no ?: '#'.$overdue[0]->id_order)],
            'in_progress' => ['n' => $working->count(), 'hint' => $working->where('order_type', 'BREAKDOWN')->count().' BMO · '.$working->whereIn('order_type', ['PREVENTIVE', 'INSPECTION'])->count().' PMO'],
            'waiting' => ['n' => $waiting->count(), 'hint' => $waiting->isEmpty() ? '—' : ($waiting[0]->doc_no ?: '#'.$waiting[0]->id_order)],
            'parts_low' => ['n' => count($below), 'hint' => $below === [] ? '—' : implode(', ', array_slice($below, 0, 2))],
        ];
    }

    /** @return list<string> kode part kritis yang stoknya di bawah usulan minimum */
    public function criticalPartsBelowMin(): array
    {
        $parts = MntAssetPart::where('activate', true)->where('is_critical', true)->where('min_stock_suggested', '>', 0)->get();
        $stock = PartSupplyService::onHand($parts->pluck('material_id'));
        $codes = MatRaw::whereIn('material_id', $parts->pluck('material_id'))->pluck('material_code', 'material_id');

        return $parts->filter(fn ($p) => ($stock[$p->material_id] ?? 0) < (float) $p->min_stock_suggested)->map(fn ($p) => (string) ($codes[$p->material_id] ?? '#'.$p->material_id))->unique()->values()->all();
    }

    /** Kepatuhan PM per bulan, 12 bulan terakhir. @return array{labels: list<string>, data: list<float|null>} */
    public function complianceTrend(int $months = 12): array
    {
        $svc = new ComplianceService;
        $labels = [];
        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = CarbonImmutable::now()->startOfMonth()->subMonths($i);
            $labels[] = $start->format('M');
            $data[] = $svc->summarize($svc->orders($start, $start->endOfMonth()))['pct'];
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /** Mesin dengan downtime tidak terencana terbanyak. @return list<array{machine: string, hours: float}> */
    public function topDowntime(CarbonImmutable $from, CarbonImmutable $to, int $limit = 4): array
    {
        $rows = ProdMachineDowntime::whereBetween('start_at', [$from->startOfDay(), $to->endOfDay()])->where('is_planned', false)->whereNotNull('end_at')->get()
            ->groupBy('machine_id')->map(fn ($g) => $g->sum('duration_minutes') / 60)->sortDesc()->take($limit);
        $names = ProdMachine::whereIn('machine_id', $rows->keys())->get(['machine_id', 'machine_code', 'machine_name'])->mapWithKeys(fn ($m) => [$m->machine_id => $m->machine_code.' '.$m->machine_name]);

        return $rows->map(fn ($h, $id) => ['machine' => (string) ($names[$id] ?? '#'.$id), 'hours' => round($h, 1)])->values()->all();
    }

    /** Downtime per jenis (jam). @return array<string, float> */
    public function downtimeByKind(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return ProdMachineDowntime::whereBetween('start_at', [$from->startOfDay(), $to->endOfDay()])->whereNotNull('end_at')->get()->groupBy('downtime_kind')
            ->map(fn ($g) => round($g->sum('duration_minutes') / 60, 1))->sortDesc()->all();
    }

    /** Kerusakan per komponen dari BMO (Breakdown dan Corrective). @return list<array{component: string, n: int}> */
    public function failuresByComponent(CarbonImmutable $from, CarbonImmutable $to, int $limit = 5): array
    {
        $labels = MntCode::options('COMPONENT');
        $rows = MntOrder::whereIn('order_type', MntOrder::typesOf('BMO'))->where('stat_confirm', true)->where('stat_cancel', false)->whereNotNull('component_code')
            ->whereBetween('planned_start', [$from->startOfDay(), $to->endOfDay()])->get()->groupBy('component_code')->map->count()->sortDesc()->take($limit);

        return $rows->map(fn ($n, $code) => ['component' => (string) ($labels[$code] ?? $code), 'n' => $n])->values()->all();
    }

    /** Biaya periode: part, tenaga teknisi, jasa vendor. @return array{part: float, labor: float, vendor: float, total: float} */
    public function cost(CarbonImmutable $from, CarbonImmutable $to, ?int $machineId = null): array
    {
        $o = MntOrder::where('stat_confirm', true)->where('stat_cancel', false)->whereBetween('planned_start', [$from->startOfDay(), $to->endOfDay()])
            ->when($machineId, fn ($q) => $q->whereIn('asset_id', MntAsset::where('machine_id', $machineId)->pluck('id_asset')))->get();
        $c = ['part' => (float) $o->sum('cost_part'), 'labor' => (float) $o->sum('cost_labor'), 'vendor' => (float) $o->sum('cost_vendor')];

        return $c + ['total' => array_sum($c)];
    }
}
