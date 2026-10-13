{{--
 REMARK
 Tujuan       : Setup > Dummy & Legacy Data: tampilan data, kelengkapan data legacy, snapshot dan reset dummy, aturan klasifikasi asal data, ringkasan per tabel.
 Sumber hulu  : App\Support\V2\Data\DataOriginDesk dan App\Services\Data\DataOriginService (aturan, snapshot, readiness per database).
 Input        : Query db (saringan database), rule (id aturan atau new); session dorigin_preview.
 Output       : Kartu, tabel, dan form x-dl::*; aksi POST ke route v2.data-origin/{op}.
 Dipakai oleh : route v2.screen (routes/v2.php), slug setup-data-origin (menu Setup > Dummy & Legacy Data).
 Status       : Aktif; hanya Administrator.
--}}
@php
    use App\Services\Data\DataOriginService;
    use App\Support\V2\Data\DataOriginDesk;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'setup-data-origin');
    $act = fn (string $op) => route('v2.data-origin', $op);
    $totals = DataOriginDesk::totals();
    $settings = DataOriginDesk::settings();
    $svc = DataOriginDesk::service();
    $n = fn ($v) => Locale::number($v);
    $dbs = array_combine(DataOriginService::DATABASES, DataOriginService::DATABASES);
    $preview = session('dorigin_preview');
    $requirements = DataOriginDesk::requirements();
    $readiness = $svc->readiness();
    $snapshot = $svc->snapshotInfo();
    $rules = DataOriginDesk::rules();
    $editId = request('rule');
    $rule = $editId === 'new' ? (object) ['id_rule' => null, 'match_type' => 'CREATED_BY', 'db_name' => '', 'table_name' => '', 'column_name' => '', 'match_value' => '',
        'origin' => 'DUMMY', 'priority' => (int) $rules->max('priority') + 10, 'note' => ''] : ($editId ? DataOriginDesk::rule((int) $editId) : null);
    $dbFilter = in_array(request('db'), DataOriginService::DATABASES, true) ? request('db') : '';
    $types = array_map('t', DataOriginDesk::TYPES);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::kpi-row>
    <x-dl::stat-box label="LIVE rows" :value="$n($totals['LIVE'])" tone="success" />
    <x-dl::stat-box label="DUMMY rows" :value="$n($totals['DUMMY'])" tone="warn" />
    <x-dl::stat-box label="LEGACY rows" :value="$n($totals['LEGACY'])" tone="accent" />
</x-dl::kpi-row>

<x-dl::card title="Data Display" description="Hiding does not delete data. It applies to every screen that reads through the application models.">
    <x-dl::action-form action="{{ $act('settings') }}">
        <x-dl::field.checkbox name="show_dummy" text="Show DUMMY data (demo / UAT)" :value="$settings['show_dummy']" :source="Src::of('tm2sys.sys_constant.DATA_SHOW_DUMMY')" />
        <x-dl::field.checkbox name="show_legacy" text="Show LEGACY data (migrated from the old system)" :value="$settings['show_legacy']" :source="Src::of('tm2sys.sys_constant.DATA_SHOW_LEGACY')" />
        @if (! $settings['show_legacy'])
            <x-dl::alert tone="warn" :message="raw(tf('Hiding LEGACY hides :n rows — including most Raw Material, Customer, Supplier, Product and employee records from the import. Make sure working data no longer depends on them.', ['n' => $n($totals['LEGACY'])]))" />
        @endif
        <x-dl::field.checkbox name="hide_incomplete" text="Hide LEGACY data that does not meet the mandatory requirement" :value="$settings['hide_incomplete']" :source="Src::of('tm2sys.sys_constant.DATA_HIDE_LEGACY_INCOMPLETE')" />
        <x-dl::field.checkbox name="new_as_dummy" text="UAT mode: new data is marked DUMMY automatically (reset with it)" :value="$settings['new_as_dummy']" :source="Src::of('tm2sys.sys_constant.DATA_NEW_AS_DUMMY')" />
        <x-dl::action-bar><x-dl::button preset="save" /></x-dl::action-bar>
    </x-dl::action-form>
</x-dl::card>

<x-dl::card title="Legacy Data Completeness" description="LEGACY data that meets the mandatory requirement may become real data (LIVE). The rest stays LEGACY so it is visible as still to complete. Tables without a requirement count as complete.">
    <x-dl::table :columns="['table' => 'Table', 'need' => 'Mandatory requirement', 'ready' => ['Complete legacy', 'num' => true], 'incomplete' => ['Incomplete legacy', 'num' => true], 'live' => ['Incomplete LIVE', 'num' => true]]"
        :rows="collect($readiness)->map(fn ($r, $tbl) => ['table' => $tbl, 'need' => $requirements->filter(fn ($q) => $q->db_name.'.'.$q->table_name === $tbl && $q->activate)->pluck('label')->implode(', '),
            'ready' => $n($r['LEGACY_READY']), 'incomplete' => $n($r['LEGACY_INCOMPLETE']), 'live' => $n($r['LIVE_INCOMPLETE'])])->values()->all()"
        empty="No mandatory requirement registered yet." :source="Src::of('tm2sys.sys_data_requirement')" />
    <x-dl::action-form action="{{ $act('recompute') }}"><x-dl::action-bar>
        <x-dl::button variant="secondary" icon="fa-rotate" label="Recalculate Completeness" />
        <x-dl::button variant="secondary" icon="fa-magnifying-glass" label="Preview Make Real" type="submit" formaction="{{ $act('promote-preview') }}" />
    </x-dl::action-bar></x-dl::action-form>

    @if ($preview && $preview['kind'] === 'promote')
        @if ($preview['data'])
            <x-dl::table :columns="['table' => 'Table', 'rows' => ['Rows', 'num' => true]]" :rows="collect($preview['data'])->map(fn ($c, $tbl) => ['table' => $tbl, 'rows' => $n($c)])->values()->all()" :source="Src::of('tm2sys.sys_data_requirement')" />
            @foreach ($preview['data'] as $tbl => $c)
                <x-dl::action-form action="{{ $act('promote') }}">
                    <input type="hidden" name="only" value="{{ $tbl }}">
                    <x-dl::button variant="secondary" size="sm" :label="t('Make Real').' · '.raw($tbl)" data-dl-confirm="{{ t('Make these rows real data (LIVE)?') }}" />
                </x-dl::action-form>
            @endforeach
            <x-dl::action-form action="{{ $act('promote') }}"><x-dl::button label="Make Real (all tables)" data-dl-confirm="{{ t('Make ALL complete legacy rows real data (LIVE)?') }}" /></x-dl::action-form>
        @else
            <x-dl::alert tone="success" message="There are no complete LEGACY rows to promote." />
        @endif
    @endif

    <x-dl::action-form action="{{ $act('req-save') }}">
        <x-dl::form.row :cols="4">
            <x-dl::field.select name="db_name" label="Database" required :options="$dbs" :placeholder="null" :source="Src::of('tm2sys.sys_data_requirement.db_name')" />
            <x-dl::field.text name="table_name" label="Table" required :case-toggle="false" placeholder="prod_product" :source="Src::of('tm2sys.sys_data_requirement.table_name')" />
            <x-dl::field.text name="column_name" label="Mandatory Column" required :case-toggle="false" placeholder="customer_id" :source="Src::of('tm2sys.sys_data_requirement.column_name')" />
            <x-dl::field.text name="label" label="Requirement Name" required placeholder="Customer ownership" :source="Src::of('tm2sys.sys_data_requirement.label')" />
        </x-dl::form.row>
        <x-dl::action-bar><x-dl::button preset="save" label="Add Requirement" /></x-dl::action-bar>
    </x-dl::action-form>
    @foreach ($requirements as $q)
        <x-dl::action-form action="{{ $act('req-toggle') }}">
            <input type="hidden" name="id" value="{{ $q->id_requirement }}">
            <x-dl::button :variant="$q->activate ? 'secondary' : 'ghost'" size="sm" :label="raw($q->table_name.'.'.$q->column_name.' — '.$q->label)" :icon="$q->activate ? 'fa-check' : 'fa-ban'" />
        </x-dl::action-form>
    @endforeach
</x-dl::card>

<x-dl::card title="Reset Dummy Data" :description="raw(tf('The snapshot is the original state of DUMMY data. Reset brings back dummy rows that were deleted or changed during UAT. Last snapshot: :rows rows :at', ['rows' => $n($snapshot['rows']), 'at' => $snapshot['at'] ? '· '.Locale::stamp($snapshot['at']).' / '.$snapshot['by'] : '']))">
    <x-dl::action-form action="{{ $act('snapshot') }}">
        <x-dl::field.checkbox name="remove_new" text="Also delete DUMMY data created after the snapshot" :value="(bool) ($preview['data']['remove_new'] ?? false)" />
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="fa-camera" label="Retake Snapshot" data-dl-confirm="{{ t('Replace the snapshot with the current state of DUMMY data?') }}" />
            <x-dl::button variant="secondary" icon="fa-magnifying-glass" label="Preview Reset" type="submit" formaction="{{ $act('reset-preview') }}" />
        </x-dl::action-bar>
    </x-dl::action-form>
    @if ($preview && $preview['kind'] === 'reset')
        @php $rp = $preview['data']; @endphp
        <x-dl::alert tone="info" :message="raw(tf('Will be restored: :restored rows lost or changed flag, :changed rows changed content, new dummy data: :new :what.', ['restored' => $rp['restored'], 'changed' => $rp['changed'], 'new' => $rp['new'], 'what' => $rp['remove_new'] ? t('(will be deleted)') : t('(left as is)')]))" />
        @if ($rp['tables'])
            <x-dl::table :columns="['table' => 'Table', 'restored' => ['Restored', 'num' => true], 'changed' => ['Changed', 'num' => true], 'new' => ['New', 'num' => true]]"
                :rows="collect($rp['tables'])->map(fn ($r, $tbl) => ['table' => $tbl, 'restored' => $n($r['restored']), 'changed' => $n($r['changed']), 'new' => $n($r['new'])])->values()->all()" />
            <x-dl::action-form action="{{ $act('reset') }}">
                <input type="hidden" name="remove_new" value="{{ $rp['remove_new'] ? 1 : 0 }}">
                <x-dl::button icon="fa-rotate-left" label="Reset Now" data-dl-confirm="{{ t('Reset DUMMY data to the current snapshot?') }}" />
            </x-dl::action-form>
        @else
            <x-dl::alert tone="success" message="Dummy data already equals the snapshot — nothing to reset." />
        @endif
    @endif
</x-dl::card>

<x-dl::card title="Classification Rules" description="Only raises LIVE to DUMMY / LEGACY. Children of a DUMMY row (relations) become DUMMY too.">
    <x-dl::action-bar>
        <x-dl::button variant="secondary" icon="fa-plus" label="New Record" :href="$home.'?rule=new'" />
        <x-dl::action-form action="{{ $act('classify-preview') }}"><x-dl::button variant="secondary" icon="fa-magnifying-glass" label="Preview" /></x-dl::action-form>
    </x-dl::action-bar>

    @if ($rule)
        <x-dl::action-form action="{{ $act('rule') }}">
            <input type="hidden" name="id_rule" value="{{ $rule->id_rule }}">
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="match_type" label="Type" required :options="$types" :value="old('match_type', $rule->match_type)" :placeholder="null" :source="Src::of('tm2sys.sys_data_origin_rule.match_type')" />
                <x-dl::field.select name="db_name" label="Database" :options="$dbs" :value="old('db_name', $rule->db_name)" placeholder="All databases" :source="Src::of('tm2sys.sys_data_origin_rule.db_name')" />
                <x-dl::field.text name="table_name" label="Table" :case-toggle="false" :value="old('table_name', $rule->table_name)" placeholder="empty = all tables" :source="Src::of('tm2sys.sys_data_origin_rule.table_name')" />
                <x-dl::field.text name="column_name" label="Column" :case-toggle="false" :value="old('column_name', $rule->column_name)" hint="Not used by the type Creator" :source="Src::of('tm2sys.sys_data_origin_rule.column_name')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.text name="match_value" label="Pattern (LIKE, % = any)" :case-toggle="false" :value="old('match_value', $rule->match_value)" hint="Not used by the type Column is filled" :source="Src::of('tm2sys.sys_data_origin_rule.match_value')" />
                <x-dl::field.select name="origin" label="Mark As" required :options="['DUMMY' => 'DUMMY', 'LEGACY' => 'LEGACY']" :value="old('origin', $rule->origin)" :placeholder="null" :source="Src::of('tm2sys.sys_data_origin_rule.origin')" />
                <x-dl::field.number name="priority" label="Priority" :value="old('priority', $rule->priority)" :source="Src::of('tm2sys.sys_data_origin_rule.priority')" />
                <x-dl::field.text name="note" label="Notes" :value="old('note', $rule->note)" :source="Src::of('tm2sys.sys_data_origin_rule.note')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" />
            </x-dl::action-bar>
        </x-dl::action-form>
    @endif

    @if ($preview && $preview['kind'] === 'classify')
        @if ($preview['data'])
            <x-dl::table :columns="['table' => 'Table', 'dummy' => ['DUMMY', 'num' => true], 'legacy' => ['LEGACY', 'num' => true]]"
                :rows="collect($preview['data'])->map(fn ($c, $tbl) => ['table' => $tbl, 'dummy' => $n($c['DUMMY']), 'legacy' => $n($c['LEGACY'])])->values()->all()" />
            <x-dl::action-form action="{{ $act('classify') }}"><x-dl::button label="Apply" data-dl-confirm="{{ t('Apply this classification?') }}" /></x-dl::action-form>
        @else
            <x-dl::alert tone="success" message="No LIVE row matches a rule — everything is already classified." />
        @endif
    @endif

    <x-dl::table :columns="['prio' => ['Priority', 'num' => true], 'type' => 'Type', 'scope' => 'Scope', 'pattern' => 'Pattern', 'origin' => 'Mark As', 'note' => 'Notes', 'status' => 'Status']"
        :rows="$rules->map(fn ($r) => ['prio' => $r->priority, 'type' => $types[$r->match_type] ?? $r->match_type, 'scope' => ($r->db_name ?: '*').'.'.($r->table_name ?: '*').($r->column_name ? '.'.$r->column_name : ''),
            'pattern' => $r->match_value ?? t('(filled)'), 'origin' => $r->origin, 'note' => $r->note, 'status' => $r->activate ? t('Active') : t('Inactive')])->all()"
        empty="No classification rule yet." :source="Src::of('tm2sys.sys_data_origin_rule')" />
    @foreach ($rules as $r)
        <x-dl::action-bar>
            <x-dl::badge :label="raw('#'.$r->priority.' '.$r->origin)" :tone="$r->activate ? 'info' : 'neutral'" />
            <x-dl::button variant="secondary" size="sm" icon="fa-pen" label="Edit" :href="$home.'?rule='.$r->id_rule" />
            <x-dl::action-form action="{{ $act('rule-toggle') }}">
                <input type="hidden" name="id" value="{{ $r->id_rule }}">
                <x-dl::button variant="secondary" size="sm" :label="$r->activate ? 'Deactivate' : 'Activate'" />
            </x-dl::action-form>
        </x-dl::action-bar>
    @endforeach
</x-dl::card>

<x-dl::card title="Tables Holding DUMMY / LEGACY Data">
    <x-dl::action-form method="get" action="{{ $home }}">
        <x-dl::field.select name="db" label="Database" :options="$dbs" :value="$dbFilter" placeholder="All databases" :source="Src::of('tm2sys.sys_data_origin_rule.db_name')" />
        <x-dl::action-bar><x-dl::button variant="secondary" label="Filter" /></x-dl::action-bar>
    </x-dl::action-form>
    <x-dl::table :columns="['table' => 'Table', 'live' => ['LIVE', 'num' => true], 'dummy' => ['DUMMY', 'num' => true], 'legacy' => ['LEGACY', 'num' => true]]"
        :rows="collect(DataOriginDesk::marked($dbFilter))->map(fn ($c, $tbl) => ['table' => $tbl, 'live' => $n($c['LIVE']), 'dummy' => $n($c['DUMMY']), 'legacy' => $n($c['LEGACY'])])->values()->all()"
        empty="There is no DUMMY / LEGACY data." :source="Src::of('tm2sys.sys_data_origin_rule')" />
</x-dl::card>
