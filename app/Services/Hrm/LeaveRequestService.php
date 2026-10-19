<?php

/*
 * REMARK
 * Tujuan       : Keputusan HR atas pengajuan Cuti / Sakit / Izin / Cuti Khusus (emp_leave_request, diajukan lewat Self-Service) dari layar v2 hrm-leave-form: Approve menambah used_days saldo cuti tahun awal cuti HANYA untuk pengajuan yang deducts_leave_balance (CUTI biasa; Sakit, Izin dan Cuti Khusus tidak memotong saldo) lalu memberi notifikasi; Reject wajib beralasan dan memberi notifikasi. Pengajuan yang disetujui dibaca AttendanceCalculationService untuk status harian.
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::hrm.leave-requests (diarsipkan di tmerp_unused/hrm_leave_requests_v1_2026-10-19.blade.php): hanya PENDING yang bisa diputuskan; saldo dibuat bila belum ada (entitlement 12, terpakai 0, bawaan 0) lalu used_days += total_days; status APPROVED + reviewed_by / reviewed_at; Reject = REJECTED + review_note maks 255. Aturan SOP pengajuan (H+7 / H-7, maks 3 hari, dokumen wajib) tetap di App\Support\V2\Data\SelfService saat submit. Tidak ada blokir saldo negatif (sama dengan v1).
 * Input        : Baris emp_leave_request, alasan (Reject), user.
 * Output       : tm2mst.emp_leave_request (+ emp_leave_balance saat Approve) berubah dalam satu transaksi; notifikasi tm2sys ke login pegawai setelah commit; ValidationException (kunci document) bila baris sudah diputuskan atau alasan kosong.
 * Dipakai oleh : routes/v2_hrm.php (v2.hrm.leave.decide), layar hrm-leave-form, grid LeaveRequestGrid, tes LeaveRequestServiceTest.
 * Status       : Aktif (T7d HRM, 2026-10-19).
 */

namespace App\Services\Hrm;

use App\Models\Master\EmpLeaveBalance;
use App\Models\Master\EmpLeaveRequest;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LeaveRequestService
{
    public const STATUSES = ['PENDING' => 'Waiting', 'APPROVED' => 'Approved', 'REJECTED' => 'Rejected'];

    private const EVENT_LABELS = [
        'MENIKAH' => 'Employee Wedding',
        'MENIKAHKAN_KHITAN_ANAK' => 'Child Wedding / Circumcision',
        'ISTRI_MELAHIRKAN' => 'Wife Childbirth / Miscarriage',
        'KELUARGA_MENINGGAL' => 'Death of Immediate Family Member',
        'IBADAH' => 'Religious Duty',
    ];

    private const IZIN_CATEGORY_LABELS = [
        'MUSIBAH' => 'Calamity',
        'INSTANSI_RESMI' => 'Official Agency',
        'KEPENGURUSAN_MUSIBAH' => 'Calamity Arrangements',
    ];

    /** Kategori kejadian (cuti khusus) atau kategori izin, bila ada. */
    public static function eventLabel(EmpLeaveRequest $r): ?string
    {
        return match (true) {
            (bool) $r->khusus_event_type => t(self::EVENT_LABELS[$r->khusus_event_type] ?? $r->khusus_event_type),
            (bool) $r->izin_category => t(self::IZIN_CATEGORY_LABELS[$r->izin_category] ?? $r->izin_category),
            default => null,
        };
    }

    /** @return array<string, string> label lampiran => path di disk public */
    public static function attachments(EmpLeaveRequest $r): array
    {
        return array_filter(['Leave Letter' => $r->attachment_surat_cuti, 'Doctor Note' => $r->attachment_surat_dokter, 'Receipt / Prescription' => $r->attachment_kwitansi_resep,
            'JKN Screenshot' => $r->attachment_jkn_screenshot, 'Attachment' => $r->attachment_path]);
    }

    /** Saldo cuti tahun awal pengajuan (hanya baca; null bila belum pernah dibuat). */
    public static function balance(EmpLeaveRequest $r): ?EmpLeaveBalance
    {
        return EmpLeaveBalance::where('employee_id', $r->employee_id)->where('year', $r->date_from->year)->first();
    }

    public function approve(EmpLeaveRequest $req, SecUser $user): void
    {
        $req = DB::connection('tm2mst')->transaction(function () use ($req, $user) {
            $req = $this->pending($req);
            if ($req->deducts_leave_balance) {
                $balance = EmpLeaveBalance::firstOrCreate(['employee_id' => $req->employee_id, 'year' => $req->date_from->year], ['entitlement_days' => 12, 'used_days' => 0, 'carried_over_days' => 0]);
                $balance->increment('used_days', $req->total_days);
            }
            $req->update(['status' => 'APPROVED', 'reviewed_by' => $user->login, 'reviewed_at' => now()]);

            return $req;
        });

        $this->notify($req, 'Pengajuan disetujui', "Pengajuan {$req->leave_type} tanggal {$req->date_from->format('d M Y')} s/d {$req->date_to->format('d M Y')} disetujui HRD.", 'success');
    }

    public function reject(EmpLeaveRequest $req, string $reason, SecUser $user): void
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255) {
            throw ValidationException::withMessages(['document' => 'Enter a rejection reason of at most 255 characters.']);
        }
        $req = DB::connection('tm2mst')->transaction(function () use ($req, $reason, $user) {
            $req = $this->pending($req);
            $req->update(['status' => 'REJECTED', 'reviewed_by' => $user->login, 'review_note' => $reason, 'reviewed_at' => now()]);

            return $req;
        });

        $this->notify($req, 'Pengajuan ditolak', "Pengajuan {$req->leave_type} ditolak HRD: {$reason}.", 'warning');
    }

    /** Baris dikunci dan diperiksa PENDING di dalam transaksi supaya saldo tidak terpotong dua kali. */
    private function pending(EmpLeaveRequest $req): EmpLeaveRequest
    {
        $fresh = EmpLeaveRequest::whereKey($req->getKey())->lockForUpdate()->first();
        if (! $fresh || $fresh->status !== 'PENDING') {
            throw ValidationException::withMessages(['document' => 'This request is not waiting for a decision.']);
        }

        return $fresh;
    }

    private function notify(EmpLeaveRequest $req, string $title, string $message, string $type): void
    {
        if ($owner = SecUser::where('employee_id', $req->employee_id)->first()) {
            SysNotification::create(['user_id' => $owner->login, 'title' => $title, 'message' => $message, 'type' => $type, 'is_read' => false]);
        }
    }

    /** Riwayat tab History: terbaru di atas. */
    public function history(EmpLeaveRequest $req): array
    {
        $rows = [['at' => $req->requested_at?->toDateTimeString(), 'by' => $req->employee?->full_name, 'action' => 'Submitted', 'note' => (string) $req->reason]];
        if ($req->reviewed_at) {
            $rows[] = ['at' => $req->reviewed_at->toDateTimeString(), 'by' => $req->reviewed_by, 'action' => $req->status === 'REJECTED' ? 'Rejected' : 'Approved', 'note' => (string) $req->review_note];
        }

        return array_reverse($rows);
    }
}
