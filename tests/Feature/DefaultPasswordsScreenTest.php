<?php

/*
 * REMARK
 * Tujuan       : Menjaga layar Default Passwords (SEMENTARA UAT): login di password_viewers melihat daftar (tanpa password di HTML), password hanya lewat reveal, reset memakai layanan yang ada dan tercatat di audit; login lain 403 di ketiga rute; respons no-store; password tidak ikut log.
 * Sumber hulu  : routes/v2_setup.php (v2.setup.default-passwords.*), DefaultPasswordViewer, layar v2.default-passwords.
 * Input        : Akun uji ZZ dibuat dalam transaksi tm2sys yang di-rollback; password contoh fiktif; config uat.password_viewers diatur per tes.
 * Output       : Lulus bila gerbang, no-store, audit dan reset berlaku. CATATAN: memakai database tm2sys (seperti SecurityAccountGroupServiceTest), jalankan hanya di lingkungan mockup.
 * Dipakai oleh : php artisan test --env=mockup --filter=DefaultPasswordsScreenTest
 * Status       : Aktif, SEMENTARA (dihapus bersama layar setelah UAT).
 */

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DefaultPasswordsScreenTest extends TestCase
{
    private const PW = 'Contoh-Fiktif-123';

    private SecUser $viewer;

    private SecUser $target;

    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('tm2sys')->beginTransaction();
        $this->viewer = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->target = SecUser::create(['login' => 'zz.pwtest', 'name' => 'ZZ Pw Test', 'pswd' => Hash::make('lain-lagi'), 'default_password' => self::PW, 'active' => true, 'activate' => true]);
        config(['uat.password_viewers' => 'johiwan']);
    }

    protected function tearDown(): void
    {
        while (DB::connection('tm2sys')->transactionLevel() > 0) {
            DB::connection('tm2sys')->rollBack();
        }
        parent::tearDown();
    }

    public function test_viewer_sees_list_without_passwords_and_no_store(): void
    {
        $r = $this->actingAs($this->viewer)->get(route('v2.setup.default-passwords.index'));
        $r->assertOk()->assertSee('zz.pwtest')->assertDontSee(self::PW);
        $this->assertStringContainsString('no-store', (string) $r->headers->get('Cache-Control'));
    }

    public function test_other_login_is_refused_on_every_route(): void
    {
        $other = SecUser::where('is_admin', true)->where('login', '<>', 'johiwan')->first();
        $this->actingAs($other ?? $this->target);
        $this->get(route('v2.setup.default-passwords.index'))->assertForbidden();
        $this->postJson(route('v2.setup.default-passwords.reveal'), ['ids' => [$this->target->id_user]])->assertForbidden();
        $this->post(route('v2.setup.default-passwords.reset', $this->target->id_user))->assertForbidden();
        config(['uat.password_viewers' => '']);
        $this->actingAs($this->viewer)->get(route('v2.setup.default-passwords.index'))->assertForbidden();
    }

    public function test_reveal_and_reset_are_logged_without_the_password(): void
    {
        $logged = [];
        Log::shouldReceive('channel')->with('password_audit')->andReturnSelf();
        Log::shouldReceive('info')->andReturnUsing(function ($msg, $ctx = []) use (&$logged) {
            $logged[] = [$msg, $ctx];
        });
        $this->actingAs($this->viewer);
        $this->postJson(route('v2.setup.default-passwords.reveal'), ['ids' => [$this->target->id_user]])->assertOk()->assertJsonPath('passwords.'.$this->target->id_user, self::PW);
        $this->post(route('v2.setup.default-passwords.reset', $this->target->id_user))->assertRedirect();
        $this->assertSame(['default-passwords.reveal', 'default-passwords.reset'], array_column($logged, 0));
        $this->assertStringNotContainsString(self::PW, json_encode($logged));
        $this->assertSame('zz.pwtest', $logged[1][1]['target']);
        $this->assertTrue(Hash::check(self::PW, $this->target->fresh()->pswd));
        $this->assertTrue((bool) $this->target->fresh()->must_change_password);
    }

    public function test_account_without_default_password_is_not_reset(): void
    {
        $this->target->update(['default_password' => null]);
        $this->actingAs($this->viewer)->post(route('v2.setup.default-passwords.reset', $this->target->id_user))->assertSessionHasErrors('account');
        $this->postJson(route('v2.setup.default-passwords.reveal'), ['ids' => [$this->target->id_user]])->assertOk()->assertJsonPath('passwords', []);
    }
}
