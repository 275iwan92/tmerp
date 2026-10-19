<?php

/*
 * REMARK
 * Tujuan       : Provision to ERP (layar v2 hrm-rcr-provision-form): lamaran dengan penawaran ACCEPTED menjadi pegawai baru = emp_employee + emp_contract (gaji dari penawaran) + salinan dokumen kandidat + login, lalu lamaran HIRED. Tidak bisa dibatalkan.
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::recruitment.hire-provision (diarsipkan di tmerp_unused/recruitment_hire_provision_v1_2026-10-19.blade.php): data pegawai dari kandidat (nama, KTP, lahir, telepon, email, alamat), join_date = start_date penawaran, kontrak CTR-{kode} PER_MONTH dengan employment_type dan proposed_salary penawaran, dokumen kandidat disalin, login lewat EmployeeLoginProvisionService, lamaran HIRED + provisioned_employee_id + provisioned_at; posisi (org_position) dipilih HR karena requisition hanya menyimpan judul teks. Baru (Jo 2026-10-19): semua langkah satu transaksi (tm2hrr, tm2mst, tm2sys) di bawah kunci baris lamaran, hanya user berhak payroll (PayrollAccess::canProcess) karena gaji ditulis ke kontrak, posisi harus aktif, ditolak bila NIK kandidat sudah dipakai pegawai WORKING, kode pegawai lewat EmployeeProfileService::nextCode (perusahaan aktif, sama dengan Master Employee). Perbaikan bug v1 yang tak terlihat di dummy (tanpa dokumen kandidat): uploaded_by_role v1 'RECRUITMENT_SYNC' tidak muat di kolom varchar(4) (SELF|HR) sehingga provisioning gagal bila kandidat punya dokumen; kini 'HR', dan jenis dokumen kandidat (CV, SERTIFIKAT, FOTO tidak ada di daftar pegawai) dipetakan: KTP dan IJAZAH tetap, lainnya LAINNYA.
 * Input        : lamaran (app_application), position_id, user yang bertindak.
 * Output       : tm2mst.emp_employee / emp_contract / emp_document, tm2sys.sec_user bertambah, tm2hrr.app_application jadi HIRED; kredensial login dikembalikan sekali; ValidationException atau AuthorizationException bila aturan dilanggar.
 * Dipakai oleh : routes/v2_hrm.php (v2.hrm.provision.save), layar hrm-rcr-provision-form, tes HireProvisionServiceTest.
 * Status       : Aktif (T7j HRM, 2026-10-19).
 */

namespace App\Services\Recruitment;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppOffer;
use App\Models\Hrr\CndDocument;
use App\Models\Master\EmpContract;
use App\Models\Master\EmpDocument;
use App\Models\Master\EmpEmployee;
use App\Models\Master\OrgCompany;
use App\Models\Master\OrgPosition;
use App\Models\Sys\SecUser;
use App\Services\Hrm\EmployeeProfileService;
use App\Services\Migration\EmployeeLoginProvisionService;
use App\Services\Payroll\PayrollAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class HireProvisionService
{
    /** Penawaran ACCEPTED terbaru lamaran ini, atau null. */
    public static function acceptedOffer(AppApplication $a): ?AppOffer
    {
        return $a->offers()->where('status', 'ACCEPTED')->first();
    }

    /** Lamaran siap diprovision: penawaran ACCEPTED dan belum jadi pegawai. */
    public static function ready(AppApplication $a): bool
    {
        return ! $a->provisioned_employee_id && self::acceptedOffer($a) !== null;
    }

    /** @return array<int, string> posisi aktif untuk pilihan; departemen requisition lebih dulu */
    public static function positions(AppApplication $a): array
    {
        $dept = $a->vacancy?->requisition?->department_id;
        $q = OrgPosition::where('activate', true);
        if ($dept) {
            $q->orderByRaw('department_id = ? DESC', [$dept]);
        }

        return $q->orderBy('position_name')->pluck('position_name', 'position_id')->all();
    }

    /** @return array{0: EmpEmployee, 1: ?array} pegawai baru dan kredensial login (tampil sekali) */
    public function provision(AppApplication $application, int $positionId, SecUser $user): array
    {
        if (! PayrollAccess::canProcess($user)) {
            throw new AuthorizationException('Only users with payroll rights can provision a new employee.');
        }
        if (! OrgPosition::where('position_id', $positionId)->where('activate', true)->exists()) {
            throw ValidationException::withMessages(['position_id' => 'Select the position of the new employee first.']);
        }

        return DB::connection('tm2hrr')->transaction(fn () => DB::connection('tm2mst')->transaction(fn () => DB::connection('tm2sys')->transaction(function () use ($application, $positionId, $user) {
            $app = AppApplication::with('candidate')->whereKey($application->getKey())->lockForUpdate()->firstOrFail();
            if ($app->provisioned_employee_id) {
                throw ValidationException::withMessages(['document' => 'This application was already provisioned.']);
            }
            $offer = self::acceptedOffer($app) ?? throw ValidationException::withMessages(['document' => 'This application has no accepted offer.']);
            $c = $app->candidate;
            if ($c->national_id && EmpEmployee::where('nik_ktp', $c->national_id)->where('work_status', 'WORKING')->exists()) {
                throw ValidationException::withMessages(['document' => 'An employee with this National ID already works here.']);
            }

            $company = OrgCompany::where('activate', true)->firstOrFail();
            $employee = EmpEmployee::create([
                'employee_code' => app(EmployeeProfileService::class)->nextCode($company), 'company_id' => $company->company_id, 'position_id' => $positionId,
                'full_name' => $c->name, 'birth_date' => $c->birth_date, 'nik_ktp' => $c->national_id, 'phone' => $c->phone, 'email_personal' => $c->email,
                'address_detail_ktp' => $c->address, 'join_date' => $offer->start_date, 'work_status' => 'WORKING', 'work_status_since' => $offer->start_date,
                'activate' => true, 'created_by' => $user->login,
            ]);
            EmpContract::create(['employee_id' => $employee->employee_id, 'contract_no' => 'CTR-'.$employee->employee_code, 'employment_type' => $offer->employment_type,
                'start_date' => $offer->start_date, 'salary_type' => 'PER_MONTH', 'salary_amount' => $offer->proposed_salary, 'activate' => true, 'created_by' => $user->login]);
            // Arsip rekrutmen tetap utuh: dokumen kandidat disalin referensinya, bukan dipindah.
            foreach (CndDocument::where('id_candidate', $c->id_candidate)->get() as $doc) {
                EmpDocument::create(['employee_id' => $employee->employee_id, 'document_type' => in_array($doc->doc_type, ['KTP', 'IJAZAH'], true) ? $doc->doc_type : 'LAINNYA', 'file_path' => $doc->file_path,
                    'uploaded_by_role' => 'HR', 'activate' => true, 'created_by' => $user->login]);
            }
            $cred = app(EmployeeLoginProvisionService::class)->provisionForEmployee($employee, $user->login);
            $app->update(['status' => 'HIRED', 'provisioned_employee_id' => $employee->employee_id, 'provisioned_at' => now()]);

            return [$employee, $cred];
        })));
    }

    /** Riwayat tab History: terbaru di atas. */
    public function history(AppApplication $a): array
    {
        $rows = [['at' => $a->applied_at?->toDateTimeString(), 'by' => '', 'action' => 'Applied', 'note' => (string) $a->vacancy?->title]];
        if ($a->provisioned_at) {
            $rows[] = ['at' => $a->provisioned_at->toDateTimeString(), 'by' => '', 'action' => 'Provisioned', 'note' => (string) EmpEmployee::whereKey($a->provisioned_employee_id)->value('employee_code')];
        }

        return array_reverse($rows);
    }
}
