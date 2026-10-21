<?php

/*
 * REMARK
 * Tujuan       : Menjaga koreksi presensi harian v2: override status (status valid, alasan 10 sampai 255 karakter, sumber MANUAL_OVERRIDE + override_by), hitung ulang tidak menimpa override manual, "Recalculate this day" melepas override, hitung ulang rentang (validasi tanggal dan batas 92 hari, jumlah override yang dipertahankan), baris AUTO tetap dihitung ulang dan bersih dari sisa override, layar form, route, kartu di hrm-attendance-upload, grid, redirect v1 dan menu.
 * Sumber hulu  : App\Services\Hrm\AttendanceOverrideService, AttendanceCalculationService, layar hrm-attendance-form, routes/v2_hrm.php, AttendanceGrid.
 * Input        : User johiwan (admin); pegawai uji dan baris presensi dibuat di dalam transaksi tm2mst yang di-rollback, jadi 1.133 baris presensi nyata tidak berubah.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=AttendanceOverrideServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Master\AttDailyStatus;
use App\Models\Master\EmpEmployee;
use App\Models\Sys\SecUser;
use App\Services\Hrm\AttendanceCalculationService;
use App\Services\Hrm\AttendanceOverrideService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttendanceOverrideServiceTest extends TestCase
{
    private int $company;

    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('tm2mst')->beginTransaction();
        $this->company = (int) DB::connection('tm2mst')->table('org_company')->value('company_id');
    }

    protected function tearDown(): void
    {
        while (DB::connection('tm2mst')->transactionLevel() > 0) {
            DB::connection('tm2mst')->rollBack();
        }
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function day(string $date = '2026-06-10', string $status = 'H', string $source = 'AUTO'): AttDailyStatus
    {
        $e = EmpEmployee::create(['employee_code' => 'ZZ-A'.substr(md5(microtime().$date), 0, 8), 'company_id' => $this->company, 'full_name' => 'ZZ Attendance', 'created_by' => 'test', 'updated_by' => 'test']);

        return AttDailyStatus::create(['employee_id' => $e->employee_id, 'attendance_date' => $date, 'status_code' => $status, 'source' => $source, 'override_by' => $source === 'AUTO' ? 'stale' : 'johiwan', 'override_reason' => $source === 'AUTO' ? 'stale reason' : 'FDT form 123456'])->fresh();
    }

    public function test_override_rules_and_the_manual_status_survives_recalculation(): void
    {
        $svc = app(AttendanceOverrideService::class);
        $calc = app(AttendanceCalculationService::class);
        $d = $this->day('2026-06-10', 'A', 'AUTO');

        foreach ([['XX', 'FDT form 123456'], ['H', ''], ['H', 'short'], ['H', str_repeat('r', 256)]] as $i => [$status, $reason]) {
            try {
                $svc->override($d, $status, $reason, $this->admin());
                $this->fail('harus ditolak: '.$i);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('document', $e->errors());
            }
        }
        $this->assertSame('A', $d->fresh()->status_code);

        $svc->override($d, 'IS', '  FDT form no. 7788  ', $this->admin());
        $d->refresh();
        $this->assertSame('IS', $d->status_code);
        $this->assertSame('MANUAL_OVERRIDE', $d->source);
        $this->assertSame('johiwan', $d->override_by);
        $this->assertSame('FDT form no. 7788', $d->override_reason);
        $this->assertSame('Manual override', $svc->history($d)[0]['action']);

        $again = $calc->calculateForDate((int) $d->employee_id, Carbon::parse('2026-06-10'), Carbon::parse('2026-06-08'));
        $this->assertSame('IS', $again->status_code, 'hitung ulang tidak menimpa override manual');
        $this->assertSame('MANUAL_OVERRIDE', $again->fresh()->source);

        $svc->recalculateDay($d);
        $d->refresh();
        $this->assertSame('AUTO', $d->source, 'Recalculate this day melepas override');
        $this->assertNull($d->override_by);
        $this->assertNull($d->override_reason);
        $this->assertNotSame('IS', $d->status_code);
    }

    public function test_auto_rows_are_recalculated_and_lose_stale_override_columns(): void
    {
        $calc = app(AttendanceCalculationService::class);
        $d = $this->day('2026-06-11', 'H', 'AUTO');
        $calc->calculateForDate((int) $d->employee_id, Carbon::parse('2026-06-11'), Carbon::parse('2026-06-08'));
        $d->refresh();
        $this->assertSame('AUTO', $d->source);
        $this->assertNull($d->override_by);
        $this->assertNull($d->override_reason);
    }

    public function test_range_recalculation_validates_and_reports_kept_overrides(): void
    {
        $svc = app(AttendanceOverrideService::class);
        foreach ([['', ''], ['2026-06-12', '2026-06-10'], ['2026-01-01', '2026-06-30'], ['abc', '2026-06-10']] as $i => [$from, $to]) {
            try {
                $svc->recalculateRange($from, $to);
                $this->fail('rentang salah harus ditolak: '.$i);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('document', $e->errors());
            }
        }
        $manual = $this->day('2026-06-12', 'I', 'MANUAL_OVERRIDE');
        $before = AttDailyStatus::where('source', 'MANUAL_OVERRIDE')->whereBetween('attendance_date', ['2026-06-12', '2026-06-12'])->count();
        $res = $svc->recalculateRange('2026-06-12', '2026-06-12');
        $this->assertGreaterThan(0, $res['employees']);
        $this->assertSame($res['employees'], $res['days']);
        $this->assertSame($before, $res['kept']);
        $this->assertSame('I', $manual->fresh()->status_code, 'override manual dipertahankan');
        $this->assertSame('MANUAL_OVERRIDE', $manual->fresh()->source);
    }

    public function test_screens_routes_grid_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        $d = $this->day('2026-06-15', 'A', 'AUTO');
        $form = route('v2.screen', 'hrm-attendance-form');
        $this->get(route('v2.screen', 'hrm-attendance'))->assertOk();
        $this->get($form)->assertNotFound();
        $this->get($form.'?id=999999999')->assertNotFound();
        $this->get($form.'?id='.$d->id_daily)->assertOk()->assertSee('Daily Attendance')->assertSee('Recalculate this day')->assertSee('name="override_reason"', false);
        $this->get(route('v2.screen', 'hrm-attendance-upload'))->assertOk()->assertSee('Recalculate Attendance');

        $this->post(route('v2.hrm.attendance.save'), ['id' => $d->id_daily, 'status_code' => 'S', 'override_reason' => 'short'])->assertSessionHasErrors('document');
        $this->post(route('v2.hrm.attendance.save'), ['id' => $d->id_daily, 'status_code' => 'S', 'override_reason' => 'Surat dokter no. 55'])->assertRedirect($form.'?id='.$d->id_daily);
        $this->assertSame('S', $d->fresh()->status_code);
        $this->get($form.'?id='.$d->id_daily)->assertOk()->assertSee('Surat dokter no. 55');
        $this->post(route('v2.hrm.attendance.save'), ['id' => $d->id_daily, 'action' => 'recalculate'])->assertRedirect($form.'?id='.$d->id_daily);
        $this->assertSame('AUTO', $d->fresh()->source);

        $this->post(route('v2.hrm.attendance.recalculate'), ['from' => '2026-06-15', 'to' => '2026-06-14'])->assertSessionHasErrors('document');
        $this->post(route('v2.hrm.attendance.recalculate'), ['from' => '2026-06-15', 'to' => '2026-06-15'])->assertRedirect(route('v2.screen', 'hrm-attendance-upload'));

        $grid = Grids::find('hrm-attendance');
        $this->assertStringContainsString('/v2/s/hrm-attendance-form?id={id}', $grid->openUrl());
        $this->assertNull($grid->newUrl());
        $this->assertFileDoesNotExist(resource_path('views/pages/hrm/attendance-daily.blade.php'));
        $this->assertArchived('hrm_attendance_daily_v1_2026-10-19.blade.php');
        $this->assertArrayNotHasKey('HRM_ATTENDANCE', MenuRoutes::map());
        $this->get('/hrm/attendance-daily')->assertRedirect('/v2/s/hrm-attendance');
        $this->get('/hrm/attendance-daily?open=1000')->assertRedirect('/v2/s/hrm-attendance-form?id=1000');
    }
}
