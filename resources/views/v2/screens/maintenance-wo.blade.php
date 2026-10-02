{{-- Maintenance Work Order v2 (docs/maintenance_tier1.md, mockup maintenance_v2.jpg bagian 1-2). Aturan = App\Services\Maintenance\MaintenanceService.
     Alur: GRID MWO -> + New Record (manual) / dari Request (P1 otomatis) / dari Preventive Plan (Generate PM) -> DRAFT ->
     Confirm (kepala maintenance: nomor MWO lahir, Scheduled) -> Start (downtime + status mesin) -> Hold (Waiting Part) <-> Resume ->
     Done (checklist wajib terisi, downtime ditutup, mesin Ready) -> Close (kepala maintenance: biaya final, plan maju). --}}
@php
    use App\Models\Prd\MntCode;
    use App\Models\Prd\MntLog;
    use App\Models\Prd\MntWo;
    use App\Models\Prd\MntWoTask;
    use App\Services\Maintenance\MaintenanceService;
    use App\Support\V2\Data\Maintenance;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'maintenance-wo');
    $wo = request('id') ? MntWo::with(['tasks', 'parts', 'labor'])->find((int) request('id')) : null;
    abort_if(request('id') && ! $wo, 404);
    $head = MaintenanceService::canDecide(auth()->user());
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $wo && ! request('new'))
    @if ($head)
        <x-dl::action-form action="{{ route('v2.mnt.wo.generate') }}">
            <x-dl::action-bar>
                <x-dl::button size="sm" variant="primary" icon="fa-bolt" label="Generate PM" type="submit" />
            </x-dl::action-bar>
        </x-dl::action-form>
    @endif
    <x-dl::grid id="mnt-wo-list" :rows="Maintenance::woRows()" search="Search WO, machine, technician..." actions="worklist" :active-key="null"
        :new-url="$home.'?new=1'" :open-url="$home.'?id={id}'" source="{{ Src::of('tm2prd.mnt_wo', 'tm2mst.prod_machine', 'tm2mst.prod_machine_downtime') }}"
        :columns="[
            ['key' => 'no', 'label' => 'WO No. / Machine', 'type' => 'doc', 'party' => 'machine'],
            ['key' => 'desc', 'label' => 'Description'],
            ['key' => 'support', 'label' => 'Machine Kind', 'visible' => false],
            ['key' => 'type', 'label' => 'Type', 'type' => 'badge', 'tones' => Maintenance::TYPE_TONES],
            ['key' => 'priority', 'label' => 'Priority', 'type' => 'badge', 'tones' => Maintenance::PRIO_TONES],
            ['key' => 'work', 'label' => 'Work Status', 'type' => 'badge', 'tones' => Maintenance::WORK_TONES],
            ['key' => 'planned_at', 'label' => 'Planned'],
            ['key' => 'technician', 'label' => 'Technician'],
            ['key' => 'downtime', 'label' => 'Downtime'],
            ['key' => 'source', 'label' => 'Source', 'visible' => false],
            ['key' => 'cost', 'label' => 'Cost', 'type' => 'money', 'sum' => true, 'visible' => false],
        ]"
        :stats="Maintenance::woStats()"
        :detail="['key' => 'items', 'title' => t('Spare Parts'), 'columns' => [
            ['key' => 'code', 'label' => t('Code')],
            ['key' => 'name', 'label' => t('Item Name'), 'strong' => true],
            ['key' => 'plan', 'label' => t('Planned Qty'), 'type' => 'number'],
            ['key' => 'used', 'label' => t('Used Qty'), 'type' => 'number'],
            ['key' => 'unit', 'label' => t('Unit')],
        ]]"
        :filters="['type' => 'Type', 'work' => 'Work Status', 'machine' => 'Machine', 'technician' => 'Technician', 'priority' => 'Priority']" />
@elseif (! $wo)
    <x-dl::action-form action="{{ route('v2.mnt.wo.create') }}">
        <x-dl::card title="+ New Record — Maintenance Work Order" description="Manual WO (e.g. a planned repair). Sudden breakdowns go through Maintenance Request; routine care through Preventive Plan.">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="machine_id" label="Machine" required :options="Maintenance::machines()" :source="Src::of('tm2mst.prod_machine')" />
                <x-dl::field.select name="wo_type" label="WO Type" required :options="MntWo::TYPES" value="CORRECTIVE" :source="Src::of('tm2prd.mnt_wo.wo_type')" />
                <x-dl::field.text name="description" label="Description" required :source="Src::of('tm2prd.mnt_wo.description')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Save" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@else
    @php
        $doc = $wo->docStatus();
        $draft = $doc === 'DRAFT';
        $working = $wo->isWorking();
        $formStatus = match (true) {
            $doc === 'CANCELLED' => 'cancelled', $doc === 'CLOSED' => 'closed', $draft => 'draft', $working => 'working', default => 'confirmed',
        };
        $stage = $doc === 'OPEN' ? MntWo::WORK[$wo->work_status] : null;
        $impact = Maintenance::impact($wo);
        $machines = Maintenance::machines();
        $comp = ['' => '—'] + MntCode::options('COMPONENT');
        $hide = ['duplicate', 'email', 'print'];
        if ($doc === 'OPEN' && $wo->work_status === 'DONE') {
            $hide[] = 'cancel';
        }
        if (! $head) {
            $hide[] = 'confirm';
        }
        $source = $wo->request_id ? ($wo->request?->doc_no ?? t('Request')) : ($wo->plan_id ? ($wo->plan?->plan_code ?? t('Plan')) : t('Manual'));
    @endphp
    @if ($wo->needs_machine_stop && $impact['downtime'])
        <x-dl::alert :tone="$impact['downtime_id'] && str_contains($impact['downtime'], 'running') ? 'danger' : 'info'"
            :message="'Machine impact — machine status: '.raw($impact['status'].' · downtime #'.$impact['downtime_id'].': '.$impact['downtime']).($impact['support'] ? ' · support equipment (not on the Scheduling Board)' : ' · production operations on this machine are rerouted in the WO Worklist').'. Done = downtime closed, machine Ready, OEE availability counted.'" />
    @endif
    <x-dl::form id="mnt-wo-form" :title="'Maintenance Work Order · '.raw($wo->doc_no ?: 'Draft #'.$wo->id_wo)" :subtitle="raw($machines[$wo->machine_id] ?? '#'.$wo->machine_id)"
        panel="top" kind="document" :status="$formStatus" :stage="$stage" :pipeline="true" :can-reopen="$head && $wo->work_status === 'SCHEDULED'" :log="MntLog::forDoc('WO', $wo->id_wo)"
        :action="route('v2.mnt.wo.save', $wo->id_wo)" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hide="$hide"
        :tabs="['general' => 'General', 'failure' => 'Failure', 'plan' => 'Schedule']"
        :created-by="$wo->created_by" :created-at="$wo->created_at?->toDateTimeString()" :updated-by="$wo->updated_by" :updated-at="$wo->updated_at?->toDateTimeString()">
        <x-slot:special>
            <x-dl::button size="sm" preset="print" :href="\App\Support\V2\Print\PrintDocs::url('maintenance-wo', $wo->id_wo)" target="_blank" />
            @if ($doc === 'OPEN' && $wo->work_status === 'SCHEDULED')
                <x-dl::button size="sm" variant="success" icon="fa-play" label="Start" type="submit" name="action" value="start" formnovalidate />
            @elseif ($wo->work_status === 'IN_PROGRESS' && $doc === 'OPEN')
                <x-dl::button size="sm" variant="secondary" icon="hold" label="Hold" type="submit" name="action" value="hold" formnovalidate />
                <x-dl::button size="sm" variant="success" icon="fa-check" label="Done" type="submit" name="action" value="done" formnovalidate />
            @elseif ($wo->work_status === 'WAITING_PART' && $doc === 'OPEN')
                <x-dl::button size="sm" variant="secondary" icon="fa-play" label="Resume" type="submit" name="action" value="resume" formnovalidate />
            @elseif ($doc === 'OPEN' && $wo->work_status === 'DONE' && $head)
                <x-dl::button size="sm" variant="success" icon="fa-lock" label="Close" type="submit" name="action" value="close" formnovalidate />
            @endif
        </x-slot:special>
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>{{ t('WO No') }}</span><b>{{ $wo->doc_no ?: t('(on Confirm)') }}</b></div>
            <div class="dl-strip__kv"><span>{{ t('Type') }}</span><b>{{ MntWo::TYPES[$wo->wo_type] ?? $wo->wo_type }}</b></div>
            <div class="dl-strip__kv"><span>{{ t('Priority') }}</span><b>{{ MntWo::PRIORITIES[$wo->priority] ?? $wo->priority }}</b></div>
            <div class="dl-strip__kv"><span>{{ t('From') }}</span><b>{{ $source }}</b></div>
            <div class="dl-strip__kv"><span>{{ t('MTBF · MTTR · Avail. (90 d)') }}</span><b>{{ $impact['rel']['mtbf'] ?? '—' }} h · {{ $impact['rel']['mttr'] ?? '—' }} h · {{ $impact['rel']['availability'] }}%</b></div>
            <div class="dl-strip__kv dl-strip__grand"><span>{{ t('Cost (live)') }}</span><b>{{ Maintenance::money($wo->totalCost()) }}</b></div>
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="machine_id" label="Machine" required :options="$machines" :value="$wo->machine_id" :disabled="! $draft" :source="Src::of('tm2mst.prod_machine')" />
                <x-dl::field.select name="wo_type" label="WO Type" required :options="MntWo::TYPES" :value="$wo->wo_type" :disabled="! $draft" :source="Src::of('tm2prd.mnt_wo.wo_type')" />
                <x-dl::field.select name="priority" label="Priority" required :options="MntWo::PRIORITIES" :value="$wo->priority" :disabled="! $draft" :source="Src::of('tm2prd.mnt_wo.priority')" />
                <x-dl::field.select name="component_code" label="Component" :options="$comp" :value="$wo->component_code" :source="Src::of('tm2prd.mnt_code (COMPONENT)')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.text name="description" label="Description" required :value="$wo->description" :disabled="! $draft" :source="Src::of('tm2prd.mnt_wo.description')" />
                <x-dl::field.lookup name="assigned_to" lookup="employee" label="Technician" :value="$wo->assigned_to" placeholder="Type a technician name..." :source="Src::of('tm2mst.emp_employee')" />
                <x-dl::field.toggle name="needs_machine_stop" label="Machine Stop" :on-label="t('Yes — downtime recorded')" :off-label="t('No — machine keeps running')" :value="$wo->needs_machine_stop" :disabled="! $draft" :source="Src::of('tm2prd.mnt_wo.needs_machine_stop')" />
                <x-dl::field.display label="Machine Status" :value="$impact['status']" :source="Src::of('tm2mst.prod_machine.machine_status')" />
            </x-dl::form.row>
        </x-slot:general>
        <x-slot:failure>
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="problem_code" label="Problem" :options="['' => '—'] + MntCode::options('PROBLEM')" :value="$wo->problem_code" :source="Src::of('tm2prd.mnt_code (PROBLEM)')" />
                <x-dl::field.select name="cause_code" label="Cause" :options="['' => '—'] + MntCode::options('CAUSE')" :value="$wo->cause_code" :source="Src::of('tm2prd.mnt_code (CAUSE)')" />
                <x-dl::field.select name="action_code" label="Action" :options="['' => '—'] + MntCode::options('ACTION')" :value="$wo->action_code" :source="Src::of('tm2prd.mnt_code (ACTION)')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="findings" label="Findings" multiline :value="$wo->findings" :source="Src::of('tm2prd.mnt_wo.findings')" />
                <x-dl::field.number name="cost_vendor" label="Vendor Service Cost (IDR)" :value="$wo->cost_vendor" :source="Src::of('tm2prd.mnt_wo.cost_vendor')" />
                <x-dl::field.number name="meter_at_done" label="Meter at Done (counter)" :value="$wo->meter_at_done" :source="Src::of('tm2prd.mnt_wo.meter_at_done')" />
            </x-dl::form.row>
        </x-slot:failure>
        <x-slot:plan>
            <x-dl::form.row :cols="4">
                <x-dl::field.date-time name="planned_start" label="Planned Start" :value="$wo->planned_start?->format('Y-m-d H:i')" :disabled="! $draft" :source="Src::of('tm2prd.mnt_wo.planned_start')" />
                <x-dl::field.date-time name="planned_end" label="Planned End" :value="$wo->planned_end?->format('Y-m-d H:i')" :disabled="! $draft" :source="Src::of('tm2prd.mnt_wo.planned_end')" />
                <x-dl::field.number name="est_minutes" label="Est. Duration (minutes)" :value="$wo->est_minutes" :disabled="! $draft" :source="Src::of('tm2prd.mnt_wo.est_minutes')" />
                <x-dl::field.display label="Actual Start → End" :value="Maintenance::stamp($wo->actual_start).' → '.Maintenance::stamp($wo->actual_end)" :source="Src::of('tm2prd.mnt_wo.actual_start', 'tm2prd.mnt_wo.actual_end')" />
            </x-dl::form.row>
        </x-slot:plan>
        <x-slot:detail>
            <x-dl::tabs :tabs="['checklist' => 'Checklist ('.raw($wo->tasks->count()).')', 'parts' => 'Spare Parts ('.raw($wo->parts->count()).')', 'labor' => 'Labor ('.raw($wo->labor->count()).')']">
                <x-slot:checklist>
                    <x-dl::lines name="tasks" title="Checklist" key-field="id_wo_task" :remove-field="$draft ? 'remove' : null" row-lock="editable" :addable="false" :duplicable="false" :min-rows="0"
                        :rows="Maintenance::tasks($wo)" :readonly="! ($draft || $working)" source="{{ Src::of('tm2prd.mnt_wo_task') }}"
                        :columns="[
                            ['key' => 'seq_no', 'label' => '#', 'type' => 'display', 'width' => '50px'],
                            ['key' => 'task_text', 'label' => 'Task', 'required' => true, 'width' => '340px'],
                            ['key' => 'kind', 'label' => 'Kind', 'type' => 'display', 'width' => '100px'],
                            ['key' => 'range', 'label' => 'Min – Max', 'type' => 'display', 'width' => '130px'],
                            ['key' => 'required', 'label' => 'Required', 'type' => 'display', 'width' => '90px'],
                            ['key' => 'result', 'label' => 'Result', 'type' => 'select', 'options' => ['' => '—'] + MntWoTask::RESULTS, 'width' => '110px'],
                            ['key' => 'measured_value', 'label' => 'Measured', 'type' => 'number', 'width' => '100px'],
                            ['key' => 'note', 'label' => 'Note', 'more' => true],
                            ['key' => 'done', 'label' => 'Done by', 'type' => 'display', 'more' => true],
                        ]">
                        @if ($draft || $working)
                            <x-slot:tools>
                                <x-dl::field.text name="new_task" label="+ Task" placeholder="Extra task description..." />
                                <x-dl::button size="sm" variant="secondary" label="+ Task" type="submit" name="action" value="add_task" formnovalidate />
                            </x-slot:tools>
                        @endif
                    </x-dl::lines>
                </x-slot:checklist>
                <x-slot:parts>
                    <x-dl::lines name="parts" title="Spare Parts" key-field="id_wo_part" :remove-field="$draft || $working ? 'remove_parts' : null" :addable="false" :duplicable="false" :min-rows="0"
                        :rows="Maintenance::parts($wo)" :readonly="! ($draft || $working)" source="{{ Src::of('tm2prd.mnt_wo_part', 'tm2mst.mat_raw') }}"
                        :columns="[
                            ['key' => 'item', 'label' => 'Item', 'type' => 'display', 'width' => '320px'],
                            ['key' => 'qty_plan', 'label' => 'Plan', 'type' => 'number', 'width' => '90px'],
                            ['key' => 'qty_used', 'label' => 'Used', 'type' => 'number', 'width' => '90px'],
                            ['key' => 'uom', 'label' => 'UoM', 'type' => 'display', 'width' => '70px'],
                            ['key' => 'stock', 'label' => 'Stock', 'type' => 'display', 'width' => '90px'],
                            ['key' => 'unit_cost', 'label' => 'Unit Cost', 'type' => 'display', 'width' => '110px'],
                            ['key' => 'amount', 'label' => 'Amount', 'type' => 'display', 'width' => '120px'],
                            ['key' => 'source', 'label' => 'Source', 'type' => 'display', 'width' => '140px'],
                        ]">
                        @if ($draft || $working)
                            <x-slot:tools>
                                <x-dl::field.lookup name="material_id" lookup="material" label="+ Spare part" placeholder="Type 2+ letters of code / name..." :source="Src::of('tm2mst.mat_raw')" />
                                <x-dl::field.number name="new_part_qty" label="Qty" :value="1" />
                                <x-dl::button size="sm" variant="secondary" label="+ Part" type="submit" name="action" value="add_part" formnovalidate />
                            </x-slot:tools>
                        @endif
                    </x-dl::lines>
                </x-slot:parts>
                <x-slot:labor>
                    <x-dl::lines name="labor" title="Labor" key-field="id_wo_labor" :remove-field="$draft || $working ? 'remove_labor' : null" :addable="false" :duplicable="false" :min-rows="0"
                        :rows="Maintenance::labor($wo)" :readonly="! ($draft || $working)" source="{{ Src::of('tm2prd.mnt_wo_labor', 'tm2sys.sys_constant (MNT_LABOR_RATE_HOUR)') }}"
                        :columns="[
                            ['key' => 'employee', 'label' => 'Technician', 'type' => 'display', 'width' => '260px'],
                            ['key' => 'work_date', 'label' => 'Date', 'type' => 'date', 'width' => '150px'],
                            ['key' => 'minutes', 'label' => 'Minutes', 'type' => 'number', 'sum' => true, 'width' => '100px'],
                            ['key' => 'rate', 'label' => 'Rate / hour', 'type' => 'display', 'width' => '120px'],
                            ['key' => 'amount', 'label' => 'Amount', 'type' => 'display', 'width' => '130px'],
                            ['key' => 'note', 'label' => 'Note', 'more' => true],
                        ]">
                        @if ($draft || $working)
                            <x-slot:tools>
                                <x-dl::field.lookup name="labor_employee_id" lookup="employee" label="+ Technician" placeholder="Type a name..." :value="$wo->assigned_to" :source="Src::of('tm2mst.emp_employee')" />
                                <x-dl::field.number name="labor_minutes" label="Minutes" :value="60" />
                                <x-dl::field.date name="labor_date" label="Date" :value="now()->toDateString()" />
                                <x-dl::button size="sm" variant="secondary" label="+ Labor" type="submit" name="action" value="add_labor" formnovalidate />
                            </x-slot:tools>
                        @endif
                    </x-dl::lines>
                </x-slot:labor>
            </x-dl::tabs>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Spare parts" :value="Maintenance::money($wo->cost_part)" :source="Src::of('tm2prd.mnt_wo.cost_part')" />
                <x-dl::field.display label="Labor" :value="Maintenance::money($wo->cost_labor)" :source="Src::of('tm2prd.mnt_wo.cost_labor')" />
                <x-dl::field.display label="Vendor service" :value="Maintenance::money($wo->cost_vendor)" :source="Src::of('tm2prd.mnt_wo.cost_vendor')" />
                <x-dl::field.display label="Total" :value="Maintenance::money($wo->totalCost())" />
            </x-dl::form.row>
        </x-slot:detail>
    </x-dl::form>
@endif
