<?php

namespace App\Support\V2\Grid\Hrm;

use App\Models\Master\AttMobileGpsLog;
use App\Models\Master\EmpEmployee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

// Approval Lokasi GPS (v1: pages::hrm.gps-approval).
class GpsApprovalGrid extends HrmGrid
{
    public function slug(): string
    {
        return 'hrm-gps';
    }

    public function title(): string
    {
        return t('GPS Location Approval');
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    protected function v1Route(): ?string
    {
        return 'hrm.gps-approval';
    }

    protected function canCreate(): bool
    {
        return false;
    }

    protected function models(): array
    {
        return [AttMobileGpsLog::class, EmpEmployee::class];
    }

    public function search(): string
    {
        return 'Search employee...';
    }

    protected function query(): Builder
    {
        return AttMobileGpsLog::query()->with('employee')
            ->orderByRaw("FIELD(hr_approval_status, 'PENDING', 'APPROVED', 'REJECTED', 'AUTO_APPROVED')")->orderByDesc('created_at');
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Employee No. / Name', 'type' => 'doc', 'party' => 'name'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'time', 'label' => 'Time'],
            ['key' => 'type', 'label' => 'Type', 'type' => 'badge', 'tones' => [t('Clock In') => 'info', t('Clock Out') => 'neutral']],
            ['key' => 'distance', 'label' => 'Distance (m)', 'type' => 'number'],
            ['key' => 'geofence', 'label' => 'Radius', 'type' => 'badge', 'tones' => [t('Outside radius') => 'danger', t('Inside radius') => 'success']],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::REQUEST_TONES],
        ];
    }

    protected function row(Model $m): array
    {
        $at = $m->created_at ? Carbon::parse($m->created_at) : null;

        return [
            'name' => $m->employee?->full_name, 'code' => $m->employee?->employee_code,
            'date' => $at?->toDateString(), 'time' => $at?->format('H:i'),
            'type' => t($m->clock_type === 'IN' ? 'Clock In' : 'Clock Out'), 'distance' => (float) $m->distance_meters,
            'geofence' => t($m->geofence_status === 'OUT_OF_RADIUS' ? 'Outside radius' : 'Inside radius'), 'status' => $m->hr_approval_status,
        ];
    }

    public function stats(): array
    {
        return $this->requestStats();
    }

    public function filters(): array
    {
        return ['type' => 'Type', 'geofence' => 'Radius', 'status' => 'Status'];
    }
}
