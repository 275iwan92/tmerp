{{-- HRM > Payroll > Cash Roster & Denominations (v2). Data & aturan = App\Support\V2\Data\PayrollDisbursement, sama dengan layar v1: pegawai TANPA rekening bank
     utama aktif, rincian amplop per pecahan uang, jumlah lembar per pecahan, dan cetakan serah terima (x-dl::print-doc, jenis cash-roster). --}}
@php
    use App\Support\V2\Data\PayrollDisbursement as Pay;
    use App\Support\V2\Print\PrintDocs;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $runId = request()->integer('run') ?: Pay::defaultRun();
    $run = Pay::run($runId);
    $rows = $run ? Pay::cashRows($run->id_run) : [];
    $src = Src::of('tm2pyr.pyr_payslip', 'tm2pyr.pyr_payroll_run', 'tm2mst.emp_bank_account', 'tm2mst.emp_employee');
@endphp

<x-dl::card :title="raw(tf('Cash Salary Payout (:n employees)', ['n' => count($rows)]))" description="Employees without an active bank account, with the breakdown of notes for each envelope.">
    <x-dl::action-form method="get" action="{{ route('v2.screen', 'hrm-cash-roster') }}">
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="run" label="Payroll Period" :options="Pay::runOptions()" :value="$run?->id_run" :placeholder="null" :source="$src" />
        </x-dl::form.row>
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="fa-filter" label="Filter" />
            @if ($run && $rows)<x-dl::button icon="fa-print" label="Print Handover" :href="PrintDocs::url('cash-roster', $run->id_run)" />@endif
        </x-dl::action-bar>
    </x-dl::action-form>
    @if (! $run)
        <x-dl::alert tone="warn" message="No confirmed payroll period yet." />
    @else
        <x-dl::alert tone="info" :message="raw(t('Total cash').': '.Locale::money(array_sum(array_column($rows, 'net'))))" />
        <x-dl::table :columns="array_map(fn ($d) => ['Rp '.Locale::number($d), 'num' => true], array_combine(Pay::DENOMINATIONS, Pay::DENOMINATIONS))" :rows="[array_map(fn ($n) => Locale::number($n), Pay::denominationTotals($rows))]"
            :source="$src" />
        <x-dl::table :columns="['code' => 'Employee No.', 'name' => 'Name', 'dept' => 'Department', 'net' => ['Net Salary', 'num' => true], 'envelope' => 'Envelope Breakdown']"
            :rows="array_map(fn ($r) => ['code' => raw($r['code']), 'name' => raw($r['name']), 'dept' => raw($r['dept'] ?: '—'), 'net' => Locale::money($r['net']), 'envelope' => raw(Pay::breakdownText($r['breakdown']))], $rows)"
            empty="No cash employee for this period." :source="$src" />
    @endif
</x-dl::card>
