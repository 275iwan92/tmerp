<?php

namespace Tests\Feature;

use App\Models\Master\EmpBankAccount;
use App\Models\Master\EmpCorrectionRequest;
use App\Models\Master\EmpEmployee;
use App\Models\Sys\SecUser;
use App\Services\Hrm\CorrectionRequestService;
use App\Services\Hrm\EmployeeProfileService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Keputusan user 2026-09-23: bank = Master Supplier kategori BANK; pegawai cuma
// simpan No. Rekening + Atas Nama; input di Master Employee, Payroll, dan
// Self-Service (ajukan ganti -> Approve HR langsung menerapkan). Mode longgar
// Master Employee: hanya Nama wajib. Semua pakai pegawai uji ZZTEST-* (data
// asli tidak disentuh) dan dibersihkan di tearDown.
class EmployeeBankSupplierTest extends TestCase
{
    private array $employeeIds = [];

    protected function tearDown(): void
    {
        $db = DB::connection('tm2mst');
        $db->table('emp_correction_request')->whereIn('employee_id', $this->employeeIds)->delete();
        $db->table('emp_bank_account')->whereIn('employee_id', $this->employeeIds)->delete();
        DB::connection('tm2sys')->table('sec_user')->whereIn('employee_id', $this->employeeIds)->delete();
        $db->table('emp_employee')->whereIn('employee_id', $this->employeeIds)->delete();

        parent::tearDown();
    }

    private function makeEmployee(): int
    {
        $id = DB::connection('tm2mst')->table('emp_employee')->insertGetId([
            'employee_code' => 'ZZTEST-'.substr(md5(microtime()), 0, 8),
            'company_id' => DB::connection('tm2mst')->table('org_company')->value('company_id'),
            'full_name' => 'ZZ Test Rekening', 'created_by' => 'test', 'updated_by' => 'test',
        ]);
        $this->employeeIds[] = $id;

        return $id;
    }

    private function bankId(string $name): int
    {
        return (int) DB::connection('tm2mst')->table('sup_supplier')->where('category', 'BANK')->where('supplier_name', $name)->value('id_supplier');
    }

    public function test_existing_accounts_are_linked_to_bank_suppliers(): void
    {
        $this->assertSame(0, EmpBankAccount::whereNull('bank_supplier_id')->count(), 'Semua rekening lama tertaut ke supplier BANK.');
        $this->assertGreaterThanOrEqual(6, DB::connection('tm2mst')->table('sup_supplier')->where('category', 'BANK')->count());
    }

    // Layar v2 hrm-employee-form menyimpan rekening lewat EmployeeProfileService (tab Bank).
    private function profileInput(array $extra = []): array
    {
        return $extra + ['full_name' => 'ZZ Test Rekening', 'staff_type' => 'NON_STAFF', 'payment_frequency' => 'MONTHLY'];
    }

    public function test_master_employee_bank_tab_saves_via_supplier_lookup(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);
        $emp = $this->makeEmployee();
        $svc = app(EmployeeProfileService::class);

        $svc->save(EmpEmployee::findOrFail($emp), $this->profileInput(['bank_supplier_id' => (string) $this->bankId('BCA'), 'bank_account_no' => '1234567890', 'bank_account_holder' => 'ZZ Test Rekening']), $admin);

        $acc = EmpBankAccount::where('employee_id', $emp)->where('is_primary', true)->where('activate', true)->firstOrFail();
        $this->assertSame($this->bankId('BCA'), (int) $acc->bank_supplier_id);
        $this->assertSame('BCA', $acc->bank_name);
        $this->assertSame('1234567890', $acc->account_no);

        try {
            $svc->save(EmpEmployee::findOrFail($emp), $this->profileInput(['bank_supplier_id' => (string) $this->bankId('BCA'), 'bank_account_no' => '', 'bank_account_holder' => '']), $admin);
            $this->fail('Bank terpilih tanpa nomor rekening harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('bank_account_no', $e->errors());
        }

        $svc->save(EmpEmployee::findOrFail($emp), $this->profileInput(['bank_supplier_id' => '']), $admin);
        $this->assertNull(EmployeeBankOf($emp), 'Cash = rekening dinonaktifkan.');
    }

    public function test_self_service_bank_request_is_applied_on_hr_approval(): void
    {
        $emp = $this->makeEmployee();
        DB::connection('tm2mst')->table('emp_bank_account')->insert([
            'employee_id' => $emp, 'bank_supplier_id' => $this->bankId('BCA'), 'bank_name' => 'BCA', 'account_no' => '111',
            'account_holder_name' => 'ZZ Lama', 'is_primary' => 1, 'activate' => 1, 'created_by' => 'test', 'updated_by' => 'test',
        ]);

        $req = EmpCorrectionRequest::create([
            'employee_id' => $emp, 'field_name' => EmpCorrectionRequest::FIELD_BANK, 'old_value' => 'BCA · 111 · a.n. ZZ Lama',
            'new_value' => json_encode(['bank_supplier_id' => $this->bankId('MANDIRI'), 'account_no' => '999', 'account_holder_name' => 'ZZ Baru']),
            'requires_hr_approval' => true, 'status' => 'PENDING', 'requested_at' => now(),
        ]);
        $this->assertSame('MANDIRI · 999 · a.n. ZZ Baru', $req->newValueLabel());

        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        app(CorrectionRequestService::class)->approve($req, SecUser::where('login', 'johiwan')->firstOrFail());

        $acc = EmpBankAccount::where('employee_id', $emp)->where('is_primary', true)->where('activate', true)->firstOrFail();
        $this->assertSame('MANDIRI', $acc->bank_name);
        $this->assertSame('999', $acc->account_no);
        $this->assertSame('ZZ Baru', $acc->account_holder_name);
        $this->assertSame('APPROVED', $req->fresh()->status);
    }

    // T7b: halaman v1 Payroll > Rekening Bank dipensiunkan; daftar = grid hrm-bank (dibatasi payroll), rekening diubah di tab Bank Master Employee.
    public function test_payroll_bank_grid_opens_the_employee_bank_tab_and_v1_redirects(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $emp = $this->makeEmployee();

        $this->get(route('v2.screen', 'hrm-bank'))->assertOk();
        $this->assertStringContainsString('/v2/s/hrm-employee-form?id={id}&tab=bank', Grids::find('hrm-bank')->openUrl());
        $this->assertNull(Grids::find('hrm-bank')->newUrl());
        $this->get(route('v2.screen', 'hrm-employee-form').'?id='.$emp.'&tab=bank')->assertOk()->assertSee('name="bank_supplier_id"', false)->assertSee('— Cash —');
        $this->get('/hrm/payroll/employee-bank')->assertRedirect('/v2/s/hrm-bank');
        $this->get('/hrm/payroll/employee-bank?open='.$emp)->assertRedirect('/v2/s/hrm-employee-form?id='.$emp.'&tab=bank');
        $this->assertFileDoesNotExist(resource_path('views/pages/hrm/payroll-employee-bank.blade.php'));
        $this->assertArchived('hrm_payroll_employee_bank_v1_2026-10-19.blade.php');
        $this->assertArrayNotHasKey('HRM_PAYROLL_BANK', MenuRoutes::map());
    }

    public function test_loose_mode_only_name_is_required(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);
        $this->assertSame(0.0, (float) DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'HRM_EMPLOYEE_MANDATORY_STRICT')->value('constant_number'));
        $emp = $this->makeEmployee();
        $svc = app(EmployeeProfileService::class);

        $svc->save(EmpEmployee::findOrFail($emp), $this->profileInput(['gender' => '', 'join_date' => '', 'position_id' => null, 'nik_ktp' => '', 'phone' => '']), $admin);
        $this->assertSame('ZZ Test Rekening', EmpEmployee::findOrFail($emp)->full_name);

        try {
            $svc->save(EmpEmployee::findOrFail($emp), $this->profileInput(['full_name' => '']), $admin);
            $this->fail('Nama kosong harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('full_name', $e->errors());
        }
    }
}

function EmployeeBankOf(int $employeeId): ?EmpBankAccount
{
    return EmpBankAccount::where('employee_id', $employeeId)->where('is_primary', true)->where('activate', true)->first();
}
