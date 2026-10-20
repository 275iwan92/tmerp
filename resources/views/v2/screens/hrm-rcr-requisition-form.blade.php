{{--
 REMARK
 Tujuan       : Form Manpower Requisition (menggantikan halaman v1 pages::recruitment.requisition.list dan entry): permohonan tenaga kerja dari Draft (Save, Delete, Submit for Approval) lewat approval berjenjang (Approve / Reject dengan alasan oleh approver yang ditugaskan) sampai Approved atau Rejected, dengan tabel langkah approval dan tab History.
 Sumber hulu  : tm2hrr.rcr_requisition + rcr_requisition_approval + rcr_approval_matrix + rcr_track, tm2mst.org_department, tm2sys.sec_user; aturan di App\Services\Recruitment\RequisitionService dan RecruitmentApprovalService.
 Input        : Query id (rcr_requisition.id_requisition) atau new=1; session v2_flash dan errors.
 Output       : POST v2.hrm.requisition.save (action save, submit, delete, approve, reject + _reason).
 Dipakai oleh : Dibuka dari grid hrm-rcr-requisition (Open dan + New Record); requisition APPROVED dipakai Vacancy Form.
 Status       : Aktif (T7l HRM).
--}}
@php
    use App\Models\Hrr\RcrRequisition;
    use App\Models\Hrr\RcrTrack;
    use App\Models\Master\OrgDepartment;
    use App\Services\Recruitment\RequisitionService as Req;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'hrm-rcr-requisition');
    $svc = app(Req::class);
    $user = Auth::user();
    $r = request('id') ? RcrRequisition::with('approvals')->find((int) request('id')) : null;
    abort_if(request('id') && ! $r, 404);
    abort_unless($r || request('new'), 404);
    $new = ! $r;
    $r ??= new RcrRequisition(['id_track' => RcrTrack::where('is_active', true)->orderBy('id_track')->value('id_track'), 'headcount' => 1, 'status' => 'DRAFT']);
    $editable = $new || ($r->status === 'DRAFT' && Req::canEdit($r, $user));
    $state = $new ? 'new' : (Req::STATUS_STATE[$r->status] ?? 'closed');
    $pending = $new ? null : Req::pendingFor($r, $user);
    $steps = $new ? [] : Req::steps($r);
    $done = collect($steps)->where('decision', 'APPROVED')->count();
    $s = fn (string $col) => Src::of('tm2hrr.rcr_requisition.'.$col);
    $val = fn (string $col) => old($col, $r->{$col});
    $tracks = RcrTrack::where('is_active', true)->orderBy('id_track')->pluck('name', 'id_track')->all();
    $depts = OrgDepartment::where('activate', true)->orderBy('department_name')->pluck('department_name', 'department_id')->all();
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="hrm-rcr-requisition-form" :title="$new ? 'New Manpower Requisition' : raw($r->requisition_no)" :subtitle="$new ? null : raw($r->position_title)"
    panel="top" kind="document" :status="$state" :pipeline="false" :approval-levels="max(1, count($steps))" :approval-at="min($done + 1, max(1, count($steps)))" :can-approve="(bool) $pending" :can-input="$editable"
    :action="route('v2.hrm.requisition.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hide="['print', 'email', 'hold', 'cancel', 'confirm', 'duplicate', 'new']"
    :hidden="$new ? [] : ['id' => $r->id_requisition]" :tabs="['general' => t('Requisition')]" :log="$new ? [] : $svc->history($r)"
    :created-at="$r->created_at?->toDateTimeString()" :updated-at="$r->updated_at?->toDateTimeString()">
    @if (! $new && $editable)
        <x-slot:special>
            <x-dl::button variant="primary" icon="fa-paper-plane" label="Submit for Approval" type="submit" name="action" value="submit" data-dl-confirm="{{ t('Submit this requisition for approval?') }}" />
        </x-slot:special>
    @endif
    @unless ($new)
        <x-slot:strip>
            <x-dl::strip-kv kind="doc" label="Requisition" :value="raw($r->requisition_no)" />
            <x-dl::strip-kv kind="grand" label="Position needed" :value="raw($r->position_title)" />
            <x-dl::strip-kv kind="grand" label="Headcount" :value="Locale::number((float) $r->headcount, 0)" />
            <x-dl::strip-kv kind="grand" label="Status" :value="t(ucfirst(strtolower($r->status)))" />
        </x-slot:strip>
    @endunless

    <x-slot:general>
        <x-dl::card title="Requisition">
            @if ($editable)
                <x-dl::hint note>Fill the header and Save the draft. Qualification and Requisition reason are required before Submit for Approval; the approval steps come from the chosen track.</x-dl::hint>
            @endif
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="id_track" label="Track" required :options="$tracks" :value="$val('id_track')" :placeholder="null" :source="$s('id_track')" />
                <x-dl::field.select name="department_id" label="Department" required :options="$depts" :value="$val('department_id')" placeholder="— select department —" :source="$s('department_id')" />
                <x-dl::field.text name="position_title" label="Position needed" required placeholder="e.g. Offset Press Operator" :value="$val('position_title')" :source="$s('position_title')" />
                <x-dl::field.number name="headcount" label="Headcount" required :value="$val('headcount')" :source="$s('headcount')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                <x-dl::field.text name="qualification" label="Qualification" multiline :value="$val('qualification')" :source="$s('qualification')" />
                <x-dl::field.text name="reason" label="Requisition reason" multiline :value="$val('reason')" :source="$s('reason')" />
            </x-dl::form.row>
        </x-dl::card>
        @if ($steps)
            <x-dl::card title="Approval steps">
                <x-dl::table :columns="['seq' => 'Step', 'role' => 'Role', 'approver' => 'Approver', 'decision' => 'Decision', 'at' => 'Decided', 'note' => 'Note']" :rows="$steps" />
            </x-dl::card>
        @endif
    </x-slot:general>
</x-dl::form>
