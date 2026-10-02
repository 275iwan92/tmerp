<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;
use Devlib\Locale;

// Capacity planning: jam operasi terjadwal dibanding jam tersedia semua mesin proses pada periode itu.
// Jam tersedia = jam per shift x shift per hari x hari kerja (master mesin). Referensi: work centre load (Business Central).
final class CapacityUtilization extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'capacity_utilization', 'title' => 'Capacity Load', 'type' => 'kpi', 'dashboard' => 'PRODUCTION', 'module' => 'PROD',
            'description' => 'Hours of scheduled Work Order operations against the available hours of all process machines in the period.',
            'icon' => 'fa-gauge-high', 'tone' => 'violet', 'phrases' => ['kapasitas', 'beban kapasitas', 'capacity planning', 'capacity load', 'utilisasi mesin'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $machines = collect($n->capacity($n->from(), $n->to()));
        $known = $machines->whereNotNull('available');
        $available = (float) $known->sum('available');
        $planned = (float) $known->sum('planned');
        $missing = $machines->whereNull('available')->count();
        $note = $missing ? raw($this->say(':n machines without hours per shift in Master Machine', ['n' => $missing])) : null;
        if ($available <= 0) {
            return ['source' => 'tm2mst.prod_machine · tm2prd.prd_wo_operation', 'data' => ['value' => '—', 'format' => 'percent', 'hint' => 'Machine hours per shift are not filled in', 'note' => $note]];
        }

        return ['source' => 'tm2mst.prod_machine (hours_per_shift, shifts_per_day) · tm2prd.prd_wo_operation', 'data' => ['value' => $planned / $available * 100, 'format' => 'percent',
            'hint' => raw($this->say(':a h planned of :b h available', ['a' => Locale::decimal($planned, 1), 'b' => Locale::decimal($available, 1)])), 'note' => $note]];
    }
}
