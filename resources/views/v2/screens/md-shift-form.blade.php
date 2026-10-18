{{--
 REMARK
 Tujuan       : Form Master Shift v2 (menggantikan halaman v1 pages::master.shift): nama, departemen, kode shift, aktif, dan jam kerja tujuh hari (jam masuk, istirahat, jam pulang) dengan jam bersih per hari dan per minggu.
 Sumber hulu  : tm2mst.emp_shift, emp_shift_detail, emp_shift_roster (jumlah entri roster), org_department; aturan di App\Services\Master\ShiftMasterService; jam bersih dari EmpShift::dayPattern / dayMinutes (sama dengan papan roster).
 Input        : Query id (shift) atau new=1, tab; session v2_flash dan errors.
 Output       : POST v2.md.shift.save (Save / Activate / Deactivate).
 Dipakai oleh : Dibuka dari grid md-shift (Open dan + New Record).
 Status       : Aktif. Jam bersih dan strip menampilkan data tersimpan; hari tanpa jam masuk dan jam pulang = tidak beroperasi; jam pulang <= jam masuk = shift lewat tengah malam.
--}}
@php
    use App\Models\Master\EmpShift;
    use App\Models\Master\EmpShiftRoster;
    use App\Services\Master\ShiftMasterService as Sh;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'md-shift');
    $svc = app(Sh::class);
    $s = request('id') ? EmpShift::with(['details', 'department'])->find((int) request('id')) : null;
    abort_if(request('id') && ! $s, 404);
    abort_unless($s || request('new'), 404);
    $new = ! $s;
    $s ??= new EmpShift(['activate' => true]);
    $src = fn (string $col) => Src::of('tm2mst.emp_shift.'.$col);
    $dsrc = fn (string $col) => Src::of('tm2mst.emp_shift_detail.'.$col);
    $v = fn (string $col) => old($col, $s->{$col});
    $tabs = ['shift' => 'Shift', 'hours' => 'Working hours'];
    $byDay = $new ? collect() : $s->details->keyBy('day_of_week');
    $pattern = $new ? [] : $s->dayPattern();
    $hours = fn (int $min) => $min > 0 ? Locale::number(round($min / 60, 2), 2) : '-';
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="md-shift-form" :title="$new ? 'New Shift' : raw($s->shift_name)" :subtitle="$new ? null : raw($s->shift_code)"
    panel="top" kind="master" :status="$new ? 'new' : ($s->activate ? 'active' : 'inactive')"
    :action="route('v2.md.shift.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'"
    :hidden="$new ? [] : ['id' => $s->shift_id]" :tabs="$tabs" :log="$new ? [] : $svc->history($s)"
    :created-by="$s->created_by" :created-at="$s->created_at?->toDateTimeString()" :updated-by="$s->updated_by" :updated-at="$s->updated_at?->toDateTimeString()">
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Shift code" :value="$new ? t('(on Save)') : raw($s->shift_code)" />
        <x-dl::strip-kv kind="grand" label="Department" :value="$new ? '—' : raw($s->department?->department_name ?: '—')" />
        <x-dl::strip-kv kind="grand" label="Days per week" :value="$new ? '—' : (string) $s->activeDayCount()" />
        <x-dl::strip-kv kind="grand" label="Net hours per week" :value="$new ? '—' : $hours($s->weekMinutes())" />
        <x-dl::strip-kv kind="grand" label="Roster entries" :value="$new ? '—' : Locale::number((float) EmpShiftRoster::where('shift_id', $s->shift_id)->count(), 0)" />
    </x-slot:strip>

    <x-slot:shift>
        <x-dl::card title="Shift">
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="shift_name" label="Shift name" required placeholder="e.g. TM Production Shift 1" :value="$v('shift_name')" :source="$src('shift_name')" />
                <x-dl::field.select name="department_id" label="Department" required placeholder="Choose" :options="$svc->departmentOptions()" :value="$v('department_id')" :source="Src::of('tm2mst.emp_shift.department_id', 'tm2mst.org_department')" />
                <x-dl::field.text name="shift_code" label="Shift code" required placeholder="SH1" hint="Up to 10 letters, digits, - or _; unique. The roster board sorts by it." :value="$v('shift_code')" :source="$src('shift_code')" />
            </x-dl::form.row>
        </x-dl::card>
    </x-slot:shift>

    <x-slot:hours>
        <x-dl::card title="Working hours per day">
            <x-dl::hint note>Leave Clock in and Clock out empty for a non-operating day. A Clock out earlier than Clock in means the shift ends the next day. Break minutes are calculated from Break start and Break end.</x-dl::hint>
            @foreach (EmpShift::DAY_ORDER as $i => $day)
                @php
                    $d = $byDay->get($day);
                    $t = fn (string $col) => $d?->{$col}?->format('H:i');
                @endphp
                <x-dl::form.row :cols="6">
                    <x-dl::field.display :label="$i === 0 ? 'Day' : ''" :value="$day" />
                    <x-dl::field.time placeholder="HH:MM" :name="'start_'.$day" :label="$i === 0 ? 'Clock in' : ''" :value="old('start_'.$day, $t('start_time'))" :source="$dsrc('start_time')" />
                    <x-dl::field.time placeholder="HH:MM" :name="'bout_'.$day" :label="$i === 0 ? 'Break start' : ''" :value="old('bout_'.$day, $t('break_out'))" :source="$dsrc('break_out')" />
                    <x-dl::field.time placeholder="HH:MM" :name="'bin_'.$day" :label="$i === 0 ? 'Break end' : ''" :value="old('bin_'.$day, $t('break_in'))" :source="$dsrc('break_in')" />
                    <x-dl::field.time placeholder="HH:MM" :name="'end_'.$day" :label="$i === 0 ? 'Clock out' : ''" :value="old('end_'.$day, $t('end_time'))" :source="$dsrc('end_time')" />
                    <x-dl::field.display :label="$i === 0 ? 'Net hours' : ''" :value="$new ? '-' : $hours(EmpShift::dayMinutes($pattern[$i]))" :source="$dsrc('break_minutes')" />
                </x-dl::form.row>
            @endforeach
        </x-dl::card>
    </x-slot:hours>
</x-dl::form>
