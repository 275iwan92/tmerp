<?php

namespace Tests\Feature;

use App\Http\Middleware\ForcePasswordChange;
use App\Models\Sys\SysConstant;
use Tests\TestCase;

// Pelonggaran pertanyaan keamanan (SECURITY_SETUP_SKIP) hanya untuk UAT di ddtsys; database live tidak memilikinya.
class SecuritySetupSkipTest extends TestCase
{
    public function test_live_database_keeps_full_security_setup(): void
    {
        $this->assertNull(SysConstant::text('SECURITY_SETUP_SKIP'));
        $this->assertFalse(ForcePasswordChange::skipsSecuritySetup('johiwan'));
    }
}
