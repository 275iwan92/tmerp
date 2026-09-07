<?php

namespace App\Services\Hrm;

use App\Models\Master\AttDailyStatus;
use App\Models\Master\AttFingerRaw;
use App\Models\Master\EmpDisciplinary;
use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpLeaveRequest;
use App\Models\Master\EmpOvertimeRequest;
use App\Models\Master\EmpShiftDetail;
use App\Models\Master\EmpShiftRoster;
use App\Models\Master\SysHoliday;
use Carbon\Carbon;
use Illuminate\Support\Collection;

// docs/attendancepayroll_tier2.md §7 -- urutan evaluasi status harian.
// Efek SP1/SP2/SP3 (Tier 2 §6) SENGAJA tidak dievaluasi di sini -- itu
// layer tambahan yang diterapkan PayrollEngineService saat payroll run
// (Tier 2 §7 langkah 5), bukan menggantikan status harian.
class AttendanceCalculationService
{
    // Toleransi tidak-finger per Tier 2 §4.3 -- max 2x per periode (F lalu FF),
    // ke-3 dst BELUM ada aturan di sumber (Open Item), direklasifikasi ke A.
    private const MAX_FINGER_TOLERANCE_EVENTS = 2;

    public function calculateForDate(int $employeeId, Carbon $date, Carbon $periodStart): AttDailyStatus
    {
        $leave = $this->approvedLeaveCovering($employeeId, $date);
        if ($leave) {
            return $this->save($employeeId, $date, $this->mapLeaveToStatusCode($leave));
        }

        $scans = AttFingerRaw::where('employee_id', $employeeId)
            ->whereBetween('scan_datetime', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->orderBy('scan_datetime')
            ->get();

        if ($scans->isEmpty()) {
            $shielding = $this->activeDisciplinaryCode($employeeId, $date, ['SKR', 'DRM']);
            if ($shielding) {
                return $this->save($employeeId, $date, $shielding);
            }

            return $this->applyFingerTolerance($employeeId, $date, $periodStart);
        }

        $shiftDetail = $this->resolveShiftDetail($employeeId, $date);
        $fingerIn = $scans->first()->scan_datetime;
        $fingerOut = $scans->count() > 1 ? $scans->last()->scan_datetime : null;

        [$statusCode, $minutesLate, $minutesEarly, $minutesOut] = $this->evaluateTimeVariant($shiftDetail, $date, $fingerIn, $fingerOut, $scans);

        $lateSeq = null;
        if ($statusCode === 'T+5' || $statusCode === 'PAT+5') {
            $lateSeq = $this->countPriorLateViolations($employeeId, $periodStart, $date) + 1;
        }

        [$overtimeMinutes, $overtimeType] = $this->resolveOvertime($employeeId, $date);
        $isUangRoti = $overtimeMinutes > 120 && $this->isProductionStaffOrSpv($employeeId);

        return $this->save(
            $employeeId,
            $date,
            $statusCode,
            $fingerIn,
            $fingerOut,
            $shiftDetail?->start_time?->format('H:i'),
            $shiftDetail?->end_time?->format('H:i'),
            $minutesLate,
            $minutesEarly,
            $minutesOut,
            $overtimeMinutes,
            $overtimeType,
            $isUangRoti,
            $lateSeq,
        );
    }

    private function approvedLeaveCovering(int $employeeId, Carbon $date): ?EmpLeaveRequest
    {
        return EmpLeaveRequest::where('employee_id', $employeeId)
            ->where('status', 'APPROVED')
            ->whereDate('date_from', '<=', $date)
            ->whereDate('date_to', '>=', $date)
            ->first();
    }

    private function mapLeaveToStatusCode(EmpLeaveRequest $leave): string
    {
        if ($leave->converted_to_izin) {
            return $leave->is_paid ? 'IS' : 'I';
        }

        // Tier 2 §4.1: SAKIT wajib surat dokter -- tanpa dokumen direklasifikasi
        // ke Alpha (dikonfirmasi silang thd sp_process_attlogs sistem lama,
        // yang juga membedakan efek payroll sakit dgn/tanpa surat dokter).
        if ($leave->leave_type === 'SAKIT' && ! $leave->attachment_surat_dokter) {
            return 'A';
        }

        return match ($leave->leave_type) {
            'SAKIT' => 'S',
            'CUTI' => 'C',
            'CUTI_KHUSUS' => str_contains(mb_strtoupper($leave->khusus_event_type ?? ''), 'MELAHIRKAN') ? 'CL' : 'C',
            'IZIN' => $leave->is_paid ? 'IS' : 'I',
            default => 'I',
        };
    }

    private function activeDisciplinaryCode(int $employeeId, Carbon $date, array $types): ?string
    {
        $active = EmpDisciplinary::where('employee_id', $employeeId)
            ->whereIn('type', $types)
            ->get()
            ->first(fn (EmpDisciplinary $d) => $d->coversDate($date));

        return $active?->type;
    }

    private function resolveShiftDetail(int $employeeId, Carbon $date): ?EmpShiftDetail
    {
        $shiftId = EmpShiftRoster::where('employee_id', $employeeId)
            ->whereDate('roster_date', $date)
            ->value('shift_id');

        $shiftId ??= EmpEmployee::where('employee_id', $employeeId)->value('default_shift_id');

        if (! $shiftId) {
            return null;
        }

        return EmpShiftDetail::where('shift_id', $shiftId)
            ->where('day_of_week', $date->format('l'))
            ->first();
    }

    /**
     * @return array{0: string, 1: int, 2: int, 3: int} [status_code, minutes_late, minutes_early_leave, minutes_out_during_shift]
     */
    private function evaluateTimeVariant(?EmpShiftDetail $shift, Carbon $date, Carbon $fingerIn, ?Carbon $fingerOut, Collection $scans): array
    {
        if (! $shift || ! $shift->start_time || ! $shift->end_time) {
            // Tidak ada acuan shift -- catat hadir tanpa evaluasi keterlambatan
            // (butuh setup emp_shift_detail/emp_shift_roster, bukan default 07:30 -- Tier 2 §3).
            return ['H', 0, 0, 0];
        }

        $shiftStart = $date->copy()->setTimeFromTimeString($shift->start_time->format('H:i:s'));
        $shiftEnd = $date->copy()->setTimeFromTimeString($shift->end_time->format('H:i:s'));

        $minutesLate = max(0, $fingerIn->diffInMinutes($shiftStart, false) * -1);
        $minutesEarly = $fingerOut ? max(0, $shiftEnd->diffInMinutes($fingerOut, false) * -1) : 0;
        $minutesOut = $this->sumKeluarJamKerja($scans, $shiftStart, $shiftEnd);

        $isPa = $minutesEarly > 0;
        $lateBand = match (true) {
            $minutesLate === 0 => null,
            $minutesLate < 5 => 'T-5',
            $minutesLate <= 15 => 'T+5',
            default => 'T+15',
        };

        if ($minutesOut > 0 && ! $lateBand && ! $isPa) {
            return ['KJK', $minutesLate, $minutesEarly, $minutesOut];
        }

        $status = match (true) {
            $isPa && $lateBand === 'T-5' => 'PAT-5',
            $isPa && in_array($lateBand, ['T+5', 'T+15'], true) => 'PAT+5',
            $isPa => 'PA',
            $lateBand !== null => $lateBand,
            default => 'H',
        };

        return [$status, $minutesLate, $minutesEarly, $minutesOut];
    }

    // Menjumlahkan window keluar-masuk di tengah shift (Tier 2 §3: [DELTA-CONFIRM]
    // diasumsikan dijumlah, bukan window terpanjang) dari scan ke-2..n-1.
    private function sumKeluarJamKerja(Collection $scans, Carbon $shiftStart, Carbon $shiftEnd): int
    {
        if ($scans->count() <= 2) {
            return 0;
        }

        $interior = $scans->slice(1, -1)->values();
        $total = 0;
        for ($i = 0; $i + 1 < $interior->count(); $i += 2) {
            $out = $interior[$i]->scan_datetime;
            $in = $interior[$i + 1]->scan_datetime;
            if ($out->between($shiftStart, $shiftEnd) && $in->between($shiftStart, $shiftEnd)) {
                $total += $out->diffInMinutes($in);
            }
        }

        return $total;
    }

    private function countPriorLateViolations(int $employeeId, Carbon $periodStart, Carbon $beforeDate): int
    {
        return AttDailyStatus::where('employee_id', $employeeId)
            ->whereIn('status_code', ['T+5', 'PAT+5'])
            ->whereBetween('attendance_date', [$periodStart, $beforeDate->copy()->subDay()])
            ->count();
    }

    private function applyFingerTolerance(int $employeeId, Carbon $date, Carbon $periodStart): AttDailyStatus
    {
        $priorToleranceEvents = AttDailyStatus::where('employee_id', $employeeId)
            ->whereIn('status_code', ['F', 'FF'])
            ->whereBetween('attendance_date', [$periodStart, $date->copy()->subDay()])
            ->count();

        if ($priorToleranceEvents >= self::MAX_FINGER_TOLERANCE_EVENTS) {
            // Ke-3 dst dlm periode -- Open Item Tier 2 §4.3, direklasifikasi ke Alpha.
            return $this->save($employeeId, $date, 'A');
        }

        return $this->save($employeeId, $date, $priorToleranceEvents === 0 ? 'F' : 'FF');
    }

    // Tipe lembur (dasar multiplier pyr_overtime_rate) -- diadopsi dari pola
    // sistem lama sp_process_attlogs: AWAL/sebelum shift = EARLY, sisanya
    // dibedakan hari Minggu/libur nasional vs hari kerja biasa (tarif lembur
    // hari libur lebih tinggi per kebiasaan UU Ketenagakerjaan).
    /**
     * @return array{0: int, 1: ?string} [overtime_minutes, overtime_type]
     */
    private function resolveOvertime(int $employeeId, Carbon $date): array
    {
        $overtime = EmpOvertimeRequest::where('employee_id', $employeeId)
            ->whereDate('overtime_date', $date)
            ->where('status', 'APPROVED')
            ->first();

        if (! $overtime || $overtime->duration_minutes <= 0) {
            return [0, null];
        }

        $type = match (true) {
            $overtime->ot_type === 'AWAL' => 'EARLY',
            $date->dayOfWeek === Carbon::SUNDAY => 'SUNDAY',
            SysHoliday::isHoliday($date->toDateString()) => 'HOLIDAY',
            default => 'WORKDAY',
        };

        return [(int) $overtime->duration_minutes, $type];
    }

    // Heuristik "Staff & SPV Produksi" (Tier 2 §4.4) -- belum ada flag role
    // eksplisit di emp_employee/org_position, dicocokkan dari nama departemen +
    // posisi. Perlu diverifikasi ke user kalau ada role produksi yang lolos/salah tangkap.
    private function isProductionStaffOrSpv(int $employeeId): bool
    {
        $employee = EmpEmployee::with('position.department')->find($employeeId);
        $departmentName = mb_strtoupper($employee?->position?->department?->department_name ?? '');
        $positionName = mb_strtoupper($employee?->position?->position_name ?? '');

        return str_contains($departmentName, 'PRODUKSI')
            && (str_contains($positionName, 'STAFF') || str_contains($positionName, 'SPV'));
    }

    private function save(
        int $employeeId,
        Carbon $date,
        string $statusCode,
        ?Carbon $fingerIn = null,
        ?Carbon $fingerOut = null,
        ?string $shiftStart = null,
        ?string $shiftEnd = null,
        int $minutesLate = 0,
        int $minutesEarly = 0,
        int $minutesOut = 0,
        int $overtimeMinutes = 0,
        ?string $overtimeType = null,
        bool $isUangRoti = false,
        ?int $lateViolationSeq = null,
    ): AttDailyStatus {
        return AttDailyStatus::updateOrCreate(
            ['employee_id' => $employeeId, 'attendance_date' => $date->toDateString()],
            [
                'status_code' => $statusCode,
                'finger_in' => $fingerIn,
                'finger_out' => $fingerOut,
                'shift_start' => $shiftStart,
                'shift_end' => $shiftEnd,
                'minutes_late' => $minutesLate,
                'minutes_early_leave' => $minutesEarly,
                'minutes_out_during_shift' => $minutesOut,
                'overtime_minutes' => $overtimeMinutes,
                'overtime_type' => $overtimeType,
                'is_uang_roti' => $isUangRoti,
                'late_violation_seq_in_period' => $lateViolationSeq,
                'source' => 'AUTO',
            ],
        );
    }
}
