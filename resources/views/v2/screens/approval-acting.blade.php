{{-- Officer Pengganti / PLT (user 2026-09-30): selama officer asli berhalangan, antrian approval jabatannya pindah ke user
     pengganti pada rentang tanggal ini (tm2mst.flow_position_acting, dibaca FlowEngineService::pendingForUser). Keputusan
     PLT tercatat di log dengan tanda PLT. Data & simpan = App\Support\V2\Data\ApprovalSetting::actingRows/saveActing. --}}
@php
    use App\Support\V2\Data\ApprovalSetting;
    use App\Support\V2\Src;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

<x-dl::form id="approval-acting-form" title="Officer Pengganti (PLT)" subtitle="Jabatan approver yang sementara digantikan orang lain"
    panel="top" kind="master" status="active" :action="route('v2.approval-acting.save')" action-field="action" :hide="['deactivate', 'new']"
    :back="route('v2.screen', 'approval-setting')" :tabs="['general' => 'General']">
    <x-slot:general>
        <x-dl::alert tone="info" message="Isi jabatan, user pengganti, dan tanggal berhalangan. Selama tanggal itu, dokumen yang menunggu jabatan tersebut muncul di Approval Center user pengganti (juga tetap bisa diputuskan pemegang aslinya)." />
    </x-slot:general>
    <x-slot:detail>
        <x-dl::lines name="acting" title="Daftar PLT" key-field="acting_id" remove-field="remove" :duplicable="false" :min-rows="0" :rows="ApprovalSetting::actingRows()"
            add-label="+ Tambah PLT" source="{{ Src::of('tm2mst.flow_position_acting', 'tm2mst.org_position', 'tm2sys.sec_user') }}"
            :columns="[
                ['key' => 'position_id', 'label' => 'Jabatan yang digantikan', 'type' => 'select', 'options' => ApprovalSetting::positionOptions(), 'required' => true, 'width' => '320px'],
                ['key' => 'acting_user_id', 'label' => 'User pengganti', 'type' => 'select', 'options' => ApprovalSetting::userOptions(), 'required' => true, 'width' => '240px'],
                ['key' => 'start_date', 'label' => 'Mulai', 'type' => 'date', 'required' => true, 'width' => '150px'],
                ['key' => 'end_date', 'label' => 'Selesai', 'type' => 'date', 'required' => true, 'width' => '150px'],
                ['key' => 'reason', 'label' => 'Alasan (cuti, dinas, ...)', 'width' => '200px'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'display', 'width' => '100px'],
                ['key' => 'activate', 'label' => 'Aktif', 'type' => 'bool', 'more' => true],
                ['key' => 'original', 'label' => 'Pemegang asli', 'type' => 'display', 'more' => true],
            ]" />
    </x-slot:detail>
</x-dl::form>
