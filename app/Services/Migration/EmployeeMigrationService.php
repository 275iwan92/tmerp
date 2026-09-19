<?php

namespace App\Services\Migration;

use App\Models\Legacy\Emp;
use App\Models\Legacy\EmpIdentities;
use App\Models\Legacy\EmpPosition as LegacyEmpPosition;
use App\Models\Legacy\LegacyOrgDepartment;
use App\Models\Master\EmpBankAccount;
use App\Models\Master\EmpDocument;
use App\Models\Master\EmpEmployee;
use App\Models\Master\OrgCompany;
use App\Models\Master\OrgDepartment;
use App\Models\Master\OrgLevel;
use App\Models\Master\OrgPosition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Engine migrasi tm2old.emp* -> tm2mst (emp_employee + org_department/org_position
// turunan). Dipicu manual dari tab "Migrasi Data Legacy" (Setup, admin-only),
// sama pola dgn VendorMigrationService. Idempotent via legacy_employee_id /
// legacy_department_id / legacy_position_id.
//
// TEMUAN PENTING (jangan dianggap bug lain kali dibaca):
// - tm2old.emp_history (347 baris) adalah SUBSET PENUH tm2old.emp (semua
//   employee_id di emp_history juga ada persis sama di emp, is_active=0,
//   employee_code "OUT-xxx") -- ini snapshot arsip, BUKAN 347 orang lain.
//   SENGAJA TIDAK dibaca supaya tidak dobel.
// - current_department_id/current_position_id di tm2old.emp nyaris selalu
//   NULL (cuma 1 & 5 dari 485 baris terisi) -- tidak cukup buat memetakan
//   485 karyawan ke org_department/org_position yang benar. SEMUA karyawan
//   hasil migrasi ini dibucket ke satu department+position fallback
//   "Migrasi Legacy - Belum Dipetakan"; admin HR wajib assign ulang manual
//   satu-satu setelah data personal (nama/NIK) beres dulu.
// - gender kosong utk 340/485 baris, hire_date kosong utk 379/485 -- TIDAK
//   dikarang. emp_employee.gender & .join_date dilonggarkan jadi nullable
//   (migration 2026_08_21_000007) khusus supaya ini bisa jujur NULL.
// - emp_contract TIDAK dibuat otomatis -- legacy tak punya data tipe
//   kontrak (TETAP/KONTRAK/HARIAN/BORONGAN) yang bisa dipercaya, cuma
//   payroll_cycle WEEKLY/MONTHLY yang terlalu lemah buat dijadikan dasar.
//   Isi manual per employee sesuai data HR asli.
class EmployeeMigrationService
{
    // legacy tm2old.org_department.division_id -> target org_division_id.
    // 2 ("Digital", 1 departemen) -> DIGITAL (division_id=2), nama cocok kuat.
    // 1 (18 departemen produksi umum: Cetak/QC/Gudang/dst) -> OFFSET (division_id=1),
    // lini bisnis utama, best-effort.
    // 3 ("Harian Lepas", 1 departemen) -> SENGAJA TIDAK dipetakan (ambigu vs
    // INDOSTRY), dibiarkan NULL, masuk laporan.
    protected const DIVISION_MAP = [1 => 1, 2 => 2];

    protected const FALLBACK_DEPT_CODE = 'LEGACY-UNMAPPED';

    protected const FALLBACK_POSITION_CODE = 'LEGACY-STAFF';

    public function run(string $actor): array
    {
        // ~485 employee + turunannya (bank/dokumen) -- lihat catatan memory_limit
        // yang sama di VendorMigrationService.
        ini_set('memory_limit', '512M');
        set_time_limit(180);

        $report = [
            'departments' => ['created' => 0, 'updated' => 0],
            'positions' => ['created' => 0, 'updated' => 0],
            'employees' => ['created' => 0, 'updated' => 0, 'skipped' => []],
            'bankAccounts' => ['created' => 0],
            'documents' => ['created' => 0],
            'genderUnknown' => 0,
            'joinDateUnknown' => 0,
            'errors' => [],
        ];

        $company = OrgCompany::firstOrFail();
        $fallbackLevelId = OrgLevel::orderBy('level_rank')->value('level_id');

        $fallbackDept = $this->ensureFallbackDepartment($company->company_id, $actor);
        $fallbackPosition = $this->ensureFallbackPosition($fallbackDept->department_id, $fallbackLevelId, $actor);

        $this->migrateDepartments($report, $company->company_id, $actor);
        $positionMap = $this->migratePositions($report, $fallbackDept->department_id, $fallbackLevelId, $actor);
        $this->migrateEmployees($report, $actor, $company->company_id, $positionMap, $fallbackPosition->position_id);

        return $report;
    }

    protected function ensureFallbackDepartment(int $companyId, string $actor): OrgDepartment
    {
        return OrgDepartment::firstOrCreate(
            ['company_id' => $companyId, 'department_code' => self::FALLBACK_DEPT_CODE],
            [
                'division_id' => null,
                'department_name' => 'Migrasi Legacy - Belum Dipetakan',
                'activate' => true,
                'created_by' => $actor,
                'updated_by' => $actor,
            ]
        );
    }

    protected function ensureFallbackPosition(int $departmentId, int $levelId, string $actor): OrgPosition
    {
        return OrgPosition::firstOrCreate(
            ['department_id' => $departmentId, 'position_code' => self::FALLBACK_POSITION_CODE],
            [
                'level_id' => $levelId,
                'position_name' => 'Karyawan Legacy - Belum Dipetakan',
                'activate' => true,
                'created_by' => $actor,
                'updated_by' => $actor,
            ]
        );
    }

    // 20 departemen produksi riil dari tm2old.org_department -> org_department.
    // Sekadar katalog referensi (employee TIDAK otomatis ditaruh di sini, lihat
    // catatan class-level) -- berguna begitu admin mulai assign ulang manual.
    protected function migrateDepartments(array &$report, int $companyId, string $actor): void
    {
        foreach (LegacyOrgDepartment::all() as $d) {
            $name = trim((string) $d->department_name);
            if ($name === '') {
                continue;
            }

            $divisionId = self::DIVISION_MAP[(int) $d->division_id] ?? null;

            $existing = OrgDepartment::where('legacy_department_id', $d->department_id)->first();
            $code = $existing?->department_code ?? $this->uniqueCode(
                $name,
                20,
                fn ($code) => OrgDepartment::where('company_id', $companyId)->where('department_code', $code)->exists()
            );

            $data = [
                'legacy_department_id' => $d->department_id,
                'division_id' => $divisionId,
                'company_id' => $companyId,
                'department_code' => $code,
                'department_name' => $name,
                'activate' => true,
                'updated_by' => $actor,
            ];

            if ($existing) {
                $existing->update($data);
                $report['departments']['updated']++;
            } else {
                OrgDepartment::create($data + ['created_by' => $actor]);
                $report['departments']['created']++;
            }
        }
    }

    // 49 jabatan legacy -> org_position, DIBUCKET SEMUA ke $fallbackDepartmentId
    // (emp_position legacy tidak punya kolom departemen sama sekali -- tak ada
    // dasar buat menebak departemen aslinya). skill_rate legacy disimpan ke
    // legacy_skill_rate sekadar referensi (belum ada tabel rate payroll di V2).
    // Return: [legacy_position_id => target position_id].
    protected function migratePositions(array &$report, int $fallbackDepartmentId, int $fallbackLevelId, string $actor): array
    {
        $map = [];

        foreach (LegacyEmpPosition::all() as $p) {
            $name = trim((string) $p->position_name);
            if ($name === '') {
                continue;
            }

            $existing = OrgPosition::where('legacy_position_id', $p->position_id)->first();
            $code = $existing?->position_code ?? $this->uniqueCode(
                $name,
                20,
                fn ($code) => OrgPosition::where('department_id', $fallbackDepartmentId)->where('position_code', $code)->exists()
            );

            $data = [
                'legacy_position_id' => $p->position_id,
                'legacy_skill_rate' => $p->skill_rate,
                'department_id' => $fallbackDepartmentId,
                'level_id' => $fallbackLevelId,
                'position_code' => $code,
                'position_name' => $name,
                'activate' => true,
                'updated_by' => $actor,
            ];

            if ($existing) {
                $existing->update($data);
                $report['positions']['updated']++;
                $map[$p->position_id] = $existing->position_id;
            } else {
                $created = OrgPosition::create($data + ['created_by' => $actor]);
                $report['positions']['created']++;
                $map[$p->position_id] = $created->position_id;
            }
        }

        return $map;
    }

    protected function migrateEmployees(array &$report, string $actor, int $companyId, array $positionMap, int $fallbackPositionId): void
    {
        $company = OrgCompany::findOrFail($companyId);
        $nextSeq = EmpEmployee::where('employee_code', 'like', $company->company_code.'-%')
            ->get()
            ->map(fn ($e) => (int) substr($e->employee_code, strlen($company->company_code) + 1))
            ->max() ?? 0;

        $ktpByEmployee = EmpIdentities::where('identity_type', 'KTP')
            ->whereNotNull('identity_number')
            ->where('identity_number', '!=', '')
            ->get()
            ->keyBy('employee_id');

        Emp::query()->orderBy('employee_id')->chunk(100, function ($chunk) use (
            &$report, &$nextSeq, $actor, $companyId, $company, $positionMap, $fallbackPositionId, $ktpByEmployee
        ) {
            foreach ($chunk as $e) {
                try {
                    $name = trim((string) $e->full_name);
                    if ($name === '') {
                        $report['employees']['skipped'][] = "employee_id={$e->employee_id}: nama kosong";

                        continue;
                    }

                    $positionId = $e->current_position_id && isset($positionMap[$e->current_position_id])
                        ? $positionMap[$e->current_position_id]
                        : $fallbackPositionId;

                    $gender = match (true) {
                        $e->gender === null => null,
                        (int) $e->gender === 1 => 'L',
                        (int) $e->gender === 0 => 'P',
                        default => null,
                    };
                    if ($gender === null) {
                        $report['genderUnknown']++;
                    }
                    if (! $e->hire_date) {
                        $report['joinDateUnknown']++;
                    }

                    $ktp = $ktpByEmployee->get($e->employee_id);

                    // Sudah di-merge ke karyawan lain (emp_employee_merge) -- jangan dihidupkan lagi.
                    if (DB::connection('tm2mst')->table('emp_employee_merge')->where('merged_legacy_employee_id', $e->employee_id)->exists()) {
                        continue;
                    }

                    $existing = EmpEmployee::where('legacy_employee_id', $e->employee_id)->first();

                    $data = [
                        'legacy_employee_id' => $e->employee_id,
                        'company_id' => $companyId,
                        'position_id' => $positionId,
                        'full_name' => $name,
                        'gender' => $gender,
                        'birth_date' => $e->birth_date ?: null,
                        'nik_ktp' => $ktp?->identity_number,
                        'religion' => $this->blank($e->religion),
                        'marital_status' => $this->blank($e->marital_status),
                        'phone' => $this->blank($e->phone_number),
                        'email_personal' => $this->blank($e->email),
                        'join_date' => $e->hire_date ?: null,
                        'resign_date' => $e->resign_date ?: null,
                        'activate' => ((int) $e->is_active) === 1,
                        'payment_frequency' => in_array($e->payroll_cycle, ['WEEKLY', 'MONTHLY'], true) ? $e->payroll_cycle : 'MONTHLY',
                        'updated_by' => $actor,
                    ];

                    if ($existing) {
                        $existing->update($data);
                        $employee = $existing;
                        $report['employees']['updated']++;
                    } else {
                        $nextSeq++;
                        $data['employee_code'] = $company->company_code.'-'.str_pad((string) $nextSeq, 5, '0', STR_PAD_LEFT);
                        $employee = EmpEmployee::create($data + ['created_by' => $actor]);
                        $report['employees']['created']++;
                    }

                    $this->migrateBankAccount($report, $employee->employee_id, $e, $actor);
                    $this->migrateKtpDocument($report, $employee->employee_id, $ktp, $actor);
                } catch (\Throwable $ex) {
                    $report['errors'][] = "employee_id={$e->employee_id} ({$e->full_name}): {$ex->getMessage()}";
                }
            }
        });
    }

    // "0" / "TIDAK ADA" adalah sentinel "tidak punya rekening" di data legacy
    // (dilihat langsung di baris arsip) -- BUKAN data rekening sungguhan,
    // sengaja dilewati supaya tidak masuk sebagai baris sampah.
    protected function migrateBankAccount(array &$report, int $employeeId, Emp $e, string $actor): void
    {
        $accountNo = trim((string) $e->bank_account_number);
        $bankName = trim((string) $e->bank_name);

        if ($accountNo === '' || $accountNo === '0' || $bankName === '' || mb_strtoupper($bankName) === 'TIDAK ADA') {
            return;
        }

        $created = EmpBankAccount::firstOrCreate(
            ['employee_id' => $employeeId, 'account_no' => $accountNo],
            [
                'bank_name' => $bankName,
                'account_holder_name' => trim((string) $e->full_name),
                'is_primary' => true,
                'activate' => true,
                'created_by' => $actor,
                'updated_by' => $actor,
            ]
        );

        if ($created->wasRecentlyCreated) {
            $report['bankAccounts']['created']++;
        }
    }

    protected function migrateKtpDocument(array &$report, int $employeeId, ?EmpIdentities $ktp, string $actor): void
    {
        if (! $ktp || trim((string) $ktp->identity_number) === '') {
            return;
        }

        $created = EmpDocument::firstOrCreate(
            ['employee_id' => $employeeId, 'document_type' => 'KTP', 'document_no' => trim($ktp->identity_number)],
            [
                'file_path' => $this->blank($ktp->file_url) ?? $this->blank($ktp->identity_url),
                'expiry_date' => $ktp->expiry_date ?: null,
                'uploaded_by_role' => 'HR',
                'activate' => true,
                'created_by' => $actor,
                'updated_by' => $actor,
            ]
        );

        if ($created->wasRecentlyCreated) {
            $report['documents']['created']++;
        }
    }

    // Slug ASCII-upper dari nama, dipotong $maxLen, tabrakan ditambah -2/-3/dst
    // -- sama pola dgn regenerateCode() di master/raw-material/entry.blade.php.
    protected function uniqueCode(string $name, int $maxLen, \Closure $exists): string
    {
        $base = Str::of($name)->ascii()->upper()->replaceMatches('/[^A-Z0-9]+/', '-')->trim('-')->substr(0, $maxLen - 3)->toString();
        $base = $base !== '' ? $base : 'CODE';

        $code = $base;
        $suffix = 1;
        while ($exists($code)) {
            $suffix++;
            $code = $base.'-'.$suffix;
        }

        return mb_substr($code, 0, $maxLen);
    }

    protected function blank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
