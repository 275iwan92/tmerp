<?php

namespace Tests\Feature;

use App\Http\Middleware\ForcePasswordChange;
use App\Models\Sys\SecUser;
use Illuminate\Http\Request;
use Tests\TestCase;

// Jo 2026-10-22: gerbang keamanan login (4 pertanyaan dan paksa ganti password) mati di SEMUA situs sampai Jo bilang UAT selesai (bawaan config/uat.php '*').
class SecuritySetupSkipTest extends TestCase
{
    public function test_security_gates_are_off_everywhere_until_uat_is_finished(): void
    {
        $this->assertSame('*', config('uat.security_setup_skip'));
        $this->assertEmpty(config('uat.accounts'));
        $this->assertTrue(ForcePasswordChange::skipsSecuritySetup('johiwan'));

        config(['uat.security_setup_skip' => '']);
        $this->assertFalse(ForcePasswordChange::skipsSecuritySetup('johiwan'), 'setelah UAT selesai: nilai kosong menyalakan gerbang lagi');
    }

    // Jo 2026-10-22: user tidak dipaksa ganti password (config uat.password_change_skip = '*' bawaan), walau must_change_password = 1.
    public function test_user_flagged_must_change_password_is_not_forced_to_change_it(): void
    {
        $user = new SecUser(['login' => 'zz.nochange']);
        $user->must_change_password = true;
        $request = Request::create('/v2/home');
        $request->setUserResolver(fn () => $user);
        $next = fn () => response('opened');

        $this->assertSame('*', config('uat.password_change_skip'));
        $this->assertSame('opened', (new ForcePasswordChange)->handle($request, $next)->getContent());

        config(['uat.password_change_skip' => '']);
        $this->assertTrue((new ForcePasswordChange)->handle($request, $next)->isRedirect(), 'tanpa saklar, gerbang lama tetap bekerja');
    }
}
