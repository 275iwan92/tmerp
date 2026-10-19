{{--
 REMARK
 Tujuan       : Form Master Process v2 (menggantikan halaman v1 pages::production.master-process): tab Main info (kode, nama, tahap, departemen, urutan routing, warna Gantt, sertifikasi crew, in-house, proses manual, QC gate, deskripsi), Parameters and capacity (satuan keluaran, setup, kapasitas, crew, waste, toleransi, lot, bottleneck, paralel), Cost and tooling (basis ongkos, tarif standar, plate / die / tooling, kode skill, lead time subkon), Executing machines (matriks proses x mesin dalam satu tabel yang disimpan bersama Save, nilai efektif dan estimasi jam hanya-baca, di mana proses dipakai), Deactivate / Activate, tab History.
 Sumber hulu  : tm2mst.prod_process, prod_process_machine, prod_machine, org_department; aturan di App\Services\Production\ProcessMasterService dan ProcessMachineMatrixService; nilai efektif dari MachineCapabilityService.
 Input        : Query id (proses) atau new=1; session v2_flash dan errors; old input.
 Output       : POST v2.prod.process.save (Save / Activate / Deactivate).
 Dipakai oleh : Dibuka dari grid prod-process (Open dan + New Record).
 Status       : Aktif (Master T6b). DATA REAL: angka kosong tetap kosong (bukan 0); pasangan mesin tidak dihapus, dinonaktifkan lewat kolom Active.
--}}
@php
    use App\Models\Master\ProdProcess;
    use App\Services\Production\ProcessMachineMatrixService as Mx;
    use App\Services\Production\ProcessMasterService as Pm;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'prod-process');
    $svc = app(Pm::class);
    $mx = app(Mx::class);
    $p = request('id') ? ProdProcess::with('department')->find((int) request('id')) : null;
    abort_if(request('id') && ! $p, 404);
    abort_unless($p || request('new'), 404);
    $new = ! $p;
    $p ??= new ProdProcess(['activate' => true, 'inhouse' => true, 'is_manual' => false, 'output_uom' => 'LEMBAR']);
    $s = fn (string $col) => Src::of('tm2mst.prod_process.'.$col);
    $v = fn (string $col) => old($col, $p->{$col});
    $n = fn ($x) => $x === null || $x === '' ? '' : (rtrim(rtrim((string) $x, '0'), '.') ?: '0');
    $nv = fn (string $col) => old($col, $n($p->{$col}));
    $oldRows = old('machines');
    $rows = is_array($oldRows) ? array_values($oldRows) : ($new ? [] : $mx->rows('process', (int) $p->id_process));
    $rows = array_map(fn ($r) => $r + ['other_id_label' => '', 'is_new' => empty($r['capability_id'])], $rows);
    $eff = $new ? [] : $mx->effective('process', (int) $p->id_process);
    $machines = $new ? 0 : $svc->machineCount($p);
    $used = $new ? [] : $svc->whereUsed($p);
    $f = fn (string $key, string $label, string $type = 'number', string $w = '90px', array $more = []) => ['key' => $key, 'label' => $label, 'type' => $type, 'width' => $w] + $more;
    $columns = [
        ['key' => 'other_id', 'label' => 'Machine', 'type' => 'lookup', 'lookup' => 'prod_machine_proc', 'width' => '230px', 'readonlyUnless' => 'is_new'],
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
    $effRows = array_map(fn ($e) => ['machine' => $e['machine'], 'speed' => $e['speed'] === null ? t('none yet') : Locale::number((float) $e['speed'], 0), 'source' => t(['CAPABILITY' => 'Pair', 'PROCESS' => 'Process default', 'MACHINE' => 'Machine', 'MISSING' => 'Missing'][$e['speed_source']] ?? $e['speed_source']),
        'setup' => $e['setup'] === null ? t('none yet') : (string) $e['setup'], 'waste' => $e['waste'] === null ? '—' : $n($e['waste']), 'hours' => $e['hours_10k'] === null ? t('cannot be calculated') : Locale::number((float) $e['hours_10k'], 2)], $eff);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="prod-process-form" :title="$new ? 'New Process' : raw($p->process_name)" :subtitle="$new ? null : raw(($p->process_code ?: '—').' · '.($p->process_stage ? $p->stageLabel() : '—'))"
    panel="top" kind="master" :status="$new ? 'new' : ($p->activate ? 'active' : 'inactive')"
    :action="route('v2.prod.process.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'"
    :hidden="$new ? [] : ['id' => $p->id_process]" :tabs="['main' => t('Main info'), 'capacity' => t('Parameters and capacity'), 'cost' => t('Cost and tooling'), 'machines' => t('Executing machines')]" :log="$new ? [] : $svc->history($p)"
    :created-by="$p->created_by" :created-at="$p->created_at?->toDateTimeString()" :updated-by="$p->updated_by" :updated-at="$p->updated_at?->toDateTimeString()">
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Process code" :value="$new ? t('(on Save)') : raw($p->process_code ?: '—')" />
        <x-dl::strip-kv kind="grand" label="Stage" :value="$new ? '—' : raw($p->process_stage ? $p->stageLabel() : '—')" />
        <x-dl::strip-kv kind="grand" label="Executing machines" :value="$new ? '—' : ($p->is_manual ? t('Manual process') : Locale::number((float) $machines, 0))" />
        <x-dl::strip-kv kind="grand" label="Standard capacity / hour" :value="$new || $p->default_speed_per_hour === null ? t('none yet') : Locale::number((float) $p->default_speed_per_hour, 0)" />
    </x-slot:strip>

    <x-slot:main>
        <x-dl::card title="Main info">
            <x-dl::form.row :cols="4">
                <x-dl::field.text name="process_code" label="Process code" placeholder="e.g. CE, PLG, LAM" hint="Letters, digits, - or _, up to 10; used in the work order and routing." :value="$v('process_code')" :source="$s('process_code')" />
                <x-dl::field.text name="process_name" label="Process name" required :value="$v('process_name')" :source="$s('process_name')" />
                <x-dl::field.select name="process_stage" label="Production stage" placeholder="—" :options="ProdProcess::STAGES" :value="$v('process_stage')" :source="$s('process_stage')" />
                <x-dl::field.select name="department_id" label="Related department" placeholder="— select department —" :options="$svc->departmentOptions()" :value="$v('department_id')" :source="$s('department_id')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.number name="routing_seq" label="Routing sequence" hint="Suggested order in the BOP." :value="$v('routing_seq')" :source="$s('routing_seq')" />
                <x-dl::field.color name="gantt_color" label="Gantt colour" :value="$v('gantt_color')" :source="$s('gantt_color')" />
                <x-dl::field.text name="skill_code" label="Crew certification" placeholder="e.g. OP-OFFSET" :value="$v('skill_code')" :source="$s('skill_code')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.checkbox name="inhouse" label="Done in-house" :value="(bool) $v('inhouse')" :source="$s('inhouse')" />
                <x-dl::field.checkbox name="is_manual" label="Manual process (no machine)" :value="(bool) $v('is_manual')" :source="$s('is_manual')" />
                <x-dl::field.checkbox name="qc_required" label="Must pass the QC gate" :value="(bool) $v('qc_required')" :source="$s('qc_required')" />
            </x-dl::form.row>
            <x-dl::field.text name="description" label="Description" multiline placeholder="Notes on how to work, material requirements, common mistakes..." :value="$v('description')" :source="$s('description')" />
        </x-dl::card>
    </x-slot:main>

    <x-slot:capacity>
        <x-dl::hint note>These are the default values of the process. When one machine differs, enter the override on the Executing machines tab instead of changing these numbers. An empty number stays empty (it is never saved as 0).</x-dl::hint>
        <x-dl::card title="Parameters and capacity">
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="output_uom" label="Output unit (UOM)" :options="ProdProcess::OUTPUT_UOM" :value="$v('output_uom')" :source="$s('output_uom')" />
                <x-dl::field.number name="default_setup_minutes" label="Setup time / make-ready (minutes)" placeholder="e.g. 45" :value="$nv('default_setup_minutes')" :source="$s('default_setup_minutes')" />
                <x-dl::field.number name="default_speed_per_hour" label="Standard capacity (per hour)" hint="In the output unit above." :value="$nv('default_speed_per_hour')" :source="$s('default_speed_per_hour')" />
                <x-dl::field.number name="default_manpower" label="Standard crew count / shift" :value="$nv('default_manpower')" :source="$s('default_manpower')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.number name="default_waste_pct" label="Running waste (%)" :value="$nv('default_waste_pct')" :source="$s('default_waste_pct')" />
                <x-dl::field.number name="default_waste_fixed_qty" label="Fixed insheet (qty)" hint="Make-ready sheets that are lost whatever the order size." :value="$nv('default_waste_fixed_qty')" :source="$s('default_waste_fixed_qty')" />
                <x-dl::field.number name="default_tolerance_pct" label="Delivery tolerance (%)" :value="$nv('default_tolerance_pct')" :source="$s('default_tolerance_pct')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.number name="min_lot_qty" label="Minimum lot" :value="$nv('min_lot_qty')" :source="$s('min_lot_qty')" />
                <x-dl::field.number name="max_lot_qty" label="Maximum lot" :value="$nv('max_lot_qty')" :source="$s('max_lot_qty')" />
                <x-dl::field.checkbox name="is_bottleneck" label="Bottleneck process (limiting capacity, scheduled first)" :value="(bool) $v('is_bottleneck')" :source="$s('is_bottleneck')" />
                <x-dl::field.checkbox name="allow_parallel" label="Parallel allowed across machines" :value="(bool) $v('allow_parallel')" :source="$s('allow_parallel')" />
            </x-dl::form.row>
        </x-dl::card>
    </x-slot:capacity>

    <x-slot:cost>
        <x-dl::card title="Cost and tooling">
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="cost_basis" label="Cost basis" placeholder="—" :options="ProdProcess::COST_BASIS" :value="$v('cost_basis')" :source="$s('cost_basis')" />
                <x-dl::field.number name="standard_rate" label="Standard rate (IDR)" hint="Needs a cost basis." :value="$nv('standard_rate')" :source="$s('standard_rate')" />
                <x-dl::field.number name="subcon_lead_days" label="Subcontract lead time (days)" :value="$nv('subcon_lead_days')" :source="$s('subcon_lead_days')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.checkbox name="requires_plate" label="Needs a plate" :value="(bool) $v('requires_plate')" :source="$s('requires_plate')" />
                <x-dl::field.checkbox name="requires_die" label="Needs a die" :value="(bool) $v('requires_die')" :source="$s('requires_die')" />
                <x-dl::field.checkbox name="requires_tooling" label="Needs other tooling" :value="(bool) $v('requires_tooling')" :source="$s('requires_tooling')" />
            </x-dl::form.row>
        </x-dl::card>
    </x-slot:cost>

    <x-slot:machines>
        <x-dl::hint note>The machines that can do this process. They appear as machine choices in routing, BOP and scheduling; a machine marked Primary is suggested first. An empty number means "use the default of the process"; pairs are never deleted, untick Active to remove one. Saved together with Save.</x-dl::hint>
        <x-dl::lines name="machines" title="Executing machines" add-label="+ Add machine" :min-rows="0" row-lock="is_new" :rows="$rows" :columns="$columns" source="{{ Src::of('tm2mst.prod_process_machine') }}" />
        @if ($effRows)
            <x-dl::card title="Effective values (read-only)">
                <x-dl::table :columns="['machine' => 'Machine', 'speed' => 'Capacity / hour', 'source' => 'Source', 'setup' => 'Setup (min)', 'waste' => 'Waste %', 'hours' => 'Est. hours for 10,000 units']" :rows="$effRows" empty="No machines yet." :source="Src::of('tm2mst.prod_process_machine')" />
            </x-dl::card>
        @endif
        @if ($used)
            <x-dl::card title="Where this process is used">
                <x-dl::table :columns="['where' => 'Used in', 'count' => 'Records']" :rows="collect($used)->map(fn ($c, $w) => ['where' => $w, 'count' => $c])->values()->all()" empty="Not used yet." :source="Src::of('tm2mst.prod_process')" />
            </x-dl::card>
        @endif
    </x-slot:machines>
</x-dl::form>
