<?php

/*
 * REMARK
 * Tujuan       : Keputusan HR atas presensi GPS di luar radius (att_mobile_gps_log berstatus PENDING) dari layar v2 hrm-gps-form: Approve menuliskan scan presensi (att_finger_raw, perangkat MOBILE-GPS) lalu menghitung ulang status harian pegawai; Reject wajib beralasan. Sebelum disetujui presensi TIDAK tercatat sama sekali.
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::hrm.gps-approval (diarsipkan di tmerp_unused/hrm_gps_approval_v1_2026-10-19.blade.php): hanya baris PENDING yang bisa diputuskan, Approve = status APPROVED + hr_approved_by / hr_approved_at + satu baris att_finger_raw (waktu = waktu clock, jenis IN / OUT) + AttendanceCalculationService::calculateForDate untuk tanggal itu (minggu mulai Senin); Reject = status REJECTED + alasan maks 255. Baris AUTO_APPROVED (dalam radius) dibuat GpsClock dan tidak melewati layar ini.
 * Input        : Baris att_mobile_gps_log, alasan (Reject), user.
 * Output       : tm2mst.att_mobile_gps_log berubah; Approve juga menambah att_finger_raw dan memperbarui att_daily_status, semuanya dalam satu transaksi; ValidationException (kunci document) bila baris sudah diputuskan atau alasan kosong.
 * Dipakai oleh : routes/v2_hrm.php (v2.hrm.gps.decide), layar hrm-gps-form, tes GpsApprovalServiceTest.
 * Status       : Aktif (T7 HRM, 2026-10-19).
 */

namespace App\Services\Hrm;

use App\Models\Master\AttFingerRaw;
use App\Models\Master\AttMobileGpsLog;
use App\Models\Sys\SecUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GpsApprovalService
{
    public const STATUSES = ['PENDING' => 'Waiting', 'APPROVED' => 'Approved', 'AUTO_APPROVED' => 'Approved (inside radius)', 'REJECTED' => 'Rejected'];

    public function approve(AttMobileGpsLog $log, SecUser $user): void
    {
        DB::connection('tm2mst')->transaction(function () use ($log, $user) {
            $log = $this->pending($log);
            $log->update(['hr_approval_status' => 'APPROVED', 'hr_approved_by' => $user->login, 'hr_approved_at' => now()]);

            AttFingerRaw::create(['id_import' => null, 'employee_id' => $log->employee_id, 'scan_datetime' => $log->created_at, 'scan_type' => $log->clock_type, 'raw_device_code' => 'MOBILE-GPS']);

            $date = $log->created_at->copy()->startOfDay();
            app(AttendanceCalculationService::class)->calculateForDate($log->employee_id, $date, $date->copy()->startOfWeek(Carbon::MONDAY));
        });
    }

    public function reject(AttMobileGpsLog $log, string $reason, SecUser $user): void
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255) {
            throw ValidationException::withMessages(['document' => 'Enter a rejection reason of at most 255 characters.']);
        }
        DB::connection('tm2mst')->transaction(function () use ($log, $reason, $user) {
            $this->pending($log)->update(['hr_approval_status' => 'REJECTED', 'hr_approved_by' => $user->login, 'hr_approved_at' => now(), 'hr_approval_note' => $reason]);
        });
    }

    /** Baris dikunci dan diperiksa ulang di dalam transaksi supaya dua HR tidak memutuskan baris yang sama. */
    private function pending(AttMobileGpsLog $log): AttMobileGpsLog
    {
        $fresh = AttMobileGpsLog::whereKey($log->getKey())->lockForUpdate()->first();
        if (! $fresh || $fresh->hr_approval_status !== 'PENDING') {
            throw ValidationException::withMessages(['document' => 'This GPS record is not waiting for a decision.']);
        }

        return $fresh;
    }

    /** Riwayat tab History: terbaru di atas. */
    public function history(AttMobileGpsLog $log): array
    {
        $rows = [['at' => $log->created_at?->toDateTimeString(), 'by' => $log->employee?->full_name, 'action' => $log->hr_approval_status === 'AUTO_APPROVED' ? 'Clock (inside radius)' : 'Clock (outside radius)', 'note' => '']];
        if ($log->hr_approved_at) {
            $rows[] = ['at' => $log->hr_approved_at->toDateTimeString(), 'by' => $log->hr_approved_by, 'action' => $log->hr_approval_status === 'REJECTED' ? 'Rejected' : 'Approved', 'note' => (string) $log->hr_approval_note];
        }

        return array_reverse($rows);
    }
}
