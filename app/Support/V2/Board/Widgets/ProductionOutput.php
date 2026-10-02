<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Hasil produksi periode itu (qty baik yang dicatat) terhadap target (qty operasi Work Order yang dijadwalkan selesai di periode itu)
// dan terhadap periode lalu. User 2026-10-02: "on production, target vs result". Referensi: completion % (Business Central, ERPNext).
final class ProductionOutput extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'production_output', 'title' => 'Production: Target vs Result', 'type' => 'kpi', 'dashboard' => 'PRODUCTION', 'module' => 'PROD',
            'description' => 'Good quantity recorded in the period against the quantity of Work Order operations planned to finish in the period.',
            'icon' => 'fa-industry', 'phrases' => ['hasil produksi', 'output produksi', 'target vs hasil', 'target produksi', 'production output', 'production result'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        [$prevFrom, $prevTo] = $n->range(1);
        $now = $n->production($n->from(), $n->to());
        $before = $n->production($prevFrom, $prevTo);

        return ['source' => 'tm2prd.prd_wo_operation (qty, target_finish) · prd_wo_operation_output (qty_good)', 'data' => ['value' => $now['result'], 'previous' => $before['result'],
            'target' => $now['target'] > 0 ? $now['target'] : null, 'format' => 'number', 'hint' => $now['target'] > 0 ? null : 'No operation is planned to finish in this period',
            'note' => raw($this->say(':a operations planned · :b output records', ['a' => $now['operations'], 'b' => $now['outputs']]))]];
    }
}
