{{--
 REMARK
 Tujuan       : HR & Payroll: headcount, cuti / dirumahkan, payroll terakhir (net, hanya untuk yang berhak melihat semua payroll), pengajuan cuti pending, pegawai per departemen, riwayat payroll run, cuti disetujui per jenis.
 Sumber hulu  : App\Support\V2\Data\Mgmt\HrReport (emp_employee, org_position, org_department, emp_leave_request, pyr_payroll_run, pyr_payslip); pindahan layar v1 pages::report.hr.
 Input        : Tidak ada (angka berjalan).
 Output       : x-dl::kpi, x-dl::progress, x-dl::table, x-dl::chips + x-dl::badge.
 Dipakai oleh : Layar v2 slug mgmt-hr lewat rute v2.screen (menu Management Reports > HR & Payroll, sys_app_menu MGMT_HR)
 Status       : Aktif
--}}
@php
    use App\Support\V2\Data\Mgmt\HrReport;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $r = new HrReport;
    $src = Src::of('tm2mst.emp_employee', 'tm2mst.emp_leave_request', 'tm2pyr.pyr_payroll_run', 'tm2pyr.pyr_payslip');
    $h = $r->headcount();
    $run = $r->latestRun();
    $leave = $r->leaveByType();
@endphp

<x-dl::card title="HR & Payroll Report" description="Headcount, leave, and payroll summary." />

<x-dl::kpi-row>
    <x-dl::kpi label="Active Employees Working" :value="$h['active']" icon="fa-users" tone="accent" :note="tf(':n recorded in total', ['n' => $h['total']])" />
    <x-dl::kpi label="On Leave / Laid Off" :value="$h['cuti'] + $h['dirumahkan']" icon="fa-house-user" tone="warn" note="Temporarily not working" />
    @if ($r->canSeePayroll())
        <x-dl::kpi label="Latest Payroll (Net)" :value="$r->latestRunNet()" format="money" icon="fa-money-check-dollar" tone="success" :note="$run ? $run->period_start->format('M Y').' · '.$run->run_status : t('No run yet')" />
    @else
        <x-dl::kpi label="Latest Payroll (Net)" value="—" icon="fa-money-check-dollar" tone="success" :note="$run ? $run->period_start->format('M Y').' · '.$run->run_status : t('No run yet')" />
    @endif
    <x-dl::kpi label="Pending Leave Requests" :value="$r->pendingLeave()" icon="fa-triangle-exclamation" tone="danger" note="Waiting for HR approval" />
</x-dl::kpi-row>

<x-dl::card title="Active Employees per Department (Top 8)">
    @foreach ($r->byDepartment() as $d)
        <x-dl::progress :value="$h['active'] > 0 ? round($d['n'] / $h['active'] * 100, 1) : 0" :label="raw($d['name'].' · '.$d['n'].' '.t('people'))" />
    @endforeach
</x-dl::card>

<x-dl::card title="Payroll Run History">
    <x-dl::table :columns="['period' => 'Period', 'status' => 'Status']" :rows="$r->runRows()" empty="No payroll run yet." :source="$src" />
</x-dl::card>

<x-dl::card title="Approved Leave This Year per Type">
    @forelse ($leave as $l)
        <x-dl::chips><x-dl::badge :label="raw($l['type'].' '.$l['n'])" tone="info" /></x-dl::chips>
    @empty
        <x-dl::hint>@t('No approved leave this year.')</x-dl::hint>
    @endforelse
</x-dl::card>
