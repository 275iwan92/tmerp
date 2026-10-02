<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Persen inquiry yang menang dari yang sudah selesai (menang + kalah) pada periode itu -- definisi laporan Win-Rate Sales.
// Referensi: quote-to-order conversion (PrintVis, SAP).
final class QuotationWinRate extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'quotation_win_rate', 'title' => 'Win Rate', 'type' => 'kpi', 'dashboard' => 'SALES', 'module' => 'SALES', 'size' => 3,
            'description' => 'Share of closed inquiries of the period that became a Customer Order: won against won plus lost.',
            'icon' => 'fa-trophy', 'tone' => 'success', 'phrases' => ['win rate', 'tingkat menang', 'konversi quotation', 'menang kalah', 'quote conversion'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $now = $n->winLoss($n->from(), $n->to());
        [$prevFrom, $prevTo] = $n->range(1);
        $before = $n->winLoss($prevFrom, $prevTo);
        $pct = fn (array $x) => ($x['won'] + $x['lost']) ? $x['won'] / ($x['won'] + $x['lost']) * 100 : null;
        if ($pct($now) === null) {
            return ['source' => 'tm2sdi.iqp_inquiry (sub_state)', 'data' => ['value' => '—', 'format' => 'percent', 'hint' => 'No inquiry was closed in this period']];
        }

        return ['source' => 'tm2sdi.iqp_inquiry (sub_state)', 'data' => ['value' => $pct($now), 'previous' => $pct($before), 'format' => 'percent',
            'note' => raw($this->say('Won :a · Lost :b', ['a' => $now['won'], 'b' => $now['lost']]))]];
    }
}
