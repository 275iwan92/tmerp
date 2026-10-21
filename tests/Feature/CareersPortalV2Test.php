<?php

namespace Tests\Feature;

use App\Mail\Portal\CandidateEmailVerification;
use App\Models\Hrr\AppApplication;
use App\Models\Hrr\CndCandidate;
use App\Models\Hrr\RcrSyncLog;
use App\Models\Hrr\VacVacancy;
use App\Models\Master\GeoCity;
use App\Services\Portal\CandidateAuthService;
use App\Services\Recruitment\FirebaseSyncAdapter;
use App\Services\Recruitment\PortalSyncService;
use App\Support\Firestore\FirestoreRestClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/*
 * REMARK
 * Tujuan       : Menguji portal karir publik v2 (CareersController + objek x-mp::careers.*): lowongan, daftar (cek NIK, surel verifikasi, duplikat), masuk, verifikasi, riwayat, form lamar lengkap sampai tersimpan di Firestore, penolakan bila kurang / surel belum diverifikasi, dan status yang ditulis balik HR terbaca di Lamaran Saya.
 * Sumber hulu  : App\Http\Controllers\Portal\CareersController, App\Services\Portal\{CareersPortalService, CandidateApplicationService}, routes/web.php (careers.*).
 * Input        : Akun kandidat uji di Firestore Emulator (surel test.<acak>@example.com, NIK 99...), lowongan terbit pertama di tm2hrr.
 * Output       : Assert respons, sesi, dokumen Firestore 'candidates' / 'submissions', baris AppApplication hasil sinkron; semua dihapus di tearDown.
 * Dipakai oleh : php artisan test --filter=CareersPortalV2Test. Firebase Emulator harus hidup (firebase emulators:start --only firestore dari folder firebase/); bila mati tes di-skip.
 * Status       : Aktif (T10 tahap 6b, 2026-10-21). Menggantikan CareersPortalTest (Livewire v1).
 */
class CareersPortalV2Test extends TestCase
{
    protected string $nationalId;

    protected string $email;

    protected ?string $candidateUid = null;

    protected ?string $submissionUuid = null;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = substr(md5(static::class.microtime()), 0, 8);
        $this->nationalId = '99'.str_pad((string) (crc32($suffix) % 100000000000000), 14, '0', STR_PAD_LEFT);
        $this->email = "test.{$suffix}@example.com";

        $host = config('recruitment.firebase.firestore_emulator_host');
        if (! $host) {
            $this->markTestSkipped('FIRESTORE_EMULATOR_HOST tidak diset di .env.');
        }
        [$ip, $port] = array_pad(explode(':', $host, 2), 2, 80);
        $conn = @fsockopen($ip, (int) $port, $errno, $errstr, 0.5);
        if (! $conn) {
            $this->markTestSkipped("Firebase Emulator tidak menyala di {$host} (firebase emulators:start --only firestore dari folder firebase/).");
        }
        fclose($conn);
    }

    protected function tearDown(): void
    {
        if ($this->app) {
            $client = app(FirestoreRestClient::class);
            if ($this->candidateUid) {
                $client->deleteDocument('candidates', $this->candidateUid);
            }
            if ($this->submissionUuid) {
                $client->deleteDocument('submissions', $this->submissionUuid);
                RcrSyncLog::where('submission_uuid', $this->submissionUuid)->delete();
                AppApplication::where('submission_uuid', $this->submissionUuid)->delete();
            }
            CndCandidate::where('national_id', $this->nationalId)->delete();
        }

        parent::tearDown();
    }

    private function candidate(bool $verified = true): array
    {
        $auth = app(CandidateAuthService::class);
        $c = $auth->register(['national_id' => $this->nationalId, 'name' => 'Test Kandidat', 'email' => $this->email, 'phone' => '081200000000', 'password' => 'password123']);
        $this->candidateUid = $c['_docId'];
        if ($verified) {
            $auth->markEmailVerified($this->candidateUid);
        }

        return $c;
    }

    private function vacancy(): VacVacancy
    {
        return VacVacancy::where('is_published', true)->whereNull('closed_at')->firstOrFail();
    }

    /** Isian POST lengkap seperti yang dikirim form (seksi berulang = baris x-dl::lines). */
    private function fullPayload(): array
    {
        return [
            'name' => 'Test E2E', 'nickname' => 'Uji', 'gender' => 'LAKI_LAKI', 'religion' => 'Islam', 'birth_date' => '2000-01-01', 'phone' => '081200000000',
            'address' => 'Jl. Test No. 1', 'address_domisili' => 'Jl. Test No. 1', 'vehicle_type' => 'MOTOR', 'sim_types' => ['C'], 'marital_status' => 'LAJANG',
            'father' => ['name' => 'Bapak Uji', 'age' => '55'], 'mother' => ['name' => 'Ibu Uji'],
            'family' => [['relation_type' => 'SIBLING', 'name' => 'Adik Uji', 'gender' => 'P', 'age' => '20']],
            'education' => [['level' => 'SMA', 'institution' => 'SMA Test', 'major' => 'IPA', 'period_start_year' => '2015', 'graduation_year' => '2018', 'final_score' => '80']],
            'educationNonformal' => [['course_type' => 'Tidak ada']],
            'orgExperience' => [['organization_name' => 'Tidak ada']],
            'workExp' => [['company' => 'Belum ada pengalaman', 'position' => '-', 'description' => '-']],
            'company_acquaintance' => 'Tidak ada', 'hobby' => 'Membaca', 'leisure_activity' => 'Membaca', 'reading_topics' => 'Berita', 'went_abroad' => 'Tidak pernah',
            'professionalReferences' => [['name' => 'Ref 1', 'phone' => '0811'], ['name' => 'Ref 2', 'phone' => '0812']],
            'emergencyContacts' => [
                ['name' => 'Kontak 1', 'relation' => 'Saudara', 'phone' => '0811', 'address' => 'Malang'],
                ['name' => 'Kontak 2', 'relation' => 'Teman', 'phone' => '0812', 'address' => 'Malang'],
                ['name' => 'Kontak 3', 'relation' => 'Tetangga', 'phone' => '0813', 'address' => 'Malang'],
            ],
            'why_join_us' => 'Ingin berkembang', 'what_you_know_about_us' => 'Percetakan', 'contribution_plan' => 'Maksimal', 'role_plan_if_hired' => 'Sesuai SOP',
            'job_type_preference' => 'Pelaksana', 'job_nature_preference' => 'Teknis', 'work_environment_preference' => 'Pabrik', 'hindering_environment' => 'Tidak ada',
            'preferred_colleague_type' => 'Jujur', 'difficult_decision_topic' => 'Menegur', 'strengths_weaknesses' => 'Rajin; kurang teliti', 'who_you_ask_when_stuck' => 'Atasan',
            'height_cm' => '170', 'weight_kg' => '60', 'blood_type' => 'O', 'illness_history' => 'Tidak ada', 'hospitalized_history' => 'Tidak ada',
            'is_smoker' => '0', 'physical_disability' => 'Tidak ada', 'family_health_ok' => '1',
            'languageSkills' => [['language' => 'Inggris', 'written' => 'CB', 'oral' => 'CB']],
            'computerSkills' => [['skill' => 'MS Office', 'level' => 'B']],
            'still_receiving_aid' => 'Tidak ada', 'other_dependents' => 'Tidak ada', 'source_of_info' => 'Website', 'expected_salary' => '4000000',
            'expected_facility' => 'Tidak ada', 'availability_date' => now()->addWeek()->toDateString(),
            'willingness' => array_fill_keys(['probation', 'training', 'overtime', 'guarantee_docs', 'resign_notice', 'obey_rules', 'resign_if_married_pregnant', 'relocation'], '1'),
            'agreement_signed' => '1', 'signature_name' => 'Test E2E',
            'cv_file' => UploadedFile::fake()->create('cv.pdf', 100), 'ijazah_file' => UploadedFile::fake()->create('ijazah.pdf', 100),
        ];
    }

    public function test_landing_and_vacancy_pages_render_for_guests_and_candidates(): void
    {
        $vacancy = $this->vacancy();

        $this->get('/hrm/recruitment/portal')->assertOk()->assertSee('Lowongan Terbuka')->assertSee($vacancy->vacancy_code);
        $this->get(route('careers.vacancy-detail', $vacancy->vacancy_code))->assertOk()->assertSee('Daftar untuk Melamar')->assertDontSee('Lamar Posisi Ini');
        $this->withSession(['portal_candidate_uid' => 'x'])->get(route('careers.vacancy-detail', $vacancy->vacancy_code))->assertOk()->assertSee('Lamar Posisi Ini');
        $this->get(route('careers.vacancy-detail', 'TIDAK-ADA'))->assertNotFound();
    }

    public function test_register_creates_candidate_sends_verification_and_signs_in(): void
    {
        Mail::fake();
        $nik = '357301010190'.random_int(1000, 9999); // kode wilayah 3573 harus ada di geo_city
        if (! GeoCity::where('city_code', 'bps:3573')->exists()) {
            $this->markTestSkipped('geo_city bps:3573 tidak ada di database ini.');
        }
        $this->nationalId = $nik;

        $post = ['national_id' => $nik, 'name' => 'Test Kandidat', 'email' => $this->email, 'phone' => '081234500000', 'password' => 'RahasiaSekali123', 'password_confirmation' => 'RahasiaSekali123'];

        $this->post(route('careers.register.store'), ['national_id' => '123'] + $post)->assertSessionHasErrors('national_id');
        $this->post(route('careers.register.store'), ['national_id' => '9999999999999999'] + $post)->assertSessionHasErrors('national_id');
        $this->post(route('careers.register.store'), $post)->assertRedirect(route('careers.register'))->assertSessionHas('registered', $this->email);

        $candidate = app(CandidateAuthService::class)->findByEmail($this->email);
        $this->assertNotNull($candidate);
        $this->candidateUid = $candidate['_docId'];
        $this->assertSame($this->candidateUid, session('portal_candidate_uid'));
        Mail::assertSent(CandidateEmailVerification::class);
        $this->get(route('careers.register'))->assertOk()->assertSee('Cek Email Anda');

        $this->post(route('careers.register.store'), $post)->assertSessionHasErrors('email');
    }

    public function test_login_logout_and_intended_url(): void
    {
        $this->candidate();

        $this->post(route('careers.login.attempt'), ['email' => $this->email, 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->assertNull(session('portal_candidate_uid'));

        $this->withSession(['intended_url' => route('careers.apply', $this->vacancy()->vacancy_code)])
            ->post(route('careers.login.attempt'), ['email' => $this->email, 'password' => 'password123'])
            ->assertRedirect(route('careers.apply', $this->vacancy()->vacancy_code));
        $this->assertSame($this->candidateUid, session('portal_candidate_uid'));

        $this->get(route('careers.logout'))->assertRedirect(route('careers.landing'));
        $this->assertNull(session('portal_candidate_uid'));
    }

    public function test_email_verification_link_marks_verified_and_dashboard_requires_login(): void
    {
        $this->candidate(false);
        $auth = app(CandidateAuthService::class);
        $this->assertNull($auth->findByUid($this->candidateUid)['email_verified_at']);

        $this->get(route('careers.dashboard'))->assertRedirect(route('careers.login'));
        $this->get(route('careers.verify-email', $this->candidateUid))->assertForbidden();

        $url = URL::temporarySignedRoute('careers.verify-email', now()->addMinutes(60), ['uid' => $this->candidateUid]);
        $this->get($url)->assertRedirect(route('careers.login'));
        $this->assertNotNull($auth->findByUid($this->candidateUid)['email_verified_at']);

        $this->withSession(['portal_candidate_uid' => $this->candidateUid])->get(route('careers.dashboard'))->assertOk()->assertSee('Belum ada lamaran')->assertDontSee('Email belum diverifikasi');
    }

    public function test_dashboard_warns_when_unverified_and_resend_sends_mail(): void
    {
        Mail::fake();
        $this->candidate(false);

        $this->withSession(['portal_candidate_uid' => $this->candidateUid])->get(route('careers.dashboard'))->assertOk()->assertSee('Email belum diverifikasi')->assertSee('Kirim ulang');
        $this->post(route('careers.resend'))->assertRedirect(route('careers.dashboard'))->assertSessionHas('resent');
        Mail::assertSent(CandidateEmailVerification::class);
        $this->get(route('careers.dashboard'))->assertSee('Terkirim');
    }

    public function test_full_apply_flow_saves_submission_and_hr_status_comes_back(): void
    {
        $this->candidate();
        $vacancy = $this->vacancy();
        $this->withSession(['portal_candidate_uid' => $this->candidateUid]);

        $this->get(route('careers.apply', $vacancy->vacancy_code))->assertOk()->assertSee('name="father[name]"', false)->assertSee('name="cv_file"', false);

        $this->post(route('careers.apply.submit', $vacancy->vacancy_code), $this->fullPayload())->assertSessionHasNoErrors()->assertRedirect(route('careers.apply', $vacancy->vacancy_code));
        $uuid = session('submitted');
        $this->assertNotEmpty($uuid);
        $this->submissionUuid = $uuid;

        $saved = collect(app(FirebaseSyncAdapter::class)->findAllByCandidateUid($this->candidateUid))->firstWhere('submission_uuid', $uuid);
        $this->assertSame($this->nationalId, $saved['national_id']);
        $this->assertSame(['FATHER', 'MOTHER', 'SIBLING'], array_column($saved['family_members'], 'relation_type'));
        $this->assertSame('Bapak Uji', $saved['family_members'][0]['name']);
        $this->assertFalse($saved['is_smoker']);
        $this->assertTrue($saved['family_health_ok']);
        $this->assertSame(4000000.0, (float) $saved['expected_salary']);
        $this->assertTrue($saved['willingness_checklist']['relocation']);
        $this->assertCount(2, $saved['documents']);

        $this->get(route('careers.apply', $vacancy->vacancy_code))->assertSee('Lamaran Terkirim');
        $this->get(route('careers.dashboard'))->assertOk()->assertSee($vacancy->vacancy_code)->assertSee('MENUNGGU SINKRONISASI');

        // Hanya lamaran milik tes ini yang disinkronkan; lamaran lain di emulator (mis. pratinjau) tidak disentuh.
        $only = new class(app(FirestoreRestClient::class), $uuid) extends FirebaseSyncAdapter
        {
            public function __construct(FirestoreRestClient $client, private string $uuid)
            {
                parent::__construct($client);
            }

            public function fetchPendingSubmissions(int $limit): array
            {
                return array_values(array_filter(parent::fetchPendingSubmissions(1000), fn ($s) => $s['submission_uuid'] === $this->uuid));
            }
        };
        $this->assertSame(1, (new PortalSyncService($only))->run()['synced']);
        $application = AppApplication::where('submission_uuid', $uuid)->firstOrFail();
        $this->assertSame('APPLIED', $application->status);

        $application->update(['status' => 'IN_PROGRESS']);
        $this->get(route('careers.dashboard'))->assertSee('DIPROSES');
    }

    public function test_unverified_email_blocks_submission(): void
    {
        $this->candidate(false);
        $vacancy = $this->vacancy();

        $this->withSession(['portal_candidate_uid' => $this->candidateUid])
            ->post(route('careers.apply.submit', $vacancy->vacancy_code), $this->fullPayload())
            ->assertSessionHasErrors('apply');
        $this->assertCount(1, session('errors')->all());
        $this->assertEmpty(app(FirebaseSyncAdapter::class)->findAllByCandidateUid($this->candidateUid));
    }

    public function test_missing_sections_are_rejected_together_with_father_and_mother(): void
    {
        $this->candidate();
        $vacancy = $this->vacancy();

        $this->withSession(['portal_candidate_uid' => $this->candidateUid])
            ->post(route('careers.apply.submit', $vacancy->vacancy_code), ['name' => 'Test'])
            ->assertSessionHasErrors(['father.name', 'mother.name', 'education', 'workExp', 'agreement_signed', 'willingness', 'cv_file', 'ijazah_file']);
        $this->assertEmpty(app(FirebaseSyncAdapter::class)->findAllByCandidateUid($this->candidateUid));

        $payload = $this->fullPayload();
        unset($payload['willingness']['relocation'], $payload['cv_file']);
        $this->post(route('careers.apply.submit', $vacancy->vacancy_code), $payload)->assertSessionHasErrors(['willingness', 'cv_file'])->assertSessionDoesntHaveErrors('education');
    }
}
