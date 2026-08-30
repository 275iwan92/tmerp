<?php

namespace Tests\Feature;

use App\Mail\Portal\CandidateEmailVerification;
use App\Models\Hrr\AppApplication;
use App\Models\Hrr\CndCandidate;
use App\Models\Hrr\VacVacancy;
use App\Services\Portal\CandidateAuthService;
use App\Services\Recruitment\FirebaseSyncAdapter;
use App\Services\Recruitment\PortalSyncService;
use App\Support\Firestore\FirestoreRestClient;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

// Portal Rekrutmen Profesional (docs/recruitmentportal_web_tier1.md) --
// backend Firebase (Firestore emulator WAJIB jalan, lihat firebase/README
// singkat di delta doc: `firebase emulators:start --only firestore,auth`
// dari folder firebase/). Test ini butuh emulator hidup -- kalau tidak,
// semua test di kelas ini gagal dgn connection refused (bukan bug kode).
class CareersPortalTest extends TestCase
{
    protected string $nationalId;

    protected string $email;

    protected ?string $candidateUid = null;

    protected ?string $submissionUuid = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([]); // pastikan session store terpasang di request test (register()/login() panggil request()->session())

        $suffix = substr(md5(static::class.microtime()), 0, 8);
        $this->nationalId = '99'.str_pad((string) (crc32($suffix) % 100000000000000), 14, '0', STR_PAD_LEFT);
        $this->email = "test.{$suffix}@example.com";
    }

    protected function tearDown(): void
    {
        $client = app(FirestoreRestClient::class);

        if ($this->candidateUid) {
            $client->deleteDocument('candidates', $this->candidateUid);
        }
        if ($this->submissionUuid) {
            $client->deleteDocument('submissions', $this->submissionUuid);
            \App\Models\Hrr\RcrSyncLog::where('submission_uuid', $this->submissionUuid)->delete();
            AppApplication::where('submission_uuid', $this->submissionUuid)->delete();
        }
        CndCandidate::where('national_id', $this->nationalId)->delete();

        parent::tearDown();
    }

    public function test_landing_page_lists_published_vacancies(): void
    {
        $this->get('/hrm/recruitment/portal')->assertOk()->assertSee('Lowongan Terbuka');
    }

    public function test_register_creates_candidate_and_sends_verification_email(): void
    {
        Mail::fake();

        $test = Livewire::test('pages::careers.register')
            ->set('national_id', $this->nationalId)
            ->set('name', 'Test Kandidat')
            ->set('email', $this->email)
            ->set('phone', '081234500000')
            ->set('password', 'RahasiaSekali123')
            ->set('password_confirmation', 'RahasiaSekali123')
            ->call('register');

        $test->assertHasNoErrors();
        $this->assertTrue($test->get('registered'));

        $candidate = app(CandidateAuthService::class)->findByEmail($this->email);
        $this->assertNotNull($candidate);
        $this->candidateUid = $candidate['_docId'];

        Mail::assertSent(CandidateEmailVerification::class);
    }

    public function test_duplicate_national_id_is_rejected(): void
    {
        $auth = app(CandidateAuthService::class);
        $candidate = $auth->register([
            'national_id' => $this->nationalId, 'name' => 'Kandidat A', 'email' => $this->email, 'phone' => '081200000001', 'password' => 'password123',
        ]);
        $this->candidateUid = $candidate['_docId'];

        $test = Livewire::test('pages::careers.register')
            ->set('national_id', $this->nationalId)
            ->set('name', 'Kandidat B')
            ->set('email', 'lain.'.$this->email)
            ->set('phone', '081200000002')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register');

        $test->assertHasErrors('email');
    }

    public function test_login_with_wrong_password_fails(): void
    {
        $auth = app(CandidateAuthService::class);
        $candidate = $auth->register([
            'national_id' => $this->nationalId, 'name' => 'Test', 'email' => $this->email, 'phone' => '08', 'password' => 'password123',
        ]);
        $this->candidateUid = $candidate['_docId'];

        Livewire::test('pages::careers.login')
            ->set('email', $this->email)
            ->set('password', 'salah')
            ->call('login')
            ->assertHasErrors('email');
    }

    public function test_email_verification_link_marks_verified(): void
    {
        $auth = app(CandidateAuthService::class);
        $candidate = $auth->register([
            'national_id' => $this->nationalId, 'name' => 'Test', 'email' => $this->email, 'phone' => '08', 'password' => 'password123',
        ]);
        $this->candidateUid = $candidate['_docId'];

        $this->assertNull($auth->findByUid($this->candidateUid)['email_verified_at']);

        $url = URL::temporarySignedRoute('careers.verify-email', now()->addMinutes(60), ['uid' => $this->candidateUid]);
        $this->get($url)->assertRedirect(route('careers.login'));

        $this->assertNotNull($auth->findByUid($this->candidateUid)['email_verified_at']);
    }

    public function test_dashboard_requires_login(): void
    {
        $this->get('/hrm/recruitment/portal/lamaran-saya')->assertRedirect(route('careers.login'));
    }

    public function test_full_apply_flow_syncs_to_internal_and_status_pushes_back(): void
    {
        $auth = app(CandidateAuthService::class);
        $candidate = $auth->register([
            'national_id' => $this->nationalId, 'name' => 'Test E2E', 'email' => $this->email, 'phone' => '081200000000', 'password' => 'password123',
        ]);
        $this->candidateUid = $candidate['_docId'];
        $auth->markEmailVerified($this->candidateUid);

        $vacancy = VacVacancy::where('is_published', true)->firstOrFail();

        $this->withSession(['portal_candidate_uid' => $this->candidateUid]);

        $apply = Livewire::test('pages::careers.apply', ['vacancy_code' => $vacancy->vacancy_code]);
        $this->assertTrue($apply->get('emailVerified'));
        $this->assertSame('Test E2E', $apply->get('name'));

        $apply->set('agreement_signed', true)
            ->set('signature_name', 'Test E2E')
            ->set('address', 'Jl. Test')
            ->call('submitApplication')
            ->assertHasNoErrors();

        $this->submissionUuid = $apply->get('lastSubmissionUuid');
        $this->assertNotNull($this->submissionUuid);

        // Dashboard sebelum sync -- masih 'pending', internal belum tahu.
        $dashboard = Livewire::test('pages::careers.dashboard');
        $subs = collect($dashboard->get('submissions'));
        $this->assertTrue($subs->contains('submission_uuid', $this->submissionUuid));

        // Sync worker.
        $stats = app(PortalSyncService::class)->run();
        $this->assertSame(1, $stats['synced']);

        $application = AppApplication::where('submission_uuid', $this->submissionUuid)->first();
        $this->assertNotNull($application);
        $this->assertSame('APPLIED', $application->status);

        // HR ubah status (simulasi Pipeline Board) -> observer HARUS push balik ke Firestore.
        $application->update(['status' => 'IN_PROGRESS']);

        $dashboard2 = Livewire::test('pages::careers.dashboard');
        $subs2 = collect($dashboard2->get('submissions'));
        $mine = $subs2->firstWhere('submission_uuid', $this->submissionUuid);
        $this->assertSame('IN_PROGRESS', $mine['app_status'] ?? null);
    }

    public function test_unverified_email_blocks_submission(): void
    {
        $auth = app(CandidateAuthService::class);
        $candidate = $auth->register([
            'national_id' => $this->nationalId, 'name' => 'Belum Verif', 'email' => $this->email, 'phone' => '08', 'password' => 'password123',
        ]);
        $this->candidateUid = $candidate['_docId'];

        $vacancy = VacVacancy::where('is_published', true)->firstOrFail();
        $this->withSession(['portal_candidate_uid' => $this->candidateUid]);

        $apply = Livewire::test('pages::careers.apply', ['vacancy_code' => $vacancy->vacancy_code]);
        $this->assertFalse($apply->get('emailVerified'));

        $apply->set('agreement_signed', true)->set('signature_name', 'X')
            ->call('submitApplication')
            ->assertHasErrors('apply');

        $this->assertNull($apply->get('lastSubmissionUuid'));
    }
}
