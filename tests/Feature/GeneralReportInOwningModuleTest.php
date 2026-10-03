<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use App\Support\V2\AppMenu;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// User 2026-10-04: "General" is an access property, not a menu. A no-price report open to everyone lives in its owning
// module (Sales now, Production later) tagged required_object = GENERAL; a user without that module's right still sees
// the module, but only its GENERAL branches. The old module "General Report" must not come back.
class GeneralReportInOwningModuleTest extends TestCase
{
    private const CODES = ['ZZT_MOD', 'ZZT_GRP', 'ZZT_PRIVATE', 'ZZT_GENRPT', 'ZZT_GENERAL_ITEM'];

    protected function tearDown(): void
    {
        $menu = DB::connection('tm2sys')->table('sys_app_menu');
        foreach (['ZZT_GENERAL_ITEM', 'ZZT_PRIVATE', 'ZZT_GENRPT', 'ZZT_GRP', 'ZZT_MOD'] as $code) {
            $menu->clone()->where('code', $code)->delete();
        }
        parent::tearDown();
    }

    public function test_no_module_named_general_exists(): void
    {
        $this->assertSame(0, DB::connection('tm2sys')->table('sys_app_menu')->where('menu_type', 'module')->where('code', 'GENERAL')->count());
    }

    public function test_user_without_module_right_sees_only_the_general_branch(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $row = fn (array $a) => $menu()->insertGetId($a + ['menu_type' => 'item', 'activate' => 1, 'sort_order' => 1, 'status_code' => 'READY_FOR_QC', 'created_at' => now()]);
        $mod = $row(['parent_id' => null, 'code' => 'ZZT_MOD', 'label' => 'Zzt Module', 'menu_type' => 'module', 'required_object' => 'ZZT_NOBODY', 'sort_order' => 9999]);
        $grp = $row(['parent_id' => $mod, 'code' => 'ZZT_GRP', 'label' => 'Zzt Group', 'menu_type' => 'group']);
        $row(['parent_id' => $grp, 'code' => 'ZZT_PRIVATE', 'label' => 'Zzt Private', 'target' => 'v2:co-rpt-summary']);
        $gen = $row(['parent_id' => $grp, 'code' => 'ZZT_GENRPT', 'label' => 'General Report', 'menu_type' => 'group', 'required_object' => SecUser::PUBLIC_MODULE]);
        $row(['parent_id' => $gen, 'code' => 'ZZT_GENERAL_ITEM', 'label' => 'Zzt Open To All', 'target' => 'v2:gen-co-rpt-summary', 'required_object' => SecUser::PUBLIC_MODULE]);

        $user = SecUser::where('is_admin', false)->where('activate', true)->firstOrFail();
        $this->assertFalse($user->canAccessModule('ZZT_NOBODY'));
        $this->assertTrue($user->canAccessModule(SecUser::PUBLIC_MODULE));

        $module = collect(AppMenu::modules($user))->firstWhere('code', 'ZZT_MOD');
        $this->assertNotNull($module, 'A module the user has no right to still shows when it has a GENERAL branch.');
        $json = json_encode($module);
        $this->assertStringContainsString('Zzt Open To All', $json);
        $this->assertStringNotContainsString('Zzt Private', $json);

        // Verify the rows really exist before tearDown removes them (cleanup is checked by the next test run).
        $this->assertSame(5, $menu()->whereIn('code', self::CODES)->count());
    }
}
