{{--
 REMARK
 Tujuan       : Form Salary & Tax Master (menggantikan dialog Set Salary & Tax halaman v1 pages::hrm.payroll-employee-salary): gaji pokok, tunjangan keahlian / loyalitas / prestasi, bonus pagi dan hadir per hari, status PTKP, saklar BPJS dan PPh21 satu pegawai; Save membuat versi baru (versi lama ditutup dengan tanggal akhir); tab Salary history. Nominal rahasia: hanya user berhak payroll atas pegawai itu.
 Sumber hulu  : tm2pyr.pyr_employee_salary (gaji pokok terenkripsi lewat cast model) + tm2mst.emp_employee; aturan di App\Services\Payroll\EmployeeSalarySettingService dan PayrollAccess.
 Input        : Query id (emp_employee.employee_id); session v2_flash dan errors.
 Output       : POST v2.hrm.salary.save.
 Dipakai oleh : Dibuka dari grid hrm-salary (Open). Dibaca PayrollEngineService saat hitung gaji.
 Status       : Aktif (T8 Payroll).
--}}
@php
    use App\Models\Master\EmpEmployee;
    use App\Models\Pyr\PyrEmployeeSalary;
    use App\Services\Payroll\EmployeeSalarySettingService as Sal;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'hrm-salary');
    $svc = app(Sal::class);
    $user = Auth::user();
    $e = EmpEmployee::with('position')->find((int) request('id'));
    abort_unless($e, 404);
    abort_unless(Sal::can($user, $e), 403, t('You do not have access to view or set the salary of this employee.'));
    $cur = PyrEmployeeSalary::activeFor($e->employee_id);
    $s = fn (string $col) => Src::of('tm2pyr.pyr_employee_salary.'.$col);
    $val = fn (string $col) => old($col, $cur?->{$col} !== null ? (float) $cur->{$col} : null);
    $flag = fn (string $col) => (bool) old($col, $cur?->{$col} ?? false);
    $versions = Sal::versions($e, $user);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="hrm-salary-form" :title="raw($e->full_name)" :subtitle="raw($e->employee_code.' · '.($e->position?->position_name ?? '—'))" panel="top" kind="master" status="active" :can-input="true" :hide="['deactivate', 'new']"
    :action="route('v2.hrm.salary.save')" :back="$home" :hidden="['id' => $e->employee_id]" :tabs="['salary' => t('Salary and tax'), 'versions' => t('Salary history')]" :log="$svc->history($e, $user)">
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Employee" :value="raw($e->employee_code)" />
        <x-dl::strip-kv kind="grand" label="Salary setup" :value="t($cur ? 'Set' : 'Not set')" />
        <x-dl::strip-kv kind="grand" label="PTKP" :value="raw($cur?->ptkp_status ?: '—')" />
        <x-dl::strip-kv kind="grand" label="Effective from" :value="$cur?->effective_from ? Locale::date($cur->effective_from) : '—'" />
    </x-slot:strip>

    <x-slot:salary>
        <x-dl::card title="Salary components (IDR per month)">
            <x-dl::hint note>Saving creates a new version: the old setting is closed with an end date, so the salary history is kept. These amounts are confidential.</x-dl::hint>
            <x-dl::form.row :cols="4">
                <x-dl::field.decimal name="basic_salary_monthly" label="Base salary / month (IDR)" required unit="IDR" :value="$val('basic_salary_monthly')" :source="$s('basic_salary_monthly')" />
                <x-dl::field.decimal name="tj_keahlian_monthly" label="Skill allowance / month (IDR)" unit="IDR" :value="$val('tj_keahlian_monthly')" :source="$s('tj_keahlian_monthly')" />
                <x-dl::field.decimal name="tj_loyalitas_monthly" label="Loyalty allowance / month (IDR)" unit="IDR" :value="$val('tj_loyalitas_monthly')" :source="$s('tj_loyalitas_monthly')" />
                <x-dl::field.decimal name="tj_prestasi_monthly" label="Performance allowance / month (IDR)" unit="IDR" :value="$val('tj_prestasi_monthly')" :source="$s('tj_prestasi_monthly')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.decimal name="bonus_pagi_amount" label="Morning bonus / day (IDR)" unit="IDR" :value="$val('bonus_pagi_amount')" :source="$s('bonus_pagi_amount')" />
                <x-dl::field.decimal name="bonus_hadir_amount" label="Attendance bonus / day (IDR)" unit="IDR" :value="$val('bonus_hadir_amount')" :source="$s('bonus_hadir_amount')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="Tax and BPJS">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="ptkp_status" label="PTKP status (required when PPh21 is enabled)" :options="Sal::PTKP" :value="old('ptkp_status', $cur?->ptkp_status)" placeholder="— select —" :source="$s('ptkp_status')" />
                <x-dl::field.checkbox name="bpjs_active" text="Deduct BPJS (JHT/JP/Kes)" :value="$flag('bpjs_active')" :source="$s('bpjs_active')" />
                <x-dl::field.checkbox name="pph21_active" text="Deduct PPh21 (TER)" :value="$flag('pph21_active')" :source="$s('pph21_active')" />
            </x-dl::form.row>
        </x-dl::card>
    </x-slot:salary>

    <x-slot:versions>
        <x-dl::card title="Salary versions">
            <x-dl::table :columns="['from' => 'From', 'to' => 'Until', 'basic' => 'Base salary (IDR)', 'skill' => 'Skill allowance (IDR)', 'ptkp' => 'PTKP', 'bpjs' => 'BPJS', 'pph21' => 'PPh21', 'by' => 'Changed by']" :rows="$versions" />
        </x-dl::card>
    </x-slot:versions>
</x-dl::form>
