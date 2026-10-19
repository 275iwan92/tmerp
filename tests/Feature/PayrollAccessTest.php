<?php

namespace Tests\Feature;

use App\Http\Middleware\ForcePasswordChange;
use App\Models\Master\EmpBankAccount;
use App\Models\Master\EmpEmployee;
use App\Models\Pyr\PyrPayslip;
use App\Models\Sys\SecUser;
use App\Services\Hrm\EmployeeProfileService;
use App\Services\Payroll\PayrollAccess;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Pembatasan payroll (user 2026-10-07): Direksi/GM semua, HR Officer hanya Non-staff, Op Manager hanya slip bawahan, selain itu tolak
// (termasuk admin / developer). Menulis akun uji sementara ke tm2sys dan menghapusnya sendiri di tearDown.
class PayrollAccessTest extends TestCase
{
    private const PREFIX = 'tst_payroll_';

    protected function tearDown(): void
    {
        $ids = DB::connection('tm2sys')->table('sec_user')->where('login', 'like', self::PREFIX.'%')->pluck('id_user');
        DB::connection('tm2sys')->table('sec_user_group')->whereIn('user_id', $ids)->delete();
        DB::connection('tm2sys')->table('sec_user')->whereIn('id_user', $ids)->delete();
        PayrollAccess::forget();

        parent::tearDown();
    }

    private function user(string $key, array $groups = [], bool $admin = false, ?int $employeeId = null): SecUser
    {
        $id = DB::connection('tm2sys')->table('sec_user')->insertGetId([
            'login' => self::PREFIX.$key, 'name' => 'Test '.$key, 'active' => 1, 'activate' => 1, 'is_admin' => $admin ? 1 : 0, 'employee_id' => $employeeId,
        ]);
        foreach ($groups as $code) {
            DB::connection('tm2sys')->table('sec_user_group')->insert([
                'user_id' => $id, 'group_id' => DB::connection('tm2sys')->table('sec_group')->where('group_code', $code)->value('id_group'),
            ]);
        }
        PayrollAccess::forget();

        return SecUser::query()->findOrFail($id);
    }

    private function employeeAt(string $positionName): int
    {
        return (int) EmpEmployee::query()->whereHas('position', fn ($q) => $q->where('position_name', $positionName))->value('employee_id');
    }

    public function test_scope_follows_group_and_admin_is_not_enough(): void
    {
        $annisa = $this->employeeAt('Operational Manager');

        $this->assertSame(PayrollAccess::ALL, PayrollAccess::scope($this->user('dir', ['DIREKSI'])));
        $this->assertSame(PayrollAccess::NON_STAFF, PayrollAccess::scope($this->user('hr', ['HR_OFFICER'])));
        $this->assertSame(PayrollAccess::SUBORDINATES, PayrollAccess::scope($this->user('opm', ['OPS_MGR'], false, $annisa)));
        $this->assertSame(PayrollAccess::NONE, PayrollAccess::scope($this->user('ops_without_employee', ['OPS_MGR'])));
        $this->assertSame(PayrollAccess::NONE, PayrollAccess::scope($this->user('plain')));
        $this->assertSame(PayrollAccess::NONE, PayrollAccess::scope($this->user('admin', [], true)), 'developer/admin without DIREKSI must not see payroll');
    }

    public function test_capabilities_per_role(): void
    {
        $dir = $this->user('dir', ['DIREKSI']);
        $hr = $this->user('hr', ['HR_OFFICER']);
        $opm = $this->user('opm', ['OPS_MGR'], false, $this->employeeAt('Operational Manager'));
        $none = $this->user('admin', [], true);

        $this->assertTrue(PayrollAccess::canProcess($dir) && PayrollAccess::canEditStaffType($dir) && $dir->canViewPayrollAmounts() && $dir->canExportPayrollAmounts());
        $this->assertTrue(PayrollAccess::canProcess($hr) && PayrollAccess::canViewPayslips($hr));
        $this->assertFalse(PayrollAccess::canEditStaffType($hr) || $hr->canViewPayrollAmounts());
        $this->assertTrue(PayrollAccess::canViewPayslips($opm));
        $this->assertFalse(PayrollAccess::canProcess($opm) || $opm->canExportPayrollAmounts(), 'Op Manager only sees subordinate payslips');
        $this->assertFalse(PayrollAccess::canViewPayslips($none) || PayrollAccess::canProcess($none));

        $this->assertFalse(PayrollAccess::screenAllowed('hrm-payslip', $none));
        $this->assertFalse(PayrollAccess::screenAllowed('hrm-salary', $opm));
        $this->assertTrue(PayrollAccess::screenAllowed('hrm-payslip', $opm));
        $this->assertTrue(PayrollAccess::screenAllowed('hrm-employee', $none), 'non-payroll screens are untouched');
        $this->assertFalse(PayrollAccess::menuAllowed('HRM_PAYROLL_CALC', $none));
        $this->assertTrue(PayrollAccess::menuAllowed('HRM_PAYROLL_PAYSLIP', $opm));
    }

    public function test_employee_scope_hr_sees_only_non_staff(): void
    {
        $hr = $this->user('hr', ['HR_OFFICER']);
        $ids = PayrollAccess::employeeIds($hr);

        $this->assertNull(PayrollAccess::employeeIds($this->user('dir', ['DIREKSI'])));
        $this->assertNotEmpty($ids);
        $this->assertSame(0, EmpEmployee::query()->whereIn('employee_id', $ids)->where('staff_type', 'STAFF')->count());

        $staff = (int) EmpEmployee::query()->where('staff_type', 'STAFF')->value('employee_id');
        $this->assertFalse(PayrollAccess::canSeeEmployee($hr, $staff));
        $this->assertSame(0, PayrollAccess::restrict(PyrPayslip::query(), $hr)->whereIn('employee_id', [$staff])->count());
    }

    public function test_op_manager_sees_subordinates_but_not_self_or_above(): void
    {
        $annisa = $this->employeeAt('Operational Manager');
        $opm = $this->user('opm', ['OPS_MGR'], false, $annisa);
        $ids = PayrollAccess::employeeIds($opm);

        $this->assertNotContains($annisa, $ids);
        $this->assertNotContains($this->employeeAt('General Manager'), $ids);
        $this->assertNotContains($this->employeeAt('Presiden Direktur'), $ids);
        $this->assertContains($this->employeeAt('Human Resource Officer'), $ids, 'HR Officer sits under the Operational Manager');
        $this->assertContains($this->employeeAt('Technical Support & Maintenance'), $ids);
    }

    public function test_payroll_screens_reject_users_without_a_payroll_role(): void
    {
        $admin = $this->user('admin', [], true);
        $this->withoutMiddleware(ForcePasswordChange::class);

        $this->actingAs($admin)->get(route('hrm.payroll.batch-run'))->assertForbidden();
        $this->actingAs($admin)->get(route('hrm.payroll.payslip-list'))->assertForbidden();
        $this->actingAs($admin)->get(route('v2.screen', 'hrm-payslip'))->assertForbidden();
        $this->actingAs($admin)->get(route('v2.screen', 'hrm-salary'))->assertForbidden();
        $this->actingAs($admin)->get(route('v2.payroll.bank-csv', 1))->assertForbidden();
    }

    public function test_every_employee_has_a_type_and_a_default_payroll_method(): void
    {
        $this->assertSame(0, EmpEmployee::query()->whereNotIn('staff_type', ['STAFF', 'NON_STAFF'])->count());
        $this->assertSame(0, EmpEmployee::query()->whereNotIn('payment_frequency', ['WEEKLY', 'MONTHLY'])->count());
        $this->assertGreaterThanOrEqual(38, EmpEmployee::query()->where('staff_type', 'STAFF')->count());
    }

    public function test_master_employee_form_shows_type_and_method_and_only_direksi_edits_the_type(): void
    {
        $staffId = (int) EmpEmployee::query()->where('staff_type', 'STAFF')->value('employee_id');

        $this->withoutMiddleware(ForcePasswordChange::class);
        $dir = $this->user('dir', ['DIREKSI']);
        $this->assertTrue(EmployeeProfileService::canEditStaffType($dir));
        $this->actingAs($dir)->get(route('v2.screen', 'hrm-employee-form').'?id='.$staffId)
            ->assertOk()->assertSee('Employee type')->assertSee('Payroll method')->assertSee('name="staff_type"', false)->assertSee('name="payment_frequency"', false);

        $hr = $this->user('hr', ['HR_OFFICER']);
        $this->assertFalse(EmployeeProfileService::canEditStaffType($hr));
        $this->assertFalse(EmployeeProfileService::canEditPayrollMethod($hr, EmpEmployee::find($staffId)), 'HR Officer cannot change the payroll method of a staff member');
    }

    // Rekening gaji = data payroll (T7b, Jo 2026-10-19): tab Bank di Master Employee dan simpan rekening mengikuti PayrollAccess.
    public function test_bank_tab_and_bank_save_follow_the_payroll_scope(): void
    {
        $this->withoutMiddleware(ForcePasswordChange::class);
        $staff = EmpEmployee::query()->where('staff_type', 'STAFF')->where('activate', true)->where('employee_id', '>', 1)->orderBy('employee_id')->firstOrFail();
        $non = EmpEmployee::query()->where('staff_type', 'NON_STAFF')->where('activate', true)->where('employee_id', '>', 1)->orderBy('employee_id')->firstOrFail();
        $dir = $this->user('dir', ['DIREKSI']);
        $hr = $this->user('hr', ['HR_OFFICER']);
        $admin = $this->user('admin', [], true);

        $this->assertTrue(EmployeeProfileService::canEditBank($dir, $staff));
        $this->assertTrue(EmployeeProfileService::canEditBank($dir, $non));
        $this->assertFalse(EmployeeProfileService::canEditBank($hr, $staff), 'HR Officer holds Non-staff only');
        $this->assertTrue(EmployeeProfileService::canEditBank($hr, $non));
        $this->assertFalse(EmployeeProfileService::canEditBank($admin, $non), 'admin without payroll role');
        $this->assertFalse(EmployeeProfileService::canEditBank($dir, null));

        $form = fn (SecUser $u, EmpEmployee $e) => $this->actingAs($u)->get(route('v2.screen', 'hrm-employee-form').'?id='.$e->employee_id.'&tab=bank');
        $form($dir, $staff)->assertOk()->assertSee('name="bank_supplier_id"', false);
        $form($hr, $non)->assertOk()->assertSee('name="bank_supplier_id"', false);
        $form($hr, $staff)->assertOk()->assertDontSee('name="bank_supplier_id"', false);
        $form($admin, $non)->assertOk()->assertDontSee('name="bank_supplier_id"', false);

        // Simpan: bank yang terkirim oleh pengguna tanpa hak diabaikan server (di dalam transaksi yang di-rollback).
        $bank = (int) DB::connection('tm2mst')->table('sup_supplier')->where('category', 'BANK')->where('activate', 1)->orderBy('id_supplier')->value('id_supplier');
        DB::connection('tm2mst')->beginTransaction();
        try {
            $tmp = EmpEmployee::create(['employee_code' => 'ZZ-BANKGAP', 'company_id' => $staff->company_id, 'full_name' => 'ZZ Bank Gap', 'staff_type' => 'STAFF', 'payment_frequency' => 'MONTHLY', 'created_by' => 'test', 'updated_by' => 'test']);
            $input = ['full_name' => 'ZZ Bank Gap', 'staff_type' => 'STAFF', 'payment_frequency' => 'MONTHLY', 'bank_supplier_id' => (string) $bank, 'bank_account_no' => '123', 'bank_account_holder' => 'ZZ'];
            app(EmployeeProfileService::class)->save($tmp, $input, $hr);
            app(EmployeeProfileService::class)->save($tmp, $input, $admin);
            $this->assertSame(0, EmpBankAccount::where('employee_id', $tmp->employee_id)->count(), 'no account written without the payroll right');
            app(EmployeeProfileService::class)->save($tmp, $input, $dir);
            $this->assertSame('123', EmpBankAccount::where('employee_id', $tmp->employee_id)->where('activate', true)->value('account_no'));
        } finally {
            DB::connection('tm2mst')->rollBack();
        }
    }
}
