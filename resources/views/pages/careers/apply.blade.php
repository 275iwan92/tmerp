<?php

use App\Models\Hrr\VacVacancy;
use App\Services\Portal\CandidateAuthService;
use App\Services\Recruitment\FirebaseSyncAdapter;
use App\Support\Recruitment\CandidateFormOptions;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

// P3 -- Form Apply (recruitment_tier4.md §4), versi web publik profesional.
// BEDA dari resources/views/pages/recruitment/portal.blade.php (simulasi
// internal): (1) wajib login akun real (middleware portal.candidate), (2)
// identitas (nama/KTP/email) dikunci dari akun terverifikasi -- bukan isi
// bebas, (3) vacancy sudah dipilih lewat route (bukan step terakhir), (4)
// submit lewat FirebaseSyncAdapter LANGSUNG (bukan PortalBackendAdapterInterface
// yang switchable) -- portal profesional ini SENGAJA hardcode Firebase sesuai
// instruksi eksplisit user ("firebase untuk database"), supaya tidak
// tergantung/berubah kalau .env PORTAL_BACKEND diganti utk keperluan simulasi
// internal (portal.blade.php lama, beda konsep akun -- lihat delta doc §7).
//
// REVISI 2026-08-30 (instruksi user): SEMUA seksi tampil sekaligus 1 halaman
// tabular (bukan wizard step-per-step lagi, lihat partial _apply-form-steps),
// dan SEMUA field wajib diisi -- termasuk Nama Ayah & Ibu (baris terkunci
// index 0/1 di $family, lihat mount()), pendidikan, pengalaman kerja, dst,
// "tidak terkecuali". Validasi dikumpulkan 1x lewat Validator::make()->after()
// supaya kandidat lihat SEMUA yang kurang sekaligus (1 alert), bukan
// ketemu satu-satu tiap klik ulang.
new #[Layout('layouts::portal')] #[Title('Apply for Position')] class extends Component
{
    use WithFileUploads;

    public string $vacancy_code;

    public bool $submitted = false;

    // -- Data Pribadi (name/national_id/email dikunci dari akun) --
    public string $name = '';

    public string $nickname = '';

    public string $national_id = '';

    public string $gender = '';

    public string $religion = '';

    public string $email = '';

    public string $phone = '';

    public string $birth_date = '';

    public string $address = '';

    public string $address_domisili = '';

    public string $vehicle_type = '';

    public array $sim_types = [];

    public string $marital_status = '';

    public string $marital_date = '';

    /** Index 0 = Ayah, index 1 = Ibu -- SELALU ada & terkunci (lihat mount()/partial), baris lain bebas ditambah/dihapus. */
    public array $family = [];

    public array $education = [];

    public array $educationNonformal = [];

    public array $orgExperience = [];

    public array $workExp = [];

    public string $company_acquaintance = '';

    public string $hobby = '';

    public string $leisure_activity = '';

    public string $reading_topics = '';

    public string $went_abroad = '';

    public array $emergencyContacts = [];

    public array $professionalReferences = [];

    public string $why_join_us = '';

    public string $what_you_know_about_us = '';

    public string $contribution_plan = '';

    public string $role_plan_if_hired = '';

    public string $job_type_preference = '';

    public string $job_nature_preference = '';

    public string $work_environment_preference = '';

    public string $hindering_environment = '';

    public string $preferred_colleague_type = '';

    public string $difficult_decision_topic = '';

    public string $strengths_weaknesses = '';

    public string $who_you_ask_when_stuck = '';

    public ?int $height_cm = null;

    public ?int $weight_kg = null;

    public string $blood_type = '';

    public string $illness_history = '';

    public string $hospitalized_history = '';

    public ?bool $is_smoker = null;

    public string $physical_disability = '';

    public ?bool $family_health_ok = null;

    public array $languageSkills = [];

    public array $computerSkills = [];

    public string $still_receiving_aid = '';

    public string $other_dependents = '';

    public string $source_of_info = '';

    public ?float $expected_salary = null;

    public string $expected_facility = '';

    public string $availability_date = '';

    public array $willingness = [];

    public bool $agreement_signed = false;

    public string $signature_name = '';

    public $cvFile = null;

    public $ijazahFile = null;

    public ?string $lastSubmissionUuid = null;

    public function mount(string $vacancy_code, CandidateAuthService $auth): void
    {
        $this->vacancy_code = $vacancy_code;
        $this->willingness = array_fill_keys(array_keys(CandidateFormOptions::WILLING_ITEMS), false);
        $this->family = [
            ['relation_type' => 'FATHER', 'name' => '', 'gender' => 'L', 'age' => '', 'last_education' => '', 'current_job' => '', 'current_employer' => ''],
            ['relation_type' => 'MOTHER', 'name' => '', 'gender' => 'P', 'age' => '', 'last_education' => '', 'current_job' => '', 'current_employer' => ''],
        ];

        $candidate = $auth->findByUid(session('portal_candidate_uid'));

        // Prefill identitas dari akun terverifikasi -- terkunci (readonly di
        // partial), TIDAK boleh diketik ulang bebas beda dari saat registrasi.
        $this->name = $candidate['name'] ?? '';
        $this->national_id = $candidate['national_id'] ?? '';
        $this->email = $candidate['email'] ?? '';
        $this->phone = $candidate['phone'] ?? '';

        // Prefill sisanya dari submission TERAKHIR (kalau pernah apply sebelumnya).
        $last = app(FirebaseSyncAdapter::class)->findLatestByNationalId($this->national_id);
        if ($last) {
            foreach (['nickname', 'gender', 'religion', 'birth_date', 'address', 'address_domisili', 'vehicle_type', 'marital_status', 'marital_date',
                'company_acquaintance', 'hobby', 'leisure_activity', 'reading_topics', 'went_abroad', 'why_join_us', 'what_you_know_about_us',
                'contribution_plan', 'role_plan_if_hired', 'job_type_preference', 'job_nature_preference', 'work_environment_preference',
                'hindering_environment', 'preferred_colleague_type', 'difficult_decision_topic', 'strengths_weaknesses', 'who_you_ask_when_stuck',
                'height_cm', 'weight_kg', 'blood_type', 'illness_history', 'hospitalized_history', 'is_smoker', 'physical_disability',
                'family_health_ok', 'still_receiving_aid', 'other_dependents', 'source_of_info', 'expected_salary', 'expected_facility',
                'availability_date', 'agreement_signed', 'signature_name'] as $field) {
                if (array_key_exists($field, $last) && $last[$field] !== null) {
                    $this->{$field} = $last[$field];
                }
            }
            $this->sim_types = $last['sim_types'] ?? [];
            $this->education = $last['education'] ?? [];
            $this->educationNonformal = $last['education_nonformal'] ?? [];
            $this->orgExperience = $last['org_experience'] ?? [];
            $this->workExp = $last['work_experience'] ?? [];
            $this->emergencyContacts = $last['emergency_contacts'] ?? [];
            $this->professionalReferences = $last['professional_references'] ?? [];
            $this->languageSkills = $last['language_skills'] ?? [];
            $this->computerSkills = $last['computer_skills'] ?? [];
            $this->willingness = array_merge($this->willingness, $last['willingness_checklist'] ?? []);

            // Timpa baris Ayah/Ibu terkunci dgn data lama KALAU ada, jangan
            // ganti seluruh array (indeks lain harus tetap FATHER/MOTHER dulu).
            foreach ($last['family_members'] ?? [] as $f) {
                if (($f['relation_type'] ?? null) === 'FATHER') {
                    $this->family[0] = array_merge($this->family[0], $f);
                } elseif (($f['relation_type'] ?? null) === 'MOTHER') {
                    $this->family[1] = array_merge($this->family[1], $f);
                } else {
                    $this->family[] = $f;
                }
            }
        }
    }

    #[Computed]
    public function vacancy()
    {
        return VacVacancy::where('vacancy_code', $this->vacancy_code)->where('is_published', true)->firstOrFail();
    }

    #[Computed]
    public function willingItems(): array
    {
        return CandidateFormOptions::labels(CandidateFormOptions::WILLING_ITEMS);
    }

    #[Computed]
    public function emailVerified(): bool
    {
        $candidate = app(CandidateAuthService::class)->findByUid(session('portal_candidate_uid'));

        return ! empty($candidate['email_verified_at'] ?? null);
    }

    public function addRow(string $prop, array $template): void
    {
        $this->{$prop}[] = $template;
    }

    public function removeRow(string $prop, int $i): void
    {
        // Baris 0/1 Ayah/Ibu di $family terkunci -- tombol hapus memang tidak
        // dirender utk itu di partial, guard ini jaga-jaga kalau dipanggil manual.
        if ($prop === 'family' && in_array($i, [0, 1], true)) {
            return;
        }

        unset($this->{$prop}[$i]);
        $this->{$prop} = array_values($this->{$prop});
    }

    public function submitApplication(): void
    {
        if (! $this->emailVerified) {
            $this->addError('apply', t('Verify your email before submitting an application (check the inbox you used to register).'));

            return;
        }

        $rules = [
            'name' => ['required', 'min:3'], 'nickname' => ['required'], 'gender' => ['required'], 'religion' => ['required'],
            'phone' => ['required'], 'birth_date' => ['required', 'date'], 'address' => ['required'], 'address_domisili' => ['required'],
            'vehicle_type' => ['required'], 'marital_status' => ['required'],
            'marital_date' => [\Illuminate\Validation\Rule::requiredIf($this->marital_status !== '' && $this->marital_status !== 'LAJANG')],

            'family.0.name' => ['required'], 'family.1.name' => ['required'],

            'education' => ['required', 'array', 'min:1'], 'education.*.institution' => ['required'],
            'educationNonformal' => ['required', 'array', 'min:1'], 'educationNonformal.*.course_type' => ['required'],
            'orgExperience' => ['required', 'array', 'min:1'], 'orgExperience.*.organization_name' => ['required'],
            'workExp' => ['required', 'array', 'min:1'], 'workExp.*.company' => ['required'],

            'company_acquaintance' => ['required'], 'hobby' => ['required'], 'leisure_activity' => ['required'],
            'reading_topics' => ['required'], 'went_abroad' => ['required'],
            'professionalReferences' => ['required', 'array', 'min:2'], 'professionalReferences.*.name' => ['required'], 'professionalReferences.*.phone' => ['required'],
            'emergencyContacts' => ['required', 'array', 'min:3'], 'emergencyContacts.*.name' => ['required'],
            'emergencyContacts.*.relation' => ['required'], 'emergencyContacts.*.phone' => ['required'], 'emergencyContacts.*.address' => ['required'],

            'why_join_us' => ['required'], 'what_you_know_about_us' => ['required'], 'contribution_plan' => ['required'],
            'role_plan_if_hired' => ['required'], 'job_type_preference' => ['required'], 'job_nature_preference' => ['required'],
            'work_environment_preference' => ['required'], 'hindering_environment' => ['required'], 'preferred_colleague_type' => ['required'],
            'difficult_decision_topic' => ['required'], 'strengths_weaknesses' => ['required'], 'who_you_ask_when_stuck' => ['required'],

            'height_cm' => ['required', 'numeric'], 'weight_kg' => ['required', 'numeric'], 'blood_type' => ['required'],
            'illness_history' => ['required'], 'hospitalized_history' => ['required'], 'is_smoker' => ['required'],
            'physical_disability' => ['required'], 'family_health_ok' => ['required'],

            'languageSkills' => ['required', 'array', 'min:1'], 'languageSkills.*.language' => ['required'],
            'computerSkills' => ['required', 'array', 'min:1'], 'computerSkills.*.skill' => ['required'],

            'still_receiving_aid' => ['required'], 'other_dependents' => ['required'], 'source_of_info' => ['required'],
            'expected_salary' => ['required', 'numeric'], 'expected_facility' => ['required'], 'availability_date' => ['required', 'date'],

            'agreement_signed' => ['accepted'], 'signature_name' => ['required'],
            'cvFile' => ['required'], 'ijazahFile' => ['required'],
        ];

        $attributes = [
            'family.0.name' => t('Father Name'), 'family.1.name' => t('Mother Name'),
            'education' => t('Formal Education'), 'educationNonformal' => t('Non-Formal Education'), 'orgExperience' => t('Organization Experience'),
            'workExp' => t('Work Experience'), 'professionalReferences' => t('References'), 'emergencyContacts' => t('Emergency Contacts'),
            'languageSkills' => t('Foreign Languages'), 'computerSkills' => t('Computer Skills'), 'cvFile' => t('CV'), 'ijazahFile' => t('Diploma'),
        ];

        $validator = Validator::make($this->all(), $rules, [], $attributes);

        // Cek tambahan yang tak murni "field kosong" -- semua Kesediaan wajib
        // dicentang (instruksi user "tidak terkecuali"), gabung ke bag error
        // yang SAMA supaya kandidat lihat SEMUA kekurangan dalam 1 alert.
        $validator->after(function ($v) {
            if (in_array(false, $this->willingness, true) || count($this->willingness) < count(CandidateFormOptions::WILLING_ITEMS)) {
                $v->errors()->add('willingness', t('All Willingness & Statement points must be checked.'));
            }
        });

        if ($validator->fails()) {
            // Livewire otomatis re-throw ValidationException ini ke $errors
            // bag milik component -- alert banner di view baca dari situ.
            $validator->validate();
        }

        $clean = fn (array $row) => array_map(fn ($v) => $v === '' ? null : $v, $row);

        $payload = [
            'vacancy_code' => $this->vacancy_code,
            'candidate_uid' => session('portal_candidate_uid'),
            'national_id' => $this->national_id,
            'name' => $this->name, 'nickname' => $this->nickname ?: null, 'gender' => $this->gender ?: null,
            'religion' => $this->religion ?: null, 'email' => $this->email, 'phone' => $this->phone,
            'birth_date' => $this->birth_date ?: null, 'address' => $this->address,
            'address_domisili' => $this->address_domisili ?: null, 'vehicle_type' => $this->vehicle_type ?: null,
            'sim_types' => $this->sim_types, 'marital_status' => $this->marital_status ?: null,
            'marital_date' => $this->marital_date ?: null,
            'company_acquaintance' => $this->company_acquaintance ?: null, 'hobby' => $this->hobby ?: null,
            'leisure_activity' => $this->leisure_activity ?: null, 'reading_topics' => $this->reading_topics ?: null,
            'went_abroad' => $this->went_abroad ?: null,
            'emergency_contacts' => array_values($this->emergencyContacts),
            'professional_references' => array_values($this->professionalReferences),
            'why_join_us' => $this->why_join_us ?: null, 'what_you_know_about_us' => $this->what_you_know_about_us ?: null,
            'contribution_plan' => $this->contribution_plan ?: null, 'role_plan_if_hired' => $this->role_plan_if_hired ?: null,
            'job_type_preference' => $this->job_type_preference ?: null, 'job_nature_preference' => $this->job_nature_preference ?: null,
            'work_environment_preference' => $this->work_environment_preference ?: null, 'hindering_environment' => $this->hindering_environment ?: null,
            'preferred_colleague_type' => $this->preferred_colleague_type ?: null, 'difficult_decision_topic' => $this->difficult_decision_topic ?: null,
            'strengths_weaknesses' => $this->strengths_weaknesses ?: null, 'who_you_ask_when_stuck' => $this->who_you_ask_when_stuck ?: null,
            'height_cm' => $this->height_cm, 'weight_kg' => $this->weight_kg, 'blood_type' => $this->blood_type ?: null,
            'illness_history' => $this->illness_history ?: null, 'hospitalized_history' => $this->hospitalized_history ?: null,
            'is_smoker' => $this->is_smoker, 'physical_disability' => $this->physical_disability ?: null,
            'family_health_ok' => $this->family_health_ok,
            'language_skills' => array_values($this->languageSkills),
            'computer_skills' => array_values($this->computerSkills),
            'still_receiving_aid' => $this->still_receiving_aid ?: null, 'other_dependents' => $this->other_dependents ?: null,
            'source_of_info' => $this->source_of_info ?: null, 'expected_salary' => $this->expected_salary,
            'expected_facility' => $this->expected_facility ?: null, 'availability_date' => $this->availability_date ?: null,
            'willingness_checklist' => $this->willingness, 'agreement_signed' => $this->agreement_signed,
            'signature_name' => $this->signature_name, 'agreement_signed_at' => now()->toDateTimeString(),
            'family_members' => array_values(array_map($clean, $this->family)),
            'education' => array_values(array_map($clean, $this->education)),
            'education_nonformal' => array_values(array_map($clean, $this->educationNonformal)),
            'org_experience' => array_values(array_map($clean, $this->orgExperience)),
            'work_experience' => array_values(array_map($clean, $this->workExp)),
        ];

        $this->lastSubmissionUuid = app(FirebaseSyncAdapter::class)->saveSubmission($payload, [
            'cv' => $this->cvFile,
            'ijazah' => $this->ijazahFile,
        ]);

        $this->cvFile = null;
        $this->ijazahFile = null;
        $this->submitted = true;
    }
};
?>

<div class="max-w-[820px] mx-auto my-8 px-5">
    @if (! $submitted)
        <a href="{{ route('careers.vacancy-detail', $vacancy_code) }}" class="text-[12px] font-bold text-muted hover:text-navy">&larr; {{ t('Cancel, back to vacancy details') }}</a>
        <h1 class="text-[18px] font-extrabold text-navy mt-2 mb-1">{{ t('Apply', 'job') }}: {{ $this->vacancy->title }}</h1>
        <p class="text-[11.5px] text-muted mb-4">{{ t('All fields marked * must be filled in before the application can be submitted.') }}</p>

        @unless ($this->emailVerified)
            <div class="bg-[#fff8e6] border border-[#f4e0a3] text-[#8a5a00] text-[11.5px] rounded-md px-3 py-2 mb-4">
                ⚠ {{ t('Your email is not verified yet.') }} {{ t('You can still fill in this form, but cannot submit it until the email is verified (check the inbox you used to register).') }}
            </div>
        @endunless

        @if ($errors->any())
            <div class="bg-[#fbeaea] border border-[#f3c6c6] text-[#a3241f] text-[12px] rounded-md px-4 py-3 mb-4">
                <p class="font-bold mb-1.5">⚠ {{ str_replace(':n', $errors->count(), t(':n fields are not complete yet:')) }}</p>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white border border-[#e6e9ef] rounded-xl p-5">
            @include('pages::careers._apply-form-steps')

            <div class="mt-6 pt-5 border-t border-[#eef1f5]">
                <h2 class="text-sm font-bold text-navy mb-3">{{ t('Submit Application') }}</h2>
                <button type="button" wire:click="submitApplication" wire:loading.attr="disabled" class="w-full bg-[#1f8a4c] text-white text-sm font-bold px-4 py-2.5 rounded-md disabled:opacity-50">
                    <span wire:loading.remove wire:target="submitApplication">{{ t('Submit Application') }}</span>
                    <span wire:loading wire:target="submitApplication">{{ t('Submitting...') }}</span>
                </button>
            </div>
        </div>
    @else
        <div class="bg-white border border-[#e6e9ef] rounded-xl p-8 text-center">
            <p class="text-3xl mb-2">✅</p>
            <h2 class="text-[16px] font-bold text-[#1f8a4c] mb-1">{{ t('Application Submitted') }}</h2>
            <p class="text-[13px] text-muted mb-1">{!! str_replace(':title', '<b>'.e($this->vacancy->title).'</b>', e(t('Thank you, we have received your application for :title.'))) !!}</p>
            @if ($lastSubmissionUuid)
                <p class="text-[10px] text-muted mb-4">{{ t('Ref') }}: {{ $lastSubmissionUuid }}</p>
            @endif
            <div class="flex justify-center gap-3">
                <a href="{{ route('careers.dashboard') }}" class="bg-navy text-white text-[13px] font-bold px-4 py-2 rounded-md">{{ t('View My Applications') }}</a>
                <a href="{{ route('careers.landing') }}" class="border border-[#d7dbe2] text-[13px] font-bold px-4 py-2 rounded-md">{{ t('Other Vacancies') }}</a>
            </div>
        </div>
    @endif
</div>
