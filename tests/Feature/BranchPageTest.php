<?php

/*
 * REMARK
 * Tujuan       : Menjaga halaman cabang menu (klik Modul / Grup di breadcrumb): terbuka untuk modul dan grup, 404 untuk selain itu,
 *                dan breadcrumb layar membawa alamat halaman cabangnya.
 * Sumber hulu  : routes/v2_account.php (v2.branch), App\Support\V2\AppMenu::branch, resources/views/v2/branch.blade.php.
 * Input        : Akun admin johiwan; tanpa menulis data.
 * Output       : Hasil uji PHPUnit.
 * Dipakai oleh : php artisan test --filter=BranchPageTest.
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use App\Support\V2\AppMenu;
use Tests\TestCase;

class BranchPageTest extends TestCase
{
    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    public function test_every_module_and_group_with_a_code_opens_its_branch_page(): void
    {
        $user = $this->admin();
        $codes = [];
        $walk = function (array $nodes) use (&$walk, &$codes) {
            foreach ($nodes as $n) {
                if (isset($n['items'])) {
                    $codes[] = $n['code'] ?? null;
                    $walk($n['items']);
                }
            }
        };
        foreach (AppMenu::modules($user) as $m) {
            $codes[] = $m['code'];
            $walk($m['children']);
        }
        $codes = array_values(array_filter($codes));
        $this->assertNotEmpty($codes);

        foreach ($codes as $code) {
            $this->actingAs($user)->get(route('v2.branch', $code))->assertOk()->assertSee('data-dl-open-item', false);
        }
    }

    public function test_unknown_code_and_screen_code_are_not_found(): void
    {
        $this->actingAs($this->admin())->get('/v2/branch/NO_SUCH_BRANCH')->assertNotFound();
        $this->actingAs($this->admin())->get('/v2/branch/SET_USER')->assertNotFound();
    }

    public function test_screen_breadcrumb_carries_the_branch_page_address(): void
    {
        $this->actingAs($this->admin())->get('/v2/item/SALES_INQ')->assertRedirect();
        $this->actingAs($this->admin())->get('/v2/s/inquiry?mi=SALES_INQ')
            ->assertOk()->assertSee('data-dl-crumb-url="'.route('v2.branch', 'SALES').'"', false);
    }
}
