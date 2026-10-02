<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;
use Devlib\Locale;

// Jam mesin berhenti tidak terencana pada periode itu, dibanding periode lalu; baris bawah = yang terencana. Referensi: downtime (ERPNext).
final class MachineDowntime extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'machine_downtime', 'title' => 'Unplanned Downtime', 'type' => 'kpi', 'dashboard' => 'PRODUCTION', 'module' => 'PROD',
            'description' => 'Hours machines stood still without a plan in the period, with the planned maintenance hours beside it.',
            'icon' => 'fa-screwdriver-wrench', 'tone' => 'danger', 'phrases' => ['downtime', 'mesin berhenti', 'mesin rusak', 'machine downtime', 'breakdown'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        [$prevFrom, $prevTo] = $n->range(1);
        $now = $n->downtime($n->from(), $n->to());
        $before = $n->downtime($prevFrom, $prevTo);

        return ['source' => 'tm2mst.prod_machine_downtime', 'data' => ['value' => round($now['unplanned'], 1), 'previous' => $before['unplanned'], 'format' => 'number', 'good_when' => 'down',
            'hint' => raw($this->say(':n downtime records, in hours', ['n' => $now['events']])), 'note' => raw($this->say('Planned :v h · :n still stopped', ['v' => Locale::decimal($now['planned'], 1), 'n' => $now['open']]))]];
    }
}
