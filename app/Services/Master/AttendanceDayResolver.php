<?php

namespace App\Services\Master;

use App\Models\Master\EmpAttendanceLog;
use App\Models\Master\EmpLeaveRequest;
use App\Models\Master\EmpShiftRoster;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

// TIER_2_HRM.md Part D — dipakai bareng oleh hrm/attendance/index.blade.php
// (stat Hadir/Telat/Alpha bulan berjalan, banyak employee sekaligus) dan
// hrm/attendance/profile.blade.php (tabel detail harian, 1 employee).
// Jadwal & status TIDAK disimpan di DB (lihat TIER_3A_HRM.sql Bagian 3B) —
// selalu dihitung ulang dari emp_shift_roster/emp_shift_detail/emp_leave_request.
class AttendanceDayResolver
{
    public const STATUS_LABELS = [
        'HADIR' => 'Hadir',
        'ALPHA' => 'Alpha',
        'HARI_LIBUR' => 'Hari Libur',
        'CUTI' => 'Cuti',
        'SAKIT' => 'Sakit',
        'IZIN' => 'Izin',
        'CUTI_KHUSUS' => 'Cuti Khusus',
    ];

    public const STATUS_BADGE = [
        'HADIR' => 'bg-[#eaf7ee] text-[#1f8a4c]',
        'ALPHA' => 'bg-[#fdecec] text-[#a3241f]',
        'HARI_LIBUR' => 'bg-[#eef0f3] text-[#667]',
        'CUTI' => 'bg-[#eef3fb] text-[#2c5aa0]',
        'SAKIT' => 'bg-[#eef3fb] text-[#2c5aa0]',
        'IZIN' => 'bg-[#eef3fb] text-[#2c5aa0]',
        'CUTI_KHUSUS' => 'bg-[#eef3fb] text-[#2c5aa0]',
    ];

    // employeeId => [ 'Y-m-d' => rowData ] utk seluruh employeeId sekaligus,
    // 3 query total (bukan 3 query per employee) -- dipakai worklist grid.
    public function resolveForEmployees(array $employeeIds, string $dateFrom, string $dateTo): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $rosters = EmpShiftRoster::with('shift.details')
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('roster_date', [$dateFrom, $dateTo])
            ->get()
            ->groupBy('employee_id');

        $logs = EmpAttendanceLog::whereIn('employee_id', $employeeIds)
            ->whereBetween('attendance_date', [$dateFrom, $dateTo])
            ->get()
            ->groupBy('employee_id');

        $leaves = EmpLeaveRequest::whereIn('employee_id', $employeeIds)
            ->where('status', 'APPROVED')
            ->where('date_from', '<=', $dateTo)
            ->where('date_to', '>=', $dateFrom)
            ->get()
            ->groupBy('employee_id');

        $result = [];
        foreach ($employeeIds as $employeeId) {
            $result[$employeeId] = $this->buildRows(
                $dateFrom,
                $dateTo,
                $rosters->get($employeeId, collect())->keyBy(fn ($r) => $r->roster_date->toDateString()),
                $logs->get($employeeId, collect())->keyBy(fn ($l) => $l->attendance_date->toDateString()),
                $leaves->get($employeeId, collect()),
            );
        }

        return $result;
    }

    // Dipakai profile.blade.php -- 1 employee, rentang filter tanggal bebas.
    public function resolve(int $employeeId, string $dateFrom, string $dateTo): array
    {
        return $this->resolveForEmployees([$employeeId], $dateFrom, $dateTo)[$employeeId] ?? [];
    }

    private function buildRows(string $dateFrom, string $dateTo, Collection $rostersByDate, Collection $logsByDate, Collection $leaves): array
    {
        $rows = [];

        foreach (Carbon::parse($dateFrom)->toPeriod($dateTo) as $date) {
            $key = $date->toDateString();
            $roster = $rostersByDate->get($key);
            $log = $logsByDate->get($key);
            $leave = $leaves->first(fn ($lr) => $key >= $lr->date_from->toDateString() && $key <= $lr->date_to->toDateString());

            $detail = $roster?->shift?->details->firstWhere('day_of_week', $date->format('l'));
            $scheduledIn = $detail?->start_time?->format('H:i');
            $scheduledOut = $detail?->end_time?->format('H:i');

            $lateMinutes = null;
            if ($log?->clock_in_at && $scheduledIn) {
                $scheduledInAt = Carbon::parse($key.' '.$scheduledIn);
                if ($log->clock_in_at->gt($scheduledInAt)) {
                    $lateMinutes = $scheduledInAt->diffInMinutes($log->clock_in_at);
                }
            }

            $earlyLeaveMinutes = null;
            if ($log?->clock_out_at && $scheduledOut) {
                $scheduledOutAt = Carbon::parse($key.' '.$scheduledOut);
                if ($log->clock_out_at->lt($scheduledOutAt)) {
                    $earlyLeaveMinutes = $log->clock_out_at->diffInMinutes($scheduledOutAt);
                }
            }

            $status = match (true) {
                $leave !== null => $leave->leave_type,
                ! $roster => 'HARI_LIBUR',
                ! $log?->clock_in_at => 'ALPHA',
                default => 'HADIR',
            };

            $rows[$key] = [
                'date' => $key,
                'roster' => $roster,
                'log' => $log,
                'leave' => $leave,
                'scheduled_in' => $scheduledIn,
                'scheduled_out' => $scheduledOut,
                'late_minutes' => $lateMinutes,
                'early_leave_minutes' => $earlyLeaveMinutes,
                'status' => $status,
                'is_overtime' => (bool) $roster?->is_overtime,
            ];
        }

        return $rows;
    }
}
