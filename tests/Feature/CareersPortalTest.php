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

        // nationalId/email diisi DULU (murni lokal, tak butuh Firestore) --
        // tearDown() membaca kedua properti typed ini tanpa syarat, jadi kalau
        // skip check di bawah dijalankan SEBELUM baris ini, tearDown() pecah
        // dgn "must not be accessed before initialization" (ditemukan nyata
        // saat verifikasi perubahan ini).
        $suffix = substr(md5(static::class.microtime()), 0, 8);
        $this->nationalId = '99'.str_pad((string) (crc32($suffix) % 100000000000000), 14, '0', STR_PAD_LEFT);
        $this->email = "test.{$suffix}@example.com";

        // Skip (bukan FAIL) kalau Firebase Local Emulator Suite tidak menyala --
        // instruksi user 2026-09-03: "gimana supaya kedepan ini tidak terjadi".
        // Sengaja TIDAK mock FirestoreRestClient (risiko mock menyimpang dari
        // perilaku Firestore asli, kasih rasa aman palsu) -- test ini exercise
        // integrasi REAL begitu emulator hidup, cuma di-skip rapi (bukan
        // "cURL error 7" yang bikin bingung) kalau emulator memang belum
        // dinyalakan manual (`firebase emulators:start --only firestore,auth`
        // dari folder firebase/, lihat CLAUDE.md §2).
        $this->skipUnlessFirestoreEmulatorReachable();

        $this->withSession([]); // pastikan session store terpasang di request test (register()/login() panggil request()->session())
    }

    protected function skipUnlessFirestoreEmulatorReachable(): void
    {
        $host = config('recruitment.firebase.firestore_emulator_host');

        if (! $host) {
            $this->markTestSkipped('FIRESTORE_EMULATOR_HOST tidak diset di .env -- lihat CLAUDE.md §2.');
        }

        [$ip, $port] = array_pad(explode(':', $host, 2), 2, 80);
        $conn = @fsockopen($ip, (int) $port, $errno, $errstr, 0.5);

        if (! $conn) {
            $this->markTestSkipped(
                "Firebase Local Emulator Suite tidak menyala di {$host} -- jalankan ".
                "'firebase emulators:start --only firestore,auth' dari folder firebase/ dulu ".
                '(CLAUDE.md §2, sengaja TIDAK auto-start).'
            );
        }

        fclose($conn);
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

        $this->fillAllRequiredApplyFields($apply);

        $apply->call('submitApplication')->assertHasNoErrors();

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

    public function test_missing_father_mother_name_and_other_required_sections_are_rejected(): void
    {
        $auth = app(CandidateAuthService::class);
        $candidate = $auth->register([
            'national_id' => $this->nationalId, 'name' => 'Test Validasi', 'email' => $this->email, 'phone' => '081200000000', 'password' => 'password123',
        ]);
        $this->candidateUid = $candidate['_docId'];
        $auth->markEmailVerified($this->candidateUid);

        $vacancy = VacVacancy::where('is_published', true)->firstOrFail();
        $this->withSession(['portal_candidate_uid' => $this->candidateUid]);

        $apply = Livewire::test('pages::careers.apply', ['vacancy_code' => $vacancy->vacancy_code]);

        // Family index 0/1 (Ayah/Ibu) pra-terisi kosong dari mount() -- kirim
        // tanpa mengisi apa pun harus ditolak spesifik krn nama Ayah & Ibu kosong.
        $this->assertSame('', $apply->get('family')[0]['name']);
        $this->assertSame('', $apply->get('family')[1]['name']);

        $apply->call('submitApplication')
            ->assertHasErrors(['family.0.name', 'family.1.name', 'education', 'workExp', 'agreement_signed']);

        $this->assertNull($apply->get('lastSubmissionUuid'));
    }

    protected function fillAllRequiredApplyFields($apply): void
    {
        $file = fn (string $name) => \Illuminate\Http\UploadedFile::fake()->create($name, 100);

        $apply->set('family.0.name', 'Bapak Uji')
            ->set('family.1.name', 'Ibu Uji')
            ->set('nickname', 'Uji')->set('gender', 'LAKI_LAKI')->set('religion', 'Islam')
            ->set('birth_date', '2000-01-01')->set('address', 'Jl. Test No. 1')->set('address_domisili', 'Jl. Test No. 1')
            ->set('vehicle_type', 'MOTOR')->set('marital_status', 'LAJANG')
            ->set('education', [['level' => 'SMA', 'institution' => 'SMA Test', 'major' => 'IPA', 'period_start_year' => 2015, 'graduation_year' => 2018, 'final_score' => 80]])
            ->set('educationNonformal', [['course_type' => 'Tidak ada', 'institution' => '-', 'period_start_year' => '', 'period_end_year' => '', 'certificate_no' => '']])
            ->set('orgExperience', [['organization_name' => 'Tidak ada', 'position' => '-', 'period' => '-', 'task_description' => '-']])
            ->set('workExp', [['company' => 'Belum ada pengalaman', 'position' => '-', 'start_date' => '', 'end_date' => '', 'description' => '-']])
            ->set('company_acquaintance', 'Tidak ada')->set('hobby', 'Membaca')->set('leisure_activity', 'Membaca')
            ->set('reading_topics', 'Berita')->set('went_abroad', 'Tidak pernah')
            ->set('professionalReferences', [['name' => 'Ref 1', 'phone' => '0811'], ['name' => 'Ref 2', 'phone' => '0812']])
            ->set('emergencyContacts', [
                ['name' => 'Kontak 1', 'relation' => 'Saudara', 'phone' => '0811', 'address' => 'Malang'],
                ['name' => 'Kontak 2', 'relation' => 'Teman', 'phone' => '0812', 'address' => 'Malang'],
                ['name' => 'Kontak 3', 'relation' => 'Tetangga', 'phone' => '0813', 'address' => 'Malang'],
            ])
            ->set('why_join_us', 'Ingin berkembang')->set('what_you_know_about_us', 'Perusahaan percetakan')
            ->set('contribution_plan', 'Kontribusi maksimal')->set('role_plan_if_hired', 'Bekerja sesuai SOP')
            ->set('job_type_preference', 'Pelaksana')->set('job_nature_preference', 'Teknis')
            ->set('work_environment_preference', 'Pabrik')->set('hindering_environment', 'Tidak ada')
            ->set('preferred_colleague_type', 'Jujur')->set('difficult_decision_topic', 'Menegur bawahan')
            ->set('strengths_weaknesses', 'Kelebihan: rajin. Kekurangan: kurang teliti')->set('who_you_ask_when_stuck', 'Atasan')
            ->set('height_cm', 170)->set('weight_kg', 60)->set('blood_type', 'O')
            ->set('illness_history', 'Tidak ada')->set('hospitalized_history', 'Tidak ada')
            ->set('is_smoker', 0)->set('physical_disability', 'Tidak ada')->set('family_health_ok', 1)
            ->set('languageSkills', [['language' => 'Inggris', 'written' => 'CB', 'oral' => 'CB', 'notes' => '']])
            ->set('computerSkills', [['skill' => 'MS Office', 'level' => 'B', 'notes' => '']])
            ->set('still_receiving_aid', 'Tidak ada')->set('other_dependents', 'Tidak ada')
            ->set('source_of_info', 'Website')->set('expected_salary', 4000000)->set('expected_facility', 'Tidak ada')
            ->set('availability_date', now()->addWeek()->toDateString())
            ->set('willingness', array_fill_keys(array_keys([
                'probation', 'training', 'overtime', 'guarantee_docs', 'resign_notice', 'obey_rules', 'resign_if_married_pregnant', 'relocation',
            ]), true))
            ->set('agreement_signed', true)->set('signature_name', 'Test E2E')
            ->set('cvFile', $file('cv.pdf'))->set('ijazahFile', $file('ijazah.pdf'));
    }
}
