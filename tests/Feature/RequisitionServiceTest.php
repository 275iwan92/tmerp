<?php

/*
 * REMARK
 * Tujuan       : Menjaga permohonan tenaga kerja v2: nomor RCR dari nomor terbesar (tanpa bentrok setelah draft dihapus), validasi header, draft hanya diubah / dihapus pembuat atau HR Officer, Submit ditolak tanpa langkah approval atau tanpa approver ditugaskan, approval berjenjang hanya oleh approver giliran itu dengan alasan reject wajib, layar form, route, grid, redirect v1 dan menu.
 * Sumber hulu  : App\Services\Recruitment\RequisitionService, RecruitmentApprovalService, layar hrm-rcr-requisition-form, routes/v2_hrm.php, RequisitionGrid.
 * Input        : User johiwan (admin); track, matriks, penugasan approver, requisition dan akun uji dibuat di dalam transaksi tm2hrr / tm2sys yang di-rollback, jadi data nyata tidak berubah.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=RequisitionServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Hrr\RcrApprovalMatrix;
use App\Models\Hrr\RcrApproverAssignment;
use App\Models\Hrr\RcrRequisition;
use App\Models\Hrr\RcrRequisitionApproval;
use App\Models\Hrr\RcrTrack;
use App\Models\Master\OrgDepartment;
use App\Models\Sys\SecUser;
use App\Services\Recruitment\RequisitionService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RequisitionServiceTest extends TestCase
{
    private const CONNS = ['tm2hrr', 'tm2sys'];

    private int $dept;

    private RcrTrack $track;

    private RcrTrack $noMatrix;

    private RcrTrack $unassigned;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::CONNS as $c) {
            DB::connection($c)->beginTransaction();
        }
        $this->dept = (int) OrgDepartment::where('activate', true)->orderBy('department_id')->value('department_id');
        $admin = $this->admin();
        // Track uji dengan dua langkah: ZZ A = user lain (9001), ZZ B = admin; track kedua tanpa matriks; track ketiga dengan peran tanpa approver.
        $this->track = RcrTrack::create(['code' => 'ZZT1', 'name' => 'ZZ Track', 'is_active' => true]);
        RcrApprovalMatrix::create(['id_track' => $this->track->id_track, 'approval_seq' => 1, 'approver_role' => 'ZZ A']);
        RcrApprovalMatrix::create(['id_track' => $this->track->id_track, 'approval_seq' => 2, 'approver_role' => 'ZZ B']);
        RcrApproverAssignment::create(['approver_role' => 'ZZ A', 'sec_user_id' => 9001, 'activate' => true]);
        RcrApproverAssignment::create(['approver_role' => 'ZZ B', 'sec_user_id' => $admin->getKey(), 'activate' => true]);
        $this->noMatrix = RcrTrack::create(['code' => 'ZZT2', 'name' => 'ZZ No Matrix', 'is_active' => true]);
        $this->unassigned = RcrTrack::create(['code' => 'ZZT3', 'name' => 'ZZ Unassigned', 'is_active' => true]);
        RcrApprovalMatrix::create(['id_track' => $this->unassigned->id_track, 'approval_seq' => 1, 'approver_role' => 'ZZ Nobody']);
    }

    protected function tearDown(): void
    {
        foreach (self::CONNS as $c) {
            while (DB::connection($c)->transactionLevel() > 0) {
                DB::connection($c)->rollBack();
            }
        }
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function user(int $id): SecUser
    {
        return (new SecUser)->forceFill(['id_user' => $id]);
    }

    private function plain(): SecUser
    {
        static $n = 0;
        $id = DB::connection('tm2sys')->table('sec_user')->insertGetId(['login' => 'tst_req_plain'.++$n, 'name' => 'Test plain', 'active' => 1, 'activate' => 1, 'is_admin' => 0]);

        return SecUser::findOrFail($id);
    }

    private function hr(): SecUser
    {
        $u = $this->plain();
        DB::connection('tm2sys')->table('sec_user_group')->insert(['user_id' => $u->getKey(), 'group_id' => DB::connection('tm2sys')->table('sec_group')->where('group_code', 'HR_OFFICER')->value('id_group')]);

        return SecUser::findOrFail($u->getKey());
    }

    private function in(array $over = []): array
    {
        return array_merge(['id_track' => $this->track->id_track, 'department_id' => $this->dept, 'position_title' => '  ZZ Operator  ', 'headcount' => '2', 'qualification' => 'SMK', 'reason' => 'Shift malam'], $over);
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

    public function test_save_numbering_validation_and_ownership(): void
    {
        $svc = app(RequisitionService::class);
        $admin = $this->admin();
        $before = RcrRequisition::count();
        foreach (['track unknown' => ['id_track' => 999999999], 'dept unknown' => ['department_id' => 999999999], 'blank title' => ['position_title' => ' '], 'long title' => ['position_title' => str_repeat('t', 151)],
            'headcount 0' => ['headcount' => 0], 'headcount 1000' => ['headcount' => 1000], 'headcount text' => ['headcount' => 'abc']] as $name => $over) {
            try {
                $svc->save(null, $this->in($over), $admin);
                $this->fail('harus ditolak: '.$name);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors(), $name);
            }
        }
        $this->assertSame($before, RcrRequisition::count());

        $a = $svc->save(null, $this->in(), $admin);
        $this->assertMatchesRegularExpression('/^RCR'.now()->format('y').'\d{5}$/', $a->requisition_no);
        $this->assertSame('DRAFT', $a->status);
        $this->assertSame('ZZ Operator', $a->position_title);
        $this->assertSame(2, (int) $a->headcount);
        $this->assertSame((int) $admin->getKey(), (int) $a->requested_by);
        $b = $svc->save(null, $this->in(), $admin);
        $this->assertSame((int) substr($a->requisition_no, 5) + 1, (int) substr($b->requisition_no, 5));
        $svc->delete($a, $admin);
        $c = $svc->save(null, $this->in(), $admin);
        $this->assertSame((int) substr($b->requisition_no, 5) + 1, (int) substr($c->requisition_no, 5), 'nomor berikut dari nomor terbesar, tanpa bentrok');

        $svc->save($b, $this->in(['position_title' => 'ZZ Changed']), $admin);
        $this->assertSame('ZZ Changed', $b->fresh()->position_title);

        foreach ([fn () => $svc->save($b, $this->in(), $this->plain()), fn () => $svc->delete($b, $this->plain()), fn () => $svc->submit($b, $this->plain())] as $i => $f) {
            try {
                $f();
                $this->fail('bukan pembuat dan bukan HR harus ditolak '.$i);
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
        $svc->save($b, $this->in(['position_title' => 'ZZ By HR']), $this->hr());
        $this->assertSame('ZZ By HR', $b->fresh()->position_title, 'HR Officer boleh mengubah draft orang lain');
    }

    public function test_submit_rules_and_approval_chain(): void
    {
        $svc = app(RequisitionService::class);
        $admin = $this->admin();

        $blank = $svc->save(null, $this->in(['qualification' => '', 'reason' => '']), $admin);
        $this->refused(fn () => $svc->submit($blank, $admin), 'document', 'kualifikasi / alasan kosong');
        $none = $svc->save(null, $this->in(['id_track' => $this->noMatrix->id_track]), $admin);
        $this->refused(fn () => $svc->submit($none, $admin), 'document', 'track tanpa langkah approval');
        $orphan = $svc->save(null, $this->in(['id_track' => $this->unassigned->id_track]), $admin);
        try {
            $svc->submit($orphan, $admin);
            $this->fail('peran tanpa approver harus ditolak');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('ZZ Nobody', $e->errors()['document'][0]);
        }
        $this->assertSame('DRAFT', $orphan->fresh()->status);
        $this->assertSame(0, RcrRequisitionApproval::where('id_requisition', $orphan->id_requisition)->count(), 'penolakan tidak menulis apa pun');

        $r = $svc->save(null, $this->in(), $admin);
        $svc->submit($r, $admin);
        $r->refresh();
        $this->assertSame('SUBMITTED', $r->status);
        $this->assertSame([9001, (int) $admin->getKey()], $r->approvals->pluck('approver_id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame('ZZ A', RequisitionService::steps($r)[0]['role']);
        foreach ([fn () => $svc->submit($r, $admin), fn () => $svc->save($r, $this->in(), $admin), fn () => $svc->delete($r, $admin)] as $i => $f) {
            $this->refused($f, 'document', 'bukan draft '.$i);
        }

        // Hanya approver giliran yang berhak; admin (langkah 2) belum boleh sebelum langkah 1.
        try {
            $svc->decide($r, $admin, 'APPROVED', null);
            $this->fail('bukan giliran admin');
        } catch (AuthorizationException) {
            $this->assertNull(RequisitionService::pendingFor($r->fresh(), $admin));
        }
        $this->refused(fn () => $svc->decide($r, $this->user(9001), 'REJECTED', ' '), 'reason', 'reject tanpa alasan');
        $svc->decide($r, $this->user(9001), 'APPROVED', null);
        $this->assertSame('SUBMITTED', $r->fresh()->status);
        $this->assertNotNull(RequisitionService::pendingFor($r->fresh(), $admin));
        $svc->decide($r, $admin, 'APPROVED', 'ok');
        $this->assertSame('APPROVED', $r->fresh()->status);
        $this->assertSame('Approved', $svc->history($r->fresh())[0]['action']);
        try {
            $svc->decide($r, $admin, 'APPROVED', null);
            $this->fail('sudah selesai');
        } catch (AuthorizationException) {
            $this->assertSame('APPROVED', $r->fresh()->status);
        }

        $x = $svc->save(null, $this->in(), $admin);
        $svc->submit($x, $admin);
        $svc->decide($x, $this->user(9001), 'REJECTED', 'Tidak sesuai anggaran');
        $this->assertSame('REJECTED', $x->fresh()->status);
        $this->assertSame('Tidak sesuai anggaran', RcrRequisitionApproval::where('id_requisition', $x->id_requisition)->where('approval_seq', 1)->value('note'));
    }

    public function test_screen_route_grid_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        $form = route('v2.screen', 'hrm-rcr-requisition-form');
        $this->get(route('v2.screen', 'hrm-rcr-requisition'))->assertOk();
        $this->get($form)->assertNotFound();
        $this->get($form.'?id=999999999')->assertNotFound();
        $this->get($form.'?new=1')->assertOk()->assertSee('name="position_title"', false)->assertSee('name="headcount"', false);

        $before = RcrRequisition::count();
        $this->post(route('v2.hrm.requisition.save'), $this->in(['position_title' => '']))->assertSessionHasErrors('position_title');
        $this->assertSame($before, RcrRequisition::count(), 'New tanpa Save tidak membuat draft');
        $this->post(route('v2.hrm.requisition.save'), $this->in(['reason' => '']) + ['action' => 'submit'])->assertRedirect();
        $r = RcrRequisition::where('position_title', 'ZZ Operator')->firstOrFail();
        $this->assertSame('DRAFT', $r->status, 'Submit gagal: draft tetap tersimpan');
        $open = $form.'?id='.$r->id_requisition;
        $this->get($open)->assertOk()->assertSee('name="action" value="submit"', false);
        $this->post(route('v2.hrm.requisition.save'), $this->in() + ['id' => $r->id_requisition, 'action' => 'submit'])->assertRedirect($open);
        $this->assertSame('SUBMITTED', $r->fresh()->status);
        $this->get($open)->assertOk()->assertSee('ZZ A')->assertDontSee('name="action" value="submit"', false);
        $this->post(route('v2.hrm.requisition.save'), ['id' => $r->id_requisition, 'action' => 'approve'])->assertForbidden();

        RcrRequisitionApproval::where('id_requisition', $r->id_requisition)->where('approval_seq', 1)->update(['decision' => 'APPROVED', 'decided_at' => now()]);
        $this->get($open)->assertOk()->assertSee('data-dl-preset="approve"', false);
        $this->post(route('v2.hrm.requisition.save'), ['id' => $r->id_requisition, 'action' => 'reject', '_reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('v2.hrm.requisition.save'), ['id' => $r->id_requisition, 'action' => 'approve'])->assertRedirect($open);
        $this->assertSame('APPROVED', $r->fresh()->status);

        $d = $this->post(route('v2.hrm.requisition.save'), $this->in(['position_title' => 'ZZ To Delete']))->assertRedirect();
        $del = RcrRequisition::where('position_title', 'ZZ To Delete')->firstOrFail();
        $this->post(route('v2.hrm.requisition.save'), ['id' => $del->id_requisition, 'action' => 'delete'])->assertRedirect(route('v2.screen', 'hrm-rcr-requisition'));
        $this->assertNull(RcrRequisition::find($del->id_requisition));
        $this->post(route('v2.hrm.requisition.save'), ['id' => $r->id_requisition, 'action' => 'bogus'])->assertStatus(422);

        $grid = Grids::find('hrm-rcr-requisition');
        $this->assertStringContainsString('/v2/s/hrm-rcr-requisition-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/hrm-rcr-requisition-form?new=1', $grid->newUrl());
        $this->assertFileDoesNotExist(resource_path('views/pages/recruitment/requisition/list.blade.php'));
        $this->assertFileDoesNotExist(resource_path('views/pages/recruitment/requisition/entry.blade.php'));
        $this->assertFileExists(base_path('tmerp_unused/recruitment_requisition_list_v1_2026-10-19.blade.php'));
        $this->assertFileExists(base_path('tmerp_unused/recruitment_requisition_entry_v1_2026-10-19.blade.php'));
        $this->assertArrayNotHasKey('HRM_RCR_REQUISITION', MenuRoutes::map());
        $this->get('/hrm/recruitment/requisition')->assertRedirect('/v2/s/hrm-rcr-requisition');
        $this->get('/hrm/recruitment/requisition?new=1')->assertRedirect('/v2/s/hrm-rcr-requisition-form?new=1');
        $this->get('/hrm/recruitment/requisition/7')->assertRedirect('/v2/s/hrm-rcr-requisition-form?id=7');
    }
}
