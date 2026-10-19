{{--
 REMARK
 Tujuan       : Form Hire Provisioning (menggantikan dialog Confirm Provisioning halaman v1 pages::recruitment.hire-provision): lamaran dengan penawaran ACCEPTED menjadi pegawai baru (pilih Position, Provision to ERP dengan konfirmasi); data kandidat dan penawaran baca-saja tanpa nominal gaji; sesudah provisioning form baca-saja dengan tautan ke Master Employee dan tab History.
 Sumber hulu  : tm2hrr.app_application + cnd_candidate + vac_vacancy + rcr_requisition + app_offer, tm2mst.org_position + emp_employee; aturan di App\Services\Recruitment\HireProvisionService.
 Input        : Query id (app_application.id_application); session v2_flash dan errors.
 Output       : POST v2.hrm.provision.save (id, position_id); sesudahnya redirect ke Master Employee dengan kartu login (v2_login_card, sekali tampil).
 Dipakai oleh : Dibuka dari grid hrm-rcr-provision (Open).
 Status       : Aktif (T7j HRM).
--}}
@php
    use App\Models\Hrr\AppApplication;
    use App\Models\Master\EmpEmployee;
    use App\Services\Payroll\PayrollAccess;
    use App\Services\Recruitment\HireProvisionService as Hire;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'hrm-rcr-provision');
    $svc = app(Hire::class);
    $a = AppApplication::with(['candidate', 'vacancy.requisition', 'offers'])->find((int) request('id'));
    abort_unless($a && ($a->provisioned_employee_id || Hire::acceptedOffer($a)), 404);
    $done = (bool) $a->provisioned_employee_id;
    $offer = Hire::acceptedOffer($a);
    $emp = $done ? EmpEmployee::find($a->provisioned_employee_id) : null;
    $allowed = PayrollAccess::canProcess(Auth::user());
    $editable = ! $done && $allowed;
    $c = $a->candidate;
    $s = fn (string $tbl, string $col) => Src::of($tbl.'.'.$col);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="hrm-rcr-provision-form" :title="raw($c?->name ?? '—')" subtitle="Hire Provisioning" panel="top" kind="master" status="active" :stage="t($done ? 'Done' : 'Waiting')" :can-input="$editable"
    :hide="['deactivate', 'new', 'undo', 'save']" :action="route('v2.hrm.provision.save')" :back="$home" :hidden="['id' => $a->id_application]"
    :tabs="['general' => t('Provisioning')]" :log="$svc->history($a)">
    @if ($editable)
        <x-slot:special>
            <x-dl::button variant="primary" icon="fa-user-plus" label="Provision to ERP" type="submit" data-dl-confirm="{{ t('Provision this employee to ERP? A login account is created and this cannot be undone.') }}" />
        </x-slot:special>
    @endif
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Candidate" :value="raw($c?->name ?? '—')" />
        <x-dl::strip-kv kind="grand" label="Status" :value="t($done ? 'Done' : 'Waiting')" />
        <x-dl::strip-kv kind="grand" label="Employee" :value="raw($emp ? $emp->employee_code : '—')" />
        <x-dl::strip-kv kind="grand" label="Provisioned" :value="$a->provisioned_at ? Locale::date($a->provisioned_at) : '—'" />
    </x-slot:strip>

    <x-slot:general>
        <x-dl::card title="Candidate and offer (read-only)">
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Name" :value="raw($c?->name ?? '—')" :source="$s('tm2hrr.cnd_candidate', 'name')" />
                <x-dl::field.display label="National ID" :value="raw($c?->national_id ?: '—')" :source="$s('tm2hrr.cnd_candidate', 'national_id')" />
                <x-dl::field.display label="Phone" :value="raw($c?->phone ?: '—')" :source="$s('tm2hrr.cnd_candidate', 'phone')" />
                <x-dl::field.display label="Email" :value="raw($c?->email ?: '—')" :source="$s('tm2hrr.cnd_candidate', 'email')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Vacancy" :value="raw($a->vacancy?->title ?? '—')" :source="$s('tm2hrr.vac_vacancy', 'title')" />
                <x-dl::field.display label="Requisition position" :value="raw($a->vacancy?->requisition?->position_title ?? '—')" :source="$s('tm2hrr.rcr_requisition', 'position_title')" />
                <x-dl::field.display label="Employment type" :value="raw($offer?->employment_type ?? '—')" :source="$s('tm2hrr.app_offer', 'employment_type')" />
                <x-dl::field.display label="Start date" :value="$offer?->start_date ? Locale::date($offer->start_date) : '—'" :source="$s('tm2hrr.app_offer', 'start_date')" />
            </x-dl::form.row>
        </x-dl::card>

        @if ($done)
            <x-dl::card title="Employee">
                <x-dl::field.display label="Employee" :value="raw($emp ? $emp->employee_code.' '.$emp->full_name : '—')" :source="$s('tm2hrr.app_application', 'provisioned_employee_id')" />
                @if ($emp)
                    <x-dl::button variant="secondary" icon="fa-user" label="Open Master Employee" :href="route('v2.screen', 'hrm-employee-form').'?id='.$emp->employee_id" />
                @endif
            </x-dl::card>
        @elseif (! $allowed)
            <x-dl::alert tone="warning" :message="t('Only users with payroll rights can provision a new employee.')" />
        @else
            <x-dl::card title="Provision to ERP">
                <x-dl::hint note>Creates the employee, the contract (salary from the accepted offer, not shown here), a copy of the candidate documents and a login account. The requisition only holds a free-text title, so confirm the real position. This cannot be undone; the login card is shown once afterwards.</x-dl::hint>
                <x-dl::field.select name="position_id" label="Position" required :options="Hire::positions($a)" :value="old('position_id')" placeholder="— select position —" :source="Src::of('tm2mst.emp_employee.position_id')" />
            </x-dl::card>
        @endif
    </x-slot:general>
</x-dl::form>
