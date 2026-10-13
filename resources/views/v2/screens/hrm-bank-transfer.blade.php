{{--
 REMARK
 Tujuan       : Membuat batch transfer gaji ke rekening bank pegawai per periode payroll dan mengunduhnya sebagai CSV.
 Sumber hulu  : App\Support\V2\Data\PayrollDisbursement (tm2pyr.pyr_payslip, pyr_payroll_run; tm2mst.emp_bank_account, emp_employee).
 Input        : Query run (periode payroll; bawaan Pay::defaultRun()).
 Output       : x-dl::card + filter periode (field.select), tabel pegawai berekening aktif, tombol unduh ke route v2.payroll.bank-csv.
 Dipakai oleh : Rute v2.screen dengan slug 'hrm-bank-transfer' (menu v2:hrm-bank-transfer di sys_app_menu), dirender view layar generik v2.
 Status       : Aktif (CSV generik; format kliring resmi tiap bank = butir terbuka).
--}}
@php
    use App\Support\V2\Data\PayrollDisbursement as Pay;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $runId = request()->integer('run') ?: Pay::defaultRun();
    $run = Pay::run($runId);
    $rows = $run ? Pay::bankRows($run->id_run) : [];
    $src = Src::of('tm2pyr.pyr_payslip', 'tm2pyr.pyr_payroll_run', 'tm2mst.emp_bank_account', 'tm2mst.emp_employee');
@endphp

<x-dl::card :title="raw(tf('Payroll Transfer to Bank Accounts (:n employees)', ['n' => count($rows)]))" description="Employees with an active primary bank account for the chosen period.">
    <x-dl::action-form method="get" action="{{ route('v2.screen', 'hrm-bank-transfer') }}">
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="run" label="Payroll Period" :options="Pay::runOptions()" :value="$run?->id_run" :placeholder="null" :source="$src" />
        </x-dl::form.row>
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="fa-filter" label="Filter" />
            @if ($run && $rows)<x-dl::button icon="fa-download" label="Download CSV Batch" :href="route('v2.payroll.bank-csv', $run->id_run)" />@endif
        </x-dl::action-bar>
    </x-dl::action-form>
    @if (! $run)
        <x-dl::alert tone="warn" message="No confirmed payroll period yet." />
    @else
        <x-dl::alert tone="info" :message="raw(t('Total transfer value of this batch').': '.Locale::money(array_sum(array_column($rows, 'net'))))" />
        <x-dl::table :columns="['code' => 'Employee No.', 'name' => 'Name', 'bank' => 'Bank', 'account_no' => 'Account No.', 'holder' => 'Account Holder', 'net' => ['Net Salary', 'num' => true]]"
            :rows="array_map(fn ($r) => ['code' => raw($r['code']), 'name' => raw($r['name']), 'bank' => raw($r['bank']), 'account_no' => raw($r['account_no']), 'holder' => raw($r['holder']), 'net' => Locale::money($r['net'])], $rows)"
            empty="No employee with a bank account for this period." :source="$src" />
    @endif
</x-dl::card>
