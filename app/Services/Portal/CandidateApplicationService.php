<?php

namespace App\Services\Portal;

use App\Services\Recruitment\FirebaseSyncAdapter;
use App\Support\Recruitment\CandidateFormOptions;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/*
 * REMARK
 * Tujuan       : Form lamaran "Data Calon Karyawan" (LJM/HRD/P01/F02) di satu tempat: isian awal (identitas dari akun + lamaran terakhir), aturan validasi semua seksi wajib, dan pembentukan payload ke Firestore. Dipindah dari careers/apply.blade.php (Livewire v1) tanpa mengubah aturan.
 * Sumber hulu  : akun kandidat (CandidateAuthService), lamaran terakhir (FirebaseSyncAdapter::findLatestByNationalId), pilihan dan kesediaan dari CandidateFormOptions.
 * Input        : array kandidat, isian POST form (datar; seksi berulang = baris x-dl::lines), berkas CV dan ijazah.
 * Output       : nilai awal form, Validator (satu galat per isian yang kurang), payload + berkas ke FirebaseSyncAdapter::saveSubmission, uuid lamaran.
 * Dipakai oleh : App\Http\Controllers\Portal\CareersController (apply, submit).
 * Status       : Aktif (T10 tahap 6b, 2026-10-21).
 */
class CandidateApplicationService
{
    public const ROWS = [
        'family' => ['relation_type' => 'SPOUSE', 'name' => '', 'gender' => '', 'age' => '', 'last_education' => '', 'current_job' => '', 'current_employer' => ''],
        'education' => ['level' => '', 'institution' => '', 'major' => '', 'period_start_year' => '', 'graduation_year' => '', 'final_score' => ''],
        'educationNonformal' => ['course_type' => '', 'institution' => '', 'period_start_year' => '', 'period_end_year' => '', 'certificate_no' => ''],
        'orgExperience' => ['organization_name' => '', 'position' => '', 'period' => '', 'task_description' => ''],
        'workExp' => ['company' => '', 'location' => '', 'business_type' => '', 'employee_count' => '', 'company_phone' => '', 'position' => '', 'start_date' => '', 'end_date' => '',
            'position_end' => '', 'position_end_start_date' => '', 'position_end_end_date' => '', 'subordinate_count_start' => '', 'subordinate_count_end' => '', 'salary_start' => '',
            'salary_end' => '', 'benefits' => '', 'supervisor_name' => '', 'supervisor_phone' => '', 'achievement' => '', 'description' => '', 'org_structure_note' => '',
            'key_challenge' => '', 'improvement_made' => '', 'leave_reason' => ''],
        'professionalReferences' => ['name' => '', 'phone' => ''],
        'emergencyContacts' => ['name' => '', 'relation' => '', 'phone' => '', 'address' => ''],
        'languageSkills' => ['language' => '', 'written' => '', 'oral' => '', 'notes' => ''],
        'computerSkills' => ['skill' => '', 'level' => '', 'notes' => ''],
    ];

    private const PARENT = ['name' => '', 'age' => '', 'last_education' => '', 'current_job' => '', 'current_employer' => ''];

    // Isian teks / angka tunggal yang diisi ulang dari lamaran terakhir (sama dengan v1).
    private const CARRY = ['nickname', 'gender', 'religion', 'birth_date', 'address', 'address_domisili', 'vehicle_type', 'marital_status', 'marital_date',
        'company_acquaintance', 'hobby', 'leisure_activity', 'reading_topics', 'went_abroad', 'why_join_us', 'what_you_know_about_us',
        'contribution_plan', 'role_plan_if_hired', 'job_type_preference', 'job_nature_preference', 'work_environment_preference',
        'hindering_environment', 'preferred_colleague_type', 'difficult_decision_topic', 'strengths_weaknesses', 'who_you_ask_when_stuck',
        'height_cm', 'weight_kg', 'blood_type', 'illness_history', 'hospitalized_history', 'is_smoker', 'physical_disability',
        'family_health_ok', 'still_receiving_aid', 'other_dependents', 'source_of_info', 'expected_salary', 'expected_facility',
        'availability_date', 'agreement_signed', 'signature_name'];

    // Kunci array lamaran terakhir -> nama isian form.
    private const CARRY_ROWS = ['education' => 'education', 'education_nonformal' => 'educationNonformal', 'org_experience' => 'orgExperience',
        'work_experience' => 'workExp', 'emergency_contacts' => 'emergencyContacts', 'professional_references' => 'professionalReferences',
        'language_skills' => 'languageSkills', 'computer_skills' => 'computerSkills'];

    public function __construct(protected FirebaseSyncAdapter $submissions) {}

    /** Kolom x-dl::lines per seksi berulang: kunci = nama isian form (kunci ROWS). */
    public function columns(string $name): array
    {
        $c = fn (string $key, string $label, array $extra = []) => ['key' => $key, 'label' => raw($label)] + $extra;
        $level = CandidateFormOptions::SKILL_LEVEL;

        return match ($name) {
            'family' => [
                $c('relation_type', 'Hubungan', ['type' => 'select', 'options' => CandidateFormOptions::FAMILY_RELATION_EDITABLE, 'default' => 'SPOUSE', 'width' => '140px']),
                $c('name', 'Nama', ['required' => true]), $c('gender', 'L/P', ['type' => 'select', 'options' => ['L' => 'L', 'P' => 'P'], 'width' => '70px']),
                $c('age', 'Usia', ['width' => '70px']), $c('last_education', 'Pendidikan Terakhir'), $c('current_job', 'Pekerjaan'),
            ],
            'education' => [
                $c('level', 'Jenjang', ['placeholder' => 'SLTP/SLTA/D3/S1', 'width' => '130px']), $c('institution', 'Instansi', ['required' => true]), $c('major', 'Jurusan'),
                $c('period_start_year', 'Dari Th', ['width' => '90px']), $c('graduation_year', 'S/d Th', ['width' => '90px']), $c('final_score', 'Nilai Akhir', ['width' => '100px']),
            ],
            'educationNonformal' => [
                $c('course_type', 'Jenis Kursus/Pelatihan', ['required' => true]), $c('institution', 'Lembaga'), $c('period_start_year', 'Dari Th', ['width' => '90px']),
                $c('period_end_year', 'S/d Th', ['width' => '90px']), $c('certificate_no', 'No. Sertifikat', ['width' => '140px']),
            ],
            'orgExperience' => [
                $c('organization_name', 'Nama Organisasi', ['required' => true]), $c('position', 'Jabatan', ['width' => '150px']),
                $c('period', 'Periode', ['placeholder' => '2020-2022', 'width' => '130px']), $c('task_description', 'Uraian Tugas'),
            ],
            'workExp' => [
                $c('company', 'Nama Perusahaan', ['required' => true]), $c('location', 'Lokasi & Jenis Usaha'), $c('position', 'Jabatan Awal'),
                $c('start_date', 'Mulai (Jabatan Awal)', ['type' => 'date', 'more' => true]), $c('end_date', 'Selesai (Jabatan Awal)', ['type' => 'date', 'more' => true]),
                $c('employee_count', 'Jumlah Karyawan', ['more' => true]), $c('company_phone', 'No. Telepon Perusahaan', ['more' => true]),
                $c('position_end', 'Jabatan Terakhir', ['more' => true]), $c('position_end_start_date', 'Mulai (Jabatan Terakhir)', ['type' => 'date', 'more' => true]),
                $c('position_end_end_date', 'Selesai (Jabatan Terakhir)', ['type' => 'date', 'more' => true]),
                $c('subordinate_count_start', 'Bawahan (awal)', ['more' => true]), $c('subordinate_count_end', 'Bawahan (akhir)', ['more' => true]),
                $c('salary_start', 'Gaji Awal', ['type' => 'money', 'more' => true]), $c('salary_end', 'Gaji Terakhir', ['type' => 'money', 'more' => true]),
                $c('benefits', 'Fasilitas selain gaji', ['more' => true]), $c('supervisor_name', 'Atasan Langsung', ['more' => true]),
                $c('supervisor_phone', 'No. Telp Atasan', ['more' => true]), $c('achievement', 'Prestasi', ['more' => true]),
                $c('description', 'Uraian Pekerjaan', ['more' => true]), $c('org_structure_note', 'Struktur Organisasi (posisi Anda)', ['more' => true]),
                $c('key_challenge', 'Masalah penting yang pernah dihadapi & cara mengatasinya', ['more' => true]),
                $c('improvement_made', 'Perubahan/pembaruan yang pernah Anda lakukan', ['more' => true]), $c('leave_reason', 'Alasan Keluar', ['more' => true]),
            ],
            'professionalReferences' => [$c('name', 'Nama', ['required' => true]), $c('phone', 'No. Telp', ['required' => true, 'width' => '200px'])],
            'emergencyContacts' => [
                $c('name', 'Nama', ['required' => true]), $c('relation', 'Hubungan', ['required' => true, 'width' => '150px']),
                $c('phone', 'No. Telp', ['required' => true, 'width' => '150px']), $c('address', 'Alamat', ['required' => true]),
            ],
            'languageSkills' => [
                $c('language', 'Bahasa', ['required' => true]), $c('written', 'Tertulis', ['type' => 'select', 'options' => $level, 'width' => '140px']),
                $c('oral', 'Lisan', ['type' => 'select', 'options' => $level, 'width' => '140px']), $c('notes', 'Keterangan'),
            ],
            'computerSkills' => [
                $c('skill', 'Skill', ['required' => true, 'placeholder' => 'mis. Desain Grafis']), $c('level', 'Level', ['type' => 'select', 'options' => $level, 'width' => '140px']),
                $c('notes', 'Keterangan'),
            ],
        };
    }

    /** Nilai awal form: identitas dari akun terverifikasi, sisanya dari lamaran terakhir bila ada. */
    public function formValues(array $candidate): array
    {
        $v = array_fill_keys(self::CARRY, '') + [
            'name' => $candidate['name'] ?? '', 'national_id' => $candidate['national_id'] ?? '', 'email' => $candidate['email'] ?? '', 'phone' => $candidate['phone'] ?? '',
            'sim_types' => [], 'willingness' => array_fill_keys(array_keys(CandidateFormOptions::WILLING_ITEMS), false),
            'father' => self::PARENT, 'mother' => self::PARENT, 'family' => [],
        ];
        $v['height_cm'] = $v['weight_kg'] = $v['expected_salary'] = $v['is_smoker'] = $v['family_health_ok'] = null;
        $v['agreement_signed'] = false;
        foreach (self::CARRY_ROWS as $name) {
            $v[$name] = [];
        }

        $last = $this->submissions->findLatestByNationalId($v['national_id']);
        if (! $last) {
            return $v;
        }

        foreach (self::CARRY as $field) {
            if (array_key_exists($field, $last) && $last[$field] !== null) {
                $v[$field] = $last[$field];
            }
        }
        foreach (['is_smoker', 'family_health_ok'] as $flag) {
            $v[$flag] = $v[$flag] === null || $v[$flag] === '' ? null : (string) (int) $v[$flag];
        }
        $v['sim_types'] = $last['sim_types'] ?? [];
        foreach (self::CARRY_ROWS as $key => $name) {
            $v[$name] = $last[$key] ?? [];
        }
        $v['willingness'] = array_merge($v['willingness'], $last['willingness_checklist'] ?? []);

        foreach ($last['family_members'] ?? [] as $f) {
            match ($f['relation_type'] ?? null) {
                'FATHER' => $v['father'] = array_merge($v['father'], $f),
                'MOTHER' => $v['mother'] = array_merge($v['mother'], $f),
                default => $v['family'][] = $f,
            };
        }

        return $v;
    }

    public function validator(array $in): ValidatorContract
    {
        $rules = [
            'name' => ['required', 'min:3'], 'nickname' => ['required'], 'gender' => ['required'], 'religion' => ['required'],
            'phone' => ['required'], 'birth_date' => ['required', 'date'], 'address' => ['required'], 'address_domisili' => ['required'],
            'vehicle_type' => ['required'], 'marital_status' => ['required'],
            'marital_date' => [Rule::requiredIf(($in['marital_status'] ?? '') !== '' && ($in['marital_status'] ?? '') !== 'LAJANG')],

            'father.name' => ['required'], 'mother.name' => ['required'],

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
            'cv_file' => ['required', 'file', 'max:12288'], 'ijazah_file' => ['required', 'file', 'max:12288'],
        ];

        $attributes = [
            'father.name' => 'Nama Ayah', 'mother.name' => 'Nama Ibu',
            'education' => 'Pendidikan Formal', 'educationNonformal' => 'Pendidikan Non-Formal', 'orgExperience' => 'Pengalaman Organisasi',
            'workExp' => 'Pengalaman Kerja', 'professionalReferences' => 'Referensi', 'emergencyContacts' => 'Kontak Darurat',
            'languageSkills' => 'Bahasa Asing', 'computerSkills' => 'Penguasaan Komputer', 'cv_file' => 'CV', 'ijazah_file' => 'Ijazah',
        ];

        $validator = Validator::make($in, $rules, [], $attributes);

        // Semua poin Kesediaan wajib dicentang; digabung ke bag galat yang sama supaya kandidat melihat semua kekurangan sekaligus.
        $validator->after(function ($v) use ($in) {
            $checked = array_filter((array) ($in['willingness'] ?? []));
            if (count(array_intersect_key($checked, CandidateFormOptions::WILLING_ITEMS)) < count(CandidateFormOptions::WILLING_ITEMS)) {
                $v->errors()->add('willingness', 'Semua poin Kesediaan & Pernyataan wajib dicentang.');
            }
        });

        return $validator;
    }

    /** Simpan lamaran ke Firestore; mengembalikan uuid lamaran. */
    public function submit(array $in, array $candidate, string $vacancyCode, ?UploadedFile $cv, ?UploadedFile $ijazah): string
    {
        $clean = fn (array $row) => array_map(fn ($x) => $x === '' ? null : $x, $row);
        $rows = fn (string $name) => array_values(array_map(fn ($r) => array_merge(self::ROWS[$name], $r), $in[$name] ?? []));
        $n = fn (string $field) => ($in[$field] ?? null) ?: null;

        $father = $clean(['relation_type' => 'FATHER', 'gender' => 'L'] + array_merge(self::PARENT, $in['father'] ?? []));
        $mother = $clean(['relation_type' => 'MOTHER', 'gender' => 'P'] + array_merge(self::PARENT, $in['mother'] ?? []));

        $payload = [
            'vacancy_code' => $vacancyCode,
            'candidate_uid' => $candidate['_docId'],
            'national_id' => $candidate['national_id'],
            'name' => $in['name'], 'nickname' => $n('nickname'), 'gender' => $n('gender'),
            'religion' => $n('religion'), 'email' => $candidate['email'], 'phone' => $in['phone'],
            'birth_date' => $n('birth_date'), 'address' => $in['address'],
            'address_domisili' => $n('address_domisili'), 'vehicle_type' => $n('vehicle_type'),
            'sim_types' => array_values($in['sim_types'] ?? []), 'marital_status' => $n('marital_status'),
            'marital_date' => $n('marital_date'),
            'company_acquaintance' => $n('company_acquaintance'), 'hobby' => $n('hobby'),
            'leisure_activity' => $n('leisure_activity'), 'reading_topics' => $n('reading_topics'),
            'went_abroad' => $n('went_abroad'),
            'emergency_contacts' => $rows('emergencyContacts'),
            'professional_references' => $rows('professionalReferences'),
            'why_join_us' => $n('why_join_us'), 'what_you_know_about_us' => $n('what_you_know_about_us'),
            'contribution_plan' => $n('contribution_plan'), 'role_plan_if_hired' => $n('role_plan_if_hired'),
            'job_type_preference' => $n('job_type_preference'), 'job_nature_preference' => $n('job_nature_preference'),
            'work_environment_preference' => $n('work_environment_preference'), 'hindering_environment' => $n('hindering_environment'),
            'preferred_colleague_type' => $n('preferred_colleague_type'), 'difficult_decision_topic' => $n('difficult_decision_topic'),
            'strengths_weaknesses' => $n('strengths_weaknesses'), 'who_you_ask_when_stuck' => $n('who_you_ask_when_stuck'),
            'height_cm' => (int) $in['height_cm'], 'weight_kg' => (int) $in['weight_kg'], 'blood_type' => $n('blood_type'),
            'illness_history' => $n('illness_history'), 'hospitalized_history' => $n('hospitalized_history'),
            'is_smoker' => (bool) (int) $in['is_smoker'], 'physical_disability' => $n('physical_disability'),
            'family_health_ok' => (bool) (int) $in['family_health_ok'],
            'language_skills' => $rows('languageSkills'),
            'computer_skills' => $rows('computerSkills'),
            'still_receiving_aid' => $n('still_receiving_aid'), 'other_dependents' => $n('other_dependents'),
            'source_of_info' => $n('source_of_info'), 'expected_salary' => (float) $in['expected_salary'],
            'expected_facility' => $n('expected_facility'), 'availability_date' => $n('availability_date'),
            'willingness_checklist' => array_map('boolval', array_replace(array_fill_keys(array_keys(CandidateFormOptions::WILLING_ITEMS), false), (array) ($in['willingness'] ?? []))),
            'agreement_signed' => true,
            'signature_name' => $in['signature_name'], 'agreement_signed_at' => now()->toDateTimeString(),
            'family_members' => array_merge([$father, $mother], array_map($clean, $rows('family'))),
            'education' => array_map($clean, $rows('education')),
            'education_nonformal' => array_map($clean, $rows('educationNonformal')),
            'org_experience' => array_map($clean, $rows('orgExperience')),
            'work_experience' => array_map($clean, $rows('workExp')),
        ];

        return $this->submissions->saveSubmission($payload, ['cv' => $cv, 'ijazah' => $ijazah]);
    }
}
