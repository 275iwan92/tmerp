{{--
 REMARK
 Tujuan       : Form Interview Schedule (menggantikan kalender dan dialog Schedule Interview halaman v1 pages::recruitment.interview-schedule): jadwal baru untuk lamaran yang menunggu di tahap Interview HR / User (Application, Date, Time, Interviewer, Location), dan jadwal yang ada baca-saja dengan Mark Done dan Cancel schedule selama SCHEDULED, serta tab History.
 Sumber hulu  : tm2hrr.app_interview_schedule + app_application + cnd_candidate + vac_vacancy + sec_user; aturan di App\Services\Recruitment\InterviewScheduleService.
 Input        : Query id (app_interview_schedule.id_schedule) atau new=1; session v2_flash dan errors.
 Output       : POST v2.hrm.interview.save (Save, action done atau cancel).
 Dipakai oleh : Dibuka dari grid hrm-rcr-interview (Open dan + New Record) dan dari tautan notifikasi interviewer.
 Status       : Aktif (T7k HRM). Kalender v1 belum ada padanannya (menunggu keputusan Jo).
--}}
@php
    use App\Models\Hrr\AppInterviewSchedule;
    use App\Services\Recruitment\InterviewScheduleService as Iv;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'hrm-rcr-interview');
    $svc = app(Iv::class);
    $s = request('id') ? AppInterviewSchedule::with('application.candidate', 'application.vacancy')->find((int) request('id')) : null;
    abort_if(request('id') && ! $s, 404);
    abort_unless($s || request('new'), 404);
    $new = ! $s;
    $open = ! $new && $s->status === 'SCHEDULED';
    $src = fn (string $col) => Src::of('tm2hrr.app_interview_schedule.'.$col);
    $cand = $s?->application?->candidate;
    $who = $s ? (Iv::interviewers()[$s->interviewer_id] ?? '—') : null;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="hrm-rcr-interview-form" :title="$new ? 'New Interview Schedule' : raw(($cand?->name ?? '—').' — '.$s->stage_code)" :subtitle="$new ? null : 'Interview Schedule'"
    panel="top" kind="master" :status="$new ? 'new' : ($open ? 'active' : 'inactive')" :stage="$new ? null : t(ucfirst(strtolower($s->status)))" :can-input="$new" :hide="['deactivate', 'activate']"
    :action="route('v2.hrm.interview.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hidden="$new ? [] : ['id' => $s->id_schedule]"
    :tabs="['general' => t('Schedule')]" :log="$new ? [] : $svc->history($s)">
    @if ($open)
        <x-slot:special>
            <x-dl::button variant="success" icon="fa-check" label="Mark Done" type="submit" name="action" value="done" formnovalidate />
            <x-dl::button variant="secondary" icon="fa-ban" label="Cancel schedule" type="submit" name="action" value="cancel" formnovalidate data-dl-confirm="{{ t('Cancel this interview schedule?') }}" />
        </x-slot:special>
    @endif
    @unless ($new)
        <x-slot:strip>
            <x-dl::strip-kv kind="doc" label="Candidate" :value="raw($cand?->name ?? '—')" />
            <x-dl::strip-kv kind="grand" label="Stage" :value="raw($s->stage_code)" />
            <x-dl::strip-kv kind="grand" label="When" :value="Locale::date($s->scheduled_at).' '.$s->scheduled_at->format('H:i')" />
            <x-dl::strip-kv kind="grand" label="Status" :value="t(ucfirst(strtolower($s->status)))" />
        </x-slot:strip>
    @endunless

    <x-slot:general>
        @if ($new)
            <x-dl::card title="Schedule">
                <x-dl::hint note>Pick an application that is in an Interview stage and has no scheduled interview yet. The stage is taken from the application. The interviewer gets a notification.</x-dl::hint>
                <x-dl::form.row :cols="2">
                    <x-dl::field.lookup name="id_application" lookup="rcr_interview_awaiting" label="Application waiting to be scheduled" required placeholder="Search candidate..." :value="old('id_application')" :source="$src('id_application')" />
                    <x-dl::field.select name="interviewer_id" label="Interviewer" required :options="Iv::interviewers()" :value="old('interviewer_id')" placeholder="— select interviewer —" :source="$src('interviewer_id')" />
                </x-dl::form.row>
                <x-dl::form.row :cols="3">
                    <x-dl::field.date name="date" label="Date" required :value="old('date', now()->toDateString())" :source="$src('scheduled_at')" />
                    <x-dl::field.time name="time" label="Time" required placeholder="HH:MM" :value="old('time', '09:00')" :source="$src('scheduled_at')" />
                    <x-dl::field.text name="location" label="Location" placeholder="e.g. HR Meeting Room" :value="old('location')" :source="$src('location')" />
                </x-dl::form.row>
            </x-dl::card>
        @else
            <x-dl::card title="Schedule (read-only)">
                <x-dl::form.row :cols="4">
                    <x-dl::field.display label="Candidate" :value="raw($cand?->name ?? '—')" :source="Src::of('tm2hrr.cnd_candidate.name')" />
                    <x-dl::field.display label="Vacancy" :value="raw($s->application?->vacancy?->title ?? '—')" :source="Src::of('tm2hrr.vac_vacancy.title')" />
                    <x-dl::field.display label="Stage" :value="raw($s->stage_code)" :source="$src('stage_code')" />
                    <x-dl::field.display label="Status" :value="t(ucfirst(strtolower($s->status)))" :source="$src('status')" />
                </x-dl::form.row>
                <x-dl::form.row :cols="4">
                    <x-dl::field.display label="Date" :value="Locale::date($s->scheduled_at)" :source="$src('scheduled_at')" />
                    <x-dl::field.display label="Time" :value="$s->scheduled_at->format('H:i')" :source="$src('scheduled_at')" />
                    <x-dl::field.display label="Interviewer" :value="raw($who)" :source="$src('interviewer_id')" />
                    <x-dl::field.display label="Location" :value="raw($s->location ?: '—')" :source="$src('location')" />
                </x-dl::form.row>
            </x-dl::card>
        @endif
    </x-slot:general>
</x-dl::form>
