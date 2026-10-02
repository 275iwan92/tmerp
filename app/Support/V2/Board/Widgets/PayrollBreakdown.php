<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Komponen penghasilan perhitungan gaji terakhir: pokok, tunjangan, bonus, lembur; potongan disebut di keterangan.
// Referensi: payroll cost summary per komponen (Zoho Payroll), salary register (Frappe HR).
final class PayrollBreakdown extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'payroll_breakdown', 'title' => 'Payroll Components', 'type' => 'chart', 'dashboard' => 'PAYROLL', 'module' => 'HRM', 'payroll' => true,
            'description' => 'Earnings of the latest payroll run by component: basic pay, allowances, bonus, overtime.',
            'icon' => 'fa-chart-pie', 'tone' => 'violet', 'phrases' => ['komponen gaji', 'rincian gaji', 'payroll components', 'payroll breakdown'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $runs = $n->payrollRuns();
        $last = end($runs) ?: ['basic' => 0, 'allowance' => 0, 'bonus' => 0, 'overtime' => 0, 'deduction' => 0];

        return ['caption' => raw($this->say('Deductions :v', ['v' => $this->money($last['deduction'])])), 'source' => 'tm2pyr.pyr_payslip',
            'data' => ['chart' => 'donut', 'format' => 'money', 'height' => '240px', 'labels' => [t('Basic pay'), t('Allowances'), t('Bonus'), t('Overtime')],
                'series' => [['name' => t('Earnings'), 'data' => [$last['basic'], $last['allowance'], $last['bonus'], $last['overtime']]]]]];
    }
}
