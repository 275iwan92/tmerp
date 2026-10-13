<?php

/*
 * REMARK
 * Tujuan       : Menguji layar Setup > Others > MD Review dan Old Stories Review: tampil, simpan keputusan, dan antrean Process with Claude.
 * Sumber hulu  : sys_md_review, sys_story_review, sys_review_request di tm2sys (database ddtsys bila dijalankan dengan --env=mockup).
 * Input        : Jalankan: php artisan test --env=mockup --filter=ReviewScreensTest (data review sudah diisi php artisan review:import).
 * Output       : Lulus/gagal; semua tulisan dibungkus transaksi lalu dibatalkan, tabel tidak berubah.
 * Dipakai oleh : Penguji (Claude) setelah mengubah layar review.
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use App\Models\Sys\SysMdReview;
use App\Models\Sys\SysReviewRequest;
use App\Models\Sys\SysStoryReview;
use App\Support\V2\Grid\Grids;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReviewScreensTest extends TestCase
{
    private SecUser $admin;

    protected function setUp(): void
    {
        parent::setUp();
        if (! SysMdReview::query()->exists() || ! SysStoryReview::query()->exists()) {
            $this->markTestSkipped('Review tables are empty: run php artisan review:import first.');
        }
        DB::connection('tm2sys')->beginTransaction();
        // --env=mockup membuat APP_ENV bukan 'testing', jadi perlindungan CSRF aktif; POST uji tidak membawa token.
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->admin = SecUser::where('is_admin', true)->firstOrFail();
    }

    protected function tearDown(): void
    {
        DB::connection('tm2sys')->rollBack();
        parent::tearDown();
    }

    public function test_menu_rows_point_to_registered_screens(): void
    {
        foreach (['SET_MD_REVIEW' => 'setup-md-review', 'SET_STORY_REVIEW' => 'setup-story-review'] as $code => $slug) {
            $row = DB::connection('tm2sys')->table('sys_app_menu')->where('code', $code)->first();
            $this->assertNotNull($row, "{$code} menu row missing");
            $this->assertSame('v2:'.$slug, $row->target);
            $this->assertSame('SET_GRP_OTHERS', DB::connection('tm2sys')->table('sys_app_menu')->where('id', $row->parent_id)->value('code'));
            $this->assertNotNull(Grids::find($slug));
        }
    }

    public function test_screens_render_with_data_and_process_button(): void
    {
        $file = SysMdReview::query()->orderBy('file_path')->value('file_path');
        $md = $this->actingAs($this->admin)->get(route('v2.screen', 'setup-md-review'))->assertOk()->assertSee($file);
        $this->assertSeeEither($md->getContent(), 'Process with Claude', 'Proses dengan Claude');

        $story = $this->actingAs($this->admin)->get(route('v2.screen', 'setup-story-review'))->assertOk();
        $this->assertSeeEither($story->getContent(), 'Process with Claude', 'Proses dengan Claude');
        $this->assertSeeEither($story->getContent(), 'Full text', 'Teks utuh');
    }

    // Layar mengikuti bahasa akun: label English atau pasangan Indonesia di kamus sama-sama sah.
    private function assertSeeEither(string $html, string $en, string $id): void
    {
        $this->assertTrue(str_contains($html, $en) || str_contains($html, $id), "Neither '{$en}' nor '{$id}' found on the screen");
    }

    public function test_decision_save_records_who_and_when_and_rejects_unknown_codes(): void
    {
        $md = SysMdReview::query()->whereNull('decision')->firstOrFail();
        $this->actingAs($this->admin)->postJson(route('v2.lookup.save', 'setup-md-review'), ['changed' => [['id' => $md->id_md_review, 'decision' => 'DISCARD']]])->assertOk();
        $md->refresh();
        $this->assertSame('DISCARD', $md->decision);
        $this->assertSame($this->admin->login, $md->decided_by);
        $this->assertNotNull($md->decided_at);

        $this->actingAs($this->admin)->postJson(route('v2.lookup.save', 'setup-md-review'), ['changed' => [['id' => $md->id_md_review, 'decision' => '']]])->assertOk();
        $md->refresh();
        $this->assertNull($md->decision);
        $this->assertNull($md->decided_by);

        $story = SysStoryReview::query()->firstOrFail();
        $this->actingAs($this->admin)->postJson(route('v2.lookup.save', 'setup-story-review'), ['changed' => [['id' => $story->id_story_review, 'decision' => 'DISCARD']]])->assertStatus(422);
    }

    public function test_process_request_needs_a_decision_and_keeps_one_open_request_per_scope(): void
    {
        SysMdReview::query()->update(['decision' => null, 'decided_by' => null, 'decided_at' => null]);
        SysReviewRequest::query()->where('scope', 'MD')->delete();

        $this->actingAs($this->admin)->post(route('v2.review.request', 'md'))->assertRedirect()->assertSessionHasErrors('review');
        $this->assertSame(0, SysReviewRequest::query()->where('scope', 'MD')->count());

        $ids = SysMdReview::query()->limit(3)->pluck('id_md_review');
        SysMdReview::query()->whereKey($ids[0])->update(['decision' => 'DISCARD']);
        $this->actingAs($this->admin)->post(route('v2.review.request', 'md'))->assertRedirect();
        $req = SysReviewRequest::query()->where('scope', 'MD')->firstOrFail();
        $this->assertSame('REQUESTED', $req->status);
        $this->assertSame(1, $req->n_decided);
        $this->assertSame('DISCARD=1', $req->summary);
        $this->assertSame($this->admin->login, $req->requested_by);

        SysMdReview::query()->whereKey($ids[1])->update(['decision' => 'KEEP']);
        $this->actingAs($this->admin)->post(route('v2.review.request', 'md'))->assertRedirect();
        $this->assertSame(1, SysReviewRequest::query()->where('scope', 'MD')->count());
        $this->assertSame(2, SysReviewRequest::query()->where('scope', 'MD')->value('n_decided'));
    }

    public function test_process_request_rejects_unknown_scope(): void
    {
        $this->actingAs($this->admin)->post('/v2/review/other/request')->assertNotFound();
    }
}
