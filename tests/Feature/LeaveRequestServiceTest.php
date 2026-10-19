<?php

/*
 * REMARK
 * Tujuan       : Menjaga keputusan HR atas pengajuan cuti / sakit / izin v2: Approve menambah used_days hanya untuk yang memotong saldo (saldo dibuat bila belum ada, tidak terpotong dua kali), Reject wajib alasan (maks 255), notifikasi ke pegawai, hanya PENDING yang bisa diputuskan, layar form dengan kartu saldo, route, grid, redirect v1 dan menu.
 * Sumber hulu  : App\Services\Hrm\LeaveRequestService, layar hrm-leave-form, routes/v2_hrm.php, LeaveRequestGrid.
 * Input        : User johiwan (admin); pegawai uji dan pengajuan dibuat di dalam transaksi tm2mst yang di-rollback; notifikasi tm2sys dihapus per id. Pengajuan nyata (13 menunggu) tidak disentuh.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=LeaveRequestServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpLeaveBalance;
use App\Models\Master\EmpLeaveRequest;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysNotification;
use App\Services\Hrm\LeaveRequestService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LeaveRequestServiceTest extends TestCase
{
    private int $company;

    private array $notifyUsers = [];

    private int $notifyFrom = 0;

    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('tm2mst')->beginTransaction();
        $this->company = (int) DB::connection('tm2mst')->table('org_company')->value('company_id');
        $this->notifyFrom = (int) SysNotification::max('id');
    }

    protected function tearDown(): void
    {
        while (DB::connection('tm2mst')->transactionLevel() > 0) {
            DB::connection('tm2mst')->rollBack();
        }
        SysNotification::where('id', '>', $this->notifyFrom)->whereIn('user_id', $this->notifyUsers)->delete();
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function employee(): EmpEmployee
    {
        return EmpEmployee::create(['employee_code' => 'ZZ-L'.substr(md5(microtime()), 0, 8), 'company_id' => $this->company, 'full_name' => 'ZZ Leave', 'created_by' => 'test', 'updated_by' => 'test']);
    }

    private function leave(EmpEmployee $e, string $type = 'CUTI', bool $deducts = true, int $days = 2, string $from = '2026-11-02'): EmpLeaveRequest
    {
        return EmpLeaveRequest::create(['employee_id' => $e->employee_id, 'leave_type' => $type, 'date_from' => $from, 'date_to' => date('Y-m-d', strtotime($from.' +'.($days - 1).' day')), 'total_days' => $days,
            'reason' => 'Acara keluarga', 'deducts_leave_balance' => $deducts, 'is_paid' => $deducts, 'status' => 'PENDING', 'requested_at' => now()]);
    }

    public function test_approve_deducts_the_balance_only_when_the_request_deducts_it(): void
    {
        $svc = app(LeaveRequestService::class);
        $e = $this->employee();

        $cuti = $this->leave($e);
        $this->assertNull(LeaveRequestService::balance($cuti));
        $svc->approve($cuti, $this->admin());
        $cuti->refresh();
        $this->assertSame('APPROVED', $cuti->status);
        $this->assertSame('johiwan', $cuti->reviewed_by);
        $bal = LeaveRequestService::balance($cuti);
        $this->assertSame(12, $bal->entitlement_days, 'saldo dibuat dengan bawaan 12');
        $this->assertSame(2, $bal->used_days);
        $this->assertSame(10, $bal->remainingDays());

        $svc->approve($this->leave($e, 'CUTI', true, 3, '2026-11-09'), $this->admin());
        $this->assertSame(5, LeaveRequestService::balance($cuti)->used_days);

        foreach ([['SAKIT', false], ['IZIN', false], ['CUTI_KHUSUS', false]] as [$type, $deducts]) {
            $svc->approve($this->leave($e, $type, $deducts, 1, '2026-11-16'), $this->admin());
        }
        $this->assertSame(5, LeaveRequestService::balance($cuti)->used_days, 'Sakit, Izin dan Cuti Khusus tidak memotong saldo');
        $this->assertSame(1, EmpLeaveBalance::where('employee_id', $e->employee_id)->where('year', 2026)->count());

        $other = $this->leave($e, 'CUTI', true, 1, '2027-01-04');
        $svc->approve($other, $this->admin());
        $this->assertSame(1, EmpLeaveBalance::where('employee_id', $e->employee_id)->where('year', 2027)->value('used_days'), 'saldo per tahun awal cuti');

        foreach ([fn () => $svc->approve($cuti, $this->admin()), fn () => $svc->reject($cuti, 'late', $this->admin())] as $i => $again) {
            try {
                $again();
                $this->fail('sudah diputuskan harus ditolak: '.$i);
            } catch (ValidationException $ex) {
                $this->assertArrayHasKey('document', $ex->errors());
            }
        }
        $this->assertSame(5, LeaveRequestService::balance($cuti)->used_days, 'tidak terpotong dua kali');
        $this->assertSame('Approved', $svc->history($cuti)[0]['action']);
    }

    public function test_reject_needs_a_reason_leaves_the_balance_and_notifies(): void
    {
        $svc = app(LeaveRequestService::class);
        $owner = SecUser::whereNotNull('employee_id')->where('employee_id', '>', 1)->firstOrFail();
        $this->notifyUsers[] = $owner->login;
        $e = EmpEmployee::findOrFail($owner->employee_id);
        $r = $this->leave($e);
        foreach (['' => 'blank', '   ' => 'spaces', str_repeat('r', 256) => 'too long'] as $reason => $name) {
            try {
                $svc->reject($r, (string) $reason, $this->admin());
                $this->fail('alasan salah harus ditolak: '.$name);
            } catch (ValidationException $ex) {
                $this->assertArrayHasKey('document', $ex->errors(), $name);
            }
        }
        $this->assertSame('PENDING', $r->fresh()->status);
        $before = SysNotification::where('user_id', $owner->login)->count();
        $svc->reject($r, '  Kuota tim penuh  ', $this->admin());
        $r->refresh();
        $this->assertSame('REJECTED', $r->status);
        $this->assertSame('Kuota tim penuh', $r->review_note);
        $this->assertNull(LeaveRequestService::balance($r), 'Reject tidak menyentuh saldo');
        $this->assertSame($before + 1, SysNotification::where('user_id', $owner->login)->count());
        $this->assertStringContainsString('Kuota tim penuh', SysNotification::where('user_id', $owner->login)->orderByDesc('id')->value('message'));

        $svc->approve($this->leave($e, 'IZIN', false, 1, '2026-12-01'), $this->admin());
        $this->assertSame($before + 2, SysNotification::where('user_id', $owner->login)->count(), 'Approve juga memberi notifikasi');
    }

    public function test_screen_route_grid_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        $e = $this->employee();
        $r = $this->leave($e);
        $form = route('v2.screen', 'hrm-leave-form');
        $this->get(route('v2.screen', 'hrm-leave'))->assertOk();
        $this->get($form)->assertNotFound();
        $this->get($form.'?id=999999999')->assertNotFound();
        $this->get($form.'?id='.$r->request_id)->assertOk()->assertSee('Leave / Sick / Permit Approval')->assertSee('Acara keluarga')->assertSee('Approve')->assertSee('Reject');

        $this->post(route('v2.hrm.leave.decide'), ['id' => $r->request_id, 'action' => 'reject', '_reason' => ''])->assertSessionHasErrors('document');
        $this->post(route('v2.hrm.leave.decide'), ['id' => $r->request_id, 'action' => 'hold'])->assertStatus(422);
        $this->post(route('v2.hrm.leave.decide'), ['id' => $r->request_id, 'action' => 'approve'])->assertRedirect($form.'?id='.$r->request_id);
        $this->assertSame(2, LeaveRequestService::balance($r)->used_days);
        $this->post(route('v2.hrm.leave.decide'), ['id' => $r->request_id, 'action' => 'approve'])->assertSessionHasErrors('document');
        $this->get($form.'?id='.$r->request_id)->assertOk()->assertSee('Remaining after approve');

        $grid = Grids::find('hrm-leave');
        $this->assertStringContainsString('/v2/s/hrm-leave-form?id={id}', $grid->openUrl());
        $this->assertNull($grid->newUrl());
        $this->assertFileDoesNotExist(resource_path('views/pages/hrm/leave-requests.blade.php'));
        $this->assertFileExists(base_path('tmerp_unused/hrm_leave_requests_v1_2026-10-19.blade.php'));
        $this->assertArrayNotHasKey('HRM_LEAVE_APPROVAL', MenuRoutes::map());
        $this->get('/hrm/leave-requests')->assertRedirect('/v2/s/hrm-leave');
        $this->get('/hrm/leave-requests?open=5')->assertRedirect('/v2/s/hrm-leave-form?id=5');
    }
}
