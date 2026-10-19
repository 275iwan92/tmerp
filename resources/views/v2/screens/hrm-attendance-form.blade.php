{{--
 REMARK
 Tujuan       : Form presensi harian satu pegawai (menggantikan dialog Override halaman v1 pages::hrm.attendance-daily): rincian hari itu (shift, scan masuk dan keluar, terlambat, pulang awal, lembur, status hasil hitung, sumber), isian Override status (status baru + alasan audit wajib minimal 10 karakter, disimpan dengan Save) dan tombol "Recalculate this day" yang melepas override lalu menghitung ulang dari scan; tab History.
 Sumber hulu  : tm2mst.att_daily_status + emp_employee; aturan di App\Services\Hrm\AttendanceOverrideService; hitung ulang oleh AttendanceCalculationService (melewati hari MANUAL_OVERRIDE).
 Input        : Query id (att_daily_status.id_daily); session v2_flash dan errors.
 Output       : POST v2.hrm.attendance.save (Save = override, action recalculate = hitung ulang hari ini).
 Dipakai oleh : Dibuka dari grid hrm-attendance (Open); baris lahir dari AttendanceCalculationService, tanpa New.
 Status       : Aktif (T7e HRM).
--}}
@php
    use App\Models\Master\AttDailyStatus;
    use App\Services\Hrm\AttendanceOverrideService as Att;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'hrm-attendance');
    $svc = app(Att::class);
    $d = AttDailyStatus::with('employee')->find((int) request('id'));
    abort_unless($d, 404);
    $manual = $d->source === 'MANUAL_OVERRIDE';
    $s = fn (string $col) => Src::of('tm2mst.att_daily_status.'.$col);
    $emp = $d->employee ? $d->employee->employee_code.' '.$d->employee->full_name : '—';
    $time = fn ($x, $f = 'H:i:s') => $x ? $x->format($f) : '—';
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="hrm-attendance-form" title="Daily Attendance" :subtitle="raw($emp.' · '.Locale::date($d->attendance_date))"
    panel="top" kind="master" status="active" :action="route('v2.hrm.attendance.save')" action-field="action" :back="$home" :hide="['deactivate', 'new', 'undo']"
    :hidden="['id' => $d->id_daily]" :tabs="['day' => t('Day')]" :log="$svc->history($d)">
    <x-slot:special>
        <x-dl::button type="submit" name="action" value="recalculate" variant="secondary" icon="fa-rotate" label="Recalculate this day" formnovalidate data-dl-confirm="{{ t('Release any manual override and recalculate this day from the fingerprint scans?') }}" />
    </x-slot:special>
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Status" :value="raw($d->status_code.' — '.Att::statusLabel($d->status_code))" />
        <x-dl::strip-kv kind="grand" label="Shift" :value="$d->shift_start ? $d->shift_start->format('H:i').' – '.($d->shift_end?->format('H:i') ?? '—') : '—'" />
        <x-dl::strip-kv kind="grand" label="Clock in / out" :value="$time($d->finger_in, 'H:i').' / '.$time($d->finger_out, 'H:i')" />
        <x-dl::strip-kv kind="grand" label="Source" :value="t($manual ? 'Manual' : 'Automatic')" />
    </x-slot:strip>

    <x-slot:day>
        <x-dl::card title="Day (calculated, read-only)">
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Employee" :value="raw($emp)" :source="$s('employee_id')" />
                <x-dl::field.display label="Date" :value="Locale::date($d->attendance_date)" :source="$s('attendance_date')" />
                <x-dl::field.display label="Clock in" :value="$time($d->finger_in)" :source="$s('finger_in')" />
                <x-dl::field.display label="Clock out" :value="$time($d->finger_out)" :source="$s('finger_out')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Late (min)" :value="(string) $d->minutes_late" :source="$s('minutes_late')" />
                <x-dl::field.display label="Early leave (min)" :value="(string) $d->minutes_early_leave" :source="$s('minutes_early_leave')" />
                <x-dl::field.display label="Out during shift (min)" :value="(string) $d->minutes_out_during_shift" :source="$s('minutes_out_during_shift')" />
                <x-dl::field.display label="Overtime" :value="$d->overtime_minutes > 0 ? $d->overtime_minutes.' min ('.$d->overtime_type.')' : '—'" :source="$s('overtime_minutes')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="Override status">
            <x-dl::hint note>Use an override only with an official reason (for example an FDT form). An override is kept when attendance is recalculated; "Recalculate this day" releases it.</x-dl::hint>
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="status_code" label="New status" required :options="Att::statusOptions()" :value="old('status_code', $d->status_code)" :placeholder="null" :source="$s('status_code')" />
                <x-dl::field.text name="override_reason" label="Audit reason (min 10 characters)" required multiline placeholder="Enter the official reason, e.g. FDT Form No. ..." :value="old('override_reason', $manual ? $d->override_reason : '')" :source="$s('override_reason')" />
            </x-dl::form.row>
            @if ($manual)
                <x-dl::field.display label="Overridden by" :value="raw($d->override_by ?: '—')" :source="$s('override_by')" />
            @endif
        </x-dl::card>
    </x-slot:day>
</x-dl::form>
