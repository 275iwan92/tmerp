{{--
 REMARK
 Tujuan       : Form keputusan HR atas presensi GPS di luar radius (menggantikan halaman v1 pages::hrm.gps-approval): rincian catatan clock (pegawai, waktu, jenis, koordinat, jarak, status radius), keputusan (status, oleh, kapan, alasan), tombol Approve dan Reject (alasan wajib lewat dialog) hanya untuk baris PENDING, tab History. Baris yang sudah diputuskan hanya-baca.
 Sumber hulu  : tm2mst.att_mobile_gps_log + emp_employee; aturan di App\Services\Hrm\GpsApprovalService (Approve menulis att_finger_raw dan menghitung ulang status harian).
 Input        : Query id (att_mobile_gps_log.id_gps_log); session v2_flash dan errors.
 Output       : POST v2.hrm.gps.decide (action approve atau reject, _reason).
 Dipakai oleh : Dibuka dari grid hrm-gps (Open); catatan GPS lahir dari GpsClock (layar hrm-clockin), tanpa New.
 Status       : Aktif (T7 HRM).
--}}
@php
    use App\Models\Master\AttMobileGpsLog;
    use App\Services\Hrm\GpsApprovalService as Gps;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'hrm-gps');
    $svc = app(Gps::class);
    $g = AttMobileGpsLog::with('employee')->find((int) request('id'));
    abort_unless($g, 404);
    $pending = $g->hr_approval_status === 'PENDING';
    $state = ['PENDING' => 'approval', 'REJECTED' => 'rejected'][$g->hr_approval_status] ?? 'approved';
    $s = fn (string $col) => Src::of('tm2mst.att_mobile_gps_log.'.$col);
    $out = $g->geofence_status === 'OUT_OF_RADIUS';
    $emp = $g->employee ? $g->employee->employee_code.' '.$g->employee->full_name : '—';
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="hrm-gps-form" title="GPS Location Approval" :subtitle="raw($emp)"
    panel="top" kind="document" :status="$state" :pipeline="false" :approval-levels="1" :approval-at="1" :can-approve="$pending"
    :action="route('v2.hrm.gps.decide')" action-field="action" :back="$home" :hide="['hold', 'print', 'email', 'cancel', 'new', 'duplicate', 'save', 'undo']"
    :hidden="['id' => $g->id_gps_log]" :tabs="['gps' => t('GPS log')]" :log="$svc->history($g)">
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Scan time" :value="Locale::dateTime($g->created_at)" />
        <x-dl::strip-kv kind="grand" label="Type" :value="t($g->clock_type === 'IN' ? 'Clock In' : 'Clock Out')" />
        <x-dl::strip-kv kind="grand" label="Distance from site" :value="Locale::number((float) $g->distance_meters, 0).' m'" />
        <x-dl::strip-kv kind="grand" label="Radius" :value="t($out ? 'Outside radius' : 'Inside radius')" />
    </x-slot:strip>

    <x-slot:gps>
        <x-dl::card title="Clock record">
            <x-dl::form.row :cols="3">
                <x-dl::field.display label="Employee" :value="raw($emp)" :source="$s('employee_id')" />
                <x-dl::field.display label="Latitude" :value="$g->latitude" :source="$s('latitude')" />
                <x-dl::field.display label="Longitude" :value="$g->longitude" :source="$s('longitude')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.display label="Geofence status" :value="$g->geofence_status" :source="$s('geofence_status')" />
                <x-dl::field.display label="Distance (m)" :value="(string) $g->distance_meters" :source="$s('distance_meters')" />
                <x-dl::field.display label="Clock type" :value="$g->clock_type" :source="$s('clock_type')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="Decision">
            <x-dl::form.row :cols="3">
                <x-dl::field.display label="Status" :value="t(Gps::STATUSES[$g->hr_approval_status] ?? $g->hr_approval_status)" :source="$s('hr_approval_status')" />
                <x-dl::field.display label="Decided by" :value="raw($g->hr_approved_by ?: '—')" :source="$s('hr_approved_by')" />
                <x-dl::field.display label="Decided at" :value="$g->hr_approved_at ? Locale::dateTime($g->hr_approved_at) : '—'" :source="$s('hr_approved_at')" />
            </x-dl::form.row>
            @if ($g->hr_approval_note)
                <x-dl::field.display label="Rejection reason" :value="raw($g->hr_approval_note)" :source="$s('hr_approval_note')" />
            @endif
            @if ($pending)
                <x-dl::hint note>Approve writes the attendance scan (device MOBILE-GPS) and recalculates the employee's daily status; until then the clock is not recorded at all. Reject asks for a reason.</x-dl::hint>
            @endif
        </x-dl::card>
    </x-slot:gps>
</x-dl::form>
