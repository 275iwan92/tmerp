{{--
 REMARK
 Tujuan       : Form Master Employee v2 (konsep A2 disetujui Jo): banner pegawai (foto, nama, chip status, cincin kelengkapan), empat KPI (masa kerja, sisa cuti, absen, disiplin), tab Profile / Job / Contract / Family / Education / Bank / Discipline dan History bawaan.
 Sumber hulu  : App\Support\V2\Data\EmployeeProfile (KPI, kelengkapan, opsi); tabel tm2mst.emp_employee dan tab anak emp_contract, emp_family, emp_education, emp_disciplinary, emp_bank_account.
 Input        : Query id (pegawai) atau new=1; session v2_flash dan errors.
 Output       : POST v2.hrm.employee.save (Save / Activate / Deactivate) -> EmployeeProfileService. Tab Contract / Family / Education / Discipline sementara baca-saja; ubah lewat layar v1 (tombol Manage).
 Dipakai oleh : Dibuka dari grid hrm-employee (Open dan + New Record). Tab Documents, BPJS, Account menyusul tahap berikutnya.
 Status       : Aktif (mockup.test dulu, keputusan Jo 2026-10-16). Gaji kontrak tersembunyi mengikuti aturan v1 (admin, posisi salary_hidden_from_hr, tipe KONTRAK / BORONGAN).
--}}
@php
    use App\Models\Master\EmpEmployee;
    use App\Services\Hrm\EmployeeBankService;
    use App\Services\Hrm\EmployeeProfileService as Profile;
    use App\Support\V2\Data\EmployeeProfile as Data;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'hrm-employee');
    $e = request('id') ? EmpEmployee::with('position.department', 'activeContract', 'contracts', 'families', 'educations', 'disciplinaries')->find((int) request('id')) : null;
    abort_if(request('id') && ! $e, 404);
    abort_unless($e || request('new'), 404);
    $new = ! $e;
    $e ??= new EmpEmployee(['gender' => 'L', 'work_status' => 'WORKING', 'staff_type' => 'NON_STAFF', 'payment_frequency' => 'MONTHLY', 'activate' => true]);
    $user = Auth::user();
    $s = fn (string $col) => Src::of('tm2mst.emp_employee.'.$col);
    $v = fn (string $col) => old($col, $e->{$col} instanceof \DateTimeInterface ? $e->{$col}->format('Y-m-d') : $e->{$col});
    $missing = $new ? [] : Data::missing($e);
    $byTab = collect($missing)->groupBy('tab')->map->count();
    $tab = fn (string $label, string $key) => $byTab->has($key) ? $label.' ('.$byTab[$key].')' : $label;
    $todo = t('To complete').': '.collect($missing)->map(fn ($m) => t($m['label']).' ('.t(ucfirst($m['tab'])).')')->implode(', ');
    $bank = $new ? null : EmployeeBankService::primary($e->employee_id);
    $seeSalary = fn ($c) => $user->is_admin || (! $e->position?->salary_hidden_from_hr && $c && in_array($c->employment_type, ['KONTRAK', 'BORONGAN'], true));
    $classic = $new ? route('hrm.employee').'?new=1' : route('hrm.employee').'?open='.$e->employee_id;
    $manage = fn () => t('Contract, family, education and discipline records are edited in the classic screen for now.');
    $statusTone = ['WORKING' => 'success', 'DIRUMAHKAN' => 'warn', 'CUTI' => 'info'];
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="hrm-employee-form" :title="$new ? 'New Employee' : raw($e->full_name)" :subtitle="$new ? null : raw($e->employee_code.' · '.($e->position?->position_name ?? '—'))"
    panel="top" kind="master" :status="$new ? 'new' : ($e->activate ? 'active' : 'inactive')"
    :action="route('v2.hrm.employee.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'" enctype="multipart/form-data"
    :hidden="$new ? [] : ['id' => $e->employee_id]"
    :tabs="['profile' => $tab('Profile', 'profile'), 'job' => $tab('Job', 'job'), 'contract' => 'Contract', 'family' => $tab('Family', 'family'), 'education' => 'Education', 'bank' => 'Bank', 'discipline' => 'Discipline']"
    :created-by="$e->created_by" :created-at="$e->created_at?->toDateTimeString()" :updated-by="$e->updated_by" :updated-at="$e->updated_at?->toDateTimeString()">
    <x-slot:strip>
        <x-dl::avatar size="lg" :name="raw($new ? 'New Employee' : $e->full_name)" :photo="$e->photo_path ? asset('storage/'.$e->photo_path) : null" :sub="$new ? t('Fill the Profile and Job tabs, then Save.') : raw($e->employee_code.' · '.($e->position?->department?->department_name ?? '—'))" />
        @unless ($new)
            <x-dl::chips>
                <x-dl::badge :label="t(['WORKING' => 'Working', 'DIRUMAHKAN' => 'Furloughed', 'CUTI' => 'On Leave'][$e->work_status] ?? $e->work_status)" :tone="$statusTone[$e->work_status] ?? 'neutral'" />
                <x-dl::badge :label="$e->staff_type === 'STAFF' ? 'Staff' : 'Non-staff'" tone="info" icon="fa-lock" />
                <x-dl::badge :label="$e->payment_frequency === 'WEEKLY' ? 'Weekly' : 'Monthly'" tone="info" icon="fa-lock" />
                @if ($e->activeContract)<x-dl::badge :label="$e->activeContract->employment_type" tone="neutral" />@endif
                @if ($e->join_date)<x-dl::badge :label="tf('Joined :d', ['d' => Locale::date($e->join_date)])" tone="neutral" />@endif
            </x-dl::chips>
            <x-dl::progress-ring :value="Data::percent($e)" :size="64" :caption="count($missing) ? tf(':n to complete', ['n' => count($missing)]) : t('Complete')" />
            <x-dl::kpi-row>
                @foreach (Data::kpis($e) as $k)
                    <x-dl::kpi :label="$k['label']" :value="$k['value']" :tone="$k['tone']" :icon="$k['icon']" :note="$k['note']" />
                @endforeach
            </x-dl::kpi-row>
            @if ($missing)
                <x-dl::alert tone="warn" :message="$todo" />
            @endif
        @endunless
    </x-slot:strip>

    <x-slot:profile>
        <x-dl::card title="Identity">
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="full_name" label="Full name" required :value="$v('full_name')" :source="$s('full_name')" />
                <x-dl::field.text name="nickname" label="Nickname" :value="$v('nickname')" :source="$s('nickname')" />
                <x-dl::field.select name="gender" label="Gender" :options="['L' => t('Male'), 'P' => t('Female')]" :value="$v('gender')" :placeholder="null" :source="$s('gender')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="birth_place" label="Birth place" :value="$v('birth_place')" :source="$s('birth_place')" />
                <x-dl::field.date name="birth_date" label="Birth date (as on KTP)" :value="$v('birth_date')" hint="Minimum age 17." :source="$s('birth_date')" />
                <x-dl::field.select name="religion" label="Religion" :options="array_combine(Profile::RELIGIONS, Profile::RELIGIONS)" :value="$v('religion')" :source="$s('religion')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="nik_ktp" label="NIK KTP" :maxlength="16" :case-toggle="false" :value="$v('nik_ktp')" hint="Exactly 16 digits." :source="$s('nik_ktp')" />
                <x-dl::field.text name="no_kk" label="Family card (KK) no." :case-toggle="false" :value="$v('no_kk')" :source="$s('no_kk')" />
                <x-dl::field.text name="npwp_number" label="NPWP" :case-toggle="false" :value="$v('npwp_number')" :source="$s('npwp_number')" />
            </x-dl::form.row>
            <x-dl::field.upload name="photo" label="Profile photo" accept=".jpg,.jpeg,.png,.webp" :source="$s('photo_path')" />
        </x-dl::card>
        <x-dl::card title="Contact and address">
            <x-dl::form.row :cols="2">
                <x-dl::field.text name="phone" label="Mobile / WhatsApp (main)" :case-toggle="false" :value="$v('phone')" :source="$s('phone')" />
                <x-dl::field.text name="email_personal" label="Personal email" type="email" :case-toggle="false" :value="$v('email_personal')" :source="$s('email_personal')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                <x-dl::field.text name="address_detail_ktp" label="KTP address" multiline :value="$v('address_detail_ktp')" :source="$s('address_detail_ktp')" />
                <x-dl::field.text name="address_detail_domisili" label="Residence address" multiline :value="$v('address_detail_domisili')" :source="$s('address_detail_domisili')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="Emergency contact">
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="emergency_contact_name" label="Name" :value="$v('emergency_contact_name')" :source="$s('emergency_contact_name')" />
                <x-dl::field.text name="emergency_contact_relation" label="Relation" :value="$v('emergency_contact_relation')" :source="$s('emergency_contact_relation')" />
                <x-dl::field.text name="emergency_contact_phone" label="Phone" :case-toggle="false" :value="$v('emergency_contact_phone')" :source="$s('emergency_contact_phone')" />
            </x-dl::form.row>
        </x-dl::card>
    </x-slot:profile>

    <x-slot:job>
        <x-dl::card title="Organization and employment">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="position_id" label="Position (department — position)" :options="Data::positionOptions()" :value="$v('position_id')" :source="Src::of('tm2mst.emp_employee.position_id', 'tm2mst.org_position')" />
                <x-dl::field.date name="join_date" label="Join date" :value="$v('join_date')" :source="$s('join_date')" />
                <x-dl::field.select name="work_status" label="Work status" :options="['WORKING' => t('Working'), 'DIRUMAHKAN' => t('Furloughed'), 'CUTI' => t('On Leave')]" :value="$v('work_status')" :placeholder="null" :source="$s('work_status')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.date name="work_status_since" label="Work status since" :value="$v('work_status_since')" :source="$s('work_status_since')" />
                @if (Profile::canEditStaffType($user))
                    <x-dl::field.select name="staff_type" label="Employee type" required :options="['STAFF' => 'Staff', 'NON_STAFF' => 'Non-staff']" :value="$v('staff_type')" :placeholder="null" hint="Decides who may see this person's payroll." :source="$s('staff_type')" />
                @else
                    <x-dl::field.display label="Employee type" :value="$e->staff_type === 'STAFF' ? 'Staff' : 'Non-staff'" :source="$s('staff_type')" />
                @endif
                @if (Profile::canEditPayrollMethod($user, $new ? null : $e))
                    <x-dl::field.select name="payment_frequency" label="Payroll method" required :options="['MONTHLY' => 'Monthly', 'WEEKLY' => 'Weekly']" :value="$v('payment_frequency')" :placeholder="null" :source="$s('payment_frequency')" />
                @else
                    <x-dl::field.display label="Payroll method" :value="$e->payment_frequency === 'WEEKLY' ? 'Weekly' : 'Monthly'" :source="$s('payment_frequency')" />
                @endif
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="Production defaults" description="Prefill the schedule only; assignments are never locked.">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="default_machine_id" label="Default machine" :options="Data::machineOptions()" :value="$v('default_machine_id')" :source="Src::of('tm2mst.emp_employee.default_machine_id', 'tm2mst.prod_machine')" />
                <x-dl::field.select name="default_shift_id" label="Default shift" :options="Data::shiftOptions()" :value="$v('default_shift_id')" :source="Src::of('tm2mst.emp_employee.default_shift_id', 'tm2mst.emp_shift')" />
            </x-dl::form.row>
        </x-dl::card>
    </x-slot:job>

    <x-slot:contract>
        <x-dl::alert tone="info" :message="$manage()" />
        <x-dl::table :columns="['type' => 'Type', 'start' => 'Start', 'end' => 'End', 'salary' => ['Salary', 'num' => true, 'raw' => true], 'status' => 'Status']"
            :rows="$e->contracts->map(fn ($c) => ['type' => $c->employment_type.($c->is_probation ? ' (Probation)' : ''), 'start' => Locale::date($c->start_date), 'end' => $c->end_date ? Locale::date($c->end_date) : '—',
                'salary' => $seeSalary($c) ? Locale::money($c->salary_amount).' / '.$c->salary_type : t('Hidden'), 'status' => $c->activate ? 'Active' : 'Ended'])->all()"
            empty="No contract yet." :source="Src::of('tm2mst.emp_contract')" />
        <x-dl::button variant="secondary" icon="fa-up-right-from-square" label="Manage in classic screen" :href="$classic" />
    </x-slot:contract>

    <x-slot:family>
        <x-dl::alert tone="info" :message="$manage()" />
        <x-dl::table :columns="['relation' => 'Relation', 'name' => 'Name', 'birth' => 'Birth date', 'state' => 'Status']"
            :rows="$e->families->map(fn ($f) => ['relation' => raw(ucwords(strtolower(str_replace('_', ' ', $f->relation)))), 'name' => raw($f->full_name), 'birth' => $f->birth_date ? Locale::date($f->birth_date) : '—',
                'state' => $f->is_deceased ? 'Deceased' : 'Alive'])->all()"
            empty="No family recorded yet." :source="Src::of('tm2mst.emp_family')" />
        <x-dl::button variant="secondary" icon="fa-up-right-from-square" label="Manage in classic screen" :href="$classic" />
    </x-slot:family>

    <x-slot:education>
        <x-dl::alert tone="info" :message="$manage()" />
        <x-dl::table :columns="['type' => 'Type', 'name' => 'Activity', 'date' => 'Date', 'funded' => 'Funded by']"
            :rows="$e->educations->map(fn ($d) => ['type' => ucwords(strtolower(str_replace('_', ' ', $d->education_type))), 'name' => raw($d->activity_name), 'date' => Locale::date($d->activity_date),
                'funded' => ucwords(strtolower($d->funded_by))])->all()"
            empty="No education or certification recorded yet." :source="Src::of('tm2mst.emp_education')" />
        <x-dl::button variant="secondary" icon="fa-up-right-from-square" label="Manage in classic screen" :href="$classic" />
    </x-slot:education>

    <x-slot:bank>
        @if ($new)
            <x-dl::alert tone="info" message="Save the employee first, then set the salary account here." />
        @else
            <x-dl::card title="Salary account" description="Banks come from Master Supplier category BANK. Leave the bank empty if the employee is paid in cash.">
                <x-dl::form.row :cols="3">
                    <x-dl::field.select name="bank_supplier_id" label="Bank" :options="EmployeeBankService::bankOptions()" :value="old('bank_supplier_id', $bank?->bank_supplier_id)" placeholder="— Cash —" :source="Src::of('tm2mst.emp_bank_account.bank_supplier_id', 'tm2mst.sup_supplier')" />
                    <x-dl::field.text name="bank_account_no" label="Account no." :case-toggle="false" :value="old('bank_account_no', $bank?->account_no)" :source="Src::of('tm2mst.emp_bank_account.account_no')" />
                    <x-dl::field.text name="bank_account_holder" label="Account holder" :value="old('bank_account_holder', $bank?->account_holder_name)" :source="Src::of('tm2mst.emp_bank_account.account_holder_name')" />
                </x-dl::form.row>
            </x-dl::card>
        @endif
    </x-slot:bank>

    <x-slot:discipline>
        <x-dl::alert tone="info" :message="$manage()" />
        <x-dl::table :columns="['date' => 'Date', 'type' => 'Type', 'reason' => 'Reason', 'by' => 'Issued by']"
            :rows="$e->disciplinaries->map(fn ($d) => ['date' => Locale::date($d->incident_date), 'type' => $d->type, 'reason' => raw($d->reason), 'by' => raw($d->issued_by)])->all()"
            empty="No discipline record." :source="Src::of('tm2mst.emp_disciplinary')" />
        <x-dl::button variant="secondary" icon="fa-up-right-from-square" label="Manage in classic screen" :href="$classic" />
    </x-slot:discipline>
</x-dl::form>
