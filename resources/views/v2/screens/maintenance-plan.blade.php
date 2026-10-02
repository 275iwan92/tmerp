{{-- Preventive Plan v2 (docs/maintenance_tier1.md §3-§4, mockup maintenance_v2.jpg bagian 3). Template WO per mesin:
     pemicu waktu dan/atau meter (mana yang lebih dulu), Floating (dari selesai terakhir) / Fixed (tanggal pasti, inspeksi K3),
     checklist + spare part standar. Generator harian 06:00 (maintenance:generate-pm) membuat MWO Draft saat jatuh tempo.
     Isi plan nyata di tm2* diisi kepala maintenance (K9) -- contoh di ddt* = praktik umum pabrikan (dummy). --}}
@php
    use App\Models\Prd\MntLog;
    use App\Models\Prd\MntPlan;
    use App\Models\Prd\MntPlanTask;
    use App\Services\Maintenance\MaintenanceService;
    use App\Support\V2\Data\Maintenance;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'maintenance-plan');
    $plan = request('id') ? MntPlan::with(['tasks', 'parts'])->find((int) request('id')) : null;
    abort_if(request('id') && ! $plan, 404);
    $head = MaintenanceService::canDecide(auth()->user());
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $plan && ! request('new'))
    <x-dl::grid id="mnt-plan-list" :rows="Maintenance::planRows()" search="Search plan, machine..." actions="worklist" :active-key="null"
        :new-url="$head ? $home.'?new=1' : null" :open-url="$home.'?id={id}'" source="{{ Src::of('tm2prd.mnt_plan', 'tm2prd.mnt_plan_task', 'tm2mst.prod_machine') }}"
        :columns="[
            ['key' => 'code', 'label' => 'Plan Code / Machine', 'type' => 'doc', 'party' => 'machine'],
            ['key' => 'name', 'label' => 'Plan Name'],
            ['key' => 'type', 'label' => 'WO Type', 'type' => 'badge', 'tones' => Maintenance::TYPE_TONES],
            ['key' => 'trigger', 'label' => 'Trigger'],
            ['key' => 'mode', 'label' => 'Schedule'],
            ['key' => 'last', 'label' => 'Last Done', 'type' => 'date'],
            ['key' => 'next', 'label' => 'Next Due', 'type' => 'date'],
            ['key' => 'due', 'label' => 'Due', 'type' => 'badge', 'tones' => ['Overdue' => 'danger', 'Due' => 'warn', 'OK' => 'success']],
        ]"
        :detail="['key' => 'items', 'title' => t('Tasks'), 'columns' => [
            ['key' => 'seq', 'label' => t('No.'), 'type' => 'number'],
            ['key' => 'task', 'label' => t('Task'), 'strong' => true],
            ['key' => 'kind', 'label' => t('Kind')],
            ['key' => 'uom', 'label' => t('Unit')],
            ['key' => 'min', 'label' => t('Min'), 'type' => 'number'],
            ['key' => 'max', 'label' => t('Max'), 'type' => 'number'],
            ['key' => 'required', 'label' => t('Required')],
        ]]"
        :stats="[['label' => 'All'], ['label' => 'Overdue', 'where' => ['due' => 'Overdue'], 'tone' => 'danger'], ['label' => 'Due soon', 'where' => ['due' => 'Due'], 'tone' => 'warn'], ['label' => 'Inspection', 'where' => ['type' => 'Inspection'], 'tone' => 'success']]"
        :filters="['machine' => 'Machine', 'type' => 'WO Type', 'due' => 'Due']" />
@else
    @php
        $new = ! $plan;
        $p = $plan ?? new MntPlan(['wo_type' => 'PREVENTIVE', 'trigger_mode' => 'TIME', 'interval_value' => 1, 'interval_unit' => 'MONTH',
            'schedule_mode' => 'FLOATING', 'lead_days' => 3, 'needs_machine_stop' => true, 'activate' => true]);
        $due = $plan ? Maintenance::nextDue($plan) : null;
        $cmp = $plan ? Maintenance::compliance($plan) : null;
        $num = fn ($v) => $v === null ? '—' : Locale::number((float) $v, 0);
    @endphp
    <x-dl::form id="mnt-plan-form" :title="'Preventive Plan · '.($p->plan_code ? raw($p->plan_code) : 'New')" :subtitle="$new ? null : raw(Maintenance::machines()[$p->machine_id] ?? null)"
        panel="top" kind="master" :status="$new ? 'new' : ($p->activate ? 'active' : 'inactive')" :can-input="$head" :log="$plan ? MntLog::forDoc('PLAN', $plan->id_plan) : []"
        :action="route('v2.mnt.plan.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hidden="$plan ? ['id' => $plan->id_plan] : []"
        :tabs="['general' => 'General', 'trigger' => 'Trigger', 'notes' => 'Notes']"
        :created-by="$p->created_by" :created-at="$p->created_at?->toDateTimeString()" :updated-by="$p->updated_by" :updated-at="$p->updated_at?->toDateTimeString()">
        @if ($plan && $head && $plan->activate)
            <x-slot:special>
                <x-dl::button size="sm" variant="secondary" icon="fa-bolt" label="Generate WO" type="submit" name="action" value="generate" formnovalidate />
            </x-slot:special>
        @endif
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>@t('Plan')</span><b>{{ $p->plan_code ?: t('(new)') }}</b></div>
            <div class="dl-strip__kv"><span>@t('Last done')</span><b>{{ $p->last_done_date ? Locale::date($p->last_done_date) : '—' }}{{ $p->last_done_meter !== null ? ' · '.$num($p->last_done_meter) : '' }}</b></div>
            <div class="dl-strip__kv"><span>@t('By time')</span><b>{{ $p->next_due_date ? Locale::date($p->next_due_date) : '—' }}</b></div>
            <div class="dl-strip__kv"><span>@t('By meter')</span><b>{{ $due && $due['next_meter'] !== null ? $num($due['next_meter']).' ('.t('now').' '.$num($due['current_meter']).')' : '—' }}</b></div>
            <div class="dl-strip__kv dl-strip__grand"><span>@t('PM compliance (12 m)')</span><b>{{ $cmp && $cmp['pct'] !== null ? $cmp['pct'].'% · '.$cmp['on_time'].' '.t('on time').' / '.$cmp['late'].' '.t('late') : '—' }}</b></div>
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="plan_code" label="Plan Code" required :case-toggle="false" :value="$p->plan_code" placeholder="PM-OFF01-01" :source="Src::of('tm2prd.mnt_plan.plan_code')" />
                <x-dl::field.text name="plan_name" label="Plan Name" required :value="$p->plan_name" :source="Src::of('tm2prd.mnt_plan.plan_name')" />
                <x-dl::field.select name="machine_id" label="Machine" required :options="Maintenance::machines()" :value="$p->machine_id" :source="Src::of('tm2mst.prod_machine')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="wo_type" label="WO Type" required :options="array_map('t', MntPlan::TYPES)" :value="$p->wo_type" :source="Src::of('tm2prd.mnt_plan.wo_type')" />
                <x-dl::field.number name="est_minutes" label="Est. Duration (minutes)" :value="$p->est_minutes" :source="Src::of('tm2prd.mnt_plan.est_minutes')" />
                <x-dl::field.toggle name="needs_machine_stop" label="Machine Stop" :on-label="t('Yes — blocks Scheduling Board')" :off-label="t('No')" :value="$p->needs_machine_stop" :source="Src::of('tm2prd.mnt_plan.needs_machine_stop')" />
                <x-dl::field.date name="certificate_valid_until" label="Certificate valid until (inspection)" :value="$p->certificate_valid_until?->toDateString()" :source="Src::of('tm2prd.mnt_plan.certificate_valid_until')" />
            </x-dl::form.row>
        </x-slot:general>
        <x-slot:trigger>
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="trigger_mode" label="Trigger" required :options="array_map('t', MntPlan::TRIGGERS)" :value="$p->trigger_mode" :source="Src::of('tm2prd.mnt_plan.trigger_mode')" />
                <x-dl::field.number name="interval_value" label="Every" :min="1" :value="$p->interval_value" :source="Src::of('tm2prd.mnt_plan.interval_value')" />
                <x-dl::field.select name="interval_unit" label="Unit" :options="['' => '—'] + array_map('t', MntPlan::UNITS)" :value="$p->interval_unit" :source="Src::of('tm2prd.mnt_plan.interval_unit')" />
                <x-dl::field.select name="schedule_mode" label="Schedule Mode" required :options="array_map('t', MntPlan::MODES)" :value="$p->schedule_mode" :source="Src::of('tm2prd.mnt_plan.schedule_mode')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="meter_type" label="Meter" :options="['' => '—'] + array_map('t', MntPlan::METERS)" :value="$p->meter_type" :source="Src::of('tm2prd.mnt_plan.meter_type')" />
                <x-dl::field.number name="meter_interval" label="Or every (meter)" :value="$p->meter_interval" :source="Src::of('tm2prd.mnt_plan.meter_interval')" />
                <x-dl::field.number name="lead_days" label="Create WO before due (days)" :value="$p->lead_days" :source="Src::of('tm2prd.mnt_plan.lead_days')" />
                <x-dl::field.date name="next_due_date" label="Next due (Fixed: first date)" :value="$p->next_due_date?->toDateString()" :source="Src::of('tm2prd.mnt_plan.next_due_date')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.date name="last_done_date" label="Last done" :value="$p->last_done_date?->toDateString()" :source="Src::of('tm2prd.mnt_plan.last_done_date')" />
                <x-dl::field.number name="last_done_meter" label="Meter at last done" :value="$p->last_done_meter" :source="Src::of('tm2prd.mnt_plan.last_done_meter')" />
            </x-dl::form.row>
        </x-slot:trigger>
        <x-slot:notes>
            <x-dl::field.text name="notes" label="Notes" multiline :value="$p->notes" :source="Src::of('tm2prd.mnt_plan.notes')" />
        </x-slot:notes>
        @if ($plan)
            <x-slot:detail>
                <x-dl::tabs :tabs="['tasks' => 'Tasks ('.raw($plan->tasks->count()).')', 'parts' => 'Spare Parts ('.raw($plan->parts->count()).')']">
                    <x-slot:tasks>
                        <x-dl::lines name="tasks" title="Tasks" key-field="id_plan_task" remove-field="remove" :addable="false" :duplicable="false" :min-rows="0"
                            :rows="Maintenance::planTasks($plan)" :readonly="! $head" source="{{ Src::of('tm2prd.mnt_plan_task') }}"
                            :columns="[
                                ['key' => 'seq_no', 'label' => '#', 'type' => 'display', 'width' => '50px'],
                                ['key' => 'task_text', 'label' => 'Task', 'required' => true, 'width' => '360px'],
                                ['key' => 'task_kind', 'label' => 'Kind', 'type' => 'select', 'options' => array_map('t', MntPlanTask::KINDS), 'width' => '120px'],
                                ['key' => 'measure_uom', 'label' => 'Measure UoM', 'width' => '100px'],
                                ['key' => 'min_value', 'label' => 'Min', 'type' => 'number', 'width' => '90px'],
                                ['key' => 'max_value', 'label' => 'Max', 'type' => 'number', 'width' => '90px'],
                                ['key' => 'is_required', 'label' => 'Required', 'type' => 'bool', 'width' => '100px'],
                            ]">
                            @if ($head)
                                <x-slot:tools>
                                    <x-dl::field.text name="new_task" label="+ Task" placeholder="Task description..." />
                                    <x-dl::field.select name="new_task_kind" label="Kind" :options="array_map('t', MntPlanTask::KINDS)" value="INSPECT" />
                                    <x-dl::button size="sm" variant="secondary" label="+ Task" type="submit" name="action" value="add_task" formnovalidate />
                                </x-slot:tools>
                            @endif
                        </x-dl::lines>
                    </x-slot:tasks>
                    <x-slot:parts>
                        <x-dl::lines name="parts" title="Spare Parts" key-field="id_plan_part" remove-field="remove_parts" :addable="false" :duplicable="false" :min-rows="0"
                            :rows="Maintenance::planParts($plan)" :readonly="! $head" source="{{ Src::of('tm2prd.mnt_plan_part', 'tm2mst.mat_raw') }}"
                            :columns="[
                                ['key' => 'item', 'label' => 'Item', 'type' => 'display', 'width' => '360px'],
                                ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'width' => '100px'],
                                ['key' => 'uom_code', 'label' => 'UoM', 'type' => 'display', 'width' => '80px'],
                            ]">
                            @if ($head)
                                <x-slot:tools>
                                    <x-dl::field.lookup name="material_id" lookup="material" label="+ Spare part" placeholder="Type 2+ letters of code / name..." :source="Src::of('tm2mst.mat_raw')" />
                                    <x-dl::field.number name="new_part_qty" label="Qty" :value="1" />
                                    <x-dl::button size="sm" variant="secondary" label="+ Part" type="submit" name="action" value="add_part" formnovalidate />
                                </x-slot:tools>
                            @endif
                        </x-dl::lines>
                    </x-slot:parts>
                </x-dl::tabs>
            </x-slot:detail>
        @endif
    </x-dl::form>
@endif
