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
    <x-dl::grid id="approval-setting-list" :rows="ApprovalSetting::rows()" search="Cari modul, jabatan..." actions="worklist" :active-key="null"
        :new-url="$home.'?new=1'" :open-url="$home.'?id={id}'" source="{{ Src::of('tm2mst.flow_config', 'tm2mst.flow_config_step') }}"
        :columns="[
            ['key' => 'module', 'label' => 'Kode'],
            ['key' => 'name', 'label' => 'Dokumen'],
            ['key' => 'levels', 'label' => 'Jumlah Level', 'type' => 'number'],
            ['key' => 'approvers', 'label' => 'Approver per Level (jabatan)'],
            ['key' => 'final', 'label' => 'Status Akhir'],
            ['key' => 'active', 'label' => 'Status', 'type' => 'badge', 'tones' => ['Aktif' => 'success', 'Nonaktif' => 'neutral']],
        ]"
        :stats="[['label' => 'Semua'], ['label' => 'Aktif', 'where' => ['active' => 'Aktif'], 'tone' => 'success'], ['label' => 'Nonaktif', 'where' => ['active' => 'Nonaktif'], 'tone' => 'neutral']]" />
@elseif (! $cfg)
    <form method="post" action="{{ route('v2.approval-setting.create') }}">
        @csrf
        <x-dl::card title="+ New Record — Setting Approval" description="Kode dokumen (mis. PR, PO, CO) harus sama dengan kode yang dipakai layar dokumennya.">
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="module_code" label="Kode Dokumen" required :case-toggle="false" placeholder="PR" />
                <x-dl::field.text name="notes" label="Keterangan" placeholder="Approval ..." />
                <x-dl::field.text name="final_status" label="Status setelah level terakhir" :case-toggle="false" value="APPROVED" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Save" />
            </x-dl::action-bar>
        </x-dl::card>
    </form>
@else
    @php
        $name = ApprovalSetting::MODULE_NAMES[$cfg->module_code] ?? $cfg->module_code;
        $levels = ApprovalSetting::levels($cfg);
    @endphp
    <x-dl::form id="approval-setting-form" :title="'Setting Approval · '.$cfg->module_code" :subtitle="$name"
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
                <x-dl::field.text name="notes" label="Keterangan" :value="$cfg->notes" :source="Src::of('tm2mst.flow_config.notes')" />
                <x-dl::field.text name="final_status" label="Status dokumen setelah level terakhir" :case-toggle="false" :value="$cfg->final_status" :source="Src::of('tm2mst.flow_config.final_status')" />
                <x-dl::field.display label="Status bila ditolak" :value="($cfg->reject_status ?? 'REJECTED').' (final)'" :source="Src::of('tm2mst.flow_config.reject_status')" />
            </x-dl::form.row>
        </x-slot:general>
        <x-slot:detail>
            <x-dl::lines name="levels" title="Level Approval (urut dari atas)" key-field="flow_step_id" remove-field="remove" :duplicable="false" :min-rows="0" :rows="$levels"
                add-label="+ Tambah Level" source="{{ Src::of('tm2mst.flow_config_step', 'tm2mst.org_position', 'tm2mst.flow_position_acting') }}"
                :columns="[
                    ['key' => 'level', 'label' => 'Level', 'type' => 'display', 'width' => '80px'],
                    ['key' => 'position_id', 'label' => 'Jabatan approver', 'type' => 'select', 'options' => ApprovalSetting::positionOptions(), 'required' => true, 'width' => '360px'],
                    ['key' => 'min_amount', 'label' => 'Berlaku bila nilai ≥', 'type' => 'money', 'width' => '150px'],
                    ['key' => 'max_amount', 'label' => 's.d. nilai (kosong = tanpa batas)', 'type' => 'money', 'width' => '170px'],
                    ['key' => 'print_label', 'label' => 'Label cetak', 'type' => 'select', 'options' => ApprovalSetting::PRINT_LABELS, 'width' => '140px'],
                    ['key' => 'plt', 'label' => 'PLT hari ini', 'type' => 'display', 'width' => '160px'],
                    ['key' => 'holder', 'label' => 'Pemegang jabatan', 'type' => 'display', 'more' => true],
                    ['key' => 'used', 'label' => 'Sudah dipakai dokumen', 'type' => 'display', 'more' => true],
                ]" />
            <x-dl::alert tone="info" message="Level yang nilainya di luar batas dilewati otomatis (mis. PO: Level 3 hanya bila nilai > Rp 1 juta). Level yang sudah dipakai dokumen tidak dihapus, hanya dinonaktifkan supaya riwayat tetap utuh." />        </x-slot:detail>
    </x-dl::form>
@endif
