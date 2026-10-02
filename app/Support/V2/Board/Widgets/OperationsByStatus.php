<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Operasi Work Order yang belum selesai, per status (istilah status selalu English).
final class OperationsByStatus extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'operations_by_status', 'title' => 'Operations by Status', 'type' => 'chart', 'dashboard' => 'PRODUCTION', 'module' => 'PROD',
            'description' => 'Operations of open Work Orders by status: Pending, Assigned, Running, Breakdown, QC hold, QC passed.',
            'icon' => 'fa-diagram-project', 'phrases' => ['status operasi', 'operasi produksi', 'operations by status', 'operation status'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $by = $n->operationStatus();
        $status = ['PENDING' => 'Pending', 'ASSIGNED' => 'Assigned', 'RUNNING' => 'Running', 'BREAKDOWN' => 'Breakdown', 'SUBCONTRACT' => 'Subcontract', 'DONE' => 'Done',
            'QC_HOLD' => 'QC hold', 'QC_REJECTED' => 'QC rejected', 'QC_PASSED' => 'QC passed'];
        $shown = array_filter($status, fn (string $s) => ($by[$s] ?? 0) > 0, ARRAY_FILTER_USE_KEY) ?: $status;

        return ['caption' => 'Open Work Orders, as of today', 'source' => 'tm2prd.prd_wo_operation',
            'data' => ['chart' => 'donut', 'format' => 'number', 'height' => '240px', 'labels' => array_values($shown),
                'series' => [['name' => t('Operations'), 'data' => array_map(fn (string $s) => (int) ($by[$s] ?? 0), array_keys($shown))]]]];
    }
}
