{{--
 REMARK
 Tujuan       : Papan kanban pipeline rekrutmen per lowongan: kartu lamaran bergerak maju satu tahap, Reject / Withdraw beralasan.
 Sumber hulu  : App\Support\V2\Data\RecruitmentPipeline (tm2hrr.app_application, app_pipeline_stage_config, vac_vacancy, app_assessment_result).
 Input        : Query vacancy (lowongan; bawaan Pipeline::defaultVacancy()); session v2_flash dan errors.
 Output       : x-dl::card + filter lowongan, x-dl::kanban kolom = tahap track, kartu = lamaran aktif; server memutuskan perpindahan.
 Dipakai oleh : Rute v2.screen dengan slug 'hrm-pipeline-board' (menu v2:hrm-pipeline-board di sys_app_menu), dirender view layar generik v2.
 Status       : Aktif (sama dengan layar v1, docs/recruitment_tier4.md section 9).
--}}
@php
    use App\Support\V2\Data\RecruitmentPipeline as Pipeline;
    use App\Support\V2\Src;

    $vacancyId = request()->integer('vacancy') ?: Pipeline::defaultVacancy();
    $vacancy = Pipeline::vacancy($vacancyId);
    $src = Src::of('tm2hrr.app_application', 'tm2hrr.app_pipeline_stage_config', 'tm2hrr.vac_vacancy', 'tm2hrr.app_assessment_result');
    $board = $vacancy ? Pipeline::board($vacancy) : null;
    $active = $vacancy ? Pipeline::active($vacancy) : collect();
    $stageNames = $vacancy ? Pipeline::stages($vacancy)->pluck('stage_name', 'stage_seq') : collect();
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::card title="Pipeline Board" description="Drag a card to the next column, or use the card menu. A card can only move forward one stage at a time.">
    <x-dl::action-form method="get" action="{{ route('v2.screen', 'hrm-pipeline-board') }}">
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="vacancy" label="Vacancy" :options="Pipeline::vacancies()->mapWithKeys(fn ($v) => [$v->id_vacancy => $v->title.' ('.$v->vacancy_code.')'])->all()" :value="$vacancy?->id_vacancy" :placeholder="null" :source="$src" />
        </x-dl::form.row>
        <x-dl::action-bar><x-dl::button variant="secondary" icon="fa-filter" label="Filter" /></x-dl::action-bar>
    </x-dl::action-form>
</x-dl::card>

@if (! $vacancy)
    <x-dl::alert tone="info" message="There is no open vacancy yet." />
@else
    <x-dl::kanban :columns="$board['columns']" :cards="$board['cards']" :transitions="$board['transitions']" :add-column="false" :add-card="false"
        move-url="{{ route('v2.pipeline.move') }}" :source="$src" />

    <x-dl::card title="Candidate Actions" description="Move a candidate to the next stage, reject with a reason, or mark the candidate as withdrawn.">
        <x-dl::action-form action="{{ route('v2.pipeline.action') }}">
            <input type="hidden" name="vacancy" value="{{ $vacancy->id_vacancy }}">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="application" label="Candidate" required :options="$active->mapWithKeys(fn ($a) => [$a->id_application => ($a->candidate->name ?? '—').' · '.($stageNames[$a->current_stage_seq] ?? $a->current_stage_seq)])->all()" placeholder="Choose candidate..." :source="$src" />
                <x-dl::field.text name="reason" label="Reject Reason" multiline hint="Required only when rejecting." :source="Src::of('tm2hrr.app_application.reject_reason')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button icon="fa-arrow-right" label="Continue" name="action" value="advance" />
                <x-dl::button variant="danger" icon="fa-ban" label="Reject" name="action" value="reject" data-dl-confirm="{{ t('Reject this candidate?') }}" />
                <x-dl::button variant="secondary" icon="fa-person-walking-arrow-right" label="Withdraw" name="action" value="withdraw" data-dl-confirm="{{ t('Mark this candidate as withdrawn?') }}" />
            </x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::card>
@endif
