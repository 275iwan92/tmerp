{{-- Setup > Data Origin (v2, pengganti layar v1 pages::setup.data-origin; mockup docs/mockups/data_origin_v2.jpg, disetujui user 2026-10-06).
     Data & aksi = App\Support\V2\Data\DataOrigin (logika di App\Services\Data\DataOriginService). Pratinjau = GET ?preview=..., tidak mengubah data;
     Apply / Make Real / Reset = POST. Aturan & syarat wajib = Grid Lookup DataOriginRuleLookup / DataRequirementLookup. --}}
@php
    use App\Services\Data\DataOriginService;
    use App\Support\V2\Data\DataOrigin;
    use App\Support\V2\Grid\Setup\DataOriginRuleLookup;
    use App\Support\V2\Grid\Setup\DataRequirementLookup;
    use App\Support\V2\Src;
    use Devlib\Locale;

    abort_unless(auth()->user()->is_admin, 403);
    $svc = app(DataOriginService::class);
    $tab = in_array(request('tab'), ['display', 'legacy', 'rules', 'snapshot', 'tables'], true) ? request('tab') : 'display';
    $preview = (string) request('preview');
    $removeNew = request()->boolean('remove_new');
    $db = in_array(request('db'), DataOriginService::DATABASES, true) ? request('db') : '';
    $here = fn (string $t, array $q = []) => route('v2.screen', 'data-origin').'?'.http_build_query(['tab' => $t] + $q);
    $n = fn ($v) => Locale::number($v);

    [$counts, $totals] = DataOrigin::counts();
    $set = DataOrigin::settings();
    $rules = (new DataOriginRuleLookup)->props();
    $reqs = (new DataRequirementLookup)->props();
    $snap = $svc->snapshotInfo();
    $classify = $tab === 'rules' && $preview === 'classify' ? $svc->classify(true) : null;
    $promote = $tab === 'legacy' && $preview === 'promote' ? $svc->promoteReadyLegacy(true) : null;
    $reset = $tab === 'snapshot' && $preview === 'reset' ? $svc->reset(true, $removeNew) : null;
    $k = fn (string $key) => Src::of('tm2sys.sys_constant.constant_number').' ('.$key.')';
    $confirm = fn (string $msg) => "return confirm('".addslashes(t($msg))."')";
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form.row cols="3">
    <x-dl::stat-box tone="success" label="LIVE rows" :value="raw($n($totals['LIVE']))" :sub="t('real data, always shown')" />
    <x-dl::stat-box tone="warn" label="DUMMY rows" :value="raw($n($totals['DUMMY']))" :sub="t('demo / UAT, can be reset')" />
    <x-dl::stat-box tone="violet" label="LEGACY rows" :value="raw($n($totals['LEGACY']))" :sub="t('migrated from the old system')" />
</x-dl::form.row>

<x-dl::tabs :active="$tab" :tabs="['display' => 'Display', 'legacy' => 'Legacy Completeness', 'rules' => 'Classification Rules', 'snapshot' => 'Dummy Snapshot & Reset', 'tables' => 'Tables']">
    <x-slot:display>
        <x-dl::action-form action="{{ route('v2.data-origin.settings') }}" data-dl-guard>
            <x-dl::card title="Data display" icon="fa-eye" description="Which rows the screens show. Hiding does not delete anything. Console, system reports and tests always see every row.">
                <x-dl::field.checkbox name="show_dummy" text="Show DUMMY data (demo / UAT)" :value="$set['show_dummy']" :source="$k('DATA_SHOW_DUMMY')" />
                <x-dl::field.checkbox name="show_legacy" text="Show LEGACY data (migrated from the old system)" :value="$set['show_legacy']" :source="$k('DATA_SHOW_LEGACY')"
                    :hint="raw(tf('Hiding LEGACY hides :n rows, including most of the imported materials, customers, suppliers, products and employees.', ['n' => $n($totals['LEGACY'])]))" />
                <x-dl::field.checkbox name="hide_incomplete" text="Hide LEGACY rows that are not complete yet" :value="$set['hide_incomplete']" :source="$k('DATA_HIDE_LEGACY_INCOMPLETE')" />
                <x-dl::field.checkbox name="new_as_dummy" text="UAT mode: new rows are marked DUMMY (reset with them)" :value="$set['new_as_dummy']" :source="$k('DATA_NEW_AS_DUMMY')" />
                <x-dl::action-bar>
                    <x-dl::button preset="save" />
                </x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    </x-slot:display>

    <x-slot:legacy>
        <x-dl::card title="Legacy data completeness" icon="fa-list-check" description="LEGACY rows that meet every required field may become real data (LIVE). The rest stay LEGACY so it shows they still need work. Tables without a required field count as complete.">
            <x-dl::action-form action="{{ route('v2.data-origin.recompute') }}">
                <x-dl::action-bar align="start">
                    <x-dl::button variant="secondary" icon="fa-rotate" label="Recount" type="submit" />
                    <x-dl::button variant="secondary" icon="fa-magnifying-glass" label="Preview Make Real" :href="$here('legacy', ['preview' => 'promote'])" />
                </x-dl::action-bar>
            </x-dl::action-form>
            <x-dl::table :columns="['table' => 'Table', 'required' => 'Required fields', 'LEGACY_READY' => ['Legacy complete', 'num' => true],
                'LEGACY_INCOMPLETE' => ['Legacy incomplete', 'num' => true], 'LIVE_INCOMPLETE' => ['LIVE incomplete', 'num' => true]]"
                :rows="DataOrigin::readiness()" empty="No required field is listed yet." :source="Src::of('tm2sys.sys_data_requirement').' + data_origin, data_ready'" />
            @if ($promote !== null)
                @if ($promote)
                    <x-dl::alert tone="info" :message="raw(tf('Will become LIVE: :list.', ['list' => collect($promote)->map(fn ($c, $t) => $t.' ('.$n($c).')')->implode(', ')]))" />
                    <x-dl::action-form action="{{ route('v2.data-origin.promote') }}">
                        <x-dl::action-bar align="start">
                            <x-dl::field.select name="only" label="Table" :options="['' => t('All tables')] + array_combine(array_keys($promote), array_keys($promote))" value="" />
                            <x-dl::button variant="success" icon="confirm" label="Make Real" type="submit" :onclick="$confirm('Make the complete LEGACY rows of this choice real data (LIVE)?')" />
                        </x-dl::action-bar>
                    </x-dl::action-form>
                @else
                    <x-dl::alert tone="success" message="No complete LEGACY row needs to be promoted." />
                @endif
            @endif
        </x-dl::card>
        <x-dl::card title="Required fields" icon="fa-asterisk" description="Untick Active to switch a requirement off. Saving recounts the completeness.">
            <x-dl::lookup-grid :rows="$reqs['rows']" :columns="$reqs['columns']" :save-url="$reqs['saveUrl']" :source="$reqs['source']" />
        </x-dl::card>
    </x-slot:legacy>

    <x-slot:rules>
        <x-dl::card title="Classification rules" icon="fa-filter" description="Rules only move LIVE rows to DUMMY or LEGACY; child rows of a DUMMY row follow it. Save the rules first, then Preview and Apply.">
            <x-dl::lookup-grid :rows="$rules['rows']" :columns="$rules['columns']" :save-url="$rules['saveUrl']" :source="$rules['source']" />
            <x-dl::action-bar align="start">
                <x-dl::button variant="secondary" icon="fa-magnifying-glass" label="Preview" :href="$here('rules', ['preview' => 'classify'])" />
            </x-dl::action-bar>
            @if ($classify !== null)
                @if ($classify)
                    <x-dl::table :columns="['table' => 'Table', 'DUMMY' => ['DUMMY', 'num' => true], 'LEGACY' => ['LEGACY', 'num' => true]]"
                        :rows="collect($classify)->map(fn ($c, $t) => ['table' => $t] + $c)->values()->all()" />
                    <x-dl::action-form action="{{ route('v2.data-origin.classify') }}">
                        <x-dl::action-bar align="start">
                            <x-dl::button preset="apply" type="submit" :onclick="$confirm('Apply this classification?')" />
                        </x-dl::action-bar>
                    </x-dl::action-form>
                @else
                    <x-dl::alert tone="success" message="No LIVE row matches the rules: everything is classified already." />
                @endif
            @endif
        </x-dl::card>
    </x-slot:rules>

    <x-slot:snapshot>
        <x-dl::card title="Dummy snapshot & reset" icon="fa-camera"
            :description="raw(tf('Last snapshot: :rows DUMMY rows', ['rows' => $n($snap['rows'])]).($snap['at'] ? ' · '.Locale::stamp($snap['at']).' · '.$snap['by'] : '').'. '.t('Reset returns DUMMY rows to that snapshot; LIVE and LEGACY rows are never touched.'))">
            <x-dl::action-form action="{{ route('v2.data-origin.snapshot') }}">
                <x-dl::action-bar align="start">
                    <x-dl::button variant="secondary" icon="fa-camera" label="Take New Snapshot" type="submit" :onclick="$confirm('Replace the snapshot with the current DUMMY data?')" />
                </x-dl::action-bar>
            </x-dl::action-form>
            <x-dl::action-form method="get" action="{{ route('v2.screen', 'data-origin') }}">
                <input type="hidden" name="tab" value="snapshot"><input type="hidden" name="preview" value="reset">
                <x-dl::action-bar align="start">
                    <x-dl::field.checkbox name="remove_new" text="Also delete DUMMY rows added after the snapshot" :value="$removeNew" />
                    <x-dl::button variant="secondary" icon="fa-magnifying-glass" label="Preview Reset" type="submit" />
                </x-dl::action-bar>
            </x-dl::action-form>
            @if ($reset !== null)
                @if ($reset['tables'])
                    <x-dl::alert tone="warn" :message="raw(tf('Will return :restored missing or re-flagged rows and restore :changed changed rows; new dummy rows: :new', ['restored' => $n($reset['restored']), 'changed' => $n($reset['changed']), 'new' => $n($reset['new'])]).' ('.t($removeNew ? 'will be deleted' : 'kept').').')" />
                    <x-dl::table :columns="['table' => 'Table', 'restored' => ['Returned', 'num' => true], 'changed' => ['Changed', 'num' => true], 'new' => ['New', 'num' => true]]"
                        :rows="collect($reset['tables'])->map(fn ($r, $t) => ['table' => $t] + $r)->values()->all()" />
                    <x-dl::action-form action="{{ route('v2.data-origin.reset') }}">
                        @if ($removeNew)<input type="hidden" name="remove_new" value="1">@endif
                        <x-dl::action-bar align="start">
                            <x-dl::button variant="danger" icon="fa-rotate-left" label="Reset Now" type="submit" :onclick="$confirm('Reset DUMMY data to the snapshot now?')" />
                        </x-dl::action-bar>
                    </x-dl::action-form>
                @else
                    <x-dl::alert tone="success" message="DUMMY data already matches the snapshot: nothing to reset." />
                @endif
            @endif
        </x-dl::card>
    </x-slot:snapshot>

    <x-slot:tables>
        <x-dl::action-form method="get" action="{{ route('v2.screen', 'data-origin') }}">
            <input type="hidden" name="tab" value="tables">
            <x-dl::action-bar align="start">
                <x-dl::field.select name="db" label="Database" :options="['' => t('All databases')] + array_combine(DataOriginService::DATABASES, DataOriginService::DATABASES)" :value="$db" />
                <x-dl::button variant="secondary" icon="fa-rotate" label="Show" type="submit" />
            </x-dl::action-bar>
        </x-dl::action-form>
        <x-dl::table :columns="['db' => 'Database', 'table' => 'Table', 'LIVE' => ['LIVE', 'num' => true], 'DUMMY' => ['DUMMY', 'num' => true], 'LEGACY' => ['LEGACY', 'num' => true]]"
            :rows="DataOrigin::markedTables($counts, $db)" empty="No DUMMY or LEGACY data." :source="raw(t('data_origin column of every flagged table'))" />
    </x-slot:tables>
</x-dl::tabs>
