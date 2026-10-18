{{-- REMARK
     Tujuan       : Tampilan objek x-mp::hrm.self-service (MockupParts Components Hrm SelfService): satu pegawai, tab data diri sampai Security.
     Sumber hulu  : App Support V2 Data SelfService::desk(); SOP dari tm2mnt.mnt_document (company, Approved).
     Input        : $me (nilai sudah jadi), $crumbs; $T menerjemahkan teks English.
     Output       : Layar Edit Profile / Self-Service.
     Dipakai oleh : Rute v2.profile.
     Status       : Aktif; sumber SOP pindah ke mnt_document 2026-10-18. --}}
@php
    use Devlib\Lang;
    $T = fn (string $s) => Lang::text($s);
    $m = $me;
    $s = $m['security'];
@endphp
<x-dl::side-screen.layout :sections="$m['tabs']" :active="$m['tab']" :groups="$groups()" :hints="$hints()" :crumbs="$crumbs">
    <x-slot:top>
        @if ($m['flash'])<x-dl::alert tone="success" :message="Lang::raw($m['flash'])" />@endif
        @if ($m['error'])<x-dl::alert tone="danger" :message="Lang::raw($m['error'])" />@endif
        @unless ($linked())<x-dl::alert tone="warn" message="This account is not linked to an employee record yet. Ask HR to link it to Master Employee; only the Security tab is available." />@endunless
    </x-slot:top>
@if ($linked())
    @php
        $p = $m['profile'];
        $lv = $m['leave'];
        $o = $lv['old'];
        $out = $m['outing'];
        $sw = $m['swap'];
        $doc = $m['documents'];
        $fam = $m['family'];
        $rq = $m['requests'];
    @endphp
    {{-- ===================== My Data ===================== --}}
    <x-slot:profile>
        <x-dl::card :title="Lang::raw($p['title'])" :description="Lang::raw($p['description'])" icon="fa-id-card">
        <x-dl::kpi-row>
            @foreach ($p['stats'] as [$label, $value, $tone, $sub])
                <x-dl::stat-box :label="$label" :value="$value" :tone="$tone" :sub="$sub" />
            @endforeach
        </x-dl::kpi-row>
        </x-dl::card>
        <x-dl::form.row :cols="2">
            <x-dl::card title="Main Profile" icon="fa-user">
                @foreach ($p['main'] as [$label, $value])
                    <x-dl::field.display :label="$label" :value="$value" :source="$src('tm2mst.emp_employee')" />
                @endforeach
                <x-dl::field.display label="Position" :value="$p['position']" :hint="$T('HR only')" :source="$src('tm2mst.emp_employee.position_id')" />
            </x-dl::card>
            <x-dl::card title="Address, BPJS & Contacts" icon="fa-location-dot">
                @foreach ($p['address'] as [$label, $value])
                    <x-dl::field.display :label="$label" :value="$value" :source="$src('tm2mst.emp_employee')" />
                @endforeach
            </x-dl::card>
        </x-dl::form.row>
        <x-dl::card title="Correction Request History" icon="fa-clock-rotate-left">
            <x-dl::table :source="$src('tm2mst.emp_correction_request')" :empty="$T('No requests yet.')"
                :columns="['field' => 'Field', 'old' => 'Current Value', 'new' => 'New Value', 'date' => 'Requested', 'status' => 'Status']" :rows="$p['corrections']" />
        </x-dl::card>
        <x-dl::side-screen.foot hint="Changes take effect after HR verification">
            <x-dl::button icon="fa-pen-to-square" label="Request Correction" data-dl-open="corr-modal" />
            <x-dl::button variant="secondary" icon="fa-building-columns" label="Change Payroll Bank" data-dl-open="bank-modal" />
            <x-dl::button variant="danger" icon="fa-person-walking-arrow-right" label="Resignation Form" data-dl-open="resign-modal" />
        </x-dl::side-screen.foot>
    </x-slot:profile>

    {{-- ===================== Job Description (read-only, milik posisi) ===================== --}}
    <x-slot:jobdesc>
        <x-dl::card title="Job Description" :description="Lang::raw($m['jobdesc']['position'])" icon="fa-clipboard-list">
            @if ($m['jobdesc']['text'])
                <p>{!! nl2br(e($m['jobdesc']['text'])) !!}</p>
            @else
                <x-dl::empty-state title="No job description for this position yet" :message="$T('Contact the HR Officer if needed.')" icon="fa-clipboard-list" />
            @endif
        </x-dl::card>
    </x-slot:jobdesc>

    {{-- ===================== SOP (read-only) ===================== --}}
    <x-slot:sop>
        <x-dl::card title="SOP That Apply to Me" :description="Lang::raw($m['sop']['count'])" icon="fa-book">
            <x-dl::table :source="$src('tm2mnt.mnt_document')" :empty="$T('No SOP documents apply to your department yet.')"
                :columns="['code' => 'Code', 'title' => 'Title', 'rev' => 'Rev', 'dept' => 'Department', 'since' => 'Effective']" :rows="$m['sop']['rows']" />
            @if ($m['sop']['docs'])
                <x-dl::action-bar align="start">
                    @foreach ($m['sop']['docs'] as $d)
                        <x-dl::button size="sm" variant="secondary" icon="fa-book-open" :label="Lang::raw($d['code'])" data-dl-open="sop-{{ $d['id'] }}" />
                    @endforeach
                </x-dl::action-bar>
            @endif
        </x-dl::card>
        @foreach ($m['sop']['docs'] as $d)
            <x-dl::modal id="sop-{{ $d['id'] }}" :title="Lang::raw($d['title'])" size="lg" icon="fa-book-open">
                <x-dl::field.display label="Document" :value="$d['line']" />
                @if ($d['purpose'])<x-dl::field.display label="Purpose" :value="$d['purpose']" />@endif
                <x-dl::divider label="Content" />
                <p>{!! nl2br(e($d['content'])) !!}</p>
                <x-dl::action-bar><x-dl::button variant="secondary" label="Close" data-dl-close-modal /></x-dl::action-bar>
            </x-dl::modal>
        @endforeach
    </x-slot:sop>

    {{-- ===================== Family & Status ===================== --}}
    <x-slot:family>
        <x-dl::card title="Registered Family Members" icon="fa-people-roof">
            <x-dl::table :source="$src('tm2mst.emp_family')" :empty="$T('No family data yet.')"
                :columns="['rel' => 'Relation', 'name' => 'Name', 'birth' => 'Date of Birth', 'status' => 'Status']" :rows="$fam['rows']" />
            <x-dl::action-bar>
                <x-dl::button icon="fa-baby" label="Report Birth" data-dl-open="fam-birth" />
                <x-dl::button variant="secondary" icon="fa-cross" label="Report Death" data-dl-open="fam-death" />
                <x-dl::button variant="secondary" icon="fa-ring" label="Spouse Status Change" data-dl-open="fam-spouse" />
            </x-dl::action-bar>
        </x-dl::card>
        <x-dl::form.row :cols="2">
            <x-dl::card title="Payroll Bank Account" icon="fa-building-columns">
                <x-dl::field.display label="Bank / Account" :value="$fam['bank']" :source="$src('tm2mst.emp_bank_account')" />
                <x-dl::action-bar><x-dl::button size="sm" variant="secondary" icon="fa-pen-to-square" label="Change Payroll Bank" data-dl-open="bank-modal" /></x-dl::action-bar>
            </x-dl::card>
            <x-dl::card title="Family Request History" icon="fa-clock-rotate-left">
                <x-dl::table :source="$src('tm2mst.emp_family_request')" :empty="$T('No requests yet.')"
                    :columns="['type' => 'Type', 'name' => 'Name', 'date' => 'Requested', 'status' => 'Status']" :rows="$fam['requests']" />
            </x-dl::card>
        </x-dl::form.row>
    </x-slot:family>

    {{-- ===================== Documents & Education ===================== --}}
    <x-slot:documents>
        <x-dl::form.row :cols="2">
            <x-dl::card title="Identity Documents" icon="fa-file-lines">
                <x-dl::table :source="$src('tm2mst.emp_document')" :columns="['doc' => 'Document', 'no' => 'Number / Status', 'valid' => 'Valid Until', 'lock' => 'Uploaded By']" :rows="$doc['rows']" />
                <x-dl::action-bar><x-dl::button icon="fa-upload" label="Upload Document" data-dl-open="doc-modal" /></x-dl::action-bar>
            </x-dl::card>
            <x-dl::card title="Education & Development History" icon="fa-graduation-cap">
                <x-dl::table :source="$src('tm2mst.emp_education')" :empty="$T('No history yet.')"
                    :columns="['act' => 'Activity', 'date' => 'Date', 'cost' => ['Cost', 'num' => true, 'money' => true], 'fund' => 'Funded By']" :rows="$doc['educations']" />
                <x-dl::action-bar><x-dl::button icon="fa-plus" label="+ Add Training" data-dl-open="edu-modal" /></x-dl::action-bar>
            </x-dl::card>
        </x-dl::form.row>
    </x-slot:documents>

    {{-- ===================== Leave / Sick / Permit (engine SOP 002/RP/TM/VII/2026) ===================== --}}
    <x-slot:leave>
        <x-dl::card :title="$lv['title']" icon="fa-calendar-days">
            <x-dl::kpi-row>
                <x-dl::stat-box label="Entitlement" :value="$lv['balance'][0]" tone="info" />
                <x-dl::stat-box label="Used" :value="$lv['balance'][1]" tone="warn" />
                <x-dl::stat-box label="Remaining" :value="$lv['balance'][2]" tone="success" />
            </x-dl::kpi-row>
        </x-dl::card>
        <x-dl::action-form action="{{ $lv['action'] }}" enctype="multipart/form-data" data-dl-guard>
            <x-dl::card title="Absence Request Form" icon="fa-paper-plane">
                <x-dl::field.select name="leave_type" label="Request Type" required :value="$o['leave_type']" :placeholder="null" :source="$src('tm2mst.emp_leave_request.leave_type')" :options="$lv['types']" />
                <x-dl::alert tone="info" message="Annual leave: request at least H+7 ahead, maximum 3 working days per request, Sundays do not count. Sick: report to your supervisor and HRD by 07:30 WIB on the day. Unpaid leave: no present no pay, maximum 3 consecutive days and 3 requests per month — beyond that it is recorded as Alpha." />
                <x-dl::form.row :cols="2">
                    <x-dl::field.date name="date_from" label="From" required :value="$o['date_from']" :source="$src('tm2mst.emp_leave_request.date_from')" />
                    <x-dl::field.date name="date_to" label="To" required :value="$o['date_to']" :source="$src('tm2mst.emp_leave_request.date_to')" />
                </x-dl::form.row>
                <x-dl::divider label="Annual leave" />
                <x-dl::field.upload name="att_surat_cuti" label="Leave Request Letter (required for annual leave)" accept="image/*,.pdf" :source="$src('tm2mst.emp_leave_request.attachment_surat_cuti')" />
                <x-dl::divider label="Sick" />
                <x-dl::form.row :cols="2">
                    <x-dl::field.time name="sakit_lapor_jam" label="Time Reported to HRD" :value="$o['sakit_lapor_jam']" :hint="$T('After 07:30 WIB is reviewed by HR.')" :source="$src('tm2mst.emp_leave_request.sakit_lapor_jam')" />
                    <x-dl::field.checkbox name="sakit_via_jkn" :text="$T('JKN member (a history screenshot is enough)')" label="JKN" :value="$o['sakit_via_jkn']" :source="$src('tm2mst.emp_leave_request.attachment_jkn_screenshot')" />
                </x-dl::form.row>
                <x-dl::form.row :cols="3">
                    <x-dl::field.upload name="att_jkn_screenshot" label="JKN App History Screenshot" accept="image/*,.pdf" />
                    <x-dl::field.upload name="att_surat_dokter" label="Doctor's Note" accept="image/*,.pdf" />
                    <x-dl::field.upload name="att_kwitansi_resep" label="Receipt / Prescription" accept="image/*,.pdf" />
                </x-dl::form.row>
                <x-dl::divider label="Unpaid leave" />
                <x-dl::form.row :cols="2">
                    <x-dl::field.select name="izin_sifat" label="Nature" :value="$o['izin_sifat']" :placeholder="null" :disabled="$lv['locked']" :options="$lv['natures']" :source="$src('tm2mst.emp_leave_request.izin_category')" />
                    <x-dl::field.select name="izin_category" label="Sudden Need Category" :value="$o['izin_category']" :placeholder="null" :disabled="$lv['locked']" :options="$lv['categories']" :source="$src('tm2mst.emp_leave_request.izin_category')" />
                </x-dl::form.row>
                <x-dl::form.row :cols="2">
                    <x-dl::field.select name="izin_relasi_pemohon" label="For" :value="$o['izin_relasi_pemohon']" :placeholder="null" :disabled="$lv['locked']" :options="$lv['relations']" :source="$src('tm2mst.emp_leave_request.izin_relasi_pemohon')" />
                    <x-dl::field.select name="izin_instansi_asal" label="Institution" :value="$o['izin_instansi_asal']" :placeholder="null" :disabled="$lv['locked']" :options="$lv['institutions']" :source="$src('tm2mst.emp_leave_request.izin_instansi_asal')" />
                </x-dl::form.row>
                <x-dl::divider label="Special leave" />
                <x-dl::field.select name="khusus_event_type" label="Special Event" :value="$o['khusus_event_type']" :placeholder="null" :options="$lv['events']" :source="$src('tm2mst.emp_leave_request.khusus_event_type')" />
                <x-dl::field.upload name="att_generic" label="Proof / Letter Attachment (sudden unpaid leave, special leave)" accept="image/*,.pdf" :source="$src('tm2mst.emp_leave_request.attachment_path')" />
                <x-dl::field.text name="reason" label="Notes / Reason" multiline :value="$o['reason']" :source="$src('tm2mst.emp_leave_request.reason')" />
                <x-dl::action-bar><x-dl::button icon="fa-paper-plane" label="Submit Request" type="submit" /></x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
        <x-dl::card title="My Requests" icon="fa-clock-rotate-left">
            <x-dl::table :source="$src('tm2mst.emp_leave_request')" :empty="$T('No requests yet.')"
                :columns="['type' => 'Type', 'dates' => 'Dates', 'days' => ['Days', 'num' => true], 'pay' => 'Pay Status', 'status' => 'Approval']" :rows="$lv['rows']" />
        </x-dl::card>
    </x-slot:leave>

    {{-- ===================== Out Permit ===================== --}}
    <x-slot:outing>
        <x-dl::form.row :cols="2">
            <x-dl::action-form action="{{ $out['action'] }}" data-dl-guard>
                <x-dl::card title="Out-of-Work-Area Permit Form" icon="fa-door-open">
                    <x-dl::alert tone="warn" message="Must be approved by your direct supervisor AND HRD before leaving the factory area." />
                    <x-dl::field.date name="permit_date" label="Date" required :value="$out['old']['permit_date']" :source="$src('tm2mst.emp_out_permit_request.permit_date')" />
                    <x-dl::form.row :cols="2">
                        <x-dl::field.time name="out_time" label="Time Out" required :value="$out['old']['out_time']" :source="$src('tm2mst.emp_out_permit_request.planned_out_time')" />
                        <x-dl::field.time name="return_time" label="Time Back" :value="$out['old']['return_time']" :source="$src('tm2mst.emp_out_permit_request.planned_return_time')" />
                    </x-dl::form.row>
                    <x-dl::field.text name="reason" label="Purpose" required multiline :value="$out['old']['reason']" :source="$src('tm2mst.emp_out_permit_request.reason')" />
                    <x-dl::action-bar><x-dl::button icon="fa-paper-plane" label="Submit Out Permit" type="submit" /></x-dl::action-bar>
                </x-dl::card>
            </x-dl::action-form>
            <x-dl::card title="Out Permit History" icon="fa-clock-rotate-left">
                <x-dl::table :source="$src('tm2mst.emp_out_permit_request')" :empty="$T('No requests yet.')"
                    :columns="['date' => 'Date', 'out' => 'Out', 'back' => 'Back', 'status' => 'Status']" :rows="$out['rows']" />
            </x-dl::card>
        </x-dl::form.row>
    </x-slot:outing>

    {{-- ===================== Shift Swap ===================== --}}
    <x-slot:swap>
        <x-dl::form.row :cols="2">
            <x-dl::card title="Shift Swap Form" icon="fa-arrows-rotate">
                <x-dl::alert tone="warn" message="Submit at most H-1. Must be approved by the swap partner, then PPIC." />
                {{-- Pilih rekan dulu (GET), jadwal rekan muncul; lalu kirim (POST). --}}
                <x-dl::action-form method="get" action="{{ $m['home'] }}">
                    <input type="hidden" name="tab" value="swap">
                    <x-dl::field.select name="colleague" label="Swap Partner" required :value="$sw['targetId'] ?: null" :options="$sw['colleagues']" placeholder="Choose a colleague..." :source="$src('tm2mst.emp_shift_swap_request.target_employee_id')" />
                    <x-dl::action-bar align="start"><x-dl::button size="sm" variant="secondary" icon="fa-magnifying-glass" label="Show Partner's Schedule" type="submit" /></x-dl::action-bar>
                </x-dl::action-form>
                <x-dl::action-form action="{{ $sw['action'] }}" data-dl-guard>
                    <input type="hidden" name="target_employee_id" value="{{ $sw['targetId'] ?: '' }}">
                    <x-dl::field.select name="requester_roster_id" label="My Shift to Swap" required :value="$sw['old']['requester_roster_id']" :options="$sw['mine']" placeholder="Choose my shift..." :source="$src('tm2mst.emp_shift_swap_request.requester_roster_id')" />
                    <x-dl::field.select name="target_roster_id" label="Partner's Shift (swap target)" required :value="$sw['old']['target_roster_id']" :disabled="! $sw['targetId']" :options="$sw['theirs']" :placeholder="$sw['theirsHint']" :source="$src('tm2mst.emp_shift_swap_request.target_roster_id')" />
                    <x-dl::field.text name="reason" label="Reason" multiline :value="$sw['old']['reason']" :source="$src('tm2mst.emp_shift_swap_request.reason')" />
                    <x-dl::action-bar><x-dl::button icon="fa-paper-plane" label="Submit Swap Request" type="submit" :disabled="! $sw['targetId']" /></x-dl::action-bar>
                </x-dl::action-form>
            </x-dl::card>
            <x-dl::card title="Shift Swap History" icon="fa-clock-rotate-left">
                <x-dl::table :source="$src('tm2mst.emp_shift_swap_request')" :empty="$T('No requests yet.')"
                    :columns="['mine' => 'My Date', 'with' => 'Swapped With', 'partner' => 'Partner', 'ppic' => 'PPIC']" :rows="$sw['rows']" />
            </x-dl::card>
        </x-dl::form.row>
    </x-slot:swap>

    {{-- ===================== My Schedule ===================== --}}
    <x-slot:schedule>
        <x-dl::card title="Official Work Schedule (published by PPIC / HR)" :description="$T('Confirmed rosters only')" icon="fa-calendar">
            <x-dl::table :source="$src('tm2mst.emp_shift_roster')" :empty="$T('No published schedule yet.')"
                :columns="['date' => 'Date', 'shift' => 'Shift', 'ot' => 'Overtime', 'station' => 'Station']" :rows="$m['schedule']" />
        </x-dl::card>
    </x-slot:schedule>

    {{-- ===================== ID Card ===================== --}}
    <x-slot:badge>
        <x-dl::form.row :cols="2">
            <x-dl::card title="Digital Employee ID Card" icon="fa-id-badge">
                <x-dl::avatar :name="$m['badge']['name']" size="lg" :sub="$m['badge']['position']" />
                <x-dl::field.display label="Company" :value="$m['badge']['company']" />
                <x-dl::field.display label="Employee Code" :value="$m['badge']['code']" :source="$src('tm2mst.emp_employee.employee_code')" />
                <x-dl::field.display label="Department" :value="$m['badge']['department']" />
            </x-dl::card>
            <x-dl::card title="Request ID Photo Update" icon="fa-camera">
                <x-dl::alert tone="warn" message="Format 4×6 cm (2:3 ratio), minimum 600×900 px, plain light background, face forward. The photo is reviewed by HRD before reprinting." />
                <x-dl::action-form action="{{ $m['badge']['action'] }}" enctype="multipart/form-data">
                    <x-dl::field.upload name="photo" label="New ID Photo" accept="image/*" required :source="$src('tm2mst.emp_badge_photo_request.photo_path')" />
                    <x-dl::action-bar><x-dl::button icon="fa-upload" label="Send Photo" type="submit" /></x-dl::action-bar>
                </x-dl::action-form>
            </x-dl::card>
        </x-dl::form.row>
    </x-slot:badge>
@endif

    {{-- ===================== Security: akun login + pertanyaan pemulihan ===================== --}}
    <x-slot:security>
        <x-dl::form.row :cols="2">
            <x-dl::action-form action="{{ $s['accountAction'] }}" data-dl-guard>
                <x-dl::card title="Account Profile" icon="fa-user-pen">
                    <x-dl::field.text name="name" label="Full name" required :value="$s['name']" :source="$src('tm2sys.sec_user.name')" />
                    <x-dl::field.text name="email" label="E-mail" type="email" required :value="$s['email']" :case-toggle="false" :hint="$T('Changing the e-mail asks for a new verification (Profile > Edit profile > E-mail).')" :source="$src('tm2sys.sec_user.email')" />
                    <x-dl::field.text name="phone" label="Phone no." :case-toggle="false" :value="$s['phone']" :source="$src('tm2sys.sec_user.phone')" />
                    <x-dl::field.display label="E-mail status" :value="$s['verified']" :source="$src('tm2sys.sec_user.email_verified')" />
                    <x-dl::action-bar>
                        <x-dl::button variant="secondary" icon="email" label="Manage e-mail" :href="$s['emailUrl']" />
                        <x-dl::button preset="save" type="submit" />
                    </x-dl::action-bar>
                </x-dl::card>
            </x-dl::action-form>
            <x-dl::action-form action="{{ $s['passwordAction'] }}">
                <x-dl::card title="Change Password" icon="fa-key">
                    <x-dl::field.password name="old_password" label="Old password" required autocomplete="current-password" :source="$src('tm2sys.sec_user.pswd')" />
                    <x-dl::field.password name="new_password" label="New password" required autocomplete="new-password" :hint="$T('Minimum 8 characters.')" />
                    <x-dl::field.password name="new_password_confirmation" label="Confirm new password" required autocomplete="new-password" />
                    <x-dl::action-bar><x-dl::button preset="save" label="Update Password" type="submit" /></x-dl::action-bar>
                </x-dl::card>
            </x-dl::action-form>
        </x-dl::form.row>
        <x-dl::action-form action="{{ $s['recoveryAction'] }}" data-dl-guard>
            <x-dl::card title="Account Recovery (security questions)" :description="$s['setup'] ? Lang::raw($s['setup']) : $T('Not set yet — needed to recover a forgotten password.')" icon="fa-shield-halved">
                <x-dl::form.row :cols="3">
                    <x-dl::field.date name="birth_date" label="Date of birth" required :value="$s['birthDate']" :source="$src('tm2sys.sec_user.birth_date')" />
                    <x-dl::field.text name="mother_name" label="Mother's maiden name" required :value="$s['motherName']" placeholder="Your mother's full name" :hint="$T('Stored hashed; type it again each time you save.')" :source="$src('tm2sys.sec_user.mother_name_hash')" />
                    <x-dl::field.text name="phone" label="Registered phone" required :case-toggle="false" :value="$s['recoveryPhone']" :source="$src('tm2sys.sec_user.phone')" />
                </x-dl::form.row>
                <x-dl::form.row :cols="2">
                    <x-dl::field.select name="question_id" label="Security question" required :value="$s['questionId']" :options="$s['questions']" placeholder="Choose a question..." :source="$src('tm2sys.sec_recovery_question')" />
                    <x-dl::field.text name="answer" label="Your answer" required :value="$s['answer']" :source="$src('tm2sys.sec_user.security_answer_hash')" />
                </x-dl::form.row>
                <x-dl::alert tone="info" message="A different date of birth or phone than the one on record is sent to HR as a correction request; it is not overwritten directly." />
                <x-dl::action-bar><x-dl::button preset="save" label="Save Recovery Data" type="submit" /></x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    </x-slot:security>
</x-dl::side-screen.layout>

@if ($linked())
    @php
        $rq = $m['requests'];
        $fam = $m['family'];
        $doc = $m['documents'];
    @endphp
    {{-- ══════════ Modals (pengajuan) ══════════ --}}
    <x-dl::modal id="corr-modal" title="Request Data Correction" size="md" icon="fa-pen-to-square">
        <x-dl::action-form action="{{ $rq['correctionAction'] }}">
            <x-dl::field.select name="field_name" label="Field" required :value="$rq['field']" placeholder="Choose the field..." :source="$src('tm2mst.emp_correction_request.field_name')" :options="$rq['fields']" />
            <x-dl::field.text name="new_value" label="New value" required :value="$rq['newValue']" :source="$src('tm2mst.emp_correction_request.new_value')" />
            <x-dl::field.text name="reason" label="Reason" multiline :value="$rq['reason']" :source="$src('tm2mst.emp_correction_request.reason')" />
            <x-dl::alert tone="info" message="Every correction goes through HR verification before it takes effect." />
            <x-dl::action-bar><x-dl::button variant="secondary" label="Close" data-dl-close-modal /><x-dl::button icon="fa-paper-plane" label="Submit Request" type="submit" /></x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>

    <x-dl::modal id="bank-modal" title="Change Payroll Bank Account" size="md" icon="fa-building-columns">
        <x-dl::action-form action="{{ $rq['correctionAction'] }}">
            <input type="hidden" name="field_name" value="{{ $rq['bankField'] }}">
            <x-dl::field.display label="Current" :value="$rq['bankCurrent']" />
            <x-dl::field.select name="bank_supplier_id" label="New bank" required :value="$rq['bankId']" :options="$rq['banks']" placeholder="Choose a bank..." :source="$src('tm2mst.sup_supplier')" />
            <x-dl::field.text name="bank_account_no" label="New account no." required :value="$rq['bankNo']" :case-toggle="false" :source="$src('tm2mst.emp_bank_account.account_no')" />
            <x-dl::field.text name="bank_holder" label="Account holder" required :value="$rq['bankHolder']" :source="$src('tm2mst.emp_bank_account.account_holder_name')" />
            <x-dl::field.text name="reason" label="Reason" multiline :value="$rq['reason']" />
            <x-dl::action-bar><x-dl::button variant="secondary" label="Close" data-dl-close-modal /><x-dl::button icon="fa-paper-plane" label="Submit Request" type="submit" /></x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>

    <x-dl::modal id="resign-modal" title="Resignation Request" size="md" icon="fa-person-walking-arrow-right" tone="danger">
        <x-dl::action-form action="{{ $rq['resignAction'] }}">
            <x-dl::alert tone="warn" message="Formal resignation request under your employment contract. Your supervisor and HR will contact you to arrange the handover." />
            <x-dl::field.date name="effective_date" label="Effective date" required :value="$rq['effectiveDate']" :source="$src('tm2mst.emp_resign_request.effective_date')" />
            <x-dl::field.text name="reason" label="Reason" required multiline :value="$rq['reason']" :source="$src('tm2mst.emp_resign_request.reason')" />
            <x-dl::action-bar><x-dl::button variant="secondary" label="Close" data-dl-close-modal /><x-dl::button variant="danger" icon="fa-paper-plane" label="Submit Request" type="submit" /></x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>

    <x-dl::modal id="fam-birth" title="Report a Newborn Child" size="md" icon="fa-baby">
        <x-dl::action-form action="{{ $fam['action'] }}" enctype="multipart/form-data">
            <input type="hidden" name="request_type" value="LAHIR"><input type="hidden" name="relation" value="ANAK">
            <x-dl::field.text name="full_name" label="Full name" required :source="$src('tm2mst.emp_family_request.full_name')" />
            <x-dl::field.date name="birth_date" label="Date of birth" :source="$src('tm2mst.emp_family_request.birth_date')" />
            <x-dl::field.upload name="attachment" label="Birth certificate" accept="image/*,.pdf" :source="$src('tm2mst.emp_family_request.attachment_path')" />
            <x-dl::action-bar><x-dl::button variant="secondary" label="Close" data-dl-close-modal /><x-dl::button icon="fa-paper-plane" label="Submit Request" type="submit" /></x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>

    <x-dl::modal id="fam-death" title="Report a Family Member's Death" size="md" icon="fa-cross">
        <x-dl::action-form action="{{ $fam['action'] }}" enctype="multipart/form-data">
            <input type="hidden" name="request_type" value="MENINGGAL">
            <x-dl::field.select name="family_id" label="Family member" required :options="$fam['alive']" placeholder="Choose..." :source="$src('tm2mst.emp_family.family_id')" />
            <x-dl::field.date name="deceased_date" label="Date of death" required :source="$src('tm2mst.emp_family_request.deceased_date')" />
            <x-dl::field.upload name="attachment" label="Death certificate" accept="image/*,.pdf" />
            <x-dl::action-bar><x-dl::button variant="secondary" label="Close" data-dl-close-modal /><x-dl::button icon="fa-paper-plane" label="Submit Request" type="submit" /></x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>

    <x-dl::modal id="fam-spouse" title="Spouse Status Change" size="md" icon="fa-ring">
        <x-dl::action-form action="{{ $fam['action'] }}" enctype="multipart/form-data">
            <input type="hidden" name="request_type" value="PASANGAN">
            <x-dl::field.select name="relation" label="Relation" required value="ISTRI" :placeholder="null" :options="['SUAMI' => $T('Husband'), 'ISTRI' => $T('Wife')]" :source="$src('tm2mst.emp_family_request.relation')" />
            <x-dl::field.text name="full_name" label="Full name" required />
            <x-dl::field.date name="birth_date" label="Date of birth" />
            <x-dl::field.upload name="attachment" label="Marriage book / certificate" accept="image/*,.pdf" />
            <x-dl::action-bar><x-dl::button variant="secondary" label="Close" data-dl-close-modal /><x-dl::button icon="fa-paper-plane" label="Submit Request" type="submit" /></x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>

    <x-dl::modal id="doc-modal" title="Upload Document" size="md" icon="fa-upload">
        <x-dl::action-form action="{{ $doc['action'] }}" enctype="multipart/form-data">
            <x-dl::field.select name="document_type" label="Document" required :value="$doc['type']" :options="$doc['types']" placeholder="Choose..." :hint="$T('A document uploaded and locked by HR cannot be replaced here.')" :source="$src('tm2mst.emp_document.document_type')" />
            <x-dl::field.text name="document_no" label="Document no." :case-toggle="false" :source="$src('tm2mst.emp_document.document_no')" />
            <x-dl::field.date name="expiry_date" label="Valid until (if any)" :source="$src('tm2mst.emp_document.expiry_date')" />
            <x-dl::field.upload name="file" label="File (JPG / PNG / PDF, max 5 MB)" required accept="image/*,.pdf" :source="$src('tm2mst.emp_document.file_path')" />
            <x-dl::action-bar><x-dl::button variant="secondary" label="Close" data-dl-close-modal /><x-dl::button icon="fa-upload" label="Save & Upload" type="submit" /></x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>

    <x-dl::modal id="edu-modal" title="Add Training History" size="md" icon="fa-graduation-cap">
        <x-dl::action-form action="{{ $doc['educationAction'] }}">
            <x-dl::field.text name="activity_name" label="Training / certification" required :source="$src('tm2mst.emp_education.activity_name')" />
            <x-dl::field.text name="organizer" label="Organizer" :source="$src('tm2mst.emp_education.organizer')" />
            <x-dl::form.row :cols="2">
                <x-dl::field.date name="activity_date" label="Date" required :source="$src('tm2mst.emp_education.activity_date')" />
                <x-dl::field.money name="cost" label="Cost" :value="0" :source="$src('tm2mst.emp_education.cost')" />
            </x-dl::form.row>
            <x-dl::field.select name="funded_by" label="Funded by" required value="PRIBADI" :placeholder="null" :options="$doc['funds']" :source="$src('tm2mst.emp_education.funded_by')" />
            <x-dl::action-bar><x-dl::button variant="secondary" label="Close" data-dl-close-modal /><x-dl::button preset="save" type="submit" /></x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::modal>
@endif
