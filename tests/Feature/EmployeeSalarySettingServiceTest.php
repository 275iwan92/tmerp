<?php

/*
 * REMARK
 * Tujuan       : Menjaga master gaji dan pajak v2: hanya user berhak payroll atas pegawai itu (HR Officer hanya non-staff), validasi angka dan PTKP dari daftar, PTKP wajib bila PPh21 aktif, setiap perubahan = versi baru dengan versi lama ditutup, tanpa perubahan tidak membuat versi, riwayat versi, layar form, route, gerbang layar payroll, grid, redirect v1 dan menu.
 * Sumber hulu  : App\Services\Payroll\EmployeeSalarySettingService, PayrollAccess, layar hrm-salary-form, routes/v2_hrm.php, SalaryGrid.
 * Input        : User johiwan (admin, Direksi); versi gaji uji dibuat di dalam transaksi tm2pyr yang di-rollback, akun uji di tm2sys; data gaji nyata tidak berubah.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=EmployeeSalarySettingServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Master\EmpEmployee;
use App\Models\Pyr\PyrEmployeeSalary;
use App\Models\Sys\SecUser;
use App\Services\Payroll\EmployeeSalarySettingService;
use App\Services\Payroll\PayrollAccess;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EmployeeSalarySettingServiceTest extends TestCase
{
    private const CONNS = ['tm2pyr', 'tm2sys'];

    private EmpEmployee $emp;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::CONNS as $c) {
            DB::connection($c)->beginTransaction();
        }
        PayrollAccess::forget();
        $this->emp = EmpEmployee::where('activate', true)->where('staff_type', 'NON_STAFF')->orderBy('employee_id')->firstOrFail();
    }

    protected function tearDown(): void
    {
        foreach (self::CONNS as $c) {
            while (DB::connection($c)->transactionLevel() > 0) {
                DB::connection($c)->rollBack();
            }
        }
        PayrollAccess::forget();
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function user(string $login, array $groups = []): SecUser
    {
        $id = DB::connection('tm2sys')->table('sec_user')->insertGetId(['login' => $login, 'name' => 'Test '.$login, 'active' => 1, 'activate' => 1, 'is_admin' => 0]);
        foreach ($groups as $code) {
            DB::connection('tm2sys')->table('sec_user_group')->insert(['user_id' => $id, 'group_id' => DB::connection('tm2sys')->table('sec_group')->where('group_code', $code)->value('id_group')]);
        }
        PayrollAccess::forget();

        return SecUser::findOrFail($id);
    }

    private function in(array $over = []): array
    {
        return array_merge(['basic_salary_monthly' => '4000000', 'tj_keahlian_monthly' => '250000', 'tj_loyalitas_monthly' => null, 'tj_prestasi_monthly' => '', 'bonus_pagi_amount' => '5000', 'bonus_hadir_amount' => '7500',
            'ptkp_status' => 'K/1', 'bpjs_active' => true, 'pph21_active' => true], $over);
    }

    private function refused(callable $f, string $key, string $why): void
    {
        try {
            $f();
            $this->fail('harus ditolak: '.$why);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors(), $why);
        }
    }

    public function test_access_follows_the_payroll_scope(): void
    {
        $svc = app(EmployeeSalarySettingService::class);
        $staff = EmpEmployee::where('activate', true)->where('staff_type', 'STAFF')->orderBy('employee_id')->first();
        $hr = $this->user('tst_sal_hr', ['HR_OFFICER']);
        $none = $this->user('tst_sal_none');

        try {
            $svc->save($this->emp, $this->in(), $none);
            $this->fail('tanpa hak payroll harus ditolak');
        } catch (AuthorizationException) {
            $this->assertSame(0, PyrEmployeeSalary::where('employee_id', $this->emp->employee_id)->where('created_by', $none->login)->count());
        }
        $this->assertNotNull($svc->save($this->emp, $this->in(), $hr), 'HR Officer boleh untuk pegawai non-staff');
        if ($staff) {
            try {
                $svc->save($staff, $this->in(), $hr);
                $this->fail('HR Officer tidak boleh untuk pegawai staff');
            } catch (AuthorizationException) {
                $this->assertSame([], EmployeeSalarySettingService::versions($staff, $hr));
            }
            $this->assertNotNull($svc->save($staff, $this->in(), $this->admin()), 'Direksi boleh untuk semua');
        }
        $this->assertSame([], EmployeeSalarySettingService::versions($this->emp, $none));
        $this->assertFalse(PayrollAccess::screenAllowed('hrm-salary-form', $none));
        $this->assertTrue(PayrollAccess::screenAllowed('hrm-salary-form', $hr));
    }

    public function test_validation_and_versioning(): void
    {
        $svc = app(EmployeeSalarySettingService::class);
        $admin = $this->admin();
        foreach (['basic missing' => ['basic_salary_monthly' => ''], 'basic negative' => ['basic_salary_monthly' => '-1'], 'basic text' => ['basic_salary_monthly' => 'abc'], 'basic huge' => ['basic_salary_monthly' => '999999999999'],
            'allowance negative' => ['tj_keahlian_monthly' => '-5'], 'bonus text' => ['bonus_hadir_amount' => 'x'], 'ptkp unknown' => ['ptkp_status' => 'ZZ/9']] as $name => $over) {
            try {
                $svc->save($this->emp, $this->in($over), $admin);
                $this->fail('harus ditolak: '.$name);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors(), $name);
            }
        }
        $this->refused(fn () => $svc->save($this->emp, $this->in(['ptkp_status' => null, 'pph21_active' => true]), $admin), 'ptkp_status', 'PPh21 tanpa PTKP');
        $this->assertSame(0, PyrEmployeeSalary::where('employee_id', $this->emp->employee_id)->where('created_by', $admin->login)->count(), 'penolakan tidak menulis apa pun');

        $a = $svc->save($this->emp, $this->in(), $admin);
        $this->assertSame(4000000.0, (float) $a->basic_salary_monthly);
        $this->assertSame(0.0, (float) $a->tj_loyalitas_monthly, 'kosong = 0');
        $this->assertSame('K/1', $a->ptkp_status);
        $this->assertTrue($a->activate);
        $this->assertSame(1, PyrEmployeeSalary::where('employee_id', $this->emp->employee_id)->where('activate', true)->count());

        $this->assertNull($svc->save($this->emp, $this->in(), $admin), 'tanpa perubahan tidak membuat versi');
        $b = $svc->save($this->emp, $this->in(['basic_salary_monthly' => '4500000', 'pph21_active' => false, 'ptkp_status' => null]), $admin);
        $this->assertNotNull($b);
        $this->assertFalse($a->fresh()->activate, 'versi lama ditutup');
        $this->assertSame(now()->toDateString(), $a->fresh()->effective_to->toDateString());
        $this->assertSame(1, PyrEmployeeSalary::where('employee_id', $this->emp->employee_id)->where('activate', true)->count());
        $this->assertSame((int) $b->id_salary, (int) PyrEmployeeSalary::activeFor($this->emp->employee_id)->id_salary);

        $versions = EmployeeSalarySettingService::versions($this->emp, $admin);
        $this->assertGreaterThanOrEqual(2, count($versions));
        $this->assertSame('Current', $svc->history($this->emp, $admin)[0]['action']);
    }

    public function test_screen_route_grid_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        $form = route('v2.screen', 'hrm-salary-form');
        $this->get(route('v2.screen', 'hrm-salary'))->assertOk();
        $this->get($form)->assertNotFound();
        $this->get($form.'?id=999999999')->assertNotFound();
        $this->get($form.'?id='.$this->emp->employee_id)->assertOk()->assertSee('name="basic_salary_monthly"', false)->assertSee('name="ptkp_status"', false);

        $save = route('v2.hrm.salary.save');
        $this->post($save, ['id' => $this->emp->employee_id, 'basic_salary_monthly' => '-5'])->assertSessionHasErrors('basic_salary_monthly');
        $this->post($save, ['id' => $this->emp->employee_id, 'basic_salary_monthly' => '4200000', 'ptkp_status' => 'TK/0', 'bpjs_active' => '1', 'pph21_active' => '1'])->assertRedirect($form.'?id='.$this->emp->employee_id);
        $cur = PyrEmployeeSalary::activeFor($this->emp->employee_id);
        $this->assertSame(4200000.0, (float) $cur->basic_salary_monthly);
        $this->assertTrue($cur->bpjs_active);
        $this->post($save, ['id' => $this->emp->employee_id, 'basic_salary_monthly' => '4200000', 'ptkp_status' => 'TK/0', 'bpjs_active' => '1', 'pph21_active' => '1'])->assertRedirect();
        $this->assertSame((int) $cur->id_salary, (int) PyrEmployeeSalary::activeFor($this->emp->employee_id)->id_salary, 'tanpa perubahan tidak ada versi baru');
        $this->post($save, ['id' => 999999999, 'basic_salary_monthly' => '1'])->assertNotFound();

        $grid = Grids::find('hrm-salary');
        $this->assertStringContainsString('/v2/s/hrm-salary-form?id={id}', $grid->openUrl());
        $this->assertFileDoesNotExist(resource_path('views/pages/hrm/payroll-employee-salary.blade.php'));
        $this->assertArchived('payroll_employee_salary_v1_2026-10-20.blade.php');
        $this->assertArrayNotHasKey('HRM_PAYROLL_SALARY', MenuRoutes::map());
        $this->get('/hrm/payroll/employee-salary')->assertRedirect('/v2/s/hrm-salary');
        $this->get('/hrm/payroll/employee-salary?open=7')->assertRedirect('/v2/s/hrm-salary-form?id=7');
    }
}
