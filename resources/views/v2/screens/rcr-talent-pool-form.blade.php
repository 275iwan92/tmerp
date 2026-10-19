{{--
 REMARK
 Tujuan       : Form Talent Pool (menggantikan halaman v1 pages::recruitment.talent-pool): satu kandidat yang sudah ada (data baca-saja), riwayat lamarannya, dan kartu Invite to vacancy yang membuat lamaran baru ke lowongan terbuka tanpa mengisi ulang data.
 Sumber hulu  : tm2hrr.cnd_candidate + app_application + vac_vacancy; aturan di App\Services\Recruitment\RecruitmentInviteService.
 Input        : Query id (cnd_candidate.id_candidate); session v2_flash dan errors.
 Output       : POST v2.hrm.talentpool.invite (id, id_vacancy).
 Dipakai oleh : Dibuka dari grid rcr-talent-pool (Open); kandidat baru tidak diketik di sini, masuk lewat portal karir.
 Status       : Aktif (T7h HRM).
--}}
@php
    use App\Models\Hrr\AppApplication;
    use App\Models\Hrr\CndCandidate;
    use App\Services\Recruitment\RecruitmentInviteService as Invite;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'rcr-talent-pool');
    $svc = app(Invite::class);
    $c = CndCandidate::find((int) request('id'));
    abort_unless($c, 404);
    $apps = AppApplication::with('vacancy')->where('id_candidate', $c->id_candidate)->orderByDesc('applied_at')->orderByDesc('id_application')->get();
    $last = $apps->first();
    $active = $apps->whereIn('status', Invite::ACTIVE)->count();
    $s = fn (string $col) => Src::of('tm2hrr.cnd_candidate.'.$col);
    $rows = $apps->map(fn ($a) => ['code' => (string) $a->vacancy?->vacancy_code, 'vacancy' => (string) ($a->vacancy?->title ?? '—'), 'applied' => $a->applied_at ? Locale::date($a->applied_at) : '—',
        'stage' => (string) $a->current_stage_seq, 'status' => (string) $a->status])->all();
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="rcr-talent-pool-form" :title="raw($c->name)" subtitle="Talent Pool" panel="top" kind="master" status="active" :can-input="true"
    :hide="['deactivate', 'new', 'undo', 'save']" :action="route('v2.hrm.talentpool.invite')" :back="$home" :hidden="['id' => $c->id_candidate]"
    :tabs="['general' => t('Candidate')]" :log="$svc->history($c)" :created-at="$c->created_at?->toDateTimeString()" :updated-at="$c->updated_at?->toDateTimeString()">
    <x-slot:special>
        <x-dl::button variant="primary" icon="fa-paper-plane" label="Invite to vacancy" type="submit" />
    </x-slot:special>
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Candidate" :value="raw($c->name)" />
        <x-dl::strip-kv kind="grand" label="Applications" :value="Locale::number((float) $apps->count(), 0)" />
        <x-dl::strip-kv kind="grand" label="Active applications" :value="Locale::number((float) $active, 0)" />
        <x-dl::strip-kv kind="grand" label="Last status" :value="raw($last?->status ?? '—')" />
    </x-slot:strip>

    <x-slot:general>
        <x-dl::card title="Candidate (read-only)">
            <x-dl::form.row :cols="3">
                <x-dl::field.display label="Name" :value="raw($c->name)" :source="$s('name')" />
                <x-dl::field.display label="National ID" :value="raw($c->national_id ?: '—')" :source="$s('national_id')" />
                <x-dl::field.display label="Gender" :value="raw($c->gender ?: '—')" :source="$s('gender')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.display label="Email" :value="raw($c->email ?: '—')" :source="$s('email')" />
                <x-dl::field.display label="Phone" :value="raw($c->phone ?: '—')" :source="$s('phone')" />
                <x-dl::field.display label="Available from" :value="$c->availability_date ? Locale::date($c->availability_date) : '—'" :source="$s('availability_date')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="Invite to vacancy">
            <x-dl::hint note>Creates a new application (APPLIED, stage 1) without retyping the candidate data. The vacancy must be open, and the candidate must not already have an active application for it.</x-dl::hint>
            <x-dl::field.lookup name="id_vacancy" lookup="rcr_vacancy_open" label="Vacancy" required placeholder="Search open vacancy..." :value="old('id_vacancy')" :source="Src::of('tm2hrr.app_application.id_vacancy')" />
        </x-dl::card>
        <x-dl::lines name="applications" title="Applications" :readonly="true" :min-rows="0" :rows="$rows" source="{{ Src::of('tm2hrr.app_application') }}"
            :columns="[['key' => 'code', 'label' => 'Vacancy code', 'type' => 'display', 'width' => '140px'], ['key' => 'vacancy', 'label' => 'Vacancy', 'type' => 'display', 'width' => '320px'], ['key' => 'applied', 'label' => 'Applied', 'type' => 'display', 'width' => '130px'], ['key' => 'stage', 'label' => 'Stage', 'type' => 'display', 'width' => '70px'], ['key' => 'status', 'label' => 'Status', 'type' => 'display', 'width' => '130px']]" />
    </x-slot:general>
</x-dl::form>
