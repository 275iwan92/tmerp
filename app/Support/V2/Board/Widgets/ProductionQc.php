<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Operasi Work Order yang belum selesai menurut hasil QC: menunggu QC, ditolak, lulus.
final class ProductionQc extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'production_qc', 'title' => 'Production QC', 'type' => 'chart', 'dashboard' => 'QUALITY', 'module' => 'PROD',
            'description' => 'Operations of open Work Orders at the quality gate: on QC hold, rejected, passed.',
            'icon' => 'fa-vial-circle-check', 'phrases' => ['qc produksi', 'qc hold', 'qc ditolak', 'production qc', 'quality gate'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $by = $n->operationStatus();
        $status = ['QC_HOLD' => 'QC hold', 'QC_REJECTED' => 'QC rejected', 'QC_PASSED' => 'QC passed'];

        return ['caption' => 'Open Work Orders, as of today', 'source' => 'tm2prd.prd_wo_operation (task_status)',
            'data' => ['chart' => 'donut', 'format' => 'number', 'height' => '240px', 'labels' => array_values($status),
                'series' => [['name' => t('Operations'), 'data' => array_map(fn (string $s) => (int) ($by[$s] ?? 0), array_keys($status))]]]];
    }
}
