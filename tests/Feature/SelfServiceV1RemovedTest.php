<?php

/*
 * REMARK
 * Tujuan       : Menjaga self-service v1 tetap terhapus: URL lama diteruskan ke Edit profile v2, menu alias Employee Self-Service tidak ada, tautan v1 menuju v2.
 * Sumber hulu  : routes/web.php (/self-service), routes/v2_account.php (v2.profile), sys_app_menu.
 * Input        : User johiwan (admin) yang sudah ada; hanya membaca.
 * Output       : Lulus bila tidak ada jalur ke layar v1.
 * Dipakai oleh : php artisan test --filter=SelfServiceV1RemovedTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SelfServiceV1RemovedTest extends TestCase
{
    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    public function test_old_self_service_url_redirects_to_v2_profile_with_tab(): void
    {
        $this->actingAs($this->admin())->get('/self-service?tab=security')
            ->assertRedirect(route('v2.profile', ['tab' => 'security']));
    }

    public function test_v2_profile_opens(): void
    {
        $this->actingAs($this->admin())->get(route('v2.profile', ['tab' => 'security']))->assertOk();
    }

    public function test_v1_view_and_alias_menu_are_gone(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/pages/self-service/index.blade.php'));
        $this->assertSame(0, DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'HRM_SELFSERVICE')->count());
        $this->assertArrayNotHasKey('HRM_SELFSERVICE', \App\Support\Menu\MenuRoutes::map());
    }
}
