{{-- REMARK
Tujuan       : Layar Work Order PPIC: grid, picker Create Work Order, dan Form WO berupa accordion (Master Card, Bill of Process, Bill of Material, Simulation, QUE).
Sumber hulu  : Ppic::workOrder/masterCard/bomSheet/simulation, prd_wo*, Master Card otomatis dari Inquiry + ProDev.
Input        : Query ?id= (WO), ?new=1 (picker), ?open= atau ?tab= (bagian terbuka), ?layout=tabs, ?extras=1 (kartu angka + legenda Gantt di Simulation, mati secara default).
Output       : Tampilan; aksi lewat rute v2.ppic.* (target, reorder, earliest, bom-cells, queue-move, process, apply, delete). Sheet BOP dan BOM: toolbar hanya ADD / DELETE / RESET (tanpa Search dan hitung baris; DELETE aktif setelah nomor baris diklik; RESET = ambil ulang dari engine ProDev, hanya WO Draft), teks hitam lewat token DevLib (tanpa kelas lokal). Urutan BOP dan Priority dari form ini tertunda sampai PROCESS; Earliest start = lantai waktu engine. Kolom Start dan Stop editable: Start menggeser operasi lewat engine dampak (pending), Stop mengubah target per jam (Run h mengikuti). Live Schedule kini menu sendiri (production-live).
Dipakai oleh : Menu Planning > Work Order (v2:production-wo).
Status       : Aktif di mockup (ddt*). Tabel Cek ENGINE dihapus: engine bekerja di background.
--}}
{{-- Work Order v2 (PPIC): what scheduling knows about one Work Order. Created from Product Development data (layout, BOP, routing, BOM) by
     WoGenerationService; enriched by WoPlanningEnricher (process ids, machines that can run each step, target / waste / spoilage, setup);
     material and tooling gates by GateService. Data = App\Support\V2\Data\Ppic::workOrder(). The schedule itself is planned and confirmed on
     Production Scheduling; this page links to it and records output. --}}
@php
    use App\Support\V2\Data\Ppic;
    use App\Support\V2\Data\Sales;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $id = request()->integer('id');
    $d = $id ? Ppic::workOrder($id) : null;
    abort_if($id && ! $d, 404);
    $src = Src::of('tm2prd.prd_wo', 'tm2prd.prd_wo_operation', 'tm2prd.prd_wo_operation_machine', 'tm2prd.prd_wo_material', 'tm2mst.prod_process_machine');
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $d && ! request()->boolean('new'))
    {{-- 1. Grid of Work Orders; New leads to the picker (what is to be processed). --}}
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Prod\WorkOrderGrid]])
@elseif (! $d)
    {{-- 2. Picker: delivery plan lines of Confirmed Customer Orders that have no Work Order yet. Tick one or more, then Create. --}}
    @php
        $pending = Ppic::pendingDeliveries();
        $pickRows = array_map(fn ($p) => ['id' => $p['id'], 'co' => raw($p['co']), 'customer' => raw($p['customer']), 'product' => raw($p['product']), 'qty' => (float) $p['qty'],
            'ship' => $p['ship'] ? \Carbon\Carbon::parse($p['ship'])->toDateString() : null,
            'pd' => $p['complete'] ? t('Complete').' · '.$p['parts'].' '.t('parts') : t('Simple Work Order (no operations)')], $pending);
    @endphp
    @php $noCard = Ppic::linesWithoutCard(); @endphp
    <x-dl::card title="Create Work Order" description="Tick the lines to process, then Create Work Order. A Work Order is built from Product Development (BOP, routing, BOM); the form opens so you can check it, PROCESS it and APPLY TO LIVE SCHEDULE in Simulation.">
        @if ($pending)
            <x-dl::grid id="wo-pick" row-key="id" actions="none" :new-record="null" :source="$src" search="Search Customer Order, customer, product..."
                :columns="[
                    ['key' => 'co', 'label' => 'Customer Order', 'type' => 'doc', 'party' => 'customer'],
                    ['key' => 'product', 'label' => 'Product'],
                    ['key' => 'qty', 'label' => 'Quantity', 'type' => 'integer'],
                    ['key' => 'ship', 'label' => 'Ship Date', 'type' => 'date'],
                    ['key' => 'pd', 'label' => 'Product Development'],
                ]"
                :rows="$pickRows" :bulk="[['key' => 'create', 'label' => 'Create Work Order', 'icon' => 'fa-plus', 'quiet' => true]]" />
            @if ($noCard)
                <x-dl::alert tone="warn" :message="raw(tf(':n delivery line(s) are not offered: their product has no Master Card yet. Add the card in Master Card Collection.', ['n' => $noCard]))" />
            @endif
            <x-dl::action-form action="{{ route('v2.ppic.wo.create') }}" id="wo-pick-form"></x-dl::action-form>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="route('v2.screen', 'production-wo')" />
            </x-dl::action-bar>
            <script>
                document.addEventListener('dl:grid-bulk', (e) => {
                    if (e.detail.action !== 'create' || !e.detail.ids.length) return;
                    const f = document.getElementById('wo-pick-form');
                    f.querySelectorAll('input[name="delivery[]"]').forEach((i) => i.remove());
                    e.detail.ids.forEach((id) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = 'delivery[]'; i.value = id; f.appendChild(i); });
                    f.submit();
                });
            </script>
        @else
            <x-dl::alert tone="info" :message="$noCard ? raw(tf(':n delivery line(s) are not offered: their product has no Master Card yet. Add the card in Master Card Collection.', ['n' => $noCard])) : t('Every Confirmed delivery plan line already has a Work Order.')" />
            <x-dl::action-bar><x-dl::button variant="secondary" icon="back" label="Back" :href="route('v2.screen', 'production-wo')" /></x-dl::action-bar>
        @endif
    </x-dl::card>
@else
    @php
        $wo = $d['wo'];
        $ops = $d['ops'];
        $first = $d['first'];
        $ups = (int) ($wo->ups_per_sheet ?: 0);
        $blocked = collect($ops)->filter(fn ($o) => in_array('BLOCK', $o['gates'], true));
        $waiting = collect($ops)->filter(fn ($o) => in_array('WAIT', $o['gates'], true));
        $g = Ppic::gantt($ops);
        $back = route('v2.screen', 'prod-work-order');
        $gateText = ['READY' => 'Ready', 'WAIT' => 'Waiting', 'BLOCK' => 'Blocked'];
        $procName = collect($ops)->pluck('process', 'process_id')->filter()->mapWithKeys(fn ($n, $id) => [(string) $id => (string) $n])->all();   // BOM Process cells hold the process id
        $resettable = in_array($wo->wo_status, ['DRAFT', 'SIMULATED'], true);   // RESET only while the Work Order is a Draft
    @endphp
    <x-dl::card :title="raw('Work Order · '.$wo->wo_no)" :description="raw(($first['customer'] ?? '—').' · '.($first['product'] ?? '—').($wo->part_code ? ' · '.$wo->part_code : '').' · '.$wo->source_type.($wo->source_co_id ? ' CO#'.$wo->source_co_id : ''))">
        @php
            $estStart = collect($ops)->pluck('start')->filter()->min();
            $estFinish = collect($ops)->pluck('finish')->filter()->max();
            $late = $estFinish && $wo->due_date && $estFinish->greaterThan(\Carbon\CarbonImmutable::parse($wo->due_date));
            $editable = in_array($wo->wo_status, ['DRAFT', 'SIMULATED', 'SCHEDULED'], true) && collect($ops)->every(fn ($o) => $o['plan_status'] !== 'CONFIRMED' && in_array($o['task_status'], ['PENDING', 'ASSIGNED'], true));
            // The first screen stays short: empty facts are not shown.
            $facts = array_filter([
                'Quantity' => Locale::number($wo->qty, 0), 'Due Date' => Locale::dt($wo->due_date),
                'Est. Start' => $estStart ? Locale::dt($estStart) : null, 'Est. Finish' => $estFinish ? Locale::dt($estFinish) : null,
                'Against due date' => $estFinish ? ($late ? t('Late') : t('On time')) : null,
                'Plan' => collect($ops)->where('plan_status', 'CONFIRMED')->count().' / '.count($ops).' '.t('confirmed'),
                'Ups per sheet' => $ups ?: null, 'Press sheet (mm)' => $wo->sheet_length_mm ? Locale::decimal($wo->sheet_length_mm, 0).' x '.Locale::decimal($wo->sheet_width_mm, 0) : null,
                'Dieline' => $wo->dieline_ref ?: null,
            ], fn ($v) => $v !== null && $v !== '');
        @endphp
        @foreach (array_chunk($facts, 8, true) as $chunk)
            <x-dl::form.row :cols="8">
                @foreach ($chunk as $label => $value)
                    <x-dl::field.display :label="$label" :value="raw((string) $value)" :source="$src" />
                @endforeach
                @if ($loop->first)
                    @php
                        // Priority and BOP order picked on this form are pending until PROCESS; the select shows the pending choice.
                        $header = Ppic::pendingHeader($wo->wo_id);
                        $current = $wo->is_hot_job ? 'HOT' : ($wo->priority === 'URGENT' ? 'MOVE_UP' : 'NORMAL');
                        $prio = $header['priority']['kind'] ?? $current;
                    @endphp
                    <x-dl::action-form action="{{ route('v2.ppic.prioritize') }}" id="wo-prio" data-current="{{ $current }}">
                        <input type="hidden" name="wo_id" value="{{ $wo->wo_id }}">
                        <input type="hidden" name="wo_form" value="1">
                        <x-dl::field.select name="kind" label="Priority" :options="['NORMAL' => t('Normal'), 'MOVE_UP' => t('Move up'), 'HOT' => t('Hot job')]" :value="$prio" :hint="isset($header['priority']) ? t('Pending until PROCESS') : null" :source="Src::of('tm2prd.prd_wo.priority')" />
                        <x-dl::box id="wo-prio-reason" hidden>
                            <x-dl::field.text name="reason" label="Reason" :case-toggle="false" :source="Src::of('tm2prd.prd_plan_decision.reason')" />
                            <x-dl::button size="sm" icon="fa-flag" label="Save" type="submit" />
                        </x-dl::box>
                    </x-dl::action-form>
                    @if ($editable)
                        {{-- Earliest start: the engines never start an operation of this Work Order before it. Saved here, used by PROCESS. --}}
                        <x-dl::action-form action="{{ route('v2.ppic.wo.earliest', $wo->wo_id) }}">
                            <x-dl::field.date-time name="earliest_start" label="Earliest start" :value="$wo->earliest_start?->format('Y-m-d H:i')" hint="Empty = as soon as possible" :source="Src::of('tm2prd.prd_wo.earliest_start')" />
                            <x-dl::button size="sm" icon="fa-calendar-check" label="Save" type="submit" />
                        </x-dl::action-form>
                    @endif
                @endif
            </x-dl::form.row>
        @endforeach
        @if ($blocked->isNotEmpty())
            <x-dl::alert tone="danger" :message="raw(t('Blocked').': '.$blocked->pluck('gate_note')->filter()->unique()->implode(' · '))" />
        @elseif ($waiting->isNotEmpty())
            <x-dl::alert tone="warn" :message="raw(t('Waiting').': '.$waiting->pluck('gate_note')->filter()->unique()->implode(' · '))" />
        @endif
        @php
            $queue = array_values(array_filter((array) session('wo_queue', []), fn ($q) => (int) $q !== (int) $wo->wo_id));
            $scheduled = collect($ops)->where('plan_status', 'CONFIRMED')->count() + collect($ops)->whereIn('task_status', ['ASSIGNED', 'RUNNING', 'DONE'])->count() > 0;
            $draftOps = collect($ops)->where('plan_status', 'DRAFT')->count();
        @endphp
        @if ($scheduled && ! $draftOps)
            <x-dl::alert tone="success" message="This Work Order is on the main schedule." />
        @endif
        @php $mcardId = \App\Models\Master\ProdMasterCard::where('product_id', (int) $wo->product_id)->orderByDesc('id_master_card')->value('id_master_card'); @endphp
        @php ob_start(); @endphp
        @if ($editable)
            @php $topPs = Ppic::processState($wo->wo_id); @endphp
            @if ($topPs['needed'])
                <x-dl::action-form action="{{ route('v2.ppic.wo.process', $wo->wo_id) }}"><x-dl::button size="sm" variant="secondary" icon="fa-gears" :label="'PROCESS ('.$topPs['n'].')'" type="submit" /></x-dl::action-form>
            @else
                <x-dl::button size="sm" variant="secondary" icon="fa-gears" label="PROCESS" disabled />
            @endif
        @endif
        @if ($editable)
            <x-dl::button variant="danger" size="sm" icon="fa-trash" label="Delete" type="button" data-dl-open="wo-delete" />
        @else
            <x-dl::button variant="secondary" size="sm" icon="fa-trash" label="Delete" type="button" disabled title="A confirmed Work Order cannot be deleted." />
        @endif
        @if ($queue)
            <x-dl::button variant="secondary" size="sm" icon="fa-forward" label="Next Work Order" :href="route('v2.screen', 'production-wo').'?id='.$queue[0]" />
        @endif
        {{-- Secondary tools are hidden for now (Jo 2026-10-12); ?actions=all shows them again. --}}
        @if (request('actions') === 'all')
        <x-dl::button variant="secondary" size="sm" icon="fa-vial" label="Show on simulation Gantt" :href="route('v2.screen', 'production-wo').'?id='.$wo->wo_id.'&tab=sim'" />
        <x-dl::button variant="secondary" size="sm" icon="fa-chart-gantt" label="Open in Gantt" :href="route('v2.screen', 'production-scheduling').'?view=gantt&q='.urlencode($wo->wo_no)" />
        @if ($mcardId)
            <x-dl::button variant="secondary" size="sm" icon="fa-id-card" label="Open Master Card" :href="route('v2.screen', 'master-card-collection').'?id='.$mcardId" />
        @endif
        <x-dl::action-form action="{{ route('v2.ppic.wo.refresh', $wo->wo_id) }}"><x-dl::button variant="secondary" size="sm" icon="fa-rotate" label="Refresh from ProDev" type="submit" /></x-dl::action-form>
        @endif
        <x-dl::button variant="secondary" size="sm" icon="back" label="Back" :href="$back" />
        @php
            // Document status (Jo 2026-10-13): Draft until it is applied to the live schedule, then APPLIED; other states as they are.
            $docStatus = ['DRAFT' => 'DRAFT', 'SIMULATED' => 'DRAFT', 'SCHEDULED' => 'APPLIED'][$wo->wo_status] ?? str_replace('_', ' ', $wo->wo_status);
            \App\Support\V2\Crumb::status(\Illuminate\Support\Facades\Blade::render('<x-dl::badge :label="$l" :tone="$t" />', [
                'l' => 'Status document: '.$docStatus, 't' => $docStatus === 'DRAFT' ? 'neutral' : ($docStatus === 'APPLIED' ? 'success' : ($wo->wo_status === 'CANCELLED' ? 'danger' : 'info')),
            ]));
        @endphp
        @php \App\Support\V2\Crumb::actions(ob_get_clean()); @endphp
    </x-dl::card>

    @php
        $mcard = Ppic::masterCard($wo);
        $part = $d['part'];
    @endphp



    @if ($editable)
        <x-dl::modal id="wo-delete" title="Delete Work Order" size="md" icon="fa-trash" tone="danger">
            <x-dl::action-form action="{{ route('v2.ppic.wo.delete', $wo->wo_id) }}" id="wo-delete-form">
                <x-dl::box as="p">{{ t('Delete this Work Order from planning? It leaves the list and its line goes back to the picker. A reason is required.') }}</x-dl::box>
                <x-dl::field.text name="reason" label="Reason" required :case-toggle="false" :source="Src::of('tm2prd.prd_wo.cancel_reason')" />
            </x-dl::action-form>
            <x-slot:footer>
                <x-dl::button preset="cancel" data-dl-close-modal />
                <x-dl::button variant="danger" icon="fa-trash" label="Delete" type="submit" form="wo-delete-form" />
            </x-slot:footer>
        </x-dl::modal>
    @endif

    @php $woHtml = []; @endphp
        @php ob_start(); @endphp
        {{-- 70% dieline and 3D (ProDev) / 30% Master Card: the first tab, open by default --}}
    <x-dl::split aside="30%">
        <x-slot:side>
            <x-dl::card :description="raw($mcard ? $mcard['name'].' · '.$mcard['code'] : t('Read only.'))">
                @if ($mcard)
                    <x-dl::form.row :cols="2">
                <x-dl::field.display label="Customer" :value="raw($mcard['customer'] ?: '—')" :source="$src" />
                <x-dl::field.display label="Size L × W × H (mm)" :value="raw($mcard['dims'])" :source="$src" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                <x-dl::field.display label="Flat size (blank)" :value="raw($mcard['flat'].' · '.$mcard['area'])" :source="$src" />
                <x-dl::field.display label="Knife and ups" :value="raw($mcard['knife'])" :source="$src" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                <x-dl::field.display label="Flaps and allowances" :value="raw($mcard['flaps'])" :source="$src" />
                <x-dl::field.display label="Hole (front panel)" :value="raw($mcard['hole'])" :source="$src" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                <x-dl::field.display label="Material" :value="raw($mcard['materials'])" :source="$src" />
                <x-dl::field.display label="Colours" :value="raw($mcard['colours'])" :source="$src" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                <x-dl::field.display label="Finishing" :value="raw($mcard['finishing'])" :source="$src" />
                <x-dl::field.display label="Print size / pack" :value="raw($mcard['print'].' · '.$mcard['pack'])" :source="$src" />
            </x-dl::form.row>
            <x-dl::field.display label="Process" :value="raw($mcard['process'])" :source="$src" />
            @if ($mcard['missing'])
                        <x-dl::alert tone="warn" :message="raw(t('Missing upstream').': '.implode(', ', array_map(fn ($x) => t($x), $mcard['missing'])))" />
                    @endif
                @else
                    <x-dl::alert tone="warn" message="This product has no Master Card data upstream yet (Inquiry and ProDev)." />
                @endif
            </x-dl::card>
        </x-slot:side>
    <x-dl::card>
        @if ($part)
            <x-mp::sales.part-visual :part="$part" :types="Sales::dielineTypes()" height="300px" :readonly="true" />
        @else
            <x-dl::alert tone="info" message="No dieline or artwork picture for this product yet." />
        @endif
    </x-dl::card>
    </x-dl::split>
        @php $woHtml['card'] = new \Illuminate\Support\HtmlString(ob_get_clean()); @endphp
        @php ob_start(); @endphp
        @if ($editable && $ops)
            @php
                $bopRows = Ppic::bopSheet($wo->wo_id);
                // "Target source" only appears once somebody overrode a target (or with ?cols=all): it then marks which cells are overrides.
                $overridden = collect($bopRows)->contains(fn ($r) => str_starts_with((string) $r['source'], 'Override'));
                $bopExtra = request('cols') === 'all'
                    ? [['key' => 'source', 'label' => 'Target source', 'readonly' => true], ['key' => 'status', 'label' => 'Status', 'readonly' => true], ['key' => 'gates', 'label' => 'Gates', 'readonly' => true]]
                    : ($overridden ? [['key' => 'source', 'label' => 'Target source', 'readonly' => true]] : []);
            @endphp
            @if (isset(Ppic::pendingHeader($wo->wo_id)['order']))
                <x-dl::alert tone="warn" message="The operation order was changed. PROCESS applies it (Discard pending changes puts it back)." />
            @endif
            @if (Ppic::bopOrderChanged($wo->wo_id))
                <x-dl::alert tone="info" message="Operation order changed from the ProDev order for this Work Order only." />
            @endif
            <x-dl::lookup-grid :source="$src" row-key="id" :add-rows="true" add-label="ADD" delete-label="DELETE" reset-label="RESET" :reset-url="$resettable ? route('v2.ppic.wo.ops-reset', $wo->wo_id) : null" :searchable="false" :show-count="false" :delete-url="route('v2.ppic.wo.ops-delete', $wo->wo_id)" :save-url="route('v2.ppic.wo.target-cells', $wo->wo_id)" :auto-save="true" :reorder-url="route('v2.ppic.wo.reorder', $wo->wo_id)" :rows="$bopRows" :columns="array_merge([
                ['key' => 'process', 'label' => 'Process', 'type' => 'select', 'options' => Ppic::processOptions(), 'lockSaved' => true, 'required' => true, 'width' => '170px'],
                ['key' => 'machine', 'label' => 'Machine', 'type' => 'select', 'options' => Ppic::machineOptions(), 'optionsBy' => ['key' => 'process', 'map' => Ppic::machinesByProcess()], 'lockSaved' => true, 'width' => '230px'],
                ['key' => 'qty_in', 'label' => 'Qty in', 'readonly' => true, 'width' => '100px'],
                ['key' => 'qty_out', 'label' => 'Good out', 'readonly' => true, 'width' => '100px'],
                ['key' => 'target', 'label' => 'Target / hour', 'type' => 'number', 'decimals' => 0, 'width' => '120px'],
                ['key' => 'actor', 'label' => 'Crew / Subcon', 'type' => 'select', 'options' => Ppic::actorOptions(), 'width' => '240px'],
                ['key' => 'setup', 'label' => 'Setup (min)', 'readonly' => true, 'width' => '90px'],
                ['key' => 'run', 'label' => 'Run (h)', 'readonly' => true, 'width' => '70px'],
                ['key' => 'start', 'label' => 'Start', 'type' => 'datetime', 'width' => '170px'],
                ['key' => 'finish', 'label' => 'Stop', 'type' => 'datetime', 'width' => '170px'],
            ], $bopExtra)" />
        @else
            @php $actors = collect(Ppic::bopSheet($wo->wo_id))->pluck('actor', 'id'); @endphp
            <x-dl::table :source="$src" empty="This Work Order has no operations yet." :columns="['seq' => '#', 'process' => 'Process', 'machine' => 'Machine', 'qin' => 'Qty in', 'qout' => 'Good out', 'target' => 'Target / hour', 'waste' => 'Waste qty', 'spoil' => 'Spoilage qty',
            'actor' => 'Crew / Subcon', 'setup' => 'Setup (min)', 'run' => 'Run (h)', 'start' => 'Start', 'finish' => 'Stop', 'out' => 'Output'] + (request('cols') === 'all' ? ['status' => 'Status', 'gates' => 'Gates'] : [])"
            :rows="array_map(fn ($o) => ['seq' => $o['seq'], 'process' => raw($o['process']), 'machine' => raw($o['machine'] ?? 'SUBCON'),
                'qin' => raw(Locale::number($o['qty'], 0).' '.$o['uom']), 'qout' => raw(Locale::number($o['qty_out'], 0).' '.$o['uom']),
                'target' => raw($o['target'] ? Locale::number($o['target'], 0).'/h'.($ups && $o['uom'] === 'LBR' ? ' = '.Locale::number($o['target'] * $ups, 0).' pcs/h' : '') : '—'),
                'waste' => $o['waste'] !== null ? Locale::number($o['waste'], 0) : '—', 'spoil' => $o['spoilage'] !== null ? Locale::number($o['spoilage'], 0) : '—',
                'actor' => raw(($actors[$o['op_id']] ?? '') !== '' ? Ppic::actorOptions()[$actors[$o['op_id']]] ?? '—' : '—'),
                'setup' => $o['setup'] ?: '—', 'run' => $o['run'] !== null ? Locale::decimal($o['run'] / 60, 2) : '—',
                'start' => $o['start'] ? raw(Locale::dt($o['start'])) : '—', 'finish' => $o['finish'] ? raw(Locale::dt($o['finish'])) : '—',
                'status' => raw($o['plan_status'] === 'DRAFT' ? 'Draft' : $o['task_status']), 'gates' => raw(implode(' / ', array_map(fn ($x) => $gateText[$x] ?? $x, $o['gates']))),
                'out' => raw($o['progress'].'% · '.Locale::number($o['good'], 0))], $ops)" />
        @endif
        @php $woHtml['bop'] = new \Illuminate\Support\HtmlString(ob_get_clean()); @endphp
        @php ob_start(); @endphp
        @php $sim = Ppic::simulation($wo->wo_id); @endphp
            {{-- PROCESS runs the whole chain (material lines, targets and setups, gates, schedule into the Draft) once; it is lit only while something changed
                 or drags are pending. APPLY TO LIVE SCHEDULE (the only way to confirm) waits for PROCESS and fixes the whole Work Order. --}}
            @php $ps = Ppic::processState($wo->wo_id); @endphp
            <x-dl::action-bar>
                @if ($editable && $ps['pending'] > 0)
                    <x-dl::action-form action="{{ route('v2.ppic.pending.discard', ['wo' => $wo->wo_id]) }}"><x-dl::button variant="link" label="Discard pending changes" type="submit" /></x-dl::action-form>
                @endif
                @if ($editable && ! $ps['needed'])
                    <x-dl::action-form action="{{ route('v2.ppic.wo.apply', $wo->wo_id) }}"><x-dl::button icon="fa-check" label="APPLY TO LIVE SCHEDULE" type="submit" /></x-dl::action-form>
                @elseif ($editable)
                    <x-dl::button icon="fa-check" label="APPLY TO LIVE SCHEDULE" disabled />
                @else
                    <x-dl::button icon="fa-lock" label="APPLIED" disabled />
                @endif
            </x-dl::action-bar>
            <x-dl::gantt :mode-switch="false" :extras="request()->boolean('extras')" :linked="$sim['linked']" :rows="$sim['rows']" :tasks="$sim['tasks']" :categories="$sim['categories']" :origin-date="$sim['originDate']" :start="$sim['start']" :end="$sim['end']" :px-per-hour="$sim['pxPerHour']"
                :snap="$sim['snap']" :now-at="$sim['nowAt']" :lock-until="$sim['lockUntil']" :submit-label="null"
                impact-url="{{ route('v2.ppic.impact') }}" move-url="{{ route('v2.ppic.queue-move') }}" move-label="Add to pending changes" bulk-label="APPLY ALL WOs to LIVE SCHEDULE" bulk-url="{{ $ps['needed'] ? '' : route('v2.ppic.apply-all') }}" :reason-required="true" resource-label="Machine" :source="$src" />
        @php $woHtml['sim'] = new \Illuminate\Support\HtmlString(ob_get_clean()); @endphp
        @php ob_start(); @endphp
    <x-dl::card description="Material lines from Product Development. Readiness comes from Material Planning (stock, booking, incoming purchases).">
        @php $subs = \App\Models\Prd\PrdWoMaterialSubstitution::where('wo_id', $wo->wo_id)->where('is_active', true)->get(); @endphp
        @if ($subs->isNotEmpty())
            <x-dl::alert tone="info" :message="raw(t('Substituted material').': '.$subs->pluck('substitute_rawmat_name')->filter()->implode(' · ').' ('.$subs->pluck('approval_status')->unique()->implode(', ').')')" />
        @endif
        @php $bomRows = Ppic::bomSheet($wo->wo_id); @endphp
        @if ($editable && $bomRows)
            <x-dl::lookup-grid :source="$src" row-key="id" :add-rows="true" add-label="ADD" delete-label="DELETE" reset-label="RESET" :reset-url="$resettable ? route('v2.ppic.wo.materials-reset', $wo->wo_id) : null" :searchable="false" :show-count="false" :delete-url="route('v2.ppic.wo.materials-delete', $wo->wo_id)" :save-url="route('v2.ppic.wo.bom-cells', $wo->wo_id)" :rows="$bomRows"
                :auto-save="true"
                :links="[['when' => ['required', 'release'], 'set' => 'purchase', 'a' => 'required', 'op' => '-', 'b' => 'release', 'min' => 0], ['when' => ['purchase'], 'set' => 'release', 'a' => 'required', 'op' => '-', 'b' => 'purchase', 'min' => 0]]"
                :columns="[
                ['key' => 'process', 'label' => 'Process', 'type' => 'select', 'options' => collect($ops)->pluck('process', 'process_id')->filter()->mapWithKeys(fn ($n, $id) => [(string) $id => (string) $n])->all(), 'lockSaved' => true, 'lockFilled' => true, 'emptyText' => 'Process not set', 'width' => '170px'],
                ['key' => 'material', 'label' => 'Material', 'lockSaved' => true, 'required' => true, 'width' => '320px'],
                ['key' => 'role', 'label' => 'Role', 'type' => 'select', 'options' => array_combine(Ppic::materialRoles(), Ppic::materialRoles()), 'lockSaved' => true, 'required' => true, 'width' => '150px'],
                ['key' => 'required', 'label' => 'Required', 'type' => 'number', 'decimals' => 2, 'width' => '110px'],
                ['key' => 'uom', 'label' => 'Unit', 'readonly' => true, 'width' => '60px'],
                ['key' => 'onhand', 'label' => 'On hand', 'readonly' => true, 'width' => '90px'],
                ['key' => 'available', 'label' => 'Available', 'readonly' => true, 'width' => '90px'],
                ['key' => 'release', 'label' => 'Release', 'type' => 'number', 'decimals' => 2, 'width' => '110px'],
                ['key' => 'purchase', 'label' => 'Purchase', 'type' => 'number', 'decimals' => 2, 'width' => '110px'],
            ]" />
        @else
            <x-dl::table :columns="['process' => 'Process', 'material' => 'Material', 'role' => 'Role', 'required' => 'Required', 'uom' => 'Unit', 'onhand' => 'On hand', 'available' => 'Available', 'release' => 'Release', 'purchase' => 'Purchase']" empty="No material lines." :source="$src"
                :rows="array_map(fn ($m) => ['process' => raw($procName[$m['process']] ?? ($m['process'] === '' ? t('Process not set') : $m['process'])), 'material' => raw($m['material']), 'role' => raw($m['role']), 'required' => Locale::decimal($m['required'], 2), 'uom' => raw($m['uom']),
                    'onhand' => $m['onhand'], 'available' => $m['available'], 'release' => Locale::decimal($m['release'], 2), 'purchase' => Locale::decimal($m['purchase'], 2)], $bomRows)" />
        @endif
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="fa-boxes-stacked" label="Open Material Planning" :href="route('v2.screen', 'material-planning')" />
            <x-dl::button variant="secondary" icon="fa-right-left" label="Substitute material" :href="route('production.wo.entry', $wo->wo_id)" />
        </x-dl::action-bar>
    </x-dl::card>
        @php $woHtml['bom'] = new \Illuminate\Support\HtmlString(ob_get_clean()); @endphp
    @php $queue = Ppic::queue($wo->wo_id); ob_start(); @endphp
        <x-dl::queue-board :processes="$queue['processes']" :machines="$queue['machines']" :rows="$queue['rows']" :process="$queue['process']" :machine="$queue['machine']" empty="Nothing is queued on this machine." />
    @php $woHtml['que'] = new \Illuminate\Support\HtmlString(ob_get_clean()); @endphp
    @php
        $queueNow = array_values(array_filter($queue['rows'], fn ($r) => $r['process'] === $queue['process'] && ($queue['machine'] === '' || $r['machine'] === $queue['machine'])));
        $secOrder = ['card', 'bop', 'bom', 'sim', 'que'];
        $openKeys = request()->filled('open') ? array_values(array_intersect($secOrder, explode(',', (string) request('open'))))
            : (request()->filled('tab') ? array_values(array_intersect($secOrder, [request('tab')])) : null);
        $usesMemory = $openKeys === null;
        $openKeys ??= ['card', 'bop'];
        $when = fn ($d) => $d ? Locale::dt($d) : '—';
        $firstStart = collect($ops)->pluck('start')->filter()->sort()->first();
        $lastFinish = collect($ops)->pluck('finish')->filter()->sort()->last();
        $gateNow = collect($ops)->pluck('gates')->map(fn ($g) => $g[0] ?? 'READY')->contains('BLOCK') ? 'BLOCK' : (collect($ops)->pluck('gates')->map(fn ($g) => $g[0] ?? 'READY')->contains('WAIT') ? 'WAIT' : 'READY');
        $buyTotal = round(collect($bomRows ?? [])->sum('purchase'), 2);
        $secSummary = [
            'card' => $mcard ? $mcard['name'].' · '.$mcard['dims'].(str_contains((string) $mcard['knife'], 'not generated') ? ' · '.t('Layout not generated yet') : '').($mcard['missing'] ? ' · '.t('Missing upstream').': '.implode(', ', array_map(fn ($x) => t($x), $mcard['missing'])) : '') : t('No Master Card data upstream yet'),
            'bop' => count($ops).' '.t('operations').($firstStart ? ' · '.$when($firstStart).' → '.$when($lastFinish) : ''),
            'bom' => count($bomRows ?? []).' '.t('materials').' · '.t('Purchase').' '.Locale::decimal($buyTotal, 2),
            'sim' => ! empty($sim['shown']['n']) ? $sim['shown']['n'].' '.$sim['shown']['process'].' '.t('operations on').' '.$sim['shown']['machine'].': '.$sim['shown']['draft'].' '.t('Draft').', '.$sim['shown']['live'].' '.t('Live').' · '.(int) ($sim['clashes'] ?? 0).' '.t('clash') : t('No operations on the board'),
            'que' => $queueNow ? (($queue['processes'][$queue['process']] ?? '').' · '.($queue['machines'][$queue['process']][$queue['machine']] ?? t('All machines')).' · '.count($queueNow).' '.t('in queue').' · '.count(array_filter($queueNow, fn ($r) => $r['status'] === 'Running')).' '.t('running')) : t('Nothing is queued'),
        ];
        $secTitle = ['card' => 'Master Card', 'bop' => 'Bill of Process', 'bom' => 'Bill of Material', 'sim' => 'Simulation', 'que' => 'QUE every process every machine'];
        $secIcon = ['card' => 'fa-id-card', 'bop' => 'fa-list-ol', 'bom' => 'fa-boxes-stacked', 'sim' => 'fa-vial', 'que' => 'fa-list-check'];
    @endphp
    @if (request('layout') === 'tabs')
        <x-dl::tabs class="wo-tabs" :tabs="$secTitle" :active="request('tab', 'card')">
            <x-slot:card>{!! $woHtml['card'] !!}</x-slot:card>
            <x-slot:bop>{!! $woHtml['bop'] !!}</x-slot:bop>
            <x-slot:bom>{!! $woHtml['bom'] !!}</x-slot:bom>
            <x-slot:sim>{!! $woHtml['sim'] !!}</x-slot:sim>
            <x-slot:que>{!! $woHtml['que'] !!}</x-slot:que>
        </x-dl::tabs>
    @else
        <x-dl::accordion-group :multiple="true" :remember="$usesMemory ? 'wo-form' : null">
            @foreach ($secOrder as $k)
                <x-dl::accordion :key="$k" :icon="$secIcon[$k]" :title="$secTitle[$k]" :summary="raw($secSummary[$k])" :open="in_array($k, $openKeys, true)"
                    :badge="$k === 'bom' ? ($gateNow === 'BLOCK' ? 'Blocked' : ($gateNow === 'WAIT' ? 'Waiting' : 'Ready')) : null" :tone="$gateNow === 'BLOCK' ? 'danger' : ($gateNow === 'WAIT' ? 'warn' : 'ok')"
                    :max-height="$k === 'card' ? null : '640px'">
                    {!! $woHtml[$k] !!}
                </x-dl::accordion>
            @endforeach
        </x-dl::accordion-group>
    @endif

    <script>
        (() => {
            const f = document.getElementById('wo-prio');
            if (f) {
                const box = document.getElementById('wo-prio-reason');
                f.addEventListener('change', (e) => { if (e.target.name === 'kind') box.hidden = e.target.value === f.dataset.current; });
            }
            document.querySelectorAll('.dl-tabs__tab').forEach((b) => b.addEventListener('click', () => {
                const u = new URL(location.href); u.searchParams.set('tab', b.dataset.tab); history.replaceState(null, '', u);
            }));
        })();
    </script>







    {{-- Hidden for now (Jo 2026-10-12); ?machines=1 shows it again. --}}
    @if (request()->boolean('machines'))
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
    @endif


    @php
        $matNames = $d['materials']->pluck('material_name')->filter()->map(fn ($n) => (string) $n)->all();
        $coming = array_values(array_filter(Ppic::incoming(), fn ($i) => in_array($i['material'], $matNames, true)));
    @endphp
    @if ($coming)
        <x-dl::card title="Incoming purchases for this Work Order" description="Open PO / PR lines of its materials against the date the first operation needs them.">
            <x-dl::table :source="$src" :columns="['type' => 'Type', 'doc' => 'Document', 'material' => 'Material', 'qty' => 'Quantity', 'arrives' => 'Arrives', 'needed' => 'Needed', 'effect' => 'Effect']"
                :rows="array_map(fn ($i) => ['type' => raw($i['type']), 'doc' => raw($i['doc']), 'material' => raw($i['material']), 'qty' => raw(Locale::number($i['qty'], 0)), 'arrives' => raw($i['arrives'] ?? '—'), 'needed' => raw($i['needed'] ?? '—'), 'effect' => raw($i['effect'])], $coming)" />
        </x-dl::card>
    @endif
@endif
