<?php

/*
 * REMARK
 * Tujuan       : Menjaga keputusan HR atas presensi GPS v2: Approve menulis scan MOBILE-GPS dan menghitung status harian, Reject wajib alasan (maks 255), hanya baris PENDING yang bisa diputuskan (dua kali putus ditolak), layar form (tombol hanya untuk PENDING), route, grid, redirect v1 dan menu.
 * Sumber hulu  : App\Services\Hrm\GpsApprovalService, layar hrm-gps-form, routes/v2_hrm.php, GpsApprovalGrid.
 * Input        : User johiwan (admin); satu pegawai non-pertama yang sudah ada (hanya dibaca). Semua baris uji dibuat di dalam transaksi tm2mst yang di-rollback (log GPS, scan, status harian), jadi tidak ada data tersisa.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=GpsApprovalServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Master\AttFingerRaw;
use App\Models\Master\AttMobileGpsLog;
use App\Models\Master\EmpEmployee;
use App\Models\Sys\SecUser;
use App\Services\Hrm\GpsApprovalService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GpsApprovalServiceTest extends TestCase
{
    private int $employee;

    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('tm2mst')->beginTransaction();
        $this->employee = (int) EmpEmployee::where('employee_id', '>', 1)->where('activate', true)->orderBy('employee_id')->value('employee_id');
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

    private function log(string $status = 'PENDING', string $type = 'IN', string $at = '2026-10-19 07:12:40'): AttMobileGpsLog
    {
        $g = new AttMobileGpsLog;
        $g->forceFill(['employee_id' => $this->employee, 'clock_type' => $type, 'latitude' => -6.9, 'longitude' => 112.7, 'distance_meters' => 180, 'geofence_status' => 'OUT_OF_RADIUS',
            'hr_approval_status' => $status, 'created_at' => $at])->save();

        return $g->fresh();
    }

    public function test_approve_writes_the_scan_and_cannot_be_decided_twice(): void
    {
        $svc = app(GpsApprovalService::class);
        $g = $this->log();
        $raw = AttFingerRaw::where('employee_id', $this->employee)->count();
        $svc->approve($g, $this->admin());
        $g->refresh();
        $this->assertSame('APPROVED', $g->hr_approval_status);
        $this->assertSame('johiwan', $g->hr_approved_by);
        $this->assertNotNull($g->hr_approved_at);
        $this->assertSame($raw + 1, AttFingerRaw::where('employee_id', $this->employee)->count());
        $scan = AttFingerRaw::where('employee_id', $this->employee)->where('raw_device_code', 'MOBILE-GPS')->orderByDesc('id_finger_raw')->first();
        $this->assertSame('IN', $scan->scan_type);
        $this->assertSame('2026-10-19 07:12:40', $scan->scan_datetime->format('Y-m-d H:i:s'));
        $this->assertSame('Approved', $svc->history($g)[0]['action']);

        foreach ([fn () => $svc->approve($g, $this->admin()), fn () => $svc->reject($g, 'late', $this->admin())] as $i => $again) {
            try {
                $again();
                $this->fail('sudah diputuskan harus ditolak: '.$i);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('document', $e->errors());
            }
        }
        $this->assertSame($raw + 1, AttFingerRaw::where('employee_id', $this->employee)->count(), 'tidak ada scan ganda');
    }

    public function test_reject_needs_a_reason_and_writes_no_scan(): void
    {
        $svc = app(GpsApprovalService::class);
        $g = $this->log('PENDING', 'OUT', '2026-10-19 17:05:00');
        $raw = AttFingerRaw::count();
        foreach (['' => 'blank', '   ' => 'spaces', str_repeat('r', 256) => 'too long'] as $reason => $name) {
            try {
                $svc->reject($g, (string) $reason, $this->admin());
                $this->fail('alasan salah harus ditolak: '.$name);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('document', $e->errors(), $name);
            }
        }
        $this->assertSame('PENDING', $g->fresh()->hr_approval_status);
        $svc->reject($g, '  Not at the factory  ', $this->admin());
        $g->refresh();
        $this->assertSame('REJECTED', $g->hr_approval_status);
        $this->assertSame('Not at the factory', $g->hr_approval_note);
        $this->assertSame($raw, AttFingerRaw::count());
        foreach (['AUTO_APPROVED', 'REJECTED', 'APPROVED'] as $st) {
            try {
                $svc->approve($this->log($st), $this->admin());
                $this->fail('bukan PENDING harus ditolak: '.$st);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('document', $e->errors());
            }
        }
    }

    public function test_screen_route_grid_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        $g = $this->log();
        $form = route('v2.screen', 'hrm-gps-form');
        $this->get(route('v2.screen', 'hrm-gps'))->assertOk();
        $this->get($form)->assertNotFound();
        $this->get($form.'?id=999999999')->assertNotFound();
        $this->get($form.'?id='.$g->id_gps_log)->assertOk()->assertSee('GPS Location Approval')->assertSee('Approve')->assertSee('Reject');

        $this->post(route('v2.hrm.gps.decide'), ['id' => $g->id_gps_log, 'action' => 'reject', '_reason' => ''])->assertSessionHasErrors('document');
        $this->post(route('v2.hrm.gps.decide'), ['id' => $g->id_gps_log, 'action' => 'hold'])->assertStatus(422);
        $this->post(route('v2.hrm.gps.decide'), ['id' => $g->id_gps_log, 'action' => 'reject', '_reason' => 'Outside the working area'])->assertRedirect($form.'?id='.$g->id_gps_log);
        $this->assertSame('REJECTED', $g->fresh()->hr_approval_status);
        $this->get($form.'?id='.$g->id_gps_log)->assertOk()->assertSee('Outside the working area');
        $this->post(route('v2.hrm.gps.decide'), ['id' => $g->id_gps_log, 'action' => 'approve'])->assertSessionHasErrors('document');

        $second = $this->log('PENDING', 'IN', '2026-10-19 07:30:00');
        $this->post(route('v2.hrm.gps.decide'), ['id' => $second->id_gps_log, 'action' => 'approve'])->assertRedirect();
        $this->assertSame('APPROVED', $second->fresh()->hr_approval_status);

        $grid = Grids::find('hrm-gps');
        $this->assertStringContainsString('/v2/s/hrm-gps-form?id={id}', $grid->openUrl());
        $this->assertNull($grid->newUrl());
        $this->assertFileDoesNotExist(resource_path('views/pages/hrm/gps-approval.blade.php'));
        $this->assertFileExists(base_path('tmerp_unused/hrm_gps_approval_v1_2026-10-19.blade.php'));
        $this->assertArrayNotHasKey('HRM_GPS_APPROVAL', MenuRoutes::map());
        $this->get('/hrm/gps-approval')->assertRedirect('/v2/s/hrm-gps');
        $this->get('/hrm/gps-approval?open=7')->assertRedirect('/v2/s/hrm-gps-form?id=7');
    }
}
