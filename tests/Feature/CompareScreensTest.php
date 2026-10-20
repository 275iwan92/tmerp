<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use App\Support\V2\AppMenu;
use App\Support\V2\Compare;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Layar pembanding Special Forms (Developer > Comparison · Special Forms): hanya admin, data contoh statis. Hanya membaca basis data.
class CompareScreensTest extends TestCase
{
    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function nonAdmin(): SecUser
    {
        return SecUser::where('is_admin', false)->first() ?? $this->markTestSkipped('Tidak ada akun non-admin di basis data ini.');
    }

    private function compareUrls(array $modules): array
    {
        $urls = [];
        array_walk_recursive($modules, function ($v, $k) use (&$urls) {
            if ($k === 'url' && str_contains((string) $v, '/compare/')) {
                $urls[] = $v;
            }
        });

        return $urls;
    }

    public function test_admin_opens_every_comparison_screen(): void
    {
        $reviews = DB::connection('tm2sys')->table('sys_qc_review')->count();
        $this->actingAs($this->admin());
        foreach (Compare::screens() as $slug => [$title]) {
            $this->get(route('v2.compare', $slug))->assertOk()->assertSee(t($title));
        }
        $this->assertSame($reviews, DB::connection('tm2sys')->table('sys_qc_review')->count());
    }

    public function test_non_admin_is_forbidden_and_unknown_slug_is_404(): void
    {
        $slug = array_key_first(Compare::screens());
        $this->actingAs($this->nonAdmin())->get(route('v2.compare', $slug))->assertForbidden();
        $this->actingAs($this->admin())->get('/v2/compare/sf-tidak-ada')->assertNotFound();
    }

    public function test_menu_group_is_listed_for_admin_only(): void
    {
        $this->assertCount(count(Compare::screens()), $this->compareUrls(AppMenu::modules($this->admin())));
        $this->assertSame([], $this->compareUrls(AppMenu::modules($this->nonAdmin())));
    }
}
