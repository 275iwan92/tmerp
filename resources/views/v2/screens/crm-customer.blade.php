{{-- CRM > Customer 360 (user 2026-10-02) lewat App\Support\V2\Data\Crm. Hanya membaca.
     GRID customer aktif + ringkasan hubungan (order terakhir, quotation / order terbuka, kontak terakhir, follow-up, komplain)
     -> klik 2x = halaman satu customer: angka kunci, grafik order 24 bulan, product yang sering dipesan, dan tab
     Activities / Quotations / Orders / Deliveries / Complaints / Contacts. Order & kiriman = sistem baru + arsip sistem lama. --}}
@php
    use App\Support\V2\Data\Crm;
    use App\Support\V2\Data\CrmComplaints;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'crm-customer');
    $c = request('id') ? Crm::find((int) request('id')) : null;
    abort_if(request('id') && ! $c, 404);
    $dueStats = [
        ['label' => 'All'],
        ['label' => 'Overdue', 'where' => ['bucket' => 'OVERDUE'], 'tone' => 'danger'],
        ['label' => 'Today', 'where' => ['bucket' => 'TODAY'], 'tone' => 'tangerine'],
        ['label' => 'Due soon', 'where' => ['bucket' => 'SOON'], 'tone' => 'yellow'],
    ];
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif

@if (! $c)
    <x-dl::grid id="crm-customer-list" mode="report" :rows="Crm::rows()" search="Search customer, code, group, industry..." :open-url="$home.'?id={id}'" row-tone="row_tone"
        source="{{ Src::of('tm2mst.cust_customer', 'tm2sdi.co_order', 'tm2sdi.co_order_legacy', 'tm2sdi.quo_quotation', 'tm2sdi.crm_activity', 'tm2sdi.crm_complaint') }}"
        :columns="[
            ['key' => 'name', 'label' => 'Customer', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'segment', 'label' => 'Segment', 'type' => 'badge', 'tones' => collect(Crm::SEGMENT_TONES)->mapWithKeys(fn ($tone, $label) => [t($label) => $tone])->all()],
            ['key' => 'group', 'label' => 'Group', 'visible' => false],
            ['key' => 'industry', 'label' => 'Industry', 'visible' => false],
            ['key' => 'last_order', 'label' => 'Last Order', 'type' => 'date'],
            ['key' => 'orders_12m', 'label' => 'Orders (12 months)', 'type' => 'number'],
            ['key' => 'open_quo', 'label' => 'Open Quotations', 'type' => 'number'],
            ['key' => 'open_co', 'label' => 'Open Orders', 'type' => 'number'],
            ['key' => 'last_contact', 'label' => 'Last Contact', 'type' => 'date'],
            ['key' => 'next_date', 'label' => 'Next Follow-up', 'type' => 'date'],
            ['key' => 'due', 'label' => 'Due', 'type' => 'badge', 'toneKey' => 'due_tone'],
            ['key' => 'complaints', 'label' => 'Open Complaints', 'type' => 'number'],
        ]"
        :stats="[...$dueStats, ['label' => 'Open complaint', 'where' => ['has_complaint' => true], 'tone' => 'accent']]"
        :tabs="[['label' => 'All'], ...collect(Crm::SEGMENT_TONES)->map(fn ($tone, $label) => ['label' => $label, 'where' => ['segment' => t($label)], 'tone' => $tone])->values()->all()]"
        :filters="['segment' => 'Segment', 'group' => 'Group', 'industry' => 'Industry']" />
@else
    @php
        $p = Crm::profile($c);
        $s = $p['summary'];
        $fu = $s['next_followup'];
        $actUrl = route('v2.screen', 'crm-activity');
        $cmpUrl = route('v2.screen', 'crm-complaint');
    @endphp
    <x-dl::card :title="$c->customer_name" :description="implode(' · ', array_filter([$c->customer_code, $c->parent_group, $c->industry_category, t($s['segment'])]))" icon="fa-building">
        <x-slot:actions>
            <x-dl::button size="sm" variant="secondary" icon="fa-phone-volume" label="+ Activity" :href="$actUrl.'?new=1&customer_id='.$c->id_customer" />
            <x-dl::button size="sm" variant="secondary" icon="fa-triangle-exclamation" label="+ Complaint" :href="$cmpUrl.'?new=1&customer_id='.$c->id_customer" />
            <x-dl::button size="sm" variant="secondary" icon="fa-building" label="Master Customer" :href="route('master.customer').'?open='.$c->id_customer" />
            <x-dl::button size="sm" variant="secondary" icon="back" label="Back" :href="$home" />
        </x-slot:actions>
        <x-dl::form.row :cols="5">
            <x-dl::field.display label="First Order" :value="Locale::d($s['first_order']) ?? '—'" :source="Src::of('tm2sdi.co_order_legacy.co_date', 'tm2sdi.co_order.co_date')" />
            <x-dl::field.display label="Last Order" :value="Locale::d($s['last_order']) ?? '—'" :source="Src::of('tm2sdi.co_order_legacy.co_date', 'tm2sdi.co_order.co_date')" />
            <x-dl::field.display label="Orders (all time)" :value="Locale::number($s['orders_total'])" :source="Src::of('tm2sdi.co_order_legacy', 'tm2sdi.co_order')" />
            @if ($p['price'])
                <x-dl::field.display label="Order Value (all time)" :value="Locale::money($s['lifetime'])" :source="Src::of('tm2sdi.co_order_legacy.grand_total', 'tm2sdi.co_order')" />
            @endif
            <x-dl::field.display label="Last Contact" :value="Locale::d($s['last_contact']) ?? '—'" :source="Src::of('tm2sdi.crm_activity.activity_date')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="5">
            <x-dl::field.display label="Open Quotations" :value="Locale::number($s['open_quo'])" :source="Src::of('tm2sdi.quo_quotation (PUBLISHED, not WIN / LOST)')" />
            <x-dl::field.display label="Open Orders" :value="Locale::number($s['open_co'])" :source="Src::of('tm2sdi.co_order (Draft / Submitted / Confirmed)')" />
            <x-dl::field.display label="Open Complaints" :value="Locale::number($s['open_complaints'])" :source="Src::of('tm2sdi.crm_complaint (Confirmed, not Closed)')" />
            <x-dl::field.display label="Open Follow-ups" :value="Locale::number($s['open_followups'])" :source="Src::of('tm2sdi.crm_activity (next_done = 0)')" />
            <x-dl::field.display label="Next Follow-up" :value="$fu ? Locale::d($fu['next_date']).' · '.$fu['due'].($fu['next'] ? ' · '.$fu['next'] : '') : '—'" :source="Src::of('tm2sdi.crm_activity.next_action_date')" />
        </x-dl::form.row>
    </x-dl::card>

    <x-dl::tabs :tabs="['overview' => ['Overview', 'fa-chart-column'], 'activities' => ['Activities', 'fa-phone-volume'], 'quotations' => ['Quotations', 'fa-file-signature'],
        'orders' => ['Orders', 'fa-bag-shopping'], 'deliveries' => ['Deliveries', 'fa-truck'], 'complaints' => ['Complaints', 'fa-triangle-exclamation'], 'contacts' => ['Contacts', 'fa-address-book']]">
        <x-slot:overview>
            <x-dl::form.row :cols="$p['chart']['value'] ? 2 : 1">
                <x-dl::chart type="bar" :title="t('Orders per month')" :subtitle="t('Last 24 months, new system + archive')" :labels="$p['chart']['labels']" height="220px" :legend="false"
                    :series="[['name' => t('Orders'), 'data' => $p['chart']['count'], 'tone' => 'accent']]" :source="Src::of('tm2sdi.co_order_legacy', 'tm2sdi.co_order')" />
                @if ($p['chart']['value'])
                    <x-dl::chart type="area" :title="t('Order value per month')" :subtitle="t('Last 24 months, archive of the old system')" :labels="$p['chart']['labels']" height="220px" format="money" :legend="false"
                        :series="[['name' => t('Order value'), 'data' => $p['chart']['value'], 'tone' => 'success']]" :source="Src::of('tm2sdi.co_order_legacy.grand_total')" />
                @endif
            </x-dl::form.row>
            <x-dl::card title="Most Ordered Products" description="What this customer buys most often: use it when talking about a repeat order." icon="fa-box">
                <x-dl::table :columns="['product' => t('Product'), 'code' => t('Code'), 'n' => [t('Times Ordered'), 'num' => true], 'qty' => [t('Total Qty'), 'num' => true], 'unit' => t('Unit'), 'last' => t('Last Ordered')]"
                    :rows="$p['products']" :empty="t('No order history yet.')" source="{{ Src::of('tm2sdi.co_order_legacy_detail', 'tm2sdi.co_order_item', 'tm2mst.prod_product') }}" />
            </x-dl::card>
            <x-dl::card title="Customer Profile" description="Read from Master Customer. Change it there." icon="fa-id-card">
                <x-dl::form.row :cols="4">
                    <x-dl::field.display label="Contact" :value="trim(($c->mr_ms ? $c->mr_ms.' ' : '').$c->contact_name) ?: '—'" :source="Src::of('tm2mst.cust_customer.contact_name')" />
                    <x-dl::field.display label="Phone" :value="$c->phone ?: '—'" :source="Src::of('tm2mst.cust_customer.phone')" />
                    <x-dl::field.display label="E-mail" :value="$c->email ?: '—'" :source="Src::of('tm2mst.cust_customer.email')" />
                    <x-dl::field.display label="Payment Term" :value="$c->paymentTerm?->term_label ?? $c->payment_term ?? '—'" :source="Src::of('tm2mst.cust_customer.payment_term')" />
                </x-dl::form.row>
                <x-dl::form.row :cols="2">
                    <x-dl::field.display label="Address" :value="$c->default_address ?: '—'" :source="Src::of('tm2mst.cust_customer.default_address')" />
                    <x-dl::field.display label="Main Product" :value="implode(' · ', array_filter([$c->primary_product, $c->secondary_product])) ?: '—'" :source="Src::of('tm2mst.cust_customer.primary_product')" />
                </x-dl::form.row>
                <x-dl::form.row :cols="2">
                    <x-dl::field.display label="Packing Instruction" :value="$c->packing_instruction ?: '—'" :source="Src::of('tm2mst.cust_customer.packing_instruction')" />
                    <x-dl::field.display label="Quality Notes" :value="$c->quality_notes ?: '—'" :source="Src::of('tm2mst.cust_customer.quality_notes')" />
                </x-dl::form.row>
            </x-dl::card>
        </x-slot:overview>
        <x-slot:activities>
            <x-dl::grid id="crm-customer-activities" mode="report" :rows="$p['activities']" search="Search notes, contact, next action..." :open-url="$actUrl.'?id={id}'" row-tone="row_tone" :per-page="25"
                source="{{ Src::of('tm2sdi.crm_activity') }}"
                :columns="[
                    ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                    ['key' => 'type', 'label' => 'Type'],
                    ['key' => 'topic', 'label' => 'Topic'],
                    ['key' => 'contact', 'label' => 'Contact'],
                    ['key' => 'result', 'label' => 'Result', 'type' => 'badge', 'toneKey' => 'result_tone'],
                    ['key' => 'notes', 'label' => 'Notes'],
                    ['key' => 'next_date', 'label' => 'Follow-up', 'type' => 'date'],
                    ['key' => 'next', 'label' => 'Next Action'],
                    ['key' => 'due', 'label' => 'Due', 'type' => 'badge', 'toneKey' => 'due_tone'],
                    ['key' => 'by', 'label' => 'By'],
                ]"
                :stats="$dueStats" :filters="['type' => 'Type', 'topic' => 'Topic', 'by' => 'By']" />
        </x-slot:activities>
        <x-slot:quotations>
            <x-dl::grid id="crm-customer-quotations" mode="report" :rows="$p['quotations']" search="Search quotation no...." :open-url="route('v2.screen', 'quotation').'?id={id}'" :per-page="25"
                source="{{ Src::of('tm2sdi.quo_quotation', 'tm2sdi.quo_quotation_item') }}"
                :columns="array_values(array_filter([
                    ['key' => 'no', 'label' => 'Quotation No.'],
                    ['key' => 'date', 'label' => 'Issued', 'type' => 'date'],
                    ['key' => 'valid', 'label' => 'Valid Until', 'type' => 'date'],
                    $p['price'] ? ['key' => 'total', 'label' => 'Total', 'type' => 'money'] : null,
                    ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['DRAFT' => 'neutral', 'PUBLISHED' => 'info', 'WON' => 'success', 'LOST' => 'danger', 'CANCELLED' => 'danger']],
                ]))" :filters="['status' => 'Status']" />
        </x-slot:quotations>
        <x-slot:orders>
            <x-dl::grid id="crm-customer-orders" mode="report" :rows="$p['orders']" row-key="no" search="Search order no., customer PO..." :per-page="25"
                source="{{ Src::of('tm2sdi.co_order', 'tm2sdi.co_order_legacy (archive of the old system)') }}"
                :columns="array_values(array_filter([
                    ['key' => 'no', 'label' => 'Order No.'],
                    ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                    ['key' => 'po', 'label' => 'Customer PO'],
                    $p['price'] ? ['key' => 'total', 'label' => 'Total', 'type' => 'money', 'sum' => true] : null,
                    ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['DRAFT' => 'neutral', 'SUBMITTED' => 'warn', 'CONFIRMED' => 'success', 'CLOSED' => 'info', 'CANCELLED' => 'danger', 'UNKNOWN' => 'neutral']],
                    ['key' => 'source', 'label' => 'Source'],
                ]))" :filters="['status' => 'Status', 'source' => 'Source']" />
        </x-slot:orders>
        <x-slot:deliveries>
            <x-dl::grid id="crm-customer-deliveries" mode="report" :rows="$p['deliveries']" row-key="no" search="Search delivery no...." :per-page="25"
                source="{{ Src::of('tm2sdi.do_delivery', 'tm2sdi.co_delivery_legacy (archive of the old system)') }}"
                :columns="[
                    ['key' => 'no', 'label' => 'Delivery No.'],
                    ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                    ['key' => 'ship', 'label' => 'Shipped', 'type' => 'date'],
                    ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['DRAFT' => 'neutral', 'CONFIRMED' => 'success', 'SHIPPED' => 'info', 'CANCELLED' => 'danger']],
                    ['key' => 'source', 'label' => 'Source'],
                ]" :filters="['status' => 'Status', 'source' => 'Source']" />
        </x-slot:deliveries>
        <x-slot:complaints>
            <x-dl::grid id="crm-customer-complaints" mode="report" :rows="$p['complaints']" search="Search complaint no., product..." :open-url="$cmpUrl.'?id={id}'" :per-page="25"
                source="{{ Src::of('tm2sdi.crm_complaint', 'tm2sdi.crm_complaint_item', 'tm2prd.qc_complaint_review') }}"
                :columns="[
                    ['key' => 'no', 'label' => 'Complaint No.'],
                    ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                    ['key' => 'type', 'label' => 'Type'],
                    ['key' => 'category', 'label' => 'Category'],
                    ['key' => 'qa', 'label' => 'QA', 'type' => 'badge', 'tones' => CrmComplaints::QA_TONES],
                    ['key' => 'resolution', 'label' => 'Resolution'],
                    ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => CrmComplaints::STATUS_TONES],
                ]" :filters="['status' => 'Status', 'category' => 'Category']" />
        </x-slot:complaints>
        <x-slot:contacts>
            <x-dl::card title="Contact Persons" description="Maintained in Master Customer." icon="fa-address-book">
                <x-dl::table :columns="['name' => t('Name'), 'position' => t('Position'), 'phone' => t('Phone'), 'email' => 'E-mail', 'note' => t('Notes')]" :rows="$p['contacts']"
                    :empty="t('No contact person yet. Add one in Master Customer.')" source="{{ Src::of('tm2mst.cust_customer_contact') }}" />
            </x-dl::card>
            <x-dl::card title="Branches / Delivery Addresses" icon="fa-location-dot">
                <x-dl::table :columns="['name' => t('Branch'), 'address' => t('Address'), 'phone' => t('Phone')]" :rows="$p['branches']" :empty="t('No branch yet.')"
                    source="{{ Src::of('tm2mst.cust_customer_branch') }}" />
            </x-dl::card>
        </x-slot:contacts>
    </x-dl::tabs>
@endif
