{{-- Maintenance Request v2 (docs/maintenance_tier1.md §3/§6, mockup maintenance_v2.jpg bagian 5 kiri). Aturan = MaintenanceService.
     Siapa saja melapor dalam 30 detik: mesin + apa yang terjadi + mesin berhenti? -> Save. Mesin berhenti = downtime langsung dibuka;
     prioritas dihitung otomatis (P1 = mesin berhenti) dan P1 LANGSUNG jadi Work Order. Selain P1: kepala maintenance Accept
     (pilih teknisi & jadwal -> MWO Scheduled) atau Reject (alasan). --}}
@php
    use App\Models\Prd\MntLog;
    use App\Models\Prd\MntRequest;
    use App\Models\Prd\MntWo;
    use App\Services\Maintenance\MaintenanceService;
    use App\Support\V2\Data\Maintenance;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'maintenance-request');
    $req = request('id') ? MntRequest::find((int) request('id')) : null;
    abort_if(request('id') && ! $req, 404);
    $head = MaintenanceService::canDecide(auth()->user());
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $req && ! request('new'))
    <x-dl::grid id="mnt-req-list" :rows="Maintenance::requestRows()" search="Search request, machine, symptom..." actions="worklist" :active-key="null"
        :new-url="$home.'?new=1'" :open-url="$home.'?id={id}'" source="{{ Src::of('tm2prd.mnt_request', 'tm2mst.prod_machine') }}"
        :columns="[
            ['key' => 'no', 'label' => 'Request No. / Machine', 'type' => 'doc', 'party' => 'machine'],
            ['key' => 'symptom', 'label' => 'Symptom'],
            ['key' => 'at', 'label' => 'Reported'],
            ['key' => 'kind', 'label' => 'Kind', 'type' => 'badge', 'tones' => ['Breakdown' => 'danger', 'Corrective' => 'warn', 'Safety' => 'danger', 'Improvement' => 'info']],
            ['key' => 'priority', 'label' => 'Priority', 'type' => 'badge', 'tones' => Maintenance::PRIO_TONES],
            ['key' => 'stopped', 'label' => 'Machine', 'type' => 'badge', 'tones' => ['Stopped' => 'danger', 'Running' => 'success']],
            ['key' => 'reporter', 'label' => 'Reported by', 'visible' => false],
            ['key' => 'age', 'label' => 'Waiting', 'visible' => false],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => Maintenance::REQ_TONES],
            ['key' => 'wo', 'label' => 'Work Order'],
        ]"
        :stats="[['label' => 'All'], ['label' => 'Open', 'where' => ['status' => 'Open'], 'tone' => 'warn'], ['label' => 'Machine stopped', 'where' => ['stopped' => 'Stopped', 'status' => 'Open'], 'tone' => 'danger'], ['label' => 'Accepted', 'where' => ['status' => 'Accepted'], 'tone' => 'success'], ['label' => 'Rejected', 'where' => ['status' => 'Rejected'], 'tone' => 'neutral']]"
        :filters="['machine' => 'Machine', 'kind' => 'Kind', 'priority' => 'Priority', 'status' => 'Status']" />
@elseif (! $req)
    <x-dl::action-form action="{{ route('v2.mnt.request.create') }}">
        <x-dl::card title="Report Problem — Maintenance Request" description="Machine, what happened, and whether it stopped. Priority is set automatically; a stopped machine (P1) becomes a Work Order at once.">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="machine_id" label="Machine" required :options="Maintenance::machines()" :value="request('machine')" :source="Src::of('tm2mst.prod_machine')" />
                <x-dl::field.select name="request_kind" label="Kind" required :options="MntRequest::KINDS" value="BREAKDOWN" :source="Src::of('tm2prd.mnt_request.request_kind')" />
                <x-dl::field.lookup name="reported_by" lookup="employee" label="Reported by" :value="auth()->user()->employee_id" :source="Src::of('tm2mst.emp_employee')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.toggle name="machine_stopped" label="Machine stopped?" on-label="Yes, stopped" off-label="No, still running" :value="false" :source="Src::of('tm2prd.mnt_request.machine_stopped')" />
                <x-dl::field.date-time name="stopped_at" label="Stopped at" :value="now()->format('Y-m-d H:i')" :source="Src::of('tm2prd.mnt_request.stopped_at')" />
            </x-dl::form.row>
            <x-dl::field.text name="symptom" label="What happened" required multiline :source="Src::of('tm2prd.mnt_request.symptom')" />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Save" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@else
    @php
        $open = $req->status === 'OPEN';
        $machines = Maintenance::machines();
        $status = match ($req->status) { 'ACCEPTED' => 'closed', 'REJECTED' => 'cancelled', default => 'draft' };
    @endphp
    <x-dl::form id="mnt-req-form" :title="'Maintenance Request · '.raw($req->doc_no)" :subtitle="raw($machines[$req->machine_id] ?? '#'.$req->machine_id)"
        panel="top" kind="document" :status="$status" :stage="$open ? 'Open' : ucfirst(strtolower($req->status))" :log="MntLog::forDoc('REQUEST', $req->id_request)"
        :action="route('v2.mnt.request.save', $req->id_request)" action-field="action" :back="$home" :new-url="$home.'?new=1'"
        :hide="['delete', 'confirm', 'duplicate', 'email', 'print']" :tabs="['general' => 'General', 'decision' => 'Decision']"
        :created-by="$req->created_by" :created-at="$req->created_at?->toDateTimeString()" :updated-by="$req->updated_by" :updated-at="$req->updated_at?->toDateTimeString()">
        <x-slot:special>
            <x-dl::button size="sm" preset="print" :href="\App\Support\V2\Print\PrintDocs::url('maintenance-request', $req->id_request)" target="_blank" />
            @if ($open && $head)
                <x-dl::button size="sm" variant="danger" icon="reject" label="Reject" data-f-act="reject" />
                <x-dl::button size="sm" variant="success" icon="approve" label="Accept" type="submit" name="action" value="accept" formnovalidate />
            @elseif ($req->wo_id)
                <x-dl::button size="sm" variant="secondary" icon="fa-screwdriver-wrench" :label="'Open '.($req->wo?->doc_no ?? 'WO')" :href="route('v2.screen', 'maintenance-wo').'?id='.$req->wo_id" />
            @endif
        </x-slot:special>
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>Request</span><b>{{ $req->doc_no }}</b></div>
            <div class="dl-strip__kv"><span>Reported</span><b>{{ Maintenance::stamp($req->request_date) }}</b></div>
            <div class="dl-strip__kv"><span>Reported by</span><b>{{ $req->reported_by ? Maintenance::employeeName($req->reported_by) : $req->created_by }}</b></div>
            <div class="dl-strip__kv"><span>Machine</span><b>{{ $req->machine_stopped ? 'Stopped since '.Maintenance::stamp($req->stopped_at) : 'Still running' }}</b></div>
            <div class="dl-strip__kv dl-strip__grand"><span>Priority</span><b>{{ MntWo::PRIORITIES[$req->priority] ?? $req->priority }}</b></div>
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="3">
                <x-dl::field.display label="Machine" :value="$machines[$req->machine_id] ?? '#'.$req->machine_id" :source="Src::of('tm2mst.prod_machine')" />
                <x-dl::field.select name="request_kind" label="Kind" required :options="MntRequest::KINDS" :value="$req->request_kind" :source="Src::of('tm2prd.mnt_request.request_kind')" />
                <x-dl::field.select name="priority" label="Priority" required :options="MntWo::PRIORITIES" :value="$req->priority" :source="Src::of('tm2prd.mnt_request.priority')" />
            </x-dl::form.row>
            <x-dl::field.text name="symptom" label="What happened" required multiline :value="$req->symptom" :source="Src::of('tm2prd.mnt_request.symptom')" />
        </x-slot:general>
        <x-slot:decision>
            @if ($open)
                <x-dl::form.row :cols="2">
                    <x-dl::field.lookup name="technician_id" lookup="employee" label="Technician (for Accept)" placeholder="Type a technician name..." :source="Src::of('tm2mst.emp_employee')" />
                    <x-dl::field.date-time name="planned_start" label="Planned start (for Accept)" :value="now()->addHour()->format('Y-m-d H:00')" :source="Src::of('tm2prd.mnt_wo.planned_start')" />
                </x-dl::form.row>
            @else
                <x-dl::form.row :cols="2">
                    <x-dl::field.display label="Decision" :value="ucfirst(strtolower($req->status))" />
                    <x-dl::field.display :label="$req->status === 'REJECTED' ? 'Reject reason' : 'Work Order'" :value="$req->status === 'REJECTED' ? $req->reject_reason : ($req->wo?->doc_no ?? '—')" />
                </x-dl::form.row>
            @endif
        </x-slot:decision>
    </x-dl::form>
@endif
