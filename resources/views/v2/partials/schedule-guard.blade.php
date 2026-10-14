{{--
 REMARK
 Tujuan       : Popup pengaman jadwal: bila ada proses BOP yang tidak terjadwal di mesin, tampil daftar WO, proses, dan alasannya.
 Sumber hulu  : App\Services\Scheduling\Plan\ScheduleGuard::issues (Ppic::operations, gerbang, kandidat mesin).
 Input        : $guardWo (id WO, kosong = semua WO aktif).
 Output       : x-dl::issue-modal terbuka saat dimuat; tidak ada keluaran bila semua proses terjadwal.
 Dipakai oleh : v2 production-wo (Simulation), production-scheduling.
 Status       : Aktif di mockup (ddt*).
--}}
@php
    use App\Services\Scheduling\Plan\ScheduleGuard;

    $guardIssues = ScheduleGuard::issues($guardWo ?? null);
@endphp
@if ($guardIssues)
    <x-dl::issue-modal id="schedule-guard" :title="t('Process not scheduled')"
        :message="raw(tf(':n process(es) are not scheduled on any machine. Fix them before PROCESS or APPLY.', ['n' => count($guardIssues)]))"
        :columns="['wo' => 'Work Order', 'process' => 'Process', 'reason' => 'Reason']"
        :rows="array_map(fn ($i) => ['wo' => raw($i['wo_no']), 'process' => raw($i['process']), 'reason' => raw($i['reason'])], $guardIssues)" />
@endif
