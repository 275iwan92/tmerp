<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AuthPagesSmokeTest extends TestCase
{
    protected ?int $createdUserId = null;

    protected function tearDown(): void
    {
        if ($this->createdUserId) {
            DB::connection('tm2sys')->table('sec_user')->where('id_user', $this->createdUserId)->delete();
        }

        parent::tearDown();
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_forgot_password_page_renders(): void
    {
        $this->get('/forgot-password')->assertOk();
    }

    public function test_register_page_renders_and_creates_pending_user(): void
    {
        $this->get('/register')->assertOk();

        $email = 'test.register.'.uniqid().'@example.com';

        Livewire::test('pages::auth.register')
            ->set('ptype', 'customer')
            ->set('name', 'Register Smoke Test')
            ->set('email', $email)
            ->call('verifyEmail')
            ->assertSet('emailVerified', true)
            ->set('phone', '081234567890')
            ->call('sendOtp')
            ->assertSet('showOtp', true)
            ->set('otp', '1234')
            ->call('verifyOtp')
            ->assertSet('waVerified', true)
            ->set('idno', '3201011234560001')
            ->set('pass', 'password123')
            ->set('pass_confirmation', 'password123')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $user = SecUser::where('email', $email)->firstOrFail();
        $this->createdUserId = $user->id_user;

        $this->assertFalse((bool) $user->active);
        $this->assertFalse((bool) $user->is_active);
        $this->assertSame('customer', $user->reg_type);
        $this->assertSame('3201011234560001', $user->id_number);
        $this->assertStringStartsWith('test.register.', $user->login);
    }
}
