{{--
 REMARK
 Tujuan       : Form Offering (menggantikan halaman v1 pages::recruitment.offer.list dan entry): penawaran kerja per lamaran dari Draft (Save, Submit for Approval) lewat approval berjenjang (Approve / Reject oleh approver yang ditugaskan), Send to Candidate, Candidate Accepted / Declined (input manual), Revise Offer; tabel langkah approval, putaran penawaran dan tab History. Gaji hanya terlihat dan bisa diubah oleh user berhak payroll atau approver penawaran itu.
 Sumber hulu  : tm2hrr.app_offer + app_offer_approval + rcr_offer_approval_matrix + app_application + cnd_candidate + vac_vacancy; aturan di App\Services\Recruitment\OfferService dan RecruitmentApprovalService.
 Input        : Query application (app_application.id_application); session v2_flash dan errors.
 Output       : POST v2.hrm.offer.save (action save, submit, approve, reject, send, accepted, declined, revise).
 Dipakai oleh : Dibuka dari grid hrm-rcr-offer (Open); penawaran ACCEPTED dilanjutkan di Hire Provisioning Form.
 Status       : Aktif (T7m HRM).
--}}
@php
    use App\Models\Hrr\AppApplication;
    use App\Services\Recruitment\OfferService as Offer;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'hrm-rcr-offer');
    $svc = app(Offer::class);
    $user = Auth::user();
    $a = AppApplication::with('candidate', 'vacancy')->find((int) request('application'));
    abort_unless($a, 404);
    $o = Offer::latest($a);
    abort_unless($o || $a->status === 'OFFERING', 404);
    $new = ! $o;
    $o ??= new \App\Models\Hrr\AppOffer(['employment_type' => 'TETAP', 'start_date' => now()->addDays(14)->toDateString(), 'status' => 'DRAFT']);
    $st = $o->status;
    $draft = $st === 'DRAFT';
    $editSalary = Offer::editSalary($user);
    $seeSalary = $new ? $editSalary : Offer::canSeeSalary($o, $user);
    $editable = $draft && $editSalary;
    $pending = $new ? null : Offer::pendingFor($o, $user);
    $steps = $new ? [] : Offer::steps($o);
    $rounds = Offer::rounds($a, $user);
    $state = $new ? 'new' : (Offer::STATUS_STATE[$st] ?? 'closed');
    $s = fn (string $col) => Src::of('tm2hrr.app_offer.'.$col);
    $val = fn (string $col) => old($col, $o->{$col});
    $c = $a->candidate;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="hrm-rcr-offer-form" :title="raw($c?->name ?? '—')" subtitle="Offering" panel="top" kind="document" :status="$state" :pipeline="false" :can-input="$editable"
    :action="route('v2.hrm.offer.save')" action-field="action" :back="$home" :hide="['print', 'email', 'hold', 'cancel', 'confirm', 'duplicate', 'new', 'delete', 'reject', 'approve']"
    :hidden="$new ? ['application' => $a->id_application] : ['application' => $a->id_application, 'id' => $o->id_offer]" :tabs="['general' => t('Offer')]" :log="$new ? [] : $svc->history($o)">
    <x-slot:special>
        @if ($editable && ! $new)
            <x-dl::button variant="primary" icon="fa-paper-plane" label="Submit for Approval" type="submit" name="action" value="submit" data-dl-confirm="{{ t('Submit this offer for approval?') }}" />
        @endif
        @if ($pending)
            <x-dl::button variant="success" icon="fa-check" label="Approve" type="submit" name="action" value="approve" formnovalidate data-dl-confirm="{{ t('Approve this offer?') }}" />
            <x-dl::button variant="danger" icon="fa-xmark" label="Reject" type="submit" name="action" value="reject" formnovalidate data-dl-confirm="{{ t('Reject this offer?') }}" />
        @endif
        @if ($st === 'APPROVED')
            <x-dl::button variant="primary" icon="fa-paper-plane" label="Send to Candidate" type="submit" name="action" value="send" formnovalidate />
        @endif
        @if ($st === 'SENT')
            <x-dl::button variant="success" icon="fa-check" label="Candidate Accepted" type="submit" name="action" value="accepted" formnovalidate data-dl-confirm="{{ t('Mark the candidate as accepting this offer?') }}" />
            <x-dl::button variant="danger" icon="fa-xmark" label="Candidate Declined" type="submit" name="action" value="declined" formnovalidate data-dl-confirm="{{ t('Mark the candidate as declining this offer?') }}" />
        @endif
        @if ($st === 'DECLINED' && $a->status === 'OFFERING')
            <x-dl::button variant="primary" icon="fa-rotate" label="Revise Offer" type="submit" name="action" value="revise" formnovalidate />
        @endif
        @if ($st === 'ACCEPTED')
            <x-dl::button variant="primary" icon="fa-user-plus" label="Provision to ERP" :href="route('v2.screen', 'hrm-rcr-provision-form').'?id='.$a->id_application" />
        @endif
    </x-slot:special>
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Candidate" :value="raw($c?->name ?? '—')" />
        <x-dl::strip-kv kind="grand" label="Vacancy" :value="raw($a->vacancy?->title ?? '—')" />
        <x-dl::strip-kv kind="grand" label="Status" :value="$new ? '—' : t(ucfirst(strtolower(str_replace('_', ' ', $st))))" />
        <x-dl::strip-kv kind="grand" label="Round" :value="Locale::number((float) max(1, count($rounds)), 0)" />
    </x-slot:strip>

    <x-slot:general>
        <x-dl::card title="Offer">
            @if ($new)
                <x-dl::hint note>No offer yet for this application. Enter the details and Save the draft; the offer is created only when you save.</x-dl::hint>
            @endif
            <x-dl::form.row :cols="3">
                @if ($seeSalary)
                    <x-dl::field.decimal name="proposed_salary" label="Proposed salary (IDR)" required unit="IDR" :value="$val('proposed_salary')" :source="$s('proposed_salary')" />
                @else
                    <x-dl::field.display label="Proposed salary (IDR)" value="—" :source="$s('proposed_salary')" />
                @endif
                <x-dl::field.select name="employment_type" label="Contract type" required :options="array_map('t', Offer::TYPES)" :value="$val('employment_type')" :placeholder="null" :source="$s('employment_type')" />
                <x-dl::field.date name="start_date" label="Start date" required :value="old('start_date', $o->start_date ? \Illuminate\Support\Carbon::parse($o->start_date)->toDateString() : null)" :source="$s('start_date')" />
            </x-dl::form.row>
            @if (! $seeSalary)
                <x-dl::hint note>The proposed salary is confidential: it is shown to users with payroll rights and to the approvers of this offer.</x-dl::hint>
            @endif
        </x-dl::card>
        @if ($steps)
            <x-dl::card title="Approval steps">
                <x-dl::table :columns="['seq' => 'Step', 'role' => 'Role', 'approver' => 'Approver', 'decision' => 'Decision', 'at' => 'Decided']" :rows="$steps" />
            </x-dl::card>
        @endif
        @if (count($rounds) > 1)
            <x-dl::card title="Offer rounds">
                <x-dl::table :columns="['round' => 'Round', 'status' => 'Status', 'type' => 'Contract type', 'start' => 'Start date', 'salary' => 'Proposed salary (IDR)']" :rows="$rounds" />
            </x-dl::card>
        @endif
    </x-slot:general>
</x-dl::form>
