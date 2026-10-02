{{-- Supplier Invoice Receipt v2 (Tanda Terima Tagihan Supplier, user 2026-10-02) lewat App\Support\V2\Data\SupplierInvoiceReceipt.
     GRID tanda terima -> + New Record = pilih supplier -> DRAFT: siapa yang menyerahkan, rencana bayar, lampiran, baris invoice
     (pilih tagihan yang sudah dicatat ATAU ketik nomor invoice) -> Confirm (nomor lahir, siap dicetak) -> Cancel wajib alasan.
     Bukan bukti bayar; tidak membuat jurnal. --}}
@php
    use App\Models\Acc\FinInvoiceReceipt;
    use App\Support\V2\Data\SupplierInvoiceReceipt;
    use App\Support\V2\Print\PrintDocs;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'supplier-invoice-receipt');
    $doc = request('id') ? SupplierInvoiceReceipt::find((int) request('id')) : null;
    abort_if(request('id') && ! $doc, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $doc && ! request('new'))
    <x-dl::grid id="supplier-invoice-receipt-list" :rows="SupplierInvoiceReceipt::rows()" search="Search receipt no., supplier, invoice no..." actions="worklist" :active-key="null"
        :new-url="$home.'?new=1'" :open-url="$home.'?id={id}'" source="{{ Src::of('tm2acc.fin_invoice_receipt', 'tm2acc.fin_invoice_receipt_line', 'tm2mst.sup_supplier') }}"
        :columns="[
            ['key' => 'no', 'label' => 'Receipt No. / Supplier', 'type' => 'doc', 'party' => 'supplier'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'handed_by', 'label' => 'Handed Over By'],
            ['key' => 'planned', 'label' => 'Planned Payment', 'type' => 'date'],
            ['key' => 'method', 'label' => 'Payment Method'],
            ['key' => 'total', 'label' => 'Total', 'type' => 'money'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => SupplierInvoiceReceipt::STATUS_TONES],
        ]"
        :detail="['key' => 'items', 'title' => 'Invoice', 'columns' => [
            ['key' => 'invoice', 'label' => 'Supplier Invoice No.', 'strong' => true],
            ['key' => 'invoice_date', 'label' => 'Invoice Date', 'type' => 'date'],
            ['key' => 'po', 'label' => 'PO No.'],
            ['key' => 'tax', 'label' => 'Tax Invoice No.'],
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
        ]]"
        :stats="[['label' => 'All'], ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'], ['label' => 'Confirmed', 'where' => ['status' => 'CONFIRMED'], 'tone' => 'success'], ['label' => 'Cancelled', 'where' => ['status' => 'CANCELLED'], 'tone' => 'danger']]"
        :filters="['supplier' => 'Supplier', 'status' => 'Status']" />
@elseif (! $doc)
    <form method="post" action="{{ route('v2.invoice-receipt.create') }}">
        @csrf
        <x-dl::card title="+ New Record — Supplier Invoice Receipt" description="Choose the supplier who hands over the invoices. The invoices are added on the next screen.">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="supplier_id" label="Supplier" required :options="SupplierInvoiceReceipt::supplierOptions()" :source="Src::of('tm2mst.sup_supplier')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Create" />
            </x-dl::action-bar>
        </x-dl::card>
    </form>
@else
    @php
        $draft = $doc->status() === 'DRAFT';
        $lines = $doc->lines->map(fn ($l) => ['id_invoice_receipt_line' => $l->id_invoice_receipt_line, 'invoice_id' => $l->invoice_id, 'supplier_invoice_no' => $l->supplier_invoice_no,
            'invoice_date' => $l->invoice_date?->toDateString(), 'po_id' => $l->po_id, 'tax_invoice_no' => $l->tax_invoice_no, 'amount' => (float) $l->amount])->values()->all();
    @endphp
    <x-dl::form id="supplier-invoice-receipt-form" :title="'Supplier Invoice Receipt · '.raw($doc->receipt_no ?: 'Draft #'.$doc->id_invoice_receipt)" :subtitle="raw(($doc->supplier?->supplier_name ?? '—').' · '.$doc->status())"
        panel="top" kind="document" :status="SupplierInvoiceReceipt::formStatus($doc)"
        :action="route('v2.invoice-receipt.save', $doc->id_invoice_receipt)" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hide="['duplicate', 'email', 'print']"
        :tabs="['general' => 'General', 'attachments' => 'Attachments', 'notes' => 'Notes']"
        :created-by="$doc->created_by" :created-at="$doc->created_at?->toDateTimeString()" :updated-by="$doc->updated_by" :updated-at="$doc->updated_at?->toDateTimeString()">
        <x-slot:special>
            @unless ($draft)<x-dl::button size="sm" preset="print" label="Print" :href="PrintDocs::url('supplier-invoice-receipt', $doc->id_invoice_receipt)" target="_blank" />@endunless
        </x-slot:special>
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>@t('Receipt No.')</span><b>{{ $doc->receipt_no ?: t('(on Confirm)') }}</b></div>
            <x-dl::field.date name="receipt_date" label="Date" :value="$doc->receipt_date?->toDateString()" :readonly="! $draft" :source="Src::of('tm2acc.fin_invoice_receipt.receipt_date')" />
            <x-dl::field.display label="Supplier" :value="$doc->supplier?->supplier_name ?? '—'" :source="Src::of('tm2mst.sup_supplier')" />
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="4">
                <x-dl::field.text name="handed_by" label="Handed Over By" :value="$doc->handed_by" :readonly="! $draft" placeholder="Name of the supplier's person" :source="Src::of('tm2acc.fin_invoice_receipt.handed_by')" />
                <x-dl::field.text name="payment_term" label="Payment Term" :value="$doc->payment_term" :readonly="! $draft" :source="Src::of('tm2acc.fin_invoice_receipt.payment_term')" />
                <x-dl::field.date name="planned_payment_date" label="Planned Payment" :value="$doc->planned_payment_date?->toDateString()" :readonly="! $draft" :source="Src::of('tm2acc.fin_invoice_receipt.planned_payment_date')" />
                <x-dl::field.select name="payment_method" label="Payment Method" :options="FinInvoiceReceipt::METHODS" :value="$doc->payment_method" :disabled="! $draft" :source="Src::of('tm2acc.fin_invoice_receipt.payment_method')" />
            </x-dl::form.row>
            @if ($doc->stat_cancel)
                <x-dl::field.display label="Cancel Reason" :value="$doc->cancel_reason ?? '—'" :source="Src::of('tm2acc.fin_invoice_receipt.cancel_reason')" />
            @endif
        </x-slot:general>
        <x-slot:attachments>
            <x-dl::form.row :cols="4">
                @foreach (FinInvoiceReceipt::ATTACHMENTS as $field => $label)
                    <x-dl::field.checkbox :name="$field" :text="t($label)" :value="$doc->{$field}" :disabled="! $draft" :source="Src::of('tm2acc.fin_invoice_receipt.'.$field)" />
                @endforeach
            </x-dl::form.row>
        </x-slot:attachments>
        <x-slot:notes>
            <x-dl::field.text name="notes" label="Notes" multiline :value="$doc->notes" :readonly="! $draft" :source="Src::of('tm2acc.fin_invoice_receipt.notes')" />
        </x-slot:notes>
        <x-slot:detail>
            <x-dl::lines name="lines" title="Details" key-field="id_invoice_receipt_line" remove-field="remove" add-label="+ Invoice" :addable="$draft" :duplicable="false" :min-rows="0" :rows="$lines" :readonly="! $draft"
                source="{{ Src::of('tm2acc.fin_invoice_receipt_line', 'tm2acc.fin_invoice', 'tm2pur.po_order') }}"
                :columns="[
                    ['key' => 'invoice_id', 'label' => 'Recorded Bill (optional)', 'type' => 'select', 'options' => SupplierInvoiceReceipt::billOptions($doc) + $doc->lines->whereNotNull('invoice_id')->pluck('supplier_invoice_no', 'invoice_id')->all(), 'width' => '300px'],
                    ['key' => 'supplier_invoice_no', 'label' => 'Supplier Invoice No.', 'type' => 'text', 'width' => '190px'],
                    ['key' => 'invoice_date', 'label' => 'Invoice Date', 'type' => 'date', 'width' => '140px'],
                    ['key' => 'po_id', 'label' => 'PO No.', 'type' => 'select', 'options' => SupplierInvoiceReceipt::poOptions($doc), 'width' => '160px'],
                    ['key' => 'tax_invoice_no', 'label' => 'Tax Invoice No.', 'type' => 'text', 'width' => '190px'],
                    ['key' => 'amount', 'label' => 'Amount', 'type' => 'money', 'sum' => true, 'width' => '150px'],
                ]" />
            @if ($draft)
                <x-dl::alert tone="info" :message="t('Pick a recorded bill and leave the other columns empty: Save fills in its number, date, tax invoice and amount. An invoice that is not recorded yet is typed in directly.')" />
            @endif
        </x-slot:detail>
    </x-dl::form>
@endif
