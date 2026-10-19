<?php

/*
 * REMARK
 * Tujuan       : Koreksi manual presensi harian oleh HR dari layar v2 hrm-attendance-form (ubah status hari itu dengan alasan audit, atau "Recalculate this day" yang melepas override dan menghitung ulang dari scan), dan hitung ulang presensi semua pegawai aktif untuk satu rentang tanggal (kartu di layar hrm-attendance-upload).
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::hrm.attendance-daily (diarsipkan di tmerp_unused/hrm_attendance_daily_v1_2026-10-19.blade.php): override = status_code baru + source MANUAL_OVERRIDE + override_by + override_reason (wajib, minimal 10 karakter, maks 255); hitung ulang rentang = calculateForDate untuk tiap pegawai aktif tiap tanggal (awal periode = tanggal mulai). Baru (Jo 2026-10-19): hitung ulang melewati hari MANUAL_OVERRIDE (AttendanceCalculationService), rentang dibatasi 92 hari.
 * Input        : Baris att_daily_status, status baru, alasan, rentang tanggal, user.
 * Output       : tm2mst.att_daily_status berubah; ValidationException (kunci document) bila status atau alasan salah, rentang salah atau terlalu panjang.
 * Dipakai oleh : routes/v2_hrm.php (v2.hrm.attendance.save, v2.hrm.attendance.recalculate), layar hrm-attendance-form dan hrm-attendance-upload, tes AttendanceOverrideServiceTest.
 * Status       : Aktif (T7e HRM, 2026-10-19).
 */

namespace App\Services\Hrm;

use App\Models\Master\AttDailyStatus;
use App\Models\Master\EmpEmployee;
use App\Models\Sys\SecUser;
use App\Support\Localization\Translator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AttendanceOverrideService
{
    public const MAX_RANGE_DAYS = 92;

    private const STATUS_DEFAULTS = [
        'H' => 'Present', 'T-5' => 'Late <5min', 'T+5' => 'Late 6-15min', 'T+15' => 'Late >15min',
        'PA' => 'Early Leave', 'PAT-5' => 'Early Leave + Late<5', 'PAT+5' => 'Early Leave + Late',
        'KJK' => 'Out During Work Hours', 'I' => 'Permission', 'IS' => 'Permission Approved', 'S' => 'Sick',
        'C' => 'Leave', 'CL' => 'Maternity Leave', 'A' => 'Absent', 'F' => 'No Fingerprint 1x', 'FF' => 'No Fingerprint 2x',
    ];

    private const STATUS_KEYS = [
        'H' => 'hrm_att_status_h', 'T-5' => 'hrm_att_status_t5', 'T+5' => 'hrm_att_status_t6_15', 'T+15' => 'hrm_att_status_t15',
        'PA' => 'hrm_att_status_pa', 'PAT-5' => 'hrm_att_status_pat5', 'PAT+5' => 'hrm_att_status_pat_plus',
        'KJK' => 'hrm_att_status_kjk', 'I' => 'hrm_att_status_i', 'IS' => 'hrm_att_status_is', 'S' => 'hrm_att_status_s',
        'C' => 'hrm_att_status_c', 'CL' => 'hrm_att_status_cl', 'A' => 'hrm_att_status_a', 'F' => 'hrm_att_status_f', 'FF' => 'hrm_att_status_ff',
    ];

    public function __construct(private AttendanceCalculationService $calc) {}

    public static function statusLabel(string $code): string
    {
        $key = self::STATUS_KEYS[$code] ?? null;

        return $key ? Translator::t($key, self::STATUS_DEFAULTS[$code]) : $code;
    }

    /** @return array<string, string> kode => "kode — label" */
    public static function statusOptions(): array
    {
        return collect(self::STATUS_DEFAULTS)->mapWithKeys(fn ($d, $code) => [$code => $code.' — '.self::statusLabel($code)])->all();
    }

    public function override(AttDailyStatus $day, string $status, string $reason, SecUser $user): void
    {
        $reason = trim($reason);
        if (! isset(self::STATUS_DEFAULTS[$status])) {
            throw ValidationException::withMessages(['document' => 'Choose a valid status.']);
        }
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 255) {
            throw ValidationException::withMessages(['document' => 'Enter an audit reason of 10 to 255 characters (for example the FDT form number).']);
        }
        DB::connection('tm2mst')->transaction(function () use ($day, $status, $reason, $user) {
            AttDailyStatus::whereKey($day->getKey())->lockForUpdate()->firstOrFail()
                ->update(['status_code' => $status, 'source' => 'MANUAL_OVERRIDE', 'override_by' => $user->login, 'override_reason' => $reason]);
        });
    }

    /** Lepas override (bila ada) lalu hitung ulang hari ini dari scan, shift, cuti dan sanksi. */
    public function recalculateDay(AttDailyStatus $day): AttDailyStatus
    {
        $date = $day->attendance_date->copy()->startOfDay();

        return DB::connection('tm2mst')->transaction(function () use ($day, $date) {
            AttDailyStatus::whereKey($day->getKey())->update(['source' => 'AUTO', 'override_by' => null, 'override_reason' => null]);

            return $this->calc->calculateForDate((int) $day->employee_id, $date, $date->copy()->startOfWeek(Carbon::MONDAY));
        });
    }

    /** @return array{employees: int, days: int, kept: int} pegawai, hari terhitung, override manual yang dipertahankan */
    public function recalculateRange(string $from, string $to): array
    {
        try {
            $start = Carbon::createFromFormat('Y-m-d', $from)->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', $to)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['document' => 'Enter both dates.']);
        }
        if ($end->lt($start)) {
            throw ValidationException::withMessages(['document' => 'The end date must not be before the start date.']);
        }
        if ($start->diffInDays($end) + 1 > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['document' => 'Choose a range of at most '.self::MAX_RANGE_DAYS.' days.']);
        }

        $ids = EmpEmployee::where('activate', true)->pluck('employee_id');
        $days = 0;
        foreach ($ids as $employeeId) {
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $this->calc->calculateForDate((int) $employeeId, $d->copy(), $start);
                $days++;
            }
        }

        return ['employees' => $ids->count(), 'days' => $days,
            'kept' => AttDailyStatus::where('source', 'MANUAL_OVERRIDE')->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])->count()];
    }

    /** Riwayat tab History: terbaru di atas. */
    public function history(AttDailyStatus $day): array
    {
        $rows = [['at' => $day->calculated_at?->toDateTimeString(), 'by' => 'System', 'action' => 'Calculated', 'note' => '']];
        if ($day->source === 'MANUAL_OVERRIDE') {
            $rows[] = ['at' => null, 'by' => $day->override_by, 'action' => 'Manual override', 'note' => (string) $day->override_reason];
        }

        return array_reverse($rows);
    }
}
