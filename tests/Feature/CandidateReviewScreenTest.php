<?php

/*
 * REMARK
 * Tujuan       : Menjaga Review Formulir Kandidat v2: layar baca-saja per lamaran menampilkan data kandidat dan tabel anaknya tanpa aksi tulis, 404 untuk lamaran yang tidak ada, grid membuka form, redirect route v1 (review dan pipeline-board) dan menu.
 * Sumber hulu  : layar hrm-rcr-review-form, ApplicationGrid, routes/web.php (redirect), MenuRoutes.
 * Input        : User johiwan (admin); kandidat, anak data dan lamaran uji dibuat di dalam transaksi tm2hrr yang di-rollback.
 * Output       : Lulus bila layar tampil dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=CandidateReviewScreenTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\CndCandidate;
use App\Models\Hrr\CndDocument;
use App\Models\Hrr\CndEducation;
use App\Models\Hrr\CndFamilyMember;
use App\Models\Hrr\CndWorkExperience;
use App\Models\Hrr\VacVacancy;
use App\Models\Sys\SecUser;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CandidateReviewScreenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('tm2hrr')->beginTransaction();
    }

    protected function tearDown(): void
    {
        while (DB::connection('tm2hrr')->transactionLevel() > 0) {
            DB::connection('tm2hrr')->rollBack();
        }
        parent::tearDown();
    }

    public function test_review_screen_is_read_only_and_complete(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $form = route('v2.screen', 'hrm-rcr-review-form');
        $c = CndCandidate::create(['name' => 'Zz Review Candidate', 'national_id' => '9990000000000099', 'gender' => 'PEREMPUAN', 'email' => 'zz.rev@example.test', 'phone' => '0800111', 'birth_date' => '1994-02-03',
            'marital_status' => 'MENIKAH', 'why_join_us' => 'Ingin berkembang', 'expected_salary' => 4000000, 'willingness_checklist' => ['probation' => true, 'overtime' => false], 'language_skills' => [['language' => 'Inggris', 'oral' => 'B']]]);
        CndFamilyMember::create(['id_candidate' => $c->id_candidate, 'relation_type' => 'FATHER', 'name' => 'Zz Ayah', 'gender' => 'L', 'age' => 60]);
        CndEducation::create(['id_candidate' => $c->id_candidate, 'level' => 'S1', 'institution' => 'Zz University', 'major' => 'Grafika', 'period_start_year' => 2012, 'graduation_year' => 2016]);
        CndWorkExperience::create(['id_candidate' => $c->id_candidate, 'company' => 'Zz Company', 'position' => 'Operator', 'start_date' => '2017-01-02']);
        CndDocument::create(['id_candidate' => $c->id_candidate, 'doc_type' => 'KTP', 'file_path' => 'zz/ktp.pdf', 'uploaded_at' => now()]);
        $a = AppApplication::create(['id_candidate' => $c->id_candidate, 'id_vacancy' => VacVacancy::orderBy('id_vacancy')->value('id_vacancy'), 'current_stage_seq' => 1, 'status' => 'APPLIED', 'applied_at' => now()]);

        $this->get(route('v2.screen', 'hrm-rcr-review'))->assertOk();
        $this->get($form)->assertNotFound();
        $this->get($form.'?application=999999999')->assertNotFound();
        $this->get($form.'?application='.$a->id_application)->assertOk()
            ->assertSee('Zz Review Candidate')->assertSee('9990000000000099')->assertSee('Female')->assertSee('Married')->assertSee('Ingin berkembang')
            ->assertSee('Zz Ayah')->assertSee('Zz University')->assertSee('Zz Company')->assertSee('Inggris')->assertSee('zz/ktp.pdf', false)
            ->assertSee('hrm-pipeline-board?vacancy=', false)->assertDontSee('data-dl-preset="save"', false);
        $this->post($form.'?application='.$a->id_application)->assertStatus(405);

        $grid = Grids::find('hrm-rcr-review');
        $this->assertStringContainsString('/v2/s/hrm-rcr-review-form?application={id}', $grid->openUrl());
        $this->assertNull($grid->newUrl());
        foreach (['list', 'detail'] as $f) {
            $this->assertFileDoesNotExist(resource_path('views/pages/recruitment/candidate-review/'.$f.'.blade.php'));
            $this->assertArchived('recruitment_candidate_review_'.$f.'_v1_2026-10-20.blade.php');
        }
        $this->assertArrayNotHasKey('HRM_RCR_REVIEW', MenuRoutes::map());
        $this->assertArrayNotHasKey('HRM_RCR_PIPELINE', MenuRoutes::map());
        $this->get('/hrm/recruitment/candidate-review')->assertRedirect('/v2/s/hrm-rcr-review');
        $this->get('/hrm/recruitment/candidate-review/7')->assertRedirect('/v2/s/hrm-rcr-review-form?application=7');
        $this->get('/hrm/recruitment/pipeline-board')->assertRedirect('/v2/s/hrm-pipeline-board');
        $this->get('/hrm/recruitment/pipeline-board?vacancy=4')->assertRedirect('/v2/s/hrm-pipeline-board?vacancy=4');
    }
}
