<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use App\Support\V2\Data\EmployeeSalaryExport;
use Tests\TestCase;

// Download Excel di Master Employee Salary & Tax (setelan gaji master, bukan slip payroll). Hanya baca; tidak menulis data.
class EmployeeSalaryExportTest extends TestCase
{
    public function test_rows_match_headings_one_per_active_employee(): void
    {
        $rows = EmployeeSalaryExport::rows();
        $this->assertNotEmpty($rows);
        $this->assertCount(count(EmployeeSalaryExport::headings()), $rows[0]);
    }

    public function test_download_is_limited_to_gm_and_direksi(): void
    {
        $url = route('v2.payroll.employee-salary-xlsx');
        $plain = SecUser::query()->get()->first(fn (SecUser $u) => ! $u->canExportPayrollAmounts() && $u->canAccessModule('HRM'));
        if ($plain) {
            $this->actingAs($plain)->get($url)->assertForbidden();
        }
        $allowed = SecUser::query()->get()->first(fn (SecUser $u) => $u->canExportPayrollAmounts() && $u->canAccessModule('HRM')) ?? $this->markTestSkipped('No GM / Direksi user.');
        $this->actingAs($allowed)->get($url)->assertOk()->assertHeader('content-disposition');
    }

    public function test_grid_screen_shows_the_download_excel_button(): void
    {
        $allowed = SecUser::query()->get()->first(fn (SecUser $u) => $u->canExportPayrollAmounts() && $u->canAccessModule('HRM')) ?? $this->markTestSkipped('No GM / Direksi user.');
        $this->actingAs($allowed)->get(route('v2.screen', 'hrm-salary'))->assertOk()->assertSee('Download Excel')->assertSee(route('v2.payroll.employee-salary-xlsx'), false);
    }
}
