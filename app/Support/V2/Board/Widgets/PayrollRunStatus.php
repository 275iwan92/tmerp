<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;
use Devlib\Locale;

// Empat perhitungan gaji terakhir: periode, jumlah slip, slip beranomali, dan statusnya. Tanpa nilai rupiah.
// Referensi: current pay run status (Zoho Payroll).
final class PayrollRunStatus extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'payroll_run_status', 'title' => 'Payroll Runs', 'type' => 'list', 'dashboard' => 'PAYROLL', 'module' => 'HRM',
            'description' => 'The latest payroll runs with their status, number of payslips and payslips flagged with an anomaly.',
            'icon' => 'fa-list-check', 'tone' => 'teal', 'phrases' => ['status gaji', 'perhitungan gaji', 'payroll run', 'pay run status'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $rows = array_map(fn (array $r) => ['title' => Locale::d($r['start']).' – '.Locale::d($r['end']),
            'sub' => $this->say(':a payslips · :b with anomaly', ['a' => $r['slips'], 'b' => $r['anomaly']]), 'value' => $r['status'],
            'tone' => in_array($r['status'], ['PAID', 'CLOSED'], true) ? 'success' : 'warn'], array_slice(array_reverse($n->payrollRuns()), 0, 4));

        return ['source' => 'tm2pyr.pyr_payroll_run + pyr_payslip', 'data' => ['rows' => $rows, 'empty' => 'No payroll run yet'],
            'open' => $this->open('HRM_PAYROLL_CALC', 'Payroll Calculation', 'fa-solid fa-calculator')];
    }
}
