{{--
 REMARK
 Tujuan       : Form Assessment Result (menggantikan dialog Enter Result halaman v1 pages::recruitment.assessment-result): hasil baru untuk lamaran yang menunggu hasil Psikotes / Interview (Application, Recommendation, Score 0-100, Notes wajib), dan hasil yang ada baca-saja dengan tab History.
 Sumber hulu  : tm2hrr.app_assessment_result + app_application + cnd_candidate + vac_vacancy + sec_user; aturan di App\Services\Recruitment\AssessmentResultService.
 Input        : Query id (app_assessment_result.id_result) atau new=1; session v2_flash dan errors.
 Output       : POST v2.hrm.assessment.save (Save).
 Dipakai oleh : Dibuka dari grid hrm-rcr-assessment (Open dan + New Record); papan Pipeline mengarahkan ke sini lewat pesan guard.
 Status       : Aktif (T7i HRM).
--}}
@php
    use App\Models\Hrr\AppAssessmentResult;
    use App\Models\Sys\SecUser;
    use App\Services\Recruitment\AssessmentResultService as Assess;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'hrm-rcr-assessment');
    $svc = app(Assess::class);
    $r = request('id') ? AppAssessmentResult::with('application.candidate', 'application.vacancy')->find((int) request('id')) : null;
    abort_if(request('id') && ! $r, 404);
    abort_unless($r || request('new'), 404);
    $new = ! $r;
    $s = fn (string $col) => Src::of('tm2hrr.app_assessment_result.'.$col);
    $recs = array_map('t', Assess::RECS);
    $cand = $r?->application?->candidate;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="hrm-rcr-assessment-form" :title="$new ? 'New Assessment Result' : raw(($cand?->name ?? '—').' — '.$r->stage_code)" :subtitle="$new ? null : 'Assessment Result'"
    panel="top" kind="master" :status="$new ? 'new' : 'active'" :can-input="$new" :hide="['deactivate']"
    :action="route('v2.hrm.assessment.save')" :back="$home" :new-url="$home.'?new=1'"
    :tabs="['general' => t('Result')]" :log="$new ? [] : $svc->history($r)">
    @unless ($new)
        <x-slot:strip>
            <x-dl::strip-kv kind="doc" label="Candidate" :value="raw($cand?->name ?? '—')" />
            <x-dl::strip-kv kind="grand" label="Stage" :value="raw($r->stage_code)" />
            <x-dl::strip-kv kind="grand" label="Recommendation" :value="$recs[$r->recommendation] ?? raw($r->recommendation)" />
            <x-dl::strip-kv kind="grand" label="Score" :value="$r->score !== null ? Locale::number((float) $r->score, 2) : '—'" />
        </x-slot:strip>
    @endunless

    <x-slot:general>
        @if ($new)
            <x-dl::card title="Result">
                <x-dl::hint note>Pick an application that is waiting for its Psikotes or Interview result. The stage is taken from the application. A result cannot be changed once saved, and the card can move to the next pipeline stage only after it exists.</x-dl::hint>
                <x-dl::form.row :cols="2">
                    <x-dl::field.lookup name="id_application" lookup="rcr_assessment_awaiting" label="Application awaiting result" required placeholder="Search candidate..." :value="old('id_application')" :source="Src::of('tm2hrr.app_assessment_result.id_application')" />
                    <x-dl::field.select name="recommendation" label="Recommendation" required :options="$recs" :value="old('recommendation', 'LANJUT')" :placeholder="null" :source="$s('recommendation')" />
                </x-dl::form.row>
                <x-dl::form.row :cols="2">
                    <x-dl::field.decimal name="score" label="Score (0-100, optional)" :value="old('score')" :source="$s('score')" />
                    <x-dl::field.text name="notes" label="Notes" required multiline :value="old('notes')" :source="$s('notes')" />
                </x-dl::form.row>
            </x-dl::card>
        @else
            <x-dl::card title="Result (read-only)">
                <x-dl::form.row :cols="4">
                    <x-dl::field.display label="Candidate" :value="raw($cand?->name ?? '—')" :source="Src::of('tm2hrr.cnd_candidate.name')" />
                    <x-dl::field.display label="Vacancy" :value="raw($r->application?->vacancy?->title ?? '—')" :source="Src::of('tm2hrr.vac_vacancy.title')" />
                    <x-dl::field.display label="Stage" :value="raw($r->stage_code)" :source="$s('stage_code')" />
                    <x-dl::field.display label="Assessed" :value="Locale::date($r->assessed_at)" :source="$s('assessed_at')" />
                </x-dl::form.row>
                <x-dl::form.row :cols="4">
                    <x-dl::field.display label="Recommendation" :value="$recs[$r->recommendation] ?? raw($r->recommendation)" :source="$s('recommendation')" />
                    <x-dl::field.display label="Score" :value="$r->score !== null ? Locale::number((float) $r->score, 2) : '—'" :source="$s('score')" />
                    <x-dl::field.display label="Assessor" :value="raw(SecUser::whereKey($r->assessor_id)->value('name') ?? '—')" :source="$s('assessor_id')" />
                </x-dl::form.row>
                <x-dl::field.display label="Notes" :value="raw($r->notes ?: '—')" :source="$s('notes')" />
            </x-dl::card>
        @endif
    </x-slot:general>
</x-dl::form>
