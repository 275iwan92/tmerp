{{--
 REMARK
 Tujuan       : Mengelola daftar officer pengganti (PLT): siapa menggantikan jabatan approver selama pemegangnya berhalangan, pada rentang tanggal tertentu.
 Sumber hulu  : App\Support\V2\Data\ApprovalSetting::actingRows / saveActing; tabel tm2mst.flow_position_acting, tm2mst.org_position, tm2sys.sec_user; dibaca FlowEngineService::pendingForUser.
 Input        : Flash v2_flash dan errors; baris acting dari ApprovalSetting::actingRows().
 Output       : x-dl::form (kind master) dengan x-dl::alert dan x-dl::lines daftar PLT; simpan ke route v2.approval-acting.save, Back ke approval-setting.
 Dipakai oleh : Menu Setup > Approval Setting > Temporary Acting Officer (slug approval-acting, route v2.screen).
 Status       : Aktif.
--}}
@php
    use App\Support\V2\Data\ApprovalSetting;
    use App\Support\V2\Src;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="approval-acting-form" title="Temporary Acting Officer" subtitle="Who stands in for an approver position while its holder is away (leave, business trip)"
    panel="top" kind="master" status="active" :action="route('v2.approval-acting.save')" action-field="action" :hide="['deactivate', 'new']"
    :back="route('v2.screen', 'approval-setting')" :tabs="['general' => 'General']">
    <x-slot:general>
        <x-dl::alert tone="info" message="Fill in the position, the acting user and the absence dates. During those dates, documents waiting for that position appear in the acting user's Approval Center (the original holder can still decide them)." />
    </x-slot:general>
    <x-slot:detail>
        <x-dl::lines name="acting" title="Temporary Acting Officer List" key-field="acting_id" remove-field="remove" :duplicable="false" :min-rows="0" :rows="ApprovalSetting::actingRows()"
            add-label="+ Add Temporary Acting Officer" source="{{ Src::of('tm2mst.flow_position_acting', 'tm2mst.org_position', 'tm2sys.sec_user') }}"
            :columns="[
                ['key' => 'position_id', 'label' => t('Position being covered'), 'type' => 'select', 'options' => ApprovalSetting::positionOptions(), 'required' => true, 'width' => '320px'],
                ['key' => 'acting_user_id', 'label' => 'Acting user', 'type' => 'select', 'options' => ApprovalSetting::userOptions(), 'required' => true, 'width' => '240px'],
                ['key' => 'start_date', 'label' => 'Start', 'type' => 'date', 'required' => true, 'width' => '150px'],
                ['key' => 'end_date', 'label' => 'End', 'type' => 'date', 'required' => true, 'width' => '150px'],
                ['key' => 'reason', 'label' => t('Reason (leave, business trip, ...)'), 'width' => '200px'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'display', 'width' => '100px'],
                ['key' => 'activate', 'label' => t('Active'), 'type' => 'bool', 'more' => true],
                ['key' => 'original', 'label' => 'Original holder', 'type' => 'display', 'more' => true],
            ]" />
    </x-slot:detail>
</x-dl::form>
