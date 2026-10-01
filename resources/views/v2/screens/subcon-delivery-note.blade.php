{{-- Subcontract Delivery Note v2 (backlog02 §19.1; user journey disetujui 2026-09-30) lewat App\Support\V2\Data\SubconDelivery.
     GRID surat jalan subkon (do_delivery do_type SUBCON) -> + New Record = pilih supplier subkon + proses -> DRAFT: rujukan PR,
     baris barang setengah jadi (baris CO asal + nama + qty) -> Confirm (nomor lahir, siap dicetak) -> Cancel wajib alasan.
     Tanpa harga. Barang kembali dari subkon dicatat di Procurement (Goods Receipt > From Subcon), bukan di sini. --}}
@php
    use App\Support\V2\Data\SubconDelivery;
    use App\Support\V2\Print\PrintDocs;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'subcon-delivery-note');
    $do = request('id') ? SubconDelivery::find((int) request('id')) : null;
    abort_if(request('id') && ! $do, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $do && ! request('new'))
    <x-dl::grid id="subcon-dn-list" :rows="SubconDelivery::rows()" search="Search note no., subcontractor, process, CO..." actions="worklist" :active-key="null"
        :new-url="$home.'?new=1'" :open-url="$home.'?id={id}'" source="{{ Src::of('tm2sdi.do_delivery', 'tm2sdi.do_line', 'tm2mst.sup_supplier') }}"
        :columns="[
            ['key' => 'no', 'label' => 'Note No. / Subcontractor', 'type' => 'doc', 'party' => 'supplier'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'process', 'label' => 'Process'],
            ['key' => 'co', 'label' => 'Customer Order'],
            ['key' => 'pr', 'label' => 'PR'],
            ['key' => 'ship', 'label' => 'Planned Shipment', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => SubconDelivery::STATUS_TONES],
        ]"
        :detail="['key' => 'items', 'title' => 'Item', 'columns' => [
            ['key' => 'item', 'label' => 'Item Name', 'strong' => true],
            ['key' => 'co', 'label' => 'CO No.'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
        ]]"
        :stats="[['label' => 'All'], ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'], ['label' => 'Confirmed', 'where' => ['status' => 'CONFIRMED'], 'tone' => 'success'], ['label' => 'Cancelled', 'where' => ['status' => 'CANCELLED'], 'tone' => 'danger']]"
        :filters="['supplier' => 'Subcontractor', 'process' => 'Process', 'status' => 'Status']" />
@elseif (! $do)
    <form method="post" action="{{ route('v2.subcon.create') }}">
        @csrf
        <x-dl::card title="+ New Record — Subcontract Delivery Note" description="Choose the subcontractor and the process. Items are added on the next screen, each with its Customer Order line.">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="supplier_id" label="Subcontractor" required :options="SubconDelivery::supplierOptions()" :source="Src::of('tm2mst.sup_supplier')" />
                <x-dl::field.text name="subcon_process" label="Subcontract Process" required placeholder="e.g. Lamination, UV spot" :source="Src::of('tm2sdi.do_delivery.subcon_process')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Create" />
            </x-dl::action-bar>
        </x-dl::card>
    </form>
@else
    @php
        $draft = $do->status === 'DRAFT';
        $coItems = SubconDelivery::coItemOptions($do);
        $lines = $do->lines->map(fn ($l) => ['id_line' => $l->id_line, 'co_order_item_id' => $l->co_order_item_id, 'item_description' => $l->item_description,
            'qty_do' => (float) $l->qty_do, 'qty_unit' => $l->qty_unit])->values()->all();
    @endphp
    <x-dl::form id="subcon-dn-form" :title="t('Subcontract Delivery Note').' · '.($do->do_no ?: 'Draft #'.$do->id_delivery)" :subtitle="($do->supplier?->supplier_name ?? '—').' · '.$do->status"
        panel="top" kind="document" :status="SubconDelivery::formStatus($do)"
        :action="route('v2.subcon.save', $do->id_delivery)" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hide="['duplicate', 'email', 'print']"
        :tabs="['general' => 'General', 'delivery' => 'Delivery', 'notes' => 'Notes']"
        :created-by="$do->created_by" :created-at="$do->created_at?->toDateTimeString()" :updated-by="$do->updated_by" :updated-at="$do->updated_at?->toDateTimeString()">
        <x-slot:special>
            @unless ($draft)<x-dl::button size="sm" preset="print" label="Print" :href="PrintDocs::url('subcon-delivery-note', $do->id_delivery)" target="_blank" />@endunless
        </x-slot:special>
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>@t('Note No.')</span><b>{{ $do->do_no ?: t('(on Confirm)') }}</b></div>
            <x-dl::field.date name="do_date" label="Date" :value="$do->do_date?->toDateString()" :source="Src::of('tm2sdi.do_delivery.do_date')" />
            <x-dl::field.select name="supplier_id" label="Subcontractor" required :options="SubconDelivery::supplierOptions() + ($do->supplier ? [$do->supplier_id => $do->supplier->supplier_name] : [])" :value="$do->supplier_id" :source="Src::of('tm2mst.sup_supplier')" />
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="subcon_process" label="Subcontract Process" required :value="$do->subcon_process" :source="Src::of('tm2sdi.do_delivery.subcon_process')" />
                <x-dl::field.select name="pr_id" label="Purchase Requisition (subcontract)" placeholder="— none —" :options="SubconDelivery::prOptions($do)" :value="$do->pr_id" :source="Src::of('tm2pur.pr_requisition')" />
                <x-dl::field.display label="Customer Order" :value="$do->lines->pluck('order.co_no')->filter()->unique()->implode(', ') ?: '—'" :source="Src::of('tm2sdi.do_line.co_order_id')" />
            </x-dl::form.row>
        </x-slot:general>
        <x-slot:delivery>
            <x-dl::form.row :cols="3">
                <x-dl::field.date name="scheduled_ship_date" label="Planned Shipment" :value="$do->scheduled_ship_date?->toDateString()" :source="Src::of('tm2sdi.do_delivery.scheduled_ship_date')" />
                <x-dl::field.display label="Ship To" :value="$do->ship_address_label ?? '—'" :source="Src::of('tm2sdi.do_delivery.ship_address_label')" />
                <x-dl::field.text name="ship_address_detail" label="Address" :value="$do->ship_address_detail" :source="Src::of('tm2sdi.do_delivery.ship_address_detail')" />
            </x-dl::form.row>
        </x-slot:delivery>
        <x-slot:notes>
            <x-dl::field.text name="notes" label="Notes" multiline :value="$do->notes" :source="Src::of('tm2sdi.do_delivery.notes')" />
        </x-slot:notes>
        <x-slot:detail>
            <x-dl::lines name="lines" title="Details" key-field="id_line" remove-field="remove" add-label="+ Item" :addable="$draft" :duplicable="false" :min-rows="0" :rows="$lines" :readonly="! $draft"
                source="{{ Src::of('tm2sdi.do_line') }}"
                :columns="[
                    ['key' => 'co_order_item_id', 'label' => 'Customer Order Line', 'type' => 'select', 'options' => $coItems, 'required' => true, 'width' => '320px'],
                    ['key' => 'item_description', 'label' => 'Item Sent (semi-finished)', 'type' => 'text', 'required' => true, 'width' => '360px'],
                    ['key' => 'qty_do', 'label' => 'Qty', 'type' => 'number', 'required' => true, 'sum' => true, 'width' => '110px'],
                    ['key' => 'qty_unit', 'label' => 'Unit', 'type' => 'text', 'required' => true, 'width' => '90px'],
                ]" />
        </x-slot:detail>
    </x-dl::form>
@endif
