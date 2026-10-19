{{--
 REMARK
 Tujuan       : Form Master Machine v2 (menggantikan halaman v1 pages::master.machine): tab Machine identity, Technical specifications, Capacity and performance, Cost and assets, Maintenance and OEE (jadwal perawatan, OEE aktual 7 / 30 / 90 hari hanya-baca, log downtime dalam satu tabel), Processes (matriks proses yang bisa dikerjakan, disimpan bersama Save, nilai efektif hanya-baca, di mana mesin dipakai), Deactivate / Activate, tab History.
 Sumber hulu  : tm2mst.prod_machine, prod_machine_model, prod_machine_downtime, prod_process_machine, prod_process, org_division, org_department; aturan di App\Services\Production\MachineMasterService dan ProcessMachineMatrixService; OEE dari MachineOeeService; di mana dipakai dari MachineCapabilityService.
 Input        : Query id (mesin) atau new=1; session v2_flash dan errors; old input.
 Output       : POST v2.prod.machine.save (Save / Activate / Deactivate).
 Dipakai oleh : Dibuka dari grid prod-machine (Open dan + New Record).
 Status       : Aktif (Master T6b). DATA REAL: angka kosong tetap kosong (bukan 0); pasangan proses tidak dihapus, dinonaktifkan lewat kolom Active; stop mesin yang ada terkunci kecuali End.
--}}
@php
    use App\Models\Master\ProdMachine;
    use App\Models\Master\ProdMachineDowntime;
    use App\Services\Production\MachineMasterService as Mm;
    use App\Services\Production\ProcessMachineMatrixService as Mx;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'prod-machine');
    $svc = app(Mm::class);
    $mx = app(Mx::class);
    $m = request('id') ? ProdMachine::find((int) request('id')) : null;
    abort_if(request('id') && ! $m, 404);
    abort_unless($m || request('new'), 404);
    $new = ! $m;
    $m ??= new ProdMachine(['activate' => true, 'machine_status' => 'READY', 'is_process_machine' => true, 'perfecting' => false]);
    $s = fn (string $col) => Src::of('tm2mst.prod_machine.'.$col);
    $v = fn (string $col) => old($col, $m->{$col});
    $n = fn ($x) => $x === null || $x === '' ? '' : (str_contains((string) $x, '.') ? (rtrim(rtrim((string) $x, '0'), '.') ?: '0') : (string) $x);
    $nv = fn (string $col) => old($col, $n($m->{$col}));
    $dv = fn (string $col) => old($col, $m->{$col}?->format('Y-m-d'));
    $types = array_combine(Mm::TYPES, Mm::TYPES);

    $oldProc = old('processes');
    $procRows = is_array($oldProc) ? array_values($oldProc) : ($new ? [] : $mx->rows('machine', (int) $m->machine_id));
    $procRows = array_map(fn ($r) => $r + ['other_id_label' => '', 'is_new' => empty($r['capability_id'])], $procRows);
    $oldDt = old('downtimes');
    $dtRows = is_array($oldDt) ? array_values($oldDt) : ($new ? [] : $svc->downtimeRows($m));
    $dtRows = array_map(fn ($r) => $r + ['is_new' => empty($r['downtime_id']), 'can_end' => empty($r['downtime_id']) || ($r['end_date'] ?? '') === '', 'nature' => '', 'hours' => ''], $dtRows);
    $eff = $new ? [] : $mx->effective('machine', (int) $m->machine_id);
    $procCount = $new ? 0 : $svc->processCount($m);
    $used = $new ? [] : $svc->whereUsed($m);
    $f = fn (string $key, string $label, string $type = 'number', string $w = '90px', array $more = []) => ['key' => $key, 'label' => $label, 'type' => $type, 'width' => $w] + $more;
    $procColumns = [
        ['key' => 'other_id', 'label' => 'Process', 'type' => 'lookup', 'lookup' => 'prod_process_machine_side', 'width' => '230px'],
        $f('primary', 'Primary', 'bool', '80px'),
        $f('is_certified', 'Certified', 'bool', '90px'),
        $f('setup_minutes', 'Setup (min)'),
        $f('speed_per_hour', 'Speed / hour', 'number', '100px'),
        $f('waste_pct', 'Waste %'),
        $f('waste_fixed_qty', 'Fixed waste qty', 'number', '100px'),
        $f('hourly_rate', 'Rate / hour (IDR)', 'number', '110px'),
        $f('manpower', 'Crew'),
        $f('gsm_min', 'GSM min'),
        $f('gsm_max', 'GSM max'),
        $f('activate', 'Active', 'bool', '80px'),
        $f('note', 'Note', 'text', '200px'),
        ['key' => 'is_new', 'label' => 'New pair', 'type' => 'bool', 'more' => true],
        ['key' => 'capability_id', 'label' => 'Pair id', 'type' => 'display', 'more' => true],
    ];
    $kinds = collect(ProdMachineDowntime::KINDS)->map(fn ($k) => t($k['label']))->all();
    $dtColumns = [
        ['key' => 'kind', 'label' => 'Type', 'type' => 'select', 'options' => $kinds, 'width' => '190px', 'default' => 'BREAKDOWN'],
        $f('start_date', 'Start date', 'date', '140px'),
        $f('start_time', 'Start time', 'text', '90px', ['placeholder' => 'HH:MM', 'readonlyUnless' => 'is_new']),
        $f('end_date', 'End date', 'date', '140px'),
        $f('end_time', 'End time', 'text', '90px', ['placeholder' => 'HH:MM', 'readonlyUnless' => 'can_end']),
        $f('cause', 'Cause', 'text', '220px', ['placeholder' => 'e.g. ink roller jammed', 'readonlyUnless' => 'is_new']),
        $f('nature', 'Nature', 'display', '100px'),
        $f('hours', 'Duration', 'display', '90px'),
        ['key' => 'downtime_id', 'label' => 'Stop id', 'type' => 'display', 'more' => true],
        ['key' => 'is_new', 'label' => 'New stop', 'type' => 'bool', 'more' => true],
        ['key' => 'can_end', 'label' => 'Can end', 'type' => 'bool', 'more' => true],
    ];
    $effRows = array_map(fn ($e) => ['process' => $e['process'], 'speed' => $e['speed'] === null ? t('none yet') : Locale::number((float) $e['speed'], 0), 'source' => t(['CAPABILITY' => 'Pair', 'PROCESS' => 'Process default', 'MACHINE' => 'Machine', 'MISSING' => 'Missing'][$e['speed_source']] ?? $e['speed_source']),
        'setup' => $e['setup'] === null ? t('none yet') : (string) $e['setup'], 'waste' => $e['waste'] === null ? '—' : $n($e['waste']), 'hours' => $e['hours_10k'] === null ? t('cannot be calculated') : Locale::number((float) $e['hours_10k'], 2)], $eff);

    $oee = [];
    if (! $new) {
        foreach ([7, 30, 90] as $days) {
            $oee[$days] = $svc->oee($m, $days);
        }
    }
    $pct = fn ($x) => $x === null ? t('n/a') : Locale::number((float) $x, 1).' %';
    $hrs = fn ($x) => $x === null ? '—' : Locale::number((float) $x, 2).' h';
    $oeeRows = [];
    if ($oee) {
        foreach (['availability' => 'Availability', 'performance' => 'Performance', 'quality' => 'Quality'] as $k => $label) {
            $oeeRows[] = ['metric' => $label] + collect($oee)->mapWithKeys(fn ($o, $d) => ['d'.$d => $pct($o['oee']['factors'][$k]['value'])])->all();
        }
        $oeeRows[] = ['metric' => 'OEE total'.($m->oee_target_pct !== null ? ' (target '.$n($m->oee_target_pct).' %)' : '')] + collect($oee)->mapWithKeys(fn ($o, $d) => ['d'.$d => $pct($o['oee']['oee'])])->all();
        $oeeRows[] = ['metric' => 'Unplanned stops'] + collect($oee)->mapWithKeys(fn ($o, $d) => ['d'.$d => $hrs($o['oee']['downtime']['unplanned'] / 60)])->all();
        $oeeRows[] = ['metric' => 'Planned stops'] + collect($oee)->mapWithKeys(fn ($o, $d) => ['d'.$d => $hrs($o['oee']['downtime']['planned'] / 60)])->all();
        $oeeRows[] = ['metric' => 'Actual MTTR'] + collect($oee)->mapWithKeys(fn ($o, $d) => ['d'.$d => $hrs($o['reliability']['mttr_hours'])])->all();
        $oeeRows[] = ['metric' => 'Actual MTBF'.($m->mtbf_hours !== null ? ' (standard '.$m->mtbf_hours.' h)' : '')] + collect($oee)->mapWithKeys(fn ($o, $d) => ['d'.$d => $hrs($o['reliability']['mtbf_hours'])])->all();
    }
    $oeeWhy = $oee ? collect($oee[30]['oee']['factors'])->filter(fn ($x) => $x['value'] === null)->map(fn ($x, $k) => ucfirst($k).': '.$x['reason'])->values()->all() : [];
    $next = $m->next_maintenance_date;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="prod-machine-form" :title="$new ? 'New Machine' : raw($m->machine_code)" :subtitle="$new ? null : raw($m->machine_name)"
    panel="top" kind="master" :status="$new ? 'new' : ($m->activate ? 'active' : 'inactive')"
    :action="route('v2.prod.machine.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'"
    :hidden="$new ? [] : ['id' => $m->machine_id]"
    :tabs="['main' => t('Machine identity'), 'specs' => t('Technical specifications'), 'capacity' => t('Capacity and performance'), 'finance' => t('Cost and assets'), 'maintenance' => t('Maintenance and OEE'), 'processes' => t('Processes')]"
    :log="$new ? [] : $svc->history($m)"
    :created-by="$m->created_by" :created-at="$m->created_at?->toDateTimeString()" :updated-by="$m->updated_by" :updated-at="$m->updated_at?->toDateTimeString()">
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Machine code" :value="$new ? t('(on Save)') : raw($m->machine_code)" />
        <x-dl::strip-kv kind="grand" label="Condition" :value="$new ? '—' : t(ProdMachine::STATUSES[$m->machine_status] ?? $m->machine_status)" />
        <x-dl::strip-kv kind="grand" label="Processes it can run" :value="$new ? '—' : ($m->is_process_machine ? Locale::number((float) $procCount, 0) : t('Support equipment'))" />
        <x-dl::strip-kv kind="grand" label="Next maintenance" :value="$next ? Locale::date($next) : t('none yet')" />
    </x-slot:strip>

    <x-slot:main>
        <x-dl::card title="Machine identity">
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="machine_code" label="Machine code" required placeholder="e.g. OFF-01" hint="Type prefix + sequence number." :value="$v('machine_code')" :source="$s('machine_code')" />
                <x-dl::field.select name="machine_status" label="Condition status" :options="array_map('t', ProdMachine::STATUSES)" :value="$v('machine_status')" :source="$s('machine_status')" />
                <x-dl::field.select name="criticality" label="Criticality" placeholder="— not rated yet —" :options="array_map('t', ProdMachine::CRITICALITY)" :value="$v('criticality')" :source="$s('criticality')" />
            </x-dl::form.row>
            <x-dl::hint note>Condition changes daily (breakdown, maintenance); Deactivate is only for a machine that is really released. A broken machine stays active so its routing and work order history stays intact.</x-dl::hint>
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="machine_name" label="Machine name and model" required placeholder="e.g. Heidelberg Speedmaster CD 102" :value="$v('machine_name')" :source="$s('machine_name')" />
                <x-dl::field.text name="nickname" label="Nickname (everyday name)" placeholder="e.g. Plong 2, Daxiang" :value="$v('nickname')" :source="$s('nickname')" />
                <x-dl::field.lookup name="machine_model_id" lookup="prod_machine_model" label="Market model (catalogue)" placeholder="Search model..." hint="Picking a model fills sheet size, speed and power; change them if the machine differs." :value="$v('machine_model_id')" :source="$s('machine_model_id')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="machine_type" label="Type classification" required placeholder="— select type —" :options="$types" :value="$v('machine_type')" :source="$s('machine_type')" />
                <x-dl::field.text name="brand_model" label="Brand / manufacturer" placeholder="e.g. Heidelberg" :value="$v('brand_model')" :source="$s('brand_model')" />
                <x-dl::field.text name="serial_no" label="Serial number" :value="$v('serial_no')" :source="$s('serial_no')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="division_id" label="Production division" required placeholder="— select division —" :options="$svc->divisionOptions()" :value="$v('division_id')" :source="$s('division_id')" />
                <x-dl::field.select name="department_id" label="Managing department" placeholder="— select department —" :options="$svc->departmentOptions()" :value="$v('department_id')" :source="$s('department_id')" />
                <x-dl::field.text name="plant_code" label="Plant / factory code" placeholder="e.g. P1" :value="$v('plant_code')" :source="$s('plant_code')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.number name="year_acquired" label="Year made / installed" placeholder="e.g. 2018" :value="$nv('year_acquired')" :source="$s('year_acquired')" />
                <x-dl::field.date name="acquired_date" label="Arrival date" hint="Leave empty if only the year is known." :value="$dv('acquired_date')" :source="$s('acquired_date')" />
                <x-dl::field.text name="location_note" label="Location on the shop floor" placeholder="e.g. Block B, near the plate warehouse" :value="$v('location_note')" :source="$s('location_note')" />
            </x-dl::form.row>
            <x-dl::field.checkbox name="is_process_machine" label="Production process machine (untick for support equipment such as a compressor or forklift: it will not appear in the routing picker)" :value="(bool) $v('is_process_machine')" :source="$s('is_process_machine')" />
            <x-dl::field.text name="notes" label="Notes" multiline :value="$v('notes')" :source="$s('notes')" />
        </x-dl::card>
    </x-slot:main>

    <x-slot:specs>
        <x-dl::card title="Media handling: sheet size">
            <x-dl::hint note>Enter in millimetres. These numbers are what the imposition engine and the "which machine fits this plano" filter use; the short text below is for display only.</x-dl::hint>
            <x-dl::form.row :cols="4">
                <x-dl::field.number name="max_sheet_length_mm" label="Max length (mm)" placeholder="1030" :value="$nv('max_sheet_length_mm')" :source="$s('max_sheet_length_mm')" />
                <x-dl::field.number name="max_sheet_width_mm" label="Max width (mm)" placeholder="720" :value="$nv('max_sheet_width_mm')" :source="$s('max_sheet_width_mm')" />
                <x-dl::field.number name="min_sheet_length_mm" label="Min length (mm)" placeholder="340" :value="$nv('min_sheet_length_mm')" :source="$s('min_sheet_length_mm')" />
                <x-dl::field.number name="min_sheet_width_mm" label="Min width (mm)" placeholder="480" :value="$nv('min_sheet_width_mm')" :source="$s('min_sheet_width_mm')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.number name="gsm_min" label="Min grammage (gsm)" :value="$nv('gsm_min')" :source="$s('gsm_min')" />
                <x-dl::field.number name="gsm_max" label="Max grammage (gsm)" :value="$nv('gsm_max')" :source="$s('gsm_max')" />
                <x-dl::field.text name="max_paper_size" label="Short size (text)" placeholder="103x72" hint="Display only." :value="$v('max_paper_size')" :source="$s('max_paper_size')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="Effective print area">
            <x-dl::hint note>The print area is smaller than the sheet because the gripper edge cannot be printed. Without it a layout can pass validation but hit the gripper on the machine.</x-dl::hint>
            <x-dl::form.row :cols="3">
                <x-dl::field.number name="max_print_length_mm" label="Print area length (mm)" :value="$nv('max_print_length_mm')" :source="$s('max_print_length_mm')" />
                <x-dl::field.number name="max_print_width_mm" label="Print area width (mm)" :value="$nv('max_print_width_mm')" :source="$s('max_print_width_mm')" />
                <x-dl::field.number name="gripper_margin_mm" label="Gripper margin (mm)" placeholder="e.g. 10" :value="$nv('gripper_margin_mm')" :source="$s('gripper_margin_mm')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="Print and coating configuration">
            <x-dl::form.row :cols="3">
                <x-dl::field.number name="color_count" label="Number of colour units" placeholder="4" hint="0 or empty for finishing machines." :value="$nv('color_count')" :source="$s('color_count')" />
                <x-dl::field.number name="max_colors_per_pass" label="Max colours per pass" :value="$nv('max_colors_per_pass')" :source="$s('max_colors_per_pass')" />
                <x-dl::field.select name="drying_system" label="Drying system" placeholder="— select —" :options="Mm::DRYING" :value="$v('drying_system')" :source="$s('drying_system')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="plate_size" label="Plate size" placeholder="e.g. 790x1030 mm" :value="$v('plate_size')" :source="$s('plate_size')" />
                <x-dl::field.text name="substrate_types" label="Runnable material types" placeholder="ART, DUPLEX, KRAFT" hint="Separate with commas." :value="$v('substrate_types')" :source="$s('substrate_types')" />
                <x-dl::field.checkbox name="perfecting" label="Perfecting: can print both sides in 1 pass (affects passes and time in scheduling)" :value="(bool) $v('perfecting')" :source="$s('perfecting')" />
            </x-dl::form.row>
        </x-dl::card>
    </x-slot:specs>

    <x-slot:capacity>
        <x-dl::card title="Speed: three numbers with different meanings">
            <x-dl::hint note>Manufacturer maximum is the brochure claim. Actual target is what is really achieved on the shop floor; scheduling and the OEE basis use it. Do not enter the same figure in both if reality is different. An empty number stays empty.</x-dl::hint>
            <x-dl::form.row :cols="3">
                <x-dl::field.number name="max_speed_per_hour" label="Manufacturer max speed (SPH)" placeholder="15000" :value="$nv('max_speed_per_hour')" :source="$s('max_speed_per_hour')" />
                <x-dl::field.number name="speed_target_per_hour" label="Actual speed target (SPH)" placeholder="12000" :value="$nv('speed_target_per_hour')" :source="$s('speed_target_per_hour')" />
                <x-dl::field.text name="capacity_per_shift" label="Capacity per shift (text)" :value="$v('capacity_per_shift')" :source="$s('capacity_per_shift')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="Machine calendar: basis of capacity and OEE">
            <x-dl::hint note>Without shifts and hours per shift the available hours are unknown and Availability OEE cannot be calculated (the system will not assume 24/7).</x-dl::hint>
            <x-dl::form.row :cols="4">
                <x-dl::field.number name="shifts_per_day" label="Shifts / day" placeholder="2" :value="$nv('shifts_per_day')" :source="$s('shifts_per_day')" />
                <x-dl::field.number name="hours_per_shift" label="Hours / shift" placeholder="8" :value="$nv('hours_per_shift')" :source="$s('hours_per_shift')" />
                <x-dl::field.number name="oee_target_pct" label="Target OEE (%)" placeholder="82" :value="$nv('oee_target_pct')" :source="$s('oee_target_pct')" />
                <x-dl::field.number name="mtbf_hours" label="Standard MTBF (hours)" placeholder="500" hint="From the machine manual." :value="$nv('mtbf_hours')" :source="$s('mtbf_hours')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="Crew requirement">
            <x-dl::hint note>Operators and helpers are separate because their wage rates differ; Payroll needs the breakdown, not just the total.</x-dl::hint>
            <x-dl::form.row :cols="3">
                <x-dl::field.number name="operator_min" label="Operator (people)" placeholder="1" :value="$nv('operator_min')" :source="$s('operator_min')" />
                <x-dl::field.number name="helper_min" label="Helper (people)" placeholder="2" :value="$nv('helper_min')" :source="$s('helper_min')" />
            </x-dl::form.row>
        </x-dl::card>
    </x-slot:capacity>

    <x-slot:finance>
        <x-dl::card title="Costing and accounting allocation">
            <x-dl::form.row :cols="3">
                <x-dl::field.number name="hourly_rate" label="Machine rate / hour (IDR)" placeholder="250000" hint="Used by the estimator for COGS (HPP)." :value="$nv('hourly_rate')" :source="$s('hourly_rate')" />
                <x-dl::field.number name="power_kw" label="Power consumption (kW)" placeholder="75" :value="$nv('power_kw')" :source="$s('power_kw')" />
                <x-dl::field.text name="cost_center_code" label="Cost center / GL code" placeholder="CT-001" :value="$v('cost_center_code')" :source="$s('cost_center_code')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="Asset register">
            <x-dl::form.row :cols="3">
                <x-dl::field.number name="acquisition_cost" label="Acquisition value (IDR)" :value="$nv('acquisition_cost')" :source="$s('acquisition_cost')" />
                <x-dl::field.number name="useful_life_years" label="Useful life (years)" placeholder="10" :value="$nv('useful_life_years')" :source="$s('useful_life_years')" />
                <x-dl::field.date name="warranty_until" label="Warranty until" :value="$dv('warranty_until')" :source="$s('warranty_until')" />
            </x-dl::form.row>
        </x-dl::card>
    </x-slot:finance>

    <x-slot:maintenance>
        <x-dl::card title="Preventive maintenance schedule">
            <x-dl::form.row :cols="4">
                <x-dl::field.number name="maintenance_interval_hours" label="Interval (running hours)" placeholder="500" :value="$nv('maintenance_interval_hours')" :source="$s('maintenance_interval_hours')" />
                <x-dl::field.number name="maintenance_interval_days" label="Interval (days)" placeholder="90" :value="$nv('maintenance_interval_days')" :source="$s('maintenance_interval_days')" />
                <x-dl::field.date name="last_maintenance_date" label="Last maintenance" :value="$dv('last_maintenance_date')" :source="$s('last_maintenance_date')" />
                <x-dl::field.date name="next_maintenance_date" label="Next maintenance" :value="$dv('next_maintenance_date')" :source="$s('next_maintenance_date')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.number name="total_run_hours" label="Total running hours" :value="$nv('total_run_hours')" :source="$s('total_run_hours')" />
                <x-dl::field.number name="total_impressions" label="Total impressions" :value="$nv('total_impressions')" :source="$s('total_impressions')" />
                <x-dl::field.text name="service_vendor" label="Service vendor" :value="$v('service_vendor')" :source="$s('service_vendor')" />
            </x-dl::form.row>
        </x-dl::card>
        @if ($oeeRows)
            <x-dl::card title="Actual OEE (read-only)">
                <x-dl::table :columns="['metric' => 'Measure', 'd7' => 'Last 7 days', 'd30' => 'Last 30 days', 'd90' => 'Last 90 days']" :rows="$oeeRows" :source="Src::of('tm2mst.prod_machine_downtime')" />
                <x-dl::hint note>n/a means it cannot be calculated yet: OEE is valid only when all three factors are measured, and a target figure alone is not enough.@if ($oeeWhy) {{ ' '.implode(' ', $oeeWhy) }}@endif</x-dl::hint>
            </x-dl::card>
        @endif
        <x-dl::hint note>Planned or unplanned is set by the stop type, not chosen by the operator, so Availability cannot be massaged. Existing stops are locked; fill End to close a stop that is still running. Without stop records Availability OEE cannot be calculated.</x-dl::hint>
        <x-dl::lines name="downtimes" title="Machine stops (downtime)" add-label="+ Add stop" :min-rows="0" row-lock="is_new" :rows="$dtRows" :columns="$dtColumns" source="{{ Src::of('tm2mst.prod_machine_downtime') }}" />
    </x-slot:maintenance>

    <x-slot:processes>
        @if (! $new && ! $m->is_process_machine)
            <x-dl::alert tone="warn" message="This machine is marked as support equipment, so it does not need to be mapped to processes. Tick Production process machine on the Machine identity tab if it is used in production." />
        @endif
        <x-dl::hint note>The processes that can run on this machine: the same pair rows as Process Master, seen from the machine side. The machine appears as a choice on each process in routing, BOP and scheduling; a process marked Primary is suggested first. An empty number means use the default of the process; pairs are never deleted, untick Active to remove one. Saved together with Save.</x-dl::hint>
        <x-dl::lines name="processes" title="Processes it can run" add-label="+ Add process" :min-rows="0" row-lock="is_new" :rows="$procRows" :columns="$procColumns" source="{{ Src::of('tm2mst.prod_process_machine') }}" />
        @if ($effRows)
            <x-dl::card title="Effective values (read-only)">
                <x-dl::table :columns="['process' => 'Process', 'speed' => 'Capacity / hour', 'source' => 'Source', 'setup' => 'Setup (min)', 'waste' => 'Waste %', 'hours' => 'Est. hours for 10,000 units']" :rows="$effRows" empty="No processes yet." :source="Src::of('tm2mst.prod_process_machine')" />
            </x-dl::card>
        @endif
        @if ($used)
            <x-dl::card title="Where this machine is used">
                <x-dl::table :columns="['where' => 'Used in', 'count' => 'Records']" :rows="collect($used)->map(fn ($c, $w) => ['where' => $w, 'count' => $c])->values()->all()" empty="Not used yet." :source="Src::of('tm2mst.prod_machine')" />
                <x-dl::hint note>Check this list before deactivating the machine: the relations to other modules are logical, not enforced by the database.</x-dl::hint>
            </x-dl::card>
        @endif
    </x-slot:processes>
</x-dl::form>
