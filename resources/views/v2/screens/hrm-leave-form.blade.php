{{--
 REMARK
 Tujuan       : Form keputusan HR atas pengajuan Cuti / Sakit / Izin / Cuti Khusus (menggantikan halaman v1 pages::hrm.leave-requests): rincian pengajuan (pegawai, jenis dan kategori, periode, hari, alasan, lampiran), kartu saldo cuti tahun itu (hanya-baca, termasuk sisa setelah Approve bila memotong saldo), keputusan, tombol Approve dan Reject (alasan wajib lewat dialog) hanya untuk baris PENDING, tab History. Baris yang sudah diputuskan hanya-baca.
 Sumber hulu  : tm2mst.emp_leave_request + emp_leave_balance + emp_employee; aturan di App\Services\Hrm\LeaveRequestService (Approve menambah used_days hanya untuk yang deducts_leave_balance).
 Input        : Query id (emp_leave_request.request_id); session v2_flash dan errors.
 Output       : POST v2.hrm.leave.decide (action approve atau reject, _reason).
 Dipakai oleh : Dibuka dari grid hrm-leave (Open); pengajuan lahir dari Self-Service, tanpa New.
 Status       : Aktif (T7d HRM).
--}}
@php
    use App\Models\Master\EmpLeaveRequest;
    use App\Services\Hrm\LeaveRequestService as Leave;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'hrm-leave');
    $svc = app(Leave::class);
    $r = EmpLeaveRequest::with('employee')->find((int) request('id'));
    abort_unless($r, 404);
    $pending = $r->status === 'PENDING';
    $state = ['PENDING' => 'approval', 'REJECTED' => 'rejected'][$r->status] ?? 'approved';
    $s = fn (string $col) => Src::of('tm2mst.emp_leave_request.'.$col);
    $emp = $r->employee ? $r->employee->employee_code.' '.$r->employee->full_name : '—';
    $event = Leave::eventLabel($r);
    $period = Locale::date($r->date_from).($r->date_to->ne($r->date_from) ? ' — '.Locale::date($r->date_to) : '');
    $bal = Leave::balance($r);
    $remaining = $bal ? $bal->remainingDays() : null;
    $afterApprove = $remaining === null ? null : ($r->deducts_leave_balance ? $remaining - $r->total_days : $remaining);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="hrm-leave-form" title="Leave / Sick / Permit Approval" :subtitle="raw($emp)"
    panel="top" kind="document" :status="$state" :pipeline="false" :approval-levels="1" :approval-at="1" :can-approve="$pending"
    :action="route('v2.hrm.leave.decide')" action-field="action" :back="$home" :hide="['hold', 'print', 'email', 'cancel', 'new', 'duplicate', 'save', 'undo']"
    :hidden="['id' => $r->request_id]" :tabs="['request' => t('Request')]" :log="$svc->history($r)">
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Type" :value="raw($r->leave_type.($event ? ' · '.$event : ''))" />
        <x-dl::strip-kv kind="grand" label="Period" :value="$period" />
        <x-dl::strip-kv kind="grand" label="Days" :value="Locale::number((float) $r->total_days, 0)" />
        <x-dl::strip-kv kind="grand" label="Leave balance" :value="t($r->deducts_leave_balance ? 'Deducts the balance' : 'Not deducted')" />
    </x-slot:strip>

    <x-slot:request>
        <x-dl::card title="Request">
            <x-dl::form.row :cols="3">
                <x-dl::field.display label="Employee" :value="raw($emp)" :source="$s('employee_id')" />
                <x-dl::field.display label="Type" :value="raw($r->leave_type)" :source="$s('leave_type')" />
                <x-dl::field.display label="Category" :value="$event ? raw($event) : '—'" :source="$s('khusus_event_type')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.display label="From" :value="Locale::date($r->date_from)" :source="$s('date_from')" />
                <x-dl::field.display label="Until" :value="Locale::date($r->date_to)" :source="$s('date_to')" />
                <x-dl::field.display label="Submitted" :value="$r->requested_at ? Locale::dateTime($r->requested_at) : '—'" :source="$s('requested_at')" />
            </x-dl::form.row>
            <x-dl::field.display label="Reason" :value="raw($r->reason ?: '—')" :source="$s('reason')" />
            @php($files = Leave::attachments($r))
            @if ($files)
                <x-dl::action-bar>
                    @foreach ($files as $label => $path)
                        <x-dl::button size="sm" variant="secondary" icon="fa-paperclip" :label="t($label)" :href="asset('storage/'.$path)" />
                    @endforeach
                </x-dl::action-bar>
            @endif
        </x-dl::card>
        <x-dl::card title="Leave balance {{ $r->date_from->year }} (read-only)">
            @if ($bal)
                <x-dl::form.row :cols="4">
                    <x-dl::field.display label="Entitlement + carried over" :value="$bal->entitlement_days.' + '.$bal->carried_over_days" :source="Src::of('tm2mst.emp_leave_balance.entitlement_days')" />
                    <x-dl::field.display label="Used" :value="(string) $bal->used_days" :source="Src::of('tm2mst.emp_leave_balance.used_days')" />
                    <x-dl::field.display label="Remaining now" :value="(string) $remaining" :source="Src::of('tm2mst.emp_leave_balance.used_days')" />
                    <x-dl::field.display label="Remaining after approve" :value="(string) $afterApprove" :source="Src::of('tm2mst.emp_leave_balance.used_days')" />
                </x-dl::form.row>
            @else
                <x-dl::hint note>No balance row for this year yet; Approve creates it with the default entitlement of 12 days when the request deducts the balance.</x-dl::hint>
            @endif
        </x-dl::card>
        <x-dl::card title="Decision">
            <x-dl::form.row :cols="3">
                <x-dl::field.display label="Status" :value="t(Leave::STATUSES[$r->status] ?? $r->status)" :source="$s('status')" />
                <x-dl::field.display label="Reviewed by" :value="raw($r->reviewed_by ?: '—')" :source="$s('reviewed_by')" />
                <x-dl::field.display label="Reviewed at" :value="$r->reviewed_at ? Locale::dateTime($r->reviewed_at) : '—'" :source="$s('reviewed_at')" />
            </x-dl::form.row>
            @if ($r->review_note)
                <x-dl::field.display label="Rejection reason" :value="raw($r->review_note)" :source="$s('review_note')" />
            @endif
            @if ($pending)
                <x-dl::hint note>Approve adds the days to the balance only when the request deducts it; Reject asks for a reason. Both send a notification to the employee.</x-dl::hint>
            @endif
        </x-dl::card>
    </x-slot:request>
</x-dl::form>
