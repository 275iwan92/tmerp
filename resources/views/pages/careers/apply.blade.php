<?php

use App\Models\Hrr\VacVacancy;
use App\Services\Portal\CandidateAuthService;
use App\Services\Recruitment\FirebaseSyncAdapter;
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
// Field step 1-11 SAMA PERSIS (partial _apply-form-steps).
new #[Layout('layouts::portal')] #[Title('Lamar Posisi')] class extends Component
{
    use WithFileUploads;

    public string $vacancy_code;

    public int $step = 1; // 1..11 isian, 12 = review & submit, 13 = selesai

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

    protected const WILLING_ITEMS = [
        'probation' => 'Mengikuti masa percobaan',
        'training' => 'Mengikuti training sesuai ketentuan Perusahaan',
        'overtime' => 'Bekerja lembur (termasuk Sabtu/Minggu/hari besar)',
        'guarantee_docs' => 'Menyerahkan jaminan Ijazah/BPKB jika dibutuhkan',
        'resign_notice' => 'Mengajukan permohonan tertulis 1 (satu) bulan sebelumnya apabila hendak mengundurkan diri',
        'obey_rules' => 'Mematuhi segala peraturan perusahaan yang berlaku',
        'resign_if_married_pregnant' => 'Bersedia mengundurkan diri jika menikah dengan rekan sekerja atau hamil sebelum masa kerja 1 (satu) tahun',
        'relocation' => 'Bersedia setiap saat dipindah/ditugaskan ke bagian/cabang lain',
    ];

    public function mount(string $vacancy_code, CandidateAuthService $auth): void
    {
        $this->vacancy_code = $vacancy_code;
        $this->willingness = array_fill_keys(array_keys(self::WILLING_ITEMS), false);

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
            $this->family = $last['family_members'] ?? [];
            $this->education = $last['education'] ?? [];
            $this->educationNonformal = $last['education_nonformal'] ?? [];
            $this->orgExperience = $last['org_experience'] ?? [];
            $this->workExp = $last['work_experience'] ?? [];
            $this->emergencyContacts = $last['emergency_contacts'] ?? [];
            $this->professionalReferences = $last['professional_references'] ?? [];
            $this->languageSkills = $last['language_skills'] ?? [];
            $this->computerSkills = $last['computer_skills'] ?? [];
            $this->willingness = array_merge($this->willingness, $last['willingness_checklist'] ?? []);
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
        return self::WILLING_ITEMS;
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
        unset($this->{$prop}[$i]);
        $this->{$prop} = array_values($this->{$prop});
    }

    public function nextStep(): void
    {
        if ($this->step === 1 && trim($this->name) === '') {
            $this->addError('name', 'Nama wajib diisi.');

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
        if (! $this->emailVerified) {
            $this->addError('apply', 'Verifikasi email Anda dulu sebelum mengirim lamaran (cek inbox saat registrasi).');

            return;
        }

        if (! $this->agreement_signed || trim($this->signature_name) === '') {
            $this->addError('apply', 'Anda wajib menyetujui pernyataan & mengisi nama sebagai tanda tangan (step Kesediaan & Pernyataan) sebelum mengirim lamaran.');

            return;
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

        $this->lastSubmissionUuid = app(FirebaseSyncAdapter::class)->saveSubmission($payload, [
            'cv' => $this->cvFile,
            'ijazah' => $this->ijazahFile,
        ]);

        $this->cvFile = null;
        $this->ijazahFile = null;
        $this->step = 13;
    }
};
?>

<div class="max-w-[760px] mx-auto my-8 px-5">
    @if ($step !== 13)
        <a href="{{ route('careers.vacancy-detail', $vacancy_code) }}" class="text-[12px] font-bold text-muted hover:text-navy">&larr; Batalkan, kembali ke detail lowongan</a>
        <h1 class="text-[18px] font-extrabold text-navy mt-2 mb-1">Lamar: {{ $this->vacancy->title }}</h1>

        @unless ($this->emailVerified)
            <div class="bg-[#fff8e6] border border-[#f4e0a3] text-[#8a5a00] text-[11.5px] rounded-md px-3 py-2 mb-4">
                ⚠ Email Anda belum diverifikasi. Anda masih bisa mengisi form ini, tapi tidak bisa mengirim sampai email diverifikasi (cek inbox saat mendaftar).
            </div>
        @endunless

        @php $stepLabels = ['Data Pribadi', 'Keluarga', 'Pendidikan', 'Organisasi', 'Pengalaman Kerja', 'Sosial & Referensi', 'Minat Pribadi', 'Kesehatan', 'Bahasa/Komputer/Info', 'Kesediaan', 'Dokumen', 'Review & Kirim']; @endphp
        <p class="text-[10.5px] text-muted text-center mb-1 mt-4">Langkah {{ $step }} dari 12 — {{ $stepLabels[$step - 1] }}</p>
        <div class="w-full h-1.5 bg-[#eef1f5] rounded-full mb-4 overflow-hidden">
            <div class="h-full bg-navy" style="width: {{ round($step / 12 * 100) }}%"></div>
        </div>

        <div class="bg-white border border-[#e6e9ef] rounded-xl p-5">
            @if ($step <= 11)
                @include('pages::careers._apply-form-steps')
            @elseif ($step === 12)
                <h2 class="text-sm font-bold text-navy mb-3">Review &amp; Kirim Lamaran</h2>
                <p class="text-[12px] text-muted mb-4">Periksa kembali ringkasan berikut. Klik "Lanjut"/"Kembali" di atas kalau ada yang mau diperbaiki dulu.</p>
                <dl class="text-[12.5px] space-y-2 mb-4">
                    <div class="flex justify-between border-b border-[#eef1f5] pb-1.5"><dt class="text-muted">Melamar untuk</dt><dd class="font-bold text-navy">{{ $this->vacancy->title }}</dd></div>
                    <div class="flex justify-between border-b border-[#eef1f5] pb-1.5"><dt class="text-muted">Nama</dt><dd>{{ $name }}</dd></div>
                    <div class="flex justify-between border-b border-[#eef1f5] pb-1.5"><dt class="text-muted">No. KTP</dt><dd>{{ $national_id }}</dd></div>
                    <div class="flex justify-between border-b border-[#eef1f5] pb-1.5"><dt class="text-muted">Email</dt><dd>{{ $email }}</dd></div>
                    <div class="flex justify-between border-b border-[#eef1f5] pb-1.5"><dt class="text-muted">Pendidikan tercatat</dt><dd>{{ count($education) }} baris</dd></div>
                    <div class="flex justify-between border-b border-[#eef1f5] pb-1.5"><dt class="text-muted">Pengalaman kerja tercatat</dt><dd>{{ count($workExp) }} baris</dd></div>
                    <div class="flex justify-between border-b border-[#eef1f5] pb-1.5"><dt class="text-muted">Dokumen</dt><dd>{{ ($cvFile ? 'CV ✓' : 'CV ✗').' / '.($ijazahFile ? 'Ijazah ✓' : 'Ijazah ✗') }}</dd></div>
                </dl>
                @error('apply') <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $message }}</p> @enderror
                <button type="button" wire:click="submitApplication" wire:loading.attr="disabled" class="w-full bg-[#1f8a4c] text-white text-sm font-bold px-4 py-2.5 rounded-md disabled:opacity-50">Kirim Lamaran</button>
            @endif

            <div class="flex justify-between mt-4 pt-3 border-t border-[#eef1f5]">
                <button type="button" wire:click="prevStep" @if($step === 1) disabled @endif class="text-xs font-bold text-muted px-4 py-2 disabled:opacity-30">← Kembali</button>
                @if ($step < 12)
                    <button type="button" wire:click="nextStep" class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">Lanjut →</button>
                @endif
            </div>
        </div>
    @else
        <div class="bg-white border border-[#e6e9ef] rounded-xl p-8 text-center">
            <p class="text-3xl mb-2">✅</p>
            <h2 class="text-[16px] font-bold text-[#1f8a4c] mb-1">Lamaran Terkirim</h2>
            <p class="text-[13px] text-muted mb-1">Terima kasih, lamaran Anda untuk <b>{{ $this->vacancy->title }}</b> sudah kami terima.</p>
            @if ($lastSubmissionUuid)
                <p class="text-[10px] text-muted mb-4">Ref: {{ $lastSubmissionUuid }}</p>
            @endif
            <div class="flex justify-center gap-3">
                <a href="{{ route('careers.dashboard') }}" class="bg-navy text-white text-[13px] font-bold px-4 py-2 rounded-md">Lihat Lamaran Saya</a>
                <a href="{{ route('careers.landing') }}" class="border border-[#d7dbe2] text-[13px] font-bold px-4 py-2 rounded-md">Lowongan Lain</a>
            </div>
        </div>
    @endif
</div>
