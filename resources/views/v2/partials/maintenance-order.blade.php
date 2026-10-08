{{-- Maintenance Order v2 (Fase 2, mockup maintenance_v2_work.jpg bagian 2). Satu form untuk semua seri nomor; layar pemanggil memberi $series
     (PMO = Preventive + Inspection, BMO = Breakdown + Corrective). Aturan = App\Services\Maintenance\OrderService.
     Alur: grid -> + New Record (manual) / dari Preventive Plan (Generate) -> DRAFT -> Confirm (kepala maintenance: nomor lahir, Scheduled) ->
     Start (downtime + status mesin untuk aset mesin) -> Hold (Waiting Part) <-> Resume -> Done (checklist wajib terisi) -> Close (kepala maintenance). --}}
@php
    use App\Models\Master\ProdMachine;
    use App\Models\Master\ProdMachineDowntime;
    use App\Models\Mnt\MntAsset;
    use App\Models\Mnt\MntCode;
    use App\Models\Mnt\MntLog;
    use App\Models\Mnt\MntOrder;
    use App\Models\Mnt\MntOrderTask;
    use App\Services\Maintenance\MaintenanceAccess;
    use App\Support\V2\Data\Maintenance;
    use App\Support\V2\Src;

    $slug = strtolower($series) === 'pmo' ? 'maintenance-pmo' : 'maintenance-bmo';
    $heading = $series === 'PMO' ? 'Preventive Maintenance Order' : 'Breakdown Maintenance Order';
    $home = route('v2.screen', $slug);
    $order = request('id') ? MntOrder::with(['tasks', 'parts', 'labor', 'asset'])->find((int) request('id')) : null;
    abort_if(request('id') && (! $order || ! in_array($order->order_type, MntOrder::typesOf($series), true)), 404);
    $head = MaintenanceAccess::canManage(auth()->user());
    $types = array_intersect_key(MntOrder::TYPES, array_flip(MntOrder::typesOf($series)));
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $order && ! request('new'))
    @if ($head && $series === 'PMO')
        <x-dl::action-form action="{{ route('v2.mnt.plan.generate-due') }}">
            <x-dl::button size="sm" variant="secondary" icon="fa-bolt" label="Generate due orders" type="submit" />
        </x-dl::action-form>
    @endif
    {{-- Daftar = objek Grid Transactional (PmoListGrid / BmoListGrid, turunan OrderListGrid), tampil lewat view generik _dbgrid. --}}
    @include('v2.screens._dbgrid', ['screen' => ['grid' => $series === 'PMO' ? new \App\Support\V2\Grid\Maint\PmoListGrid : new \App\Support\V2\Grid\Maint\BmoListGrid]])
@elseif (! $order)
    <x-dl::action-form action="{{ route('v2.mnt.order.create') }}">
        <x-dl::card :title="raw('+ New Record — '.t($heading))" description="Manual order. Routine care is created from a Preventive Plan; this is for a one-off job.">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="asset_id" label="Asset" required :options="Maintenance::assetOptions()" :source="Src::of('tm2mnt.mnt_asset')" />
                <x-dl::field.select name="order_type" label="Order Type" required :options="array_map('t', $types)" :value="array_key_first($types)" :source="Src::of('tm2mnt.mnt_order.order_type')" />
                <x-dl::field.text name="description" label="Description" required :source="Src::of('tm2mnt.mnt_order.description')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Save" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@else
    @php
        $doc = $order->docStatus();
        $draft = $doc === 'DRAFT';
        $working = $order->isWorking();
        $formStatus = match (true) {
            $doc === 'CANCELLED' => 'cancelled', $doc === 'CLOSED' => 'closed', $draft => 'draft', $working => 'working', default => 'confirmed',
        };
        $stage = $doc === 'OPEN' ? MntOrder::WORK[$order->work_status] : null;
        $assets = Maintenance::assetOptions();
        $machine = $order->asset?->machine_id ? ProdMachine::find($order->asset->machine_id) : null;
        $down = $order->downtime_id ? ProdMachineDowntime::find($order->downtime_id) : null;
        $hide = ['duplicate'];
        if ($doc === 'OPEN' && $order->work_status === 'DONE') {
            $hide[] = 'cancel';
        }
        if (! $head) {
            $hide[] = 'confirm';
        }
        $source = $order->request_id ? t('Request') : ($order->plan_id ? ($order->plan?->plan_code ?? t('Plan')) : t('Manual'));
        $canWork = $draft || $working;
    @endphp
    @if ($machine && $order->needs_stop && $down)
        <x-dl::alert :tone="$down->end_at ? 'info' : 'warn'"
            :message="'Machine impact — machine status: '.raw($machine->machine_status.' · downtime #'.$down->downtime_id.': '.Maintenance::stamp($down->start_at).' → '.($down->end_at ? Maintenance::stamp($down->end_at) : t('(running)'))).'. Done = downtime closed and the machine is Ready again.'" />
    @endif
    <x-dl::form id="mnt-order-form" :title="$heading.' · '.raw($order->doc_no ?: 'Draft #'.$order->id_order)" :subtitle="raw($assets[$order->asset_id] ?? '#'.$order->asset_id)"
        panel="top" kind="document" :status="$formStatus" :stage="$stage" :pipeline="true" :can-reopen="$head && $order->work_status === 'SCHEDULED'" :log="MntLog::forDoc('ORDER', $order->id_order)"
        :action="route('v2.mnt.order.save', $order->id_order)" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hide="$hide"
        :tabs="['general' => 'General', 'failure' => 'Failure', 'plan' => 'Schedule']"
        :created-by="$order->created_by" :created-at="$order->created_at?->toDateTimeString()" :updated-by="$order->updated_by" :updated-at="$order->updated_at?->toDateTimeString()">
        <x-slot:special>
            @if ($doc === 'OPEN' && $order->work_status === 'SCHEDULED')
                <x-dl::button size="sm" variant="success" icon="fa-play" label="Start" type="submit" name="action" value="start" formnovalidate />
            @elseif ($order->work_status === 'IN_PROGRESS' && $doc === 'OPEN')
                <x-dl::button size="sm" variant="secondary" icon="hold" label="Hold" type="submit" name="action" value="hold" formnovalidate />
                <x-dl::button size="sm" variant="success" icon="fa-check" label="Done" type="submit" name="action" value="done" formnovalidate />
            @elseif ($order->work_status === 'WAITING_PART' && $doc === 'OPEN')
                <x-dl::button size="sm" variant="secondary" icon="fa-play" label="Resume" type="submit" name="action" value="resume" formnovalidate />
            @elseif ($doc === 'OPEN' && $order->work_status === 'DONE' && $head)
                <x-dl::button size="sm" variant="success" icon="fa-lock" label="Close" type="submit" name="action" value="close" formnovalidate />
            @endif
        </x-slot:special>
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>@t('Order No.')</span><b>{{ $order->doc_no ?: t('(on Confirm)') }}</b></div>
            <div class="dl-strip__kv"><span>@t('Type')</span><b>{{ t(MntOrder::TYPES[$order->order_type] ?? $order->order_type) }}</b></div>
            <div class="dl-strip__kv"><span>@t('Priority')</span><b>{{ t(MntOrder::PRIORITIES[$order->priority] ?? $order->priority) }}</b></div>
            <div class="dl-strip__kv"><span>@t('From')</span><b>{{ $source }}</b></div>
            <div class="dl-strip__kv dl-strip__grand"><span>@t('Cost (live)')</span><b>{{ Maintenance::money($order->totalCost()) }}</b></div>
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="asset_id" label="Asset" required :options="$assets" :value="$order->asset_id" :disabled="! $draft" :source="Src::of('tm2mnt.mnt_asset')" />
                <x-dl::field.select name="order_type" label="Order Type" required :options="array_map('t', $types)" :value="$order->order_type" :disabled="! $draft" :source="Src::of('tm2mnt.mnt_order.order_type')" />
                <x-dl::field.select name="priority" label="Priority" required :options="array_map('t', MntOrder::PRIORITIES)" :value="$order->priority" :disabled="! $draft" :source="Src::of('tm2mnt.mnt_order.priority')" />
                <x-dl::field.select name="component_code" label="Component" :options="['' => '—'] + MntCode::options('COMPONENT')" :value="$order->component_code" :source="Src::of('tm2mnt.mnt_code (COMPONENT)')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.text name="description" label="Description" required :value="$order->description" :disabled="! $draft" :source="Src::of('tm2mnt.mnt_order.description')" />
                <x-dl::field.lookup name="assigned_to" lookup="employee" label="Technician" :value="$order->assigned_to" placeholder="Type a technician name..." :source="Src::of('tm2mst.emp_employee')" />
                <x-dl::field.toggle name="needs_stop" label="Asset Stop" :on-label="t('Yes: downtime is recorded')" :off-label="t('No: the asset keeps running')" :value="$order->needs_stop" :disabled="! $draft" :source="Src::of('tm2mnt.mnt_order.needs_stop')" />
                <x-dl::field.display label="Machine Status" :value="$machine?->machine_status ?? t('Not a machine')" :source="Src::of('tm2mst.prod_machine.machine_status')" />
            </x-dl::form.row>
        </x-slot:general>
        <x-slot:failure>
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="problem_code" label="Problem" :options="['' => '—'] + MntCode::options('PROBLEM')" :value="$order->problem_code" :source="Src::of('tm2mnt.mnt_code (PROBLEM)')" />
                <x-dl::field.select name="cause_code" label="Cause" :options="['' => '—'] + MntCode::options('CAUSE')" :value="$order->cause_code" :source="Src::of('tm2mnt.mnt_code (CAUSE)')" />
                <x-dl::field.select name="action_code" label="Action Taken" :options="['' => '—'] + MntCode::options('ACTION')" :value="$order->action_code" :source="Src::of('tm2mnt.mnt_code (ACTION)')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="findings" label="Findings" multiline :value="$order->findings" :source="Src::of('tm2mnt.mnt_order.findings')" />
                <x-dl::field.number name="cost_vendor" label="Vendor Service Cost (IDR)" :value="$order->cost_vendor" :source="Src::of('tm2mnt.mnt_order.cost_vendor')" />
                <x-dl::field.number name="meter_at_done" label="Meter at Done (counter)" :value="$order->meter_at_done" :source="Src::of('tm2mnt.mnt_order.meter_at_done')" />
            </x-dl::form.row>
        </x-slot:failure>
        <x-slot:plan>
            <x-dl::form.row :cols="4">
                <x-dl::field.date-time name="planned_start" label="Planned Start" :value="$order->planned_start?->format('Y-m-d H:i')" :disabled="! $draft" :source="Src::of('tm2mnt.mnt_order.planned_start')" />
                <x-dl::field.date-time name="planned_end" label="Planned End" :value="$order->planned_end?->format('Y-m-d H:i')" :disabled="! $draft" :source="Src::of('tm2mnt.mnt_order.planned_end')" />
                <x-dl::field.number name="est_minutes" label="Est. Duration (minutes)" :value="$order->est_minutes" :disabled="! $draft" :source="Src::of('tm2mnt.mnt_order.est_minutes')" />
                <x-dl::field.display label="Actual Start → End" :value="Maintenance::stamp($order->actual_start).' → '.Maintenance::stamp($order->actual_end)" :source="Src::of('tm2mnt.mnt_order.actual_start', 'tm2mnt.mnt_order.actual_end')" />
            </x-dl::form.row>
        </x-slot:plan>
        <x-slot:detail>
            <x-dl::tabs :tabs="['checklist' => 'Checklist ('.raw($order->tasks->count()).')', 'parts' => 'Spare Parts ('.raw($order->parts->count()).')', 'labor' => 'Labor ('.raw($order->labor->count()).')']">
                <x-slot:checklist>
                    <x-dl::lines name="tasks" title="Checklist" key-field="id_order_task" :remove-field="$draft ? 'remove' : null" row-lock="editable" :addable="false" :duplicable="false" :min-rows="0"
                        :rows="Maintenance::orderTaskRows($order)" :readonly="! $canWork" source="{{ Src::of('tm2mnt.mnt_order_task') }}"
                        :columns="[
                            ['key' => 'task_text', 'label' => 'Task', 'required' => true, 'width' => '340px'],
                            ['key' => 'task_kind', 'label' => 'Kind', 'type' => 'display', 'width' => '100px'],
                            ['key' => 'range', 'label' => 'Min – Max', 'type' => 'display', 'width' => '130px'],
                            ['key' => 'result', 'label' => 'Result', 'type' => 'select', 'options' => ['' => '—'] + array_map('t', MntOrderTask::RESULTS), 'width' => '110px'],
                            ['key' => 'measured_value', 'label' => 'Measured', 'type' => 'number', 'width' => '100px'],
                            ['key' => 'note', 'label' => 'Note', 'more' => true],
                        ]">
                        @if ($canWork)
                            <x-slot:tools>
                                <x-dl::field.text name="new_task" label="+ Task" placeholder="Extra task description..." />
                                <x-dl::button size="sm" variant="secondary" label="+ Task" type="submit" name="action" value="add_task" formnovalidate />
                            </x-slot:tools>
                        @endif
                    </x-dl::lines>
                </x-slot:checklist>
                <x-slot:parts>
                    <x-dl::lines name="parts" title="Spare Parts" key-field="id_order_part" :remove-field="$canWork ? 'remove_parts' : null" :addable="false" :duplicable="false" :min-rows="0"
                        :rows="Maintenance::orderPartRows($order)" :readonly="! $canWork" source="{{ Src::of('tm2mnt.mnt_order_part', 'tm2mst.mat_raw') }}"
                        :columns="[
                            ['key' => 'item', 'label' => 'Item', 'type' => 'display', 'width' => '320px'],
                            ['key' => 'qty_plan', 'label' => 'Plan', 'type' => 'number', 'width' => '90px'],
                            ['key' => 'qty_used', 'label' => 'Used', 'type' => 'number', 'width' => '90px'],
                            ['key' => 'uom_code', 'label' => 'UoM', 'type' => 'display', 'width' => '70px'],
                            ['key' => 'unit_cost', 'label' => 'Unit Cost', 'type' => 'display', 'width' => '110px'],
                        ]">
                        @if ($canWork)
                            <x-slot:tools>
                                <x-dl::field.lookup name="material_id" lookup="material" label="+ Spare part" placeholder="Type 2+ letters of code / name..." :source="Src::of('tm2mst.mat_raw')" />
                                <x-dl::field.number name="new_part_qty" label="Qty" :value="1" />
                                <x-dl::button size="sm" variant="secondary" label="+ Part" type="submit" name="action" value="add_part" formnovalidate />
                            </x-slot:tools>
                        @endif
                    </x-dl::lines>
                </x-slot:parts>
                <x-slot:labor>
                    <x-dl::lines name="labor" title="Labor" key-field="id_order_labor" :remove-field="$canWork ? 'remove_labor' : null" :addable="false" :duplicable="false" :min-rows="0"
                        :rows="Maintenance::orderLaborRows($order)" :readonly="! $canWork" source="{{ Src::of('tm2mnt.mnt_order_labor', 'tm2sys.sys_constant (MNT_LABOR_RATE_HOUR)') }}"
                        :columns="[
                            ['key' => 'employee', 'label' => 'Technician', 'type' => 'display', 'width' => '260px'],
                            ['key' => 'work_date', 'label' => 'Date', 'type' => 'date', 'width' => '150px'],
                            ['key' => 'minutes', 'label' => 'Minutes', 'type' => 'number', 'sum' => true, 'width' => '100px'],
                            ['key' => 'note', 'label' => 'Note', 'more' => true],
                        ]">
                        @if ($canWork)
                            <x-slot:tools>
                                <x-dl::field.lookup name="labor_employee_id" lookup="employee" label="+ Technician" placeholder="Type a name..." :value="$order->assigned_to" :source="Src::of('tm2mst.emp_employee')" />
                                <x-dl::field.number name="labor_minutes" label="Minutes" :value="60" />
                                <x-dl::field.date name="labor_date" label="Date" :value="now()->toDateString()" />
                                <x-dl::button size="sm" variant="secondary" label="+ Labor" type="submit" name="action" value="add_labor" formnovalidate />
                            </x-slot:tools>
                        @endif
                    </x-dl::lines>
                </x-slot:labor>
            </x-dl::tabs>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Spare parts" :value="Maintenance::money((float) $order->cost_part)" :source="Src::of('tm2mnt.mnt_order.cost_part')" />
                <x-dl::field.display label="Labor" :value="Maintenance::money((float) $order->cost_labor)" :source="Src::of('tm2mnt.mnt_order.cost_labor')" />
                <x-dl::field.display label="Vendor service" :value="Maintenance::money((float) $order->cost_vendor)" :source="Src::of('tm2mnt.mnt_order.cost_vendor')" />
                <x-dl::field.display label="Total" :value="Maintenance::money($order->totalCost())" />
            </x-dl::form.row>
        </x-slot:detail>
    </x-dl::form>
@endif
