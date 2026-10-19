<?php

/*
 * REMARK
 * Tujuan       : Keputusan HR atas pengajuan koreksi data pegawai (emp_correction_request, dari Self-Service "Ajukan Koreksi" dan Setup Keamanan Akun) dari layar v2 hrm-correction-form: Approve menerapkan nilai baru ke kolom emp_employee yang diizinkan, atau rekening gaji lewat EmployeeBankService (field lain, mis. BPJS, hanya ditutup: HR memperbarui manual); Reject wajib beralasan dan mengirim notifikasi ke login pegawai. Rekening gaji = data payroll: nilainya disamarkan dan keputusannya hanya untuk yang berhak payroll atas pegawai itu (EmployeeProfileService::canEditBank).
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::hrm.correction-requests (diarsipkan di tmerp_unused/hrm_correction_requests_v1_2026-10-19.blade.php): hanya PENDING yang bisa diputuskan; Approve = kolom terpetakan (Tanggal Lahir, NIK KTP, Telepon / WA, Alamat KTP, Alamat Domisili, Kontak Darurat) diperbarui + status APPROVED + reviewed_by / reviewed_at; Reject = status REJECTED + review_note maks 255 + sys_notifications ke sec_user pegawai.
 * Input        : Baris emp_correction_request, alasan (Reject), user.
 * Output       : tm2mst.emp_correction_request berubah (+ emp_employee atau emp_bank_account saat Approve) dalam satu transaksi; notifikasi tm2sys setelah commit; ValidationException (kunci document) bila baris sudah diputuskan, alasan kosong, atau tidak berhak payroll atas permintaan rekening.
 * Dipakai oleh : routes/v2_hrm.php (v2.hrm.correction.decide), layar hrm-correction-form, grid CorrectionRequestGrid, tes CorrectionRequestServiceTest dan EmployeeBankSupplierTest.
 * Status       : Aktif (T7c HRM, 2026-10-19).
 */

namespace App\Services\Hrm;

use App\Models\Master\EmpCorrectionRequest;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CorrectionRequestService
{
    public const STATUSES = ['PENDING' => 'Waiting', 'APPROVED' => 'Approved', 'REJECTED' => 'Rejected'];

    // Field yang diterapkan otomatis ke emp_employee saat Approve; field lain (tabel relasi seperti BPJS) hanya ditutup.
    public const FIELD_COLUMN_MAP = [
        'Tanggal Lahir' => 'birth_date',
        'NIK KTP' => 'nik_ktp',
        'Telepon / WA' => 'phone',
        'Alamat KTP' => 'address_detail_ktp',
        'Alamat Domisili' => 'address_detail_domisili',
        'Kontak Darurat' => 'emergency_contact_name',
    ];

    public static function isBank(EmpCorrectionRequest $r): bool
    {
        return $r->field_name === EmpCorrectionRequest::FIELD_BANK;
    }

    /** Hak payroll atas pegawai pemilik pengajuan rekening. */
    public static function canSeeBank(SecUser $user, EmpCorrectionRequest $r): bool
    {
        return EmployeeProfileService::canEditBank($user, $r->employee);
    }

    /** @return array{old: string, new: string} nilai lama dan baru; rekening gaji disamarkan bagi yang tidak berhak payroll */
    public static function values(EmpCorrectionRequest $r, SecUser $user): array
    {
        if (self::isBank($r) && ! self::canSeeBank($user, $r)) {
            $hidden = t('Payroll bank account (confidential)');

            return ['old' => $hidden, 'new' => $hidden];
        }

        return ['old' => (string) $r->old_value, 'new' => $r->newValueLabel()];
    }

    public function approve(EmpCorrectionRequest $req, SecUser $user): void
    {
        DB::connection('tm2mst')->transaction(function () use ($req, $user) {
            $req = $this->pending($req, $user);

            $column = self::FIELD_COLUMN_MAP[$req->field_name] ?? null;
            if ($column && $req->employee) {
                $req->employee->update([$column => $req->new_value, 'updated_by' => $user->login]);
            }
            if ($bank = $req->bankPayload()) {
                EmployeeBankService::setPrimary($req->employee_id, $bank['bank_supplier_id'], $bank['account_no'] ?? null, $bank['account_holder_name'] ?? null, $user->login);
            }
            $req->update(['status' => 'APPROVED', 'reviewed_by' => $user->login, 'reviewed_at' => now()]);
        });
    }

    public function reject(EmpCorrectionRequest $req, string $reason, SecUser $user): void
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255) {
            throw ValidationException::withMessages(['document' => 'Enter a rejection reason of at most 255 characters.']);
        }
        $req = DB::connection('tm2mst')->transaction(function () use ($req, $reason, $user) {
            $req = $this->pending($req, $user);
            $req->update(['status' => 'REJECTED', 'reviewed_by' => $user->login, 'review_note' => $reason, 'reviewed_at' => now()]);

            return $req;
        });

        if ($owner = SecUser::where('employee_id', $req->employee_id)->first()) {
            SysNotification::create(['user_id' => $owner->login, 'title' => 'Pengajuan koreksi data ditolak',
                'message' => "Koreksi \"{$req->field_name}\" ditolak HR: {$reason}. Silakan ajukan ulang dengan data yang benar.", 'type' => 'warning', 'is_read' => false]);
        }
    }

    /** Baris dikunci dan diperiksa PENDING di dalam transaksi; permintaan rekening butuh hak payroll atas pegawainya. */
    private function pending(EmpCorrectionRequest $req, SecUser $user): EmpCorrectionRequest
    {
        $fresh = EmpCorrectionRequest::with('employee')->whereKey($req->getKey())->lockForUpdate()->first();
        if (! $fresh || $fresh->status !== 'PENDING') {
            throw ValidationException::withMessages(['document' => 'This request is not waiting for a decision.']);
        }
        if (self::isBank($fresh) && ! self::canSeeBank($user, $fresh)) {
            throw ValidationException::withMessages(['document' => 'A payroll bank account request can only be decided by someone with the payroll right for this employee.']);
        }

        return $fresh;
    }

    /** Riwayat tab History: terbaru di atas. */
    public function history(EmpCorrectionRequest $req): array
    {
        $rows = [['at' => $req->requested_at?->toDateTimeString(), 'by' => $req->employee?->full_name, 'action' => 'Submitted', 'note' => (string) $req->reason]];
        if ($req->reviewed_at) {
            $rows[] = ['at' => $req->reviewed_at->toDateTimeString(), 'by' => $req->reviewed_by, 'action' => $req->status === 'REJECTED' ? 'Rejected' : 'Approved', 'note' => (string) $req->review_note];
        }

        return array_reverse($rows);
    }
}
