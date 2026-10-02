<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;
use Carbon\CarbonImmutable;
use Devlib\Locale;

// Work Order yang lewat due date dan belum selesai, yang paling lama terlambat di atas. Referensi: jobs late (print MIS).
final class LateWorkOrders extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'late_work_orders', 'title' => 'Late Work Orders', 'type' => 'list', 'group' => 'Production', 'module' => 'PROD',
            'description' => 'Work Orders that are past their due date and not completed yet, longest overdue first.',
            'icon' => 'fa-triangle-exclamation', 'tone' => 'danger', 'phrases' => ['wo terlambat', 'work order terlambat', 'produksi terlambat', 'late work orders', 'late jobs'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $late = $n->lateWorkOrders();
        $rows = $late->take(6)->map(fn ($w) => ['title' => (string) $w->wo_no, 'sub' => $w->wo_status.' · '.Locale::d($w->due_date),
            'value' => $this->say('Late :n days', ['n' => (int) CarbonImmutable::parse($w->due_date)->startOfDay()->diffInDays($n->now->startOfDay())]), 'tone' => 'danger'])->values()->all();

        return ['caption' => $late->count() ? raw($this->say(':n work orders', ['n' => $late->count()])) : null, 'source' => 'tm2prd.prd_wo',
            'data' => ['rows' => $rows, 'empty' => 'No Work Order is late'], 'open' => $this->open('PROD_WO_LIST', 'Work Order', 'fa-solid fa-gears')];
    }
}
