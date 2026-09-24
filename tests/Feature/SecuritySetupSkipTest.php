<?php

namespace Tests\Feature;

use App\Http\Middleware\ForcePasswordChange;
use Tests\TestCase;

// Pelonggaran pertanyaan keamanan hanya untuk UAT (.env.mockup); tmerp.test tidak mengisinya -> gerbang tetap penuh.
class SecuritySetupSkipTest extends TestCase
{
    public function test_live_site_keeps_full_security_setup_and_no_uat_accounts(): void
    {
        $this->assertEmpty(config('uat.security_setup_skip'));
        $this->assertEmpty(config('uat.accounts'));
        $this->assertFalse(ForcePasswordChange::skipsSecuritySetup('johiwan'));
    }
}
