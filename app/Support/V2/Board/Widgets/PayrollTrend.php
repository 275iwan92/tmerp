<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Gaji bersih per perhitungan gaji, delapan run terakhir. Referensi: total net wage trend (Odoo).
final class PayrollTrend extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'payroll_trend', 'title' => 'Net Pay per Payroll Run', 'type' => 'chart', 'dashboard' => 'PAYROLL', 'module' => 'HRM', 'payroll' => true, 'size' => 8,
            'description' => 'Net pay of the last eight payroll runs.',
            'icon' => 'fa-chart-column', 'phrases' => ['tren gaji', 'grafik gaji', 'gaji per periode', 'payroll trend', 'net pay trend'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $runs = $n->payrollRuns();

        return ['caption' => 'By period end date', 'source' => 'tm2pyr.pyr_payroll_run + pyr_payslip',
            'data' => ['chart' => 'bar', 'format' => 'money', 'height' => '280px', 'legend' => false, 'labels' => array_column($runs, 'label'),
                'series' => [['name' => t('Net pay'), 'data' => array_column($runs, 'net'), 'tone' => 'accent']]]];
    }
}
