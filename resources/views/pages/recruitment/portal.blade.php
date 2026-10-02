<?php

use App\Contracts\Recruitment\PortalBackendAdapterInterface;
use App\Models\Hrr\AppApplication;
use App\Models\Hrr\CndCandidate;
use App\Models\Hrr\VacVacancy;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

// "Portal Candidate" -- SIMULASI portal kandidat (P1-P4), dibangun DI DALAM
// repo tmerp ini dulu (lihat docs/recruitment_state.yaml simulasi_portal_sementara).
// REVISI 2026-08-29: form Apply (step 1-11) dirombak total mengikuti PERSIS
// formulir resmi HR "DATA CALON KARYAWAN" (No. Dok LJM/HRD/P01/F02, contoh
// terisi diberikan user) -- field & urutan section sesuai dokumen itu, bukan
// dikira-kira. Skema pendukung: migration 2026_08_29_000010_expand_candidate_form_fields.php.
// Auth tetap disederhanakan (masuk via No. KTP, tanpa password/OTP sungguhan).
//
// REVISI 2026-08-30 (docs/recruitment_delta_plan_ab_local.md): submitApplication()
// TIDAK LAGI menulis langsung ke tm2hrr (Cnd*/AppApplication) -- sekarang lewat
// PortalBackendAdapterInterface (Plan A Firebase / Plan B tm2crp MariaDB, switch
// via config('recruitment.backend')), sesuai arsitektur 2-host terkunci di
// recruitment_tier3.md §1. Internal (tm2hrr) baru terisi setelah sync worker
// (php artisan recruitment:sync-portal, jadwal tiap 2 menit) menariknya --
// myApplications() karena itu bisa tampil KOSONG sesaat setelah kirim lamaran,
// itu bukan bug, itu memang eventual-consistency dari desain PULL sync.
new #[Title('Portal Candidate (Simulasi) — TELASIH MAKMUR ERP')] class extends Component
{
    use WithFileUploads;

    public int $step = 0; // 0=masuk .. 12=review/lamar, 13=selesai

    public string $loginKtp = '';

    // -- Data Pribadi --
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

    // -- Data Keluarga --
    public string $marital_status = '';

    public string $marital_date = '';

    public array $family = [];

    // -- Pendidikan --
    public array $education = [];

    public array $educationNonformal = [];

    // -- Organisasi --
    public array $orgExperience = [];

    // -- Pengalaman Kerja --
    public array $workExp = [];

    // -- Aktivitas Sosial & Referensi --
    public string $company_acquaintance = '';

    public string $hobby = '';

    public string $leisure_activity = '';

    public string $reading_topics = '';

    public string $went_abroad = '';

    public array $emergencyContacts = [];

    public array $professionalReferences = [];

    // -- Minat & Konsep Pribadi --
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

    // -- Riwayat Kesehatan --
    public ?int $height_cm = null;

    public ?int $weight_kg = null;

    public string $blood_type = '';

    public string $illness_history = '';

    public string $hospitalized_history = '';

    public ?bool $is_smoker = null;

    public string $physical_disability = '';

    public ?bool $family_health_ok = null;

    // -- Bahasa, Komputer, Info Lowongan --
    public array $languageSkills = [];

    public array $computerSkills = [];

    public string $still_receiving_aid = '';

    public string $other_dependents = '';

    public string $source_of_info = '';

    public ?float $expected_salary = null;

    public string $expected_facility = '';

    public string $availability_date = '';

    // -- Kesediaan & Pernyataan --
    public array $willingness = [];

    public bool $agreement_signed = false;

    public string $signature_name = '';

    // -- Dokumen --
    public $cvFile = null;

    public $ijazahFile = null;

    // -- Lamar --
    public ?int $applyVacancyId = null;

    public ?int $submittedApplicationId = null;

    public ?string $lastSubmissionUuid = null;

    protected const WILLING_ITEMS = [
        'probation' => 'Undergo a probation period',
        'training' => 'Attend training according to Company rules',
        'overtime' => 'Work overtime (including Saturday/Sunday/public holidays)',
        'guarantee_docs' => 'Hand over a Diploma/BPKB as guarantee if required',
        'resign_notice' => 'Submit a written request 1 (one) month in advance when intending to resign',
        'obey_rules' => 'Comply with all applicable company regulations',
        'resign_if_married_pregnant' => 'Willing to resign if marrying a coworker or becoming pregnant before 1 (one) year of service',
        'relocation' => 'Willing to be transferred/assigned to another department/branch at any time',
    ];

    public function mount(): void
    {
        $this->willingness = array_fill_keys(array_keys(self::WILLING_ITEMS), false);
    }

    // Resolve lewat container tiap kali dipakai (bukan property Livewire --
    // service adapter tak aman diserialisasi antar-request). Lihat catatan
    // revisi 2026-08-30 di atas.
    protected function adapter(): PortalBackendAdapterInterface
    {
        return app(PortalBackendAdapterInterface::class);
    }

    #[Computed]
    public function willingItems(): array
    {
        return self::WILLING_ITEMS;
    }

    #[Computed]
    public function openVacancies()
    {
        return VacVacancy::where('is_published', true)->whereNull('closed_at')->orderByDesc('id_vacancy')->get();
    }

    #[Computed]
    public function myApplications()
    {
        // Baca dari internal (tm2hrr) -- HANYA terisi setelah sync worker
        // menariknya dari backend portal aktif. Sebelum sync jalan, kandidat
        // yang baru saja submit belum punya id_candidate di sini -- lihat
        // catatan "eventual consistency" di komentar atas class ini.
        $candidate = CndCandidate::where('national_id', $this->national_id)->first();

        if (! $candidate) {
            return collect();
        }

        return AppApplication::with('vacancy')->where('id_candidate', $candidate->id_candidate)->orderByDesc('id_application')->get();
    }

    public function enter(): void
    {
        if (trim($this->loginKtp) === '') {
            $this->addError('login', t('ID card number is required.'));

            return;
        }

        // Prefill dari submission TERAKHIR di backend portal aktif (bukan
        // tm2hrr) -- portal tidak pernah baca internal langsung, sesuai
        // arsitektur 2-host terkunci (recruitment_tier3.md §1).
        $last = $this->adapter()->findLatestByNationalId($this->loginKtp);

        if ($last) {
            $this->name = (string) ($last['name'] ?? '');
            $this->nickname = (string) ($last['nickname'] ?? '');
            $this->national_id = (string) $last['national_id'];
            $this->gender = (string) ($last['gender'] ?? '');
            $this->religion = (string) ($last['religion'] ?? '');
            $this->email = (string) ($last['email'] ?? '');
            $this->phone = (string) ($last['phone'] ?? '');
            $this->birth_date = (string) ($last['birth_date'] ?? '');
            $this->address = (string) ($last['address'] ?? '');
            $this->address_domisili = (string) ($last['address_domisili'] ?? '');
            $this->vehicle_type = (string) ($last['vehicle_type'] ?? '');
            $this->sim_types = $last['sim_types'] ?? [];
            $this->marital_status = (string) ($last['marital_status'] ?? '');
            $this->marital_date = (string) ($last['marital_date'] ?? '');

            $this->family = $last['family_members'] ?? [];
            $this->education = $last['education'] ?? [];
            $this->educationNonformal = $last['education_nonformal'] ?? [];
            $this->orgExperience = $last['org_experience'] ?? [];
            $this->workExp = $last['work_experience'] ?? [];

            $this->company_acquaintance = (string) ($last['company_acquaintance'] ?? '');
            $this->hobby = (string) ($last['hobby'] ?? '');
            $this->leisure_activity = (string) ($last['leisure_activity'] ?? '');
            $this->reading_topics = (string) ($last['reading_topics'] ?? '');
            $this->went_abroad = (string) ($last['went_abroad'] ?? '');
            $this->emergencyContacts = $last['emergency_contacts'] ?? [];
            $this->professionalReferences = $last['professional_references'] ?? [];

            $this->why_join_us = (string) ($last['why_join_us'] ?? '');
            $this->what_you_know_about_us = (string) ($last['what_you_know_about_us'] ?? '');
            $this->contribution_plan = (string) ($last['contribution_plan'] ?? '');
            $this->role_plan_if_hired = (string) ($last['role_plan_if_hired'] ?? '');
            $this->job_type_preference = (string) ($last['job_type_preference'] ?? '');
            $this->job_nature_preference = (string) ($last['job_nature_preference'] ?? '');
            $this->work_environment_preference = (string) ($last['work_environment_preference'] ?? '');
            $this->hindering_environment = (string) ($last['hindering_environment'] ?? '');
            $this->preferred_colleague_type = (string) ($last['preferred_colleague_type'] ?? '');
            $this->difficult_decision_topic = (string) ($last['difficult_decision_topic'] ?? '');
            $this->strengths_weaknesses = (string) ($last['strengths_weaknesses'] ?? '');
            $this->who_you_ask_when_stuck = (string) ($last['who_you_ask_when_stuck'] ?? '');

            $this->height_cm = $last['height_cm'] ?? null;
            $this->weight_kg = $last['weight_kg'] ?? null;
            $this->blood_type = (string) ($last['blood_type'] ?? '');
            $this->illness_history = (string) ($last['illness_history'] ?? '');
            $this->hospitalized_history = (string) ($last['hospitalized_history'] ?? '');
            $this->is_smoker = $last['is_smoker'] ?? null;
            $this->physical_disability = (string) ($last['physical_disability'] ?? '');
            $this->family_health_ok = $last['family_health_ok'] ?? null;

            $this->languageSkills = $last['language_skills'] ?? [];
            $this->computerSkills = $last['computer_skills'] ?? [];
            $this->still_receiving_aid = (string) ($last['still_receiving_aid'] ?? '');
            $this->other_dependents = (string) ($last['other_dependents'] ?? '');
            $this->source_of_info = (string) ($last['source_of_info'] ?? '');
            $this->expected_salary = $last['expected_salary'] ?? null;
            $this->expected_facility = (string) ($last['expected_facility'] ?? '');
            $this->availability_date = (string) ($last['availability_date'] ?? '');

            $this->willingness = array_merge($this->willingness, $last['willingness_checklist'] ?? []);
            $this->agreement_signed = (bool) ($last['agreement_signed'] ?? false);
            $this->signature_name = (string) ($last['signature_name'] ?? '');
        } else {
            $this->national_id = $this->loginKtp;
        }

        $this->step = 1;
    }

    // -- helper generik tambah/hapus baris repeatable --
    public function addRow(string $prop, array $template): void
    {
        $this->{$prop}[] = $template;
    }

    public function removeRow(string $prop, int $i): void
    {
        unset($this->{$prop}[$i]);
        $this->{$prop} = array_values($this->{$prop});
    }

    public function nextStep(): void
    {
        if ($this->step === 1 && trim($this->name) === '') {
            $this->addError('name', t('Name is required.'));

            return;
        }

        if ($this->step === 12 && ! $this->applyVacancyId) {
            $this->addError('apply', t('Select the vacancy you are applying for.'));

            return;
        }

        $this->step++;
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function submitApplication(): void
    {
        if (! $this->applyVacancyId) {
            $this->addError('apply', t('Select the vacancy you are applying for.'));

            return;
        }

        if (! $this->agreement_signed || trim($this->signature_name) === '') {
            $this->addError('apply', t('You must agree to the statement & type your name as signature (Willingness & Statement step) before submitting the application.'));

            return;
        }

        // REVISI 2026-08-30: bukan tulis langsung ke tm2hrr lagi -- payload
        // dikirim ke backend portal aktif (Plan A/B) lewat adapter, internal
        // baru terisi setelah sync worker jalan. Lihat komentar atas class ini.
        $vacancyCode = VacVacancy::findOrFail($this->applyVacancyId)->vacancy_code;

        $clean = fn (array $row) => array_map(fn ($v) => $v === '' ? null : $v, $row);

        $payload = [
            'vacancy_code' => $vacancyCode,
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
            'emergency_contacts' => array_values(array_filter($this->emergencyContacts, fn ($r) => trim($r['name'] ?? '') !== '')),
            'professional_references' => array_values(array_filter($this->professionalReferences, fn ($r) => trim($r['name'] ?? '') !== '')),
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
            'language_skills' => array_values(array_filter($this->languageSkills, fn ($r) => trim($r['language'] ?? '') !== '')),
            'computer_skills' => array_values(array_filter($this->computerSkills, fn ($r) => trim($r['skill'] ?? '') !== '')),
            'still_receiving_aid' => $this->still_receiving_aid ?: null, 'other_dependents' => $this->other_dependents ?: null,
            'source_of_info' => $this->source_of_info ?: null, 'expected_salary' => $this->expected_salary,
            'expected_facility' => $this->expected_facility ?: null, 'availability_date' => $this->availability_date ?: null,
            'willingness_checklist' => $this->willingness, 'agreement_signed' => $this->agreement_signed,
            'signature_name' => $this->signature_name, 'agreement_signed_at' => now()->toDateTimeString(),
            'family_members' => array_values(array_filter(array_map($clean, $this->family), fn ($f) => trim($f['name'] ?? '') !== '')),
            'education' => array_values(array_filter(array_map($clean, $this->education), fn ($e) => trim($e['institution'] ?? '') !== '')),
            'education_nonformal' => array_values(array_filter(array_map($clean, $this->educationNonformal), fn ($e) => trim($e['course_type'] ?? '') !== '')),
            'org_experience' => array_values(array_filter(array_map($clean, $this->orgExperience), fn ($o) => trim($o['organization_name'] ?? '') !== '')),
            'work_experience' => array_values(array_filter(array_map($clean, $this->workExp), fn ($w) => trim($w['company'] ?? '') !== '')),
        ];

        $submissionUuid = $this->adapter()->saveSubmission($payload, [
            'cv' => $this->cvFile,
            'ijazah' => $this->ijazahFile,
        ]);

        $this->submittedApplicationId = null;
        $this->lastSubmissionUuid = $submissionUuid;
        $this->cvFile = null;
        $this->ijazahFile = null;
        $this->step = 13;
        unset($this->myApplications);
    }

    public function backToLanding(): void
    {
        $this->step = 12;
    }
};
?>

<div class="max-w-[760px] mx-auto my-5 px-4">
    <div class="bg-[#f1ecfc] border border-[#ddd0f7] text-[#6b4fa0] text-[11px] rounded-md px-3 py-2 mb-4">
        🧪 {{ t('This is a candidate portal simulation — it will later become a separate app (Flutter, another host).') }} {{ t('This form follows the official HR "Employee Candidate Data" form exactly.') }}
    </div>

    @if ($step === 0)
        <div class="bg-white border border-[#d7dbe2] rounded-md p-5">
            <h2 class="text-sm font-bold text-navy mb-1">{{ t('Sign In / Register') }}</h2>
            <p class="text-[11.5px] text-muted mb-3">{{ t('Enter your ID Card No. If you have applied before, your data is loaded automatically.') }}</p>
            @error('login') <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $message }}</p> @enderror
            <x-form.input :label="t('ID Card No.')" wire:model="loginKtp" :placeholder="t('16-digit ID card no.')" />
            <button type="button" wire:click="enter" class="mt-3 bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">{{ t('Proceed') }} →</button>
        </div>
    @else
        @php
            $stepLabels = ['Personal Data', 'Family', 'Education', 'Organization', 'Work Experience', 'Social & References', 'Personal Interests', 'Health', 'Language/Computer/Info', 'Willingness', 'Documents', 'Apply'];
        @endphp
        @if ($step <= 12)
            <p class="text-[10.5px] text-muted text-center mb-1">{{ str_replace(':step', $step, t('Step :step of 12')) }} — {{ t($stepLabels[$step - 1]) }}</p>
            <div class="w-full h-1.5 bg-[#eef1f5] rounded-full mb-4 overflow-hidden">
                <div class="h-full bg-navy" style="width: {{ round($step / 12 * 100) }}%"></div>
            </div>
        @endif

        <div class="bg-white border border-[#d7dbe2] rounded-md p-5">
            @if ($step === 1)
                <h2 class="text-sm font-bold text-navy mb-3">{{ t('Personal Data') }}</h2>
                @error('name') <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $message }}</p> @enderror
                <div class="space-y-3">
                    <x-form.input :label="t('ID Card No.')" wire:model="national_id" readonly class="bg-[#f8fafc]" />
                    <div class="grid two">
                        <x-form.input :label="t('Full Name')" wire:model="name" />
                        <x-form.input :label="t('Nickname')" wire:model="nickname" />
                    </div>
                    <div class="grid two">
                        <x-form.select wire:model="gender" :options="['LAKI_LAKI' => t('Male'), 'PEREMPUAN' => t('Female')]"><x-slot:label>{{ t('Gender') }}</x-slot:label></x-form.select>
                        <x-form.input :label="t('Religion')" wire:model="religion" />
                    </div>
                    <x-form.date-picker :label="t('Date of Birth')" wire:model="birth_date" />
                    <div class="grid two">
                        <x-form.input :label="t('Email')" type="email" wire:model="email" />
                        <x-form.input :label="t('Mobile No.')" wire:model="phone" />
                    </div>
                    <x-form.textarea rows="2" wire:model="address"><x-slot:label>{{ t('Address as on ID Card') }}</x-slot:label></x-form.textarea>
                    <x-form.textarea rows="2" wire:model="address_domisili"><x-slot:label>{{ t('Residential Address (if different from ID card)') }}</x-slot:label></x-form.textarea>
                    <div class="grid two">
                        <x-form.select wire:model="vehicle_type" :options="['MOTOR' => t('Motorcycle'), 'MOBIL' => t('Car'), 'TIDAK_ADA' => t('No Vehicle')]" :placeholder="t('— select —')"><x-slot:label>{{ t('Vehicle Owned') }}</x-slot:label></x-form.select>
                        <div>
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ t('Driving Licenses Held') }}</label>
                            <div class="flex gap-3 pt-1.5">
                                @foreach (['C', 'A', 'B', 'B1'] as $sim)
                                    <label class="flex items-center gap-1.5 text-xs"><input type="checkbox" value="{{ $sim }}" wire:model="sim_types" class="!w-auto"> {{ t('License') }} {{ $sim }}</label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @elseif ($step === 2)
                <h2 class="text-sm font-bold text-navy mb-3">{{ t('Family Data') }}</h2>
                <div class="grid two mb-3">
                    <x-form.select wire:model="marital_status" :options="['LAJANG' => t('Single'), 'MENIKAH' => t('Married'), 'DUDA' => t('Widower/Divorced Man'), 'JANDA' => t('Widow/Divorced Woman')]" :placeholder="t('— select —')"><x-slot:label>{{ t('Marital Status') }}</x-slot:label></x-form.select>
                    <x-form.date-picker :label="t('Marriage/Divorce Date')" wire:model="marital_date" />
                </div>
                <p class="text-[10.5px] font-bold text-muted uppercase mb-2">{{ t('Family Members') }} <span class="font-normal normal-case">({{ t('spouse, children, parents, siblings') }})</span></p>
                @foreach ($family as $i => $f)
                    <div class="border border-[#eef1f5] rounded-md p-3 mb-2" wire:key="fam-{{ $i }}">
                        <div class="grid two mb-2">
                            <x-form.select wire:model="family.{{ $i }}.relation_type" :options="['FATHER' => t('Father'), 'MOTHER' => t('Mother'), 'SPOUSE' => t('Husband/Wife'), 'CHILD' => t('Child'), 'SIBLING' => t('Sibling'), 'FATHER_IN_LAW' => t('Father-in-law'), 'MOTHER_IN_LAW' => t('Mother-in-law')]" :placeholder="false" />
                            <x-form.input wire:model="family.{{ $i }}.name" :placeholder="t('Name')" />
                        </div>
                        <div class="grid gap-2 mb-2" style="grid-template-columns: 90px 100px 1fr;">
                            <x-form.select wire:model="family.{{ $i }}.gender" :options="['L' => 'L', 'P' => 'P']" :placeholder="t('M/F')" />
                            <x-form.input type="number" wire:model="family.{{ $i }}.age" :placeholder="t('Age')" />
                            <x-form.input wire:model="family.{{ $i }}.last_education" :placeholder="t('Last education')" />
                        </div>
                        <x-form.input wire:model="family.{{ $i }}.current_job" :placeholder="t('Occupation')" />
                        <button type="button" wire:click="removeRow('family', {{ $i }})" class="text-[10.5px] text-[#a3241f] mt-2">✕ {{ t('Remove') }}</button>
                    </div>
                @endforeach
                <button type="button" wire:click="addRow('family', {{ json_encode(['relation_type' => 'FATHER', 'name' => '', 'gender' => '', 'age' => '', 'last_education' => '', 'current_job' => '', 'current_employer' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded">＋ {{ t('Add Family Member') }}</button>
            @elseif ($step === 3)
                <h2 class="text-sm font-bold text-navy mb-3">{{ t('Education History') }}</h2>
                <p class="text-[10.5px] font-bold text-muted uppercase mb-2">{{ t('Formal Education') }}</p>
                @foreach ($education as $i => $e)
                    <div class="border border-[#eef1f5] rounded-md p-3 mb-2" wire:key="edu-{{ $i }}">
                        <div class="grid two mb-2">
                            <x-form.input wire:model="education.{{ $i }}.level" :placeholder="t('Level (SLTP/SLTA/D3/S1/S2)')" />
                            <x-form.input wire:model="education.{{ $i }}.institution" :placeholder="t('Institution')" />
                        </div>
                        <div class="grid gap-2" style="grid-template-columns: 1fr 90px 90px 90px;">
                            <x-form.input wire:model="education.{{ $i }}.major" :placeholder="t('Major')" />
                            <x-form.input type="number" wire:model="education.{{ $i }}.period_start_year" :placeholder="t('From Yr')" />
                            <x-form.input type="number" wire:model="education.{{ $i }}.graduation_year" :placeholder="t('To Yr')" />
                            <x-form.input wire:model="education.{{ $i }}.final_score" :placeholder="t('Final Score')" />
                        </div>
                        <button type="button" wire:click="removeRow('education', {{ $i }})" class="text-[10.5px] text-[#a3241f] mt-2">✕ {{ t('Remove') }}</button>
                    </div>
                @endforeach
                <button type="button" wire:click="addRow('education', {{ json_encode(['level' => '', 'institution' => '', 'major' => '', 'period_start_year' => '', 'graduation_year' => '', 'final_score' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded mb-4">＋ {{ t('Add Formal Education') }}</button>

                <p class="text-[10.5px] font-bold text-muted uppercase mb-2 mt-4">{{ t('Non-Formal Education (Courses/Training)') }}</p>
                @foreach ($educationNonformal as $i => $e)
                    <div class="border border-[#eef1f5] rounded-md p-3 mb-2" wire:key="nonformal-{{ $i }}">
                        <div class="grid two mb-2">
                            <x-form.input wire:model="educationNonformal.{{ $i }}.course_type" :placeholder="t('Course/Training Type')" />
                            <x-form.input wire:model="educationNonformal.{{ $i }}.institution" :placeholder="t('Provider Name')" />
                        </div>
                        <div class="grid gap-2" style="grid-template-columns: 90px 90px 1fr;">
                            <x-form.input type="number" wire:model="educationNonformal.{{ $i }}.period_start_year" :placeholder="t('From Yr')" />
                            <x-form.input type="number" wire:model="educationNonformal.{{ $i }}.period_end_year" :placeholder="t('To Yr')" />
                            <x-form.input wire:model="educationNonformal.{{ $i }}.certificate_no" :placeholder="t('Diploma/Certificate No.')" />
                        </div>
                        <button type="button" wire:click="removeRow('educationNonformal', {{ $i }})" class="text-[10.5px] text-[#a3241f] mt-2">✕ {{ t('Remove') }}</button>
                    </div>
                @endforeach
                <button type="button" wire:click="addRow('educationNonformal', {{ json_encode(['course_type' => '', 'institution' => '', 'period_start_year' => '', 'period_end_year' => '', 'certificate_no' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded">＋ {{ t('Add Course/Training') }}</button>
            @elseif ($step === 4)
                <h2 class="text-sm font-bold text-navy mb-3">{{ t('Organization Experience') }}</h2>
                @foreach ($orgExperience as $i => $o)
                    <div class="border border-[#eef1f5] rounded-md p-3 mb-2" wire:key="org-{{ $i }}">
                        <div class="grid two mb-2">
                            <x-form.input wire:model="orgExperience.{{ $i }}.organization_name" :placeholder="t('Organization Name')" />
                            <x-form.input wire:model="orgExperience.{{ $i }}.position" :placeholder="t('Job Title')" />
                        </div>
                        <x-form.input wire:model="orgExperience.{{ $i }}.period" :placeholder="t('Period (e.g. 2020-2022)')" class="mb-2" />
                        <x-form.textarea rows="2" wire:model="orgExperience.{{ $i }}.task_description"><x-slot:label>{{ t('Duties') }}</x-slot:label></x-form.textarea>
                        <button type="button" wire:click="removeRow('orgExperience', {{ $i }})" class="text-[10.5px] text-[#a3241f] mt-2">✕ {{ t('Remove') }}</button>
                    </div>
                @endforeach
                <button type="button" wire:click="addRow('orgExperience', {{ json_encode(['organization_name' => '', 'position' => '', 'period' => '', 'task_description' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded">＋ {{ t('Add Organization Experience') }}</button>
            @elseif ($step === 5)
                <h2 class="text-sm font-bold text-navy mb-1">{{ t('Work Experience') }}</h2>
                <p class="text-[10.5px] text-muted mb-3">{{ t('Order from the most recent company work experience.') }}</p>
                @foreach ($workExp as $i => $w)
                    <div class="border border-[#d7dbe2] rounded-md p-3 mb-3" wire:key="work-{{ $i }}">
                        <div class="grid two mb-2">
                            <x-form.input wire:model="workExp.{{ $i }}.company" :placeholder="t('Company Name')" />
                            <x-form.input wire:model="workExp.{{ $i }}.location" :placeholder="t('Location & Business Type')" />
                        </div>
                        <div class="grid two mb-2">
                            <x-form.input wire:model="workExp.{{ $i }}.employee_count" :placeholder="t('Number of Employees')" />
                            <x-form.input wire:model="workExp.{{ $i }}.company_phone" :placeholder="t('Company Phone No.')" />
                        </div>
                        <p class="text-[10px] font-bold text-muted uppercase mt-2 mb-1">{{ t('First Job Title') }}</p>
                        <div class="grid gap-2 mb-2" style="grid-template-columns:1fr 130px 130px;">
                            <x-form.input wire:model="workExp.{{ $i }}.position" :placeholder="t('First Job Title')" />
                            <x-form.date-picker wire:model="workExp.{{ $i }}.start_date" />
                            <x-form.date-picker wire:model="workExp.{{ $i }}.end_date" />
                        </div>
                        <p class="text-[10px] font-bold text-muted uppercase mt-2 mb-1">{{ t('Last Job Title') }}</p>
                        <div class="grid gap-2 mb-2" style="grid-template-columns:1fr 130px 130px;">
                            <x-form.input wire:model="workExp.{{ $i }}.position_end" :placeholder="t('Last Job Title')" />
                            <x-form.date-picker wire:model="workExp.{{ $i }}.position_end_start_date" />
                            <x-form.date-picker wire:model="workExp.{{ $i }}.position_end_end_date" />
                        </div>
                        <div class="grid gap-2 mb-2" style="grid-template-columns:1fr 1fr 1fr 1fr;">
                            <x-form.input wire:model="workExp.{{ $i }}.subordinate_count_start" :placeholder="t('Subordinates (start)')" />
                            <x-form.input wire:model="workExp.{{ $i }}.subordinate_count_end" :placeholder="t('Subordinates (end)')" />
                            <x-form.input type="number" wire:model="workExp.{{ $i }}.salary_start" :placeholder="t('Starting Salary')" />
                            <x-form.input type="number" wire:model="workExp.{{ $i }}.salary_end" :placeholder="t('Last Salary')" />
                        </div>
                        <x-form.input wire:model="workExp.{{ $i }}.benefits" :placeholder="t('Benefits besides salary')" class="mb-2" />
                        <div class="grid two mb-2">
                            <x-form.input wire:model="workExp.{{ $i }}.supervisor_name" :placeholder="t('Direct Supervisor')" />
                            <x-form.input wire:model="workExp.{{ $i }}.supervisor_phone" :placeholder="t('Supervisor Phone No.')" />
                        </div>
                        <x-form.input wire:model="workExp.{{ $i }}.achievement" :placeholder="t('Achievements')" class="mb-2" />
                        <x-form.textarea rows="2" wire:model="workExp.{{ $i }}.description" class="mb-2"><x-slot:label>{{ t('Job Description') }}</x-slot:label></x-form.textarea>
                        <x-form.input wire:model="workExp.{{ $i }}.org_structure_note" :placeholder="t('Describe the organization structure showing your position')" class="mb-2" />
                        <x-form.textarea rows="2" wire:model="workExp.{{ $i }}.key_challenge" class="mb-2"><x-slot:label>{{ t('Key problems you have faced & how you solved them') }}</x-slot:label></x-form.textarea>
                        <x-form.textarea rows="2" wire:model="workExp.{{ $i }}.improvement_made" class="mb-2"><x-slot:label>{{ t('Changes/improvements you have made') }}</x-slot:label></x-form.textarea>
                        <x-form.input wire:model="workExp.{{ $i }}.leave_reason" :placeholder="t('Reason for Leaving')" />
                        <button type="button" wire:click="removeRow('workExp', {{ $i }})" class="text-[10.5px] text-[#a3241f] mt-2">✕ {{ t('Remove This Experience') }}</button>
                    </div>
                @endforeach
                <button type="button" wire:click="addRow('workExp', {{ json_encode(['company' => '', 'location' => '', 'business_type' => '', 'employee_count' => '', 'company_phone' => '', 'position' => '', 'start_date' => '', 'end_date' => '', 'position_end' => '', 'position_end_start_date' => '', 'position_end_end_date' => '', 'subordinate_count_start' => '', 'subordinate_count_end' => '', 'salary_start' => '', 'salary_end' => '', 'benefits' => '', 'supervisor_name' => '', 'supervisor_phone' => '', 'achievement' => '', 'description' => '', 'org_structure_note' => '', 'key_challenge' => '', 'improvement_made' => '', 'leave_reason' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded">＋ {{ t('Add Work Experience') }}</button>
            @elseif ($step === 6)
                <h2 class="text-sm font-bold text-navy mb-3">{{ t('Social Activities, Activities & References') }}</h2>
                <div class="space-y-3 mb-4">
                    <x-form.input :label="t('Do you know anyone in our company? (name & job title)')" wire:model="company_acquaintance" />
                    <x-form.input :label="t('Hobbies')" wire:model="hobby" />
                    <x-form.input :label="t('How do you spend your leisure time?')" wire:model="leisure_activity" />
                    <x-form.input :label="t('What topics do you read about?')" wire:model="reading_topics" />
                    <x-form.textarea rows="2" wire:model="went_abroad"><x-slot:label>{{ t('Have you ever been abroad? When & for what purpose?') }}</x-slot:label></x-form.textarea>
                </div>
                <p class="text-[10.5px] font-bold text-muted uppercase mb-2">{{ t('References') }} <span class="font-normal normal-case">({{ t('at least 2 people, not family') }})</span></p>
                @foreach ($professionalReferences as $i => $r)
                    <div class="grid two gap-2 mb-2" wire:key="ref-{{ $i }}">
                        <x-form.input wire:model="professionalReferences.{{ $i }}.name" :placeholder="t('Name')" />
                        <div class="flex gap-1">
                            <x-form.input wire:model="professionalReferences.{{ $i }}.phone" :placeholder="t('Phone No.')" class="flex-1" />
                            <button type="button" wire:click="removeRow('professionalReferences', {{ $i }})" class="text-[#a3241f] text-xs px-2">✕</button>
                        </div>
                    </div>
                @endforeach
                <button type="button" wire:click="addRow('professionalReferences', {{ json_encode(['name' => '', 'phone' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded mb-4">＋ {{ t('Add Reference') }}</button>

                <p class="text-[10.5px] font-bold text-muted uppercase mb-2 mt-4">{{ t('Emergency Contacts') }} <span class="font-normal normal-case">({{ t('at least 3 people, with relation & address') }})</span></p>
                @foreach ($emergencyContacts as $i => $c)
                    <div class="border border-[#eef1f5] rounded-md p-3 mb-2" wire:key="emg-{{ $i }}">
                        <div class="grid two mb-2">
                            <x-form.input wire:model="emergencyContacts.{{ $i }}.name" :placeholder="t('Name')" />
                            <x-form.input wire:model="emergencyContacts.{{ $i }}.relation" :placeholder="t('Relation')" />
                        </div>
                        <div class="grid two mb-2">
                            <x-form.input wire:model="emergencyContacts.{{ $i }}.phone" :placeholder="t('Phone No.')" />
                            <x-form.input wire:model="emergencyContacts.{{ $i }}.address" :placeholder="t('Address')" />
                        </div>
                        <button type="button" wire:click="removeRow('emergencyContacts', {{ $i }})" class="text-[10.5px] text-[#a3241f]">✕ {{ t('Remove') }}</button>
                    </div>
                @endforeach
                <button type="button" wire:click="addRow('emergencyContacts', {{ json_encode(['name' => '', 'relation' => '', 'phone' => '', 'address' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded">＋ {{ t('Add Emergency Contact') }}</button>
            @elseif ($step === 7)
                <h2 class="text-sm font-bold text-navy mb-3">{{ t('Interests & Self-Concept') }}</h2>
                <div class="space-y-3">
                    <x-form.textarea rows="2" wire:model="why_join_us"><x-slot:label>{{ t('Why do you want to work at our Company?') }}</x-slot:label></x-form.textarea>
                    <x-form.textarea rows="2" wire:model="what_you_know_about_us"><x-slot:label>{{ t('What do you know about our company?') }}</x-slot:label></x-form.textarea>
                    <x-form.textarea rows="2" wire:model="contribution_plan"><x-slot:label>{{ t('What contribution can you make in the position applied for?') }}</x-slot:label></x-form.textarea>
                    <x-form.textarea rows="2" wire:model="role_plan_if_hired"><x-slot:label>{{ t('What would you do if you held that position?') }}</x-slot:label></x-form.textarea>
                    <x-form.textarea rows="2" wire:model="job_type_preference"><x-slot:label>{{ t('What type of work do you enjoy? (Planner/Executor) — give your reason') }}</x-slot:label></x-form.textarea>
                    <x-form.textarea rows="2" wire:model="job_nature_preference"><x-slot:label>{{ t('What nature of work do you enjoy? (Research/Technical/Non-Technical) — give your reason') }}</x-slot:label></x-form.textarea>
                    <x-form.textarea rows="2" wire:model="work_environment_preference"><x-slot:label>{{ t('What work environment do you enjoy? (Factory/Office/Field/Laboratory) — give your reason') }}</x-slot:label></x-form.textarea>
                    <x-form.textarea rows="2" wire:model="hindering_environment"><x-slot:label>{{ t('What kind of work/environment can hinder your work & trigger human error?') }}</x-slot:label></x-form.textarea>
                    <x-form.textarea rows="2" wire:model="preferred_colleague_type"><x-slot:label>{{ t('What type of people do you like to work with?') }}</x-slot:label></x-form.textarea>
                    <x-form.textarea rows="2" wire:model="difficult_decision_topic"><x-slot:label>{{ t('On what matters do you find it hardest to make firm, high-impact decisions?') }}</x-slot:label></x-form.textarea>
                    <x-form.textarea rows="3" wire:model="strengths_weaknesses"><x-slot:label>{{ t('What are your strengths & weaknesses? (at least 5 each)') }}</x-slot:label></x-form.textarea>
                    <x-form.textarea rows="2" wire:model="who_you_ask_when_stuck"><x-slot:label>{{ t('When you have difficulty solving a problem, whom do you ask? Why?') }}</x-slot:label></x-form.textarea>
                </div>
            @elseif ($step === 8)
                <h2 class="text-sm font-bold text-navy mb-3">{{ t('Health History') }}</h2>
                <div class="grid gap-2 mb-3" style="grid-template-columns:1fr 1fr 1fr;">
                    <x-form.input :label="t('Height (cm)')" type="number" wire:model="height_cm" />
                    <x-form.input :label="t('Weight (kg)')" type="number" wire:model="weight_kg" />
                    <x-form.input :label="t('Blood Type')" wire:model="blood_type" />
                </div>
                <div class="space-y-3">
                    <x-form.textarea rows="2" wire:model="illness_history"><x-slot:label>{{ t('What illnesses have you suffered? For how long? What is your current condition?') }}</x-slot:label></x-form.textarea>
                    <x-form.textarea rows="2" wire:model="hospitalized_history"><x-slot:label>{{ t('Have you ever been hospitalized? For what? What lasting effects do you have now?') }}</x-slot:label></x-form.textarea>
                    <div class="grid two">
                        <x-form.select wire:model="is_smoker" :options="[1 => t('Yes'), 0 => t('No')]" :placeholder="t('— select —')"><x-slot:label>{{ t('Do you smoke?') }}</x-slot:label></x-form.select>
                        <x-form.select wire:model="family_health_ok" :options="[1 => t('Yes, all fine'), 0 => t('There are problems')]" :placeholder="t('— select —')"><x-slot:label>{{ t('Is your family in good health?') }}</x-slot:label></x-form.select>
                    </div>
                    <x-form.textarea rows="2" wire:model="physical_disability"><x-slot:label>{{ t('Do you have any permanent/severe physical impairment?') }}</x-slot:label></x-form.textarea>
                </div>
            @elseif ($step === 9)
                <h2 class="text-sm font-bold text-navy mb-3">{{ t('Languages, Computer & Vacancy Info') }}</h2>
                <p class="text-[10.5px] font-bold text-muted uppercase mb-2">{{ t('Foreign Language Proficiency') }}</p>
                @foreach ($languageSkills as $i => $l)
                    <div class="grid gap-2 mb-2" style="grid-template-columns:1fr 110px 110px 1fr auto;" wire:key="lang-{{ $i }}">
                        <x-form.input wire:model="languageSkills.{{ $i }}.language" :placeholder="t('Language Name')" />
                        <x-form.select wire:model="languageSkills.{{ $i }}.written" :options="['S' => t('Written').': '.t('Fair'), 'CB' => t('Written').': '.t('Fairly Good'), 'B' => t('Written').': '.t('Good')]" :placeholder="t('Written')" />
                        <x-form.select wire:model="languageSkills.{{ $i }}.oral" :options="['S' => t('Spoken').': '.t('Fair'), 'CB' => t('Spoken').': '.t('Fairly Good'), 'B' => t('Spoken').': '.t('Good')]" :placeholder="t('Spoken')" />
                        <x-form.input wire:model="languageSkills.{{ $i }}.notes" :placeholder="t('Description')" />
                        <button type="button" wire:click="removeRow('languageSkills', {{ $i }})" class="text-[#a3241f] text-xs">✕</button>
                    </div>
                @endforeach
                <button type="button" wire:click="addRow('languageSkills', {{ json_encode(['language' => '', 'written' => '', 'oral' => '', 'notes' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded mb-4">＋ {{ t('Add Language') }}</button>

                <p class="text-[10.5px] font-bold text-muted uppercase mb-2 mt-4">{{ t('Computer Skills') }}</p>
                @foreach ($computerSkills as $i => $c)
                    <div class="grid gap-2 mb-2" style="grid-template-columns:1fr 130px 1fr auto;" wire:key="comp-{{ $i }}">
                        <x-form.input wire:model="computerSkills.{{ $i }}.skill" :placeholder="t('Skill (e.g. Graphic Design)')" />
                        <x-form.select wire:model="computerSkills.{{ $i }}.level" :options="['S' => t('Fair'), 'CB' => t('Fairly Good'), 'B' => t('Good')]" :placeholder="t('Level')" />
                        <x-form.input wire:model="computerSkills.{{ $i }}.notes" :placeholder="t('Description')" />
                        <button type="button" wire:click="removeRow('computerSkills', {{ $i }})" class="text-[#a3241f] text-xs">✕</button>
                    </div>
                @endforeach
                <button type="button" wire:click="addRow('computerSkills', {{ json_encode(['skill' => '', 'level' => '', 'notes' => '']) }})" class="text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded mb-4">＋ {{ t('Add Computer Skill') }}</button>

                <div class="space-y-3 mt-4">
                    <x-form.textarea rows="2" wire:model="still_receiving_aid"><x-slot:label>{{ t('Are you still receiving support? From whom & how much/how long?') }}</x-slot:label></x-form.textarea>
                    <x-form.textarea rows="2" wire:model="other_dependents"><x-slot:label>{{ t('Do you have other dependents besides children & spouse? Name them & how much') }}</x-slot:label></x-form.textarea>
                    <x-form.input :label="t('Where did you hear about vacancies at our company?')" wire:model="source_of_info" />
                    <div class="grid two">
                        <x-form.input :label="t('Expected salary')" type="number" wire:model="expected_salary" />
                        <x-form.date-picker :label="t('Available to start work')" wire:model="availability_date" />
                    </div>
                    <x-form.input :label="t('Expected benefits')" wire:model="expected_facility" />
                </div>
            @elseif ($step === 10)
                <h2 class="text-sm font-bold text-navy mb-3">{{ t('Willingness & Statement') }}</h2>
                <div class="space-y-2 mb-4">
                    @foreach ($this->willingItems as $key => $label)
                        <label class="flex items-start gap-2 text-[12px] border border-[#eef1f5] rounded-md p-2.5">
                            <input type="checkbox" wire:model="willingness.{{ $key }}" class="!w-auto mt-0.5">
                            <span>{{ t($label) }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="bg-[#f8fafc] border border-[#eef1f5] rounded-md p-3 text-[11px] text-muted mb-3">
                    "{{ t('I declare that the information I have given is true and reflects my condition.') }} {{ t('If any of this information is found to be inaccurate, I am willing to resign/be dismissed without severance or compensation of any kind,') }} {{ t('accept all consequences imposed by Telasih Makmur, and be processed according to the applicable law.') }}"
                </div>
                @error('apply') <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $message }}</p> @enderror
                <label class="flex items-center gap-2 text-xs mb-2"><input type="checkbox" wire:model="agreement_signed" class="!w-auto"> {{ t('I agree to the statement above') }}</label>
                <x-form.input :label="t('Type your full name as signature')" wire:model="signature_name" :placeholder="t('Full name')" />
            @elseif ($step === 11)
                <h2 class="text-sm font-bold text-navy mb-3">{{ t('Upload Documents') }}</h2>
                <div class="space-y-3">
                    <div>
                        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ t('CV') }} <span class="text-[#a3241f]">*</span></label>
                        <input type="file" wire:model="cvFile" class="text-xs">
                        @if ($cvFile) <p class="text-[10.5px] text-[#1f8a4c] mt-1">✓ {{ $cvFile->getClientOriginalName() }}</p> @endif
                    </div>
                    <div>
                        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ t('Diploma') }} <span class="text-[#a3241f]">*</span></label>
                        <input type="file" wire:model="ijazahFile" class="text-xs">
                        @if ($ijazahFile) <p class="text-[10.5px] text-[#1f8a4c] mt-1">✓ {{ $ijazahFile->getClientOriginalName() }}</p> @endif
                    </div>
                </div>
            @elseif ($step === 12)
                <h2 class="text-sm font-bold text-navy mb-3">{{ t('Review & Apply for Vacancy') }}</h2>
                @error('apply') <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $message }}</p> @enderror
                <div class="bg-[#f8fafc] border border-[#eef1f5] rounded-md p-3 text-xs mb-3 space-y-1">
                    <p><b>{{ $name }}</b> ({{ $nickname }}) — {{ $national_id }}</p>
                    <p class="text-muted">{{ $email }} · {{ $phone }}</p>
                    <p class="text-muted">{{ count($family) }} {{ t('family records') }} · {{ count($education) }} {{ t('formal education') }} · {{ count($educationNonformal) }} {{ t('non-formal') }} · {{ count($orgExperience) }} {{ t('organizations') }} · {{ count($workExp) }} {{ t('work experiences') }}</p>
                    <p class="text-muted">{{ count($professionalReferences) }} {{ t('references') }} · {{ count($emergencyContacts) }} {{ t('emergency contacts') }} · {{ count($languageSkills) }} {{ t('languages') }} · {{ count($computerSkills) }} {{ t('computer skills') }}</p>
                    <p class="text-muted">{{ t('Willingness') }}: {{ collect($willingness)->filter()->count() }}/{{ count($this->willingItems) }} {{ t('agreed') }} · {{ t('Signature') }}: {{ $signature_name ?: t('(not filled in yet)') }}</p>
                </div>
                <x-form.select wire:model="applyVacancyId" :options="$this->openVacancies->pluck('title', 'id_vacancy')->all()" :placeholder="t('— select vacancy —')">
                    <x-slot:label>{{ t('Vacancy Applied For') }}</x-slot:label>
                </x-form.select>
            @elseif ($step === 13)
                <div class="text-center py-4">
                    <p class="text-3xl mb-2">✅</p>
                    <h2 class="text-sm font-bold text-[#1f8a4c] mb-1">{{ t('Application Submitted') }}</h2>
                    <p class="text-[11.5px] text-muted">{{ t('Thank you, we have received your application.') }}</p>
                    @if ($lastSubmissionUuid)
                        <p class="text-[10px] text-muted mt-1">{{ t('Ref') }}: {{ $lastSubmissionUuid }}</p>
                    @endif
                </div>
                @if ($this->myApplications->isEmpty())
                    <div class="bg-[#fff8e6] border border-[#f4e0a3] text-[#8a5a00] text-[11px] rounded-md px-3 py-2 mb-2">
                        ⏳ {{ t('The application is waiting to be synced to the internal HR system (runs automatically every 2 minutes) -- it appears in the history below once done.') }}
                    </div>
                @endif
                <div class="border-t border-[#eef1f5] pt-4 mt-2">
                    <p class="text-[10.5px] font-bold text-muted uppercase mb-2">{{ t('My Application History') }}</p>
                    @foreach ($this->myApplications as $app)
                        <div class="flex items-center justify-between text-[11.5px] py-1.5 border-b border-[#eef1f5] last:border-0">
                            <span>{{ $app->vacancy->title ?? '—' }}</span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ match ($app->status) {
                                'HIRED' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                                'REJECTED', 'WITHDRAWN' => 'bg-[#fbeaea] text-[#a3241f]',
                                'OFFERING' => 'bg-[#f1ecfc] text-[#6d28d9]',
                                default => 'bg-[#fff5e0] text-[#8a5a00]',
                            } }}">{{ match ($app->status) {
                                'APPLIED', 'IN_PROGRESS' => t('PROCESSING'),
                                'OFFERING' => t('OFFERING'),
                                'HIRED' => t('ACCEPTED'),
                                'REJECTED' => t('NOT PASSED'),
                                'WITHDRAWN' => t('WITHDRAWN'),
                                default => $app->status,
                            } }}</span>
                        </div>
                    @endforeach
                </div>
                <button type="button" wire:click="backToLanding" class="mt-4 text-[11px] font-bold text-navy border border-[#d7dbe2] px-3 py-1.5 rounded">＋ {{ t('Apply for Another Vacancy') }}</button>
            @endif

            @if ($step >= 1 && $step <= 12)
                <div class="flex justify-between mt-4 pt-3 border-t border-[#eef1f5]">
                    <button type="button" wire:click="prevStep" @if($step === 1) disabled @endif class="text-xs font-bold text-muted px-4 py-2 disabled:opacity-30">← {{ t('Back') }}</button>
                    @if ($step < 12)
                        <button type="button" wire:click="nextStep" class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">{{ t('Proceed') }} →</button>
                    @else
                        <button type="button" wire:click="submitApplication" class="bg-[#1f8a4c] text-white text-xs font-bold px-4 py-2 rounded-md">{{ t('Submit Application') }}</button>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
