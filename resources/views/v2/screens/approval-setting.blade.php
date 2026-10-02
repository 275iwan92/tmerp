{{-- Setting Approval (user 2026-09-30, pengganti m_authorize/_detail lama). Data & aturan = App\Support\V2\Data\ApprovalSetting
     (tm2mst.flow_config + flow_config_step). GRID modul -> form: header (keterangan, status akhir, aktif) + Level 1..n
     (jabatan, batas nilai, label cetak). Approver = jabatan: officer pengganti diatur di layar Officer Pengganti (PLT). --}}
@php
    use App\Models\Master\FlowConfig;
    use App\Support\V2\Data\ApprovalSetting;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'approval-setting');
    $cfg = request('id') ? FlowConfig::find((int) request('id')) : null;
    abort_if(request('id') && ! $cfg, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $cfg && ! request('new'))
    <x-dl::grid id="approval-setting-list" :rows="ApprovalSetting::rows()" search="Search module, position..." actions="worklist" :active-key="null"
        :new-url="$home.'?new=1'" :open-url="$home.'?id={id}'" source="{{ Src::of('tm2mst.flow_config', 'tm2mst.flow_config_step') }}"
        :columns="[
            ['key' => 'module', 'label' => 'Document Code / Name', 'type' => 'doc', 'party' => 'name'],
            ['key' => 'levels', 'label' => t('Number of Levels'), 'type' => 'number'],
            ['key' => 'approvers', 'label' => t('Approver per Level (position)')],
            ['key' => 'final', 'label' => t('Final Status')],
            ['key' => 'active', 'label' => 'Status', 'type' => 'badge', 'tones' => ['Aktif' => 'success', 'Nonaktif' => 'neutral']],
        ]"
        :stats="[['label' => t('All')], ['label' => t('Active'), 'where' => ['active' => 'Aktif'], 'tone' => 'success'], ['label' => t('Inactive'), 'where' => ['active' => 'Nonaktif'], 'tone' => 'neutral']]" />
@elseif (! $cfg)
    <x-dl::action-form action="{{ route('v2.approval-setting.create') }}">
        <x-dl::card title="+ New Record — Setting Approval" description="The document code (e.g. PR, PO, CO) must match the code used by its document screen.">
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="module_code" label="Document Code" required :case-toggle="false" placeholder="PR" />
                <x-dl::field.text name="notes" label="Description" placeholder="Approval ..." />
                <x-dl::field.text name="final_status" label="Status after the last level" :case-toggle="false" value="APPROVED" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Save" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@else
    @php
        $name = ApprovalSetting::MODULE_NAMES[$cfg->module_code] ?? $cfg->module_code;
        $levels = ApprovalSetting::levels($cfg);
    @endphp
    <x-dl::form id="approval-setting-form" :title="'Setting Approval · '.raw($cfg->module_code)" :subtitle="$name"
        panel="top" kind="master" :status="$cfg->activate ? 'active' : 'inactive'"
        :action="route('v2.approval-setting.save', $cfg->flow_config_id)" action-field="action" :back="$home" :new-url="$home.'?new=1'"
        :tabs="['general' => 'General']"
        :created-by="$cfg->created_by" :created-at="$cfg->created_at?->toDateTimeString()" :updated-by="$cfg->updated_by" :updated-at="$cfg->updated_at?->toDateTimeString()">
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>Kode</span><b>{{ $cfg->module_code }}</b></div>
            <div class="dl-strip__kv"><span>Dokumen</span><b>{{ $name }}</b></div>
            <div class="dl-strip__kv dl-strip__grand"><span>Jumlah Level</span><b>{{ count($levels) }}</b></div>
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="notes" label="Description" :value="$cfg->notes" :source="Src::of('tm2mst.flow_config.notes')" />
                <x-dl::field.text name="final_status" label="Document status after the last level" :case-toggle="false" :value="$cfg->final_status" :source="Src::of('tm2mst.flow_config.final_status')" />
                <x-dl::field.display label="Status when rejected" :value="($cfg->reject_status ?? 'REJECTED').' (final)'" :source="Src::of('tm2mst.flow_config.reject_status')" />
            </x-dl::form.row>
        </x-slot:general>
        <x-slot:detail>
            <x-dl::lines name="levels" title="Approval Levels (in order from the top)" key-field="flow_step_id" remove-field="remove" :duplicable="false" :min-rows="0" :rows="$levels"
                add-label="+ Add Level" source="{{ Src::of('tm2mst.flow_config_step', 'tm2mst.org_position', 'tm2mst.flow_position_acting') }}"
                :columns="[
                    ['key' => 'level', 'label' => 'Level', 'type' => 'display', 'width' => '80px'],
                    ['key' => 'position_id', 'label' => t('Approver position'), 'type' => 'select', 'options' => ApprovalSetting::positionOptions(), 'required' => true, 'width' => '360px'],
                    ['key' => 'min_amount', 'label' => 'Applies when value ≥', 'type' => 'money', 'width' => '150px'],
                    ['key' => 'max_amount', 'label' => 'Up to value (empty = no limit)', 'type' => 'money', 'width' => '170px'],
                    ['key' => 'print_label', 'label' => t('Print label'), 'type' => 'select', 'options' => ApprovalSetting::PRINT_LABELS, 'width' => '140px'],
                    ['key' => 'plt', 'label' => t('Acting today'), 'type' => 'display', 'width' => '160px'],
                    ['key' => 'holder', 'label' => t('Position holder'), 'type' => 'display', 'more' => true],
                    ['key' => 'used', 'label' => t('Used by documents'), 'type' => 'display', 'more' => true],
                ]" />
            <x-dl::alert tone="info" message="Levels whose value is outside the limit are skipped automatically (e.g. PO: Level 3 only when value > Rp 1 million). Levels already used by documents are not deleted, only deactivated, so history stays intact." />        </x-slot:detail>
    </x-dl::form>
@endif
