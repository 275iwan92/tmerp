<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;
use Devlib\Locale;

// Gaji bersih perhitungan gaji (payroll run) terakhir, dibanding run sebelumnya. Hanya untuk yang berhak melihat nilai payroll.
// Referensi: payroll cost summary (Zoho Payroll), payroll expense KPI (NetSuite).
final class PayrollCost extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'payroll_cost', 'title' => 'Payroll Net Pay', 'type' => 'kpi', 'dashboard' => 'PAYROLL', 'module' => 'HRM', 'payroll' => true,
            'description' => 'Net pay of the latest payroll run against the run before, with overtime and deductions.',
            'icon' => 'fa-money-check-dollar', 'phrases' => ['biaya gaji', 'total gaji', 'gaji bersih', 'payroll cost', 'net pay'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $runs = $n->payrollRuns();
        $last = end($runs) ?: null;
        if (! $last) {
            return ['source' => 'tm2pyr.pyr_payroll_run + pyr_payslip', 'data' => ['value' => '—', 'format' => 'money', 'hint' => 'No payroll run yet']];
        }
        $before = $runs[count($runs) - 2] ?? null;

        return ['source' => 'tm2pyr.pyr_payroll_run + pyr_payslip', 'data' => ['value' => $last['net'], 'previous' => $before['net'] ?? null, 'format' => 'money',
            'hint' => raw(Locale::d($last['start']).' – '.Locale::d($last['end']).' · '.$this->say(':n payslips', ['n' => $last['slips']])),
            'note' => raw($this->say('Overtime :a · Deductions :b', ['a' => $this->money($last['overtime']), 'b' => $this->money($last['deduction'])]))]];
    }
}
