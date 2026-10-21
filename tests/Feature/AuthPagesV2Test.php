<?php

namespace Tests\Feature;

use App\Models\Sys\SecLoginAttempt;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysNotification;
use App\Services\Security\SecurityRecoveryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/*
 * REMARK
 * Tujuan       : Menguji halaman tamu dan paksa-ganti v2 (login, daftar, lupa password 3 langkah, ganti password, setup keamanan): isi respons, catatan percobaan login, akun menunggu approval + notifikasi admin, pesan generik, batas percobaan, reset ke default, simpan password/pertanyaan.
 * Sumber hulu  : App\Http\Controllers\Auth\*, App\Services\Auth\*, routes/web.php.
 * Input        : Akun uji sendiri (login 'zz_auth_*'), dibuat dan dihapus di tes.
 * Output       : Assert respons, baris sec_user / sec_login_attempt / sys_notification, sesi.
 * Dipakai oleh : php artisan test --filter=AuthPagesV2Test.
 * Status       : Aktif (T10 tahap 6a, 2026-10-21). Menggantikan AuthPagesSmokeTest (Livewire v1).
 */
class AuthPagesV2Test extends TestCase
{
    private const PASS = 'Abcd1234x';

    /** @var list<int> */
    private array $users = [];

    private string $login = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->login = 'zz_auth_'.substr(uniqid(), -6);
    }

    protected function tearDown(): void
    {
        $sys = DB::connection('tm2sys');
        $sys->table('sec_login_attempt')->where('login', 'like', 'zz_auth_%')->orWhere('login', 'like', 'zz.register.%')->delete();
        SysNotification::where('message', 'like', '%zz.register.%')->delete();
        $sys->table('sec_user')->whereIn('id_user', $this->users)->delete();
        $sys->table('sec_user')->where('email', 'like', 'zz.register.%')->delete();
        RateLimiter::clear('password-recovery:'.$this->login.'|127.0.0.1');

        parent::tearDown();
    }

    private function user(array $extra = []): SecUser
    {
        $u = SecUser::create($extra + ['login' => $this->login, 'pswd' => Hash::make(self::PASS), 'name' => 'ZZ Auth Test', 'email' => $this->login.'@example.com', 'active' => true, 'activate' => true]);
        $this->users[] = $u->id_user;

        return $u;
    }

    public function test_guest_pages_render(): void
    {
        $this->get('/login')->assertOk()->assertSee('dl-loginbox', false);
        $this->get('/register')->assertOk()->assertSee('data-dl-register', false);
        $this->get('/forgot-password')->assertOk()->assertSee('name="login"', false)->assertDontSee('birth_date', false);
    }

    public function test_login_logs_every_attempt_and_signs_in_active_accounts_only(): void
    {
        $this->user();

        $this->post('/login', ['login' => $this->login, 'password' => 'salah'])->assertRedirect()->assertSessionHas('login_error', 'wrong');
        $this->post('/login', ['login' => '', 'password' => ''])->assertSessionHas('login_error', 'empty');
        $this->assertSame(1, SecLoginAttempt::where('login', $this->login)->where('success', false)->count());

        $this->post('/login', ['login' => $this->login.'@example.com', 'password' => self::PASS])->assertRedirect();
        $this->assertAuthenticated();
        $this->assertSame(1, SecLoginAttempt::where('login', $this->login.'@example.com')->where('success', true)->count());

        $this->post('/logout')->assertStatus(405);
        $this->get('/logout')->assertRedirect(route('login'));
        $this->assertGuest();

        SecUser::where('login', $this->login)->update(['active' => false]);
        $this->post('/login', ['login' => $this->login, 'password' => self::PASS])->assertSessionHas('login_error', 'wrong');
        $this->assertGuest();
    }

    public function test_register_creates_a_pending_account_and_notifies_admins(): void
    {
        $email = 'zz.register.'.uniqid().'@example.com';
        $post = ['reg_type' => 'customer', 'name' => 'ZZ Register', 'email' => $email, 'whatsapp' => '081234567890', 'id_no' => '3201011234560001',
            'password' => 'password123', 'password_confirmation' => 'password123'];

        $this->post('/register', ['id_no' => '123'] + $post)->assertSessionHasErrors('id_no');
        $this->post('/register', $post)->assertRedirect(route('register'))->assertSessionHas('registered');

        $user = SecUser::where('email', $email)->firstOrFail();
        $this->assertFalse((bool) $user->active);
        $this->assertFalse((bool) $user->activate);
        $this->assertSame('customer', $user->reg_type);
        $this->assertStringStartsWith('zz.register.', $user->login);
        $this->assertSame(SecUser::where('is_admin', true)->count(), SysNotification::where('message', 'like', '%'.$user->login.'%')->count());

        $this->post('/register', $post)->assertSessionHasErrors('email');
    }

    public function test_forgot_password_flow_is_generic_rate_limited_and_resets_to_the_default_password(): void
    {
        $service = app(SecurityRecoveryService::class);
        $user = $this->user(['phone' => '08123456789', 'default_password' => 'Tmk-Default1']);
        $service->saveSetup($user->refresh(), '1990-05-17', 'Siti Aminah', (int) $service->activeQuestions()->firstOrFail()->id_question, 'Kucing');

        // Akun tidak ada = pesan yang sama dengan akun belum setup (cegah enumerasi).
        $this->post('/forgot-password', ['login' => 'zz_auth_tidak_ada'])->assertSessionHasErrors('recovery');
        $this->post('/forgot-password', ['login' => $this->login])->assertRedirect(route('password.request'));
        $this->get('/forgot-password')->assertOk()->assertSee('birth_date', false);

        $wrong = ['birth_date' => '1990-05-17', 'mother_name' => 'Siti Aminah', 'phone' => '08123456789', 'security_answer' => 'Anjing'];
        $this->post('/forgot-password/verify', $wrong)->assertSessionHasErrors('recovery');
        $this->assertFalse(Hash::check('Tmk-Default1', (string) $user->refresh()->pswd));

        $this->post('/forgot-password/verify', ['security_answer' => 'kucing'] + $wrong)->assertRedirect(route('password.request'));
        $this->assertTrue(Hash::check('Tmk-Default1', (string) $user->refresh()->pswd));
        $this->assertTrue((bool) $user->must_change_password);
        $this->get('/forgot-password')->assertOk()->assertDontSee('birth_date', false)->assertDontSee('name="login"', false);
    }

    public function test_forgot_password_stops_after_too_many_wrong_attempts(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->post('/forgot-password', ['login' => $this->login]);
        }

        $this->post('/forgot-password', ['login' => $this->login])->assertSessionHasErrors('recovery');
        $this->assertTrue(RateLimiter::tooManyAttempts('password-recovery:'.$this->login.'|127.0.0.1', 8));
    }

    public function test_forced_password_change_and_security_setup_save_for_the_signed_in_user(): void
    {
        $user = $this->user(['default_password' => 'Tmk-Default2', 'must_change_password' => true]);
        $this->actingAs($user);

        $this->get('/password/change')->assertOk()->assertSee('password_confirmation', false);
        $this->post('/password/change', ['password' => 'abc12345', 'password_confirmation' => 'zzz'])->assertSessionHasErrors('password');
        $this->post('/password/change', ['password' => 'Tmk-Default2', 'password_confirmation' => 'Tmk-Default2'])->assertSessionHasErrors('password');
        $this->post('/password/change', ['password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])->assertRedirect();
        $user->refresh();
        $this->assertTrue(Hash::check('NewPass123', (string) $user->pswd));
        $this->assertFalse((bool) $user->must_change_password);

        $q = app(SecurityRecoveryService::class)->activeQuestions()->firstOrFail();
        $this->get('/security/setup')->assertOk()->assertSee('question_id', false);
        $this->post('/security/setup', ['birth_date' => '1991-01-02', 'mother_name' => 'Ibu Uji', 'phone' => '081200001111', 'question_id' => $q->id_question, 'answer' => 'Jawab'])->assertRedirect();
        $user->refresh();
        $this->assertNotNull($user->security_setup_at);
        $this->assertSame('081200001111', $user->phone);
        $this->assertSame('1991-01-02', $user->birth_date->toDateString());
    }
}
