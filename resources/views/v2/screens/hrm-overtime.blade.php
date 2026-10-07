{{-- HRM > Time Attendance > Overtime (v2) lewat App\Support\V2\Data\Overtime (tm2mst.emp_overtime_request).
     GRID pengajuan lembur -> + New Record / Open = form: pegawai, tanggal, jenis, durasi (menit) -> Save (draft) -> Submit -> Approve / Reject.
     Hanya yang APPROVED dibaca perhitungan presensi hari itu. Ubah isian hanya saat draft. --}}
@php
    use App\Support\V2\Data\Overtime;
    use App\Support\V2\Grid\Hrm\OvertimeListGrid;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'hrm-overtime');
    $o = request('id') ? Overtime::find((int) request('id')) : null;
    abort_if(request('id') && ! $o, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $o && ! request('new'))
    {{-- Daftar = objek Grid Transactional App\Support\V2\Grid\Hrm\OvertimeListGrid (DocListGrid), tampil lewat view generik _dbgrid. --}}
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new OvertimeListGrid]])
@else
    @php
        $new = ! $o;
        $o ??= Overtime::blank();
        $edit = $new || Overtime::canEdit($o);
        $decide = ! $new && Overtime::canDecide($o, auth()->user());
        $src = fn (string $col) => Src::of('tm2mst.emp_overtime_request.'.$col);
    @endphp
    <x-dl::form id="overtime-form" :title="$new ? 'Overtime · New' : 'Overtime · '.raw($o->employee?->full_name ?? '—')" :subtitle="$new ? null : raw($o->overtime_date?->toDateString())"
        panel="top" kind="master" :status="$new ? 'new' : 'active'" :stage="$new ? null : t($o->status)" :can-input="$edit" :hide="['deactivate', 'activate']"
        :action="$new ? route('v2.overtime.store') : route('v2.overtime.save', $o->id_overtime)" action-field="action" :back="$home" :new-url="$home.'?new=1'"
        :created-by="$o->created_by" :created-at="$o->created_at?->toDateTimeString()" :updated-by="$o->updated_by" :updated-at="$o->updated_at?->toDateTimeString()">
        <x-slot:special>
            @if (! $new && $o->status === 'DRAFT')<x-dl::button size="sm" variant="success" icon="fa-paper-plane" label="Submit" data-f-act="submit" />@endif
            @if ($decide)
                <x-dl::button size="sm" variant="success" icon="fa-check" label="Approve" data-f-act="approve" />
                <x-dl::button size="sm" variant="danger" icon="fa-xmark" label="Reject" data-f-act="reject" />
            @endif
            @if (! $new && $o->status === 'REJECTED')<x-dl::button size="sm" variant="secondary" icon="fa-rotate-left" label="Reopen" data-f-act="reopen" />@endif
            @if (! $new && $edit)<x-dl::button size="sm" preset="delete" label="Delete" data-f-act="delete" />@endif
        </x-slot:special>
        <x-dl::form.row :cols="4">
            <x-dl::field.select name="employee_id" label="Employee" required :options="Overtime::employeeOptions()" :value="$o->employee_id" :disabled="! $edit" :source="$src('employee_id')" />
            <x-dl::field.date name="overtime_date" label="Date" required :value="$o->overtime_date?->toDateString()" :disabled="! $edit" :source="$src('overtime_date')" />
            <x-dl::field.select name="ot_type" label="Overtime Type" required :options="array_map('t', Overtime::TYPES)" :value="$o->ot_type" :placeholder="null" :disabled="! $edit" :source="$src('ot_type')" />
            <x-dl::field.number name="duration_minutes" label="Duration (minutes)" required :value="$o->duration_minutes" :disabled="! $edit" hint="Total overtime of that day, 1–960 minutes." :source="$src('duration_minutes')" />
        </x-dl::form.row>
        @if (! $new)
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Status" :value="t($o->status)" :source="$src('status')" />
                <x-dl::field.display label="Decided By" :value="raw($o->approved_by ?? '—')" :source="$src('approved_by')" />
                <x-dl::field.display label="Decided On" :value="raw($o->approved_at?->toDateString() ?? '—')" :source="$src('approved_at')" />
            </x-dl::form.row>
        @endif
        @if (! $edit)
            <x-dl::alert tone="info" message="Only a draft can be changed. A rejected request can be reopened as a draft." />
        @endif
    </x-dl::form>
@endif
