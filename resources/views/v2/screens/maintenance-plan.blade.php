{{-- Preventive Plan v2 (Fase 2, mockup maintenance_v2_work.jpg bagian 3). Template Preventive Maintenance Order per aset: pemicu waktu dan/atau meter
     (mana yang lebih dulu), Floating (dari selesai terakhir) atau Fixed (tanggal pasti, inspeksi wajib), checklist dan spare part standar.
     Perintah harian maintenance:generate-pm membuat PMO Draft saat jatuh tempo. Isi plan nyata diketik kepala maintenance dari manual pabrikan. --}}
@php
    use App\Models\Mnt\MntLog;
    use App\Models\Mnt\MntMeterReading;
    use App\Models\Mnt\MntOrder;
    use App\Models\Mnt\MntPlan;
    use App\Models\Mnt\MntPlanTask;
    use App\Services\Maintenance\ComplianceService;
    use App\Services\Maintenance\MaintenanceAccess;
    use App\Support\V2\Data\Maintenance;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'maintenance-plan');
    $plan = request('id') ? MntPlan::with(['tasks', 'parts'])->find((int) request('id')) : null;
    abort_if(request('id') && ! $plan, 404);
    $head = MaintenanceAccess::canManage(auth()->user());
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $plan && ! request('new'))
    @if ($head)
        <x-dl::action-form action="{{ route('v2.mnt.plan.generate-due') }}">
            <x-dl::button size="sm" variant="secondary" icon="fa-bolt" label="Generate due orders" type="submit" />
        </x-dl::action-form>
    @endif
    {{-- Daftar = objek Grid Transactional App\Support\V2\Grid\Maint\PlanListGrid (DocListGrid), tampil lewat view generik _dbgrid. --}}
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Maint\PlanListGrid]])
@else
    @php
        $new = ! $plan;
        $p = $plan ?? new MntPlan(['order_type' => 'PREVENTIVE', 'trigger_mode' => 'TIME', 'interval_value' => 1, 'interval_unit' => 'MONTH', 'schedule_mode' => 'FLOATING',
            'lead_days' => 3, 'needs_stop' => true, 'activate' => true]);
        $cur = $plan && $plan->usesMeter() ? MntMeterReading::latestValue($plan->asset_id, (string) $plan->meter_type) : null;
        $cmp = $plan ? (new ComplianceService)->summarize(MntOrder::with('plan')->where('plan_id', $plan->id_plan)->where('stat_confirm', true)->where('stat_cancel', false)
            ->where('planned_start', '>=', now()->subYear())->get()) : null;
        $num = fn ($v) => $v === null ? '—' : Locale::number((float) $v, 0);
        $assets = Maintenance::assetOptions();
    @endphp
    <x-dl::form id="mnt-plan-form" :title="'Preventive Plan · '.($p->plan_code ? raw($p->plan_code) : 'New')" :subtitle="$new ? null : raw($assets[$p->asset_id] ?? null)"
        panel="top" kind="master" :status="$new ? 'new' : ($p->activate ? 'active' : 'inactive')" :can-input="$head" :log="$plan ? MntLog::forDoc('PLAN', $plan->id_plan) : []"
        :action="route('v2.mnt.plan.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hidden="$plan ? ['id' => $plan->id_plan] : []"
        :tabs="['general' => 'General', 'trigger' => 'Trigger', 'notes' => 'Notes']"
        :created-by="$p->created_by" :created-at="$p->created_at?->toDateTimeString()" :updated-by="$p->updated_by" :updated-at="$p->updated_at?->toDateTimeString()">
        @if ($plan && $head && $plan->activate)
            <x-slot:special>
                <x-dl::button size="sm" variant="secondary" icon="fa-bolt" label="Generate Order" type="submit" name="action" value="generate" formnovalidate />
            </x-slot:special>
        @endif
        <x-slot:strip>
            <x-dl::strip-kv kind="doc" label="Plan" :value="$p->plan_code ?: t('(new)')" />
            <x-dl::strip-kv label="Last done" :value="($p->last_done_date ? Locale::date($p->last_done_date) : '—').($p->last_done_meter !== null ? ' · '.$num($p->last_done_meter) : '')" />
            <x-dl::strip-kv label="By time" :value="$p->next_due_date ? Locale::date($p->next_due_date) : '—'" />
            <x-dl::strip-kv label="By meter" :value="$p->next_due_meter !== null ? $num($p->next_due_meter).' ('.t('now').' '.$num($cur).')' : '—'" />
            <x-dl::strip-kv kind="grand" label="PM compliance (12 m)" :value="$cmp && $cmp['pct'] !== null ? $cmp['pct'].'% · '.$cmp['on_time'].' '.t('on time').' / '.$cmp['late'].' '.t('late') : '—'" />
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="plan_code" label="Plan Code" required :case-toggle="false" :value="$p->plan_code" placeholder="PM-AC01-01" :source="Src::of('tm2mnt.mnt_plan.plan_code')" />
                <x-dl::field.text name="plan_name" label="Plan Name" required :value="$p->plan_name" :source="Src::of('tm2mnt.mnt_plan.plan_name')" />
                <x-dl::field.select name="asset_id" label="Asset" required :options="$assets" :value="$p->asset_id" :source="Src::of('tm2mnt.mnt_asset')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="order_type" label="Order Type" required :options="array_map('t', MntPlan::TYPES)" :value="$p->order_type" :source="Src::of('tm2mnt.mnt_plan.order_type')" />
                <x-dl::field.number name="est_minutes" label="Est. Duration (minutes)" :value="$p->est_minutes" :source="Src::of('tm2mnt.mnt_plan.est_minutes')" />
                <x-dl::field.toggle name="needs_stop" label="Asset Stop" :on-label="t('Yes: the asset stops')" :off-label="t('No')" :value="$p->needs_stop" :source="Src::of('tm2mnt.mnt_plan.needs_stop')" />
                <x-dl::field.date name="certificate_valid_until" label="Certificate valid until (inspection)" :value="$p->certificate_valid_until?->toDateString()" :source="Src::of('tm2mnt.mnt_plan.certificate_valid_until')" />
            </x-dl::form.row>
        </x-slot:general>
        <x-slot:trigger>
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="trigger_mode" label="Trigger" required :options="array_map('t', MntPlan::TRIGGERS)" :value="$p->trigger_mode" :source="Src::of('tm2mnt.mnt_plan.trigger_mode')" />
                <x-dl::field.number name="interval_value" label="Every" :min="1" :value="$p->interval_value" :source="Src::of('tm2mnt.mnt_plan.interval_value')" />
                <x-dl::field.select name="interval_unit" label="Unit" :options="['' => '—'] + array_map('t', MntPlan::UNITS)" :value="$p->interval_unit" :source="Src::of('tm2mnt.mnt_plan.interval_unit')" />
                <x-dl::field.select name="schedule_mode" label="Schedule Mode" required :options="array_map('t', MntPlan::MODES)" :value="$p->schedule_mode" :source="Src::of('tm2mnt.mnt_plan.schedule_mode')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="meter_type" label="Meter" :options="['' => '—'] + array_map('t', MntPlan::METERS)" :value="$p->meter_type" :source="Src::of('tm2mnt.mnt_plan.meter_type')" />
                <x-dl::field.number name="meter_interval" label="Or every (meter)" :value="$p->meter_interval" :source="Src::of('tm2mnt.mnt_plan.meter_interval')" />
                <x-dl::field.number name="lead_days" label="Create order before due (days)" :value="$p->lead_days" :source="Src::of('tm2mnt.mnt_plan.lead_days')" />
                <x-dl::field.date name="next_due_date" label="Next due (Fixed: first date)" :value="$p->next_due_date?->toDateString()" :source="Src::of('tm2mnt.mnt_plan.next_due_date')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.date name="last_done_date" label="Last done" :value="$p->last_done_date?->toDateString()" :source="Src::of('tm2mnt.mnt_plan.last_done_date')" />
                <x-dl::field.number name="last_done_meter" label="Meter at last done" :value="$p->last_done_meter" :source="Src::of('tm2mnt.mnt_plan.last_done_meter')" />
            </x-dl::form.row>
        </x-slot:trigger>
        <x-slot:notes>
            <x-dl::field.text name="notes" label="Notes" multiline :value="$p->notes" :source="Src::of('tm2mnt.mnt_plan.notes')" />
        </x-slot:notes>
        @if ($plan)
            <x-slot:detail>
                <x-dl::tabs :tabs="['tasks' => 'Tasks ('.raw($plan->tasks->count()).')', 'parts' => 'Spare Parts ('.raw($plan->parts->count()).')']">
                    <x-slot:tasks>
                        <x-dl::lines name="tasks" title="Tasks" key-field="id_plan_task" remove-field="remove" :addable="false" :duplicable="false" :min-rows="0"
                            :rows="Maintenance::planTaskRows($plan)" :readonly="! $head" source="{{ Src::of('tm2mnt.mnt_plan_task') }}"
                            :columns="[
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
                            :rows="Maintenance::planPartRows($plan)" :readonly="! $head" source="{{ Src::of('tm2mnt.mnt_plan_part', 'tm2mst.mat_raw') }}"
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
