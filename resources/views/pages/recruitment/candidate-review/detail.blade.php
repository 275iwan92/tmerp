<?php

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppPipelineStageConfig;
use App\Models\Hrr\CndCandidate;
use App\Support\Recruitment\CandidateFormOptions;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Review Formulir Kandidat -- tampilan read-only lengkap "Data Calon
// Karyawan" per lamaran, utk HR meninjau sebelum memutuskan lanjut/reject di
// Pipeline Board. SUMBER MARKUP SAMA dgn portal (careers/apply.blade.php),
// lewat partial careers/_apply-form-steps.blade.php dgn toggle readonly=true
// (keputusan user 2026-09-05: ubah salah satu -- layout maupun konten --
// harus otomatis berlaku ke yang lain). Makanya property di sini WAJIB
// bernama PERSIS sama dgn apply.blade.php -- partial itu membaca property
// tsb by name, bukan lewat parameter eksplisit.
new #[Title('Candidate Form Review — TELASIH MAKMUR ERP')] class extends Component
{
    public int $applicationId;

    // -- Sama persis dgn public property careers/apply.blade.php --
    public string $name = '';

    public string $nickname = '';

    public string $national_id = '';

    public string $gender = '';

    public string $religion = '';

    public string $email = '';

    public string $phone = '';

    public ?string $birth_date = null;

    public string $address = '';

    public string $address_domisili = '';

    public string $vehicle_type = '';

    public array $sim_types = [];

    public string $marital_status = '';

    public ?string $marital_date = null;

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

    public ?string $availability_date = null;

    public array $willingness = [];

    public bool $agreement_signed = false;

    public string $signature_name = '';

    // -- Khusus mode readonly (tidak ada di apply.blade.php) --
    public array $documents = [];

    public function mount(int $application): void
    {
        $this->applicationId = $application;

        $candidate = CndCandidate::with([
            'familyMembers', 'education', 'educationNonformal', 'organizationExperience', 'workExperience', 'documents',
        ])->findOrFail($this->application->id_candidate);

        $this->name = $candidate->name;
        $this->nickname = (string) $candidate->nickname;
        $this->national_id = $candidate->national_id;
        $this->gender = (string) $candidate->gender;
        $this->religion = (string) $candidate->religion;
        $this->email = (string) $candidate->email;
        $this->phone = (string) $candidate->phone;
        $this->birth_date = $candidate->birth_date?->translatedFormat('d M Y');
        $this->address = (string) $candidate->address;
        $this->address_domisili = (string) $candidate->address_domisili;
        $this->vehicle_type = (string) $candidate->vehicle_type;
        $this->sim_types = $candidate->sim_types ?? [];
        $this->marital_status = (string) $candidate->marital_status;
        $this->marital_date = $candidate->marital_date?->translatedFormat('d M Y');
        $this->family = $candidate->familyMembers->map->toArray()->values()->all();
        $this->education = $candidate->education->map->toArray()->values()->all();
        $this->educationNonformal = $candidate->educationNonformal->map->toArray()->values()->all();
        $this->orgExperience = $candidate->organizationExperience->map->toArray()->values()->all();
        $this->workExp = $candidate->workExperience->map(fn ($w) => [
            ...array_diff_key($w->toArray(), array_flip(['start_date', 'end_date', 'position_end_start_date', 'position_end_end_date'])),
            'start_date' => $w->start_date?->translatedFormat('d M Y'),
            'end_date' => $w->end_date?->translatedFormat('d M Y'),
            'position_end_start_date' => $w->position_end_start_date?->translatedFormat('d M Y'),
            'position_end_end_date' => $w->position_end_end_date?->translatedFormat('d M Y'),
        ])->values()->all();
        $this->company_acquaintance = (string) $candidate->company_acquaintance;
        $this->hobby = (string) $candidate->hobby;
        $this->leisure_activity = (string) $candidate->leisure_activity;
        $this->reading_topics = (string) $candidate->reading_topics;
        $this->went_abroad = (string) $candidate->went_abroad;
        $this->emergencyContacts = $candidate->emergency_contacts ?? [];
        $this->professionalReferences = $candidate->professional_references ?? [];
        $this->why_join_us = (string) $candidate->why_join_us;
        $this->what_you_know_about_us = (string) $candidate->what_you_know_about_us;
        $this->contribution_plan = (string) $candidate->contribution_plan;
        $this->role_plan_if_hired = (string) $candidate->role_plan_if_hired;
        $this->job_type_preference = (string) $candidate->job_type_preference;
        $this->job_nature_preference = (string) $candidate->job_nature_preference;
        $this->work_environment_preference = (string) $candidate->work_environment_preference;
        $this->hindering_environment = (string) $candidate->hindering_environment;
        $this->preferred_colleague_type = (string) $candidate->preferred_colleague_type;
        $this->difficult_decision_topic = (string) $candidate->difficult_decision_topic;
        $this->strengths_weaknesses = (string) $candidate->strengths_weaknesses;
        $this->who_you_ask_when_stuck = (string) $candidate->who_you_ask_when_stuck;
        $this->height_cm = $candidate->height_cm;
        $this->weight_kg = $candidate->weight_kg;
        $this->blood_type = (string) $candidate->blood_type;
        $this->illness_history = (string) $candidate->illness_history;
        $this->hospitalized_history = (string) $candidate->hospitalized_history;
        $this->is_smoker = $candidate->is_smoker;
        $this->physical_disability = (string) $candidate->physical_disability;
        $this->family_health_ok = $candidate->family_health_ok;
        $this->languageSkills = $candidate->language_skills ?? [];
        $this->computerSkills = $candidate->computer_skills ?? [];
        $this->still_receiving_aid = (string) $candidate->still_receiving_aid;
        $this->other_dependents = (string) $candidate->other_dependents;
        $this->source_of_info = (string) $candidate->source_of_info;
        $this->expected_salary = $candidate->expected_salary !== null ? (float) $candidate->expected_salary : null;
        $this->expected_facility = (string) $candidate->expected_facility;
        $this->availability_date = $candidate->availability_date?->translatedFormat('d M Y');
        $this->willingness = $candidate->willingness_checklist ?? [];
        $this->agreement_signed = (bool) $candidate->agreement_signed;
        $this->signature_name = (string) $candidate->signature_name;
        $this->documents = $candidate->documents->all();
    }

    #[Computed]
    public function application(): AppApplication
    {
        return AppApplication::with('candidate', 'vacancy.requisition')->findOrFail($this->applicationId);
    }

    #[Computed]
    public function stageName(): ?string
    {
        $trackId = $this->application->vacancy->requisition->id_track ?? null;

        if (! $trackId) {
            return null;
        }

        return AppPipelineStageConfig::where('id_track', $trackId)
            ->where('stage_seq', $this->application->current_stage_seq)
            ->value('stage_name');
    }

    #[Computed]
    public function willingItems(): array
    {
        return CandidateFormOptions::labels(CandidateFormOptions::WILLING_ITEMS);
    }
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">📄 {{ t('Candidate Form Review') }}</b>
        <span class="text-xs text-white/75">{{ $name }} — {{ $this->application->vacancy->title ?? '' }}</span>
        <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full
            {{ match ($this->application->status) {
                'APPLIED' => 'bg-[#fff5e0] text-[#8a5a00]',
                'OFFERING' => 'bg-[#f1ecfc] text-[#6d28d9]',
                'HIRED' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                'REJECTED', 'WITHDRAWN' => 'bg-[#fbeaea] text-[#a3241f]',
                default => 'bg-[#eef1f5] text-[#4b5568]',
            } }}">{{ $this->application->status }}{{ $this->stageName ? ' · '.$this->stageName : '' }}</span>
        <x-back-button :fallback="route('hrm.recruitment.candidate-review.list')" title="Back" class="ml-auto" />
    </header>

    <div class="max-w-[820px] mx-auto my-5 px-4">
        <div class="bg-white border border-[#e6e9ef] rounded-xl p-5">
            @include('pages::careers._apply-form-steps', ['readonly' => true])
        </div>
    </div>
</div>
