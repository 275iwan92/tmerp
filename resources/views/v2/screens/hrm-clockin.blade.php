{{--
 REMARK
 Tujuan       : Clock-in / clock-out presensi berbasis GPS (geofencing) dari ponsel untuk karyawan sendiri.
 Sumber hulu  : App\Support\V2\Data\GpsClock; tm2mst.att_mobile_gps_log, att_finger_raw, sys_geofence_location; auth()->user()->employee_id.
 Input        : Akun login (harus terhubung ke record karyawan).
 Output       : x-dl::geo-clock dengan check-url v2.clock.check dan clock-url v2.clock.store; alert bila akun belum terhubung ke karyawan.
 Dipakai oleh : Rute v2.screen dengan slug 'hrm-clockin' (menu v2:hrm-clockin di sys_app_menu), dirender view layar generik v2.
 Status       : Aktif (dalam radius = presensi langsung, di luar radius = menunggu approval HR).
--}}
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
