<?php

/*
 * REMARK
 * Tujuan       : Menjaga undangan Talent Pool v2: Invite membuat satu lamaran APPLIED tahap 1 untuk lowongan terbuka, menolak lowongan tertutup / belum terbit / tidak ada dan lamaran aktif ganda (lamaran lama yang sudah REJECTED tidak menghalangi), lookup lowongan terbuka, layar form, route, grid, redirect v1 dan menu.
 * Sumber hulu  : App\Services\Recruitment\RecruitmentInviteService, layar rcr-talent-pool-form, routes/v2_hrm.php, TalentPoolGrid.
 * Input        : User johiwan (admin); lowongan dan lamaran uji dibuat di dalam transaksi tm2hrr yang di-rollback, jadi data nyata tidak berubah.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=RecruitmentInviteServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\CndCandidate;
use App\Models\Hrr\VacVacancy;
use App\Models\Sys\SecUser;
use App\Services\Recruitment\RecruitmentInviteService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RecruitmentInviteServiceTest extends TestCase
{
    private CndCandidate $cand;

    private int $reqId;

    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('tm2hrr')->beginTransaction();
        $this->cand = CndCandidate::orderBy('id_candidate')->firstOrFail();
        $this->reqId = (int) VacVacancy::orderBy('id_vacancy')->value('id_requisition');
    }

    protected function tearDown(): void
    {
        while (DB::connection('tm2hrr')->transactionLevel() > 0) {
            DB::connection('tm2hrr')->rollBack();
        }
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function vac(string $code, bool $published = true, bool $closed = false): VacVacancy
    {
        return VacVacancy::create(['vacancy_code' => $code, 'id_requisition' => $this->reqId, 'channel' => 'LAINNYA', 'title' => 'ZZ '.$code,
            'is_published' => $published, 'published_at' => $published ? now() : null, 'closed_at' => $closed ? now() : null]);
    }

    private function refused(callable $f, string $why): void
    {
        try {
            $f();
            $this->fail('harus ditolak: '.$why);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('id_vacancy', $e->errors(), $why);
        }
    }

    public function test_invite_rules(): void
    {
        $svc = app(RecruitmentInviteService::class);
        $open = $this->vac('ZZOPEN01');
        $before = AppApplication::count();

        $this->refused(fn () => $svc->invite($this->cand, ''), 'kosong');
        $this->refused(fn () => $svc->invite($this->cand, 999999999), 'tidak ada');
        $this->refused(fn () => $svc->invite($this->cand, $this->vac('ZZDRAFT01', false)->id_vacancy), 'belum terbit');
        $this->refused(fn () => $svc->invite($this->cand, $this->vac('ZZCLOSE01', true, true)->id_vacancy), 'sudah ditutup');
        $this->assertSame($before, AppApplication::count(), 'penolakan tidak menulis apa pun');

        $a = $svc->invite($this->cand, $open->id_vacancy);
        $this->assertSame($before + 1, AppApplication::count());
        $this->assertSame('APPLIED', $a->status);
        $this->assertSame(1, (int) $a->current_stage_seq);
        $this->assertNotNull($a->applied_at);
        $this->assertSame((int) $this->cand->id_candidate, (int) $a->id_candidate);
        $this->assertNull($a->submission_uuid);

        $this->refused(fn () => $svc->invite($this->cand, $open->id_vacancy), 'lamaran aktif ganda');
        $this->assertSame($before + 1, AppApplication::count());

        // Lamaran lama yang sudah selesai tidak menghalangi undangan baru.
        $a->update(['status' => 'REJECTED']);
        $again = $svc->invite($this->cand, $open->id_vacancy);
        $this->assertNotSame($a->id_application, $again->id_application);
        $this->assertSame('Applied', $svc->history($this->cand)[0]['action']);
    }

    public function test_lookup_only_open_vacancies(): void
    {
        $open = $this->vac('ZZLKOPEN');
        $this->vac('ZZLKCLOSE', true, true);
        $this->vac('ZZLKDRAFT', false);
        $codes = array_column(RecruitmentInviteService::vacancySearch('ZZLK'), 'code');
        $this->assertSame(['ZZLKOPEN'], $codes);
        $this->assertSame((int) $open->id_vacancy, RecruitmentInviteService::vacancyFind($open->id_vacancy)['id']);
    }

    public function test_screen_route_grid_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        $form = route('v2.screen', 'rcr-talent-pool-form');
        $open = $this->vac('ZZSCREEN');
        $this->get(route('v2.screen', 'rcr-talent-pool'))->assertOk();
        $this->get($form)->assertNotFound();
        $this->get($form.'?id=999999999')->assertNotFound();
        $this->get($form.'?id='.$this->cand->id_candidate)->assertOk()->assertSee('name="id_vacancy"', false)->assertSee('Invite to vacancy');

        $before = AppApplication::count();
        $this->post(route('v2.hrm.talentpool.invite'), ['id' => $this->cand->id_candidate])->assertSessionHasErrors('id_vacancy');
        $this->post(route('v2.hrm.talentpool.invite'), ['id' => $this->cand->id_candidate, 'id_vacancy' => $open->id_vacancy])->assertRedirect($form.'?id='.$this->cand->id_candidate);
        $this->assertSame($before + 1, AppApplication::count());
        $this->post(route('v2.hrm.talentpool.invite'), ['id' => $this->cand->id_candidate, 'id_vacancy' => $open->id_vacancy])->assertSessionHasErrors('id_vacancy');
        $this->post(route('v2.hrm.talentpool.invite'), ['id' => 999999999, 'id_vacancy' => $open->id_vacancy])->assertNotFound();
        $this->assertSame($before + 1, AppApplication::count());

        $this->assertStringContainsString('/v2/s/rcr-talent-pool-form?id={id}', Grids::find('rcr-talent-pool')->openUrl());
        $this->assertNull(Grids::find('rcr-talent-pool')->newUrl());
        $this->assertFileDoesNotExist(resource_path('views/pages/recruitment/talent-pool.blade.php'));
        $this->assertArchived('recruitment_talent_pool_v1_2026-10-19.blade.php');
        $this->assertArrayNotHasKey('HRM_RCR_TALENTPOOL', MenuRoutes::map());
        $this->get('/hrm/recruitment/talent-pool')->assertRedirect('/v2/s/rcr-talent-pool');
        $this->get('/hrm/recruitment/talent-pool?open=7')->assertRedirect('/v2/s/rcr-talent-pool-form?id=7');
    }
}
