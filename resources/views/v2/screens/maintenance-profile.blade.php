{{-- Asset Maintenance Profile v2 (Fase 1 Master Data, mockup maintenance_v2_master.jpg). Satu profil per aset: apa yang dicek (Watch Points),
     spare part, dokumen (SOP / WI), riwayat. Aset = mesin produksi, alat penunjang, kendaraan, fasilitas. Mesin tertaut Master Mesin:
     kode, nama, kelas dan kritikalitas dibaca dari sana. Mengubah profil = hak approve modul MAINT. --}}
@php
    use App\Models\Mnt\MntAsset;
    use App\Models\Mnt\MntLog;
    use App\Models\Mnt\MntWatchPoint;
    use App\Models\Acc\FinAsset;
    use App\Services\Maintenance\AssetProfileService;
    use App\Services\Maintenance\MaintenanceAccess;
    use App\Support\V2\Data\Maintenance;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'maintenance-profile');
    $asset = request('id') ? MntAsset::with(['watchPoints', 'parts', 'documents'])->find((int) request('id')) : null;
    abort_if(request('id') && ! $asset, 404);
    $head = MaintenanceAccess::canManage(auth()->user());
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $asset && ! request('new'))
    @if ($head)
        <x-dl::action-form action="{{ route('v2.mnt.asset.sync') }}">
            <x-dl::button size="sm" variant="secondary" icon="fa-rotate" label="Add machines from Master Machine" type="submit" />
        </x-dl::action-form>
    @endif
    {{-- Daftar = objek Grid Transactional App\Support\V2\Grid\Maint\AssetListGrid (DocListGrid), tampil lewat view generik _dbgrid. --}}
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Maint\AssetListGrid]])
@else
    @php
        $new = ! $asset;
        $a = $asset ?? new MntAsset(['asset_class' => 'FACILITY', 'meter_type' => 'NONE', 'activate' => true]);
        $linked = $a->isLinkedMachine();
        $done = $asset ? AssetProfileService::completenessOf($asset) : null;
        $crit = $asset ? Maintenance::criticalityOf($asset) : null;
        $fixed = ['' => '—'] + FinAsset::orderBy('asset_code')->get(['asset_id', 'asset_code', 'asset_name'])->mapWithKeys(fn ($f) => [$f->asset_id => $f->asset_code.' · '.$f->asset_name])->all();
    @endphp
    <x-dl::form id="mnt-asset-form" :title="'Asset Maintenance Profile · '.($a->asset_code ? raw($a->asset_code) : 'New')" :subtitle="$new ? null : raw($a->asset_name)"
        panel="top" kind="master" :status="$new ? 'new' : ($a->activate ? 'active' : 'inactive')" :can-input="$head" :log="$asset ? MntLog::forDoc('ASSET', $asset->id_asset) : []"
        :action="route('v2.mnt.asset.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hidden="$asset ? ['id' => $asset->id_asset] : []"
        :tabs="['general' => 'Profile', 'notes' => 'Notes']"
        :created-by="$a->created_by" :created-at="$a->created_at?->toDateTimeString()" :updated-by="$a->updated_by" :updated-at="$a->updated_at?->toDateTimeString()">
        <x-slot:strip>
            <x-dl::strip-kv kind="doc" label="Asset" :value="$a->asset_code ?: t('(new)')" />
            <x-dl::strip-kv label="Class" :value="t(MntAsset::CLASSES[$a->asset_class] ?? $a->asset_class)" />
            <x-dl::strip-kv label="Criticality" :value="$crit ? t(MntAsset::CRITICALITY[$crit] ?? $crit) : '—'" />
            <x-dl::strip-kv kind="grand" label="Complete" :value="$done ? $done['done'].' / '.$done['total'] : '—'" />
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="asset_code" label="Asset Code" required :case-toggle="false" :readonly="$linked" :value="$a->asset_code" placeholder="AC-01" :source="Src::of('tm2mnt.mnt_asset.asset_code')" />
                <x-dl::field.text name="asset_name" label="Asset Name" required :readonly="$linked" :value="$a->asset_name" :source="Src::of('tm2mnt.mnt_asset.asset_name')" />
                <x-dl::field.select name="asset_class" label="Class" required :readonly="$linked" :options="array_map('t', MntAsset::CLASSES)" :value="$a->asset_class" :source="Src::of('tm2mnt.mnt_asset.asset_class')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                @if ($linked)
                    <x-dl::field.display name="criticality_view" label="Criticality" :value="$crit ? t(MntAsset::CRITICALITY[$crit] ?? $crit) : t('Set it in Master Machine')" :source="Src::of('tm2mst.prod_machine.criticality')" />
                @else
                    <x-dl::field.select name="criticality" label="Criticality" :options="['' => '—'] + array_map('t', MntAsset::CRITICALITY)" :value="$a->criticality" :source="Src::of('tm2mnt.mnt_asset.criticality')" />
                @endif
                <x-dl::field.select name="meter_type" label="Meter" required :options="array_map('t', MntAsset::METERS)" :value="$a->meter_type" :source="Src::of('tm2mnt.mnt_asset.meter_type')" />
                <x-dl::field.text name="location" label="Location" :value="$a->location" :source="Src::of('tm2mnt.mnt_asset.location')" />
                <x-dl::field.select name="fixed_asset_id" label="Fixed Asset (Finance, optional)" :options="$fixed" :value="$a->fixed_asset_id" :source="Src::of('tm2acc.fin_asset')" />
            </x-dl::form.row>
            @if ($linked)
                <p class="dl-hint">@t('This asset is a machine from Master Machine. Its code, name and class are kept in step with the master.')</p>
            @endif
        </x-slot:general>
        <x-slot:notes>
            <x-dl::field.text name="notes" label="Notes" multiline :value="$a->notes" :source="Src::of('tm2mnt.mnt_asset.notes')" />
        </x-slot:notes>
        @if ($asset)
            <x-slot:detail>
                <x-dl::tabs :tabs="['watch' => 'Watch Points ('.raw($asset->watchPoints->count()).')', 'parts' => 'Spare Parts ('.raw($asset->parts->count()).')', 'docs' => 'Documents ('.raw($asset->documents->count()).')']">
                    <x-slot:watch>
                        <x-dl::lines name="watch" title="Watch Points" key-field="id_watch_point" remove-field="remove" :addable="false" :duplicable="false" :min-rows="0"
                            :rows="Maintenance::watchRows($asset)" :readonly="! $head" source="{{ Src::of('tm2mnt.mnt_watch_point') }}"
                            :columns="[
                                ['key' => 'component_code', 'label' => 'Component', 'type' => 'select', 'options' => Maintenance::componentOptions(), 'width' => '170px'],
                                ['key' => 'check_text', 'label' => 'What to check', 'required' => true, 'width' => '250px'],
                                ['key' => 'method_text', 'label' => 'How', 'width' => '150px'],
                                ['key' => 'normal_min', 'label' => 'Normal min', 'type' => 'number', 'width' => '100px'],
                                ['key' => 'normal_max', 'label' => 'Normal max', 'type' => 'number', 'width' => '100px'],
                                ['key' => 'measure_uom', 'label' => 'Unit', 'width' => '80px'],
                                ['key' => 'frequency', 'label' => 'Frequency', 'type' => 'select', 'options' => array_map('t', MntWatchPoint::FREQUENCIES), 'width' => '120px'],
                                ['key' => 'checked_by_role', 'label' => 'Checked by', 'type' => 'select', 'options' => array_map('t', MntWatchPoint::ROLES), 'width' => '120px'],
                                ['key' => 'activate', 'label' => 'Active', 'type' => 'bool', 'width' => '80px'],
                            ]">
                            @if ($head)
                                <x-slot:tools>
                                    <x-dl::field.text name="new_watch" label="+ Watch point" placeholder="What to check..." />
                                    <x-dl::field.select name="new_watch_component" label="Component" :options="Maintenance::componentOptions()" />
                                    <x-dl::field.select name="new_watch_frequency" label="Frequency" :options="array_map('t', MntWatchPoint::FREQUENCIES)" value="DAILY" />
                                    <x-dl::field.select name="new_watch_role" label="Checked by" :options="array_map('t', MntWatchPoint::ROLES)" value="OPERATOR" />
                                    <x-dl::button size="sm" variant="secondary" label="+ Watch Point" type="submit" name="action" value="add_watch" formnovalidate />
                                    <x-dl::field.select name="copy_from" label="Copy from asset" :options="['' => '—'] + array_diff_key(Maintenance::assetOptions(), [$asset->id_asset => 1])" />
                                    <x-dl::button size="sm" variant="secondary" icon="fa-regular fa-copy" label="Copy" type="submit" name="action" value="copy_watch" formnovalidate />
                                </x-slot:tools>
                            @endif
                        </x-dl::lines>
                    </x-slot:watch>
                    <x-slot:parts>
                        <x-dl::lines name="parts" title="Spare Parts" key-field="id_asset_part" remove-field="remove_parts" :addable="false" :duplicable="false" :min-rows="0"
                            :rows="Maintenance::partRows($asset)" :readonly="! $head" source="{{ Src::of('tm2mnt.mnt_asset_part', 'tm2mst.mat_raw') }}"
                            :columns="[
                                ['key' => 'item', 'label' => 'Item', 'type' => 'display', 'width' => '320px'],
                                ['key' => 'component_code', 'label' => 'Component', 'type' => 'select', 'options' => Maintenance::componentOptions(), 'width' => '170px'],
                                ['key' => 'qty_per_use', 'label' => 'Qty / use', 'type' => 'number', 'width' => '90px'],
                                ['key' => 'min_stock_suggested', 'label' => 'Suggested min stock', 'type' => 'number', 'width' => '130px'],
                                ['key' => 'expected_life_days', 'label' => 'Expected life (days)', 'type' => 'number', 'width' => '130px'],
                                ['key' => 'is_critical', 'label' => 'Critical', 'type' => 'bool', 'width' => '80px'],
                            ]">
                            @if ($head)
                                <x-slot:tools>
                                    <x-dl::field.lookup name="material_id" lookup="material" label="+ Spare part" placeholder="Type 2+ letters of code / name..." :source="Src::of('tm2mst.mat_raw')" />
                                    <x-dl::field.select name="new_part_component" label="Component" :options="Maintenance::componentOptions()" />
                                    <x-dl::field.number name="new_part_qty" label="Qty / use" :value="1" />
                                    <x-dl::button size="sm" variant="secondary" label="+ Spare Part" type="submit" name="action" value="add_part" formnovalidate />
                                </x-slot:tools>
                            @endif
                        </x-dl::lines>
                    </x-slot:parts>
                    <x-slot:docs>
                        <x-dl::lines name="docs" title="Documents" key-field="id_document" remove-field="remove_docs" :addable="false" :duplicable="false" :min-rows="0"
                            :rows="Maintenance::documentRows($asset)" :readonly="! $head" source="{{ Src::of('tm2mnt.mnt_document', 'tm2mnt.mnt_asset_document') }}"
                            :columns="[
                                ['key' => 'doc_no', 'label' => 'Document', 'type' => 'display', 'width' => '170px'],
                                ['key' => 'kind', 'label' => 'Kind', 'type' => 'display', 'width' => '140px'],
                                ['key' => 'title', 'label' => 'Title', 'type' => 'display', 'width' => '360px'],
                                ['key' => 'revision', 'label' => 'Rev', 'type' => 'display', 'width' => '60px'],
                                ['key' => 'status', 'label' => 'Status', 'type' => 'display', 'width' => '100px'],
                            ]">
                            @if ($head)
                                <x-slot:tools>
                                    <x-dl::field.select name="link_document" label="+ Link document" :options="['' => '—'] + Maintenance::linkableDocuments($asset)" />
                                    <x-dl::button size="sm" variant="secondary" label="+ Link" type="submit" name="action" value="link_doc" formnovalidate />
                                </x-slot:tools>
                            @endif
                        </x-dl::lines>
                        <p class="dl-hint">@t('Open SOP and Work Instructions to read or approve a document.')</p>
                    </x-slot:docs>
                </x-dl::tabs>
            </x-slot:detail>
        @endif
    </x-dl::form>
@endif
