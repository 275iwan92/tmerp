{{-- Holiday Calendar v2 (App\Support\V2\Grid\Master\HolidayCalendarGrid): kalender setahun berwarna (klik tanggal libur = buka,
     klik tanggal kosong = libur baru di tanggal itu) + grid per tahun + form master. Libur nasional & cuti bersama = SKB 3 Menteri. --}}
@php
    use App\Models\Master\SysHoliday;
    use App\Support\V2\Data\WorkCalendar;
    use App\Support\V2\Src;

    $grid = $screen['grid'];
    $year = WorkCalendar::year();
    $home = route('v2.screen', 'holiday-calendar');
    $h = request('id') ? SysHoliday::find((int) request('id')) : null;
    abort_if(request('id') && ! $h, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $h && ! request('new'))
    <x-dl::action-bar>
        <x-dl::button variant="secondary" icon="prev" :label="(string) ($year - 1)" :href="$home.'?year='.($year - 1)" />
        <x-dl::badge :label="(string) $year" tone="info" />
        <x-dl::button variant="secondary" icon="next" :label="(string) ($year + 1)" :href="$home.'?year='.($year + 1)" />
        <x-dl::button variant="secondary" icon="fa-business-time" label="Office Hours" :href="route('v2.screen', 'office-hours')" />
    </x-dl::action-bar>
    <x-dl::year-calendar :year="$year" :marks="WorkCalendar::marks($year, $grid->openUrl())" :off-days="WorkCalendar::offDays()"
        :new-url="$home.'?year='.$year.'&new=1&date={date}'" source="{{ Src::of('tm2mst.sys_holiday', 'tm2mst.sys_office_hour') }}"
        :legend="['danger' => 'National holiday', 'warn' => 'Collective leave', 'info' => 'Company holiday', 'off' => 'Office closed (Office Hours)']" />
    <br>
    @include('v2.screens._dbgrid', ['screen' => $screen])
@else
    @php
        $new = ! $h;
        $v = $h ?? new SysHoliday(['holiday_date' => request('date'), 'holiday_type' => 'COMPANY', 'activate' => true]);
    @endphp
    <x-dl::form id="holiday-form" :title="'Holiday · '.($new ? 'New' : raw(WorkCalendar::name($v->holiday_name)))" panel="top" kind="master"
        :status="$new ? 'new' : ($v->activate ? 'active' : 'inactive')" :action="route('v2.holiday.save')" action-field="action"
        :back="$home.'?year='.$year" :new-url="$home.'?year='.$year.'&new=1'" :hidden="($h ? ['id' => $h->id_holiday] : []) + ['year' => $year]"
        :created-by="$v->created_by" :created-at="$v->created_at?->toDateTimeString()" :updated-by="$v->updated_by" :updated-at="$v->updated_at?->toDateTimeString()">
        <x-dl::form.row :cols="3">
            <x-dl::field.date name="holiday_date" label="Date" required :value="old('holiday_date', $v->holiday_date?->toDateString() ?? request('date'))" :source="Src::of('tm2mst.sys_holiday.holiday_date')" />
            <x-dl::field.select name="holiday_type" label="Type" required :options="array_map([WorkCalendar::class, 'typeLabel'], array_combine(array_keys(WorkCalendar::TYPES), array_keys(WorkCalendar::TYPES)))"
                :value="old('holiday_type', $v->holiday_type)" :source="Src::of('tm2mst.sys_holiday.holiday_type')" />
            <x-dl::field.text name="holiday_name" label="Holiday Name (English)" required :value="old('holiday_name', $v->holiday_name)"
                hint="Write in English; the Indonesian name is filled in Setup > Translation Dictionary." :source="Src::of('tm2mst.sys_holiday.holiday_name')" />
        </x-dl::form.row>
    </x-dl::form>
@endif
