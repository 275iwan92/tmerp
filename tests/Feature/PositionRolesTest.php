<?php

namespace Tests\Feature;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpEmployeePosition;
use App\Models\Master\OrgPosition;
use App\Models\Master\OrgPositionKpi;
use App\Models\Sys\SecUser;
use App\Services\Hrm\EmployeeProfileService;
use App\Services\Hrm\EmployeeRoleService;
use App\Services\Hrm\PositionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

// Jabatan ganda per pegawai (emp_employee_position) dan Master Position v2 (job description + KPI). Semua di transaksi lalu di-rollback.
class PositionRolesTest extends TestCase
{
    private function inTransaction(callable $fn): void
    {
        DB::connection('tm2mst')->beginTransaction();
        DB::connection('tm2sys')->beginTransaction();
        try {
            $admin = SecUser::query()->where('is_admin', true)->where('active', true)->firstOrFail();
            $m = DB::connection('tm2mst');
            $dept = $m->table('org_department')->where('activate', true)->value('department_id');
            $level = $m->table('org_level')->where('activate', true)->value('level_id');
            $mk = fn (string $code, string $name) => OrgPosition::create(['position_code' => $code, 'position_name' => $name, 'department_id' => $dept, 'level_id' => $level, 'activate' => true]);
            $a = $mk('ZZ-A', 'Zz Position A');
            $b = $mk('ZZ-B', 'Zz Position B');
            $c = $mk('ZZ-C', 'Zz Position C');
            [$e] = app(EmployeeProfileService::class)->save(null, ['full_name' => 'Zz Test Jabatan', 'staff_type' => 'NON_STAFF', 'payment_frequency' => 'MONTHLY', 'position_id' => $a->position_id], $admin);
            $fn($e, $admin, [$a, $b, $c], $dept, $level);
        } finally {
            DB::connection('tm2sys')->rollBack();
            DB::connection('tm2mst')->rollBack();
        }
    }

    public function test_one_person_holds_several_positions_and_primary_can_be_swapped(): void
    {
        $this->inTransaction(function (EmpEmployee $e, SecUser $admin, array $p) {
            $svc = app(EmployeeRoleService::class);
            [$a, $b, $c] = $p;

            $svc->add($e, $b->position_id, '2026-03-15', $admin);
            $svc->add($e, $c->position_id, null, $admin);
            $held = $svc->held($e->fresh());
            $this->assertSame([$a->position_id, $b->position_id, $c->position_id], $held->pluck('position.position_id')->all());
            $this->assertSame([true, false, false], $held->pluck('primary')->all());

            $svc->makePrimary($e, $b->position_id, $admin);
            $this->assertSame($b->position_id, $e->fresh()->position_id, 'posisi utama berpindah');
            $held = $svc->held($e->fresh());
            $this->assertEqualsCanonicalizing([$a->position_id, $b->position_id, $c->position_id], $held->pluck('position.position_id')->all());
            $this->assertSame($b->position_id, $held->first()['position']->position_id);
            $this->assertSame(1, $held->where('primary', true)->count());

            $svc->remove($e->fresh(), $c->position_id, $admin);
            $this->assertCount(2, $svc->held($e->fresh()));
            $svc->add($e->fresh(), $c->position_id, null, $admin); // dipasang ulang = baris lama diaktifkan lagi
            $this->assertSame(1, EmpEmployeePosition::where('employee_id', $e->employee_id)->where('position_id', $c->position_id)->count());
        });
    }

    public function test_role_rules_are_enforced(): void
    {
        $this->inTransaction(function (EmpEmployee $e, SecUser $admin, array $p) {
            $svc = app(EmployeeRoleService::class);
            [$a, $b] = $p;
            $svc->add($e, $b->position_id, null, $admin);

            foreach ([
                fn () => $svc->add($e->fresh(), $a->position_id, null, $admin),      // sudah posisi utama
                fn () => $svc->add($e->fresh(), $b->position_id, null, $admin),      // sudah dipegang
                fn () => $svc->remove($e->fresh(), $a->position_id, $admin),         // utama tidak bisa dilepas
                fn () => $svc->makePrimary($e->fresh(), 99999999, $admin),           // hanya jabatan yang dipegang
            ] as $i => $try) {
                try {
                    $try();
                    $this->fail("aturan #$i harus menolak");
                } catch (ValidationException) {
                    $this->addToAssertionCount(1);
                }
            }
        });
    }

    public function test_position_save_rules_job_description_gate_and_kpi_weights(): void
    {
        $this->inTransaction(function (EmpEmployee $e, SecUser $admin, array $p, $dept, $level) {
            $svc = app(PositionService::class);
            [$a, $b] = $p;

            $saved = $svc->save(null, ['position_code' => 'ZZ-N', 'position_name' => 'Zz New', 'department_id' => $dept, 'level_id' => $level, 'job_description' => 'Tugas satu'], $admin);
            $this->assertSame('Tugas satu', $saved->fresh()->job_description);
            $this->assertNotNull($saved->fresh()->job_description_updated_at);

            try {
                $svc->save(null, ['position_code' => 'ZZ-N', 'position_name' => 'Zz Dup', 'department_id' => $dept, 'level_id' => $level], $admin);
                $this->fail('kode ganda di departemen yang sama harus ditolak');
            } catch (ValidationException $ex) {
                $this->assertArrayHasKey('position_code', $ex->errors());
            }

            // siklus: A melapor ke B, lalu B melapor ke A ditolak
            $svc->save($a, ['position_code' => 'ZZ-A', 'position_name' => 'Zz Position A', 'department_id' => $dept, 'level_id' => $level, 'reports_to_position_id' => $b->position_id], $admin);
            try {
                $svc->save($b, ['position_code' => 'ZZ-B', 'position_name' => 'Zz Position B', 'department_id' => $dept, 'level_id' => $level, 'reports_to_position_id' => $a->position_id], $admin);
                $this->fail('siklus harus ditolak');
            } catch (ValidationException $ex) {
                $this->assertArrayHasKey('reports_to_position_id', $ex->errors());
            }

            $k1 = $svc->addKpi($a, ['kpi_name' => 'Output', 'unit' => 'sheets', 'direction' => 'HIGHER', 'target_value' => '12000', 'weight_pct' => '60'], $admin);
            $svc->addKpi($a, ['kpi_name' => 'Waste', 'unit' => '%', 'direction' => 'LOWER', 'target_value' => '4', 'weight_pct' => '40'], $admin);
            $this->assertEqualsWithDelta(100.0, $svc->totalWeight($a), 0.001);
            try {
                $svc->addKpi($a, ['kpi_name' => 'Extra', 'direction' => 'HIGHER', 'target_value' => '1', 'weight_pct' => '1'], $admin);
                $this->fail('total bobot > 100% harus ditolak');
            } catch (ValidationException $ex) {
                $this->assertArrayHasKey('weight_pct', $ex->errors());
            }
            $svc->removeKpi($k1, $admin);
            $this->assertEqualsWithDelta(40.0, $svc->totalWeight($a), 0.001);
            $this->assertFalse((bool) OrgPositionKpi::find($k1->id_position_kpi)->activate, 'KPI dinonaktifkan, bukan dihapus');
        });
    }

    public function test_only_hr_officer_edits_job_description_and_kpi(): void
    {
        $this->inTransaction(function (EmpEmployee $e, SecUser $admin, array $p, $dept, $level) {
            $uid = DB::connection('tm2sys')->table('sec_user')->insertGetId(['login' => 'zz_plain_user', 'name' => 'Zz Plain', 'active' => 1, 'activate' => 1, 'is_admin' => 0]);
            $plain = SecUser::findOrFail($uid);
            $svc = app(PositionService::class);
            $this->assertFalse($svc->canEditJobDesk($plain));

            [$a] = $p;
            $svc->save($a, ['position_code' => 'ZZ-A', 'position_name' => 'Zz Position A', 'department_id' => $dept, 'level_id' => $level, 'job_description' => 'Dari non HR'], $plain);
            $this->assertNull($a->fresh()->job_description, 'job description diabaikan untuk non HR Officer');

            $this->expectException(HttpException::class);
            $svc->addKpi($a, ['kpi_name' => 'X', 'direction' => 'HIGHER', 'target_value' => '1', 'weight_pct' => '10'], $plain);
        });
    }

    public function test_screens_render_and_routes_redirect_to_the_right_tab(): void
    {
        $this->inTransaction(function (EmpEmployee $e, SecUser $admin, array $p) {
            [$a, $b] = $p;
            app(EmployeeRoleService::class)->add($e, $b->position_id, null, $admin);
            app(PositionService::class)->addKpi($a, ['kpi_name' => 'Zz KPI Output', 'unit' => 'sheets', 'direction' => 'HIGHER', 'target_value' => '100', 'weight_pct' => '100'], $admin);
            $a->update(['job_description' => 'Zz uraian tugas A']);

            $job = $this->actingAs($admin)->get(route('v2.screen', 'hrm-employee-form').'?id='.$e->employee_id)->assertOk()
                ->assertSee('data-dl-switcher', false)->assertSee('Zz Position B')->assertSee('Zz uraian tugas A')->assertSee('Zz KPI Output');
            $this->assertNotNull($job);

            $this->actingAs($admin)->get(route('v2.screen', 'hrm-position-form').'?id='.$a->position_id)->assertOk()->assertSee('Zz KPI Output')->assertSee('Zz Test Jabatan');
            $this->actingAs($admin)->get(route('v2.screen', 'hrm-position-form').'?new=1')->assertOk()->assertSee('New Position');
            $this->actingAs($admin)->get(route('v2.screen', 'hrm-position'))->assertOk()->assertSee('hrm-position-form', false);

            $this->actingAs($admin)->post(route('v2.hrm.employee.position.primary', $e->employee_id), ['position_id' => $b->position_id])
                ->assertRedirect(route('v2.screen', 'hrm-employee-form').'?id='.$e->employee_id.'&tab=job');
            $this->assertSame($b->position_id, $e->fresh()->position_id);
            $this->actingAs($admin)->post(route('v2.hrm.position.kpi', $b->position_id), ['kpi_name' => 'Zz B', 'direction' => 'LOWER', 'target_value' => '5', 'weight_pct' => '50'])
                ->assertRedirect(route('v2.screen', 'hrm-position-form').'?id='.$b->position_id.'&tab=kpi');
            $this->assertSame(1, OrgPositionKpi::where('position_id', $b->position_id)->where('activate', true)->count());
            $this->get('/hrm/position')->assertRedirect('/v2/s/hrm-position');
        });
    }
}
