{{-- CRM > Complaint & Return (user 2026-10-02: informasi retur / komplain ke Sales, mutu ke QA) lewat App\Support\V2\Data\CrmComplaints.
     GRID -> + New Record = pilih customer + jenis -> DRAFT: isi keluhan, product, qty -> Confirm (nomor lahir; kategori mutu muncul
     di Production > Complaint Review untuk QA) -> jawaban QA terbaca di tab QA Review -> Sales mengisi Resolution -> Close.
     Cancel wajib alasan. Qty Returned hanya informasi (belum menggerakkan stok). --}}
@php
    use App\Models\Prd\QcComplaintReview;
    use App\Models\Sdi\CrmActivity;
    use App\Models\Sdi\CrmComplaint;
    use App\Models\Master\ProdProduct;
    use App\Support\V2\Data\CrmActivities;
    use App\Support\V2\Data\CrmComplaints;
    use App\Support\V2\Print\PrintDocs;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'crm-complaint');
    $doc = request('id') ? CrmComplaints::find((int) request('id')) : null;
    abort_if(request('id') && ! $doc, 404);
    $labels = fn (array $list) => array_map(fn ($l) => t($l), $list);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $doc && ! request('new'))
    <x-dl::grid id="crm-complaint-list" :rows="CrmComplaints::rows()" search="Search complaint no., customer, product..." actions="worklist" :active-key="null"
        :new-url="$home.'?new=1'" :open-url="$home.'?id={id}'" source="{{ Src::of('tm2sdi.crm_complaint', 'tm2sdi.crm_complaint_item', 'tm2prd.qc_complaint_review', 'tm2mst.cust_customer') }}"
        :columns="[
            ['key' => 'no', 'label' => 'Complaint No. / Customer', 'type' => 'doc', 'party' => 'customer'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'ref', 'label' => 'Order / Ref.'],
            ['key' => 'request', 'label' => 'Customer Asks', 'visible' => false],
            ['key' => 'qa', 'label' => 'QA', 'type' => 'badge', 'tones' => CrmComplaints::QA_TONES],
            ['key' => 'verdict', 'label' => 'QA Verdict', 'visible' => false],
            ['key' => 'resolution', 'label' => 'Resolution'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => CrmComplaints::STATUS_TONES],
        ]"
        :detail="['key' => 'items', 'title' => 'Product', 'columns' => [
            ['key' => 'product', 'label' => 'Product', 'strong' => true],
            ['key' => 'qty', 'label' => 'Qty Complained', 'type' => 'number'],
            ['key' => 'returned', 'label' => 'Qty Returned', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'defect', 'label' => 'Problem'],
        ]]"
        :stats="[
            ['label' => 'All'],
            ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'],
            ['label' => 'Confirmed', 'where' => ['status' => 'CONFIRMED'], 'tone' => 'success'],
            ['label' => 'Waiting QA', 'where' => ['qa' => 'Waiting QA'], 'tone' => 'warn'],
            ['label' => 'Closed', 'where' => ['status' => 'CLOSED'], 'tone' => 'info'],
            ['label' => 'Cancelled', 'where' => ['status' => 'CANCELLED'], 'tone' => 'danger'],
        ]"
        :tabs="[['label' => 'All'], ...collect(CrmComplaint::TYPES)->map(fn ($l) => ['label' => $l, 'where' => ['type' => t($l)]])->values()->all()]"
        :filters="['customer' => 'Customer', 'category' => 'Category', 'status' => 'Status']" />
@elseif (! $doc)
    <form method="post" action="{{ route('v2.crm.complaint.create') }}">
        @csrf
        <x-dl::card title="+ New Record — Complaint & Return" description="Choose the customer and whether goods come back. The details are filled in on the next screen.">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="customer_id" label="Customer" required :options="CrmActivities::customerOptions()" :value="request('customer_id')" :source="Src::of('tm2mst.cust_customer')" />
                <x-dl::field.select name="complaint_type" label="Type" required :options="$labels(CrmComplaint::TYPES)" value="COMPLAINT" :source="Src::of('tm2sdi.crm_complaint.complaint_type')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Create" />
            </x-dl::action-bar>
        </x-dl::card>
    </form>
@else
    @php
        $status = $doc->status();
        $draft = $status === 'DRAFT';
        $qa = $doc->qaStage();
        $rv = $doc->review?->stat_confirm ? $doc->review : null;
        $names = ProdProduct::query()->whereKey($doc->items->pluck('product_id')->filter()->all())->pluck('product_name', 'id_product');
        $lines = $doc->items->map(fn ($i) => ['id_complaint_item' => $i->id_complaint_item, 'product_id' => $i->product_id, 'product_id_label' => $names[$i->product_id] ?? '',
            'product_name' => $i->product_name, 'qty_complaint' => (float) $i->qty_complaint, 'qty_return' => (float) $i->qty_return, 'qty_unit' => $i->qty_unit, 'defect_note' => $i->defect_note])->values()->all();
    @endphp
    <x-dl::form id="crm-complaint-form" :title="t(CrmComplaint::TYPES[$doc->complaint_type]).' · '.($doc->complaint_no ?: 'Draft #'.$doc->id_complaint)" :subtitle="$doc->customer?->customer_name ?? '—'"
        panel="top" kind="document" :status="CrmComplaints::formStatus($doc)" :stage="$status === 'CONFIRMED' && $qa !== 'NONE' ? t(CrmComplaints::QA_LABELS[$qa]) : null"
        :action="route('v2.crm.complaint.save', $doc->id_complaint)" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hide="['duplicate', 'email', 'print']"
        :tabs="['general' => 'General', 'qa' => ['QA Review', 'fa-magnifying-glass-chart'], 'resolution' => ['Resolution', 'fa-handshake']]" :log="CrmComplaints::log($doc)"
        :created-by="$doc->created_by" :created-at="$doc->created_at?->toDateTimeString()" :updated-by="$doc->updated_by" :updated-at="$doc->updated_at?->toDateTimeString()">
        <x-slot:special>
            <x-dl::button size="sm" variant="secondary" icon="fa-address-card" label="Customer 360" :href="route('v2.screen', 'crm-customer').'?id='.$doc->customer_id" />
            @unless ($draft)<x-dl::button size="sm" preset="print" label="Print" :href="PrintDocs::url('customer-complaint', $doc->id_complaint)" target="_blank" />@endunless
            @if ($status === 'CONFIRMED')<x-dl::button size="sm" variant="success" icon="fa-circle-check" label="Close" data-f-act="close" />@endif
        </x-slot:special>
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>@t('Complaint No.')</span><b>{{ $doc->complaint_no ?: t('(on Confirm)') }}</b></div>
            <x-dl::field.date name="complaint_date" label="Date" :value="$doc->complaint_date?->toDateString()" :readonly="! $draft" :source="Src::of('tm2sdi.crm_complaint.complaint_date')" />
            <x-dl::field.display label="Customer" :value="$doc->customer?->customer_name ?? '—'" :source="Src::of('tm2mst.cust_customer')" />
            <x-dl::field.select name="complaint_type" label="Type" :options="$labels(CrmComplaint::TYPES)" :value="$doc->complaint_type" :disabled="! $draft" :source="Src::of('tm2sdi.crm_complaint.complaint_type')" />
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="category" label="Category" required :options="$labels(CrmComplaint::CATEGORIES)" :value="$doc->category" :disabled="! $draft" :source="Src::of('tm2sdi.crm_complaint.category')" />
                <x-dl::field.select name="received_via" label="Received Via" :options="$labels(CrmActivity::TYPES)" :value="$doc->received_via" :disabled="! $draft" :source="Src::of('tm2sdi.crm_complaint.received_via')" />
                <x-dl::field.select name="contact_id" label="Contact" :options="CrmComplaints::contactOptions($doc)" :value="$doc->contact_id" :disabled="! $draft" :source="Src::of('tm2mst.cust_customer_contact')" />
                <x-dl::field.text name="contact_person" label="Other Contact (not in master)" :value="$doc->contact_id ? null : $doc->contact_person" :readonly="! $draft" :source="Src::of('tm2sdi.crm_complaint.contact_person')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="co_order_id" label="Customer Order" :options="CrmComplaints::orderOptions($doc)" :value="$doc->co_order_id" :disabled="! $draft" :source="Src::of('tm2sdi.crm_complaint.co_order_id', 'tm2sdi.co_order')" />
                <x-dl::field.text name="ref_doc_no" label="Reference Doc No." :value="$doc->ref_doc_no" :readonly="! $draft" placeholder="Old CO no. / delivery note no. / customer PO" :source="Src::of('tm2sdi.crm_complaint.ref_doc_no')" />
                <x-dl::field.select name="requested_action" label="Customer Asks For" :options="$labels(CrmComplaint::REQUESTS)" :value="$doc->requested_action" :disabled="! $draft" :source="Src::of('tm2sdi.crm_complaint.requested_action')" />
                <x-dl::field.checkbox name="needs_qa" :text="t('Send to QA for review')" :value="$doc->needs_qa" :disabled="! $draft" :source="Src::of('tm2sdi.crm_complaint.needs_qa')" />
            </x-dl::form.row>
            <x-dl::field.text name="description" label="What the Customer Complains About" multiline required :value="$doc->description" :readonly="! $draft" :source="Src::of('tm2sdi.crm_complaint.description')" />
            @if ($doc->stat_cancel)
                <x-dl::field.display label="Cancel Reason" :value="$doc->cancel_reason ?? '—'" :source="Src::of('tm2sdi.crm_complaint.cancel_reason')" />
            @endif
        </x-slot:general>
        <x-slot:qa>
            @if (! $doc->needs_qa)
                <x-dl::alert tone="info" :message="t('This complaint is not sent to QA. Tick Send to QA for review while it is still a Draft when quality has to be checked.')" />
            @elseif (! $rv)
                <x-dl::alert tone="warn" :message="$draft ? t('QA receives this complaint after Confirm.') : t('Waiting for QA. The answer appears here after QA confirms it in Production > Complaint Review.')" />
            @else
                <x-dl::form.row :cols="4">
                    <x-dl::field.display label="Verdict" :value="t(QcComplaintReview::VERDICTS[$rv->verdict] ?? '—')" :source="Src::of('tm2prd.qc_complaint_review.verdict')" />
                    <x-dl::field.display label="Returned Goods" :value="$rv->disposition ? t(QcComplaintReview::DISPOSITIONS[$rv->disposition]) : '—'" :source="Src::of('tm2prd.qc_complaint_review.disposition')" />
                    <x-dl::field.display label="Reviewed By" :value="CrmActivities::people(collect([$rv->reviewed_by]))[$rv->reviewed_by] ?? $rv->reviewed_by" :source="Src::of('tm2prd.qc_complaint_review.reviewed_by')" />
                    <x-dl::field.display label="Reviewed At" :value="Locale::stamp($rv->reviewed_at)" :source="Src::of('tm2prd.qc_complaint_review.reviewed_at')" />
                </x-dl::form.row>
                <x-dl::field.display label="Root Cause" :value="$rv->root_cause ?: '—'" :source="Src::of('tm2prd.qc_complaint_review.root_cause')" />
                <x-dl::field.display label="Corrective Action" :value="$rv->corrective_action ?: '—'" :source="Src::of('tm2prd.qc_complaint_review.corrective_action')" />
                <x-dl::field.display label="Preventive Action" :value="$rv->preventive_action ?: '—'" :source="Src::of('tm2prd.qc_complaint_review.preventive_action')" />
            @endif
        </x-slot:qa>
        <x-slot:resolution>
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="resolution" label="Resolution Given to the Customer" :options="$labels(CrmComplaint::RESOLUTIONS)" :value="$doc->resolution" :disabled="$status !== 'CONFIRMED'" :source="Src::of('tm2sdi.crm_complaint.resolution')" />
                <x-dl::field.text name="resolution_note" label="Resolution Note" :value="$doc->resolution_note" :readonly="$status !== 'CONFIRMED'" :source="Src::of('tm2sdi.crm_complaint.resolution_note')" />
            </x-dl::form.row>
            @if ($draft)
                <x-dl::alert tone="info" :message="t('The resolution is filled in after Confirm, when the answer for the customer is known. Close ends the complaint.')" />
            @elseif ($status === 'CONFIRMED' && $qa === 'WAITING')
                <x-dl::alert tone="warn" :message="t('Close is possible after QA has answered.')" />
            @endif
        </x-slot:resolution>
        <x-slot:detail>
            <x-dl::lines name="items" title="Details" key-field="id_complaint_item" remove-field="remove" add-label="+ Product" :addable="$draft" :duplicable="false" :min-rows="0" :rows="$lines" :readonly="! $draft"
                source="{{ Src::of('tm2sdi.crm_complaint_item', 'tm2mst.prod_product') }}"
                :columns="[
                    ['key' => 'product_id', 'label' => 'Master Product', 'type' => 'lookup', 'lookup' => 'product', 'width' => '280px'],
                    ['key' => 'product_name', 'label' => 'Product Name (if not in master)', 'type' => 'text', 'width' => '240px'],
                    ['key' => 'qty_complaint', 'label' => 'Qty Complained', 'type' => 'number', 'sum' => true, 'width' => '130px'],
                    ['key' => 'qty_return', 'label' => 'Qty Returned', 'type' => 'number', 'sum' => true, 'width' => '130px'],
                    ['key' => 'qty_unit', 'label' => 'Unit', 'type' => 'text', 'width' => '90px'],
                    ['key' => 'defect_note', 'label' => 'Problem', 'type' => 'text', 'width' => '300px'],
                ]" />
        </x-slot:detail>
    </x-dl::form>
@endif
