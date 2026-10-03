{{-- Edit profile (full screen) v2 -- Profile > Edit profile > "Personal data, leave, documents, shifts" / "Account security".
     Port layar self-service v1 (pages/self-service/index.blade.php) ke objek DevLib: alur, aturan dan tabel sama,
     hanya kulit yang diganti (user 2026-10-03). Data pribadi read-only; perubahan = pengajuan + approval HR
     (App\Support\V2\Data\SelfService). Akun login dan pertanyaan pemulihan diubah langsung. --}}
@php
    use App\Models\Master\EmpCorrectionRequest;
    use App\Support\V2\Data\SelfService;
    use App\Support\V2\Src;
    use Devlib\Locale;
    use Illuminate\Support\Facades\Auth;

    $u = Auth::user();
    $e = SelfService::employee($u);
    $tab = SelfService::tab(request('tab'));
    $home = route('v2.profile');
    $go = fn (string $t) => $home.'?tab='.$t;
    $sec = SelfService::securityDefaults($u);
    $tabs = $e ? [
        'profile' => ['My Data', 'fa-user'], 'jobdesc' => ['Job Description', 'fa-clipboard-list'], 'sop' => ['SOP', 'fa-book'],
        'family' => ['Family & Status', 'fa-people-roof'], 'documents' => ['Documents & Education', 'fa-file-lines'],
        'leave' => ['Leave / Sick / Permit', 'fa-calendar-days'], 'outing' => ['Out Permit', 'fa-door-open'], 'swap' => ['Shift Swap', 'fa-arrows-rotate'],
        'schedule' => ['My Schedule', 'fa-calendar'], 'badge' => ['ID Card', 'fa-id-badge'], 'security' => ['Security', 'fa-shield-halved'],
    ] : ['security' => ['Security', 'fa-shield-halved']];
    if (! isset($tabs[$tab])) { $tab = 'security'; }
    if ($e) {
        $balance = SelfService::balance($e->employee_id);
        $fields = SelfService::correctableFields($e);
        $bank = SelfService::primaryBank($e->employee_id);
        $roster = SelfService::upcomingRoster($e->employee_id);
    }
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if ($e)
    <x-dl::card :title="raw($e->full_name.' · '.$e->employee_code)" :description="raw(($e->position?->position_name ?? '—').' · '.($e->position?->department?->department_name ?? '—'))" icon="fa-id-card">
        <div class="dl-grid__stats">
            <x-dl::stat-box label="Leave Left" :value="(string) $balance->remainingDays().' '.t('days')" tone="success" :sub="t('this year')" />
            <x-dl::stat-box label="Work Status" :value="$e->work_status === 'WORKING' ? t('Active') : (string) $e->work_status" tone="info" />
            <x-dl::stat-box label="Pending Requests" :value="(string) (SelfService::corrections($e->employee_id)->where('status', 'PENDING')->count() + SelfService::leaveRequests($e->employee_id)->where('status', 'PENDING')->count())" tone="warn" :sub="t('corrections + leave')" />
        </div>
    </x-dl::card>
@else
    <x-dl::alert tone="warn" message="This account is not linked to an employee record yet. Ask HR to link it to Master Employee; only the Security tab is available." />
@endif

<x-dl::tabs :tabs="$tabs" :active="$tab">
@if ($e)
    {{-- ===================== My Data ===================== --}}
    <x-slot:profile>
        <x-dl::alert tone="info" message="Data update SOP: the information below is read-only. If an entry is wrong, click Request Correction — the change takes effect after HR verifies it." />
        <x-dl::form.row :cols="2">
            <x-dl::card title="Main Profile" icon="fa-user">
                @foreach (['Nama Lengkap', 'Tanggal Lahir', 'NIK KTP', 'Agama', 'Telepon / WA'] as $k)
                    <x-dl::field.display :label="$fields[$k][0]" :value="$fields[$k][1] ?: '—'" :source="Src::of('tm2mst.emp_employee')" />
                @endforeach
                <x-dl::field.display label="Position" :value="$e->position?->position_name ?: '—'" :hint="t('HR only')" :source="Src::of('tm2mst.emp_employee.position_id')" />
            </x-dl::card>
            <x-dl::card title="Address, BPJS & Contacts" icon="fa-location-dot">
                @foreach (['Alamat KTP', 'Alamat Domisili', 'No. BPJS Kesehatan', 'No. BPJS Ketenagakerjaan', 'Kontak Darurat'] as $k)
                    <x-dl::field.display :label="$fields[$k][0]" :value="$fields[$k][1] ?: '—'" :source="Src::of('tm2mst.emp_employee')" />
                @endforeach
            </x-dl::card>
        </x-dl::form.row>
        <x-dl::action-bar>
            <x-dl::button icon="fa-pen-to-square" label="Request Correction" data-dl-open="corr-modal" />
            <x-dl::button variant="secondary" icon="fa-building-columns" label="Change Payroll Bank" data-dl-open="bank-modal" />
            <x-dl::button variant="danger" icon="fa-person-walking-arrow-right" label="Resignation Form" data-dl-open="resign-modal" />
        </x-dl::action-bar>
        <x-dl::card title="Correction Request History" icon="fa-clock-rotate-left">
            <x-dl::table :source="Src::of('tm2mst.emp_correction_request')" :empty="t('No requests yet.')"
                :columns="['field' => 'Field', 'old' => 'Current Value', 'new' => 'New Value', 'date' => 'Requested', 'status' => 'Status']"
                :rows="SelfService::corrections($e->employee_id)->map(fn ($c) => ['field' => t($fields[$c->field_name][0] ?? $c->field_name), 'old' => $c->old_value, 'new' => $c->new_value, 'date' => Locale::d($c->requested_at), 'status' => t((string) $c->status)])->all()" />
        </x-dl::card>
    </x-slot:profile>

    {{-- ===================== Job Description (read-only, milik posisi) ===================== --}}
    <x-slot:jobdesc>
        <x-dl::card title="Job Description" :description="raw($e->position?->position_name ?? '—')" icon="fa-clipboard-list">
            @if ($e->position?->job_description)
                <p>{!! nl2br(e($e->position->job_description)) !!}</p>
            @else
                <x-dl::empty-state title="No job description for this position yet" :message="t('Contact the HR Officer if needed.')" icon="fa-clipboard-list" />
            @endif
            <x-dl::alert tone="info" message="Only the HR Officer can change this description — it is read-only here." />
        </x-dl::card>
    </x-slot:jobdesc>

    {{-- ===================== SOP (read-only) ===================== --}}
    <x-slot:sop>
        @php $sops = SelfService::sopDocuments($e); @endphp
        <x-dl::card title="SOP That Apply to Me" :description="raw($sops->count().' '.t('documents'))" icon="fa-book">
            <x-dl::table :source="Src::of('tm2mst.sop_document')" :empty="t('No SOP documents apply to your department yet.')"
                :columns="['code' => 'Code', 'title' => 'Title', 'rev' => 'Rev', 'dept' => 'Department', 'since' => 'Effective']"
                :rows="$sops->map(fn ($d) => ['code' => $d->sop_code, 'title' => $d->title, 'rev' => $d->revision, 'dept' => $d->department_id ? $d->department?->department_name : t('All departments'), 'since' => Locale::d($d->effective_date) ?? '—'])->all()" />
            @if ($sops->isNotEmpty())
                <x-dl::action-bar align="start">
                    @foreach ($sops as $d)
                        <x-dl::button size="sm" variant="secondary" icon="fa-book-open" :label="raw($d->sop_code)" data-dl-open="sop-{{ $d->sop_id }}" />
                    @endforeach
                </x-dl::action-bar>
            @endif
        </x-dl::card>
        @foreach ($sops as $d)
            <x-dl::modal id="sop-{{ $d->sop_id }}" :title="raw($d->title)" size="lg" icon="fa-book-open">
                <x-dl::field.display label="Document" :value="$d->sop_code.' · Rev '.$d->revision.($d->effective_date ? ' · '.t('effective').' '.Locale::d($d->effective_date) : '')" />
                @if ($d->purpose)<x-dl::field.display label="Purpose" :value="$d->purpose" />@endif
                <x-dl::divider label="Content" />
                <p>{!! nl2br(e($d->content ?: '—')) !!}</p>
                <x-dl::action-bar><x-dl::button variant="secondary" label="Close" data-dl-close-modal /></x-dl::action-bar>
            </x-dl::modal>
        @endforeach
    </x-slot:sop>

    {{-- ===================== Family & Status ===================== --}}
    <x-slot:family>
        <x-dl::card title="Registered Family Members" icon="fa-people-roof">
            <x-dl::table :source="Src::of('tm2mst.emp_family')" :empty="t('No family data yet.')"
                :columns="['rel' => 'Relation', 'name' => 'Name', 'birth' => 'Date of Birth', 'status' => 'Status']"
                :rows="$e->families->map(fn ($f) => ['rel' => $f->relation, 'name' => $f->full_name, 'birth' => Locale::d($f->birth_date) ?? '—', 'status' => $f->is_deceased ? t('Deceased').' — '.Locale::d($f->deceased_date) : t('Dependant')])->all()" />
            <x-dl::alert tone="info" message="Deceased family members are not deleted from the history — to keep past allowance and claim records accurate." />
            <x-dl::action-bar>
                <x-dl::button icon="fa-baby" label="Report Birth" data-dl-open="fam-birth" />
                <x-dl::button variant="secondary" icon="fa-cross" label="Report Death" data-dl-open="fam-death" />
                <x-dl::button variant="secondary" icon="fa-ring" label="Spouse Status Change" data-dl-open="fam-spouse" />
            </x-dl::action-bar>
        </x-dl::card>
        <x-dl::form.row :cols="2">
            <x-dl::card title="Payroll Bank Account" icon="fa-building-columns">
                <x-dl::field.display label="Bank / Account" :value="$bank ? trim($bank->bank_name).' · '.$bank->account_no.' · a.n. '.$bank->account_holder_name : t('Cash')" :source="Src::of('tm2mst.emp_bank_account')" />
                <x-dl::action-bar><x-dl::button size="sm" variant="secondary" icon="fa-pen-to-square" label="Change Payroll Bank" data-dl-open="bank-modal" /></x-dl::action-bar>
            </x-dl::card>
            <x-dl::card title="Family Request History" icon="fa-clock-rotate-left">
                <x-dl::table :source="Src::of('tm2mst.emp_family_request')" :empty="t('No requests yet.')"
                    :columns="['type' => 'Type', 'name' => 'Name', 'date' => 'Requested', 'status' => 'Status']"
                    :rows="SelfService::familyRequests($e->employee_id)->map(fn ($f) => ['type' => t(['LAHIR' => 'Birth', 'MENINGGAL' => 'Death', 'PASANGAN' => 'Spouse'][$f->request_type] ?? $f->request_type), 'name' => $f->full_name, 'date' => Locale::d($f->requested_at), 'status' => t((string) $f->status)])->all()" />
            </x-dl::card>
        </x-dl::form.row>
    </x-slot:family>

    {{-- ===================== Documents & Education ===================== --}}
    <x-slot:documents>
        <x-dl::form.row :cols="2">
            <x-dl::card title="Identity Documents" icon="fa-file-lines">
                <x-dl::table :source="Src::of('tm2mst.emp_document')" :columns="['doc' => 'Document', 'no' => 'Number / Status', 'valid' => 'Valid Until', 'lock' => 'Uploaded By']"
                    :rows="collect(SelfService::DOC_TYPES)->map(function ($label, $type) use ($e) { $d = $e->documents->firstWhere('document_type', $type); return ['doc' => t($label), 'no' => $d ? ($d->document_no ?: t('Uploaded')) : t('No file yet'), 'valid' => $d?->expiry_date ? Locale::d($d->expiry_date) : '—', 'lock' => $d ? ($d->uploaded_by_role === 'HR' ? t('HR (locked)') : t('Me')) : '—']; })->values()->all()" />
                <x-dl::action-bar><x-dl::button icon="fa-upload" label="Upload Document" data-dl-open="doc-modal" /></x-dl::action-bar>
            </x-dl::card>
            <x-dl::card title="Education & Development History" icon="fa-graduation-cap">
                <x-dl::table :source="Src::of('tm2mst.emp_education')" :empty="t('No history yet.')"
                    :columns="['act' => 'Activity', 'date' => 'Date', 'cost' => ['Cost', 'num' => true, 'money' => true], 'fund' => 'Funded By']"
                    :rows="$e->educations->map(fn ($ed) => ['act' => $ed->activity_name, 'date' => Locale::d($ed->activity_date), 'cost' => (float) $ed->cost, 'fund' => t(['PRIBADI' => 'Personal', 'PERUSAHAAN' => 'Company', 'SHARING' => 'Shared'][$ed->funded_by] ?? $ed->funded_by)])->all()" />
                <x-dl::action-bar><x-dl::button icon="fa-plus" label="+ Add Training" data-dl-open="edu-modal" /></x-dl::action-bar>
            </x-dl::card>
        </x-dl::form.row>
    </x-slot:documents>

    {{-- ===================== Leave / Sick / Permit (engine SOP 002/RP/TM/VII/2026) ===================== --}}
    <x-slot:leave>
        @php $izinLocked = SelfService::izinLocked($e->employee_id); @endphp
        <x-dl::card :title="t('Annual Leave Balance').' — '.now()->year" icon="fa-calendar-days">
            <div class="dl-grid__stats">
                <x-dl::stat-box label="Entitlement" :value="(string) $balance->entitlement_days" tone="info" />
                <x-dl::stat-box label="Used" :value="(string) $balance->used_days" tone="warn" />
                <x-dl::stat-box label="Remaining" :value="(string) $balance->remainingDays()" tone="success" />
            </div>
        </x-dl::card>
        <x-dl::action-form action="{{ route('v2.profile.leave') }}" enctype="multipart/form-data" data-dl-guard>
            <x-dl::card title="Absence Request Form" icon="fa-paper-plane">
                <x-dl::field.select name="leave_type" label="Request Type" required :value="old('leave_type', 'CUTI')" :placeholder="null" :source="Src::of('tm2mst.emp_leave_request.leave_type')" :options="[
                    'CUTI' => t('Annual Leave (paid)'), 'SAKIT' => t('Sick'),
                    'IZIN' => t('Unpaid Leave').($izinLocked ? ' — '.t('locked, leave left').' '.$balance->remainingDays().' '.t('days') : ''),
                    'CUTI_KHUSUS' => t('Special Leave (wedding, bereavement, ...)'),
                ]" />
                <x-dl::alert tone="info" message="Annual leave: request at least H+7 ahead, maximum 3 working days per request, Sundays do not count. Sick: report to your supervisor and HRD by 07:30 WIB on the day. Unpaid leave: no present no pay, maximum 3 consecutive days and 3 requests per month — beyond that it is recorded as Alpha." />
                <x-dl::form.row :cols="2">
                    <x-dl::field.date name="date_from" label="From" required :value="old('date_from')" :source="Src::of('tm2mst.emp_leave_request.date_from')" />
                    <x-dl::field.date name="date_to" label="To" required :value="old('date_to')" :source="Src::of('tm2mst.emp_leave_request.date_to')" />
                </x-dl::form.row>
                <x-dl::divider label="Annual leave" />
                <x-dl::field.upload name="att_surat_cuti" label="Leave Request Letter (required for annual leave)" accept="image/*,.pdf" :source="Src::of('tm2mst.emp_leave_request.attachment_surat_cuti')" />
                <x-dl::divider label="Sick" />
                <x-dl::form.row :cols="2">
                    <x-dl::field.time name="sakit_lapor_jam" label="Time Reported to HRD" :value="old('sakit_lapor_jam')" :hint="t('After 07:30 WIB is reviewed by HR.')" :source="Src::of('tm2mst.emp_leave_request.sakit_lapor_jam')" />
                    <x-dl::field.checkbox name="sakit_via_jkn" :text="t('JKN member (a history screenshot is enough)')" label="JKN" :value="(bool) old('sakit_via_jkn')" :source="Src::of('tm2mst.emp_leave_request.attachment_jkn_screenshot')" />
                </x-dl::form.row>
                <x-dl::form.row :cols="3">
                    <x-dl::field.upload name="att_jkn_screenshot" label="JKN App History Screenshot" accept="image/*,.pdf" />
                    <x-dl::field.upload name="att_surat_dokter" label="Doctor's Note" accept="image/*,.pdf" />
                    <x-dl::field.upload name="att_kwitansi_resep" label="Receipt / Prescription" accept="image/*,.pdf" />
                </x-dl::form.row>
                <x-dl::divider label="Unpaid leave" />
                <x-dl::form.row :cols="2">
                    <x-dl::field.select name="izin_sifat" label="Nature" :value="old('izin_sifat', 'terjadwal')" :placeholder="null" :disabled="$izinLocked" :options="['terjadwal' => t('Planned (min. H-7)'), 'mendadak' => t('Sudden (emergency / misfortune)')]" :source="Src::of('tm2mst.emp_leave_request.izin_category')" />
                    <x-dl::field.select name="izin_category" label="Sudden Need Category" :value="old('izin_category', 'MUSIBAH')" :placeholder="null" :disabled="$izinLocked" :options="['MUSIBAH' => t('Family misfortune / accident / disaster'), 'INSTANSI_RESMI' => t('Official government institution matter'), 'KEPENGURUSAN_MUSIBAH' => t('Bereavement / misfortune arrangements')]" :source="Src::of('tm2mst.emp_leave_request.izin_category')" />
                </x-dl::form.row>
                <x-dl::form.row :cols="2">
                    <x-dl::field.select name="izin_relasi_pemohon" label="For" :value="old('izin_relasi_pemohon', 'DIRI_SENDIRI')" :placeholder="null" :disabled="$izinLocked" :options="['DIRI_SENDIRI' => t('Myself'), 'SUAMI_ISTRI' => t('Spouse'), 'ANAK_KANDUNG' => t('Own child'), 'ORANG_TUA_KANDUNG' => t('Own parent'), 'MERTUA' => t('Parent-in-law')]" :source="Src::of('tm2mst.emp_leave_request.izin_relasi_pemohon')" />
                    <x-dl::field.select name="izin_instansi_asal" label="Institution" :value="old('izin_instansi_asal', 'BPJS')" :placeholder="null" :disabled="$izinLocked" :options="['BPJS' => 'BPJS', 'RUMAH_SAKIT' => t('Hospital'), 'SEKOLAH' => t('School'), 'KEPOLISIAN' => t('Police'), 'INSTANSI_PEMERINTAH_LAINNYA' => t('Other government institution')]" :source="Src::of('tm2mst.emp_leave_request.izin_instansi_asal')" />
                </x-dl::form.row>
                <x-dl::divider label="Special leave" />
                <x-dl::field.select name="khusus_event_type" label="Special Event" :value="old('khusus_event_type', 'MENIKAH')" :placeholder="null" :options="['MENIKAH' => t('Employee wedding'), 'MENIKAHKAN_KHITAN_ANAK' => t('Child wedding / circumcision'), 'ISTRI_MELAHIRKAN' => t('Wife giving birth / miscarriage'), 'KELUARGA_MENINGGAL' => t('Death of immediate family'), 'IBADAH' => t('Religious pilgrimage')]" :source="Src::of('tm2mst.emp_leave_request.khusus_event_type')" />
                <x-dl::field.upload name="att_generic" label="Proof / Letter Attachment (sudden unpaid leave, special leave)" accept="image/*,.pdf" :source="Src::of('tm2mst.emp_leave_request.attachment_path')" />
                <x-dl::field.text name="reason" label="Notes / Reason" multiline :value="old('reason')" :source="Src::of('tm2mst.emp_leave_request.reason')" />
                <x-dl::action-bar><x-dl::button icon="fa-paper-plane" label="Submit Request" type="submit" /></x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
        <x-dl::card title="My Requests" icon="fa-clock-rotate-left">
            <x-dl::table :source="Src::of('tm2mst.emp_leave_request')" :empty="t('No requests yet.')"
                :columns="['type' => 'Type', 'dates' => 'Dates', 'days' => ['Days', 'num' => true], 'pay' => 'Pay Status', 'status' => 'Approval']"
                :rows="SelfService::leaveRequests($e->employee_id)->map(fn ($lr) => ['type' => t(['CUTI' => 'Annual leave', 'SAKIT' => 'Sick', 'IZIN' => 'Unpaid leave', 'CUTI_KHUSUS' => 'Special leave'][$lr->leave_type] ?? $lr->leave_type), 'dates' => Locale::d($lr->date_from).' – '.Locale::d($lr->date_to), 'days' => (int) $lr->total_days, 'pay' => $lr->is_paid ? t('Paid') : t('Unpaid'), 'status' => t((string) $lr->status)])->all()" />
        </x-dl::card>
    </x-slot:leave>

    {{-- ===================== Out Permit ===================== --}}
    <x-slot:outing>
        <x-dl::form.row :cols="2">
            <x-dl::action-form action="{{ route('v2.profile.outing') }}" data-dl-guard>
                <x-dl::card title="Out-of-Work-Area Permit Form" icon="fa-door-open">
                    <x-dl::alert tone="warn" message="Must be approved by your direct supervisor AND HRD before leaving the factory area." />
                    <x-dl::field.date name="permit_date" label="Date" required :value="old('permit_date')" :source="Src::of('tm2mst.emp_out_permit_request.permit_date')" />
                    <x-dl::form.row :cols="2">
                        <x-dl::field.time name="out_time" label="Time Out" required :value="old('out_time')" :source="Src::of('tm2mst.emp_out_permit_request.planned_out_time')" />
                        <x-dl::field.time name="return_time" label="Time Back" :value="old('return_time')" :source="Src::of('tm2mst.emp_out_permit_request.planned_return_time')" />
                    </x-dl::form.row>
                    <x-dl::field.text name="reason" label="Purpose" required multiline :value="old('reason')" :source="Src::of('tm2mst.emp_out_permit_request.reason')" />
                    <x-dl::action-bar><x-dl::button icon="fa-paper-plane" label="Submit Out Permit" type="submit" /></x-dl::action-bar>
                </x-dl::card>
            </x-dl::action-form>
            <x-dl::card title="Out Permit History" icon="fa-clock-rotate-left">
                <x-dl::table :source="Src::of('tm2mst.emp_out_permit_request')" :empty="t('No requests yet.')"
                    :columns="['date' => 'Date', 'out' => 'Out', 'back' => 'Back', 'status' => 'Status']"
                    :rows="SelfService::outPermits($e->employee_id)->map(fn ($op) => ['date' => Locale::d($op->permit_date), 'out' => substr((string) $op->planned_out_time, 0, 5), 'back' => $op->planned_return_time ? substr((string) $op->planned_return_time, 0, 5) : '—', 'status' => t((string) $op->status)])->all()" />
            </x-dl::card>
        </x-dl::form.row>
    </x-slot:outing>

    {{-- ===================== Shift Swap ===================== --}}
    <x-slot:swap>
        @php
            $colleagues = SelfService::swapColleagues($e);
            $targetId = (int) old('target_employee_id', request('colleague'));
            $targetRoster = $targetId ? SelfService::upcomingRoster($targetId) : collect();
        @endphp
        <x-dl::form.row :cols="2">
            <x-dl::card title="Shift Swap Form" icon="fa-arrows-rotate">
                <x-dl::alert tone="warn" message="Submit at most H-1. Must be approved by the swap partner, then PPIC." />
                {{-- Pilih rekan dulu (GET), jadwal rekan muncul; lalu kirim (POST). --}}
                <x-dl::action-form method="get" action="{{ $home }}">
                    <input type="hidden" name="tab" value="swap">
                    <x-dl::field.select name="colleague" label="Swap Partner" required :value="$targetId ?: null" :options="$colleagues->pluck('full_name', 'employee_id')->all()" placeholder="Choose a colleague..." :source="Src::of('tm2mst.emp_shift_swap_request.target_employee_id')" />
                    <x-dl::action-bar align="start"><x-dl::button size="sm" variant="secondary" icon="fa-magnifying-glass" label="Show Partner's Schedule" type="submit" /></x-dl::action-bar>
                </x-dl::action-form>
                <x-dl::action-form action="{{ route('v2.profile.swap') }}" data-dl-guard>
                    <input type="hidden" name="target_employee_id" value="{{ $targetId ?: '' }}">
                    <x-dl::field.select name="requester_roster_id" label="My Shift to Swap" required :value="old('requester_roster_id')" :options="$roster->mapWithKeys(fn ($r) => [$r->roster_id => SelfService::rosterLabel($r)])->all()" placeholder="Choose my shift..." :source="Src::of('tm2mst.emp_shift_swap_request.requester_roster_id')" />
                    <x-dl::field.select name="target_roster_id" label="Partner's Shift (swap target)" required :value="old('target_roster_id')" :disabled="! $targetId" :options="$targetRoster->mapWithKeys(fn ($r) => [$r->roster_id => SelfService::rosterLabel($r)])->all()" :placeholder="$targetId ? t('Choose the partner\'s shift...') : t('Choose a partner first')" :source="Src::of('tm2mst.emp_shift_swap_request.target_roster_id')" />
                    <x-dl::field.text name="reason" label="Reason" multiline :value="old('reason')" :source="Src::of('tm2mst.emp_shift_swap_request.reason')" />
                    <x-dl::action-bar><x-dl::button icon="fa-paper-plane" label="Submit Swap Request" type="submit" :disabled="! $targetId" /></x-dl::action-bar>
                </x-dl::action-form>
            </x-dl::card>
            <x-dl::card title="Shift Swap History" icon="fa-clock-rotate-left">
                <x-dl::table :source="Src::of('tm2mst.emp_shift_swap_request')" :empty="t('No requests yet.')"
                    :columns="['mine' => 'My Date', 'with' => 'Swapped With', 'partner' => 'Partner', 'ppic' => 'PPIC']"
                    :rows="SelfService::swapRequests($e->employee_id)->map(fn ($s) => ['mine' => Locale::d($s->requesterRoster?->roster_date) ?? '—', 'with' => $s->targetEmployee?->full_name, 'partner' => t((string) $s->partner_status), 'ppic' => t((string) $s->ppic_status)])->all()" />
            </x-dl::card>
        </x-dl::form.row>
    </x-slot:swap>

    {{-- ===================== My Schedule ===================== --}}
    <x-slot:schedule>
        <x-dl::card title="Official Work Schedule (published by PPIC / HR)" :description="t('Confirmed rosters only')" icon="fa-calendar">
            <x-dl::table :source="Src::of('tm2mst.emp_shift_roster')" :empty="t('No published schedule yet.')"
                :columns="['date' => 'Date', 'shift' => 'Shift', 'ot' => 'Overtime', 'station' => 'Station']"
                :rows="$roster->map(fn ($s) => ['date' => Locale::d($s->roster_date), 'shift' => $s->shift?->shift_name, 'ot' => $s->is_overtime ? t('Yes') : '—', 'station' => $s->machine?->machine_name ?? '—'])->all()" />
        </x-dl::card>
    </x-slot:schedule>

    {{-- ===================== ID Card ===================== --}}
    <x-slot:badge>
        <x-dl::form.row :cols="2">
            <x-dl::card title="Digital Employee ID Card" icon="fa-id-badge">
                <x-dl::avatar :name="$e->full_name" size="lg" :sub="$e->position?->position_name" />
                <x-dl::field.display label="Company" :value="\App\Support\Branding\Brand::name()" />
                <x-dl::field.display label="Employee Code" :value="$e->employee_code" :source="Src::of('tm2mst.emp_employee.employee_code')" />
                <x-dl::field.display label="Department" :value="$e->position?->department?->department_name ?: '—'" />
            </x-dl::card>
            <x-dl::card title="Request ID Photo Update" icon="fa-camera">
                <x-dl::alert tone="warn" message="Format 4×6 cm (2:3 ratio), minimum 600×900 px, plain light background, face forward. The photo is reviewed by HRD before reprinting." />
                <x-dl::action-form action="{{ route('v2.profile.badge') }}" enctype="multipart/form-data">
                    <x-dl::field.upload name="photo" label="New ID Photo" accept="image/*" required :source="Src::of('tm2mst.emp_badge_photo_request.photo_path')" />
                    <x-dl::action-bar><x-dl::button icon="fa-upload" label="Send Photo" type="submit" /></x-dl::action-bar>
                </x-dl::action-form>
            </x-dl::card>
        </x-dl::form.row>
    </x-slot:badge>
@endif

    {{-- ===================== Security: akun login + pertanyaan pemulihan ===================== --}}
    <x-slot:security>
        <x-dl::alert tone="info" message="Login account data (not HR data) — changed directly without HR approval." />
        <x-dl::form.row :cols="2">
            <x-dl::action-form action="{{ route('v2.profile.account') }}" data-dl-guard>
                <x-dl::card title="Account Profile" icon="fa-user-pen">
                    <x-dl::field.text name="name" label="Full name" required :value="old('name', $u->name)" :source="Src::of('tm2sys.sec_user.name')" />
                    <x-dl::field.text name="email" label="E-mail" type="email" required :value="old('email', $u->email)" :case-toggle="false" :hint="t('Changing the e-mail asks for a new verification (Profile > Edit profile > E-mail).')" :source="Src::of('tm2sys.sec_user.email')" />
                    <x-dl::field.text name="phone" label="Phone no." :case-toggle="false" :value="old('phone', $u->phone)" :source="Src::of('tm2sys.sec_user.phone')" />
                    <x-dl::field.display label="E-mail status" :value="$u->email_verified ? t('Verified') : t('Not verified')" :source="Src::of('tm2sys.sec_user.email_verified')" />
                    <x-dl::action-bar>
                        <x-dl::button variant="secondary" icon="email" label="Manage e-mail" :href="route('v2.account.email')" />
                        <x-dl::button preset="save" type="submit" />
                    </x-dl::action-bar>
                </x-dl::card>
            </x-dl::action-form>
            <x-dl::action-form action="{{ route('v2.profile.password') }}">
                <x-dl::card title="Change Password" icon="fa-key">
                    <x-dl::field.password name="old_password" label="Old password" required autocomplete="current-password" :source="Src::of('tm2sys.sec_user.pswd')" />
                    <x-dl::field.password name="new_password" label="New password" required autocomplete="new-password" :hint="t('Minimum 8 characters.')" />
                    <x-dl::field.password name="new_password_confirmation" label="Confirm new password" required autocomplete="new-password" />
                    <x-dl::action-bar><x-dl::button preset="save" label="Update Password" type="submit" /></x-dl::action-bar>
                </x-dl::card>
            </x-dl::action-form>
        </x-dl::form.row>
        <x-dl::action-form action="{{ route('v2.profile.security') }}" data-dl-guard>
            <x-dl::card title="Account Recovery (security questions)" :description="$sec['setup_at'] ? raw(t('Last set').' '.Locale::dt($sec['setup_at'])) : t('Not set yet — needed to recover a forgotten password.')" icon="fa-shield-halved">
                <x-dl::form.row :cols="3">
                    <x-dl::field.date name="birth_date" label="Date of birth" required :value="old('birth_date', $sec['birth_date'])" :source="Src::of('tm2sys.sec_user.birth_date')" />
                    <x-dl::field.text name="mother_name" label="Mother's maiden name" required :value="old('mother_name')" placeholder="Your mother's full name" :hint="t('Stored hashed; type it again each time you save.')" :source="Src::of('tm2sys.sec_user.mother_name_hash')" />
                    <x-dl::field.text name="phone" label="Registered phone" required :case-toggle="false" :value="old('phone', $sec['phone'])" :source="Src::of('tm2sys.sec_user.phone')" />
                </x-dl::form.row>
                <x-dl::form.row :cols="2">
                    <x-dl::field.select name="question_id" label="Security question" required :value="old('question_id', $sec['question_id'])" :options="$sec['questions']" placeholder="Choose a question..." :source="Src::of('tm2sys.sec_recovery_question')" />
                    <x-dl::field.text name="answer" label="Your answer" required :value="old('answer')" :source="Src::of('tm2sys.sec_user.security_answer_hash')" />
                </x-dl::form.row>
                <x-dl::alert tone="info" message="A different date of birth or phone than the one on record is sent to HR as a correction request; it is not overwritten directly." />
                <x-dl::action-bar><x-dl::button preset="save" label="Save Recovery Data" type="submit" /></x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    </x-slot:security>
</x-dl::tabs>

@if ($e)
    {{-- ══════════ Modals (pengajuan) ══════════ --}}
    <x-dl::modal id="corr-modal" title="Request Data Correction" size="md" icon="fa-pen-to-square">
        <x-dl::action-form action="{{ route('v2.profile.correction') }}">
            <x-dl::field.select name="field_name" label="Field" required :value="old('field_name')" placeholder="Choose the field..." :source="Src::of('tm2mst.emp_correction_request.field_name')"
                :options="collect($fields)->except(EmpCorrectionRequest::FIELD_BANK)->map(fn ($f) => t($f[0]).' — '.($f[1] ?: '—'))->all()" />
            <x-dl::field.text name="new_value" label="New value" required :value="old('new_value')" :source="Src::of('tm2mst.emp_correction_request.new_value')" />
            <x-dl::field.text name="reason" label="Reason" multiline :value="old('reason')" :source="Src::of('tm2mst.emp_correction_request.reason')" />
            <x-dl::alert tone="info" message="Every correction goes through HR verification before it takes effect." />
            <x-dl::action-bar><x-dl::button variant="secondary" label="Close" data-dl-close-modal /><x-dl::button icon="fa-paper-plane" label="Submit Request" type="submit" /></x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>

    <x-dl::modal id="bank-modal" title="Change Payroll Bank Account" size="md" icon="fa-building-columns">
        <x-dl::action-form action="{{ route('v2.profile.correction') }}">
            <input type="hidden" name="field_name" value="{{ EmpCorrectionRequest::FIELD_BANK }}">
            <x-dl::field.display label="Current" :value="$fields[EmpCorrectionRequest::FIELD_BANK][1]" />
            <x-dl::field.select name="bank_supplier_id" label="New bank" required :value="old('bank_supplier_id', $bank?->bank_supplier_id)" :options="SelfService::bankOptions()" placeholder="Choose a bank..." :source="Src::of('tm2mst.sup_supplier')" />
            <x-dl::field.text name="bank_account_no" label="New account no." required :value="old('bank_account_no', $bank?->account_no)" :case-toggle="false" :source="Src::of('tm2mst.emp_bank_account.account_no')" />
            <x-dl::field.text name="bank_holder" label="Account holder" required :value="old('bank_holder', $bank?->account_holder_name)" :source="Src::of('tm2mst.emp_bank_account.account_holder_name')" />
            <x-dl::field.text name="reason" label="Reason" multiline :value="old('reason')" />
            <x-dl::action-bar><x-dl::button variant="secondary" label="Close" data-dl-close-modal /><x-dl::button icon="fa-paper-plane" label="Submit Request" type="submit" /></x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>

    <x-dl::modal id="resign-modal" title="Resignation Request" size="md" icon="fa-person-walking-arrow-right" tone="danger">
        <x-dl::action-form action="{{ route('v2.profile.resign') }}">
            <x-dl::alert tone="warn" message="Formal resignation request under your employment contract. Your supervisor and HR will contact you to arrange the handover." />
            <x-dl::field.date name="effective_date" label="Effective date" required :value="old('effective_date')" :source="Src::of('tm2mst.emp_resign_request.effective_date')" />
            <x-dl::field.text name="reason" label="Reason" required multiline :value="old('reason')" :source="Src::of('tm2mst.emp_resign_request.reason')" />
            <x-dl::action-bar><x-dl::button variant="secondary" label="Close" data-dl-close-modal /><x-dl::button variant="danger" icon="fa-paper-plane" label="Submit Request" type="submit" /></x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>

    <x-dl::modal id="fam-birth" title="Report a Newborn Child" size="md" icon="fa-baby">
        <x-dl::action-form action="{{ route('v2.profile.family') }}" enctype="multipart/form-data">
            <input type="hidden" name="request_type" value="LAHIR"><input type="hidden" name="relation" value="ANAK">
            <x-dl::field.text name="full_name" label="Full name" required :source="Src::of('tm2mst.emp_family_request.full_name')" />
            <x-dl::field.date name="birth_date" label="Date of birth" :source="Src::of('tm2mst.emp_family_request.birth_date')" />
            <x-dl::field.upload name="attachment" label="Birth certificate" accept="image/*,.pdf" :source="Src::of('tm2mst.emp_family_request.attachment_path')" />
            <x-dl::action-bar><x-dl::button variant="secondary" label="Close" data-dl-close-modal /><x-dl::button icon="fa-paper-plane" label="Submit Request" type="submit" /></x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>

    <x-dl::modal id="fam-death" title="Report a Family Member's Death" size="md" icon="fa-cross">
        <x-dl::action-form action="{{ route('v2.profile.family') }}" enctype="multipart/form-data">
            <input type="hidden" name="request_type" value="MENINGGAL">
            <x-dl::field.select name="family_id" label="Family member" required :options="$e->families->where('is_deceased', false)->mapWithKeys(fn ($f) => [$f->family_id => $f->full_name.' ('.$f->relation.')'])->all()" placeholder="Choose..." :source="Src::of('tm2mst.emp_family.family_id')" />
            <x-dl::field.date name="deceased_date" label="Date of death" required :source="Src::of('tm2mst.emp_family_request.deceased_date')" />
            <x-dl::field.upload name="attachment" label="Death certificate" accept="image/*,.pdf" />
            <x-dl::action-bar><x-dl::button variant="secondary" label="Close" data-dl-close-modal /><x-dl::button icon="fa-paper-plane" label="Submit Request" type="submit" /></x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>

    <x-dl::modal id="fam-spouse" title="Spouse Status Change" size="md" icon="fa-ring">
        <x-dl::action-form action="{{ route('v2.profile.family') }}" enctype="multipart/form-data">
            <input type="hidden" name="request_type" value="PASANGAN">
            <x-dl::field.select name="relation" label="Relation" required value="ISTRI" :placeholder="null" :options="['SUAMI' => t('Husband'), 'ISTRI' => t('Wife')]" :source="Src::of('tm2mst.emp_family_request.relation')" />
            <x-dl::field.text name="full_name" label="Full name" required />
            <x-dl::field.date name="birth_date" label="Date of birth" />
            <x-dl::field.upload name="attachment" label="Marriage book / certificate" accept="image/*,.pdf" />
            <x-dl::action-bar><x-dl::button variant="secondary" label="Close" data-dl-close-modal /><x-dl::button icon="fa-paper-plane" label="Submit Request" type="submit" /></x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>

    <x-dl::modal id="doc-modal" title="Upload Document" size="md" icon="fa-upload">
        <x-dl::action-form action="{{ route('v2.profile.document') }}" enctype="multipart/form-data">
            <x-dl::field.select name="document_type" label="Document" required :value="old('document_type')" :options="array_map('t', SelfService::DOC_TYPES)" placeholder="Choose..." :hint="t('A document uploaded and locked by HR cannot be replaced here.')" :source="Src::of('tm2mst.emp_document.document_type')" />
            <x-dl::field.text name="document_no" label="Document no." :case-toggle="false" :source="Src::of('tm2mst.emp_document.document_no')" />
            <x-dl::field.date name="expiry_date" label="Valid until (if any)" :source="Src::of('tm2mst.emp_document.expiry_date')" />
            <x-dl::field.upload name="file" label="File (JPG / PNG / PDF, max 5 MB)" required accept="image/*,.pdf" :source="Src::of('tm2mst.emp_document.file_path')" />
            <x-dl::action-bar><x-dl::button variant="secondary" label="Close" data-dl-close-modal /><x-dl::button icon="fa-upload" label="Save & Upload" type="submit" /></x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>

    <x-dl::modal id="edu-modal" title="Add Training History" size="md" icon="fa-graduation-cap">
        <x-dl::action-form action="{{ route('v2.profile.education') }}">
            <x-dl::field.text name="activity_name" label="Training / certification" required :source="Src::of('tm2mst.emp_education.activity_name')" />
            <x-dl::field.text name="organizer" label="Organizer" :source="Src::of('tm2mst.emp_education.organizer')" />
            <x-dl::form.row :cols="2">
                <x-dl::field.date name="activity_date" label="Date" required :source="Src::of('tm2mst.emp_education.activity_date')" />
                <x-dl::field.money name="cost" label="Cost" :value="0" :source="Src::of('tm2mst.emp_education.cost')" />
            </x-dl::form.row>
            <x-dl::field.select name="funded_by" label="Funded by" required value="PRIBADI" :placeholder="null" :options="['PRIBADI' => t('Personal'), 'PERUSAHAAN' => t('Company'), 'SHARING' => t('Shared')]" :source="Src::of('tm2mst.emp_education.funded_by')" />
            <x-dl::action-bar><x-dl::button variant="secondary" label="Close" data-dl-close-modal /><x-dl::button preset="save" type="submit" /></x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>
@endif
