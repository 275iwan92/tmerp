<?php

/*
 * REMARK
 * Tujuan       : Menjaga akun dan grup Security v2: akun baru tervalidasi (login unik huruf kecil, password min 8, grup aktif), ubah profil dengan verifikasi e-mail gugur, reset dan lihat password sebagai kartu login, admin tak bisa menonaktifkan dirinya, grup dengan kode kapital unik, grup beranggota aktif tak bisa dinonaktifkan, hanya administrator, layar form, route, grid, redirect v1 dan menu.
 * Sumber hulu  : App\Services\Security\SecurityAccountService dan SecurityGroupService, layar security-account-form dan security-group-form, routes/v2_setup.php, AccountGrid dan GroupGrid.
 * Input        : User johiwan (administrator); akun, grup dan penugasan uji dibuat di dalam transaksi tm2sys yang di-rollback, jadi data nyata tidak berubah.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=SecurityAccountGroupServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Sys\SecGroup;
use App\Models\Sys\SecUser;
use App\Services\Security\SecurityAccountService;
use App\Services\Security\SecurityGroupService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SecurityAccountGroupServiceTest extends TestCase
{
    private SecGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('tm2sys')->beginTransaction();
        $this->group = SecGroup::create(['group_code' => 'ZZ_TEST', 'group_name' => 'ZZ Test Group', 'mgmt_level' => 2, 'activate' => true]);
    }

    protected function tearDown(): void
    {
        while (DB::connection('tm2sys')->transactionLevel() > 0) {
            DB::connection('tm2sys')->rollBack();
        }
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function acct(array $over = []): array
    {
        return array_merge(['login' => 'zz.tester', 'name' => 'ZZ Tester', 'email' => 'zz@example.test', 'password' => 'Secret123', 'group_id' => $this->group->id_group], $over);
    }

    private function refused(callable $f, string $field, string $why): void
    {
        try {
            $f();
            $this->fail('harus ditolak: '.$why);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors(), $why);
        }
    }

    public function test_account_rules_profile_reset_and_active(): void
    {
        $svc = app(SecurityAccountService::class);
        $admin = $this->admin();
        $inactive = SecGroup::create(['group_code' => 'ZZ_OFF', 'group_name' => 'ZZ Off', 'mgmt_level' => 1, 'activate' => false]);
        $before = SecUser::count();

        foreach (['login upper' => ['login' => 'ZZ.Tester'], 'login space' => ['login' => 'zz tester'], 'login long' => ['login' => str_repeat('a', 191)], 'login taken' => ['login' => $admin->login],
            'short password' => ['password' => 'short'], 'no name' => ['name' => ''], 'bad email' => ['email' => 'nope'], 'no group' => ['group_id' => 999999999], 'inactive group' => ['group_id' => $inactive->id_group]] as $name => $over) {
            try {
                $svc->save(null, $this->acct($over), $admin);
                $this->fail('harus ditolak: '.$name);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors(), $name);
            }
        }
        $this->assertSame($before, SecUser::count(), 'penolakan tidak menulis apa pun');

        $u = $svc->save(null, $this->acct(), $admin);
        $this->assertTrue($u->activate);
        $this->assertSame([(int) $this->group->id_group], $u->groups()->pluck('id_group')->map(fn ($v) => (int) $v)->all());
        $this->assertTrue(password_verify('Secret123', $u->fresh()->pswd));
        $this->refused(fn () => $svc->save(null, $this->acct(), $admin), 'login', 'login ganda');

        $other = SecGroup::create(['group_code' => 'ZZ_OTHER', 'group_name' => 'ZZ Other', 'mgmt_level' => 3, 'activate' => true]);
        $u->forceFill(['email_verified' => true, 'email_verified_at' => now()])->save();
        $svc->save($u, ['name' => 'ZZ Changed', 'email' => 'zz@example.test', 'group_id' => $other->id_group, 'login' => 'ignored', 'password' => 'ignored1'], $admin);
        $u->refresh();
        $this->assertSame('zz.tester', $u->login, 'login tidak berubah');
        $this->assertSame('ZZ Changed', $u->name);
        $this->assertTrue((bool) $u->email_verified, 'e-mail sama = tetap terverifikasi');
        $this->assertSame([(int) $other->id_group], $u->groups()->pluck('id_group')->map(fn ($v) => (int) $v)->all());
        $svc->save($u, ['name' => 'ZZ Changed', 'email' => 'new@example.test', 'group_id' => $other->id_group], $admin);
        $this->assertFalse((bool) $u->fresh()->email_verified, 'e-mail berubah = verifikasi gugur');

        $this->refused(fn () => $svc->reveal($u->fresh()), 'account', 'belum ada password bawaan');
        $card = $svc->resetPassword($u);
        $this->assertSame('zz.tester', $card['login']);
        $this->assertMatchesRegularExpression('/^Tmk-/', $card['password']);
        $this->assertTrue((bool) $u->fresh()->must_change_password);
        $this->assertSame($card['password'], $svc->reveal($u->fresh())['password']);

        $svc->setActive($u, false, $admin);
        $this->assertFalse((bool) $u->fresh()->activate);
        $svc->setActive($u, true, $admin);
        $this->refused(fn () => $svc->setActive($admin, false, $admin), 'account', 'admin menonaktifkan dirinya');
        $this->assertTrue((bool) $admin->fresh()->activate);
        $this->assertSame('Password changed', $svc->history($u->fresh())[0]['action']);
    }

    public function test_group_rules_and_deactivation(): void
    {
        $svc = app(SecurityGroupService::class);
        $admin = $this->admin();
        $before = SecGroup::count();

        foreach (['no code' => ['group_code' => ''], 'bad code' => ['group_code' => 'zz-bad!'], 'long code' => ['group_code' => str_repeat('A', 41)], 'no name' => ['group_name' => ''], 'name taken' => ['group_name' => 'ZZ Test Group'],
            'level 0' => ['mgmt_level' => 0], 'level 6' => ['mgmt_level' => 6], 'code taken' => ['group_code' => 'zz test']] as $name => $over) {
            try {
                $svc->save(null, array_merge(['group_code' => 'zz new', 'group_name' => 'ZZ New', 'mgmt_level' => 3], $over), $admin);
                $this->fail('harus ditolak: '.$name);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors(), $name);
            }
        }
        $this->assertSame($before, SecGroup::count());

        $g = $svc->save(null, ['group_code' => ' zz new group ', 'group_name' => '  ZZ New  ', 'mgmt_level' => '3'], $admin);
        $this->assertSame('ZZ_NEW_GROUP', $g->group_code);
        $this->assertSame('ZZ New', $g->group_name);
        $this->assertTrue($g->activate);
        $svc->save($g, ['group_code' => 'IGNORED', 'group_name' => 'ZZ Renamed', 'mgmt_level' => 4], $admin);
        $this->assertSame(['ZZ_NEW_GROUP', 'ZZ Renamed', 4], [$g->fresh()->group_code, $g->fresh()->group_name, $g->fresh()->mgmt_level], 'kode tidak berubah');
        $svc->save($g, ['group_name' => 'ZZ Renamed', 'mgmt_level' => 4], $admin);

        // Anggota aktif menghalangi penonaktifan; anggota nonaktif tidak.
        $member = app(SecurityAccountService::class)->save(null, $this->acct(['group_id' => $g->id_group]), $admin);
        $this->refused(fn () => $svc->setActive($g, false, $admin), 'group', 'masih ada anggota aktif');
        $this->assertTrue($g->fresh()->activate);
        app(SecurityAccountService::class)->setActive($member, false, $admin);
        $svc->setActive($g, false, $admin);
        $this->assertFalse($g->fresh()->activate);
        $svc->setActive($g, true, $admin);
        $this->assertTrue($g->fresh()->activate);
    }

    public function test_screens_routes_grids_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        $acctForm = route('v2.screen', 'security-account-form');
        $grpForm = route('v2.screen', 'security-group-form');
        $this->get(route('v2.screen', 'security-accounts'))->assertOk();
        $this->get(route('v2.screen', 'security-groups'))->assertOk();
        $this->get($acctForm)->assertNotFound();
        $this->get($acctForm.'?id=999999999')->assertNotFound();
        $this->get($grpForm.'?id=999999999')->assertNotFound();
        $this->get($acctForm.'?new=1')->assertOk()->assertSee('name="login"', false)->assertSee('name="password"', false);
        $this->get($grpForm.'?new=1')->assertOk()->assertSee('name="group_code"', false);

        $this->post(route('v2.setup.account.save'), $this->acct(['password' => 'x']))->assertSessionHasErrors('password');
        $this->post(route('v2.setup.account.save'), $this->acct())->assertRedirect();
        $u = SecUser::where('login', 'zz.tester')->firstOrFail();
        $open = $acctForm.'?id='.$u->id_user;
        $this->get($open)->assertOk()->assertSee('zz.tester')->assertSee('name="action" value="reset"', false)->assertDontSee('name="password"', false);
        $this->post(route('v2.setup.account.save'), ['id' => $u->id_user, 'action' => 'reset'])->assertRedirect($open)->assertSessionHas('v2_login_card');
        $this->get($open)->assertOk()->assertSee('name="action" value="reveal"', false);
        $this->post(route('v2.setup.account.save'), ['id' => $u->id_user, 'action' => 'toggle'])->assertRedirect($open);
        $this->assertFalse((bool) $u->fresh()->activate);
        $this->post(route('v2.setup.account.save'), ['id' => $this->admin()->id_user, 'action' => 'toggle'])->assertSessionHasErrors('account');
        $this->post(route('v2.setup.account.save'), ['action' => 'reset'])->assertStatus(422);
        $this->post(route('v2.setup.account.save'), ['id' => $u->id_user, 'action' => 'bogus'])->assertStatus(422);

        $this->post(route('v2.setup.group.save'), ['group_code' => 'zz http', 'group_name' => 'ZZ Http', 'mgmt_level' => 2])->assertRedirect();
        $g = SecGroup::where('group_code', 'ZZ_HTTP')->firstOrFail();
        $this->get($grpForm.'?id='.$g->id_group)->assertOk()->assertSee('ZZ Http')->assertSee('name="action" value="toggle"', false);
        $this->post(route('v2.setup.group.save'), ['id' => $g->id_group, 'action' => 'toggle'])->assertRedirect();
        $this->assertFalse((bool) $g->fresh()->activate);

        // Bukan administrator: ditolak oleh middleware admin.
        $id = DB::connection('tm2sys')->table('sec_user')->insertGetId(['login' => 'tst_sec_plain', 'name' => 'Test plain', 'active' => 1, 'activate' => 1, 'is_admin' => 0]);
        $this->actingAs(SecUser::findOrFail($id));
        $this->post(route('v2.setup.account.save'), $this->acct(['login' => 'zz.other']))->assertForbidden();
        $this->post(route('v2.setup.group.save'), ['group_code' => 'ZZ_X', 'group_name' => 'ZZ X', 'mgmt_level' => 1])->assertForbidden();
        $this->actingAs($this->admin());

        $this->assertStringContainsString('/v2/s/security-account-form?id={id}', Grids::find('security-accounts')->openUrl());
        $this->assertStringContainsString('/v2/s/security-account-form?new=1', Grids::find('security-accounts')->newUrl());
        $this->assertStringContainsString('/v2/s/security-group-form?id={id}', Grids::find('security-groups')->openUrl());
        $this->assertStringContainsString('/v2/s/security-group-form?new=1', Grids::find('security-groups')->newUrl());
        $this->assertFileDoesNotExist(resource_path('views/pages/security/index.blade.php'));
        $this->assertFileExists(base_path('tmerp_unused/security_index_v1_2026-10-20.blade.php'));
        $this->assertStringContainsString('security-accounts', MenuRoutes::map()['SET_USER']);
        $this->get('/security')->assertRedirect('/v2/s/security-accounts');
        $this->get('/security?tab=users&open=7')->assertRedirect('/v2/s/security-account-form?id=7');
        $this->get('/security?tab=users&new=1')->assertRedirect('/v2/s/security-account-form?new=1');
        $this->get('/security?tab=groups')->assertRedirect('/v2/s/security-groups');
        $this->get('/security?tab=groups&open=3')->assertRedirect('/v2/s/security-group-form?id=3');
        $this->get('/security?tab=perms')->assertRedirect('/v2/s/security-permissions');
        $this->get('/security?tab=migration')->assertRedirect('/v2/s/setup-migration');
    }
}
