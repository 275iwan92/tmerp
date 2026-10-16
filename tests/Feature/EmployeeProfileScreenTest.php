<?php

namespace Tests\Feature;

use App\Models\Master\EmpEmployee;
use App\Models\Sys\SecUser;
use App\Services\Hrm\EmployeeProfileService;
use App\Support\V2\Data\EmployeeProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Layar v2 Master Employee Form (hrm-employee-form): render, kelengkapan, dan simpan. Simpan berjalan di transaksi lalu di-rollback (data nyata tidak berubah).
class EmployeeProfileScreenTest extends TestCase
{
    private function admin(): SecUser
    {
        return SecUser::query()->where('is_admin', true)->where('active', true)->first() ?? $this->fail('Butuh satu akun admin aktif.');
    }

    public function test_form_renders_banner_and_tabs_for_an_existing_employee(): void
    {
        $e = EmpEmployee::withoutGlobalScopes()->where('activate', true)->whereNotNull('join_date')->firstOrFail();
        $this->actingAs($this->admin())->get(route('v2.screen', 'hrm-employee-form').'?id='.$e->employee_id)
            ->assertOk()->assertSee('Tenure')->assertSee('Leave days left')->assertSee('Discipline')->assertSee('Salary account')->assertSee('History');
    }

    public function test_new_form_renders_and_grid_points_to_it(): void
    {
        $this->actingAs($this->admin())->get(route('v2.screen', 'hrm-employee-form').'?new=1')->assertOk()->assertSee('New Employee');
        $this->actingAs($this->admin())->get(route('v2.screen', 'hrm-employee'))->assertOk()->assertSee('hrm-employee-form', false);
    }

    public function test_percent_chains_with_missing_list(): void
    {
        $e = new EmpEmployee(['full_name' => 'X', 'nik_ktp' => '123']);
        $missing = count(EmployeeProfile::missing($e));
        $this->assertSame((int) round(100 * (EmployeeProfile::total() - $missing) / EmployeeProfile::total()), EmployeeProfile::percent($e));
    }

    public function test_save_creates_then_updates_and_validates_nik(): void
    {
        $svc = app(EmployeeProfileService::class);
        $admin = $this->admin();
        $base = ['full_name' => 'Zz Test Profil', 'gender' => 'P', 'birth_date' => '1995-05-05', 'nik_ktp' => '1234567890123456', 'no_kk' => '111', 'phone' => '0812',
            'address_detail_domisili' => 'Jl Test', 'join_date' => '2026-01-01', 'work_status' => 'WORKING', 'email_personal' => 'zz@test.local', 'staff_type' => 'NON_STAFF', 'payment_frequency' => 'MONTHLY'];
        $pos = DB::connection('tm2mst')->table('org_position')->where('activate', true)->value('position_id');

        DB::connection('tm2mst')->beginTransaction();
        DB::connection('tm2sys')->beginTransaction();
        try {
            [$e] = $svc->save(null, $base + ['position_id' => $pos], $admin);
            $this->assertMatchesRegularExpression('/^[A-Z]+-\d{5}$/', $e->employee_code);
            [$e2] = $svc->save($e, ['full_name' => 'Zz Test Profil B'] + $base + ['position_id' => $pos, 'nickname' => 'Zz'], $admin);
            $this->assertSame('Zz Test Profil B', $e2->fresh()->full_name);
            $this->expectException(ValidationException::class);
            $svc->save($e2, ['nik_ktp' => '12'] + $base + ['position_id' => $pos], $admin);
        } finally {
            DB::connection('tm2sys')->rollBack();
            DB::connection('tm2mst')->rollBack();
        }
    }
}
