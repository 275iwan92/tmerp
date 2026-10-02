<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;
use Devlib\Locale;

// Permohonan cuti, izin, dan sakit yang menunggu keputusan. Referensi: leave today / pending approvals (Zoho People, Frappe HR).
final class LeaveWaiting extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'leave_waiting', 'title' => 'Leave Requests Waiting', 'type' => 'list', 'dashboard' => 'HR', 'module' => 'HRM', 'size' => 6,
            'description' => 'Leave, permit and sick requests that wait for a decision.',
            'icon' => 'fa-plane-departure', 'tone' => 'warn', 'phrases' => ['cuti menunggu', 'permohonan cuti', 'izin menunggu', 'leave requests', 'pending leave'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $all = $n->leaveWaiting();
        $names = $n->employeeNames($all->pluck('employee_id')->all());
        $rows = $all->take(6)->map(fn ($r) => ['title' => $names[$r->employee_id] ?? '#'.$r->employee_id,
            'sub' => $r->leave_type.' · '.Locale::d($r->date_from).($r->date_to && (string) $r->date_to !== (string) $r->date_from ? ' – '.Locale::d($r->date_to) : ''),
            'value' => $this->say(':n days', ['n' => Locale::decimal((float) $r->total_days, 1)]), 'tone' => 'warn'])->values()->all();

        return ['caption' => $all->count() ? raw($this->say(':n requests', ['n' => $all->count()])) : null, 'source' => 'tm2mst.emp_leave_request',
            'data' => ['rows' => $rows, 'empty' => 'No leave request waits for a decision'], 'open' => $this->open('HRM_LEAVE_APPROVAL', 'Leave Approval', 'fa-solid fa-plane-departure')];
    }
}
