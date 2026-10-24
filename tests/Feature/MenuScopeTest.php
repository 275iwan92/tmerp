<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use App\Support\V2\AppMenu;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Grup bercakupan (PRODEV_STAFF, MENU_PROCOST, MENU_SALES_INQ; migration 2026_10_24_000400): menu hanya cabang yang disebut + item GENERAL.
// Menulis akun uji sementara ke tm2sys dan menghapusnya sendiri di tearDown.
class MenuScopeTest extends TestCase
{
    private const PREFIX = 'tst_scope_';

    protected function tearDown(): void
    {
        $ids = DB::connection('tm2sys')->table('sec_user')->where('login', 'like', self::PREFIX.'%')->pluck('id_user');
        DB::connection('tm2sys')->table('sec_user_group')->whereIn('user_id', $ids)->delete();
        DB::connection('tm2sys')->table('sec_user')->whereIn('id_user', $ids)->delete();

        parent::tearDown();
    }

    private function codes(string $key, array $groups): array
    {
        $db = DB::connection('tm2sys');
        $id = $db->table('sec_user')->insertGetId(['login' => self::PREFIX.$key, 'name' => 'Test '.$key, 'active' => 1, 'activate' => 1, 'is_admin' => 0]);
        foreach ($groups as $code) {
            $db->table('sec_user_group')->insert(['user_id' => $id, 'group_id' => $db->table('sec_group')->where('group_code', $code)->value('id_group')]);
        }
        $walk = function (array $nodes) use (&$walk) {
            foreach ($nodes as $n) {
                yield from isset($n['items']) ? $walk($n['items']) : [$n['code']];
            }
        };

        return iterator_to_array($walk(array_merge(...array_map(fn ($m) => $m['children'], AppMenu::modules(SecUser::query()->findOrFail($id))))), false);
    }

    public function test_prodev_staff_sees_only_prodev_and_general(): void
    {
        $codes = $this->codes('prodev', ['PRODEV_STAFF']);

        $this->assertContains('PRE_PRODEV_NEW', $codes);
        $this->assertContains('GENERAL_CO_SUMMARY', $codes);
        $this->assertContains('HRM_CLOCKIN', $codes);
        $this->assertNotContains('SALES_QUOT', $codes);
        $this->assertNotContains('SALES_COST', $codes);
        $this->assertNotContains('PROD_WO_LIST', $codes);
    }

    public function test_procost_scope_sees_only_procost(): void
    {
        $codes = $this->codes('procost', ['COSTING_STAFF', 'MENU_PROCOST']);

        $this->assertContains('SALES_COST', $codes);
        $this->assertNotContains('PRE_PRODEV_NEW', $codes);
        $this->assertNotContains('SALES_QUOT', $codes);
        $this->assertNotContains('PROD_WO_LIST', $codes);
    }

    public function test_sales_scope_sees_inquiry_quotation_and_orders_but_not_prodev_or_production(): void
    {
        $codes = $this->codes('sales', ['SALES_STAFF', 'MENU_SALES_INQ']);

        foreach (['PRE_INQ_NEW', 'SALES_QUOT', 'SALES_SO', 'SALES_DO'] as $c) {
            $this->assertContains($c, $codes);
        }
        $this->assertNotContains('PRE_PRODEV_NEW', $codes);
        $this->assertNotContains('SALES_COST', $codes);
        $this->assertNotContains('PROD_WO_LIST', $codes);
        $this->assertNotContains('PROD_OPERATOR', $codes);
    }

    public function test_group_without_scope_is_unchanged(): void
    {
        $this->assertContains('PRE_PRODEV_NEW', $this->codes('plain', ['SALES_STAFF']));
    }
}
