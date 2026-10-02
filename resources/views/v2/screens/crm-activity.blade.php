{{-- CRM > Activities & Follow-up (user 2026-10-02) lewat App\Support\V2\Data\CrmActivities.
     GRID semua kontak dengan customer; baris berwarna menurut jatuh tempo follow-up (pola CPDEL) -> + New Record = pilih customer
     -> form: jenis, topik, kontak, hasil, catatan, tindak lanjut -> Save. Follow-up selesai lewat Follow-up Done atau
     Log Result (mencatat aktivitas baru dari follow-up itu, follow-up asalnya ditutup). Ubah / hapus = pembuatnya. --}}
@php
    use App\Models\Master\CustCustomer;
    use App\Models\Sdi\CrmActivity;
    use App\Support\V2\Data\CrmActivities;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'crm-activity');
    $a = request('id') ? CrmActivities::find((int) request('id')) : null;
    abort_if(request('id') && ! $a, 404);
    $from = ! $a && request('from') ? CrmActivities::find((int) request('from')) : null;
    $customer = $a?->customer ?? ($from?->customer ?? (request('customer_id') ? CustCustomer::query()->find((int) request('customer_id')) : null));
    $labels = fn (array $list) => array_map(fn ($l) => t($l), $list);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $a && ! request('new') && ! $from)
    <x-dl::grid id="crm-activity-list" :rows="CrmActivities::rows()" search="Search customer, contact, notes, next action..." actions="worklist" :active-key="null"
        :new-url="$home.'?new=1'" :open-url="$home.'?id={id}'" row-tone="row_tone" source="{{ Src::of('tm2sdi.crm_activity', 'tm2mst.cust_customer', 'tm2sdi.quo_quotation') }}"
        :columns="[
            ['key' => 'type', 'label' => 'Activity / Customer', 'type' => 'doc', 'party' => 'customer'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'topic', 'label' => 'Topic'],
            ['key' => 'contact', 'label' => 'Contact'],
            ['key' => 'result', 'label' => 'Result', 'type' => 'badge', 'toneKey' => 'result_tone'],
            ['key' => 'quotation', 'label' => 'Quotation', 'visible' => false],
            ['key' => 'notes', 'label' => 'Notes', 'visible' => false],
            ['key' => 'next_date', 'label' => 'Follow-up', 'type' => 'date'],
            ['key' => 'next', 'label' => 'Next Action'],
            ['key' => 'due', 'label' => 'Due', 'type' => 'badge', 'toneKey' => 'due_tone'],
            ['key' => 'by', 'label' => 'By'],
        ]"
        :stats="[
            ['label' => 'All'],
            ['label' => 'Overdue', 'where' => ['bucket' => 'OVERDUE'], 'tone' => 'danger'],
            ['label' => 'Today', 'where' => ['bucket' => 'TODAY'], 'tone' => 'tangerine'],
            ['label' => 'Due soon', 'where' => ['bucket' => 'SOON'], 'tone' => 'yellow'],
            ['label' => 'Later', 'where' => ['bucket' => 'LATER'], 'tone' => 'accent'],
        ]"
        :tabs="[['label' => 'All'], ...collect(CrmActivity::TOPICS)->map(fn ($l) => ['label' => $l, 'where' => ['topic' => t($l)]])->values()->all()]"
        :filters="['type' => 'Type', 'topic' => 'Topic', 'result' => 'Result', 'by' => 'By']" />
@elseif (! $a && ! $customer)
    <x-dl::action-form method="get" action="{{ $home }}">
        <input type="hidden" name="new" value="1">
        <x-dl::card title="+ New Record — Activity" description="Choose the customer you contacted. The details are filled in on the next screen.">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="customer_id" label="Customer" required :options="CrmActivities::customerOptions()" :source="Src::of('tm2mst.cust_customer')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Continue" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@else
    @php
        $new = ! $a;
        $a ??= CrmActivities::blank($customer->id_customer, $from);
        $edit = CrmActivities::canEdit($a);
        $open = ! $new && $a->followUpOpen();
        $stage = $new ? null : ($a->next_action_date ? ($a->next_done ? t('Follow-up done') : t('Follow-up open')) : null);
        $contacts = CrmActivities::contactOptions($customer->id_customer);
    @endphp
    <x-dl::form id="crm-activity-form" :title="'Activity · '.raw($customer->customer_name)" :subtitle="$from ? 'Result of the follow-up: '.raw($from->next_action ?: '—') : raw($customer->customer_code)"
        panel="top" kind="master" :status="$new ? 'new' : 'active'" :stage="$stage" :can-input="$edit" :hide="['deactivate', 'activate']"
        :action="$new ? route('v2.crm.activity.store') : route('v2.crm.activity.save', $a->id_activity)" action-field="action" :back="$home" :new-url="$home.'?new=1'"
        :hidden="$new ? ['customer_id' => $customer->id_customer, 'from' => $from?->id_activity] : []"
        :created-by="$a->created_by" :created-at="$a->created_at?->toDateTimeString()" :updated-by="$a->updated_by" :updated-at="$a->updated_at?->toDateTimeString()">
        <x-slot:special>
            <x-dl::button size="sm" variant="secondary" icon="fa-address-card" label="Customer 360" :href="route('v2.screen', 'crm-customer').'?id='.$customer->id_customer" />
            @if ($open)
                <x-dl::button size="sm" variant="secondary" icon="fa-pen-to-square" label="Log Result" :href="$home.'?from='.$a->id_activity" />
                <x-dl::button size="sm" variant="success" icon="fa-check" label="Follow-up Done" data-f-act="done" />
            @elseif (! $new && $a->next_done)
                <x-dl::button size="sm" variant="secondary" icon="fa-rotate-left" label="Reopen Follow-up" data-f-act="reopen" />
            @endif
            @if (! $new && $edit)<x-dl::button size="sm" preset="delete" label="Delete" data-f-act="delete" />@endif
        </x-slot:special>
        <x-dl::form.row :cols="4">
            <x-dl::field.date name="activity_date" label="Date" required :value="$a->activity_date?->toDateString()" :source="Src::of('tm2sdi.crm_activity.activity_date')" />
            <x-dl::field.select name="activity_type" label="Type" required :options="$labels(CrmActivity::TYPES)" :value="$a->activity_type" :source="Src::of('tm2sdi.crm_activity.activity_type')" />
            <x-dl::field.select name="topic" label="Topic" required :options="$labels(CrmActivity::TOPICS)" :value="$a->topic" :source="Src::of('tm2sdi.crm_activity.topic')" />
            <x-dl::field.select name="result" label="Result" :options="$labels(CrmActivity::RESULTS)" :value="$a->result" :source="Src::of('tm2sdi.crm_activity.result')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="4">
            <x-dl::field.select name="contact_id" label="Contact" :options="$contacts" :value="$a->contact_id" :source="Src::of('tm2mst.cust_customer_contact')" />
            <x-dl::field.text name="contact_person" label="Other Contact (not in master)" :value="$a->contact_id ? null : $a->contact_person" :source="Src::of('tm2sdi.crm_activity.contact_person')" />
            <x-dl::field.select name="quotation_id" label="Quotation (optional)" :options="CrmActivities::quotationOptions($customer->id_customer)" :value="$a->quotation_id" :source="Src::of('tm2sdi.crm_activity.quotation_id', 'tm2sdi.quo_quotation')" />
        </x-dl::form.row>
        <x-dl::field.text name="notes" label="Notes" multiline :value="$a->notes" placeholder="What was discussed, what the customer said..." :source="Src::of('tm2sdi.crm_activity.notes')" />
        <x-dl::form.row :cols="2">
            <x-dl::field.date name="next_action_date" label="Follow-up Date" :value="$a->next_action_date?->toDateString()" :source="Src::of('tm2sdi.crm_activity.next_action_date')" />
            <x-dl::field.text name="next_action" label="Next Action" :value="$a->next_action" placeholder="What to do on the follow-up date" :source="Src::of('tm2sdi.crm_activity.next_action')" />
        </x-dl::form.row>
        @if (! $edit)
            <x-dl::alert tone="info" :message="t('Only the person who recorded this activity can change it. Use Log Result to record what happened next.')" />
        @endif
    </x-dl::form>
@endif
