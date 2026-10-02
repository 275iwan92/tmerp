<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Jumlah Work Order per status, per hari ini (Cancelled tidak ikut). Referensi: work orders by status (ERPNext, Business Central).
final class WorkOrderStatus extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'work_order_status', 'title' => 'Work Orders by Status', 'type' => 'chart', 'dashboard' => 'PRODUCTION', 'module' => 'PROD',
            'description' => 'Work Orders by status: Draft, Simulated, Scheduled, In progress, On hold, Completed.',
            'icon' => 'fa-gears', 'phrases' => ['status wo', 'work order per status', 'status produksi', 'work orders by status', 'wo status'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $by = $n->workOrders();
        $status = ['DRAFT' => 'Draft', 'SIMULATED' => 'Simulated', 'SCHEDULED' => 'Scheduled', 'IN_PROGRESS' => 'In progress', 'ON_HOLD' => 'On hold', 'COMPLETED' => 'Completed'];

        return ['caption' => 'As of today', 'source' => 'tm2prd.prd_wo', 'open' => $this->open('PROD_WO_LIST', 'Work Order', 'fa-solid fa-gears'),
            'data' => ['chart' => 'donut', 'format' => 'number', 'height' => '240px', 'labels' => array_values($status),
                'series' => [['name' => t('Work Orders'), 'data' => array_map(fn (string $s) => (int) ($by[$s] ?? 0), array_keys($status))]]]];
    }
}
