{{-- Work Order v2 (PPIC): what scheduling knows about one Work Order. Created from Product Development data (layout, BOP, routing, BOM) by
     WoGenerationService; enriched by WoPlanningEnricher (process ids, machines that can run each step, target / waste / spoilage, setup);
     material and tooling gates by GateService. Data = App\Support\V2\Data\Ppic::workOrder(). The schedule itself is planned and confirmed on
     Production Scheduling; this page links to it and records output. --}}
@php
    use App\Support\V2\Data\Ppic;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $id = request()->integer('id');
    $d = $id ? Ppic::workOrder($id) : null;
    abort_if($id && ! $d, 404);
    $src = Src::of('tm2prd.prd_wo', 'tm2prd.prd_wo_operation', 'tm2prd.prd_wo_operation_machine', 'tm2prd.prd_wo_material', 'tm2mst.prod_process_machine');
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $d)
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Prod\WorkOrderGrid]])
@else
    @php
        $wo = $d['wo'];
        $ops = $d['ops'];
        $first = $d['first'];
        $layout = (array) json_decode((string) ($wo->layout_json ?? '[]'), true);
        $ups = (int) ($wo->ups_per_sheet ?: 0);
        $blocked = collect($ops)->filter(fn ($o) => in_array('BLOCK', $o['gates'], true));
        $waiting = collect($ops)->filter(fn ($o) => in_array('WAIT', $o['gates'], true));
        $g = Ppic::gantt($ops);
        $back = route('v2.screen', 'prod-work-order');
        $gateText = ['READY' => 'Ready', 'WAIT' => 'Waiting', 'BLOCK' => 'Blocked'];
    @endphp

    <x-dl::card :title="raw('Work Order · '.$wo->wo_no)" :description="raw(($first['customer'] ?? '—').' · '.($first['product'] ?? '—').($wo->part_code ? ' · '.$wo->part_code : ''))">
        <x-dl::form.row :cols="4">
            <x-dl::field.display label="Status" :value="$wo->wo_status" :source="$src" />
            <x-dl::field.display label="Quantity" :value="Locale::number($wo->qty, 0)" :source="$src" />
            <x-dl::field.display label="Due Date" :value="Locale::dt($wo->due_date)" :source="$src" />
            <x-dl::field.display label="Priority" :value="$wo->is_hot_job ? t('Hot job') : t('Normal')" :source="$src" />
        </x-dl::form.row>
        <x-dl::form.row :cols="4">
            <x-dl::field.display label="Ups per sheet" :value="$ups ?: '—'" :source="$src" />
            <x-dl::field.display label="Press sheet (mm)" :value="$wo->sheet_length_mm ? Locale::decimal($wo->sheet_length_mm, 0).' x '.Locale::decimal($wo->sheet_width_mm, 0) : '—'" :source="$src" />
            <x-dl::field.display label="Board (gsm)" :value="$wo->board_gsm ?: '—'" :source="$src" />
            <x-dl::field.display label="Colours" :value="$wo->colour_count ?: '—'" :source="$src" />
        </x-dl::form.row>
        <x-dl::form.row :cols="4">
            <x-dl::field.display label="Dieline" :value="$wo->dieline_ref ?: '—'" :source="$src" />
            <x-dl::field.display label="Customer tier" :value="$wo->customer_tier ?: 2" :source="$src" />
            <x-dl::field.display label="Source" :value="$wo->source_type.($wo->source_co_id ? ' · CO#'.$wo->source_co_id : '')" :source="$src" />
            <x-dl::field.display label="Layout" :value="$layout ? collect($layout)->map(fn ($v, $k) => $k.': '.$v)->implode(' · ') : t('Not in Product Development yet')" :source="$src" />
        </x-dl::form.row>
        @if ($blocked->isNotEmpty())
            <x-dl::alert tone="danger" :message="raw(t('Blocked').': '.$blocked->pluck('gate_note')->filter()->unique()->implode(' · '))" />
        @elseif ($waiting->isNotEmpty())
            <x-dl::alert tone="warn" :message="raw(t('Waiting').': '.$waiting->pluck('gate_note')->filter()->unique()->implode(' · '))" />
        @endif
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="back" label="Back" :href="$back" />
            <x-dl::button variant="secondary" icon="fa-chart-gantt" label="Open in Gantt" :href="route('v2.screen', 'production-scheduling').'?view=gantt&q='.urlencode($wo->wo_no)" />
            <x-dl::action-form action="{{ route('v2.ppic.wo.refresh', $wo->wo_id) }}"><x-dl::button variant="secondary" icon="fa-rotate" label="Refresh from ProDev" type="submit" /></x-dl::action-form>
        </x-dl::action-bar>
        <x-dl::action-form action="{{ route('v2.ppic.prioritize') }}">
            <input type="hidden" name="wo_id" value="{{ $wo->wo_id }}">
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="kind" label="Priority" :options="['HOT' => t('Hot job'), 'MOVE_UP' => t('Move up')]" :value="'HOT'" :source="Src::of('tm2prd.prd_wo.priority')" />
                <x-dl::field.text name="reason" label="Reason" hint="Required. Written to the decision log; the engine re-plans around it." :source="Src::of('tm2prd.prd_plan_decision.reason')" />
                <x-dl::button icon="fa-fire" label="Give priority" type="submit" />
            </x-dl::form.row>
        </x-dl::action-form>
    </x-dl::card>

    <x-dl::card title="BOP and routing" description="Operations from Product Development. Quantity in of each step is the good output of the step before it; waste and spoilage of every step are already inside, so material is requested once.">
        <x-dl::table :source="$src" empty="This Work Order has no operations yet." :columns="['seq' => '#', 'process' => 'Process', 'machine' => 'Machine', 'qin' => 'Qty in', 'qout' => 'Good out', 'target' => 'Target / hour', 'waste' => 'Waste qty', 'spoil' => 'Spoilage qty',
            'setup' => 'Setup (min)', 'run' => 'Run (h)', 'start' => 'Start', 'finish' => 'Finish', 'status' => 'Status', 'gates' => 'Gates', 'out' => 'Output']"
            :rows="array_map(fn ($o) => ['seq' => $o['seq'], 'process' => raw($o['process']), 'machine' => raw($o['machine'] ?? ($o['candidates'] ? t('Chosen on Confirm') : '—')),
                'qin' => raw(Locale::number($o['qty'], 0).' '.$o['uom']), 'qout' => raw(Locale::number($o['qty_out'], 0).' '.$o['uom']),
                'target' => raw($o['target'] ? Locale::number($o['target'], 0).'/h'.($ups && $o['uom'] === 'LBR' ? ' = '.Locale::number($o['target'] * $ups, 0).' pcs/h' : '') : '—'),
                'waste' => $o['waste'] !== null ? Locale::number($o['waste'], 0) : '—', 'spoil' => $o['spoilage'] !== null ? Locale::number($o['spoilage'], 0) : '—',
                'setup' => $o['setup'] ?: '—', 'run' => $o['run'] !== null ? Locale::decimal($o['run'] / 60, 2) : '—',
                'start' => $o['start'] ? raw(Locale::dt($o['start'])) : '—', 'finish' => $o['finish'] ? raw(Locale::dt($o['finish'])) : '—',
                'status' => raw($o['plan_status'] === 'DRAFT' ? 'Draft' : $o['task_status']), 'gates' => raw(implode(' / ', array_map(fn ($x) => $gateText[$x] ?? $x, $o['gates']))),
                'out' => raw($o['progress'].'% · '.Locale::number($o['good'], 0))], $ops)" />
    </x-dl::card>

    <x-dl::card title="Machines that can run each process" description="Checked against sheet size, board weight, colours and the machine master. Drag a bar on the Gantt only to a machine that shows Yes.">
        @php
            $cand = [];
            foreach ($ops as $o) {
                foreach ($o['cands'] as $c) {
                    $cand[] = ['process' => raw($o['process']), 'machine' => raw($c['machine']), 'ok' => $c['eligible'] ? t('Yes') : t('No'), 'why' => raw($c['reason'] ?? ''), 'rank' => $c['rank'] ?? '—',
                        'target' => $c['target'] ? raw(Locale::number($c['target'], 0).'/h') : '—', 'setup' => $c['setup'] ?? '—'];
                }
            }
        @endphp
        <x-dl::table :columns="['process' => 'Process', 'machine' => 'Machine', 'ok' => 'Eligible', 'why' => 'Why not', 'rank' => 'Rank', 'target' => 'Target for this product', 'setup' => 'Setup (min)']" :rows="$cand" empty="Press Refresh from ProDev to compute the machines." :source="$src" />
    </x-dl::card>

    <x-dl::card title="BOM and material" description="Material lines from Product Development. Readiness comes from Material Planning (stock, booking, incoming purchases).">
        <x-dl::table :columns="['name' => 'Material', 'role' => 'Role', 'need' => 'Required', 'uom' => 'Unit', 'by' => 'Needed by']" empty="No material lines." :source="$src"
            :rows="$d['materials']->map(fn ($m) => ['name' => raw($m->material_name), 'role' => raw($m->material_role), 'need' => Locale::decimal($m->qty_required, 2), 'uom' => raw($m->uom), 'by' => $m->material_needed_by ? raw(Locale::d($m->material_needed_by)) : '—'])->all()" />
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="fa-boxes-stacked" label="Open Material Planning" :href="route('v2.screen', 'material-planning')" />
        </x-dl::action-bar>
    </x-dl::card>

    @if ($g['tasks'])
        <x-dl::card title="Schedule of this Work Order" description="Read only here. Plan and move it on Production Scheduling.">
            <x-dl::gantt :rows="$g['rows']" :tasks="$g['tasks']" :categories="$g['categories']" :origin-date="$g['originDate']" :start="$g['start']" :end="$g['end']" :px-per-hour="$g['pxPerHour']"
                :now-at="$g['nowAt']" :lock-until="$g['lockUntil']" :simulation="false" :submit-label="null" :draft-label="$g['draftLabel']" resource-label="Machine" :source="$src" />
        </x-dl::card>
    @endif

    <x-dl::card title="Output" description="Good quantity per operation. The operator timer screen writes the same table.">
        <x-dl::action-form action="{{ route('v2.ppic.wo.output', $wo->wo_id) }}">
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="operation_id" label="Operation" required :options="collect($ops)->mapWithKeys(fn ($o) => [$o['op_id'] => $o['seq'].' · '.$o['process']])->all()" placeholder="Choose operation..." :source="$src" />
                <x-dl::field.number name="qty_good" label="Good quantity" required :source="Src::of('tm2prd.prd_wo_operation_output.qty_good')" />
                <x-dl::field.text name="note" label="Note" :case-toggle="false" :source="Src::of('tm2prd.prd_wo_operation_output.notes')" />
                <x-dl::button preset="save" label="Record output" type="submit" />
            </x-dl::form.row>
        </x-dl::action-form>
    </x-dl::card>
@endif
