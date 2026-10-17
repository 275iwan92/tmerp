{{--
 REMARK
 Tujuan       : Form Master Position v2 (menggantikan dialog v1): kode, nama, departemen, level, atasan; job description (hanya HR Officer); KPI jabatan (target, bobot, arah); daftar pemegang jabatan.
 Sumber hulu  : tm2mst.org_position, org_position_kpi, org_department, org_level; pemegang dari emp_employee (utama) dan emp_employee_position (tambahan); aturan di App\Services\Hrm\PositionService.
 Input        : Query id (jabatan) atau new=1, tab; session v2_flash dan errors.
 Output       : POST v2.hrm.position.save (Save / Activate / Deactivate), v2.hrm.position.kpi (tambah KPI), v2.hrm.position.kpi.remove (lepas KPI).
 Dipakai oleh : Dibuka dari grid hrm-position (Open dan + New Record); job description dan KPI tampil di tab Job pegawai (hrm-employee-form).
 Status       : Aktif. Hasil KPI bulanan dan skor kemampuan = tahap B (belum). Job description dan KPI hanya bisa diubah HR Officer (keputusan user 2026-09-13).
--}}
@php
    use App\Models\Master\OrgPosition;
    use App\Services\Hrm\PositionService;
    use App\Support\V2\Data\PositionDesk as Desk;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'hrm-position');
    $p = request('id') ? OrgPosition::with('department', 'level', 'reportsTo')->find((int) request('id')) : null;
    abort_if(request('id') && ! $p, 404);
    abort_unless($p || request('new'), 404);
    $new = ! $p;
    $p ??= new OrgPosition(['activate' => true]);
    $svc = app(PositionService::class);
    $canJd = $svc->canEditJobDesk(Auth::user());
    $kpis = $new ? collect() : $svc->kpis($p);
    $total = $new ? 0.0 : $svc->totalWeight($p);
    $holders = $new ? [] : Desk::holders($p);
    $s = fn (string $col) => Src::of('tm2mst.org_position.'.$col);
    $v = fn (string $col) => old($col, $p->{$col});
    $dirs = ['HIGHER' => 'Higher is better', 'LOWER' => 'Lower is better'];
    $tabs = ['general' => 'General'] + ($new ? [] : ['kpi' => 'KPI', 'holders' => 'Holders']);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="hrm-position-form" :title="$new ? 'New Position' : raw($p->position_name)" :subtitle="$new ? null : raw($p->position_code.' · '.($p->department?->department_name ?? '—'))"
    panel="top" kind="master" :status="$new ? 'new' : ($p->activate ? 'active' : 'inactive')"
    :action="route('v2.hrm.position.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'"
    :hidden="$new ? [] : ['id' => $p->position_id]" :tabs="$tabs"
    :created-by="$p->created_by" :created-at="$p->created_at?->toDateTimeString()" :updated-by="$p->updated_by" :updated-at="$p->updated_at?->toDateTimeString()">
    <x-slot:general>
        <x-dl::card title="Position">
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="position_code" label="Code" required :case-toggle="false" :value="$v('position_code')" :source="$s('position_code')" />
                <x-dl::field.text name="position_name" label="Name" required :value="$v('position_name')" :source="$s('position_name')" />
                <x-dl::field.select name="department_id" label="Department" required :options="Desk::departments()" :value="$v('department_id')" :source="Src::of('tm2mst.org_position.department_id', 'tm2mst.org_department')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="level_id" label="Level" required :options="Desk::levels()" :value="$v('level_id')" :source="Src::of('tm2mst.org_position.level_id', 'tm2mst.org_level')" />
                <x-dl::field.select name="reports_to_position_id" label="Reports to" :options="Desk::reportsTo($new ? null : $p->position_id)" :value="$v('reports_to_position_id')" :source="Src::of('tm2mst.org_position.reports_to_position_id')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="Job description" :description="$canJd ? 'Shown read-only on the Job tab of every employee who holds this position.' : 'Only the HR Officer can change the job description.'">
            @if ($canJd)
                <x-dl::field.text name="job_description" label="Job description" multiline :value="$v('job_description')" :source="$s('job_description')" />
            @else
                <x-dl::field.display label="Job description" :value="raw($p->job_description ?: '—')" :source="$s('job_description')" />
            @endif
            @if ($p->job_description_updated_at)
                <x-dl::hint note>{{ tf('Updated :d by :u', ['d' => Locale::date($p->job_description_updated_at), 'u' => $p->job_description_updated_by]) }}</x-dl::hint>
            @endif
        </x-dl::card>
    </x-slot:general>

    @unless ($new)
        <x-slot:kpi>
            <x-dl::action-bar>
                @if ($canJd)
                    <x-dl::button type="button" size="sm" variant="secondary" icon="fa-plus" label="Add KPI" data-dl-open="pos-kpi-add" />
                    @if ($kpis->isNotEmpty())<x-dl::button type="button" size="sm" variant="secondary" icon="fa-trash" label="Remove KPI" data-dl-open="pos-kpi-remove" />@endif
                @endif
                <x-dl::badge :label="tf('Total weight :w%', ['w' => Locale::decimal($total, 2)])" :tone="abs($total - 100) < 0.01 ? 'success' : 'warn'" />
            </x-dl::action-bar>
            <x-dl::table :columns="['name' => 'KPI', 'unit' => 'Unit', 'dir' => 'Better when', 'target' => ['Target', 'num' => true, 'raw' => true], 'weight' => ['Weight', 'num' => true, 'raw' => true]]"
                :rows="$kpis->map(fn ($k) => ['name' => raw($k->kpi_name), 'unit' => raw($k->unit ?: '—'), 'dir' => $dirs[$k->direction], 'target' => Locale::decimal($k->target_value, 2), 'weight' => Locale::decimal($k->weight_pct, 2).'%'])->all()"
                empty="No KPI defined for this position yet." :source="Src::of('tm2mst.org_position_kpi')" />
            @if ($kpis->isNotEmpty() && abs($total - 100) >= 0.01)
                <x-dl::alert tone="warn" message="The weights of the KPIs must add up to 100% before monthly results can be entered." />
            @endif
        </x-slot:kpi>

        <x-slot:holders>
            <x-dl::table :columns="['name' => 'Employee', 'code' => 'Code', 'role' => 'Position of this employee']"
                :rows="array_map(fn ($h) => ['name' => raw($h['name']), 'code' => raw($h['code']), 'role' => $h['primary'] ? 'Primary' : 'Also holds'], $holders)"
                empty="Nobody holds this position yet." :source="Src::of('tm2mst.emp_employee.position_id', 'tm2mst.emp_employee_position')" />
        </x-slot:holders>
    @endunless
</x-dl::form>

@if (! $new && $canJd)
    <x-dl::modal id="pos-kpi-add" title="Add KPI" icon="fa-bullseye" size="lg">
        <x-dl::action-form action="{{ route('v2.hrm.position.kpi', $p->position_id) }}">
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="kpi_name" label="KPI" required :source="Src::of('tm2mst.org_position_kpi.kpi_name')" />
                <x-dl::field.text name="unit" label="Unit" hint="For example sheets, %, minutes." :source="Src::of('tm2mst.org_position_kpi.unit')" />
                <x-dl::field.select name="direction" label="Better when" required :options="array_map('t', $dirs)" value="HIGHER" :placeholder="null" :source="Src::of('tm2mst.org_position_kpi.direction')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                <x-dl::field.decimal name="target_value" label="Target" required :source="Src::of('tm2mst.org_position_kpi.target_value')" />
                <x-dl::field.decimal name="weight_pct" label="Weight (%)" required :hint="tf('Still free: :w%', ['w' => Locale::decimal(max(0, 100 - $total), 2)])" :source="Src::of('tm2mst.org_position_kpi.weight_pct')" />
            </x-dl::form.row>
            <x-dl::alert tone="info" message="Monthly results are scored against this target: min(actual / target, 100%) when higher is better, min(target / actual, 100%) when lower is better." />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                <x-dl::button preset="save" />
            </x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>

    @if ($kpis->isNotEmpty())
        <x-dl::modal id="pos-kpi-remove" title="Remove KPI" icon="fa-trash">
            <x-dl::action-form action="{{ route('v2.hrm.position.kpi.remove', $p->position_id) }}">
                <x-dl::field.select name="kpi_id" label="KPI" required :options="$kpis->mapWithKeys(fn ($k) => [$k->id_position_kpi => $k->kpi_name])->all()" :source="Src::of('tm2mst.org_position_kpi.activate')" />
                <x-dl::alert tone="info" message="The KPI is switched off, not deleted. Results entered earlier keep their own frozen target and weight." />
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                    <x-dl::button preset="save" label="Remove KPI" data-dl-confirm="{{ t('Remove this KPI from the position?') }}" />
                </x-dl::action-bar>
            </x-dl::action-form>
        </x-dl::modal>
    @endif
@endif
