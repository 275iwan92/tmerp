<?php

/*
 * REMARK
 * Tujuan       : Menjaga jadwal interview v2: hanya lamaran IN_PROGRESS di tahap Interview tanpa jadwal SCHEDULED yang bisa dijadwalkan (tahap dari server), interviewer aktif, tanggal tidak lampau, location maks 150, notifikasi ke interviewer, Done / Cancel hanya dari SCHEDULED, lookup antrean, layar form, route, grid, redirect v1 dan menu.
 * Sumber hulu  : App\Services\Recruitment\InterviewScheduleService, layar hrm-rcr-interview-form, routes/v2_hrm.php, InterviewGrid.
 * Input        : User johiwan (admin); lamaran, jadwal dan notifikasi uji dibuat di dalam transaksi tm2hrr / tm2sys yang di-rollback, jadi data nyata tidak berubah.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=InterviewScheduleServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppInterviewSchedule;
use App\Models\Hrr\AppPipelineStageConfig;
use App\Models\Hrr\CndCandidate;
use App\Models\Hrr\VacVacancy;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysNotification;
use App\Services\Recruitment\InterviewScheduleService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InterviewScheduleServiceTest extends TestCase
{
    private const CONNS = ['tm2hrr', 'tm2sys'];

    private CndCandidate $cand;

    private VacVacancy $vac;

    private int $track;

    private int $interviewer;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::CONNS as $c) {
            DB::connection($c)->beginTransaction();
        }
        $this->cand = CndCandidate::orderBy('id_candidate')->firstOrFail();
        $this->vac = VacVacancy::with('requisition')->orderBy('id_vacancy')->firstOrFail();
        $this->track = (int) $this->vac->requisition->id_track;
        $this->interviewer = (int) SecUser::where('activate', true)->whereNotNull('login')->orderBy('id_user')->value('id_user');
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

    private function app(string $stageCode, string $status = 'IN_PROGRESS'): AppApplication
    {
        $seq = (int) AppPipelineStageConfig::where('id_track', $this->track)->where('stage_code', $stageCode)->value('stage_seq');
        $this->assertGreaterThan(0, $seq, 'track uji punya tahap '.$stageCode);

        return AppApplication::create(['id_candidate' => $this->cand->id_candidate, 'id_vacancy' => $this->vac->id_vacancy, 'current_stage_seq' => $seq, 'status' => $status, 'applied_at' => now()]);
    }

    private function in(AppApplication $a, array $over = []): array
    {
        return array_merge(['id_application' => $a->id_application, 'date' => now()->addDays(3)->toDateString(), 'time' => '10:30', 'interviewer_id' => $this->interviewer, 'location' => 'Ruang Uji'], $over);
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

    public function test_schedule_rules_and_notification(): void
    {
        $svc = app(InterviewScheduleService::class);
        $a = $this->app('INTERVIEW_HR');
        $before = AppInterviewSchedule::count();

        $this->refused(fn () => $svc->schedule($this->in($a, ['date' => now()->subDay()->toDateString()])), 'date', 'tanggal lampau');
        $this->refused(fn () => $svc->schedule($this->in($a, ['time' => '25:99'])), 'time', 'jam salah');
        $this->refused(fn () => $svc->schedule($this->in($a, ['interviewer_id' => 999999999])), 'interviewer_id', 'interviewer tidak ada');
        $this->refused(fn () => $svc->schedule($this->in($a, ['location' => str_repeat('x', 151)])), 'location', 'location > 150');
        $this->refused(fn () => $svc->schedule($this->in($a, ['id_application' => 999999999])), 'id_application', 'lamaran tidak ada');
        $this->refused(fn () => $svc->schedule($this->in($this->app('INTERVIEW_HR', 'APPLIED'))), 'id_application', 'bukan IN_PROGRESS');
        $this->refused(fn () => $svc->schedule($this->in($this->app('SCREENING'))), 'id_application', 'tahap bukan interview');
        $this->assertSame($before, AppInterviewSchedule::count(), 'penolakan tidak menulis apa pun');

        $s = $svc->schedule($this->in($a));
        $this->assertSame($before + 1, AppInterviewSchedule::count());
        $this->assertSame('INTERVIEW_HR', $s->stage_code, 'tahap dari server');
        $this->assertSame('SCHEDULED', $s->status);
        $this->assertSame(now()->addDays(3)->toDateString().' 10:30:00', $s->scheduled_at->toDateTimeString());
        $this->assertSame('Ruang Uji', $s->location);
        $n = SysNotification::where('user_id', SecUser::whereKey($this->interviewer)->value('login'))->latest('id')->first();
        $this->assertStringContainsString($this->cand->name, $n->message);
        $this->assertStringContainsString('hrm-rcr-interview-form?id='.$s->id_schedule, (string) $n->link);

        $this->refused(fn () => $svc->schedule($this->in($a)), 'id_application', 'sudah ada jadwal SCHEDULED');
        $this->assertSame($before + 1, AppInterviewSchedule::count());

        // Setelah dibatalkan lamaran menunggu lagi; hari ini boleh; location kosong menjadi NULL.
        $svc->cancel($s);
        $again = $svc->schedule($this->in($a, ['date' => now()->toDateString(), 'location' => '  ']));
        $this->assertNull($again->location);
        $this->assertSame('Scheduled', $svc->history($again)[0]['action']);
    }

    public function test_done_and_cancel_only_from_scheduled_and_lookup(): void
    {
        $svc = app(InterviewScheduleService::class);
        $a = $this->app('INTERVIEW_USER');
        $this->assertContains((int) $a->id_application, array_column(InterviewScheduleService::awaitingSearch(''), 'id'));
        $s = $svc->schedule($this->in($a));
        $this->assertNotContains((int) $a->id_application, array_column(InterviewScheduleService::awaitingSearch(''), 'id'), 'sudah terjadwal');
        $this->assertNull(InterviewScheduleService::awaitingFind($a->id_application));

        $svc->markDone($s);
        $this->assertSame('DONE', $s->fresh()->status);
        foreach ([fn () => $svc->markDone($s), fn () => $svc->cancel($s)] as $i => $f) {
            $this->refused($f, 'document', 'bukan SCHEDULED '.$i);
        }
        $this->assertSame('DONE', $s->fresh()->status);

        $b = $this->app('INTERVIEW_HR');
        $c = $svc->schedule($this->in($b));
        $svc->cancel($c);
        $this->assertSame('CANCELLED', $c->fresh()->status);
        $this->assertSame((int) $b->id_application, InterviewScheduleService::awaitingFind($b->id_application)['id'], 'batal = menunggu lagi');
    }

    public function test_screen_route_grid_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        $a = $this->app('INTERVIEW_HR');
        $form = route('v2.screen', 'hrm-rcr-interview-form');
        $this->get(route('v2.screen', 'hrm-rcr-interview'))->assertOk();
        $this->get($form)->assertNotFound();
        $this->get($form.'?id=999999999')->assertNotFound();
        $this->get($form.'?new=1')->assertOk()->assertSee('name="id_application"', false)->assertSee('name="interviewer_id"', false)->assertSee('name="location"', false);

        $before = AppInterviewSchedule::count();
        $this->post(route('v2.hrm.interview.save'), $this->in($a, ['interviewer_id' => '']))->assertSessionHasErrors('interviewer_id');
        $this->post(route('v2.hrm.interview.save'), $this->in($a))->assertRedirect();
        $this->assertSame($before + 1, AppInterviewSchedule::count());
        $s = AppInterviewSchedule::where('id_application', $a->id_application)->firstOrFail();
        $open = $form.'?id='.$s->id_schedule;
        $this->get($open)->assertOk()->assertSee('INTERVIEW_HR')->assertSee('value="done"', false)->assertDontSee('name="location"', false);
        $this->post(route('v2.hrm.interview.save'), ['id' => $s->id_schedule, 'action' => 'bogus'])->assertStatus(422);
        $this->post(route('v2.hrm.interview.save'), ['id' => $s->id_schedule, 'action' => 'done'])->assertRedirect($open);
        $this->assertSame('DONE', $s->fresh()->status);
        $this->post(route('v2.hrm.interview.save'), ['id' => $s->id_schedule, 'action' => 'cancel'])->assertSessionHasErrors('document');
        $this->get($open)->assertOk()->assertDontSee('value="done"', false);

        $grid = Grids::find('hrm-rcr-interview');
        $this->assertStringContainsString('/v2/s/hrm-rcr-interview-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/hrm-rcr-interview-form?new=1', $grid->newUrl());
        $this->assertFileDoesNotExist(resource_path('views/pages/recruitment/interview-schedule.blade.php'));
        $this->assertArchived('recruitment_interview_schedule_v1_2026-10-19.blade.php');
        $this->assertArrayNotHasKey('HRM_RCR_INTERVIEW', MenuRoutes::map());
        $this->get('/hrm/recruitment/interview-schedule')->assertRedirect('/v2/s/hrm-rcr-interview');
        $this->get('/hrm/recruitment/interview-schedule?open=2')->assertRedirect('/v2/s/hrm-rcr-interview-form?id=2');
    }
}
