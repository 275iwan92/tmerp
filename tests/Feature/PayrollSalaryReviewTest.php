<?php

namespace Tests\Feature;

use App\Models\Master\EmpEmployee;
use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\PayrollSalaryReview;
use Tests\TestCase;

// Salary Review (Excel) untuk direksi. Periode uji tahun 2099 status CALCULATED dengan satu slip (gaji pokok 1.500.000, bersih 1.234.000); dibersihkan lagi.
class PayrollSalaryReviewTest extends TestCase
{
    private PyrPayrollRun $run;

    protected function setUp(): void
    {
        parent::setUp();
        $employee = EmpEmployee::where('activate', true)->first() ?? $this->markTestSkipped('No active employee.');
        $this->run = PyrPayrollRun::create(['period_type' => 'WEEKLY', 'period_start' => '2099-02-02', 'period_end' => '2099-02-08', 'run_status' => 'CALCULATED', 'triggered_by' => 'test', 'triggered_at' => now()]);
        PyrPayslip::create(['id_run' => $this->run->id_run, 'employee_id' => $employee->employee_id, 'total_pokok' => 1_500_000, 'total_gaji_bersih' => 1_234_000, 'has_anomaly' => false]);
    }

    protected function tearDown(): void
    {
        if (isset($this->run)) {
            PyrPayslip::where('id_run', $this->run->id_run)->delete();
            $this->run->delete();
        }
        parent::tearDown();
    }

    public function test_rows_have_decrypted_amounts_matching_headings_and_a_total_row(): void
    {
        $rows = PayrollSalaryReview::rows($this->run->id_run);
        $this->assertCount(2, $rows);
        $this->assertCount(count(PayrollSalaryReview::headings()), $rows[0]);
        $this->assertSame(1_500_000.0, $rows[0][4]);
        $this->assertSame(1_234_000.0, $rows[0][15]);
        $this->assertSame(1_234_000.0, $rows[1][15]);
    }

    public function test_download_is_limited_to_those_allowed_to_see_salary_amounts(): void
    {
        $url = route('v2.payroll.salary-xlsx', $this->run->id_run);
        $plain = SecUser::query()->get()->first(fn (SecUser $u) => ! $u->canViewPayrollAmounts() && $u->canAccessModule('HRM'));
        if ($plain) {
            $this->actingAs($plain)->get($url)->assertForbidden();
        }
        $allowed = SecUser::query()->get()->first(fn (SecUser $u) => $u->canViewPayrollAmounts() && $u->canAccessModule('HRM')) ?? $this->markTestSkipped('No user allowed to see payroll amounts.');
        $this->actingAs($allowed)->get($url)->assertOk()->assertHeader('content-disposition');
    }

    public function test_screen_shows_the_download_excel_button(): void
    {
        $allowed = SecUser::query()->get()->first(fn (SecUser $u) => $u->canViewPayrollAmounts() && $u->canAccessModule('HRM')) ?? $this->markTestSkipped('No user allowed to see payroll amounts.');
        $this->actingAs($allowed)->get(route('v2.screen', 'hrm-salary-review').'?run='.$this->run->id_run)
            ->assertOk()->assertSee('Download Excel')->assertSee(route('v2.payroll.salary-xlsx', $this->run->id_run), false);
    }
}
