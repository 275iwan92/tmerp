<?php

namespace Tests\Feature;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppAssessmentResult;
use App\Models\Hrr\CndCandidate;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\OrgStructure;
use App\Support\V2\Data\RecruitmentPipeline;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Pipeline Board v2 + Organization Chart v2 (C8). Kandidat uji "ZZ Test Candidate" + satu lamaran di lowongan terbuka; dibersihkan lagi.
class RecruitmentPipelineTest extends TestCase
{
    private AppApplication $application;

    private CndCandidate $candidate;

    private int $vacancyId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->vacancyId = RecruitmentPipeline::defaultVacancy() ?? $this->markTestSkipped('No published vacancy.');
        $this->candidate = CndCandidate::create(['national_id' => 'ZZ-TEST-'.random_int(100000, 999999), 'name' => 'ZZ Test Candidate', 'activate' => true]);
        $this->application = AppApplication::create(['id_candidate' => $this->candidate->id_candidate, 'id_vacancy' => $this->vacancyId, 'current_stage_seq' => 1, 'status' => 'APPLIED', 'applied_at' => now()]);
    }

    protected function tearDown(): void
    {
        if (isset($this->application)) {
            AppAssessmentResult::where('id_application', $this->application->id_application)->delete();
            $this->application->delete();
        }
        isset($this->candidate) && $this->candidate->delete();
        parent::tearDown();
    }

    private function stage(): int
    {
        return (int) $this->application->fresh()->current_stage_seq;
    }

    public function test_board_lists_the_card_in_its_stage_with_forward_only_transitions(): void
    {
        $b = RecruitmentPipeline::board(RecruitmentPipeline::vacancy($this->vacancyId));
        $card = collect($b['cards'])->firstWhere('id', $this->application->id_application);
        $this->assertSame('1', $card['col']);
        $this->assertSame('ZZ Test Candidate', $card['title']);
        $this->assertSame(['2'], $b['transitions']['1']);
        $this->assertSame([], $b['transitions'][(string) count($b['columns'])]);
    }

    public function test_cannot_skip_a_stage(): void
    {
        $this->expectException(ValidationException::class);
        RecruitmentPipeline::move($this->application->id_application, 3);
    }

    public function test_moving_on_from_an_assessed_stage_needs_a_result(): void
    {
        RecruitmentPipeline::move($this->application->id_application, 2);
        $this->assertSame(2, $this->stage());

        try {
            RecruitmentPipeline::move($this->application->id_application, 3);
            $this->fail('Psikotes needs an assessment result first.');
        } catch (ValidationException) {
            $this->assertSame(2, $this->stage());
        }

        AppAssessmentResult::create(['id_application' => $this->application->id_application, 'stage_code' => 'PSIKOTES', 'assessor_id' => 1, 'score' => 80, 'recommendation' => 'LANJUT', 'assessed_at' => now()]);
        RecruitmentPipeline::move($this->application->id_application, 3);
        $this->assertSame(3, $this->stage());
        $this->assertSame('IN_PROGRESS', $this->application->fresh()->status);
    }

    public function test_reject_needs_a_reason_and_withdraw_removes_the_card(): void
    {
        try {
            RecruitmentPipeline::reject($this->application->id_application, '  ');
            $this->fail('A reason is required.');
        } catch (ValidationException) {
            $this->assertSame('APPLIED', $this->application->fresh()->status);
        }
        RecruitmentPipeline::reject($this->application->id_application, 'Not a fit');
        $this->assertSame('REJECTED', $this->application->fresh()->status);
        $this->assertSame('Not a fit', $this->application->fresh()->reject_reason);
        $ids = collect(RecruitmentPipeline::board(RecruitmentPipeline::vacancy($this->vacancyId))['cards'])->pluck('id');
        $this->assertNotContains($this->application->id_application, $ids->all());

        $other = AppApplication::create(['id_candidate' => $this->candidate->id_candidate, 'id_vacancy' => $this->vacancyId, 'current_stage_seq' => 1, 'status' => 'APPLIED', 'applied_at' => now()]);
        RecruitmentPipeline::withdraw($other->id_application);
        $this->assertSame('WITHDRAWN', $other->fresh()->status);
        $other->delete();
    }

    public function test_move_and_action_routes(): void
    {
        $admin = SecUser::where('is_admin', true)->firstOrFail();
        $this->actingAs($admin)->postJson(route('v2.pipeline.move'), ['card' => $this->application->id_application, 'from' => '1', 'to' => '3'])->assertStatus(422);
        $this->assertSame(1, $this->stage());
        $this->actingAs($admin)->postJson(route('v2.pipeline.move'), ['card' => $this->application->id_application, 'from' => '1', 'to' => '2'])->assertOk();
        $this->assertSame(2, $this->stage());

        $this->actingAs($admin)->post(route('v2.pipeline.action'), ['vacancy' => $this->vacancyId, 'application' => $this->application->id_application, 'action' => 'reject'])->assertSessionHasErrors();
        $this->actingAs($admin)->post(route('v2.pipeline.action'), ['vacancy' => $this->vacancyId, 'application' => $this->application->id_application, 'action' => 'reject', 'reason' => 'Test'])->assertRedirect();
        $this->assertSame('REJECTED', $this->application->fresh()->status);
    }

    public function test_screens_render(): void
    {
        $admin = SecUser::where('is_admin', true)->firstOrFail();
        $this->actingAs($admin)->get(route('v2.screen', 'hrm-pipeline-board').'?vacancy='.$this->vacancyId)->assertOk()->assertSee('ZZ Test Candidate')->assertSee('data-move-url', false);
        $this->actingAs($admin)->get(route('v2.screen', 'hrm-org-chart'))->assertOk()->assertSee('data-dl-org', false);
    }

    public function test_org_nodes_point_to_existing_parents_and_carry_people(): void
    {
        $nodes = OrgStructure::nodes();
        $this->assertNotEmpty($nodes);
        $ids = array_column($nodes, 'id');
        foreach ($nodes as $n) {
            $this->assertTrue($n['parent'] === null || in_array($n['parent'], $ids, true), 'orphan parent for '.$n['title']);
            $this->assertIsArray($n['people']);
        }
        $this->assertNotEmpty(OrgStructure::departments());
        $this->assertFalse(OrgStructure::isFlat([]));
        $this->assertTrue(OrgStructure::isFlat([['parent' => null], ['parent' => null]]));
    }
}
