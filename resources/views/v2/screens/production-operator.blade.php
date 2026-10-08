{{-- Operator Queue v2 (PPIC): the tablet at the machine. Shows only the CONFIRMED queue of one machine; the operator cannot reorder it.
     Play / Pause / Stop write prd_operation_time_log (App\Services\Scheduling\Operator\OperatorTimerService); quantities go to the same output
     tables as the Work Order screen. Problem opens a Maintenance Request filled with machine and Work Order. Data = App\Support\V2\Data\Ppic. --}}
@php
    use App\Models\Prd\PrdScrapType;
    use App\Services\Scheduling\Operator\OperatorTimerService as Timer;
    use App\Support\V2\Data\Ppic;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $svc = app(Timer::class);
    $src = Src::of('tm2prd.prd_operation_time_log', 'tm2prd.prd_wo_operation', 'tm2prd.prd_wo_operation_output');
    $confirmed = collect(Ppic::operations())->filter(fn ($o) => $o['plan_status'] === 'CONFIRMED' && $o['machine_id'] && ! in_array($o['bucket'], ['done'], true));
    $machineId = request()->integer('machine');
    $machines = $confirmed->groupBy('machine_id');
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $machineId)
    <x-dl::card title="Operator Queue" description="Choose your machine. Only the confirmed, locked queue of that machine is shown.">
        @forelse ($machines as $id => $list)
            <x-dl::button variant="secondary" icon="fa-industry" :label="raw($list->first()['machine'].' · '.$list->count().' '.t('jobs'))" :href="route('v2.screen', 'production-operator').'?machine='.$id" />
        @empty
            <x-dl::alert tone="info" message="No confirmed queue yet. Confirm the plan on Production Scheduling first." />
        @endforelse
    </x-dl::card>
@else
    @php
        $queue = $confirmed->where('machine_id', $machineId)->sortBy(fn ($o) => $o['start']?->timestamp ?? PHP_INT_MAX)->values();
        $open = $svc->open($machineId);
        $current = $open ? $queue->firstWhere('op_id', (int) $open->operation_id) : null;
        $next = $current ? $queue->first(fn ($o) => $o['op_id'] !== $current['op_id']) : $queue->first();
        $label = $queue->first()['machine'] ?? ('#'.$machineId);
        $lockedUntil = $queue->where('locked', true)->max('finish');
        $reasons = array_map('t', Timer::PAUSE_REASONS);
    @endphp

    <x-dl::card :title="raw($label)" :description="raw(tf('Confirmed queue · :n jobs', ['n' => $queue->count()]).($lockedUntil ? ' · '.t('Locked until').' '.Locale::dt($lockedUntil) : ''))">
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="back" label="Back" :href="route('v2.screen', 'production-operator')" />
        </x-dl::action-bar>
    </x-dl::card>

    @if ($current)
        @php
            $sum = $svc->summary($current['op_id']);
            $paused = $open->kind === 'PAUSE';
            $kindLabel = ['SETUP' => t('Setup'), 'RUN' => t('Run'), 'PAUSE' => t('Paused')][$open->kind];
            $runH = max($sum['run'], 1) / 60;
            $speed = $sum['run'] > 0 ? round($sum['good'] / $runH) : null;
            $scrap = PrdScrapType::query()->where('activate', 1)->where('category', 'REJECT')->orderBy('sort_order')->pluck('type_name', 'type_id')->all();
        @endphp
        <x-dl::card :title="raw($current['wo_no'].' · '.($current['product'] ?? '—'))" :description="raw(($current['customer'] ?? '—').' · '.$current['process'].' · '.Locale::number($current['qty'], 0).' '.$current['uom'].' '.t('planned').($current['target'] ? ' · '.t('target').' '.Locale::number($current['target'], 0).'/h' : ''))">
            <x-dl::badge :label="$kindLabel" :tone="$paused ? 'warn' : ($open->kind === 'SETUP' ? 'info' : 'success')" />
            <x-dl::timer :seconds="$svc->elapsed($open)" :running="! $paused" :tone="$paused ? 'warn' : 'accent'" :label="raw($paused ? t('paused').' · '.t(Timer::PAUSE_REASONS[$open->pause_reason] ?? 'Other') : ($open->kind === 'SETUP' ? t('setup time') : t('run time')))" />
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Setup so far (min)" :value="$sum['setup'].' / '.$current['setup'].' '.t('plan')" :source="$src" />
                <x-dl::field.display label="Good so far" :value="raw(Locale::number($sum['good'], 0).' / '.Locale::number($current['qty_out'], 0))" :source="$src" />
                <x-dl::field.display label="Actual speed" :value="$speed ? raw(Locale::number($speed, 0).' /h') : '—'" :source="$src" />
                <x-dl::field.display label="Paused (min)" :value="$sum['pause']" :source="$src" />
            </x-dl::form.row>
            <x-dl::action-bar>
                @if ($paused || $open->kind === 'SETUP')
                    <x-dl::action-form action="{{ route('v2.ppic.op.play', $current['op_id']) }}"><x-dl::button icon="fa-play" :label="$paused ? 'Resume' : 'Start run'" type="submit" /></x-dl::action-form>
                @endif
                <x-dl::button variant="secondary" icon="fa-wrench" label="Problem" :href="route('v2.ppic.op.problem', $current['op_id'])" />
            </x-dl::action-bar>
        </x-dl::card>

        @unless ($paused)
            <x-dl::card title="Pause: why?" description="The timer stops and the reason is saved. Machine problem opens a Maintenance Request filled with this machine and Work Order.">
                <x-dl::action-form action="{{ route('v2.ppic.op.pause', $current['op_id']) }}">
                    <x-dl::form.row :cols="3">
                        <x-dl::field.select name="reason" label="Reason" required :options="$reasons" :source="Src::of('tm2prd.prd_operation_time_log.pause_reason')" />
                        <x-dl::button variant="secondary" icon="fa-pause" label="Pause" type="submit" />
                    </x-dl::form.row>
                </x-dl::action-form>
            </x-dl::card>

            <x-dl::card title="Add quantity" description="Good quantity made so far. Press as often as you like during the run.">
                <x-dl::action-form action="{{ route('v2.ppic.op.quantity', $current['op_id']) }}">
                    <x-dl::form.row :cols="3">
                        <x-dl::field.number name="qty" label="Good quantity" required :source="Src::of('tm2prd.prd_wo_operation_output.qty_good')" />
                        <x-dl::button icon="fa-plus" label="Add" type="submit" />
                    </x-dl::form.row>
                </x-dl::action-form>
            </x-dl::card>
        @endunless

        <x-dl::card title="Stop" description="Closes the timer. Enter what was made in this run; the Work Order output is saved and the operation is finished.">
            <x-dl::action-form action="{{ route('v2.ppic.op.stop', $current['op_id']) }}">
                <x-dl::form.row :cols="3">
                    <x-dl::field.number name="good" label="Good quantity (this stop)" required :source="Src::of('tm2prd.prd_wo_operation_output.qty_good')" />
                    <x-dl::field.number name="waste" label="Waste quantity" :source="Src::of('tm2prd.prd_wo_operation_output_defect.qty')" />
                    <x-dl::field.number name="reject" label="Reject quantity" :source="Src::of('tm2prd.prd_wo_operation_output_defect.qty')" />
                </x-dl::form.row>
                <x-dl::form.row :cols="3">
                    <x-dl::field.select name="scrap_type_id" label="Reject reason (scrap type)" :options="$scrap" placeholder="Choose reason..." :source="Src::of('tm2prd.prd_scrap_type')" />
                    <x-dl::field.select name="change_kind" label="What changed at setup?" :options="array_map('t', Timer::CHANGE_KINDS)" placeholder="Nothing / same as before" hint="Teaches the planner the real changeover time." :source="Src::of('tm2prd.prd_operation_time_log.change_kind')" />
                    <x-dl::button variant="danger" icon="fa-stop" label="Save and stop" type="submit" />
                </x-dl::form.row>
            </x-dl::action-form>
        </x-dl::card>
    @elseif ($next)
        <x-dl::card :title="raw(t('Next job').' · '.$next['wo_no'].' · '.($next['product'] ?? '—'))" :description="raw(($next['customer'] ?? '—').' · '.$next['process'].' · '.Locale::number($next['qty'], 0).' '.$next['uom'].' · '.t('expected setup').' '.$next['setup'].' min')">
            <x-dl::field.display label="Planned start" :value="$next['start'] ? raw(Locale::dt($next['start'])) : '—'" :source="$src" />
            @if (in_array('BLOCK', $next['gates'], true) || in_array('WAIT', $next['gates'], true))
                <x-dl::alert tone="warn" :message="raw(t('Not ready').': '.($next['gate_note'] ?: t('material or tooling not ready')))" />
            @endif
            <x-dl::action-form action="{{ route('v2.ppic.op.play', $next['op_id']) }}">
                <x-dl::button icon="fa-play" label="Play: start setup" type="submit" />
            </x-dl::action-form>
        </x-dl::card>
    @else
        <x-dl::alert tone="info" message="The queue of this machine is empty." />
    @endif

    <x-dl::card title="My queue (confirmed, locked)" description="Order is set by Production Scheduling. A Hot job added by the officer appears here with a reason.">
        <x-dl::table :source="$src" empty="The queue of this machine is empty."
            :columns="['seq' => '#', 'wo' => 'Work Order', 'product' => 'Product', 'process' => 'Process', 'qty' => 'Quantity', 'start' => 'Planned start', 'setup' => 'Setup (min)', 'state' => 'State']"
            :rows="$queue->values()->map(fn ($o, $i) => ['seq' => $i + 1, 'wo' => raw($o['wo_no'].($o['hot'] ? ' 🔥' : '').($o['locked'] ? ' 🔒' : '')), 'product' => raw($o['product'] ?? '—'), 'process' => raw($o['process']),
                'qty' => raw(Locale::number($o['qty'], 0).' '.$o['uom']), 'start' => $o['start'] ? raw(Locale::dt($o['start'])) : '—', 'setup' => $o['setup'],
                'state' => raw($current && $current['op_id'] === $o['op_id'] ? t('Running') : (in_array('BLOCK', $o['gates'], true) ? t('Blocked') : (in_array('WAIT', $o['gates'], true) ? t('Waiting') : t('Ready'))))])->all()" />
    </x-dl::card>
@endif
