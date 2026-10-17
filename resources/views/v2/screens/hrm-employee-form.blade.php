{{--
 REMARK
 Tujuan       : Form Master Employee v2 (konsep A2 disetujui Jo): banner pegawai (foto, nama, chip status, cincin kelengkapan), empat KPI (masa kerja, sisa cuti, absen, disiplin), tab Profile / Job / Contract / Family / Education / Documents / BPJS / Bank / Discipline / Account (hanya Admin App) dan History bawaan.
 Sumber hulu  : App\Support\V2\Data\EmployeeProfile (KPI, kelengkapan, opsi); tabel tm2mst.emp_employee dan tab anak emp_contract, emp_family, emp_education, emp_disciplinary, emp_bank_account.
 Input        : Query id (pegawai) atau new=1; session v2_flash dan errors.
 Output       : POST v2.hrm.employee.save (Save / Activate / Deactivate / Remove Photo) -> EmployeeProfileService. POST v2.hrm.employee.contract / family / deceased / education / discipline -> EmployeeRecordService (tiap dialog menyimpan sendiri; tanpa edit / hapus baris).
 Dipakai oleh : Dibuka dari grid hrm-employee (Open dan + New Record), kembali dari dialog lewat ?tab=; flash v2_login_card = kartu login pegawai baru (sekali tampil). Documents = unggah dokumen HR (EmployeeRecordService::addDocument); BPJS ikut Save utama; Account = EmployeeAccountService (buat login, reset password, aktif / nonaktif), kartu login sekali tampil.
 Status       : Aktif (mockup.test dulu, keputusan Jo 2026-10-16). Gaji kontrak tersembunyi mengikuti aturan v1 (admin, posisi salary_hidden_from_hr, tipe KONTRAK / BORONGAN).
--}}
@php
    use App\Models\Master\EmpEmployee;
    use App\Services\Hrm\EmployeeAccountService as Account;
    use App\Services\Hrm\EmployeeBankService;
    use App\Services\Hrm\EmployeeProfileService as Profile;
    use App\Services\Hrm\EmployeeRecordService as Records;
    use App\Support\V2\Data\EmployeeProfile as Data;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'hrm-employee');
    $e = request('id') ? EmpEmployee::with('position.department', 'activeContract', 'contracts', 'families', 'educations', 'disciplinaries', 'documents', 'bpjsKesehatan', 'bpjsKetenagakerjaan')->find((int) request('id')) : null;
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
    $kes = $e->bpjsKesehatan;
    $ket = $e->bpjsKetenagakerjaan;
    $isAdmin = (bool) $user->is_admin;
    $acct = $isAdmin && ! $new ? app(Account::class)->userOf($e) : null;
    $tabs = ['profile' => $tab('Profile', 'profile'), 'job' => $tab('Job', 'job'), 'contract' => 'Contract', 'family' => $tab('Family', 'family'), 'education' => 'Education']
        + ($new ? [] : ['documents' => 'Documents', 'bpjs' => 'BPJS']) + ['bank' => 'Bank', 'discipline' => 'Discipline'] + ($isAdmin && ! $new ? ['account' => 'Account'] : []);
    $seeSalary = fn ($c) => $user->is_admin || (! $e->position?->salary_hidden_from_hr && $c && in_array($c->employment_type, ['KONTRAK', 'BORONGAN'], true));
    $statusTone = ['WORKING' => 'success', 'DIRUMAHKAN' => 'warn', 'CUTI' => 'info'];
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="hrm-employee-form" :title="$new ? 'New Employee' : raw($e->full_name)" :subtitle="$new ? null : raw($e->employee_code.' · '.($e->position?->position_name ?? '—'))"
    panel="top" kind="master" :status="$new ? 'new' : ($e->activate ? 'active' : 'inactive')"
    :action="route('v2.hrm.employee.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'" enctype="multipart/form-data"
    :hidden="$new ? [] : ['id' => $e->employee_id, 'bpjs_present' => 1]"
    :tabs="$tabs"
    :created-by="$e->created_by" :created-at="$e->created_at?->toDateTimeString()" :updated-by="$e->updated_by" :updated-at="$e->updated_at?->toDateTimeString()">
    @if ($e->photo_path)
        <x-slot:special>
            <x-dl::button size="sm" preset="delete" label="Remove Photo" data-f-act="remove_photo" data-dl-confirm="{{ t('Remove the profile photo of this employee?') }}" />
        </x-slot:special>
    @endif
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
        @if ($new)
            <x-dl::alert tone="info" message="Save the employee first, then add the contract." />
        @else
            <x-dl::action-bar>
                <x-dl::button type="button" size="sm" variant="secondary" icon="fa-plus" label="New Contract" data-dl-open="emp-contract" />
            </x-dl::action-bar>
            <x-dl::table :columns="['type' => 'Type', 'start' => 'Start', 'end' => 'End', 'salary' => ['Salary', 'num' => true, 'raw' => true], 'status' => 'Status']"
                :rows="$e->contracts->map(fn ($c) => ['type' => $c->employment_type.($c->is_probation ? ' (Probation)' : ''), 'start' => Locale::date($c->start_date), 'end' => $c->end_date ? Locale::date($c->end_date) : '—',
                    'salary' => $seeSalary($c) ? Locale::money($c->salary_amount).' / '.$c->salary_type : t('Hidden'), 'status' => $c->activate ? 'Active' : 'Ended'])->all()"
                empty="No contract yet." :source="Src::of('tm2mst.emp_contract')" />
        @endif
    </x-slot:contract>

    <x-slot:family>
        @if ($new)
            <x-dl::alert tone="info" message="Save the employee first, then add family members." />
        @else
            <x-dl::chips>
                @foreach (Profile::MANDATORY_FAMILY as $code => $label)
                    <x-dl::badge :label="$e->families->contains('relation', $code) ? $label : $label.' — missing'" :tone="$e->families->contains('relation', $code) ? 'success' : 'danger'" />
                @endforeach
            </x-dl::chips>
            <x-dl::action-bar>
                <x-dl::button type="button" size="sm" variant="secondary" icon="fa-plus" label="Add Family" data-dl-open="emp-family" />
                @if ($e->families->where('is_deceased', false)->isNotEmpty())
                    <x-dl::button type="button" size="sm" variant="secondary" icon="fa-plus" label="Mark Deceased" data-dl-open="emp-deceased" />
                @endif
            </x-dl::action-bar>
            <x-dl::table :columns="['relation' => 'Relation', 'name' => 'Name', 'birth' => 'Birth date', 'state' => 'Status']"
                :rows="$e->families->map(fn ($f) => ['relation' => t(Records::FAMILY_RELATIONS[$f->relation] ?? $f->relation), 'name' => raw($f->full_name), 'birth' => $f->birth_date ? Locale::date($f->birth_date) : '—',
                    'state' => $f->is_deceased ? tf('Deceased :d', ['d' => Locale::date($f->deceased_date)]) : 'Alive'])->all()"
                empty="No family recorded yet." :source="Src::of('tm2mst.emp_family')" />
        @endif
    </x-slot:family>

    <x-slot:education>
        @if ($new)
            <x-dl::alert tone="info" message="Save the employee first, then add education records." />
        @else
            <x-dl::action-bar>
                <x-dl::button type="button" size="sm" variant="secondary" icon="fa-plus" label="Add Activity" data-dl-open="emp-education" />
            </x-dl::action-bar>
            <x-dl::table :columns="['type' => 'Type', 'name' => 'Activity', 'date' => 'Date', 'cost' => ['Cost', 'num' => true, 'raw' => true], 'funded' => 'Funded by', 'bonding' => 'Bonding']"
                :rows="$e->educations->map(fn ($d) => ['type' => t(Records::EDUCATION_TYPES[$d->education_type] ?? $d->education_type), 'name' => raw($d->activity_name), 'date' => Locale::date($d->activity_date),
                    'cost' => Locale::money($d->cost), 'funded' => t(Records::FUNDED_BY[$d->funded_by] ?? $d->funded_by), 'bonding' => $d->is_bonding ? 'Yes' : 'No'])->all()"
                empty="No education or certification recorded yet." :source="Src::of('tm2mst.emp_education')" />
        @endif
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
        @if ($new)
            <x-dl::alert tone="info" message="Save the employee first, then record discipline." />
        @else
            <x-dl::action-bar>
                <x-dl::button type="button" size="sm" variant="secondary" icon="fa-plus" label="Add Record" data-dl-open="emp-discipline" />
            </x-dl::action-bar>
            <x-dl::table :columns="['date' => 'Date', 'type' => 'Type', 'reason' => 'Reason', 'by' => 'Issued by']"
                :rows="$e->disciplinaries->map(fn ($d) => ['date' => Locale::date($d->incident_date), 'type' => t(Records::DISCIPLINARY_TYPES[$d->type] ?? $d->type), 'reason' => raw($d->reason), 'by' => raw($d->issued_by)])->all()"
                empty="No discipline record." :source="Src::of('tm2mst.emp_disciplinary')" />
        @endif
    </x-slot:discipline>

    @unless ($new)
        <x-slot:documents>
            <x-dl::action-bar>
                <x-dl::button type="button" size="sm" variant="secondary" icon="fa-upload" label="Upload Document" data-dl-open="emp-document" />
            </x-dl::action-bar>
            <x-dl::table :columns="['type' => 'Type', 'no' => 'Document no.', 'valid' => 'Valid until', 'status' => 'Status', 'source' => 'Source', 'file' => 'File']"
                :rows="$e->documents->where('activate', true)->map(function ($d) {
                    [$label, $tone] = Data::documentStatus($d);

                    return ['type' => t(Records::DOCUMENT_TYPES[$d->document_type] ?? $d->document_type), 'no' => raw($d->document_no ?: '—'), 'valid' => $d->expiry_date ? Locale::date($d->expiry_date) : '—',
                        'status' => $label, 'source' => $d->uploaded_by_role === 'HR' ? 'HR (locked)' : 'Employee', 'file' => $d->file_path ? 'Uploaded' : 'No file'];
                })->values()->all()"
                empty="No document uploaded yet." :source="Src::of('tm2mst.emp_document')" />
            @foreach ($e->documents->where('activate', true)->filter->file_path as $d)
                <x-dl::button size="sm" variant="secondary" icon="fa-file" :label="t(Records::DOCUMENT_TYPES[$d->document_type] ?? $d->document_type)" :href="asset('storage/'.$d->file_path)" target="_blank" />
            @endforeach
        </x-slot:documents>

        <x-slot:bpjs>
            <x-dl::card title="BPJS Health" description="Reference data. The contribution amounts are calculated in Payroll.">
                <x-dl::form.row :cols="3">
                    <x-dl::field.text name="bpjs_kes_no" label="Membership no." :case-toggle="false" :value="old('bpjs_kes_no', $kes?->no_kepesertaan)" :source="Src::of('tm2mst.emp_bpjs_kesehatan.no_kepesertaan')" />
                    <x-dl::field.select name="bpjs_kes_class" label="Care class" :options="array_map('t', Profile::BPJS_CLASSES)" :value="old('bpjs_kes_class', $kes?->kelas_rawat)" :source="Src::of('tm2mst.emp_bpjs_kesehatan.kelas_rawat')" />
                    <x-dl::field.number name="bpjs_kes_dependents" label="Dependents" :value="old('bpjs_kes_dependents', $kes?->dependents_count ?? 0)" :source="Src::of('tm2mst.emp_bpjs_kesehatan.dependents_count')" />
                </x-dl::form.row>
            </x-dl::card>
            <x-dl::card title="BPJS Employment">
                <x-dl::form.row :cols="3">
                    <x-dl::field.text name="bpjs_ket_no" label="Membership no." :case-toggle="false" :value="old('bpjs_ket_no', $ket?->no_kepesertaan)" :source="Src::of('tm2mst.emp_bpjs_ketenagakerjaan.no_kepesertaan')" />
                    <x-dl::field.select name="bpjs_ket_status" label="Enrollment status" :options="array_map('t', Profile::BPJS_STATUSES)" :value="old('bpjs_ket_status', $ket?->enrollment_status)" :source="Src::of('tm2mst.emp_bpjs_ketenagakerjaan.enrollment_status')" />
                    <x-dl::field.select name="bpjs_ket_category" label="Category" :options="array_map('t', Profile::BPJS_CATEGORIES)" :value="old('bpjs_ket_category', $ket?->bpjs_category)" :source="Src::of('tm2mst.emp_bpjs_ketenagakerjaan.bpjs_category')" />
                </x-dl::form.row>
                <x-dl::field.text name="bpjs_ket_note" label="Enrollment note" :value="old('bpjs_ket_note', $ket?->enrollment_note)" :source="Src::of('tm2mst.emp_bpjs_ketenagakerjaan.enrollment_note')" />
                <x-dl::form.row :cols="5">
                    @foreach (Profile::BPJS_PROGRAMS as $p => $label)
                        <x-dl::field.checkbox :name="'bpjs_'.$p" :text="$label" :value="(bool) old('bpjs_'.$p, $ket?->{'program_'.$p} ?? true)" :source="Src::of('tm2mst.emp_bpjs_ketenagakerjaan.program_'.$p)" />
                    @endforeach
                </x-dl::form.row>
            </x-dl::card>
        </x-slot:bpjs>

        @if ($isAdmin)
            <x-slot:account>
                @if (! $acct)
                    <x-dl::alert tone="warn" message="This employee has no login yet." />
                    <x-dl::action-bar>
                        <x-dl::button type="button" size="sm" variant="secondary" icon="fa-user-plus" label="Create Login" data-dl-open="emp-account-create" />
                    </x-dl::action-bar>
                @else
                    <x-dl::card title="Login account" description="Groups and permissions are managed in Setup > Security > Account.">
                        <x-dl::form.row :cols="3">
                            <x-dl::field.display label="Username" :value="raw($acct->login)" :source="Src::of('tm2sys.sec_user.login')" />
                            <x-dl::field.display label="Status" :value="$acct->activate ? 'Active' : 'Inactive'" :source="Src::of('tm2sys.sec_user.activate')" />
                            <x-dl::field.display label="Password" :value="$acct->must_change_password ? 'Must be changed at next login' : 'Changed by the user'" :source="Src::of('tm2sys.sec_user.must_change_password')" />
                        </x-dl::form.row>
                        <x-dl::form.row :cols="3">
                            <x-dl::field.display label="Last password change" :value="Locale::stamp($acct->pswd_last_updated)" :source="Src::of('tm2sys.sec_user.pswd_last_updated')" />
                            <x-dl::field.display label="E-mail verified" :value="$acct->email_verified ? 'Yes' : 'No'" :source="Src::of('tm2sys.sec_user.email_verified')" />
                            <x-dl::field.display label="Groups" :value="raw($acct->groups->pluck('group_name')->implode(', ') ?: '—')" :source="Src::of('tm2sys.sec_user_group')" />
                        </x-dl::form.row>
                    </x-dl::card>
                    <x-dl::action-bar>
                        <x-dl::button type="button" size="sm" variant="secondary" icon="fa-key" label="Reset Password" data-dl-open="emp-account-reset" />
                        <x-dl::button type="button" size="sm" variant="secondary" :icon="$acct->activate ? 'fa-user-lock' : 'fa-user-check'" :label="$acct->activate ? 'Deactivate Account' : 'Activate Account'" data-dl-open="emp-account-toggle" />
                        <x-dl::button size="sm" variant="secondary" icon="fa-up-right-from-square" label="Open in Security" :href="route('security.index').'?tab=users&open='.$acct->id_user" />
                    </x-dl::action-bar>
                @endif
            </x-slot:account>
        @endif
    @endunless
</x-dl::form>

{{-- Dialog tambah (tiap dialog = satu form sendiri, di luar x-dl::form agar form tidak bersarang). --}}
@unless ($new)
    <x-dl::modal id="emp-contract" title="New Contract" icon="fa-file-contract" size="lg">
        <x-dl::action-form action="{{ route('v2.hrm.employee.contract', $e->employee_id) }}">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="employment_type" label="Type" required :options="array_combine(Records::EMPLOYMENT_TYPES, Records::EMPLOYMENT_TYPES)" value="TETAP" :placeholder="null" :source="Src::of('tm2mst.emp_contract.employment_type')" />
                <x-dl::field.date name="start_date" label="Start date" required :value="now()->toDateString()" :source="Src::of('tm2mst.emp_contract.start_date')" />
                <x-dl::field.date name="end_date" label="End date" :source="Src::of('tm2mst.emp_contract.end_date')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="salary_type" label="Salary type" required :options="array_map('t', Records::SALARY_TYPES)" value="PER_MONTH" :placeholder="null" :source="Src::of('tm2mst.emp_contract.salary_type')" />
                <x-dl::field.decimal name="salary_amount" label="Salary amount" required value="0" :source="Src::of('tm2mst.emp_contract.salary_amount')" />
                <x-dl::field.date name="probation_end_date" label="Probation until" :source="Src::of('tm2mst.emp_contract.probation_end_date')" />
            </x-dl::form.row>
            <x-dl::field.checkbox name="is_probation" text="This contract starts with a probation period" :source="Src::of('tm2mst.emp_contract.is_probation')" />
            <x-dl::alert tone="info" message="The active contract ends when this one is saved. Only one contract is active per employee." />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                <x-dl::button preset="save" />
            </x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>

    <x-dl::modal id="emp-family" title="Add Family" icon="fa-people-roof">
        <x-dl::action-form action="{{ route('v2.hrm.employee.family', $e->employee_id) }}">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="relation" label="Relation" required :options="array_map('t', Records::FAMILY_RELATIONS)" value="ANAK" :placeholder="null" :source="Src::of('tm2mst.emp_family.relation')" />
                <x-dl::field.text name="full_name" label="Full name" required :source="Src::of('tm2mst.emp_family.full_name')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                <x-dl::field.date name="birth_date" label="Birth date" :source="Src::of('tm2mst.emp_family.birth_date')" />
                <x-dl::field.text name="nik" label="NIK" :case-toggle="false" :source="Src::of('tm2mst.emp_family.nik')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                <x-dl::button preset="save" />
            </x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>

    <x-dl::modal id="emp-deceased" title="Mark Deceased" icon="fa-heart-crack">
        <x-dl::action-form action="{{ route('v2.hrm.employee.deceased', $e->employee_id) }}">
            <x-dl::field.select name="family_id" label="Family member" required :options="$e->families->where('is_deceased', false)->mapWithKeys(fn ($f) => [$f->family_id => $f->full_name.' · '.t(Records::FAMILY_RELATIONS[$f->relation] ?? $f->relation)])->all()" :source="Src::of('tm2mst.emp_family.is_deceased')" />
            <x-dl::alert tone="info" message="The record stays; it is marked deceased as of today." />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                <x-dl::button preset="save" label="Mark Deceased" data-dl-confirm="{{ t('Mark this family member as deceased?') }}" />
            </x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>

    <x-dl::modal id="emp-education" title="Add Activity" icon="fa-graduation-cap" size="lg">
        <x-dl::action-form action="{{ route('v2.hrm.employee.education', $e->employee_id) }}">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="education_type" label="Type" required :options="array_map('t', Records::EDUCATION_TYPES)" value="PELATIHAN" :placeholder="null" :source="Src::of('tm2mst.emp_education.education_type')" />
                <x-dl::field.text name="activity_name" label="Activity" required :source="Src::of('tm2mst.emp_education.activity_name')" />
                <x-dl::field.text name="organizer" label="Organizer" :source="Src::of('tm2mst.emp_education.organizer')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.date name="activity_date" label="Date" required :value="now()->toDateString()" :source="Src::of('tm2mst.emp_education.activity_date')" />
                <x-dl::field.decimal name="cost" label="Cost" required value="0" :source="Src::of('tm2mst.emp_education.cost')" />
                <x-dl::field.select name="funded_by" label="Funded by" required :options="array_map('t', Records::FUNDED_BY)" value="PERUSAHAAN" :placeholder="null" :source="Src::of('tm2mst.emp_education.funded_by')" />
            </x-dl::form.row>
            <x-dl::field.checkbox name="is_bonding" text="Service bond (employee must stay after the training)" :source="Src::of('tm2mst.emp_education.is_bonding')" />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                <x-dl::button preset="save" />
            </x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>

    <x-dl::modal id="emp-discipline" title="Add Record" icon="fa-gavel">
        <x-dl::action-form action="{{ route('v2.hrm.employee.discipline', $e->employee_id) }}">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="type" label="Type" required :options="array_map('t', Records::DISCIPLINARY_TYPES)" value="TEGURAN_VERBAL" :placeholder="null" :source="Src::of('tm2mst.emp_disciplinary.type')" />
                <x-dl::field.date name="incident_date" label="Incident date" required :value="now()->toDateString()" :source="Src::of('tm2mst.emp_disciplinary.incident_date')" />
            </x-dl::form.row>
            <x-dl::field.text name="reason" label="Reason" required multiline :source="Src::of('tm2mst.emp_disciplinary.reason')" />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                <x-dl::button preset="save" />
            </x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>

    <x-dl::modal id="emp-document" title="Upload Document" icon="fa-file-arrow-up">
        <x-dl::action-form action="{{ route('v2.hrm.employee.document', $e->employee_id) }}" enctype="multipart/form-data">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="document_type" label="Type" required :options="array_map('t', Records::DOCUMENT_TYPES)" value="KTP" :placeholder="null" :source="Src::of('tm2mst.emp_document.document_type')" />
                <x-dl::field.text name="document_no" label="Document no." :case-toggle="false" :source="Src::of('tm2mst.emp_document.document_no')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                <x-dl::field.date name="expiry_date" label="Valid until" hint="Leave empty when the document has no expiry date (e-KTP is valid for life)." :source="Src::of('tm2mst.emp_document.expiry_date')" />
                <x-dl::field.upload name="file" label="File" required accept=".jpg,.jpeg,.png,.pdf" :source="Src::of('tm2mst.emp_document.file_path')" />
            </x-dl::form.row>
            <x-dl::alert tone="info" message="One document per type. Uploading the same type again replaces the old file. Documents uploaded by HR cannot be changed by the employee." />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                <x-dl::button preset="save" />
            </x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>

    @if ($isAdmin)
        <x-dl::modal id="emp-account-create" title="Create Login" icon="fa-user-plus">
            <x-dl::action-form action="{{ route('v2.hrm.employee.account', [$e->employee_id, 'create']) }}">
                <x-dl::alert tone="info" message="A login is created for this employee. The initial password is shown once on a printable card." />
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                    <x-dl::button preset="save" label="Create Login" />
                </x-dl::action-bar>
            </x-dl::action-form>
        </x-dl::modal>

        @if ($acct)
            <x-dl::modal id="emp-account-reset" title="Reset Password" icon="fa-key">
                <x-dl::action-form action="{{ route('v2.hrm.employee.account', [$e->employee_id, 'reset']) }}">
                    <x-dl::alert tone="warn" message="The current password stops working. A new password is made and shown once on a printable card." />
                    <x-dl::action-bar>
                        <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                        <x-dl::button preset="save" label="Reset Password" />
                    </x-dl::action-bar>
                </x-dl::action-form>
            </x-dl::modal>

            <x-dl::modal id="emp-account-toggle" :title="$acct->activate ? 'Deactivate Account' : 'Activate Account'" icon="fa-user-lock">
                <x-dl::action-form action="{{ route('v2.hrm.employee.account', [$e->employee_id, $acct->activate ? 'deactivate' : 'activate']) }}">
                    <x-dl::alert tone="info" :message="$acct->activate ? t('The employee can no longer log in. Your own account cannot be deactivated.') : t('The employee can log in again.')" />
                    <x-dl::action-bar>
                        <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                        <x-dl::button preset="save" :label="$acct->activate ? 'Deactivate Account' : 'Activate Account'" />
                    </x-dl::action-bar>
                </x-dl::action-form>
            </x-dl::modal>
        @endif
    @endif
@endunless

{{-- Kartu login pegawai baru: kredensial dititipkan flash session, tampil sekali. --}}
@if ($card = session('v2_login_card'))
    <x-dl::modal id="emp-login-card" :title="$card['title'] ?? 'Login Card'" icon="fa-id-card" :open="true">
        <x-dl::login-card :title="$card['title'] ?? 'Login card'" :name="raw($card['name'])" :code="$card['code']" :login="raw($card['login'])" :password="raw($card['password'])">
            <x-dl::button type="button" variant="secondary" label="Close" data-dl-close-modal />
        </x-dl::login-card>
    </x-dl::modal>
@endif
