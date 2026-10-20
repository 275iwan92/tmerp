{{--
 REMARK
 Tujuan       : Review Formulir Kandidat (menggantikan halaman v1 pages::recruitment.candidate-review.detail): tampilan baca-saja lengkap "Data Calon Karyawan" per lamaran supaya HR meninjau sebelum memutuskan lanjut atau tolak di Pipeline Board; tab Personal, Family, Education, Experience, Motivation, Health, Skills, Contacts, Agreement dan Documents. Tidak ada aksi tulis.
 Sumber hulu  : tm2hrr.app_application + cnd_candidate + cnd_family_member + cnd_education + cnd_education_nonformal + cnd_organization_experience + cnd_work_experience + cnd_document + vac_vacancy + app_pipeline_stage_config.
 Input        : Query application (app_application.id_application).
 Output       : Hanya baca; tombol ke Pipeline Board lowongan itu.
 Dipakai oleh : Dibuka dari grid hrm-rcr-review (Open). Formulir yang sama diisi kandidat di portal karir publik (careers/apply), yang tidak diubah.
 Status       : Aktif (T7n HRM).
--}}
@php
    use App\Models\Hrr\AppApplication;
    use App\Models\Hrr\AppPipelineStageConfig;
    use App\Models\Hrr\CndCandidate;
    use App\Support\Recruitment\CandidateFormOptions as Opt;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'hrm-rcr-review');
    $a = AppApplication::with('candidate', 'vacancy.requisition')->find((int) request('application'));
    abort_unless($a, 404);
    $c = CndCandidate::with(['familyMembers', 'education', 'educationNonformal', 'organizationExperience', 'workExperience', 'documents'])->findOrFail($a->id_candidate);
    $stage = AppPipelineStageConfig::where('id_track', $a->vacancy?->requisition?->id_track)->where('stage_seq', $a->current_stage_seq)->value('stage_name');
    $s = fn (string $col) => Src::of('tm2hrr.cnd_candidate.'.$col);
    $dash = fn ($v) => ($v === null || $v === '' || $v === []) ? '—' : $v;
    $date = fn ($d) => $d ? Locale::date($d) : '—';
    $yn = fn ($v) => $v === null ? '—' : ($v ? 'Yes' : 'No');
    $gender = ['LAKI_LAKI' => 'Male', 'PEREMPUAN' => 'Female'];
    $marital = ['LAJANG' => 'Single', 'MENIKAH' => 'Married', 'DUDA' => 'Widower', 'JANDA' => 'Widow'];
    $vehicle = ['MOTOR' => 'Motorcycle', 'MOBIL' => 'Car', 'TIDAK_ADA' => 'None'];
    // Tabel dari array baris: kolom = kunci baris pertama (kecuali id), nilai dibungkus raw().
    $table = function ($rows, array $skip = []) {
        $rows = collect($rows)->map(fn ($r) => is_array($r) ? $r : (array) $r)->values();
        $keys = $rows->isEmpty() ? [] : array_values(array_filter(array_keys($rows->first()), fn ($k) => ! in_array($k, array_merge(['id', 'id_candidate', 'created_at', 'updated_at'], $skip), true) && ! str_starts_with((string) $k, 'id_')));
        $cols = collect($keys)->mapWithKeys(fn ($k) => [$k => ucfirst(str_replace('_', ' ', $k))])->all();
        $cell = fn ($v) => is_scalar($v) ? (preg_match('/^\d{4}-\d{2}-\d{2}T/', (string) $v) ? Locale::date($v) : (string) $v) : ($v === null ? '—' : json_encode($v, JSON_UNESCAPED_UNICODE));
        $out = $rows->map(fn ($r) => collect($keys)->mapWithKeys(fn ($k) => [$k => raw($cell($r[$k] ?? null))])->all())->all();

        return [$cols, $out];
    };
    $personal = [
        'Name' => $c->name, 'Nickname' => $dash($c->nickname), 'National ID' => $dash($c->national_id), 'Gender' => $gender[$c->gender] ?? $dash($c->gender), 'Religion' => $dash($c->religion), 'Birth date' => $date($c->birth_date),
        'Email' => $dash($c->email), 'Phone' => $dash($c->phone), 'Marital status' => $marital[$c->marital_status] ?? $dash($c->marital_status), 'Marital date' => $date($c->marital_date),
        'Vehicle' => $vehicle[$c->vehicle_type] ?? $dash($c->vehicle_type), 'Driving licences (SIM)' => $dash(implode(', ', (array) $c->sim_types)),
    ];
    $motivation = ['Acquaintance at the company' => $c->company_acquaintance, 'Hobby' => $c->hobby, 'Leisure activity' => $c->leisure_activity, 'Reading topics' => $c->reading_topics, 'Went abroad' => $c->went_abroad,
        'Why join us' => $c->why_join_us, 'What they know about us' => $c->what_you_know_about_us, 'Planned contribution' => $c->contribution_plan, 'Role planned if hired' => $c->role_plan_if_hired,
        'Preferred job type' => $c->job_type_preference, 'Preferred job nature' => $c->job_nature_preference, 'Preferred work environment' => $c->work_environment_preference, 'Hindering environment' => $c->hindering_environment,
        'Preferred colleague type' => $c->preferred_colleague_type, 'Hardest decision' => $c->difficult_decision_topic, 'Strengths and weaknesses' => $c->strengths_weaknesses, 'Who they ask when stuck' => $c->who_you_ask_when_stuck];
    $health = ['Height (cm)' => $dash($c->height_cm), 'Weight (kg)' => $dash($c->weight_kg), 'Blood type' => $dash($c->blood_type), 'Smoker' => $yn($c->is_smoker), 'Family health is fine' => $yn($c->family_health_ok),
        'Physical disability' => $dash($c->physical_disability), 'Illness history' => $dash($c->illness_history), 'Hospitalised history' => $dash($c->hospitalized_history), 'Still receiving aid' => $dash($c->still_receiving_aid), 'Other dependents' => $dash($c->other_dependents)];
    $willing = (array) $c->willingness_checklist;
    [$famCols, $famRows] = $table($c->familyMembers->map->toArray());
    [$eduCols, $eduRows] = $table($c->education->map->toArray());
    [$nfCols, $nfRows] = $table($c->educationNonformal->map->toArray());
    [$orgCols, $orgRows] = $table($c->organizationExperience->map->toArray());
    [$workCols, $workRows] = $table($c->workExperience->map->toArray());
    [$langCols, $langRows] = $table($c->language_skills ?? []);
    [$pcCols, $pcRows] = $table($c->computer_skills ?? []);
    [$emCols, $emRows] = $table($c->emergency_contacts ?? []);
    [$refCols, $refRows] = $table($c->professional_references ?? []);
    $tabs = ['personal' => t('Personal'), 'family' => t('Family'), 'education' => t('Education'), 'experience' => t('Experience'), 'motivation' => t('Motivation'), 'health' => t('Health'), 'skills' => t('Skills'), 'contacts' => t('Contacts'), 'agreement' => t('Agreement')];
@endphp

<x-dl::form id="hrm-rcr-review-form" :title="raw($c->name)" :subtitle="raw($a->vacancy?->title ?? '—')" panel="top" kind="master" status="active" :can-input="false" :hide="['deactivate']" :back="$home" :tabs="$tabs">
    <x-slot:special>
        @if ($a->id_vacancy)
            <x-dl::button variant="secondary" icon="fa-diagram-next" label="Open Pipeline Board" :href="route('v2.screen', 'hrm-pipeline-board').'?vacancy='.$a->id_vacancy" />
        @endif
    </x-slot:special>
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Candidate" :value="raw($c->name)" />
        <x-dl::strip-kv kind="grand" label="Vacancy" :value="raw($a->vacancy?->title ?? '—')" />
        <x-dl::strip-kv kind="grand" label="Status" :value="raw($a->status)" />
        <x-dl::strip-kv kind="grand" label="Stage" :value="raw($stage ?? '—')" />
    </x-slot:strip>

    <x-slot:personal>
        <x-dl::card title="Personal data">
            @foreach (array_chunk($personal, 3, true) as $chunk)
                <x-dl::form.row :cols="3">
                    @foreach ($chunk as $label => $value)
                        <x-dl::field.display :label="$label" :value="raw((string) $value)" :source="$s('name')" />
                    @endforeach
                </x-dl::form.row>
            @endforeach
            <x-dl::field.display label="Address (as on KTP)" :value="raw($dash($c->address))" :source="$s('address')" />
            <x-dl::field.display label="Residence address" :value="raw($dash($c->address_domisili))" :source="$s('address_domisili')" />
        </x-dl::card>
    </x-slot:personal>

    <x-slot:family>
        <x-dl::card title="Family"><x-dl::table :columns="$famCols" :rows="$famRows" /></x-dl::card>
    </x-slot:family>

    <x-slot:education>
        <x-dl::card title="Formal education"><x-dl::table :columns="$eduCols" :rows="$eduRows" /></x-dl::card>
        <x-dl::card title="Non-formal education and training"><x-dl::table :columns="$nfCols" :rows="$nfRows" /></x-dl::card>
    </x-slot:education>

    <x-slot:experience>
        <x-dl::card title="Work experience"><x-dl::table :columns="$workCols" :rows="$workRows" /></x-dl::card>
        <x-dl::card title="Organisation experience"><x-dl::table :columns="$orgCols" :rows="$orgRows" /></x-dl::card>
    </x-slot:experience>

    <x-slot:motivation>
        <x-dl::card title="Motivation and preferences">
            @foreach ($motivation as $label => $value)
                <x-dl::field.display :label="$label" :value="raw((string) $dash($value))" :source="$s('why_join_us')" />
            @endforeach
        </x-dl::card>
    </x-slot:motivation>

    <x-slot:health>
        <x-dl::card title="Health and dependents">
            @foreach (array_chunk($health, 3, true) as $chunk)
                <x-dl::form.row :cols="3">
                    @foreach ($chunk as $label => $value)
                        <x-dl::field.display :label="$label" :value="raw((string) $value)" :source="$s('height_cm')" />
                    @endforeach
                </x-dl::form.row>
            @endforeach
        </x-dl::card>
    </x-slot:health>

    <x-slot:skills>
        <x-dl::card title="Language skills"><x-dl::table :columns="$langCols" :rows="$langRows" /></x-dl::card>
        <x-dl::card title="Computer skills"><x-dl::table :columns="$pcCols" :rows="$pcRows" /></x-dl::card>
        <x-dl::card title="Expectations">
            <x-dl::form.row :cols="3">
                <x-dl::field.display label="Expected salary (IDR)" :value="$c->expected_salary !== null ? Locale::number((float) $c->expected_salary, 2) : '—'" :source="$s('expected_salary')" />
                <x-dl::field.display label="Expected facilities" :value="raw($dash($c->expected_facility))" :source="$s('expected_facility')" />
                <x-dl::field.display label="Available from" :value="$date($c->availability_date)" :source="$s('availability_date')" />
            </x-dl::form.row>
            <x-dl::field.display label="Source of information" :value="raw($dash($c->source_of_info))" :source="$s('source_of_info')" />
        </x-dl::card>
    </x-slot:skills>

    <x-slot:contacts>
        <x-dl::card title="Emergency contacts"><x-dl::table :columns="$emCols" :rows="$emRows" /></x-dl::card>
        <x-dl::card title="Professional references"><x-dl::table :columns="$refCols" :rows="$refRows" /></x-dl::card>
        <x-dl::card title="Documents">
            @forelse ($c->documents as $doc)
                <x-dl::button size="sm" variant="secondary" icon="fa-paperclip" :label="$doc->doc_type.' · '.$date($doc->uploaded_at)" :href="$doc->file_path" />
            @empty
                <x-dl::hint note>No documents uploaded.</x-dl::hint>
            @endforelse
        </x-dl::card>
    </x-slot:contacts>

    <x-slot:agreement>
        <x-dl::card title="Willingness">
            @foreach (Opt::WILLING_ITEMS as $key => $label)
                @php($on = isset($willing[$key]) ? (bool) $willing[$key] : in_array($key, $willing, true))
                <x-dl::field.display :label="$label" :value="$on ? 'Yes' : 'No'" :source="$s('willingness_checklist')" />
            @endforeach
        </x-dl::card>
        <x-dl::card title="Agreement">
            <x-dl::form.row :cols="3">
                <x-dl::field.display label="Agreement signed" :value="$yn((bool) $c->agreement_signed)" :source="$s('agreement_signed')" />
                <x-dl::field.display label="Signature name" :value="raw($dash($c->signature_name))" :source="$s('signature_name')" />
                <x-dl::field.display label="Signed at" :value="$c->agreement_signed_at ? Locale::dateTime($c->agreement_signed_at) : '—'" :source="$s('agreement_signed_at')" />
            </x-dl::form.row>
        </x-dl::card>
    </x-slot:agreement>
</x-dl::form>
