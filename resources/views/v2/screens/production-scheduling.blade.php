{{-- Production Scheduling (PPIC, MPS) v2. Engines prepare a Draft plan (App\Services\Scheduling\Plan\AutoPlanRunner); the officer reviews the exceptions,
     gives priority where needed and presses Confirm. Four views of the same operations: Gantt, Kanban, Calendar, Grid. Data = App\Support\V2\Data\Ppic. --}}
@php
    use App\Support\V2\Data\Ppic;
    use App\Support\V2\Src;

    $tab = in_array(request('tab'), ['schedule', 'material', 'incoming', 'monitoring'], true) ? request('tab') : 'schedule';
    $view = in_array(request('view'), ['gantt', 'kanban', 'calendar', 'grid'], true) ? request('view') : 'gantt';
    $filter = ['process' => request('process'), 'machine' => request('machine'), 'q' => request('q')];
    $all = Ppic::operations();
    $ops = Ppic::filtered($filter);
    $stats = Ppic::stats($all);
    $opts = Ppic::filterOptions();
    $run = $stats['run'];
    $home = route('v2.screen', 'production-scheduling');
    $keep = array_filter($filter) + ['tab' => $tab, 'view' => $view];
    $link = fn (array $over) => $home.'?'.http_build_query(array_filter(array_merge($keep, $over), fn ($v) => $v !== null && $v !== ''));
    $src = Src::of('tm2prd.prd_wo_operation', 'tm2prd.prd_wo_operation_machine', 'tm2prd.prd_plan_run', 'tm2mst.prod_process_machine', 'tm2mst.prod_machine');
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::card title="Production Scheduling" description="The engines plan automatically and leave a Draft. Review what needs attention, give priority if needed, then Confirm the plan.">
    <div class="dl-grid__stats">
        <x-dl::stat-box label="Operations" :value="(string) $stats['ops']" tone="accent" :sub="$stats['planned'].' planned'" />
        <x-dl::stat-box label="Ready, not scheduled" :value="(string) $stats['ready']" tone="success" />
        <x-dl::stat-box label="Not ready" :value="(string) $stats['not_ready']" tone="warn" sub="material or tooling" />
        <x-dl::stat-box label="Needs attention" :value="(string) $stats['exceptions']" tone="danger" sub="from the last plan" />
        <x-dl::stat-box label="Last plan" :value="$run ? '#'.$run->run_id : '—'" tone="info" :sub="$run ? raw($run->status.' · '.\Devlib\Locale::dt($run->created_at)) : 'No plan yet'" />
    </div>
    <x-dl::action-bar>
        <x-dl::action-form action="{{ route('v2.ppic.run') }}"><x-dl::button size="sm" variant="secondary" icon="fa-wand-magic-sparkles" label="Re-plan" type="submit" /></x-dl::action-form>
        @if ($stats['draft'])
            <x-dl::action-form action="{{ route('v2.ppic.confirm') }}"><input type="hidden" name="run" value="{{ $run->run_id }}"><x-dl::button size="sm" preset="confirm" label="Confirm plan" type="submit" data-dl-confirm="{{ t('Confirm this plan? The next shift of every machine is locked.') }}" /></x-dl::action-form>
            <x-dl::action-form action="{{ route('v2.ppic.discard') }}"><input type="hidden" name="run" value="{{ $run->run_id }}"><x-dl::button size="sm" variant="secondary" icon="fa-rotate-left" label="Undo" type="submit" data-dl-confirm="{{ t('Discard this Draft?') }}" /></x-dl::action-form>
        @endif
    </x-dl::action-bar>
    <x-dl::action-bar>
        @foreach (['schedule' => 'Schedule', 'material' => 'Material Control', 'incoming' => 'Incoming Purchases', 'monitoring' => 'Monitoring'] as $k => $label)
            <x-dl::button size="sm" :variant="$tab === $k ? 'primary' : 'secondary'" :label="$label" :href="$link(['tab' => $k])" />
        @endforeach
    </x-dl::action-bar>
</x-dl::card>

@if ($tab === 'schedule')
    <x-dl::card title="Needs attention" description="Only these, or a priority you want to give, need a person. Everything else is already planned.">
        @php($ex = Ppic::exceptions())
        @if ($ex)
            <x-dl::table :columns="['kind' => 'Type', 'wo' => 'Work Order', 'text' => 'Detail', 'options' => 'What you can do']" :rows="array_map(fn ($e) => ['kind' => t($e['kind']), 'wo' => raw($e['wo']), 'text' => raw($e['text']), 'options' => t($e['options'])], $ex)" />
        @else
            <x-dl::alert tone="info" message="Nothing needs attention." />
        @endif
        <x-dl::action-form action="{{ route('v2.ppic.prioritize') }}">
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="wo_id" label="Work Order" required :options="collect($all)->unique('wo_id')->mapWithKeys(fn ($r) => [$r['wo_id'] => $r['wo_no'].' · '.($r['customer'] ?? '')])->all()" placeholder="Choose Work Order..." :source="$src" />
                <x-dl::field.select name="kind" label="Priority" required :options="['HOT' => t('Hot job'), 'MOVE_UP' => t('Move up')]" :value="'HOT'" :source="Src::of('tm2prd.prd_wo.priority')" />
                <x-dl::field.text name="reason" label="Reason" required hint="Required. It is written to the decision log." :source="Src::of('tm2prd.prd_plan_decision.reason')" />
                <x-dl::button icon="fa-fire" label="Give priority" type="submit" />
            </x-dl::form.row>
        </x-dl::action-form>
    </x-dl::card>

    <x-dl::card title="Schedule" description="Same operations in four views. Filter by process and machine; a process can run on several machines.">
        <x-dl::action-form method="get" action="{{ $home }}">
            <input type="hidden" name="tab" value="schedule"><input type="hidden" name="view" value="{{ $view }}">
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="process" label="Process" :options="['' => t('All processes')] + $opts['processes']" :value="request('process')" :placeholder="null" :source="$src" />
                <x-dl::field.select name="machine" label="Machine" :options="['' => t('All machines')] + $opts['machines']" :value="request('machine')" :placeholder="null" :source="$src" />
                <x-dl::field.text name="q" label="Search" :value="request('q')" :case-toggle="false" hint="WO number, customer or product." :source="$src" />
                <x-dl::button variant="secondary" icon="fa-filter" label="Filter" type="submit" />
            </x-dl::form.row>
        </x-dl::action-form>
        <x-dl::action-bar>
            @foreach (['gantt' => 'Gantt', 'kanban' => 'Kanban', 'calendar' => 'Calendar', 'grid' => 'Grid'] as $k => $label)
                <x-dl::button size="sm" :variant="$view === $k ? 'primary' : 'secondary'" :label="$label" :href="$link(['view' => $k])" />
            @endforeach
        </x-dl::action-bar>
    </x-dl::card>

    @if (! $ops)
        <x-dl::alert tone="info" message="No operations match. Create Work Orders from a confirmed Customer Order, then press Re-plan." />
    @elseif ($view === 'gantt')
        @php($g = Ppic::gantt($ops))
        <x-dl::gantt :rows="$g['rows']" :tasks="$g['tasks']" :categories="$g['categories']" :origin-date="$g['originDate']" :start="$g['start']" :end="$g['end']" :px-per-hour="$g['pxPerHour']"
            :snap="$g['snap']" :now-at="$g['nowAt']" :lock-until="$g['lockUntil']" :unscheduled="$g['unscheduled']" :submit-label="null" :draft-label="$g['draftLabel']"
            impact-url="{{ route('v2.ppic.impact') }}" move-url="{{ route('v2.ppic.move') }}" resource-label="Machine" :source="$src" />
    @elseif ($view === 'kanban')
        @php($k = Ppic::kanban($ops))
        <x-dl::kanban :columns="$k['columns']" :cards="$k['cards']" :add-column="false" :add-card="false" move-url="{{ route('v2.ppic.kanban-move') }}" :source="$src" />
    @elseif ($view === 'calendar')
        <x-dl::calendar :events="Ppic::calendarEvents($ops)" display="week" :week-start="1" :sidebar="false" :source="$src" />
    @else
        <x-dl::sheet title="Operations" row-key="id" :rows="Ppic::sheetRows($ops)" save-url="{{ route('v2.ppic.sheet-save') }}" :source="$src"
            :columns="[
                ['key' => 'wo', 'label' => 'Work Order', 'readonly' => true, 'width' => '110px'],
                ['key' => 'customer', 'label' => 'Customer', 'readonly' => true, 'width' => '150px'],
                ['key' => 'process', 'label' => 'Process', 'readonly' => true, 'width' => '110px'],
                ['key' => 'machine', 'label' => 'Machine', 'readonly' => true, 'width' => '150px'],
                ['key' => 'qty', 'label' => 'Qty in', 'type' => 'number', 'readonly' => true, 'width' => '90px'],
                ['key' => 'uom', 'label' => 'Unit', 'readonly' => true, 'width' => '56px'],
                ['key' => 'target', 'label' => 'Target / hour', 'type' => 'number', 'width' => '100px'],
                ['key' => 'waste', 'label' => 'Waste qty', 'type' => 'number', 'width' => '90px'],
                ['key' => 'spoilage', 'label' => 'Spoilage qty', 'type' => 'number', 'width' => '100px'],
                ['key' => 'setup', 'label' => 'Setup (min)', 'type' => 'number', 'readonly' => true, 'width' => '90px'],
                ['key' => 'run', 'label' => 'Run (h)', 'type' => 'number', 'decimals' => 2, 'readonly' => true, 'width' => '80px'],
                ['key' => 'start', 'label' => 'Start', 'readonly' => true, 'width' => '130px'],
                ['key' => 'finish', 'label' => 'Finish', 'readonly' => true, 'width' => '130px'],
                ['key' => 'due', 'label' => 'Due', 'readonly' => true, 'width' => '100px'],
                ['key' => 'slack', 'label' => 'Slack (h)', 'type' => 'number', 'decimals' => 1, 'readonly' => true, 'width' => '80px'],
                ['key' => 'gates', 'label' => 'Gates', 'readonly' => true, 'width' => '100px'],
                ['key' => 'status', 'label' => 'Status', 'readonly' => true, 'width' => '100px'],
            ]" />
        <x-dl::alert tone="info" message="Target per hour, waste qty and spoilage qty are estimated by the engines. Type a value only to override it; press Re-plan afterwards." />
    @endif
@elseif ($tab === 'material')
    @include('v2.screens._ppic-material', ['ops' => $all, 'src' => $src])
@elseif ($tab === 'incoming')
    @include('v2.screens._ppic-incoming', ['src' => $src])
@else
    @include('v2.screens._ppic-monitoring', ['src' => $src])
@endif
