<?php

/*
 * REMARK
 * Tujuan       : Menjaga lowongan kerja v2: Save menerbitkan dari requisition APPROVED (requisition lain ditolak server, channel harus dari daftar termasuk GLINTS), kode VAC diambil dari nomor terbesar (bukan jumlah baris), Close hanya untuk lowongan terbuka, lowongan yang ada baca-saja, lookup requisition, layar form, route, grid, redirect v1 dan menu.
 * Sumber hulu  : App\Services\Recruitment\RecruitmentVacancyService, layar hrm-rcr-vacancy-form, routes/v2_hrm.php, VacancyGrid.
 * Input        : User johiwan (admin); lowongan uji dibuat di dalam transaksi tm2hrr yang di-rollback, jadi 9 lowongan nyata tidak berubah.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=RecruitmentVacancyServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Hrr\RcrRequisition;
use App\Models\Hrr\VacVacancy;
use App\Models\Sys\SecUser;
use App\Services\Recruitment\RecruitmentVacancyService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RecruitmentVacancyServiceTest extends TestCase
{
    private int $approved;

    private int $rejected;

    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('tm2hrr')->beginTransaction();
        $this->approved = (int) RcrRequisition::where('status', 'APPROVED')->orderBy('id_requisition')->value('id_requisition');
        $this->rejected = (int) RcrRequisition::where('status', '!=', 'APPROVED')->orderBy('id_requisition')->value('id_requisition');
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

    private function in(array $over = []): array
    {
        return array_merge(['id_requisition' => $this->approved, 'channel' => 'GLINTS', 'title' => '  ZZ Vacancy  ', 'description' => 'Deskripsi publik'], $over);
    }

    public function test_publish_rules_and_code_sequence(): void
    {
        $svc = app(RecruitmentVacancyService::class);
        $this->assertGreaterThan(0, $this->rejected, 'ada requisition non-APPROVED');
        foreach (['no requisition' => ['id_requisition' => ''], 'not approved' => ['id_requisition' => $this->rejected], 'unknown' => ['id_requisition' => 999999999], 'bad channel' => ['channel' => 'XX'],
            'blank title' => ['title' => '  '], 'long title' => ['title' => str_repeat('t', 151)]] as $name => $over) {
            try {
                $svc->publish($this->in($over));
                $this->fail('harus ditolak: '.$name);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors(), $name);
            }
        }

        $before = VacVacancy::count();
        $a = $svc->publish($this->in());
        $this->assertSame($before + 1, VacVacancy::count());
        $this->assertMatchesRegularExpression('/^VAC'.now()->format('y').'\d{5}$/', $a->vacancy_code);
        $this->assertSame('ZZ Vacancy', $a->title);
        $this->assertTrue((bool) $a->is_published);
        $this->assertNotNull($a->published_at);
        $this->assertNull($a->closed_at);
        $this->assertSame('GLINTS', $a->channel);
        $b = $svc->publish($this->in(['title' => 'ZZ Two', 'description' => '']));
        $this->assertSame((int) substr($a->vacancy_code, 5) + 1, (int) substr($b->vacancy_code, 5), 'nomor berikutnya = nomor terbesar + 1');
        $this->assertNull($b->description);

        // Dihapus satu di tengah: nomor berikutnya tetap dari yang terbesar, tidak bentrok dengan jumlah baris.
        VacVacancy::whereKey($a->id_vacancy)->delete();
        $c = $svc->publish($this->in(['title' => 'ZZ Three']));
        $this->assertSame((int) substr($b->vacancy_code, 5) + 1, (int) substr($c->vacancy_code, 5));
        $this->assertSame('Open', RecruitmentVacancyService::status($c));
        $this->assertSame('Created', $svc->history($c)[count($svc->history($c)) - 1]['action']);
    }

    public function test_close_only_an_open_vacancy_and_lookup(): void
    {
        $svc = app(RecruitmentVacancyService::class);
        $v = $svc->publish($this->in());
        $svc->close($v);
        $v->refresh();
        $this->assertNotNull($v->closed_at);
        $this->assertSame('Closed', RecruitmentVacancyService::status($v));
        $this->assertSame('Closed', $svc->history($v)[0]['action']);
        $draft = VacVacancy::create(['vacancy_code' => 'ZZDRAFT01', 'id_requisition' => $this->approved, 'channel' => 'LAINNYA', 'title' => 'ZZ Draft', 'is_published' => false]);
        foreach ([$v, $draft] as $i => $closed) {
            try {
                $svc->close($closed);
                $this->fail('bukan lowongan terbuka harus ditolak: '.$i);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('document', $e->errors());
            }
        }

        $rows = RecruitmentVacancyService::requisitionSearch('');
        $this->assertNotEmpty($rows);
        $this->assertSame(RcrRequisition::where('status', 'APPROVED')->count(), count(RecruitmentVacancyService::requisitionSearch('', 100)), 'hanya APPROVED');
        $this->assertArrayHasKey('title', $rows[0]);
        $this->assertSame($this->approved, RecruitmentVacancyService::requisitionFind($this->approved)['id']);
    }

    public function test_screen_route_grid_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        $form = route('v2.screen', 'hrm-rcr-vacancy-form');
        $this->get(route('v2.screen', 'hrm-rcr-vacancy'))->assertOk();
        $this->get($form)->assertNotFound();
        $this->get($form.'?id=999999999')->assertNotFound();
        $this->get($form.'?new=1')->assertOk()->assertSee('name="id_requisition"', false)->assertSee('name="title"', false);

        $this->post(route('v2.hrm.vacancy.save'), $this->in(['id_requisition' => $this->rejected]))->assertSessionHasErrors('id_requisition');
        $this->post(route('v2.hrm.vacancy.save'), $this->in())->assertRedirect();
        $v = VacVacancy::where('title', 'ZZ Vacancy')->firstOrFail();
        $open = $form.'?id='.$v->id_vacancy;
        $this->get($open)->assertOk()->assertSee('ZZ Vacancy')->assertSee('Close vacancy');
        $this->post(route('v2.hrm.vacancy.save'), ['id' => $v->id_vacancy, 'title' => 'Changed'])->assertStatus(422);
        $this->assertSame('ZZ Vacancy', $v->fresh()->title, 'lowongan yang ada baca-saja');
        $this->post(route('v2.hrm.vacancy.save'), ['id' => $v->id_vacancy, 'action' => 'close'])->assertRedirect($open);
        $this->assertNotNull($v->fresh()->closed_at);
        $this->get($open)->assertOk()->assertDontSee('value="close"', false);
        $this->post(route('v2.hrm.vacancy.save'), ['id' => $v->id_vacancy, 'action' => 'close'])->assertSessionHasErrors('document');

        $grid = Grids::find('hrm-rcr-vacancy');
        $this->assertStringContainsString('/v2/s/hrm-rcr-vacancy-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/hrm-rcr-vacancy-form?new=1', $grid->newUrl());
        $this->assertFileDoesNotExist(resource_path('views/pages/recruitment/vacancy.blade.php'));
        $this->assertArchived('recruitment_vacancy_v1_2026-10-19.blade.php');
        $this->assertArrayNotHasKey('HRM_RCR_VACANCY', MenuRoutes::map());
        $this->get('/hrm/recruitment/vacancy')->assertRedirect('/v2/s/hrm-rcr-vacancy');
        $this->get('/hrm/recruitment/vacancy?new=1')->assertRedirect('/v2/s/hrm-rcr-vacancy-form?new=1');
    }
}
