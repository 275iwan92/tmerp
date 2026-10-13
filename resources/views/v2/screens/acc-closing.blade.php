{{--
 REMARK
 Tujuan       : Tutup buku periode: checklist, kontrol otomatis, Period Manager, kunci periode permanen dan voucher penutup tahun DRAFT.
 Sumber hulu  : App\Support\V2\Data\ClosingDesk (CloseService, FinPeriodGuard); tabel tm2acc.fin_period, fin_close_task, fin_voucher.
 Input        : Query string year, period, to; flash v2_flash dan errors.
 Output       : x-dl::card, tabel / daftar tugas dan form DevLib; POST aksi tutup periode di routes/v2.php (kembali ke acc-closing).
 Dipakai oleh : Menu Finance > Controls & Tax > Period Closing (slug acc-closing, route v2.screen).
 Status       : Aktif.
--}}
@php
    use App\Support\V2\Data\ClosingDesk as Closing;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $year = request()->integer('year') ?: Closing::defaultYear();
    $period = Closing::period($year, request()->integer('period') ?: null);
    $periods = Closing::periods($year);
    $tasks = Closing::tasks($period);
    $controls = Closing::controls($period);
    $completion = Closing::completion($tasks, $controls);
    $blockers = Closing::blockers($period);
    $lastClosed = Closing::close()->lastClosed();
    $home = route('v2.screen', 'acc-closing');
    $src = Src::of('tm2acc.fin_period', 'tm2acc.fin_close_task', 'tm2acc.fin_voucher');
    $scope = ['year' => $year, 'period' => $period?->period_id];
    $url = fn (array $extra = []) => $home.'?'.http_build_query(array_filter($scope + $extra, fn ($v) => $v !== null));
    $to = in_array(request('to'), array_keys(Closing::STATUSES), true) ? request('to') : null;
    $hidden = fn () => collect($scope)->filter(fn ($v) => $v !== null)->all();
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::card title="Period Closing" description="Month-end controls, accounting cut-off, approval, and period-lock workflow.">
    <x-dl::action-form method="get" action="{{ $home }}">
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="year" label="Fiscal Year" :options="Closing::years()" :value="$year" :placeholder="null" :source="$src" />
        </x-dl::form.row>
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="fa-filter" label="Filter" />
            @if ($period)<x-dl::button variant="secondary" icon="fa-download" label="Checklist" :href="route('v2.acc.closing.checklist-csv', $scope)" />@endif
        </x-dl::action-bar>
    </x-dl::action-form>
    @if ($period && $tasks->isEmpty())
        <x-dl::action-form action="{{ route('v2.acc.closing.start') }}">
            @foreach ($hidden() as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <x-dl::button icon="fa-play" label="Start Closing" />
        </x-dl::action-form>
    @elseif ($period && $period->status !== 'CLOSED')
        <x-dl::button icon="fa-lock" label="Approve & Lock" :href="$url(['to' => 'CLOSED'])" />
    @endif
</x-dl::card>

<x-dl::kpi-row>
    @foreach (Closing::kpis($period, $completion, $lastClosed) as $k)
        <x-dl::kpi :label="$k['label']" :value="$k['value']" :format="$k['format']" :previous="$k['previous']" :good-when="$k['goodWhen']" :icon="$k['icon']" :tone="$k['tone']" :note="$k['note']" />
    @endforeach
</x-dl::kpi-row>

<x-dl::flow title="Month-End Close Flow" :steps="Closing::flow()" :source="$src" />

@if ($period && $to)
    <x-dl::card :title="$to === 'CLOSED' ? 'Lock Period Permanently' : 'Change Period Status'">
        @if ($to === 'CLOSED')
            <x-dl::alert tone="warn" :message="raw(tf('CLOSED is irreversible: no button, permission or endpoint can open it again. Posting to :period will be refused forever.', ['period' => $period->period_name]))" />
        @endif
        <x-dl::action-form action="{{ route('v2.acc.closing.status') }}">
            @foreach ($hidden() as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <input type="hidden" name="status" value="{{ $to }}">
            <x-dl::field.display label="Period" :value="raw($period->period_name.' — '.$period->status.' → '.$to)" :source="$src" />
            <x-dl::field.text name="reason" label="Reason (required, recorded in the audit trail)" required placeholder="e.g. August 2026 close approved by the controller" :source="$src" />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$url()" />
                <x-dl::button :variant="$to === 'CLOSED' ? 'danger' : 'primary'" :icon="$to === 'CLOSED' ? 'fa-lock' : 'fa-check'" :label="$to === 'CLOSED' ? 'Lock Permanently' : 'Change Status'" />
            </x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::card>
@endif

<x-dl::card :title="raw(($period?->period_name ?? '—').' '.t('Closing Checklist'))" description="Each control has an owner, evidence, and completion status.">
    @forelse ($tasks as $t)
        <x-dl::status-card :title="raw($t->step_no.'. '.$t->title)" :desc="raw((string) $t->description)" :status="$t->status === 'COMPLETE' ? t('Complete') : ($t->status === 'IN_PROGRESS' ? t('In progress') : t('Pending'))"
            :tone="$t->status === 'COMPLETE' ? 'success' : 'warn'" :icon="$t->status === 'COMPLETE' ? 'circle-check' : 'clock'" />
        <x-dl::action-form action="{{ route('v2.acc.closing.task', [$t->task_id, $t->status === 'COMPLETE' ? 'reopen' : 'complete']) }}">
            @foreach ($hidden() as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <x-dl::button size="sm" :variant="$t->status === 'COMPLETE' ? 'ghost' : 'secondary'" :icon="$t->status === 'COMPLETE' ? 'fa-rotate-left' : 'fa-check'" :label="$t->status === 'COMPLETE' ? 'Reopen' : 'Mark complete'" />
        </x-dl::action-form>
    @empty
        <x-dl::alert tone="info" message="The checklist has not been created yet — click Start Closing." />
    @endforelse
</x-dl::card>

<x-dl::card title="Close Controls" description="Automatic control status for this period.">
    @foreach ($controls as $c)
        <x-dl::status-card :title="raw($c[1])" :desc="raw($c[2])" :status="$c[3]" :tone="Closing::CONTROL_TONES[$c[3]] ?? 'warn'" :icon="$c[3] === 'Passed' ? 'circle-check' : 'triangle-exclamation'" />
    @endforeach
</x-dl::card>

<x-dl::card title="Period Manager" description="13 periods per fiscal year (12 months + adjustment).">
    <x-dl::table :columns="['period' => 'Period', 'range' => 'Range', 'status' => 'Status']"
        :rows="$periods->map(fn ($p) => ['period' => raw($p->period_name.' · '.($p->is_adjustment ? t('Adjustment (period 13)') : tf('Period :n', ['n' => $p->period_no]))), 'range' => raw(Closing::rangeText($p)),
            'status' => t(Closing::STATUSES[$p->status] ?? $p->status)])->all()" :source="$src" />
    @foreach ($periods->where('status', '<>', 'CLOSED') as $p)
        <x-dl::action-bar>
            <x-dl::badge :label="raw($p->period_name)" :tone="Closing::STATUS_TONES[$p->status] ?? 'neutral'" />
            <x-dl::button size="sm" variant="secondary" icon="fa-hand-pointer" label="Select for closing" :href="$home.'?year='.$year.'&period='.$p->period_id" />
            <x-dl::button size="sm" variant="secondary" :icon="$p->status === 'OPEN' ? 'fa-pause' : 'fa-lock-open'" :label="$p->status === 'OPEN' ? 'Set On hold' : 'Reopen (to Open)'"
                :href="$home.'?year='.$year.'&period='.$p->period_id.'&to='.($p->status === 'OPEN' ? 'ON_HOLD' : 'OPEN')" />
            <x-dl::button size="sm" variant="secondary" icon="fa-lock" label="Lock permanently" :href="$home.'?year='.$year.'&period='.$p->period_id.'&to=CLOSED'" />
        </x-dl::action-bar>
    @endforeach
    @if ($period?->period_no === 12)
        <x-dl::action-form action="{{ route('v2.acc.closing.year-end') }}">
            @foreach ($hidden() as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <x-dl::button variant="secondary" icon="fa-file-circle-plus" label="Create Year-End Closing Voucher" data-dl-confirm="{{ t('Create the year-end closing voucher as a DRAFT?') }}" />
        </x-dl::action-form>
    @endif
</x-dl::card>

@if ($blockers)
    <x-dl::card title="Lock Blockers" description="Everything must be cleared before the period may be locked permanently.">
        @foreach ($blockers as $b)<x-dl::alert tone="danger" :message="raw($b)" />@endforeach
    </x-dl::card>
@endif
