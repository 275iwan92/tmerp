{{--
 REMARK
 Tujuan       : Form keputusan HR atas pengajuan koreksi data pegawai (menggantikan halaman v1 pages::hrm.correction-requests): rincian pengajuan (pegawai, field, nilai lama dan baru, alasan, waktu), keputusan (status, oleh, kapan, alasan penolakan), tombol Approve dan Reject (alasan wajib lewat dialog) hanya untuk baris PENDING; pengajuan rekening gaji disamarkan dan tidak bisa diputuskan oleh yang tidak berhak payroll atas pegawai itu. Baris yang sudah diputuskan hanya-baca.
 Sumber hulu  : tm2mst.emp_correction_request + emp_employee; aturan di App\Services\Hrm\CorrectionRequestService (Approve menerapkan kolom emp_employee atau rekening lewat EmployeeBankService).
 Input        : Query id (emp_correction_request.request_id); session v2_flash dan errors.
 Output       : POST v2.hrm.correction.decide (action approve atau reject, _reason).
 Dipakai oleh : Dibuka dari grid hrm-correction (Open); pengajuan lahir dari Self-Service dan Setup Keamanan Akun, tanpa New.
 Status       : Aktif (T7c HRM).
--}}
@php
    use App\Models\Master\EmpCorrectionRequest;
    use App\Services\Hrm\CorrectionRequestService as Corr;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'hrm-correction');
    $svc = app(Corr::class);
    $user = Auth::user();
    $c = EmpCorrectionRequest::with('employee')->find((int) request('id'));
    abort_unless($c, 404);
    $pending = $c->status === 'PENDING';
    $bank = Corr::isBank($c);
    $canDecide = $pending && (! $bank || Corr::canSeeBank($user, $c));
    $state = ['PENDING' => 'approval', 'REJECTED' => 'rejected'][$c->status] ?? 'approved';
    $v = Corr::values($c, $user);
    $s = fn (string $col) => Src::of('tm2mst.emp_correction_request.'.$col);
    $emp = $c->employee ? $c->employee->employee_code.' '.$c->employee->full_name : '—';
    $auto = isset(Corr::FIELD_COLUMN_MAP[$c->field_name]) ? 'Updates the employee record' : ($bank ? 'Updates the payroll bank account' : 'Closes the request (HR updates it manually)');
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="hrm-correction-form" title="Employee Data Correction" :subtitle="raw($emp)"
    panel="top" kind="document" :status="$state" :pipeline="false" :approval-levels="1" :approval-at="1" :can-approve="$canDecide"
    :action="route('v2.hrm.correction.decide')" action-field="action" :back="$home" :hide="['hold', 'print', 'email', 'cancel', 'new', 'duplicate', 'save', 'undo']"
    :hidden="['id' => $c->request_id]" :tabs="['request' => t('Request')]" :log="$svc->history($c)">
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Field" :value="raw($c->field_name)" />
        <x-dl::strip-kv kind="grand" label="Submitted" :value="$c->requested_at ? Locale::dateTime($c->requested_at) : '—'" />
        <x-dl::strip-kv kind="grand" label="On approve" :value="t($auto)" />
        <x-dl::strip-kv kind="grand" label="Status" :value="t(Corr::STATUSES[$c->status] ?? $c->status)" />
    </x-slot:strip>

    <x-slot:request>
        <x-dl::card title="Request">
            <x-dl::form.row :cols="3">
                <x-dl::field.display label="Employee" :value="raw($emp)" :source="$s('employee_id')" />
                <x-dl::field.display label="Old value" :value="raw($v['old'] ?: '—')" :source="$s('old_value')" />
                <x-dl::field.display label="New value" :value="raw($v['new'] ?: '—')" :source="$s('new_value')" />
            </x-dl::form.row>
            <x-dl::field.display label="Reason" :value="raw($c->reason ?: '—')" :source="$s('reason')" />
        </x-dl::card>
        <x-dl::card title="Decision">
            <x-dl::form.row :cols="3">
                <x-dl::field.display label="Status" :value="t(Corr::STATUSES[$c->status] ?? $c->status)" :source="$s('status')" />
                <x-dl::field.display label="Reviewed by" :value="raw($c->reviewed_by ?: '—')" :source="$s('reviewed_by')" />
                <x-dl::field.display label="Reviewed at" :value="$c->reviewed_at ? Locale::dateTime($c->reviewed_at) : '—'" :source="$s('reviewed_at')" />
            </x-dl::form.row>
            @if ($c->review_note)
                <x-dl::field.display label="Rejection reason" :value="raw($c->review_note)" :source="$s('review_note')" />
            @endif
            @if ($canDecide)
                <x-dl::hint note>Approve applies the new value as shown on the "On approve" tile. Reject asks for a reason and sends it to the employee as a notification.</x-dl::hint>
            @elseif ($pending && $bank)
                <x-dl::hint note>A payroll bank account request can only be seen and decided by someone with the payroll right for this employee.</x-dl::hint>
            @endif
        </x-dl::card>
    </x-slot:request>
</x-dl::form>
