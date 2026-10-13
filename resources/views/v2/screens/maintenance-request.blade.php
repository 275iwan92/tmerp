{{-- Maintenance Request v2 (Fase 3). Siapa pun di modul boleh melapor masalah; kepala maintenance menilai: Accept membuat BMO Draft, Reject butuh alasan.
     Darurat (P1: keselamatan, atau mesin berhenti pada aset kritis) langsung menjadi BMO Confirmed. Aturan = App\Services\Maintenance\RequestService. --}}
@php
    use App\Models\Mnt\MntLog;
    use App\Models\Mnt\MntOrder;
    use App\Models\Mnt\MntRequest;
    use App\Services\Maintenance\MaintenanceAccess;
    use App\Services\Maintenance\RequestService;
    use App\Support\V2\Data\Maintenance;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'maintenance-request');
    $req = request('id') ? MntRequest::find((int) request('id')) : null;
    abort_if(request('id') && ! $req, 404);
    $head = MaintenanceAccess::canManage(auth()->user());
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $req && ! request('new'))
    {{-- Daftar = objek Grid Transactional App\Support\V2\Grid\Maint\RequestListGrid (DocListGrid), tampil lewat view generik _dbgrid. --}}
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Maint\RequestListGrid]])
@elseif (! $req)
    <x-dl::action-form action="{{ route('v2.mnt.request.create') }}">
        <x-dl::card title="+ New Record — Maintenance Request" description="Report a problem. Leave Priority on Auto: safety, or a stopped critical machine, is P1 and becomes a Breakdown Maintenance Order at once.">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="asset_id" label="Asset" required :options="Maintenance::assetOptions()" :value="request('asset_id')" :source="Src::of('tm2mnt.mnt_asset')" />
                <x-dl::field.select name="request_kind" label="Kind" required :options="array_map('t', RequestService::OPEN_KINDS)" value="BREAKDOWN" :source="Src::of('tm2mnt.mnt_request.request_kind')" />
                <x-dl::field.select name="priority" label="Priority" :options="['' => t('Auto')] + array_map('t', MntOrder::PRIORITIES)" value="" :source="Src::of('tm2mnt.mnt_request.priority')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                <x-dl::field.checkbox name="asset_stopped" text="The asset is stopped" :value="request()->boolean('asset_stopped')" :source="Src::of('tm2mnt.mnt_request.asset_stopped')" />
                <x-dl::field.date-time name="stopped_at" label="Stopped Since" :value="now()->format('Y-m-d H:i')" :source="Src::of('tm2mnt.mnt_request.stopped_at')" />
            </x-dl::form.row>
            <x-dl::field.text name="symptom" label="What is wrong? (symptom)" multiline required :value="request('symptom')" :source="Src::of('tm2mnt.mnt_request.symptom')" />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Send Request" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@else
    @php
        $assets = Maintenance::assetOptions();
        $open = $req->status === 'OPEN';
        $order = $req->order_id ? MntOrder::find($req->order_id) : null;
        $orderUrl = $order ? route('v2.screen', 'maintenance-bmo').'?id='.$order->id_order : null;
    @endphp
    <x-dl::form id="mnt-request-form" :title="'Maintenance Request · '.raw($req->doc_no)" :subtitle="raw($assets[$req->asset_id] ?? '#'.$req->asset_id)"
        panel="top" kind="document" :status="match ($req->status) { 'ACCEPTED' => 'confirmed', 'REJECTED' => 'cancelled', default => 'draft' }" :stage="t(MntRequest::STATUS[$req->status] ?? $req->status)" :log="MntLog::forDoc('REQUEST', $req->id_request)"
        :action="route('v2.mnt.request.save', $req->id_request)" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hide="['delete', 'confirm', 'cancel', 'duplicate', 'email', 'print', 'save']"
        :tabs="['general' => 'Request']" :created-by="$req->created_by" :created-at="$req->created_at?->toDateTimeString()" :updated-by="$req->updated_by" :updated-at="$req->updated_at?->toDateTimeString()">
        @if ($open && $head)
            <x-slot:special>
                <x-dl::button size="sm" variant="success" icon="fa-check" label="Accept" type="submit" name="action" value="accept" formnovalidate />
                <x-dl::button size="sm" variant="danger" icon="fa-ban" label="Reject" type="submit" name="action" value="reject" formnovalidate />
            </x-slot:special>
        @endif
        <x-slot:strip>
            <x-dl::strip-kv kind="doc" label="Request" :value="$req->doc_no" />
            <x-dl::strip-kv label="Priority" :value="$req->priority" />
            <x-dl::strip-kv label="Kind" :value="t(MntRequest::KINDS[$req->request_kind] ?? $req->request_kind)" />
            <x-dl::strip-kv kind="grand" label="Order" :value="$order?->doc_no ?: '—'" />
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Asset" :value="raw($assets[$req->asset_id] ?? '#'.$req->asset_id)" :source="Src::of('tm2mnt.mnt_asset')" />
                <x-dl::field.display label="Reported" :value="Locale::dt($req->request_date)" :source="Src::of('tm2mnt.mnt_request.request_date')" />
                <x-dl::field.display label="Reported By" :value="raw($req->created_by)" :source="Src::of('tm2mnt.mnt_request.created_by')" />
                <x-dl::field.display label="Source" :value="$req->source_type === 'DAILY_CHECK' ? t('Daily Check') : t('Manual')" :source="Src::of('tm2mnt.mnt_request.source_type')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Asset Stopped" :value="$req->asset_stopped ? t('Yes') : t('No')" :source="Src::of('tm2mnt.mnt_request.asset_stopped')" />
                <x-dl::field.display label="Stopped Since" :value="$req->stopped_at ? Locale::dt($req->stopped_at) : '—'" :source="Src::of('tm2mnt.mnt_request.stopped_at')" />
                <x-dl::field.display label="Status" :value="t(MntRequest::STATUS[$req->status] ?? $req->status)" :source="Src::of('tm2mnt.mnt_request.status')" />
                <x-dl::field.display label="Order" :value="$order?->doc_no ?: '—'" :source="Src::of('tm2mnt.mnt_request.order_id')" />
            </x-dl::form.row>
            <x-dl::field.display label="Symptom" :value="raw($req->symptom)" :source="Src::of('tm2mnt.mnt_request.symptom')" />
            @if ($req->status === 'REJECTED')
                <x-dl::field.display label="Reject Reason" :value="raw($req->reject_reason)" :source="Src::of('tm2mnt.mnt_request.reject_reason')" />
            @endif
            @if ($open && $head)
                <x-dl::field.text name="reject_reason" label="Reject Reason (only when rejecting)" :source="Src::of('tm2mnt.mnt_request.reject_reason')" />
            @endif
            @if ($orderUrl)
                <x-dl::button size="sm" variant="secondary" icon="open" label="Open Order" :href="$orderUrl" />
            @endif
        </x-slot:general>
    </x-dl::form>
@endif
