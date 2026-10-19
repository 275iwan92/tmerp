{{--
 REMARK
 Tujuan       : Form Job Vacancy (menggantikan dialog Create and Publish halaman v1 pages::recruitment.vacancy): lowongan baru dari requisition APPROVED (Requisition, Channel, Vacancy title, Public description; Save = terbitkan ke portal karir), dan lowongan yang ada baca-saja dengan tombol Close vacancy (kandidat tidak bisa melamar lagi), jumlah pelamar dan tab History.
 Sumber hulu  : tm2hrr.vac_vacancy + rcr_requisition + app_application; aturan di App\Services\Recruitment\RecruitmentVacancyService.
 Input        : Query id (vac_vacancy.id_vacancy) atau new=1; session v2_flash dan errors.
 Output       : POST v2.hrm.vacancy.save (Save = publish, action close).
 Dipakai oleh : Dibuka dari grid hrm-rcr-vacancy (Open dan + New Record).
 Status       : Aktif (T7g HRM).
--}}
@php
    use App\Models\Hrr\VacVacancy;
    use App\Services\Recruitment\RecruitmentVacancyService as Vac;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'hrm-rcr-vacancy');
    $svc = app(Vac::class);
    $v = request('id') ? VacVacancy::with('requisition')->withCount('applications')->find((int) request('id')) : null;
    abort_if(request('id') && ! $v, 404);
    abort_unless($v || request('new'), 404);
    $new = ! $v;
    $v ??= new VacVacancy(['channel' => 'PORTAL_SENDIRI']);
    $open = ! $new && $v->is_published && ! $v->closed_at;
    $status = $new ? '—' : Vac::status($v);
    $s = fn (string $col) => Src::of('tm2hrr.vac_vacancy.'.$col);
    $val = fn (string $col) => old($col, $v->{$col});
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="hrm-rcr-vacancy-form" :title="$new ? 'New Vacancy' : raw($v->title)" :subtitle="$new ? null : raw($v->vacancy_code)"
    panel="top" kind="master" :status="$new ? 'new' : ($open ? 'active' : 'inactive')" :stage="$new ? null : t($status)" :can-input="$new" :hide="['deactivate', 'activate']"
    :action="route('v2.hrm.vacancy.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hidden="$new ? [] : ['id' => $v->id_vacancy]"
    :tabs="['general' => t('Vacancy')]" :log="$new ? [] : $svc->history($v)"
    :created-at="$v->created_at?->toDateTimeString()" :updated-at="$v->updated_at?->toDateTimeString()">
    @if ($open)
        <x-slot:special>
            <x-dl::button variant="secondary" icon="fa-ban" label="Close vacancy" type="submit" name="action" value="close" formnovalidate data-dl-confirm="{{ t('Close this vacancy? Candidates can no longer apply.') }}" />
        </x-slot:special>
    @endif
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Vacancy code" :value="$new ? t('(on Save)') : raw($v->vacancy_code)" />
        <x-dl::strip-kv kind="grand" label="Status" :value="$new ? '—' : t($status)" />
        <x-dl::strip-kv kind="grand" label="Published" :value="$v->published_at ? Locale::date($v->published_at) : '—'" />
        <x-dl::strip-kv kind="grand" label="Applicants" :value="$new ? '—' : Locale::number((float) $v->applications_count, 0)" />
    </x-slot:strip>

    <x-slot:general>
        <x-dl::card title="Vacancy">
            @if ($new)
                <x-dl::hint note>Saving publishes the vacancy to the careers portal at once. Only approved requisitions can be chosen; the vacancy title is filled from the position and can be changed.</x-dl::hint>
            @endif
            <x-dl::form.row :cols="3">
                <x-dl::field.lookup name="id_requisition" lookup="rcr_requisition_approved" label="Requisition" required placeholder="Search approved requisition..." :value="$val('id_requisition')" :source="$s('id_requisition')" />
                <x-dl::field.select name="channel" label="Channel" required :options="Vac::CHANNELS + (isset(Vac::CHANNELS[$v->channel]) || ! $v->channel ? [] : [$v->channel => $v->channel])" :value="$val('channel')" :placeholder="null" :source="$s('channel')" />
                <x-dl::field.text name="title" label="Vacancy title" required placeholder="e.g. Offset Press Operator" :value="$val('title')" :source="$s('title')" />
            </x-dl::form.row>
            <x-dl::field.text name="description" label="Public description" multiline :value="$val('description')" :source="$s('description')" />
        </x-dl::card>
    </x-slot:general>
</x-dl::form>
