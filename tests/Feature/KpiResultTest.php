<?php

namespace Tests\Feature;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpEmployeeWorkCenterSkill;
use App\Models\Master\EmpKpiResult;
use App\Models\Master\OrgPosition;
use App\Models\Master\OrgPositionKpi;
use App\Models\Sys\SecUser;
use App\Services\Hrm\EmployeeProfileService;
use App\Services\Hrm\KpiResultService;
use App\Services\Hrm\PositionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

// Hasil KPI bulanan dan persentase kemampuan (tahap B). Semua di transaksi lalu di-rollback; angka mengikuti preview yang disetujui.
class KpiResultTest extends TestCase
{
    private function inTransaction(callable $fn): void
    {
        DB::connection('tm2mst')->beginTransaction();
        DB::connection('tm2sys')->beginTransaction();
        try {
            $admin = SecUser::query()->where('is_admin', true)->where('active', true)->firstOrFail();
            $m = DB::connection('tm2mst');
            $pos = OrgPosition::create(['position_code' => 'ZZ-KPI', 'position_name' => 'Zz KPI Position', 'department_id' => $m->table('org_department')->where('activate', true)->value('department_id'),
                'level_id' => $m->table('org_level')->where('activate', true)->value('level_id'), 'activate' => true]);
            $ps = app(PositionService::class);
            $k = [
                'output' => $ps->addKpi($pos, ['kpi_name' => 'Output', 'unit' => 'sheets', 'direction' => 'HIGHER', 'target_value' => '12000', 'weight_pct' => '40'], $admin),
                'waste' => $ps->addKpi($pos, ['kpi_name' => 'Waste', 'unit' => '%', 'direction' => 'LOWER', 'target_value' => '4', 'weight_pct' => '30'], $admin),
                'setup' => $ps->addKpi($pos, ['kpi_name' => 'Setup', 'unit' => 'min', 'direction' => 'LOWER', 'target_value' => '25', 'weight_pct' => '20'], $admin),
                'att' => $ps->addKpi($pos, ['kpi_name' => 'Attendance', 'unit' => '%', 'direction' => 'HIGHER', 'target_value' => '95', 'weight_pct' => '10'], $admin),
            ];
            [$e] = app(EmployeeProfileService::class)->save(null, ['full_name' => 'Zz Test KPI', 'staff_type' => 'NON_STAFF', 'payment_frequency' => 'MONTHLY', 'position_id' => $pos->position_id], $admin);
            $fn($e, $admin, $pos, $k, app(KpiResultService::class));
        } finally {
            DB::connection('tm2sys')->rollBack();
            DB::connection('tm2mst')->rollBack();
        }
    }

    private function enter(KpiResultService $svc, EmpEmployee $e, SecUser $u, OrgPosition $pos, array $k, string $month, array $vals, ?int $wc = null): void
    {
        $svc->save($e, $pos->position_id, $month, $wc, [$k['output']->id_position_kpi => $vals[0], $k['waste']->id_position_kpi => $vals[1], $k['setup']->id_position_kpi => $vals[2], $k['att']->id_position_kpi => $vals[3] ?? ''], null, $u);
    }

    public function test_score_formula(): void
    {
        $this->assertEqualsWithDelta(0.95, KpiResultService::score('HIGHER', 12000, 11400), 1e-9);
        $this->assertSame(1.0, KpiResultService::score('HIGHER', 12000, 15000), 'tidak pernah di atas 100%');
        $this->assertEqualsWithDelta(25 / 30, KpiResultService::score('LOWER', 25, 30), 1e-9);
        $this->assertSame(1.0, KpiResultService::score('LOWER', 4, 3.1));
        $this->assertSame(1.0, KpiResultService::score('LOWER', 4, 0), 'nol pada KPI makin kecil makin baik = 100%');
        $this->assertCount(3, KpiResultService::window());
        $this->assertSame(now()->format('Y-m'), KpiResultService::window()[2]);
    }

    public function test_capability_follows_the_approved_preview_numbers(): void
    {
        $this->inTransaction(function (EmpEmployee $e, SecUser $admin, OrgPosition $pos, array $k, KpiResultService $svc) {
            [$m0, $m1, $m2] = KpiResultService::window();
            $this->assertNull($svc->forPosition($e, $pos->position_id)['pct'], 'tanpa hasil = belum ada persen');

            $this->enter($svc, $e, $admin, $pos, $k, $m0, ['11400', '3.8', '30', '98']);
            $this->enter($svc, $e, $admin, $pos, $k, $m1, ['11800', '3.4', '28', '97']);
            $this->enter($svc, $e, $admin, $pos, $k, $m2, ['11200', '3.1', '31', '98']);

            $agg = $svc->forPosition($e, $pos->position_id);
            $out = (0.95 + 11800 / 12000 + 11200 / 12000) / 3;
            $setup = (25 / 30 + 25 / 28 + 25 / 31) / 3;
            $this->assertEqualsWithDelta((40 * $out + 30 * 1 + 20 * $setup + 10 * 1) / 100, $agg['pct'], 1e-9);
            $this->assertEqualsWithDelta(0.951, $agg['pct'], 0.0006, 'sekitar 95%');
            $this->assertEqualsWithDelta($setup, $agg['scores'][$k['setup']->id_position_kpi], 1e-9);
        });
    }

    public function test_snapshot_is_frozen_and_same_month_is_replaced(): void
    {
        $this->inTransaction(function (EmpEmployee $e, SecUser $admin, OrgPosition $pos, array $k, KpiResultService $svc) {
            $m = KpiResultService::window()[2];
            $this->enter($svc, $e, $admin, $pos, $k, $m, ['6000', '2', '20', '95']);
            $k['output']->update(['target_value' => 100, 'weight_pct' => 40]); // target diubah setelah entri
            $before = $svc->forPosition($e, $pos->position_id)['scores'][$k['output']->id_position_kpi];
            $this->assertEqualsWithDelta(0.5, $before, 1e-9, 'skor tetap pakai target beku 12000');

            $this->enter($svc, $e, $admin, $pos, $k, $m, ['9000', '2', '20', '95']);
            $this->assertSame(4, EmpKpiResult::where('employee_id', $e->employee_id)->count(), 'bulan yang sama diganti, bukan ditambah');
            $this->assertEqualsWithDelta(0.75, $svc->forPosition($e, $pos->position_id)['scores'][$k['output']->id_position_kpi], 1e-9);
            $this->assertEqualsWithDelta(12000.0, EmpKpiResult::where('position_kpi_id', $k['output']->id_position_kpi)->value('target_snapshot'), 1e-9);
        });
    }

    public function test_capability_per_machine_uses_only_tagged_results_and_renormalizes_weights(): void
    {
        $this->inTransaction(function (EmpEmployee $e, SecUser $admin, OrgPosition $pos, array $k, KpiResultService $svc) {
            $wc = (int) DB::connection('tm2mst')->table('emp_work_center')->where('activate', true)->value('work_center_id');
            [$m0, $m1, $m2] = KpiResultService::window();
            $this->enter($svc, $e, $admin, $pos, $k, $m0, ['11900', '3.5', '27'], $wc);
            $this->enter($svc, $e, $admin, $pos, $k, $m1, ['12100', '3.2', '26'], $wc);
            $this->enter($svc, $e, $admin, $pos, $k, $m2, ['11600', '3.0', '29'], $wc);
            $this->enter($svc, $e, $admin, $pos, $k, $m2, ['1000', '9', '90', '50']); // tanpa mesin: tidak ikut hitungan mesin

            $out = (11900 / 12000 + 1 + 11600 / 12000) / 3;
            $setup = (25 / 27 + 25 / 26 + 25 / 29) / 3;
            $this->assertEqualsWithDelta((40 * $out + 30 * 1 + 20 * $setup) / 90, $svc->forWorkCenter($e, $wc), 1e-9);
            $this->assertNull($svc->forWorkCenter($e, $wc + 100000), 'mesin tanpa hasil bertanda = belum ada persen');
        });
    }

    public function test_entry_rules(): void
    {
        $this->inTransaction(function (EmpEmployee $e, SecUser $admin, OrgPosition $pos, array $k, KpiResultService $svc) {
            $m = KpiResultService::window()[2];
            $id = $k['output']->id_position_kpi;
            $cases = [
                'bulan depan' => fn () => $svc->save($e, $pos->position_id, now()->addMonth()->format('Y-m'), null, [$id => '1'], null, $admin),
                'kosong semua' => fn () => $svc->save($e, $pos->position_id, $m, null, [$id => ''], null, $admin),
                'bukan angka' => fn () => $svc->save($e, $pos->position_id, $m, null, [$id => 'abc'], null, $admin),
                'negatif' => fn () => $svc->save($e, $pos->position_id, $m, null, [$id => '-5'], null, $admin),
                'KPI bukan milik jabatan' => fn () => $svc->save($e, $pos->position_id, $m, null, [99999999 => '5'], null, $admin),
                'jabatan tidak dipegang' => fn () => $svc->save($e, 99999999, $m, null, [$id => '5'], null, $admin),
            ];
            foreach ($cases as $name => $try) {
                try {
                    $try();
                    $this->fail("harus ditolak: $name");
                } catch (ValidationException) {
                    $this->addToAssertionCount(1);
                }
            }

            $k['att']->update(['weight_pct' => 5]); // total 95%
            try {
                $svc->save($e, $pos->position_id, $m, null, [$id => '5'], null, $admin);
                $this->fail('total bobot bukan 100% harus ditolak');
            } catch (ValidationException $ex) {
                $this->assertArrayHasKey('position_id', $ex->errors());
            }

            $plain = SecUser::findOrFail(DB::connection('tm2sys')->table('sec_user')->insertGetId(['login' => 'zz_kpi_plain', 'name' => 'Zz', 'active' => 1, 'activate' => 1, 'is_admin' => 0]));
            $this->expectException(HttpException::class);
            $svc->save($e, $pos->position_id, $m, null, [$id => '5'], null, $plain);
        });
    }

    public function test_tabs_route_and_screen(): void
    {
        $this->inTransaction(function (EmpEmployee $e, SecUser $admin, OrgPosition $pos, array $k) {
            $url = route('v2.screen', 'hrm-employee-form').'?id='.$e->employee_id;
            $m = KpiResultService::window()[2];
            $post = $this->actingAs($admin)->post(route('v2.hrm.employee.kpi-result', $e->employee_id), ['position_id' => $pos->position_id, 'period_month' => $m,
                'actual' => [$k['output']->id_position_kpi => '9000', $k['waste']->id_position_kpi => '3', $k['setup']->id_position_kpi => '25', $k['att']->id_position_kpi => '95']]);
            $post->assertRedirect($url.'&tab=kpi');
            $this->assertSame(4, EmpKpiResult::where('employee_id', $e->employee_id)->count());

            $wc = (int) DB::connection('tm2mst')->table('emp_work_center')->where('activate', true)->value('work_center_id');
            EmpEmployeeWorkCenterSkill::create(['employee_id' => $e->employee_id, 'work_center_id' => $wc, 'activate' => true]);

            $this->actingAs($admin)->get($url)->assertOk()->assertSee('data-tab="kpi"', false)->assertSee('data-tab="skills"', false)->assertSee('Enter Results')
                ->assertSee('Zz KPI Position')->assertSee('Capability 90%', false);
            $this->assertSame(4, OrgPositionKpi::where('position_id', $pos->position_id)->count());
        });
    }
}
