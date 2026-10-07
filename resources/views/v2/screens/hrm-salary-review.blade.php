{{-- HRM > Payroll > Salary Review (v2). Data = App\Support\V2\Data\PayrollSalaryReview: rincian gaji per pegawai satu periode, diunduh sebagai Excel untuk telaah manual direksi.
     Hanya admin / DIREKSI / MGR_ACC (SecUser::canViewPayrollAmounts); nilai terenkripsi didekripsi lewat model. --}}
@php
    use App\Support\V2\Data\PayrollSalaryReview as Review;
    use App\Support\V2\Src;
    use Devlib\Locale;

    abort_unless(auth()->user()->canViewPayrollAmounts(), 403);

    $runId = request()->integer('run') ?: Review::defaultRun();
    $run = Review::run($runId);
    $rows = $run ? array_slice(Review::rows($run->id_run), 0, -1) : [];
    $src = Src::of('tm2pyr.pyr_payslip', 'tm2pyr.pyr_payroll_run', 'tm2mst.emp_employee');
@endphp

<x-dl::card :title="raw(tf('Salary Review (:n employees)', ['n' => count($rows)]))" description="Salary breakdown per employee for the chosen payroll period, downloadable to Excel.">
    <x-dl::action-form method="get" action="{{ route('v2.screen', 'hrm-salary-review') }}">
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="run" label="Payroll Period" :options="Review::runOptions()" :value="$run?->id_run" :placeholder="null" :source="$src" />
        </x-dl::form.row>
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="fa-filter" label="Filter" />
            @if ($run && $rows)<x-dl::button icon="fa-file-excel" label="Download Excel" :href="route('v2.payroll.salary-xlsx', $run->id_run)" />@endif
        </x-dl::action-bar>
    </x-dl::action-form>
    @if (! $run)
        <x-dl::alert tone="warn" message="No calculated payroll period yet." />
    @else
        <x-dl::alert tone="info" :message="raw(t('Total net salary of this period').': '.Locale::money(array_sum(array_column($rows, 15))))" />
        <x-dl::table :columns="['code' => 'Employee No.', 'name' => 'Name', 'dept' => 'Department', 'basic' => ['Basic Salary', 'num' => true], 'net' => ['Net Salary', 'num' => true]]"
            :rows="array_map(fn ($r) => ['code' => raw($r[0]), 'name' => raw($r[1]), 'dept' => raw($r[2]), 'basic' => Locale::money($r[4]), 'net' => Locale::money($r[15])], $rows)"
            empty="No payslip for this period." :source="$src" />
    @endif
</x-dl::card>
