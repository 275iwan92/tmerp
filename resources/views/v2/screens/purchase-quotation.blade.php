{{-- Purchase Quotation v2 (user 2026-10-01) lewat App\Support\V2\Data\PurchaseQuotation. Officer tidak mengetik penawaran:
     GRID PQ -> + New Record = pilih PR acuan (opsional) + upload file dari email supplier (PDF / Excel / foto, boleh banyak) ->
     tiap file jadi PQ Draft yang sudah terbaca & dicocokkan (supplier, baris PR, Master Bahan) -> officer cek baris kuning ->
     Confirm (nomor PQ lahir) -> Bandingkan per PR: pilih pemenang per baris (default termurah) -> Buat PO (1 PO per supplier,
     lewat alokasi PR -> PR ikut ter-update). PQ Closed saat semua barisnya sudah jadi PO; PQ yang kalah dibiarkan. --}}
@php
    use App\Models\Pur\PqQuotation;
    use App\Services\Pur\PqFileReader;
    use App\Services\Pur\PqMatcher;
    use App\Support\V2\Data\PurchaseOrder;
    use App\Support\V2\Data\PurchaseQuotation;
    use App\Support\V2\Src;
    use Illuminate\Support\Facades\Storage;

    $home = route('v2.screen', 'purchase-quotation');
    $pq = request('id') ? PqQuotation::with('lines.material')->find((int) request('id')) : null;
    abort_if(request('id') && ! $pq, 404);
    $compare = request('compare') ? PurchaseQuotation::compare((int) request('compare')) : null;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if ($compare)
    {{-- Bandingkan: baris PR x supplier (PQ Confirmed), pilih pemenang per baris -> Buat PO. --}}
    <x-dl::action-form action="{{ route('v2.pq.po') }}">
        <x-dl::card :title="'Compare Quotations — '.raw($compare['pr']->doc_no)" description="Unit price in IDR (after the rate). Ticked by default = the cheapest with the same unit as the PR; you may change it (e.g. faster lead time) or Skip.">
            <x-dl::pick-matrix name="pick" unit="PO" :source="Src::of('tm2pur.pq_line', 'tm2pur.pq_quotation (CONFIRMED)', 'tm2pur.pr_line')"
                :columns="collect($compare['suppliers'])->mapWithKeys(fn ($s) => [$s['pq_id'] => ['label' => raw($s['name']), 'sub' => $s['no'].' · '.($s['term'] ?: t('terms').' —').' · '.t('until').' '.$s['valid']]])->all()"
                :rows="collect($compare['rows'])->map(fn ($r) => ['key' => $r['pr_line_id'], 'label' => $r['label'], 'sub' => $r['qty'], 'default' => $r['cheapest'], 'locked' => $r['done'] ? t('Already ordered') : null,
                    'cells' => collect($r['cells'])->map(fn ($c) => ['value' => $c['pq_line_id'], 'text' => \Devlib\Locale::number($c['price'], 0).' / '.$c['uom'],
                        'sub' => $c['lead'] ? t('lead').' '.$c['lead'].' '.t('days') : null, 'disabled' => $c['ordered'] ? t('Already ordered (PO)') : (! $c['same_uom'] ? t('Unit differs from the PR') : null)])->all()])->all()" />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="url()->previous() !== url()->current() ? url()->previous() : $home" />
                <x-dl::button preset="save" label="Create PO" :onclick="'return confirm('.json_encode(t('Create a Draft PO for the selected lines?')).')'" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@elseif (! $pq && ! request('new'))
    {{-- Daftar = objek Grid Transactional App\Support\V2\Grid\Pur\PurchaseQuotationGrid (DbGrid), tampil lewat view generik _dbgrid. --}}
    @include('v2.screens._dbgrid', ['screen' => (new \App\Support\V2\Grid\Pur\PurchaseQuotationGrid)->screen()])
    <x-dl::action-form method="get" action="{{ $home }}">
        <x-dl::card title="Compare quotations per PR" description="Choose a PR that has Confirmed PQs from several suppliers, then pick the winner per line.">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="compare" label="PR" required :options="PurchaseQuotation::prOptions()" :source="Src::of('tm2pur.pr_requisition (APPROVED / PARTIALLY_ORDERED)')" />
            </x-dl::form.row>
            <x-dl::action-bar><x-dl::button variant="secondary" icon="fa-scale-balanced" label="Compare" type="submit" /></x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@elseif (! $pq)
    <x-dl::action-form action="{{ route('v2.pq.upload') }}" enctype="multipart/form-data">
        <x-dl::card title="+ New Record — Purchase Quotation" description="Upload the quotation file exactly as received from the supplier e-mail. One file = one Draft PQ; it is read and matched automatically, you only check it.">
            @unless (PqFileReader::aiEnabled())
                <x-dl::alert tone="warn" message="The AI reader is not active (ANTHROPIC_API_KEY is not set in .env): Excel / CSV are still read automatically; PDF and photos become an empty Draft PQ to fill in by hand." />
            @endunless
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="pr_id" label="Reference PR (optional)" placeholder="— no PR (price survey) —" :options="PurchaseQuotation::prOptions()" :value="request('pr')" :source="Src::of('tm2pur.pr_requisition (APPROVED / PARTIALLY_ORDERED)')" />
            </x-dl::form.row>
            <x-dl::field.upload name="files" label="Quotation files (PDF, Excel, CSV, JPG, PNG — max. 10 files, 20 MB each)" multiple required accept=".pdf,.xlsx,.xls,.csv,.jpg,.jpeg,.png,.webp" :source="Src::of('tm2pur.pq_quotation.source_path')" />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Upload & Read" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@else
    @php
        $draft = $pq->status === 'DRAFT';
        $ordered = PurchaseQuotation::orderedLineIds($pq);
        $open = $pq->lines->reject(fn ($l) => isset($ordered[$l->pq_line_id]));
        $prOpts = PurchaseQuotation::prLineOptions($pq);
        $money = fn ($v) => 'Rp '.\Devlib\Locale::number((float) $v, 0);
        $fileUrl = $pq->source_path ? Storage::disk('public')->url($pq->source_path) : null;
        $ext = strtolower(pathinfo((string) $pq->source_name, PATHINFO_EXTENSION));
        $lines = $pq->lines->map(fn ($l) => [
            'pq_line_id' => $l->pq_line_id, 'i_source_text' => $l->i_source_text ?: t('(entered by hand)'),
            'match' => $l->c_match_pct === null ? '—' : ($l->c_match_pct >= PqMatcher::SURE ? '✔ ' : '⚠ ').$l->c_match_pct.'%',
            'pr_line_id' => $l->pr_line_id, 'item_id' => $l->item_id, 'item_id_label' => $l->material ? $l->material->material_code.' · '.$l->material->material_name : '',
            'i_description' => $l->i_description, 'i_qty' => (float) $l->i_qty, 'i_uom' => $l->i_uom, 'i_price_fx' => (float) $l->i_price_fx,
            'i_lead_time_day' => $l->i_lead_time_day, 'po' => $ordered[$l->pq_line_id] ?? '—',
        ])->values()->all();
    @endphp
    @if ($draft && in_array($pq->extract_status, ['CHECK', 'MANUAL', 'FAILED'], true))
        <x-dl::alert tone="warn" :message="(PurchaseQuotation::READ_LABELS[$pq->extract_status] ?? '').': '.raw($pq->extract_note ?: t('lines marked ⚠ are not a sure match — choose the PR line / Material Master item, check qty, unit and price, then Confirm.'))" />
    @endif
    <x-dl::form id="pq-form" :title="'Purchase Quotation · '.raw($pq->doc_no ?: 'Draft #'.$pq->pq_id)" :subtitle="raw((PurchaseOrder::suppliers([$pq->i_supplier_id])[$pq->i_supplier_id] ?? ($pq->i_supplier_text ?: t('supplier not chosen yet'))).' · '.$pq->status)"
        panel="top" kind="document" :status="PurchaseQuotation::formStatus($pq)"
        :action="route('v2.pq.save', $pq->pq_id)" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hide="['duplicate', 'email', 'print']"
        :tabs="['general' => 'General', 'file' => 'Quotation File', 'notes' => 'Notes']"
        :created-by="$pq->created_by" :created-at="$pq->created_at?->toDateTimeString()" :updated-by="$pq->updated_by" :updated-at="$pq->updated_at?->toDateTimeString()">
        <x-slot:special>
            @if ($pq->i_pr_id && $pq->status !== 'DRAFT')<x-dl::button size="sm" variant="secondary" icon="fa-scale-balanced" label="Compare" :href="$home.'?compare='.$pq->i_pr_id" />@endif
            @if ($pq->status === 'CONFIRMED' && $open->isNotEmpty())
                @foreach ($open as $l)<input type="hidden" name="pq_line_ids[]" value="{{ $l->pq_line_id }}" form="pq-po-{{ $pq->pq_id }}">@endforeach
                <x-dl::button size="sm" variant="success" icon="fa-file-invoice-dollar" label="Create PO" type="submit" form="pq-po-{{ $pq->pq_id }}" :onclick="'return confirm('.json_encode(t('Turn every line of this PQ into a Draft PO for this supplier?').' ('.$open->count().')').')'" />
            @endif
            @unless ($draft)<x-dl::button size="sm" preset="print" label="Print" :href="\App\Support\V2\Print\PrintDocs::url('purchase-quotation', $pq->pq_id)" target="_blank" />@endunless
        </x-slot:special>
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>@t('PQ No.')</span><b>{{ $pq->doc_no ?: t('(on Confirm)') }}</b></div>
            <x-dl::field.select name="i_supplier_id" label="Supplier" required placeholder="— choose supplier —" :options="PurchaseOrder::suppliers()" :value="$pq->i_supplier_id" :source="Src::of('tm2mst.sup_supplier')" />
            <x-dl::field.date name="i_doc_date" label="Quotation Date" :value="$pq->i_doc_date?->toDateString()" :source="Src::of('tm2pur.pq_quotation.i_doc_date')" />
            <div class="dl-strip__kv dl-strip__grand"><span>@t('Total (IDR)')</span><b>{{ $money($pq->c_total_idr) }}</b></div>
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Supplier in the file" :value="$pq->i_supplier_text ?: '—'" :source="Src::of('tm2pur.pq_quotation.i_supplier_text')" />
                <x-dl::field.display label="Reference PR" :value="$pq->requisition?->doc_no ?? '— '.t('no PR').' —'" :source="Src::of('tm2pur.pq_quotation.i_pr_id')" />
                <x-dl::field.date name="i_quotation_valid_until" label="Valid Until" required :value="$pq->i_quotation_valid_until?->toDateString()" :source="Src::of('tm2pur.pq_quotation.i_quotation_valid_until')" />
                <x-dl::field.text name="i_payment_term" label="Payment Terms" :case-toggle="false" placeholder="e.g. 30 days" :value="$pq->i_payment_term" :source="Src::of('tm2pur.pq_quotation.i_payment_term')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="i_currency_id" label="Currency" :options="PurchaseQuotation::currencies()" :value="$pq->i_currency_id" :source="Src::of('tm2mst.fin_currency')" />
                <x-dl::field.decimal name="i_fx_rate" label="Rate to IDR" :value="(float) $pq->i_fx_rate" :source="Src::of('tm2pur.pq_quotation.i_fx_rate')" />
                <x-dl::field.number name="i_lead_time_day" label="Lead Time (days)" :value="$pq->i_lead_time_day" :source="Src::of('tm2pur.pq_quotation.i_lead_time_day')" />
                <x-dl::field.display label="Reading Result" :value="(isset(PurchaseQuotation::READ_LABELS[$pq->extract_status]) ? t(PurchaseQuotation::READ_LABELS[$pq->extract_status]) : '—').($pq->extract_note ? ' · '.$pq->extract_note : '')" :source="Src::of('tm2pur.pq_quotation.extract_status', 'extract_note')" />
            </x-dl::form.row>
        </x-slot:general>
        <x-slot:file>
            @if (! $fileUrl)
                <x-dl::empty-state title="No file" message="This PQ was created without a quotation file." />
            @else
                <x-dl::action-bar><x-dl::button size="sm" variant="secondary" icon="fa-download" :label="$pq->source_name" :href="$fileUrl" target="_blank" /></x-dl::action-bar>
                @if ($ext === 'pdf')
                    <iframe src="{{ $fileUrl }}" title="{{ $pq->source_name }}" class="mp-file-preview"></iframe>
                @elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true))
                    <img src="{{ $fileUrl }}" alt="{{ $pq->source_name }}" class="mp-file-preview">
                @endif
            @endif
        </x-slot:file>
        <x-slot:notes>
            <x-dl::field.text name="i_notes" label="Notes" multiline :value="$pq->i_notes" :source="Src::of('tm2pur.pq_quotation.i_notes')" />
            @if ($pq->cancel_reason)<x-dl::field.display label="Cancel reason" :value="$pq->cancel_reason" :source="Src::of('tm2pur.pq_quotation.cancel_reason')" />@endif
        </x-slot:notes>
        <x-slot:detail>
            <x-dl::lines name="lines" title="Details" key-field="pq_line_id" remove-field="remove" add-label="+ Manual row" :addable="$draft" :duplicable="false" :min-rows="0" :rows="$lines" :readonly="! $draft"
                source="{{ Src::of('tm2pur.pq_line') }}"
                :columns="array_values(array_filter([
                    ['key' => 'i_source_text', 'label' => 'Text in the file', 'type' => 'display', 'width' => '220px'],
                    ['key' => 'match', 'label' => 'Match', 'type' => 'display', 'width' => '70px'],
                    $prOpts ? ['key' => 'pr_line_id', 'label' => 'PR Line', 'type' => 'select', 'options' => $prOpts, 'placeholder' => t('— not from the PR —'), 'width' => '240px'] : null,
                    ['key' => 'item_id', 'label' => 'Material Master Item', 'type' => 'lookup', 'lookup' => 'material', 'width' => '240px'],
                    ['key' => 'i_qty', 'label' => 'Qty', 'type' => 'number', 'required' => true, 'width' => '90px'],
                    ['key' => 'i_uom', 'label' => 'Unit', 'type' => 'text', 'required' => true, 'width' => '80px'],
                    ['key' => 'i_price_fx', 'label' => 'Price', 'type' => 'money', 'required' => true, 'width' => '120px'],
                    ['key' => 'sub', 'label' => 'Subtotal', 'type' => 'money', 'formula' => 'i_qty * i_price_fx', 'sum' => true, 'width' => '130px'],
                    ['key' => 'i_description', 'label' => 'Description (for the PO)', 'type' => 'text', 'required' => true, 'more' => true],
                    ['key' => 'i_lead_time_day', 'label' => 'Lead time (days)', 'type' => 'number', 'more' => true],
                    ['key' => 'po', 'label' => 'Ordered (PO)', 'type' => 'display', 'more' => true],
                ]))" />
        </x-slot:detail>
    </x-dl::form>
    <x-dl::action-form id="pq-po-{{ $pq->pq_id }}" action="{{ route('v2.pq.po') }}" hidden></x-dl::action-form>
@endif
