<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Hutang supplier yang sudah lewat jatuh tempo, per hari ini; baris bawah = yang jatuh tempo 7 hari ke depan.
// Referensi: overdue purchase invoices, invoices due next week (Business Central, Zoho Books).
final class PayablesOverdue extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'payables_overdue', 'title' => 'Overdue Payables', 'type' => 'kpi', 'dashboard' => 'AR_AP', 'module' => 'FINACC', 'size' => 3,
            'description' => 'Unpaid supplier invoices that are past their due date, as of today, and what falls due in the next 7 days.',
            'icon' => 'fa-file-invoice', 'tone' => 'danger', 'phrases' => ['hutang jatuh tempo', 'hutang lewat jatuh tempo', 'hutang supplier', 'overdue payables', 'payables due'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $rows = $n->payables();
        $late = $rows->where('days_overdue', '>', 0);
        $week = $rows->filter(fn ($r) => $r->days_overdue <= 0 && $r->days_overdue >= -7);

        return ['source' => 'tm2acc.v1_fin_ap_aging', 'data' => ['value' => (float) $late->sum('outstanding'), 'format' => 'money', 'good_when' => 'down',
            'hint' => raw($this->say(':n invoices past due', ['n' => $late->count()])), 'note' => raw($this->say('Due in 7 days :v', ['v' => $this->money((float) $week->sum('outstanding'))]))]];
    }
}
