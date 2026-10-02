{{-- Customer Invoice v2 (user 2026-10-02) lewat App\Support\V2\Data\CustomerInvoice -> App\Services\Acc\CustomerInvoiceService.
     Satu layar untuk dua jenis dokumen: Invoice (dari baris Surat Jalan yang sudah diterima customer) dan Down Payment (dari
     nilai Customer Order). GRID -> + New Record (pilih jenis + order, lalu centang baris Surat Jalan) -> DRAFT -> Confirm
     (nomor dari seri, jurnal, potongan uang muka) -> Cancel wajib alasan. Tagihan uang muka tidak menjurnal; jurnal lahir
     saat Record Receipt. Ketentuan: docs/md_for_systems/customerinvoice_accounting.md. --}}
@php
    use App\Services\Acc\CustomerInvoiceService as Service;
    use App\Support\V2\Data\CustomerInvoice;
    use App\Support\V2\Print\PrintDocs;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'customer-invoice');
    $inv = request('id') ? CustomerInvoice::find((int) request('id')) : null;
    abort_if(request('id') && ! $inv, 404);
    $num = fn ($v, int $d = 2) => Locale::number($v, $d);
    // Angka mentah untuk kotak isian: 2000000 -> "2000000", 268358.83 -> "268358.83", 50.00 -> "50" (titik desimal, tanpa pemisah ribuan).
    $plain = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $inv && ! request('new'))
    <x-dl::grid id="customer-invoice-list" :rows="CustomerInvoice::rows()" search="Search invoice no., customer, order no..." actions="worklist" :active-key="null" row-tone="tone"
        :new-url="$home.'?new=1'" :open-url="$home.'?id={id}'" source="{{ Src::of('tm2acc.fin_invoice', 'tm2acc.fin_invoice_line', 'tm2mst.cust_customer', 'tm2sdi.co_order') }}"
        :columns="[
            ['key' => 'no', 'label' => 'Invoice No. / Customer', 'type' => 'doc', 'party' => 'customer'],
            ['key' => 'kind', 'label' => 'Type', 'visible' => false],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'due', 'label' => 'Due Date', 'type' => 'date'],
            ['key' => 'co_no', 'label' => 'Customer Order'],
            ['key' => 'total', 'label' => 'Total', 'type' => 'money'],
            ['key' => 'dp', 'label' => 'Down Payment', 'type' => 'money'],
            ['key' => 'outstanding', 'label' => 'Outstanding', 'type' => 'money'],
            ['key' => 'state', 'label' => 'Status', 'type' => 'badge', 'tones' => CustomerInvoice::STATE_TONES],
        ]"
        :detail="['key' => 'items', 'title' => 'Item', 'columns' => [
            ['key' => 'product', 'label' => 'Product', 'strong' => true],
            ['key' => 'dn', 'label' => 'Delivery Note'],
            ['key' => 'received', 'label' => 'Qty Received', 'type' => 'number'],
            ['key' => 'invoiced', 'label' => 'Qty Invoiced', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'price', 'label' => 'Price', 'type' => 'money'],
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
        ]]"
        :stats="[['label' => 'All'], ['label' => 'Draft', 'where' => ['state' => 'Draft'], 'tone' => 'neutral'],
            ['label' => 'Open', 'where' => ['state' => ['Open', 'Partially paid', 'Due this week']], 'tone' => 'info'],
            ['label' => 'Overdue', 'where' => ['state' => 'Overdue'], 'tone' => 'danger'], ['label' => 'Paid', 'where' => ['state' => 'Paid'], 'tone' => 'success'],
            ['label' => 'Cancelled', 'where' => ['state' => 'Cancelled'], 'tone' => 'danger'],
            ['label' => 'Down Payment', 'where' => ['kind' => 'Down Payment'], 'tone' => 'warn']]"
        :filters="['customer' => 'Customer', 'kind' => 'Type', 'state' => 'Status']" />
@elseif (! $inv)
    @php
        $type = request('type') === 'DOWN_PAYMENT' ? 'DOWN_PAYMENT' : 'INVOICE';
        $orders = CustomerInvoice::orderOptions($type);
        $coId = (int) request('co_id');
    @endphp
    @if ($type === 'INVOICE' && isset($orders[$coId]))
        <form method="post" action="{{ route('v2.customer-invoice.create') }}">
            @csrf
            <input type="hidden" name="type" value="INVOICE">
            <input type="hidden" name="co_id" value="{{ $coId }}">
            <x-dl::card title="+ New Record — Customer Invoice" description="Delivery note lines the customer has received and that are not invoiced yet. Set Invoice to No for a line you do not bill now.">
                <x-dl::form.row :cols="2">
                    <x-dl::field.display label="Customer Order" :value="$orders[$coId]" :source="Src::of('tm2sdi.co_order', 'tm2mst.cust_customer')" />
                </x-dl::form.row>
                <x-dl::lines name="lines" title="Delivery Note Lines" key-field="id_line" :addable="false" :duplicable="false" :min-rows="0" :rows="CustomerInvoice::billableRows($coId)"
                    source="{{ Src::of('tm2sdi.do_line', 'tm2sdi.do_delivery') }}"
                    :columns="[
                        ['key' => 'dn', 'label' => 'Delivery Note', 'type' => 'display', 'width' => '140px'],
                        ['key' => 'date', 'label' => 'Date', 'type' => 'display', 'width' => '120px'],
                        ['key' => 'product', 'label' => 'Product', 'type' => 'display'],
                        ['key' => 'shipped', 'label' => 'Shipped', 'type' => 'display', 'width' => '100px'],
                        ['key' => 'received', 'label' => 'Received', 'type' => 'display', 'width' => '100px'],
                        ['key' => 'invoiced', 'label' => 'Already Invoiced', 'type' => 'display', 'width' => '130px'],
                        ['key' => 'remaining', 'label' => 'To Invoice', 'type' => 'display', 'width' => '100px'],
                        ['key' => 'unit', 'label' => 'Unit', 'type' => 'display', 'width' => '70px'],
                        ['key' => 'pick', 'label' => 'Invoice', 'type' => 'bool', 'default' => true, 'width' => '90px'],
                    ]" />
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="back" label="Back" :href="$home.'?new=1&type=INVOICE'" />
                    <x-dl::button preset="save" label="Create" />
                </x-dl::action-bar>
            </x-dl::card>
        </form>
    @else
        <x-dl::card title="+ New Record — Customer Invoice"
            :description="$type === 'INVOICE'
                ? 'Invoice: choose the order. Only orders with delivery notes the customer has received (Proof of Delivery) and that are not fully invoiced are listed.'
                : 'Down Payment: choose the confirmed order the customer pays in advance for. A down payment invoice posts no journal; the journal is made when the money is received.'">
            <x-dl::action-bar>
                <x-dl::button :variant="$type === 'INVOICE' ? 'primary' : 'secondary'" icon="fa-file-invoice-dollar" label="Invoice" :href="$home.'?new=1&type=INVOICE'" />
                <x-dl::button :variant="$type === 'DOWN_PAYMENT' ? 'primary' : 'secondary'" icon="fa-money-bill-wave" label="Down Payment" :href="$home.'?new=1&type=DOWN_PAYMENT'" />
            </x-dl::action-bar>
            <form method="{{ $type === 'INVOICE' ? 'get' : 'post' }}" action="{{ $type === 'INVOICE' ? $home : route('v2.customer-invoice.create') }}">
                @if ($type === 'INVOICE')
                    <input type="hidden" name="new" value="1">
                @else
                    @csrf
                @endif
                <input type="hidden" name="type" value="{{ $type }}">
                <x-dl::form.row :cols="2">
                    <x-dl::field.select name="co_id" label="Customer Order" required :options="$orders" :source="Src::of('tm2sdi.co_order', 'tm2mst.cust_customer')"
                        :hint="$orders ? null : t($type === 'INVOICE' ? 'No order has a received delivery note left to invoice. Record the Proof of Delivery first.' : 'No confirmed order found.')" />
                </x-dl::form.row>
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                    <x-dl::button preset="save" :label="$type === 'INVOICE' ? 'Next' : 'Create'" />
                </x-dl::action-bar>
            </form>
        </x-dl::card>
    @endif
@else
    @php
        $svc = CustomerInvoice::service();
        $isDp = $inv->invoice_type === Service::DOWN_PAYMENT;
        $status = CustomerInvoice::formStatus($inv);
        $draft = $status === 'draft';
        $t = $svc->totals($inv);
        $customer = \App\Models\Master\CustCustomer::withoutGlobalScopes()->find($inv->partner_id);
        $coNo = $inv->co_id ? \App\Models\Sdi\CoOrder::query()->whereKey($inv->co_id)->value('co_no') : null;
        $identity = $inv->is_ppn ? \App\Support\Branding\Brand::companyLegalName().' (PPN)' : \App\Support\Branding\Brand::companyName().' (Non PPN)';
        $legacy = ! $isDp && $inv->lines->isEmpty();
        $printType = $isDp ? 'dp-invoice' : 'customer-invoice';
        $canReceive = $isDp && $inv->doc_status === 'POSTED' && $inv->payment_status !== 'PAID';
        $canPay = ! $isDp && $inv->doc_status === 'POSTED' && $inv->payment_status !== 'PAID';
        $journal = CustomerInvoice::journalRows($inv);
        $payments = CustomerInvoice::paymentRows($inv);
        $dpRows = $isDp ? [] : CustomerInvoice::downPaymentRows($inv);
    @endphp
    <x-dl::form id="customer-invoice-form" :title="($isDp ? 'Down Payment Invoice' : 'Customer Invoice').' · '.raw($inv->invoice_number ?: 'Draft #'.$inv->invoice_id)"
        :subtitle="raw(($customer?->customer_name ?? '—').' · '.$identity)" panel="top" kind="document" :status="$status" :stage="$draft ? null : CustomerInvoice::state($inv)"
        :action="route('v2.customer-invoice.save', $inv->invoice_id)" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hide="['duplicate', 'email', 'print']"
        :tabs="['general' => 'General', 'tax' => 'Tax', 'notes' => 'Notes', 'payments' => 'Payments', 'journal' => 'Journal']"
        :created-by="$inv->created_by" :created-at="$inv->created_at?->toDateTimeString()" :updated-by="$inv->updated_by" :updated-at="$inv->updated_at?->toDateTimeString()">
        <x-slot:special>
            <x-dl::button size="sm" preset="print" label="Print" :href="PrintDocs::url($printType, $inv->invoice_id)" target="_blank" />
            @if ($isDp && (float) $inv->paid_amount > 0)
                <x-dl::button size="sm" variant="secondary" icon="print" label="Print Receipt" :href="PrintDocs::url('dp-receipt', $inv->invoice_id)" target="_blank" />
            @endif
            @if ($canReceive)
                <x-dl::button size="sm" variant="success" icon="fa-money-bill-wave" label="Record Receipt" data-dl-open="dp-receipt" />
            @endif
            @if ($canPay)
                <x-dl::button size="sm" variant="success" icon="fa-money-bill-wave" label="Record Payment" data-dl-open="invoice-payment" />
            @endif
        </x-slot:special>
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>@t('Invoice No.')</span><b>{{ $inv->invoice_number ?: t('(on Confirm)') }}</b></div>
            <x-dl::field.date name="invoice_date" label="Invoice Date" :value="$inv->invoice_date?->toDateString()" :readonly="! $draft" :source="Src::of('tm2acc.fin_invoice.invoice_date')" />
            <x-dl::field.display label="Customer" :value="$customer?->customer_name ?? '—'" :source="Src::of('tm2mst.cust_customer')" />
            <x-dl::field.display label="Customer Order" :value="$coNo ?? '—'" :source="Src::of('tm2sdi.co_order.co_no')" />
            <div class="dl-strip__kv dl-strip__grand"><span>{{ t($isDp ? 'Bill Amount' : 'Balance Due') }}</span><b>{{ $num($isDp ? $t['total'] : $t['balance']) }}</b></div>
        </x-slot:strip>

        <x-slot:general>
            @if ($legacy)
                <x-dl::alert tone="info" :message="t('This invoice was made before delivery note lines existed: it has one amount and no lines.')" />
            @endif
            @if ($inv->doc_status === 'CANCELLED')
                <x-dl::field.display label="Cancel Reason" :value="$inv->cancel_reason ?? '—'" :source="Src::of('tm2acc.fin_invoice.cancel_reason')" />
            @endif
            <x-dl::form.row :cols="4">
                <x-dl::field.text name="customer_po_no" label="Customer PO No." :value="$inv->customer_po_no" :readonly="! $draft" :source="Src::of('tm2acc.fin_invoice.customer_po_no')" />
                <x-dl::field.select name="payment_term_code" label="Payment Term" :options="CustomerInvoice::termOptions($inv)" :value="$inv->payment_term_code" :disabled="! $draft"
                    :source="Src::of('tm2acc.fin_invoice.payment_term_code', 'tm2mst.fin_payment_term')" />
                <x-dl::field.display label="Due Date" :value="Locale::d($inv->due_date) ?? '—'" :source="Src::calc('invoice date + days of the payment term', 'tm2acc.fin_invoice.due_date')" />
                <x-dl::field.display label="Identity" :value="$identity" :source="Src::of('tm2acc.fin_invoice.is_ppn')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="company_bank_id" label="Transfer To" required :options="CustomerInvoice::bankOptions($inv)" :value="$inv->company_bank_id" :disabled="! $draft"
                    :hint="t('Printed on the invoice. The default follows the tax identity (Finance > Company Bank Accounts).')" :source="Src::of('tm2acc.fin_invoice.company_bank_id', 'tm2acc.fin_company_bank')" />
                <x-dl::field.text name="bill_to_name" label="Bill To" :value="$inv->bill_to_name" :readonly="! $draft" :source="Src::of('tm2acc.fin_invoice.bill_to_name')" />
            </x-dl::form.row>
            <x-dl::field.text name="bill_to_address" label="Bill To Address" multiline :value="$inv->bill_to_address" :readonly="! $draft" :source="Src::of('tm2acc.fin_invoice.bill_to_address')" />
            @if ($isDp)
                <x-dl::form.row :cols="4">
                    <x-dl::field.display label="Total Order Amount" :value="$num($inv->order_amount)" :source="Src::of('tm2acc.fin_invoice.order_amount')" />
                    <x-dl::field.display label="Billed Before" :value="$num(CustomerInvoice::billedBefore($inv))" :source="Src::calc('other confirmed down payment invoices of this order', 'tm2acc.fin_invoice.total_amount')" />
                    <x-dl::field.decimal name="dp_percent" label="Percent of Order" unit="%" :value="$inv->dp_percent !== null ? $plain($inv->dp_percent) : ''" :disabled="! $draft"
                        :hint="t('Leave empty to type the amount yourself.')" :source="Src::of('tm2acc.fin_invoice.dp_percent')" />
                    <x-dl::field.decimal name="bill_amount" label="Bill Amount" :value="$plain($inv->total_amount)" :disabled="! $draft"
                        :hint="t('Used when Percent is empty. Includes VAT on a VAT order.')" :source="Src::of('tm2acc.fin_invoice.total_amount')" />
                </x-dl::form.row>
                <x-dl::field.text name="description" label="Description" :value="$inv->description" :readonly="! $draft" :source="Src::of('tm2acc.fin_invoice.description')" />
                <x-dl::alert tone="info" :message="t('A down payment invoice posts no journal and is not a receivable. The journal is made when the money is received (Record Receipt).')" />
            @else
                <x-dl::form.row :cols="4">
                    <x-dl::field.decimal name="charge_amount" label="Other Charges" :value="$plain($inv->charge_amount)" :disabled="! $draft"
                        :hint="t('Freight or other cost billed to the customer; part of the tax base.')" :source="Src::of('tm2acc.fin_invoice.charge_amount')" />
                    <x-dl::field.text name="charge_note" label="Other Charges Note" :value="$inv->charge_note" :readonly="! $draft" :source="Src::of('tm2acc.fin_invoice.charge_note')" />
                </x-dl::form.row>
            @endif
        </x-slot:general>

        <x-slot:tax>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Identity" :value="$identity" :source="Src::of('tm2acc.fin_invoice.is_ppn')" />
                <x-dl::field.display label="VAT Rate" :value="$inv->is_ppn ? Locale::decimal($inv->tax_rate, 2).'%' : '—'" :source="Src::of('tm2acc.fin_invoice.tax_rate')" />
                <x-dl::field.display label="Tax Base" :value="$inv->is_ppn ? $num($t['dpp']) : '—'" :source="Src::calc('subtotal - discount + other charges', 'tm2acc.fin_invoice.subtotal')" />
                <x-dl::field.display label="Tax ID (NPWP)" :value="$customer?->tax_code ?: '—'" :source="Src::of('tm2mst.cust_customer.tax_code')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                <x-dl::field.text name="tax_invoice_no" label="Tax Invoice No." :value="$inv->tax_invoice_no" :readonly="! $draft || ! $inv->is_ppn" :case-toggle="false"
                    :hint="t('Given by Coretax after the tax invoice is uploaded. May be filled in later.')" :source="Src::of('tm2acc.fin_invoice.tax_invoice_no')" />
            </x-dl::form.row>
        </x-slot:tax>

        <x-slot:notes>
            <x-dl::field.text name="notes" label="Notes" multiline :value="$inv->notes" :readonly="! $draft" :source="Src::of('tm2acc.fin_invoice.notes')" />
        </x-slot:notes>

        <x-slot:payments>
            <x-dl::table :columns="['what' => 'Event', 'no' => 'Document No.', 'date' => 'Date', 'amount' => ['Amount', 'num' => true, 'raw' => true]]" :rows="$payments"
                empty="No money received for this document yet." source="{{ Src::of('tm2acc.fin_payment_alloc', 'tm2acc.fin_dp_allocation', 'tm2acc.fin_voucher') }}" />
        </x-slot:payments>

        <x-slot:journal>
            @if ($isDp && ! $journal)
                <x-dl::alert tone="info" :message="t('No journal: a down payment invoice is a request for payment. The journal appears here after Record Receipt.')" />
            @else
                <x-dl::table :columns="['voucher' => 'Voucher', 'account' => 'Account', 'debit' => ['Debit', 'num' => true, 'raw' => true], 'credit' => ['Credit', 'num' => true, 'raw' => true], 'narration' => 'Narration']" :rows="$journal"
                    empty="No journal yet." source="{{ Src::of('tm2acc.fin_voucher', 'tm2acc.fin_voucher_line') }}" />
            @endif
        </x-slot:journal>

        <x-slot:detail>
            @unless ($isDp)
                <x-dl::lines name="lines" title="Items" key-field="id_invoice_line" remove-field="remove" :addable="false" :duplicable="false" :min-rows="1" :rows="CustomerInvoice::lineRows($inv)" :readonly="! $draft"
                    source="{{ Src::of('tm2acc.fin_invoice_line', 'tm2sdi.do_line') }}"
                    :columns="[
                        ['key' => 'description', 'label' => 'Product', 'type' => 'display'],
                        ['key' => 'color_config', 'label' => 'CFG', 'type' => 'display', 'width' => '60px'],
                        ['key' => 'order_type', 'label' => 'Type', 'type' => 'display', 'width' => '70px'],
                        ['key' => 'delivery_no', 'label' => 'Delivery Note', 'type' => 'display', 'width' => '130px'],
                        ['key' => 'qty_received', 'label' => 'Received', 'type' => 'display', 'width' => '100px'],
                        ['key' => 'invoiced_before', 'label' => 'Invoiced Before', 'type' => 'display', 'width' => '120px'],
                        ['key' => 'qty_invoiced', 'label' => 'Qty Invoiced', 'type' => 'number', 'required' => true, 'width' => '120px'],
                        ['key' => 'qty_unit', 'label' => 'Unit', 'type' => 'display', 'width' => '70px'],
                        ['key' => 'unit_price', 'label' => 'Price', 'type' => 'display', 'width' => '110px'],
                        ['key' => 'line_total', 'label' => 'Amount', 'type' => 'display', 'width' => '130px'],
                    ]" />
                @if ($draft)
                    <x-dl::alert tone="info" :message="t('The price comes from the Customer Order. Change Qty Invoiced when you bill less than what is left; Save recalculates the amounts. A lump sum line (Qty Minim) is billed once, for its price.')" />
                @endif
                @if ($dpRows)
                    <x-dl::lines name="dp" title="Down Payment of This Order" key-field="dp_id" :addable="false" :duplicable="false" :min-rows="count($dpRows)" :rows="$dpRows" :readonly="! $draft"
                        source="{{ Src::of('tm2acc.fin_invoice (CUSTOMER_DP)', 'tm2acc.fin_dp_allocation') }}"
                        :columns="array_values(array_filter([
                            ['key' => 'no', 'label' => 'Down Payment No.', 'type' => 'display'],
                            ['key' => 'date', 'label' => 'Date', 'type' => 'display', 'width' => '120px'],
                            ['key' => 'received', 'label' => 'Received', 'type' => 'display', 'width' => '140px'],
                            $draft ? ['key' => 'used', 'label' => 'Used Before', 'type' => 'display', 'width' => '140px'] : null,
                            $draft ? ['key' => 'remaining', 'label' => 'Remaining', 'type' => 'display', 'width' => '140px'] : null,
                            ['key' => 'apply', 'label' => 'Apply to This Invoice', 'type' => 'money', 'width' => '170px'],
                        ]))" />
                @endif
            @endunless
            <x-dl::table :columns="['label' => 'Summary', 'value' => ['Amount', 'num' => true, 'raw' => true]]"
                :rows="array_values(array_filter([
                    ['label' => t($isDp ? 'Price part' : 'Subtotal'), 'value' => $num($t['subtotal'])],
                    $t['discount'] > 0 ? ['label' => t('Discount').' '.Locale::decimal($inv->discount_pct, 2).'%', 'value' => '('.$num($t['discount']).')'] : null,
                    $t['charge'] > 0 ? ['label' => t('Other Charges'), 'value' => $num($t['charge'])] : null,
                    $inv->is_ppn ? ['label' => t('VAT').' '.Locale::decimal($inv->tax_rate, 2).'%', 'value' => $num($t['tax'])] : null,
                    ['label' => t('Total'), 'value' => $num($t['total'])],
                    $t['dp'] > 0 ? ['label' => t('Down Payment'), 'value' => '('.$num($t['dp']).')'] : null,
                    (float) $inv->paid_amount - (float) $inv->dp_applied_amount > 0 ? ['label' => t('Paid'), 'value' => '('.$num((float) $inv->paid_amount - (float) $inv->dp_applied_amount).')'] : null,
                    ['label' => t($isDp ? 'Not yet received' : 'Balance Due'), 'value' => $num($t['balance'])],
                    ['label' => t('In Words'), 'value' => \Devlib\Terbilang::words((int) round(max(0, $isDp ? $t['total'] : $t['balance'])), 'IDR')],
                ]))"
                source="{{ Src::calc('totals of the lines, tax and down payment', 'tm2acc.fin_invoice') }}" />
        </x-slot:detail>
    </x-dl::form>

    @if ($canReceive)
        <x-dl::modal id="dp-receipt" title="Record Receipt" icon="fa-money-bill-wave" size="lg">
            <form method="post" action="{{ route('v2.customer-invoice.receipt', $inv->invoice_id) }}">
                @csrf
                <x-dl::form.row :cols="2">
                    <x-dl::field.date name="received_date" label="Received Date" required :value="now()->toDateString()" :source="Src::of('tm2acc.fin_voucher.entry_date')" />
                    <x-dl::field.decimal name="amount" label="Amount Received" required :value="$plain((float) $inv->total_amount - (float) $inv->paid_amount)" :source="Src::of('tm2acc.fin_invoice.paid_amount')" />
                </x-dl::form.row>
                <x-dl::form.row :cols="2">
                    <x-dl::field.select name="company_bank_id" label="Received In" required :options="CustomerInvoice::bankOptions($inv)" :value="$inv->company_bank_id" :source="Src::of('tm2acc.fin_company_bank')" />
                    <x-dl::field.text name="tax_invoice_no" label="Tax Invoice No. (Down Payment)" :value="$inv->tax_invoice_no" :case-toggle="false" :disabled="! $inv->is_ppn || (float) $inv->tax_amount <= 0"
                        :hint="t('May be filled in later.')" :source="Src::of('tm2acc.fin_invoice.tax_invoice_no')" />
                </x-dl::form.row>
                <x-dl::alert tone="info" :message="t((float) $inv->tax_amount > 0
                    ? 'Confirm Receipt makes the bank receipt voucher and the journal: Bank to Customer Down Payment and Output VAT. After that the Down Payment Receipt can be printed.'
                    : 'Confirm Receipt makes the bank receipt voucher and the journal: Bank to Customer Down Payment. After that the Down Payment Receipt can be printed.')" />
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                    <x-dl::button preset="save" variant="success" label="Confirm Receipt" />
                </x-dl::action-bar>
            </form>
        </x-dl::modal>
    @endif

    @if ($canPay)
        @php $openRows = CustomerInvoice::openInvoiceRows($inv); @endphp
        <x-dl::modal id="invoice-payment" title="Record Payment" icon="fa-money-bill-wave" size="lg">
            <form method="post" action="{{ route('v2.customer-invoice.payment', $inv->invoice_id) }}">
                @csrf
                <x-dl::form.row :cols="3">
                    <x-dl::field.date name="received_date" label="Received Date" required :value="now()->toDateString()" :source="Src::of('tm2acc.fin_voucher.entry_date')" />
                    <x-dl::field.select name="company_bank_id" label="Received In" required :options="CustomerInvoice::bankOptions($inv)" :value="$inv->company_bank_id" :source="Src::of('tm2acc.fin_company_bank')" />
                    <x-dl::field.text name="note" label="Note" :source="Src::of('tm2acc.fin_voucher.description')" />
                </x-dl::form.row>
                <x-dl::lines name="pay" title="Open Invoices of This Customer" key-field="invoice_id" :addable="false" :duplicable="false" :min-rows="count($openRows)" :rows="$openRows"
                    source="{{ Src::of('tm2acc.fin_invoice', 'tm2acc.fin_payment_alloc') }}"
                    :columns="[
                        ['key' => 'no', 'label' => 'Invoice No.', 'type' => 'display'],
                        ['key' => 'date', 'label' => 'Date', 'type' => 'display', 'width' => '120px'],
                        ['key' => 'due', 'label' => 'Due Date', 'type' => 'display', 'width' => '120px'],
                        ['key' => 'outstanding', 'label' => 'Outstanding', 'type' => 'display', 'width' => '160px'],
                        ['key' => 'paid', 'label' => 'Paid Now', 'type' => 'money', 'width' => '170px'],
                    ]" />
                <x-dl::alert tone="info" :message="t('One receipt may pay several invoices of this customer, in part or in full. Confirm Payment makes the bank receipt voucher and the journal: Bank to Receivable.')" />
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                    <x-dl::button preset="save" variant="success" label="Confirm Payment" />
                </x-dl::action-bar>
            </form>
        </x-dl::modal>
    @endif
@endif
