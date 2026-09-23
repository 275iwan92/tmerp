<?php

namespace Tests\Feature;

use App\Models\Master\EmpBankAccount;
use App\Models\Master\EmpCorrectionRequest;
use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
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

    private function testEmployee(): int
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

    public function test_master_employee_bank_tab_saves_via_supplier_lookup(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $emp = $this->testEmployee();

        Livewire::test('pages::master.employee')
            ->call('openEdit', $emp)
            ->assertSee('Rekening Bank')
            ->set('bank_supplier_id', (string) $this->bankId('BCA'))
            ->set('bank_account_no', '1234567890')
            ->set('bank_account_holder', 'ZZ Test Rekening')
            ->call('saveBank')
            ->assertHasNoErrors();

        $acc = EmpBankAccount::where('employee_id', $emp)->where('is_primary', true)->where('activate', true)->firstOrFail();
        $this->assertSame($this->bankId('BCA'), (int) $acc->bank_supplier_id);
        $this->assertSame('BCA', $acc->bank_name);
        $this->assertSame('1234567890', $acc->account_no);
    }

    public function test_self_service_bank_request_is_applied_on_hr_approval(): void
    {
        $emp = $this->testEmployee();
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
        Livewire::test('pages::hrm.correction-requests')->call('approve', $req->request_id);

        $acc = EmpBankAccount::where('employee_id', $emp)->where('is_primary', true)->where('activate', true)->firstOrFail();
        $this->assertSame('MANDIRI', $acc->bank_name);
        $this->assertSame('999', $acc->account_no);
        $this->assertSame('ZZ Baru', $acc->account_holder_name);
        $this->assertSame('APPROVED', $req->fresh()->status);
    }

    public function test_payroll_bank_screen_uses_supplier_lookup_and_cash_option(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $emp = $this->testEmployee();

        $c = Livewire::test('pages::hrm.payroll-employee-bank')->call('openForm', $emp)
            ->set('bankSupplierId', (string) $this->bankId('BRI'))->set('accountNo', '555')->set('accountHolderName', 'ZZ')
            ->call('save')->assertHasNoErrors();
        $this->assertSame('BRI', EmployeeBankOf($emp)?->bank_name);

        $c->call('openForm', $emp)->set('bankSupplierId', '')->call('save');
        $this->assertNull(EmployeeBankOf($emp), 'Cash = rekening dinonaktifkan.');
    }

    public function test_loose_mode_only_name_is_required(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $this->assertSame(0.0, (float) DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'HRM_EMPLOYEE_MANDATORY_STRICT')->value('constant_number'));
        $emp = $this->testEmployee();

        Livewire::test('pages::master.employee')->call('openEdit', $emp)
            ->set('gender', '')->set('join_date', '')->set('position_id', null)->set('nik_ktp', '')->set('phone', '')
            ->call('save')->assertHasNoErrors();

        Livewire::test('pages::master.employee')->call('openEdit', $emp)->set('full_name', '')->call('save')->assertHasErrors(['full_name']);
    }
}

function EmployeeBankOf(int $employeeId): ?EmpBankAccount
{
    return EmpBankAccount::where('employee_id', $employeeId)->where('is_primary', true)->where('activate', true)->first();
}
