<?php

namespace Database\Seeders;

use App\Models\Master\EmpContract;
use App\Models\Master\EmpDisciplinary;
use App\Models\Master\EmpEducation;
use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpFamily;
use App\Models\Master\EmpShift;
use App\Models\Master\EmpShiftDetail;
use App\Models\Master\OrgCompany;
use App\Models\Master\OrgDepartment;
use App\Models\Master\OrgDivision;
use App\Models\Master\OrgLevel;
use App\Models\Master\OrgPosition;
use App\Models\Master\ProdMachine;
use App\Models\Sys\SecUser;
use Illuminate\Database\Seeder;

// TIER_3A_HRM.sql — struktur organisasi (1 CV, 3 SBU) + data demo Master
// Mesin/Shift/Employee, cukup untuk menguji layar tanpa data kosong.
// mockup_master_produksi.html & mockup_master_employee.html jadi acuan nilai.
class HrmMasterSeeder extends Seeder
{
    public function run(): void
    {
        $company = $this->seedCompany();
        $divisions = $this->seedDivisions($company);
        $departments = $this->seedDepartments($company, $divisions);
        $levels = $this->seedLevels();
        $positions = $this->seedPositions($departments, $levels);
        $this->seedMachines($divisions);
        $this->seedShifts($departments);
        $this->seedEmployees($company, $positions);
        $this->linkAdminToEmployee();
    }

    // TIER_1_HRM.md §10: 1 user WAJIB tertaut 1 employee_id sebelum bisa pakai
    // portal self-service ("Jadwal Saya"/dst). SecUserSeeder kebetulan sudah
    // menamai user 'admin' sebagai "Budi Santoso" — tautkan ke TM-00001 supaya
    // ada akun siap pakai untuk uji coba tanpa perlu bikin user baru.
    private function linkAdminToEmployee(): void
    {
        $admin = SecUser::query()->where('login', 'admin')->first();
        $employee = EmpEmployee::query()->where('employee_code', 'TM-00001')->first();

        if ($admin && $employee && ! $admin->employee_id) {
            $admin->employee_id = $employee->employee_id;
            $admin->save();
        }
    }

    private function seedCompany(): OrgCompany
    {
        return OrgCompany::query()->updateOrCreate(
            ['company_code' => 'TM'],
            ['company_name' => 'CV Telasih Makmur Group', 'is_active' => true]
        );
    }

    /** @return array<string, OrgDivision> */
    private function seedDivisions(OrgCompany $company): array
    {
        $rows = [
            'OFFSET' => 'Offset Printing',
            'DIGITAL' => 'Digital Printing',
            'INDOSTRY' => 'Indostry',
        ];

        $divisions = [];
        foreach ($rows as $code => $name) {
            $divisions[$code] = OrgDivision::query()->updateOrCreate(
                ['company_id' => $company->company_id, 'division_code' => $code],
                ['division_name' => $name, 'is_active' => true]
            );
        }

        return $divisions;
    }

    /** @return array<string, OrgDepartment> */
    private function seedDepartments(OrgCompany $company, array $divisions): array
    {
        $rows = [
            'PRESS' => ['name' => 'Produksi - Cetak', 'division' => 'OFFSET'],
            'PREPRESS' => ['name' => 'Prepress & CTP', 'division' => 'OFFSET'],
            'QC' => ['name' => 'Quality Control', 'division' => 'OFFSET'],
            'DIGITALPROD' => ['name' => 'Produksi - Digital Printing', 'division' => 'DIGITAL'],
            'INDOSTRYPROD' => ['name' => 'Produksi - Indostry', 'division' => 'INDOSTRY'],
            'SECURITY' => ['name' => 'Security', 'division' => null],
            'ADMIN' => ['name' => 'Staff/Admin', 'division' => null],
        ];

        $departments = [];
        foreach ($rows as $code => $row) {
            $departments[$code] = OrgDepartment::query()->updateOrCreate(
                ['company_id' => $company->company_id, 'department_code' => $code],
                [
                    'division_id' => $row['division'] ? $divisions[$row['division']]->division_id : null,
                    'department_name' => $row['name'],
                    'is_active' => true,
                ]
            );
        }

        return $departments;
    }

    /** @return array<string, OrgLevel> */
    private function seedLevels(): array
    {
        $rows = [
            'STAFF' => ['name' => 'Staff / Operator', 'rank' => 1],
            'SPV' => ['name' => 'Supervisor', 'rank' => 2],
            'MGR' => ['name' => 'Manager', 'rank' => 3],
            'DIR' => ['name' => 'Direksi', 'rank' => 4],
        ];

        $levels = [];
        foreach ($rows as $code => $row) {
            $levels[$code] = OrgLevel::query()->updateOrCreate(
                ['level_code' => $code],
                ['level_name' => $row['name'], 'level_rank' => $row['rank'], 'is_active' => true]
            );
        }

        return $levels;
    }

    /** @return array<string, OrgPosition> */
    private function seedPositions(array $departments, array $levels): array
    {
        $rows = [
            'OP-HEIDELBERG' => ['name' => 'Senior Heidelberg Operator', 'dept' => 'PRESS', 'level' => 'STAFF'],
            'PREPRESS-SPEC' => ['name' => 'Prepress Planning Specialist', 'dept' => 'PREPRESS', 'level' => 'STAFF'],
            'QC-INSPECTOR' => ['name' => 'Quality Control Inspector', 'dept' => 'QC', 'level' => 'STAFF'],
            'OP-DIGITAL' => ['name' => 'Operator Digital Printing', 'dept' => 'DIGITALPROD', 'level' => 'STAFF'],
            'OP-INDOSTRY' => ['name' => 'Operator Indostry', 'dept' => 'INDOSTRYPROD', 'level' => 'STAFF'],
        ];

        $positions = [];
        foreach ($rows as $code => $row) {
            $department = $departments[$row['dept']];
            $positions[$code] = OrgPosition::query()->updateOrCreate(
                ['department_id' => $department->department_id, 'position_code' => $code],
                [
                    'level_id' => $levels[$row['level']]->level_id,
                    'position_name' => $row['name'],
                    'is_active' => true,
                ]
            );
        }

        return $positions;
    }

    private function seedMachines(array $divisions): void
    {
        $offset = $divisions['OFFSET']->division_id;

        $rows = [
            ['code' => 'CETAK-01', 'name' => 'Cetak 1', 'type' => 'Cetak', 'brand' => 'Heidelberg SM 74', 'year' => 2015, 'serial' => 'SM74-2015-0231'],
            ['code' => 'CETAK-02', 'name' => 'Cetak 2', 'type' => 'Cetak', 'brand' => 'Komori L528', 'year' => 2018, 'serial' => null],
            ['code' => 'COAT-01', 'name' => 'Coating', 'type' => 'Coating', 'brand' => 'Tec Lighter TL-104', 'year' => null, 'serial' => null],
            ['code' => 'LAM-01', 'name' => 'Laminasi', 'type' => 'Laminasi', 'brand' => 'GMP Saturn 540', 'year' => null, 'serial' => null],
        ];

        foreach ($rows as $r) {
            ProdMachine::query()->updateOrCreate(
                ['division_id' => $offset, 'machine_code' => $r['code']],
                [
                    'machine_name' => $r['name'],
                    'machine_type' => $r['type'],
                    'brand_model' => $r['brand'],
                    'year_acquired' => $r['year'],
                    'serial_no' => $r['serial'],
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedShifts(array $departments): void
    {
        $shift1 = EmpShift::query()->updateOrCreate(
            ['department_id' => $departments['PRESS']->department_id, 'shift_name' => 'TM Production Shift 1'],
            ['is_active' => true]
        );
        $this->seedDailyHours($shift1, '05:00', '09:30', '10:00', '13:15', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']);

        $shift2 = EmpShift::query()->updateOrCreate(
            ['department_id' => $departments['PRESS']->department_id, 'shift_name' => 'TM Production Shift 2'],
            ['is_active' => true]
        );
        $this->seedDailyHours($shift2, '13:15', null, null, '21:15', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']);

        $shift3 = EmpShift::query()->updateOrCreate(
            ['department_id' => $departments['SECURITY']->department_id, 'shift_name' => 'Security Shift 3'],
            ['is_active' => true]
        );
        $this->seedDailyHours($shift3, '11:30', null, null, '21:30', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);

        EmpShift::query()->updateOrCreate(
            ['department_id' => $departments['DIGITALPROD']->department_id, 'shift_name' => 'TDP Production Long Shift 1'],
            ['is_active' => true]
        );
        EmpShift::query()->updateOrCreate(
            ['department_id' => $departments['DIGITALPROD']->department_id, 'shift_name' => 'TDP Production Shift 2'],
            ['is_active' => true]
        );

        EmpShift::query()->updateOrCreate(
            ['department_id' => $departments['INDOSTRYPROD']->department_id, 'shift_name' => 'Indostry Shift 1'],
            ['is_active' => true]
        );
        EmpShift::query()->updateOrCreate(
            ['department_id' => $departments['INDOSTRYPROD']->department_id, 'shift_name' => 'Indostry Shift 2'],
            ['is_active' => true]
        );
    }

    private function seedDailyHours(EmpShift $shift, ?string $start, ?string $breakOut, ?string $breakIn, ?string $end, array $days): void
    {
        foreach ($days as $day) {
            EmpShiftDetail::query()->updateOrCreate(
                ['shift_id' => $shift->shift_id, 'day_of_week' => $day],
                ['start_time' => $start, 'break_out' => $breakOut, 'break_in' => $breakIn, 'end_time' => $end]
            );
        }
    }

    private function seedEmployees(OrgCompany $company, array $positions): void
    {
        $rows = [
            [
                'code' => 'TM-00001',
                'position' => 'OP-HEIDELBERG',
                'name' => 'Budi Santoso',
                'gender' => 'L',
                'birth_place' => 'Surabaya',
                'birth_date' => '1988-03-12',
                'nik_ktp' => '3275081203880004',
                'npwp' => '71.234.567.8-012.000',
                'phone' => '+62 812-3456-7890',
                'join_date' => '2022-03-01',
                'contract' => ['type' => 'BORONGAN', 'salary_type' => 'PER_UNIT', 'salary' => 0, 'start' => '2022-03-01'],
                'education' => ['type' => 'PENDIDIKAN_FORMAL', 'name' => 'D3 Teknik Grafika - Politeknik Negeri Media Kreatif', 'date' => '2010-08-01', 'cost' => 0, 'funded_by' => 'PRIBADI'],
                'family' => ['relation' => 'ISTRI', 'name' => 'Siti Aminah', 'birth_date' => '1990-04-15', 'nik' => '3275084504900002'],
                'disciplinary' => [
                    ['type' => 'TEGURAN_VERBAL', 'date' => '2026-03-12', 'reason' => 'Terlambat shift 15 menit', 'by' => 'Hendra Kusuma'],
                    ['type' => 'TEGURAN_VERBAL', 'date' => '2026-05-18', 'reason' => 'Abaikan APD Press Room', 'by' => 'Hendra Kusuma'],
                ],
            ],
            [
                'code' => 'TM-00002',
                'position' => 'PREPRESS-SPEC',
                'name' => 'Dewi Rahmawati',
                'gender' => 'P',
                'birth_place' => 'Jakarta',
                'birth_date' => '1991-05-22',
                'nik_ktp' => '3174095205910002',
                'npwp' => '82.456.789.1-015.000',
                'phone' => null,
                'join_date' => '2021-06-01',
                'contract' => ['type' => 'TETAP', 'salary_type' => 'PER_MONTH', 'salary' => 6500000, 'start' => '2021-06-01'],
                'education' => null,
                'family' => null,
                'disciplinary' => array_map(
                    fn ($i) => ['type' => 'TEGURAN_VERBAL', 'date' => '2026-0'.min($i, 9).'-10', 'reason' => 'Keterlambatan berulang', 'by' => 'HR'],
                    range(1, 6)
                ),
            ],
            [
                'code' => 'TM-00003',
                'position' => 'QC-INSPECTOR',
                'name' => 'Agus Setiawan',
                'gender' => 'L',
                'birth_place' => 'Bekasi',
                'birth_date' => '1992-07-24',
                'nik_ktp' => '3578012407920001',
                'npwp' => '91.112.334.5-099.000',
                'phone' => null,
                'join_date' => '2023-01-10',
                'contract' => ['type' => 'TETAP', 'salary_type' => 'PER_MONTH', 'salary' => 5800000, 'start' => '2023-01-10'],
                'education' => null,
                'family' => null,
                'disciplinary' => [],
            ],
            [
                'code' => 'TM-00004',
                'position' => 'OP-DIGITAL',
                'name' => 'Rudi Hartono',
                'gender' => 'L',
                'birth_place' => 'Bandung',
                'birth_date' => '1994-02-18',
                'nik_ktp' => '3273011802940003',
                'npwp' => null,
                'phone' => null,
                'join_date' => '2024-02-01',
                'contract' => ['type' => 'KONTRAK', 'salary_type' => 'PER_MONTH', 'salary' => 4500000, 'start' => '2024-02-01'],
                'education' => null,
                'family' => null,
                'disciplinary' => [],
            ],
            [
                'code' => 'TM-00005',
                'position' => 'OP-INDOSTRY',
                'name' => 'Wawan Setiadi',
                'gender' => 'L',
                'birth_place' => 'Malang',
                'birth_date' => '1990-11-05',
                'nik_ktp' => '3573010511900004',
                'npwp' => null,
                'phone' => null,
                'join_date' => '2023-09-01',
                'contract' => ['type' => 'HARIAN', 'salary_type' => 'PER_DAY', 'salary' => 150000, 'start' => '2023-09-01'],
                'education' => null,
                'family' => null,
                'disciplinary' => [],
            ],
        ];

        foreach ($rows as $r) {
            $employee = EmpEmployee::query()->updateOrCreate(
                ['employee_code' => $r['code']],
                [
                    'company_id' => $company->company_id,
                    'position_id' => $positions[$r['position']]->position_id,
                    'full_name' => $r['name'],
                    'gender' => $r['gender'],
                    'birth_place' => $r['birth_place'],
                    'birth_date' => $r['birth_date'],
                    'nik_ktp' => $r['nik_ktp'],
                    'npwp_number' => $r['npwp'],
                    'phone' => $r['phone'],
                    'join_date' => $r['join_date'],
                    'work_status' => 'WORKING',
                    'is_active' => true,
                ]
            );

            EmpContract::query()->updateOrCreate(
                ['employee_id' => $employee->employee_id, 'start_date' => $r['contract']['start']],
                [
                    'employment_type' => $r['contract']['type'],
                    'salary_type' => $r['contract']['salary_type'],
                    'salary_amount' => $r['contract']['salary'],
                    'is_active' => true,
                ]
            );

            if ($r['education']) {
                EmpEducation::query()->updateOrCreate(
                    ['employee_id' => $employee->employee_id, 'activity_name' => $r['education']['name']],
                    [
                        'education_type' => $r['education']['type'],
                        'activity_date' => $r['education']['date'],
                        'cost' => $r['education']['cost'],
                        'funded_by' => $r['education']['funded_by'],
                        'uploaded_by_role' => 'HR',
                    ]
                );
            }

            if ($r['family']) {
                EmpFamily::query()->updateOrCreate(
                    ['employee_id' => $employee->employee_id, 'full_name' => $r['family']['name']],
                    [
                        'relation' => $r['family']['relation'],
                        'birth_date' => $r['family']['birth_date'],
                        'nik' => $r['family']['nik'],
                        'is_active' => true,
                    ]
                );
            }

            foreach ($r['disciplinary'] as $d) {
                EmpDisciplinary::query()->updateOrCreate(
                    ['employee_id' => $employee->employee_id, 'incident_date' => $d['date'], 'type' => $d['type']],
                    ['reason' => $d['reason'], 'issued_by' => $d['by']]
                );
            }
        }
    }
}
