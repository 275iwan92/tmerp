<?php

/*
 * REMARK
 * Tujuan       : Aturan dan simpan profil pegawai (identitas, pekerjaan, rekening gaji) dari layar v2 Master Employee Form; satu pintu, tanpa logika di layar.
 * Sumber hulu  : Aturan sama dengan layar v1 pages::master.employee (zona beku: validasi, kode pegawai TM-nnnnn, login otomatis pegawai baru, hak ubah Staff / Payroll tidak diubah).
 * Input        : Array isian form, file foto opsional, user yang menyimpan.
 * Output       : EmpEmployee tersimpan (+ kredensial login bila pegawai baru dan belum punya login); ValidationException bila isian salah.
 * Dipakai oleh : routes/v2.php (hrm.employee.save), tes EmployeeProfileServiceTest.
 * Status       : Aktif (dipakai bersama v1 sampai v1 dipensiunkan; tab anak Contract / Family / Education / Discipline masih di v1).
 */

namespace App\Services\Hrm;

use App\Models\Master\EmpEmployee;
use App\Models\Master\OrgCompany;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysConstant;
use App\Services\Migration\EmployeeLoginProvisionService;
use App\Services\Payroll\PayrollAccess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

final class EmployeeProfileService
{
    // Keluarga wajib (keputusan user 2026-08-27); dicek di tab Family.
    public const MANDATORY_FAMILY = ['AYAH' => 'Father', 'IBU' => 'Mother', 'MERTUA_AYAH' => 'Father-in-law', 'MERTUA_IBU' => 'Mother-in-law'];

    public const RELIGIONS = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Lainnya'];

    // HRM_EMPLOYEE_MANDATORY_STRICT (Settings > Constants) menyalakan isian identitas wajib; mati selama belum go-live.
    public static function strict(): bool
    {
        return SysConstant::number('HRM_EMPLOYEE_MANDATORY_STRICT', 0) >= 1;
    }

    public static function canEditStaffType(SecUser $user): bool
    {
        return PayrollAccess::canEditStaffType($user);
    }

    // HR Officer hanya memegang pegawai Non-staff; pegawai baru boleh diisi.
    public static function canEditPayrollMethod(SecUser $user, ?EmpEmployee $e): bool
    {
        return PayrollAccess::canProcess($user) && (! $e?->exists || PayrollAccess::canSeeEmployee($user, $e->employee_id));
    }

    /** @return array{0: EmpEmployee, 1: ?array} pegawai tersimpan dan kredensial login (hanya untuk pegawai baru) */
    public function save(?EmpEmployee $e, array $input, SecUser $user, ?UploadedFile $photo = null): array
    {
        $data = $this->validate($e, $input, $user, $photo);
        $payload = $this->payload($data) + ['updated_by' => $user->login];
        if (self::canEditStaffType($user) && isset($data['staff_type'])) {
            $payload['staff_type'] = $data['staff_type'];
        }
        if (self::canEditPayrollMethod($user, $e) && isset($data['payment_frequency'])) {
            $payload['payment_frequency'] = $data['payment_frequency'];
        }

        $credentials = null;
        if ($e?->exists) {
            $e->update($payload);
        } else {
            $company = OrgCompany::query()->where('activate', true)->firstOrFail();
            $e = EmpEmployee::create($payload + ['company_id' => $company->company_id, 'employee_code' => $this->nextCode($company), 'created_by' => $user->login,
                'staff_type' => $payload['staff_type'] ?? 'NON_STAFF', 'payment_frequency' => $payload['payment_frequency'] ?? 'MONTHLY']);
            // Seperti v1: mode strict menunda login sampai keluarga wajib lengkap; pegawai baru belum punya keluarga, jadi ditunda (tab Family tahap berikutnya).
            $credentials = self::strict() ? null : app(EmployeeLoginProvisionService::class)->provisionForEmployee($e, $user->login);
        }
        if ($e->wasRecentlyCreated === false && array_key_exists('bank_supplier_id', $input)) {
            EmployeeBankService::setPrimary($e->employee_id, $data['bank_supplier_id'] ? (int) $data['bank_supplier_id'] : null,
                $data['bank_account_no'] ?? null, $data['bank_account_holder'] ?? null, $user->login);
        }
        if ($photo) {
            $e->update(['photo_path' => $photo->store("hrm/{$e->employee_id}/profile", 'public')]);
        }

        return [$e, $credentials];
    }

    public function setActive(EmpEmployee $e, bool $active, SecUser $user): void
    {
        $e->update(['activate' => $active, 'updated_by' => $user->login]);
    }

    private function validate(?EmpEmployee $e, array $input, SecUser $user, ?UploadedFile $photo): array
    {
        $req = self::strict() ? 'required' : 'nullable';
        $reqNew = ! $e?->exists && self::strict() ? 'required' : 'nullable';
        $minBirth = now()->subYears(17)->toDateString();

        return Validator::make($input + ['photo' => $photo], [
            'full_name' => ['required', 'string', 'max:150'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'gender' => [$req, 'in:L,P'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => [$req, 'date', 'before_or_equal:'.$minBirth],
            'nik_ktp' => [$req, 'digits:16'],
            'no_kk' => [$req, 'string', 'max:20'],
            'npwp_number' => ['nullable', 'string', 'max:30'],
            'religion' => ['nullable', 'in:'.implode(',', self::RELIGIONS)],
            'phone' => [$req, 'string', 'max:30'],
            'email_personal' => [$reqNew, 'email', 'max:100'],
            'address_detail_ktp' => ['nullable', 'string', 'max:255'],
            'address_detail_domisili' => [$req, 'string', 'max:255'],
            'join_date' => [$req, 'date'],
            'work_status' => [$req, 'in:WORKING,DIRUMAHKAN,CUTI'],
            'work_status_since' => ['nullable', 'date'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'position_id' => [$req, 'integer', 'exists:tm2mst.org_position,position_id'],
            'default_machine_id' => ['nullable', 'integer', 'exists:tm2mst.prod_machine,machine_id'],
            'default_shift_id' => ['nullable', 'integer', 'exists:tm2mst.emp_shift,shift_id'],
            'staff_type' => [self::canEditStaffType($user) ? 'required' : 'nullable', 'in:STAFF,NON_STAFF'],
            'payment_frequency' => [self::canEditPayrollMethod($user, $e) ? 'required' : 'nullable', 'in:WEEKLY,MONTHLY'],
            'photo' => ['nullable', 'image', 'max:5120'],
            // Rekening gaji: kosong = dibayar tunai (Cash); bank terisi -> nomor dan nama wajib.
            'bank_supplier_id' => ['nullable', 'integer', 'exists:tm2mst.sup_supplier,id_supplier'],
            'bank_account_no' => ['required_with:bank_supplier_id', 'nullable', 'string', 'max:50'],
            'bank_account_holder' => ['required_with:bank_supplier_id', 'nullable', 'string', 'max:150'],
        ], [
            'nik_ktp.digits' => 'NIK KTP must be exactly 16 digits.',
            'birth_date.before_or_equal' => 'The employee must be at least 17 years old (birth date on or before '.$minBirth.').',
        ])->validate();
    }

    private function payload(array $data): array
    {
        $cols = ['full_name', 'nickname', 'gender', 'birth_place', 'birth_date', 'nik_ktp', 'no_kk', 'npwp_number', 'religion', 'phone', 'email_personal',
            'address_detail_ktp', 'address_detail_domisili', 'join_date', 'work_status', 'work_status_since', 'emergency_contact_name',
            'emergency_contact_relation', 'emergency_contact_phone', 'position_id', 'default_machine_id', 'default_shift_id'];
        $out = [];
        foreach ($cols as $c) {
            $out[$c] = ($data[$c] ?? null) ?: null;
        }
        $out['work_status'] ??= 'WORKING';

        return $out;
    }

    private function nextCode(OrgCompany $company): string
    {
        $max = EmpEmployee::where('company_id', $company->company_id)->where('employee_code', 'like', $company->company_code.'-%')->pluck('employee_code')
            ->map(fn ($c) => (int) substr($c, strlen($company->company_code) + 1))->max() ?? 0;

        return $company->company_code.'-'.str_pad((string) ($max + 1), 5, '0', STR_PAD_LEFT);
    }
}
