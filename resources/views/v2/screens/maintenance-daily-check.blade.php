{{--
 REMARK
 Tujuan       : Lembar Daily Check operator per aset per shift: tandai tiap watch point OK / Not OK / N/A, Submit mengunci lembar dan membuka Maintenance Request per titik Not OK.
 Sumber hulu  : Model MntDailyCheck dan MntDailyCheckLine (tm2mnt), watch point operator aktif; Maintenance::*; aturan DailyCheckService; daftar = grid Maint\DailyCheckListGrid.
 Input        : Query id (lembar), new (buka lembar); sesi v2_flash dan errors.
 Output       : Grid atau form x-dl::action-form dan x-dl::card; posting ke route v2.mnt.daily.open dan v2.mnt.daily.save.
 Dipakai oleh : Route v2.screen slug maintenance-daily-check (App\Support\V2\Screens, menu sys_app_menu target v2:maintenance-daily-check) di menu Maintenance
 Status       : Aktif (mockup maintenance_v2_flow.jpg bagian 3)
--}}
@php
    use App\Models\Mnt\MntDailyCheck;
    use App\Models\Mnt\MntDailyCheckLine;
    use App\Models\Mnt\MntLog;
    use App\Support\V2\Data\Maintenance;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'maintenance-daily-check');
    $sheet = request('id') ? MntDailyCheck::with(['lines', 'asset'])->find((int) request('id')) : null;
    abort_if(request('id') && ! $sheet, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $sheet && ! request('new'))
    {{-- Daftar = objek Grid Transactional App\Support\V2\Grid\Maint\DailyCheckListGrid (DocListGrid), tampil lewat view generik _dbgrid. --}}
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Maint\DailyCheckListGrid]])
@elseif (! $sheet)
    <x-dl::action-form action="{{ route('v2.mnt.daily.open') }}">
        <x-dl::card title="+ New Record — Daily Check" description="Pick the asset, the date and the shift. The sheet is built from the active operator watch points; an existing sheet is opened instead of a second one.">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="asset_id" label="Asset" required :options="Maintenance::assetOptions()" :source="Src::of('tm2mnt.mnt_asset')" />
                <x-dl::field.date name="check_date" label="Date" required :value="now()->toDateString()" :source="Src::of('tm2mnt.mnt_daily_check.check_date')" />
                <x-dl::field.select name="shift_code" label="Shift" required :options="array_map('t', MntDailyCheck::SHIFTS)" value="1" :source="Src::of('tm2mnt.mnt_daily_check.shift_code')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Open Sheet" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@else
    @php
        $locked = $sheet->isSubmitted();
        $assets = Maintenance::assetOptions();
        $rows = $sheet->lines->map(fn (MntDailyCheckLine $l) => ['id_daily_check_line' => $l->id_daily_check_line, 'check_text' => $l->check_text,
            'range' => $l->normal_min !== null || $l->normal_max !== null ? ($l->normal_min ?? '…').' – '.($l->normal_max ?? '…').' '.$l->measure_uom : '—',
            'result' => $l->result, 'measured_value' => $l->measured_value, 'note' => $l->note, 'request' => $l->request_id ? t('Request opened') : ''])->all();
        $notOk = $sheet->lines->where('result', 'NOT_OK')->count();
    @endphp
    <x-dl::form id="mnt-daily-check-form" :title="'Daily Check · '.raw($sheet->doc_no)" :subtitle="raw($assets[$sheet->asset_id] ?? '#'.$sheet->asset_id)"
        panel="top" kind="document" :status="$locked ? 'confirmed' : 'draft'" :stage="$locked ? t('Submitted') : t('Open')" :log="MntLog::forDoc('CHECK', $sheet->id_daily_check)"
        :action="route('v2.mnt.daily.save', $sheet->id_daily_check)" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hide="['delete', 'confirm', 'cancel', 'duplicate', 'email', 'print', 'new']"
        :tabs="['general' => 'Sheet']" :created-by="$sheet->created_by" :created-at="$sheet->created_at?->toDateTimeString()" :updated-by="$sheet->updated_by" :updated-at="$sheet->updated_at?->toDateTimeString()">
        @if (! $locked)
            <x-slot:special>
                <x-dl::button size="sm" variant="success" icon="fa-check" label="Submit" type="submit" name="action" value="submit" formnovalidate />
            </x-slot:special>
        @endif
        <x-slot:strip>
            <x-dl::strip-kv kind="doc" label="Sheet" :value="$sheet->doc_no" />
            <x-dl::strip-kv label="Date" :value="Locale::date($sheet->check_date)" />
            <x-dl::strip-kv label="Shift" :value="t(MntDailyCheck::SHIFTS[$sheet->shift_code] ?? $sheet->shift_code)" />
            <x-dl::strip-kv kind="grand" label="Not OK" :value="$notOk" />
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Asset" :value="raw($assets[$sheet->asset_id] ?? '#'.$sheet->asset_id)" :source="Src::of('tm2mnt.mnt_asset')" />
                <x-dl::field.display label="Date" :value="Locale::date($sheet->check_date)" :source="Src::of('tm2mnt.mnt_daily_check.check_date')" />
                <x-dl::field.display label="Checked By" :value="raw($sheet->checked_by)" :source="Src::of('tm2mnt.mnt_daily_check.checked_by')" />
                <x-dl::field.display label="Submitted" :value="$sheet->submitted_at ? Locale::dt($sheet->submitted_at) : '—'" :source="Src::of('tm2mnt.mnt_daily_check.submitted_at')" />
            </x-dl::form.row>
        </x-slot:general>
        <x-slot:detail>
            <x-dl::lines name="lines" title="Check points" key-field="id_daily_check_line" :addable="false" :duplicable="false" :min-rows="0" :rows="$rows" :readonly="$locked"
                source="{{ Src::of('tm2mnt.mnt_daily_check_line') }}"
                :columns="[
                    ['key' => 'check_text', 'label' => 'What to check', 'type' => 'display', 'width' => '320px'],
                    ['key' => 'range', 'label' => 'Normal limit', 'type' => 'display', 'width' => '140px'],
                    ['key' => 'result', 'label' => 'Result', 'type' => 'select', 'options' => ['' => '—', 'OK' => 'OK', 'NOT_OK' => 'Not OK', 'NA' => 'N/A'], 'width' => '110px'],
                    ['key' => 'measured_value', 'label' => 'Measured', 'type' => 'number', 'width' => '100px'],
                    ['key' => 'note', 'label' => 'Note', 'width' => '260px'],
                    ['key' => 'request', 'label' => 'Request', 'type' => 'display', 'width' => '140px'],
                ]" />
        </x-slot:detail>
    </x-dl::form>
@endif
