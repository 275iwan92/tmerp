<?php

/*
 * REMARK
 * Tujuan       : Menjaga penawaran kerja v2: hanya lamaran OFFERING dan satu penawaran aktif, isian divalidasi, hanya user berhak payroll yang mengisi gaji, urutan status dijaga server (tidak bisa ACCEPTED sebelum APPROVED dan SENT), Submit ditolak tanpa langkah approval atau approver, approval berjenjang hanya oleh approver giliran itu, Revise dari DECLINED, gaji tersembunyi bagi yang tak berhak, layar form (membuka layar tidak membuat penawaran), route, grid, redirect v1 dan menu.
 * Sumber hulu  : App\Services\Recruitment\OfferService, RecruitmentApprovalService, layar hrm-rcr-offer-form, routes/v2_hrm.php, OfferGrid.
 * Input        : User johiwan (admin); track, matriks, penugasan approver, requisition, lowongan, lamaran, penawaran dan akun uji dibuat di dalam transaksi tm2hrr / tm2sys yang di-rollback, jadi data nyata tidak berubah.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=OfferServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppOffer;
use App\Models\Hrr\AppOfferApproval;
use App\Models\Hrr\CndCandidate;
use App\Models\Hrr\RcrApproverAssignment;
use App\Models\Hrr\RcrOfferApprovalMatrix;
use App\Models\Hrr\RcrRequisition;
use App\Models\Hrr\RcrTrack;
use App\Models\Hrr\VacVacancy;
use App\Models\Master\OrgDepartment;
use App\Models\Sys\SecUser;
use App\Services\Payroll\PayrollAccess;
use App\Services\Recruitment\OfferService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OfferServiceTest extends TestCase
{
    private const CONNS = ['tm2hrr', 'tm2sys'];

    private CndCandidate $cand;

    private VacVacancy $vac;

    private VacVacancy $vacNoMatrix;

    private VacVacancy $vacOrphan;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::CONNS as $c) {
            DB::connection($c)->beginTransaction();
        }
        PayrollAccess::forget();
        $this->cand = CndCandidate::orderBy('id_candidate')->firstOrFail();
        $admin = $this->admin();
        $this->vac = $this->vacancyOn($this->track('ZZO1'), 'ZZV1');
        $this->vacNoMatrix = $this->vacancyOn($this->track('ZZO2'), 'ZZV2');
        $orphanTrack = $this->track('ZZO3');
        RcrOfferApprovalMatrix::create(['id_track' => $orphanTrack->id_track, 'approval_seq' => 1, 'approver_role' => 'ZZ Nobody']);
        $this->vacOrphan = $this->vacancyOn($orphanTrack, 'ZZV3');
        $main = $this->vac->requisition;
        RcrOfferApprovalMatrix::create(['id_track' => $main->id_track, 'approval_seq' => 1, 'approver_role' => 'ZZ OA']);
        RcrOfferApprovalMatrix::create(['id_track' => $main->id_track, 'approval_seq' => 2, 'approver_role' => 'ZZ OB']);
        RcrApproverAssignment::create(['approver_role' => 'ZZ OA', 'sec_user_id' => 9001, 'activate' => true]);
        RcrApproverAssignment::create(['approver_role' => 'ZZ OB', 'sec_user_id' => $admin->getKey(), 'activate' => true]);
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

    private function track(string $code): RcrTrack
    {
        return RcrTrack::create(['code' => $code, 'name' => 'ZZ '.$code, 'is_active' => true]);
    }

    private function vacancyOn(RcrTrack $t, string $code): VacVacancy
    {
        $req = RcrRequisition::create(['requisition_no' => $code, 'id_track' => $t->id_track, 'department_id' => (int) OrgDepartment::orderBy('department_id')->value('department_id'), 'position_title' => 'ZZ',
            'headcount' => 1, 'qualification' => 'x', 'reason' => 'x', 'requested_by' => 1, 'status' => 'APPROVED']);

        return VacVacancy::create(['vacancy_code' => $code, 'id_requisition' => $req->id_requisition, 'channel' => 'LAINNYA', 'title' => 'ZZ '.$code, 'is_published' => true, 'published_at' => now()])->load('requisition');
    }

    private function app(VacVacancy $v, string $status = 'OFFERING'): AppApplication
    {
        return AppApplication::create(['id_candidate' => $this->cand->id_candidate, 'id_vacancy' => $v->id_vacancy, 'current_stage_seq' => 5, 'status' => $status, 'applied_at' => now()]);
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
        $id = DB::connection('tm2sys')->table('sec_user')->insertGetId(['login' => 'tst_offer_plain', 'name' => 'Test plain', 'active' => 1, 'activate' => 1, 'is_admin' => 0]);
        PayrollAccess::forget();

        return SecUser::findOrFail($id);
    }

    private function in(array $over = []): array
    {
        return array_merge(['proposed_salary' => '5000000', 'employment_type' => 'KONTRAK', 'start_date' => now()->addDays(10)->toDateString()], $over);
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

    public function test_save_rules(): void
    {
        $svc = app(OfferService::class);
        $admin = $this->admin();
        $this->assertTrue(PayrollAccess::canProcess($admin));
        $a = $this->app($this->vac);
        $before = AppOffer::count();

        try {
            $svc->save($a, null, $this->in(), $this->plain());
            $this->fail('user tanpa hak payroll harus ditolak');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
        foreach (['salary 0' => ['proposed_salary' => 0], 'salary negative' => ['proposed_salary' => -5], 'salary text' => ['proposed_salary' => 'abc'], 'salary huge' => ['proposed_salary' => 99999999999],
            'bad type' => ['employment_type' => 'XX'], 'past date' => ['start_date' => now()->subDay()->toDateString()], 'bad date' => ['start_date' => '31-12-2026']] as $name => $over) {
            try {
                $svc->save($a, null, $this->in($over), $admin);
                $this->fail('harus ditolak: '.$name);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors(), $name);
            }
        }
        $this->refused(fn () => $svc->save($this->app($this->vac, 'APPLIED'), null, $this->in(), $admin), 'document', 'lamaran bukan OFFERING');
        $this->assertSame($before, AppOffer::count(), 'penolakan tidak menulis apa pun');

        $o = $svc->save($a, null, $this->in(), $admin);
        $this->assertSame('DRAFT', $o->status);
        $this->assertSame(5000000.0, (float) $o->proposed_salary);
        $this->assertSame((int) $admin->getKey(), (int) $o->created_by);
        $this->refused(fn () => $svc->save($a, null, $this->in(), $admin), 'document', 'penawaran aktif sudah ada');
        $svc->save($a, $o, $this->in(['proposed_salary' => '6000000']), $admin);
        $this->assertSame(6000000.0, (float) $o->fresh()->proposed_salary);
    }

    public function test_submit_rules_decisions_and_status_order(): void
    {
        $svc = app(OfferService::class);
        $admin = $this->admin();

        $none = $svc->save($this->app($this->vacNoMatrix), null, $this->in(), $admin);
        $this->refused(fn () => $svc->submit($none), 'document', 'track tanpa langkah approval');
        $orphan = $svc->save($this->app($this->vacOrphan), null, $this->in(), $admin);
        try {
            $svc->submit($orphan);
            $this->fail('peran tanpa approver harus ditolak');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('ZZ Nobody', $e->errors()['document'][0]);
        }
        $this->assertSame(0, AppOfferApproval::where('id_offer', $orphan->id_offer)->count());

        $a = $this->app($this->vac);
        $o = $svc->save($a, null, $this->in(), $admin);
        // Belum disetujui: tidak boleh dikirim / diterima / ditolak kandidat (v1 membolehkan ACCEPTED dari DRAFT).
        foreach (['send', 'accepted', 'declined'] as $m) {
            $this->refused(fn () => $svc->$m($o), 'document', $m.' dari DRAFT');
        }
        $svc->submit($o);
        $o->refresh();
        $this->assertSame('PENDING_APPROVAL', $o->status);
        $this->assertSame([9001, (int) $admin->getKey()], AppOfferApproval::where('id_offer', $o->id_offer)->orderBy('approval_seq')->pluck('approver_id')->map(fn ($v) => (int) $v)->all());
        $this->refused(fn () => $svc->submit($o), 'document', 'submit ganda');
        $this->refused(fn () => $svc->save($a, $o, $this->in(), $admin), 'document', 'ubah setelah submit');
        foreach (['send', 'accepted'] as $m) {
            $this->refused(fn () => $svc->$m($o), 'document', $m.' dari PENDING_APPROVAL');
        }

        try {
            $svc->decide($o, $admin, 'APPROVED');
            $this->fail('bukan giliran admin');
        } catch (AuthorizationException) {
            $this->assertNull(OfferService::pendingFor($o->fresh(), $admin));
        }
        $svc->decide($o, $this->user(9001), 'APPROVED');
        $this->assertSame('PENDING_APPROVAL', $o->fresh()->status);
        $svc->decide($o, $admin, 'APPROVED');
        $this->assertSame('APPROVED', $o->fresh()->status);
        $this->assertSame('Approved', $svc->history($o->fresh())[0]['action']);
        $this->refused(fn () => $svc->accepted($o), 'document', 'accepted sebelum SENT');
        $svc->send($o);
        $this->assertSame('SENT', $o->fresh()->status);
        $svc->accepted($o);
        $this->assertSame('ACCEPTED', $o->fresh()->status);
        $this->refused(fn () => $svc->declined($o), 'document', 'declined setelah ACCEPTED');
        $this->refused(fn () => $svc->revise($o, $admin), 'document', 'revise dari ACCEPTED');
    }

    public function test_revision_rounds_and_visibility(): void
    {
        $svc = app(OfferService::class);
        $admin = $this->admin();
        $a = $this->app($this->vac);
        $o = $svc->save($a, null, $this->in(['start_date' => now()->addDays(1)->toDateString()]), $admin);
        $svc->submit($o);
        $svc->decide($o, $this->user(9001), 'REJECTED');
        $n = $svc->revise($o->fresh(), $admin);
        $this->assertSame('DRAFT', $n->status);
        $this->assertSame((int) $o->id_offer, (int) $n->previous_offer_id);
        $this->assertSame(5000000.0, (float) $n->proposed_salary);
        $this->refused(fn () => $svc->revise($o->fresh(), $admin), 'document', 'putaran lama sudah digantikan');
        $this->refused(fn () => $svc->save($a, $o->fresh(), $this->in(), $admin), 'document', 'putaran lama bukan terbaru');

        $rounds = OfferService::rounds($a->fresh(), $admin);
        $this->assertCount(2, $rounds);
        $this->assertSame(['2', 'DRAFT'], [$rounds[0]['round'], $rounds[0]['status']]);
        $this->assertSame('5,000,000.00', $rounds[0]['salary']);
        $plain = $this->plain();
        $this->assertFalse(OfferService::canSeeSalary($n->load('approvals'), $plain));
        $this->assertSame('—', OfferService::rounds($a->fresh(), $plain)[0]['salary']);
        $this->assertTrue(OfferService::canSeeSalary($o->fresh()->load('approvals'), $this->user(9001)), 'approver penawaran melihat gaji');
        $this->assertFalse(OfferService::canSeeSalary($n->load('approvals'), $this->user(9001)), 'putaran baru belum punya approver');
    }

    public function test_screen_route_grid_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        $form = route('v2.screen', 'hrm-rcr-offer-form');
        $a = $this->app($this->vac);
        $this->get(route('v2.screen', 'hrm-rcr-offer'))->assertOk();
        $this->get($form)->assertNotFound();
        $this->get($form.'?application=999999999')->assertNotFound();
        $this->get($form.'?application='.$this->app($this->vac, 'APPLIED')->id_application)->assertNotFound();

        $before = AppOffer::count();
        $this->get($form.'?application='.$a->id_application)->assertOk()->assertSee('name="proposed_salary"', false);
        $this->assertSame($before, AppOffer::count(), 'membuka layar tidak membuat penawaran');

        $save = route('v2.hrm.offer.save');
        $this->post($save, ['application' => $a->id_application, 'proposed_salary' => '0', 'employment_type' => 'KONTRAK', 'start_date' => now()->addDay()->toDateString()])->assertSessionHasErrors('proposed_salary');
        $this->post($save, ['application' => $a->id_application, 'proposed_salary' => '4500000', 'employment_type' => 'KONTRAK', 'start_date' => now()->addDays(5)->toDateString(), 'action' => 'submit'])->assertRedirect($form.'?application='.$a->id_application);
        $o = AppOffer::where('id_application', $a->id_application)->firstOrFail();
        $this->assertSame('PENDING_APPROVAL', $o->status);
        $this->get($form.'?application='.$a->id_application)->assertOk()->assertSee('ZZ OA');
        $this->post($save, ['application' => $a->id_application, 'id' => $o->id_offer, 'action' => 'approve'])->assertForbidden();
        $this->post($save, ['application' => $a->id_application, 'id' => $o->id_offer, 'action' => 'accepted'])->assertSessionHasErrors('document');
        $this->post($save, ['application' => $a->id_application, 'id' => $o->id_offer, 'action' => 'bogus'])->assertStatus(422);
        AppOfferApproval::where('id_offer', $o->id_offer)->where('approval_seq', 1)->update(['decision' => 'APPROVED', 'decided_at' => now()]);
        $this->get($form.'?application='.$a->id_application)->assertOk()->assertSee('name="action" value="approve"', false);
        $this->post($save, ['application' => $a->id_application, 'id' => $o->id_offer, 'action' => 'approve'])->assertRedirect();
        $this->post($save, ['application' => $a->id_application, 'id' => $o->id_offer, 'action' => 'send'])->assertRedirect();
        $this->post($save, ['application' => $a->id_application, 'id' => $o->id_offer, 'action' => 'accepted'])->assertRedirect();
        $this->assertSame('ACCEPTED', $o->fresh()->status);
        $this->get($form.'?application='.$a->id_application)->assertOk()->assertSee('hrm-rcr-provision-form');

        $grid = Grids::find('hrm-rcr-offer');
        $this->assertStringContainsString('/v2/s/hrm-rcr-offer-form?application={id}', $grid->openUrl());
        $this->assertNull($grid->newUrl());
        foreach (['list', 'entry'] as $f) {
            $this->assertFileDoesNotExist(resource_path('views/pages/recruitment/offer/'.$f.'.blade.php'));
            $this->assertArchived('recruitment_offer_'.$f.'_v1_2026-10-20.blade.php');
        }
        $this->assertArrayNotHasKey('HRM_RCR_OFFER', MenuRoutes::map());
        $this->get('/hrm/recruitment/offer')->assertRedirect('/v2/s/hrm-rcr-offer');
        $this->get('/hrm/recruitment/offer/7')->assertRedirect('/v2/s/hrm-rcr-offer-form?application=7');
    }
}
