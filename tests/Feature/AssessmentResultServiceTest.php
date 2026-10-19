<?php

/*
 * REMARK
 * Tujuan       : Menjaga hasil Psikotes / Interview v2: hanya lamaran IN_PROGRESS di tahap penilaian tanpa hasil yang bisa dicatat (tahap dari server, tidak ganda), skor 0-100, recommendation dari daftar, notes wajib, lookup antrean, layar form, route, grid (badge Consider), redirect v1 dan menu.
 * Sumber hulu  : App\Services\Recruitment\AssessmentResultService, layar hrm-rcr-assessment-form, routes/v2_hrm.php, AssessmentGrid.
 * Input        : User johiwan (admin); lamaran dan hasil uji dibuat di dalam transaksi tm2hrr yang di-rollback, jadi data nyata tidak berubah.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=AssessmentResultServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppAssessmentResult;
use App\Models\Hrr\AppPipelineStageConfig;
use App\Models\Hrr\CndCandidate;
use App\Models\Hrr\VacVacancy;
use App\Models\Sys\SecUser;
use App\Services\Recruitment\AssessmentResultService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AssessmentResultServiceTest extends TestCase
{
    private CndCandidate $cand;

    private VacVacancy $vac;

    private int $track;

    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('tm2hrr')->beginTransaction();
        $this->cand = CndCandidate::orderBy('id_candidate')->firstOrFail();
        $this->vac = VacVacancy::with('requisition')->orderBy('id_vacancy')->firstOrFail();
        $this->track = (int) $this->vac->requisition->id_track;
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

    private function app(string $stageCode, string $status = 'IN_PROGRESS'): AppApplication
    {
        $seq = (int) AppPipelineStageConfig::where('id_track', $this->track)->where('stage_code', $stageCode)->value('stage_seq');
        $this->assertGreaterThan(0, $seq, 'track uji punya tahap '.$stageCode);

        return AppApplication::create(['id_candidate' => $this->cand->id_candidate, 'id_vacancy' => $this->vac->id_vacancy, 'current_stage_seq' => $seq, 'status' => $status, 'applied_at' => now()]);
    }

    private function in(AppApplication $a, array $over = []): array
    {
        return array_merge(['id_application' => $a->id_application, 'recommendation' => 'LANJUT', 'score' => '85', 'notes' => '  Hasil baik  '], $over);
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

    public function test_record_rules(): void
    {
        $svc = app(AssessmentResultService::class);
        $a = $this->app('PSIKOTES');
        $before = AppAssessmentResult::count();

        $this->refused(fn () => $svc->record($this->in($a, ['notes' => ' ']), 1), 'notes', 'notes kosong');
        $this->refused(fn () => $svc->record($this->in($a, ['recommendation' => 'XX']), 1), 'recommendation', 'recommendation tidak dikenal');
        $this->refused(fn () => $svc->record($this->in($a, ['score' => '101']), 1), 'score', 'skor > 100');
        $this->refused(fn () => $svc->record($this->in($a, ['score' => '-1']), 1), 'score', 'skor < 0');
        $this->refused(fn () => $svc->record($this->in($a, ['id_application' => 999999999]), 1), 'id_application', 'tidak ada');
        $this->refused(fn () => $svc->record($this->in($this->app('PSIKOTES', 'APPLIED')), 1), 'id_application', 'bukan IN_PROGRESS');
        $this->refused(fn () => $svc->record($this->in($this->app('SCREENING')), 1), 'id_application', 'tahap bukan penilaian');
        $this->assertSame($before, AppAssessmentResult::count(), 'penolakan tidak menulis apa pun');

        $r = $svc->record($this->in($a), 7);
        $this->assertSame($before + 1, AppAssessmentResult::count());
        $this->assertSame('PSIKOTES', $r->stage_code, 'tahap dari server');
        $this->assertSame(7, (int) $r->assessor_id);
        $this->assertSame('85.00', (string) $r->score);
        $this->assertSame('Hasil baik', $r->notes);
        $this->assertNotNull($r->assessed_at);

        $this->refused(fn () => $svc->record($this->in($a), 1), 'id_application', 'hasil ganda untuk tahap yang sama');
        $this->assertSame($before + 1, AppAssessmentResult::count());

        $noScore = $svc->record($this->in($this->app('INTERVIEW_HR'), ['score' => null, 'recommendation' => 'PERTIMBANGAN']), 1);
        $this->assertNull($noScore->score);
        $this->assertSame('Recorded', $svc->history($noScore)[0]['action']);
    }

    public function test_awaiting_lookup(): void
    {
        $waiting = $this->app('INTERVIEW_USER');
        $done = $this->app('PSIKOTES');
        AppAssessmentResult::create(['id_application' => $done->id_application, 'stage_code' => 'PSIKOTES', 'assessor_id' => 1, 'recommendation' => 'LANJUT', 'notes' => 'x', 'assessed_at' => now()]);
        $applied = $this->app('PSIKOTES', 'APPLIED');

        $ids = array_column(AssessmentResultService::awaitingSearch(''), 'id');
        $this->assertContains((int) $waiting->id_application, $ids);
        $this->assertNotContains((int) $done->id_application, $ids, 'sudah punya hasil');
        $this->assertNotContains((int) $applied->id_application, $ids, 'belum IN_PROGRESS');
        $this->assertSame((int) $waiting->id_application, AssessmentResultService::awaitingFind($waiting->id_application)['id']);
        $this->assertNull(AssessmentResultService::awaitingFind($done->id_application));
    }

    public function test_screen_route_grid_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        $form = route('v2.screen', 'hrm-rcr-assessment-form');
        $a = $this->app('PSIKOTES');
        $this->get(route('v2.screen', 'hrm-rcr-assessment'))->assertOk();
        $this->get($form)->assertNotFound();
        $this->get($form.'?id=999999999')->assertNotFound();
        $this->get($form.'?new=1')->assertOk()->assertSee('name="id_application"', false)->assertSee('name="notes"', false);

        $before = AppAssessmentResult::count();
        $this->post(route('v2.hrm.assessment.save'), $this->in($a, ['notes' => '']))->assertSessionHasErrors('notes');
        $this->post(route('v2.hrm.assessment.save'), $this->in($a, ['score' => '85,5']))->assertRedirect();
        $this->assertSame($before + 1, AppAssessmentResult::count());
        $res = AppAssessmentResult::where('id_application', $a->id_application)->firstOrFail();
        $this->assertSame((int) $this->admin()->getKey(), (int) $res->assessor_id);
        $this->get($form.'?id='.$res->id_result)->assertOk()->assertSee('PSIKOTES')->assertDontSee('name="notes"', false);
        $this->post(route('v2.hrm.assessment.save'), $this->in($a))->assertSessionHasErrors('id_application');
        $this->assertSame($before + 1, AppAssessmentResult::count());

        $grid = Grids::find('hrm-rcr-assessment');
        $this->assertStringContainsString('/v2/s/hrm-rcr-assessment-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/hrm-rcr-assessment-form?new=1', $grid->newUrl());
        $this->assertSame('warn', collect($grid->columns())->firstWhere('key', 'recommendation')['tones']['PERTIMBANGAN']);
        $this->assertFileDoesNotExist(resource_path('views/pages/recruitment/assessment-result.blade.php'));
        $this->assertFileExists(base_path('tmerp_unused/recruitment_assessment_result_v1_2026-10-19.blade.php'));
        $this->assertArrayNotHasKey('HRM_RCR_ASSESSMENT', MenuRoutes::map());
        $this->get('/hrm/recruitment/assessment-result')->assertRedirect('/v2/s/hrm-rcr-assessment');
        $this->get('/hrm/recruitment/assessment-result?new=1')->assertRedirect('/v2/s/hrm-rcr-assessment-form?new=1');
    }
}
