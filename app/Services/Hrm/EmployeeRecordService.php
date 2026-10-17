<?php

/*
 * REMARK
 * Tujuan       : Catatan anak pegawai dari layar v2 Master Employee Form: kontrak baru, keluarga (tambah, tandai meninggal), pendidikan, kedisiplinan, dokumen. Tiap tambah tersimpan sendiri.
 * Sumber hulu  : Aturan sama dengan dialog v1 pages::master.employee (zona beku): satu kontrak aktif per pegawai, anak maks 20, keluarga wajib sekali, cek silang keluarga-pekerjaan.
 * Input        : Pegawai (EmpEmployee), array isian dialog, user yang menyimpan.
 * Output       : Baris tersimpan di tm2mst.emp_contract / emp_family / emp_education / emp_disciplinary; ValidationException bila isian salah; kredensial login bila keluarga wajib lengkap (mode strict).
 * Dipakai oleh : routes/v2.php (hrm.employee.contract / family / deceased / education / discipline), tes EmployeeRecordServiceTest.
 * Status       : Aktif. Tanpa edit / hapus baris (sama dengan v1: kontrak dan catatan disiplin adalah riwayat).
 */

namespace App\Services\Hrm;

use App\Models\Master\EmpContract;
use App\Models\Master\EmpDisciplinary;
use App\Models\Master\EmpDocument;
use App\Models\Master\EmpEducation;
use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpFamily;
use App\Models\Sys\SecUser;
use App\Services\Migration\EmployeeLoginProvisionService;
use Devlib\Locale;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class EmployeeRecordService
{
    public const EMPLOYMENT_TYPES = ['TETAP', 'KONTRAK', 'HARIAN', 'BORONGAN'];

    public const SALARY_TYPES = ['PER_MONTH' => 'Per month', 'PER_DAY' => 'Per day', 'PER_UNIT' => 'Per unit'];

    public const EDUCATION_TYPES = ['SERTIFIKASI' => 'Certification', 'SEMINAR' => 'Seminar', 'PELATIHAN' => 'Training', 'PENDIDIKAN_FORMAL' => 'Formal education'];

    public const FUNDED_BY = ['PERUSAHAAN' => 'Company', 'PRIBADI' => 'Personal', 'SHARING' => 'Shared'];

    public const FAMILY_RELATIONS = ['SUAMI' => 'Husband', 'ISTRI' => 'Wife', 'ANAK' => 'Child', 'AYAH' => 'Father', 'IBU' => 'Mother', 'MERTUA_AYAH' => 'Father-in-law', 'MERTUA_IBU' => 'Mother-in-law'];

    public const DISCIPLINARY_TYPES = ['TEGURAN_VERBAL' => 'Verbal warning', 'SP1' => 'SP1', 'SP2' => 'SP2', 'SP3' => 'SP3', 'ALPHA' => 'Alpha'];

    public const DOCUMENT_TYPES = ['KTP' => 'ID card (e-KTP)', 'NPWP' => 'Tax ID (NPWP)', 'SIM' => 'Driving licence (SIM C)', 'KK' => 'Family card (KK)', 'PASSPORT' => 'Passport',
        'SKCK' => 'Police clearance (SKCK)', 'IJAZAH' => 'Diploma', 'KONTRAK' => 'Contract', 'LAINNYA' => 'Other'];

    private const MAX_CHILDREN = 20;

    // Satu kontrak aktif per pegawai (TIER_3A_HRM.sql §2.2): kontrak aktif lama dinonaktifkan, yang baru aktif.
    public function addContract(EmpEmployee $e, array $in, SecUser $user): EmpContract
    {
        $in['salary_amount'] = Locale::input($in['salary_amount'] ?? null) ?? ($in['salary_amount'] ?? null);
        $in['is_probation'] = $this->flag($in, 'is_probation');
        $d = Validator::make($in, [
            'employment_type' => ['required', 'in:'.implode(',', self::EMPLOYMENT_TYPES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'salary_type' => ['required', 'in:'.implode(',', array_keys(self::SALARY_TYPES))],
            'salary_amount' => ['required', 'numeric', 'min:0'],
            'is_probation' => ['boolean'],
            'probation_end_date' => ['nullable', 'date', 'required_if:is_probation,true'],
        ])->validate();

        return DB::connection('tm2mst')->transaction(function () use ($e, $d, $user) {
            EmpContract::where('employee_id', $e->employee_id)->where('activate', true)->update(['activate' => false]);

            return EmpContract::create([
                'employee_id' => $e->employee_id, 'employment_type' => $d['employment_type'], 'start_date' => $d['start_date'], 'end_date' => ($d['end_date'] ?? null) ?: null,
                'salary_type' => $d['salary_type'], 'salary_amount' => $d['salary_amount'], 'is_probation' => $d['is_probation'],
                'probation_end_date' => ($d['probation_end_date'] ?? null) ?: null, 'activate' => true, 'created_by' => $user->login,
            ]);
        });
    }

    /** @return ?array kredensial login bila keluarga wajib baru lengkap (hanya mode strict) */
    public function addFamily(EmpEmployee $e, array $in, SecUser $user): ?array
    {
        $d = Validator::make($in, [
            'relation' => ['required', 'in:'.implode(',', array_keys(self::FAMILY_RELATIONS))],
            'full_name' => ['required', 'string', 'max:150'],
            'birth_date' => ['nullable', 'date'],
            'nik' => ['nullable', 'string', 'max:20'],
        ])->validate();

        $same = EmpFamily::where('employee_id', $e->employee_id)->where('relation', $d['relation']);
        if ($d['relation'] === 'ANAK' && $same->count() >= self::MAX_CHILDREN) {
            throw ValidationException::withMessages(['relation' => 'Maximum '.self::MAX_CHILDREN.' children per employee.']);
        }
        if (isset(EmployeeProfileService::MANDATORY_FAMILY[$d['relation']]) && $same->exists()) {
            throw ValidationException::withMessages(['relation' => EmployeeProfileService::MANDATORY_FAMILY[$d['relation']].' is already recorded.']);
        }

        $family = EmpFamily::create(['employee_id' => $e->employee_id, 'relation' => $d['relation'], 'full_name' => $d['full_name'],
            'birth_date' => ($d['birth_date'] ?? null) ?: null, 'nik' => ($d['nik'] ?? null) ?: null, 'activate' => true, 'created_by' => $user->login, 'updated_by' => $user->login]);
        app(FamilyEmploymentCrossCheckService::class)->checkFamilyMember($family);

        return $this->provisionWhenFamilyComplete($e, $user);
    }

    // Meninggal ditandai, bukan dihapus (Tier 1 §5).
    public function markDeceased(EmpFamily $f, SecUser $user): void
    {
        $f->update(['is_deceased' => true, 'deceased_date' => now()->toDateString(), 'updated_by' => $user->login]);
    }

    public function addEducation(EmpEmployee $e, array $in, SecUser $user): EmpEducation
    {
        $in['cost'] = Locale::input($in['cost'] ?? null) ?? ($in['cost'] ?? null);
        $in['is_bonding'] = $this->flag($in, 'is_bonding');
        $d = Validator::make($in, [
            'education_type' => ['required', 'in:'.implode(',', array_keys(self::EDUCATION_TYPES))],
            'activity_name' => ['required', 'string', 'max:200'],
            'organizer' => ['nullable', 'string', 'max:150'],
            'activity_date' => ['required', 'date'],
            'cost' => ['required', 'numeric', 'min:0'],
            'funded_by' => ['required', 'in:'.implode(',', array_keys(self::FUNDED_BY))],
            'is_bonding' => ['boolean'],
        ])->validate();

        return EmpEducation::create(['employee_id' => $e->employee_id, 'education_type' => $d['education_type'], 'activity_name' => $d['activity_name'],
            'organizer' => ($d['organizer'] ?? null) ?: null, 'activity_date' => $d['activity_date'], 'cost' => $d['cost'], 'funded_by' => $d['funded_by'],
            'is_bonding' => $d['is_bonding'], 'uploaded_by_role' => 'HR']);
    }

    public function addDiscipline(EmpEmployee $e, array $in, SecUser $user): EmpDisciplinary
    {
        $d = Validator::make($in, [
            'type' => ['required', 'in:'.implode(',', array_keys(self::DISCIPLINARY_TYPES))],
            'incident_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
        ])->validate();

        return EmpDisciplinary::create(['employee_id' => $e->employee_id, 'type' => $d['type'], 'incident_date' => $d['incident_date'], 'reason' => $d['reason'], 'issued_by' => $user->login]);
    }

    // Satu dokumen per jenis per pegawai (sama dengan self-service): unggah ulang menggantikan berkas lama. Unggahan HR mengunci dokumen dari self-service.
    public function addDocument(EmpEmployee $e, array $in, ?UploadedFile $file, SecUser $user): EmpDocument
    {
        $d = Validator::make($in + ['file' => $file], [
            'document_type' => ['required', 'in:'.implode(',', array_keys(self::DOCUMENT_TYPES))],
            'document_no' => ['nullable', 'string', 'max:50'],
            'expiry_date' => ['nullable', 'date'],
            'file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ])->validate();

        $doc = EmpDocument::firstOrNew(['employee_id' => $e->employee_id, 'document_type' => $d['document_type']]);
        if ($doc->file_path) {
            Storage::disk('public')->delete($doc->file_path);
        }
        $doc->fill(['document_no' => ($d['document_no'] ?? null) ?: null, 'expiry_date' => ($d['expiry_date'] ?? null) ?: null, 'uploaded_by_role' => 'HR', 'activate' => true,
            'file_path' => $file->store("hrm/{$e->employee_id}/documents", 'public'), 'updated_by' => $user->login]);
        $doc->created_by ??= $user->login;
        $doc->save();

        return $doc;
    }

    // Mode strict menunda login sampai keluarga wajib lengkap (sama dengan v1); provisioning idempotent.
    private function provisionWhenFamilyComplete(EmpEmployee $e, SecUser $user): ?array
    {
        if (! EmployeeProfileService::strict()) {
            return null;
        }
        $have = EmpFamily::where('employee_id', $e->employee_id)->pluck('relation')->all();
        if (array_diff(array_keys(EmployeeProfileService::MANDATORY_FAMILY), $have)) {
            return null;
        }

        return app(EmployeeLoginProvisionService::class)->provisionForEmployee($e, $user->login);
    }

    private function flag(array $in, string $key): bool
    {
        return filter_var($in[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
