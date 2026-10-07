{{-- HRM > Time Attendance > Shift Roster (v2). Data & aturan = App\Support\V2\Data\ShiftRoster (emp_shift_roster, emp_shift_roster_batch), papan = objek
     x-mp::roster-board. Lingkup: Jadwal -> Departemen -> Unit kerja; minggu lewat panah papan (?w, ?step). 1 penempatan = 1 pegawai 1 shift 1 minggu penuh.
     Save (di papan) = Draft; Publish & Lock mengunci minggu itu; Unlock membukanya lagi. Katalog shift = Master Shift. Cetak masih layar v1. --}}
@php
    use App\Support\V2\Data\ShiftRoster;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'hrm-roster');
    $week = request()->integer('w') + request()->integer('step');
    $r = ShiftRoster::for(request()->integer('sched') ?: null, request()->integer('dept') ?: null, request()->integer('wc') ?: null, $week);
    $scope = ['sched' => $r->scheduleId, 'dept' => $r->departmentId, 'wc' => $r->workCenterId, 'w' => $week];
    $query = http_build_query(array_filter($scope, fn ($v) => $v !== null));
    $login = auth()->user()->login;
    $board = $r->board($login);
    $batch = $r->batch();
    $locked = $r->isLocked();
    $center = $r->workCenters()->firstWhere('work_center_id', $r->workCenterId);
    $hidden = ShiftRoster::hiddenShifts($login);
    $src = Src::of('tm2mst.emp_shift_roster', 'tm2mst.emp_shift_roster_batch', 'tm2mst.emp_shift', 'tm2mst.emp_work_center', 'tm2mst.emp_employee');
    $fields = fn () => collect($scope)->filter(fn ($v) => $v !== null)->all();
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $r->scheduleId)
    <x-dl::alert tone="warn" message="No active roster schedule yet." />
@else
    <x-dl::card title="Roster Scope" description="Choose the schedule, the department (empty = all departments) and the work unit. A person can hold only one shift per week in a schedule.">
        <x-dl::action-form method="get" action="{{ $home }}">
            <input type="hidden" name="w" value="{{ $week }}">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="sched" label="Schedule" :options="$r->schedules()->pluck('schedule_name', 'schedule_id')->all()" :value="$r->scheduleId" :placeholder="null" :source="$src" />
                <x-dl::field.select name="dept" label="Department" :options="$r->departments()->pluck('department_name', 'department_id')->all()" :value="$r->departmentId" placeholder="All departments" :source="$src" />
                <x-dl::field.select name="wc" label="Work Unit" :options="$r->workCenters()->pluck('work_center_name', 'work_center_id')->all()" :value="$r->workCenterId" :placeholder="null" :source="$src" />
            </x-dl::form.row>
            <x-dl::action-bar><x-dl::button variant="secondary" icon="fa-filter" label="Filter" /></x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::card>

    <x-dl::action-bar>
        <x-dl::badge :label="$locked ? 'Confirmed' : ($batch ? 'Draft' : 'Not saved yet')" :tone="$locked ? 'success' : ($batch ? 'warn' : 'neutral')" />
        @if ($locked)
            <x-dl::action-form action="{{ route('v2.roster.unlock') }}">
                @foreach ($fields() as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                <x-dl::button variant="secondary" icon="fa-lock-open" label="Unlock" data-dl-confirm="{{ t('Open the lock so this week can be changed again?') }}" />
            </x-dl::action-form>
        @else
            <x-dl::action-form action="{{ route('v2.roster.confirm') }}">
                @foreach ($fields() as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                <x-dl::button icon="fa-lock" label="Publish & Lock" data-dl-confirm="{{ t('Publish this week and lock the roster? Save the board first.') }}" />
            </x-dl::action-form>
        @endif
        <x-dl::button variant="secondary" icon="fa-print" label="Print" :href="route('hrm.shift-roster.print')" />
        <x-dl::button variant="secondary" icon="fa-clock" label="Master Shift" :href="route('v2.screen', 'md-shift')" />
    </x-dl::action-bar>

    @if ($locked)
        <x-dl::alert tone="info" :message="raw(tf('Week :week is published and locked by :by. Unlock it to change the roster.', ['week' => $r->weekNumber(), 'by' => $batch->confirmed_by ?? '—']))" />
    @endif

    @if (! $r->workCenterId)
        <x-dl::alert tone="warn" message="Choose a work unit first." />
    @else
        <x-mp::roster-board :title="raw($center?->work_center_name ?? '')" :week="raw(tf('Week :week · :from – :to', ['week' => $r->weekNumber(), 'from' => Locale::d($r->weekStart()), 'to' => Locale::d($r->weekEnd())]))"
            :shifts="$board['shifts']" :days="$board['days']" :people="$board['people']" :assign="$board['assign']" :locked="$locked"
            save-url="{{ route('v2.roster.save').'?'.$query }}" week-url="{{ $home.'?'.http_build_query(array_filter(['sched' => $r->scheduleId, 'dept' => $r->departmentId, 'wc' => $r->workCenterId, 'w' => $week], fn ($v) => $v !== null)).'&step={step}' }}"
            :source="$src" />
    @endif

    <x-dl::card title="Shifts Shown on the Board" description="Hiding a shift only changes your view. Placements in a hidden shift stay in the database and keep those employees out of the pool.">
        <x-dl::action-bar>
            @foreach ($r->shifts() as $s)
                <x-dl::action-form action="{{ route('v2.roster.shift.toggle', $s->shift_id) }}">
                    @foreach ($fields() as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                    <x-dl::button size="sm" :variant="in_array($s->shift_id, $hidden, true) ? 'ghost' : 'secondary'" :icon="in_array($s->shift_id, $hidden, true) ? 'fa-eye-slash' : 'fa-eye'" :label="raw($s->shift_name)" />
                </x-dl::action-form>
            @endforeach
        </x-dl::action-bar>
    </x-dl::card>
@endif
