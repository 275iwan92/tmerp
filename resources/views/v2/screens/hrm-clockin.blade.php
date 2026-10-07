{{-- HRM > Time Attendance > GPS Clock-In (v2, ponsel). Objek x-dl::geo-clock; jarak & keputusan dihitung server (App\Support\V2\Data\GpsClock):
     dalam radius = langsung menjadi presensi, di luar radius = menunggu approval HR (GPS Location Approval). Milik sendiri, semua karyawan. --}}
@php
    use App\Support\V2\Data\GpsClock;
    use App\Support\V2\Src;

    $employeeId = auth()->user()->employee_id;
@endphp

@if (! $employeeId)
    <x-dl::alert tone="warn" message="This account is not linked to an employee record — contact HR to enable mobile attendance." />
@else
    <x-dl::geo-clock title="GPS Geofencing Clock-In" subtitle="Check your distance to the work location, then clock in or out."
        check-url="{{ route('v2.clock.check') }}" clock-url="{{ route('v2.clock.store') }}" :history="GpsClock::history($employeeId)"
        :source="Src::of('tm2mst.att_mobile_gps_log', 'tm2mst.att_finger_raw', 'tm2mst.sys_geofence_location')" />
@endif
