{{-- Production > Complaint Review (QA) (user 2026-10-02: "mutu kualitas masuk ke QA") lewat App\Support\V2\Data\CrmComplaints.
     GRID komplain customer yang sudah Confirmed Sales dan diteruskan ke QA -> buka -> isi keputusan, akar masalah, tindakan
     perbaikan / pencegahan, nasib barang kembali -> Save (boleh dicicil) -> Confirm = jawaban final, terbaca Sales di dokumen
     komplainnya. Dokumen komplain sendiri hanya dibaca di sini (milik Sales). --}}
@php
    use App\Models\Prd\QcComplaintReview;
    use App\Models\Sdi\CrmComplaint;
    use App\Support\V2\Data\CrmComplaints;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'qc-complaint-review');
    $doc = request('id') ? CrmComplaints::reviewable((int) request('id')) : null;
    abort_if(request('id') && ! $doc, 404);
    $labels = fn (array $list) => array_map(fn ($l) => t($l), $list);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $doc)
    {{-- Daftar = objek Grid Transactional App\Support\V2\Grid\Prod\QcComplaintReviewListGrid (DocListGrid), tampil lewat view generik _dbgrid. --}}
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Prod\QcComplaintReviewListGrid]])
@else
    @php
        $rv = $doc->review ?? new QcComplaintReview;
        $open = ! $rv->stat_confirm;
        $lines = $doc->items->map(fn ($i) => ['product_name' => $i->product_name, 'qty_complaint' => (float) $i->qty_complaint, 'qty_return' => (float) $i->qty_return,
            'qty_unit' => $i->qty_unit, 'defect_note' => $i->defect_note])->values()->all();
    @endphp
    <x-dl::form id="qc-complaint-review-form" :title="'Complaint Review · '.raw($doc->complaint_no)" :subtitle="raw($doc->customer?->customer_name ?? '—').' · '.CrmComplaint::TYPES[$doc->complaint_type]"
        panel="top" kind="document" :status="$open ? 'draft' : 'confirmed'" :action="route('v2.qc.complaint-review.save', $doc->id_complaint)" action-field="action" :back="$home"
        :hide="['delete', 'new', 'duplicate', 'cancel', 'email', 'print']" :tabs="['review' => ['QA Review', 'fa-magnifying-glass-chart'], 'complaint' => ['Complaint', 'fa-triangle-exclamation']]"
        :created-by="$rv->created_by" :created-at="$rv->created_at?->toDateTimeString()" :updated-by="$rv->updated_by" :updated-at="$rv->updated_at?->toDateTimeString()">
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>@t('Complaint No.')</span><b>{{ $doc->complaint_no }}</b></div>
            <x-dl::field.display label="Date" format="date" :value="$doc->complaint_date?->toDateString()" :source="Src::of('tm2sdi.crm_complaint.complaint_date')" />
            <x-dl::field.display label="Customer" :value="$doc->customer?->customer_name ?? '—'" :source="Src::of('tm2mst.cust_customer')" />
            <x-dl::field.display label="Category" :value="t(CrmComplaint::CATEGORIES[$doc->category] ?? $doc->category)" :source="Src::of('tm2sdi.crm_complaint.category')" />
        </x-slot:strip>
        <x-slot:review>
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="verdict" label="Verdict" required :options="$labels(QcComplaintReview::VERDICTS)" :value="$rv->verdict" :source="Src::of('tm2prd.qc_complaint_review.verdict')" />
                <x-dl::field.select name="disposition" label="Returned Goods" :options="$labels(QcComplaintReview::DISPOSITIONS)" :value="$rv->disposition" :source="Src::of('tm2prd.qc_complaint_review.disposition')" />
            </x-dl::form.row>
            <x-dl::field.text name="root_cause" label="Root Cause" multiline required :value="$rv->root_cause" :source="Src::of('tm2prd.qc_complaint_review.root_cause')" />
            <x-dl::form.row :cols="2">
                <x-dl::field.text name="corrective_action" label="Corrective Action" multiline :value="$rv->corrective_action" :source="Src::of('tm2prd.qc_complaint_review.corrective_action')" />
                <x-dl::field.text name="preventive_action" label="Preventive Action" multiline :value="$rv->preventive_action" :source="Src::of('tm2prd.qc_complaint_review.preventive_action')" />
            </x-dl::form.row>
        </x-slot:review>
        <x-slot:complaint>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Contact" :value="$doc->contact_person ?: '—'" :source="Src::of('tm2sdi.crm_complaint.contact_person')" />
                <x-dl::field.display label="Order / Ref." :value="($doc->order?->co_no ?: $doc->ref_doc_no) ?: '—'" :source="Src::of('tm2sdi.crm_complaint.co_order_id', 'ref_doc_no')" />
                <x-dl::field.display label="Customer Asks For" :value="$doc->requested_action ? t(CrmComplaint::REQUESTS[$doc->requested_action]) : '—'" :source="Src::of('tm2sdi.crm_complaint.requested_action')" />
                <x-dl::field.display label="Confirmed By" :value="\App\Support\V2\Data\CrmActivities::people(collect([$doc->confirmed_by]))[$doc->confirmed_by] ?? $doc->confirmed_by" :source="Src::of('tm2sdi.crm_complaint.confirmed_by')" />
            </x-dl::form.row>
            <x-dl::field.display label="What the Customer Complains About" :value="$doc->description ?: '—'" :source="Src::of('tm2sdi.crm_complaint.description')" />
        </x-slot:complaint>
        <x-slot:detail>
            <x-dl::lines name="items" title="Details" :addable="false" :duplicable="false" :min-rows="0" :rows="$lines" readonly source="{{ Src::of('tm2sdi.crm_complaint_item') }}"
                :columns="[
                    ['key' => 'product_name', 'label' => 'Product', 'type' => 'text', 'width' => '320px'],
                    ['key' => 'qty_complaint', 'label' => 'Qty Complained', 'type' => 'number', 'sum' => true, 'width' => '130px'],
                    ['key' => 'qty_return', 'label' => 'Qty Returned', 'type' => 'number', 'sum' => true, 'width' => '130px'],
                    ['key' => 'qty_unit', 'label' => 'Unit', 'type' => 'text', 'width' => '90px'],
                    ['key' => 'defect_note', 'label' => 'Problem', 'type' => 'text', 'width' => '320px'],
                ]" />
        </x-slot:detail>
    </x-dl::form>
@endif
