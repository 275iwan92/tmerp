<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Master\AttFingerRaw;
use App\Models\Master\AttMobileGpsLog;
use App\Services\Hrm\AttendanceCalculationService;
use App\Services\Hrm\GpsHaversineService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

// HRM > Time Attendance > GPS Clock-In (v2): objek x-dl::geo-clock di atas aturan layar v1. Jarak dan keputusan dihitung SERVER
// (GpsHaversineService, lokasi dari sys_geofence_location); dalam radius = AUTO_APPROVED dan langsung menjadi presensi (att_finger_raw
// MOBILE-GPS + AttendanceCalculationService); di luar radius = PENDING, menunggu approval HR (GPS Location Approval), belum tercatat.
final class GpsClock
{
    public const TYPES = ['IN', 'OUT'];

    /** @return array{distance: float, in_radius: bool} */
    public static function check(float $lat, float $lng): array
    {
        $r = app(GpsHaversineService::class)->nearestLocation($lat, $lng);

        return ['distance' => $r['distance_meters'], 'in_radius' => $r['is_in_radius']];
    }

    /** @return array{message: string, tone: string, history: list<array<string, string>>} */
    public static function clock(?int $employeeId, string $type, float $lat, float $lng): array
    {
        if (! $employeeId) {
            throw ValidationException::withMessages(['employee' => t('This account is not linked to an employee record — contact HR to enable mobile attendance.')]);
        }
        if (! in_array($type, self::TYPES, true)) {
            throw ValidationException::withMessages(['type' => t('Invalid clock type.')]);
        }

        $c = self::check($lat, $lng);
        $log = AttMobileGpsLog::create(['employee_id' => $employeeId, 'clock_type' => $type, 'latitude' => $lat, 'longitude' => $lng,
            'distance_meters' => (int) round($c['distance']), 'geofence_status' => $c['in_radius'] ? 'IN_RADIUS' : 'OUT_OF_RADIUS',
            'hr_approval_status' => $c['in_radius'] ? 'AUTO_APPROVED' : 'PENDING']);

        if ($c['in_radius']) {
            self::writeAttendance($log);
        }

        return ['message' => $c['in_radius'] ? t('Attendance saved — inside the work location radius.')
            : t('Location is outside the work location radius — the request was sent to HR for approval; attendance is not recorded until approved.'),
            'tone' => $c['in_radius'] ? 'success' : 'warn', 'history' => self::history($employeeId)];
    }

    /** @return list<array{type: string, time: string, status: string, tone: string}> catatan hari ini, lama -> baru */
    public static function history(?int $employeeId): array
    {
        if (! $employeeId) {
            return [];
        }

        return AttMobileGpsLog::where('employee_id', $employeeId)->whereDate('created_at', now())->orderBy('created_at')->get()
            ->map(fn (AttMobileGpsLog $l) => ['type' => $l->clock_type, 'time' => $l->created_at->format('H:i:s'), 'status' => (string) $l->hr_approval_status,
                'tone' => $l->hr_approval_status === 'PENDING' ? 'warn' : 'success'])->all();
    }

    private static function writeAttendance(AttMobileGpsLog $log): void
    {
        AttFingerRaw::create(['id_import' => null, 'employee_id' => $log->employee_id, 'scan_datetime' => $log->created_at ?? now(),
            'scan_type' => $log->clock_type, 'raw_device_code' => 'MOBILE-GPS']);
        $today = Carbon::today();
        app(AttendanceCalculationService::class)->calculateForDate($log->employee_id, $today, $today->copy()->startOfWeek(Carbon::MONDAY));
    }
}
