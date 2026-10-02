<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;
use Devlib\Locale;

// Beban per mesin pada periode itu: jam terjadwal dibanding jam tersedia, mesin paling penuh di atas.
final class MachineLoad extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'machine_load', 'title' => 'Machine Load', 'type' => 'list', 'dashboard' => 'PRODUCTION', 'module' => 'PROD', 'size' => 6,
            'description' => 'Scheduled hours against available hours per machine in the period, the fullest machine first.',
            'icon' => 'fa-gears', 'tone' => 'warn', 'phrases' => ['beban mesin', 'kapasitas mesin', 'jadwal mesin', 'machine load', 'machine capacity'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $rows = collect($n->capacity($n->from(), $n->to()))->filter(fn (array $m) => $m['planned'] > 0)->take(8)->map(function (array $m) {
            $pct = $m['available'] ? $m['planned'] / $m['available'] * 100 : null;

            return ['title' => $m['code'].' · '.$m['name'],
                'sub' => $pct === null ? $this->say(':a h planned', ['a' => Locale::decimal($m['planned'], 1)])
                    : $this->say(':a h planned of :b h available', ['a' => Locale::decimal($m['planned'], 1), 'b' => Locale::decimal($m['available'], 1)]),
                'value' => $pct === null ? t('No capacity set') : Locale::percent($pct, 0), 'tone' => $pct === null ? 'neutral' : ($pct > 100 ? 'danger' : ($pct >= 85 ? 'warn' : 'success'))];
        })->values()->all();

        return ['caption' => raw($n->rangeText()), 'source' => 'tm2mst.prod_machine · tm2prd.prd_wo_operation',
            'data' => ['rows' => $rows, 'empty' => 'No operation is scheduled on a machine in this period'], 'open' => $this->open('PROD_WO_LIST', 'Work Order', 'fa-solid fa-gears')];
    }
}
