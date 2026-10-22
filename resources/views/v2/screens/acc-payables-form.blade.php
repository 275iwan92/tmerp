{{--
 REMARK
 Tujuan       : Form tagihan vendor v2 (menggantikan halaman v1 pages::finance.payables): catat tagihan (kartu Start = pilih vendor), Confirm (Approve and Post), Approve, Cancel, Validate Tax Invoice, Pay Bill (dialog Payment Run), tab GL Voucher dan Payments.
 Sumber hulu  : tm2acc.fin_invoice (SUPPLIER_BILL), fin_voucher + fin_voucher_line (jurnal PUR), fin_payment_alloc, tm2mst.sup_supplier, tm2pur.pv_receipt; aturan di App\Services\Acc\VendorBillService dan ArApService.
 Input        : Query id (tagihan), atau supplier (tagihan baru dari vendor itu); new=1 menampilkan kartu Start; session v2_flash dan errors.
 Output       : POST v2.acc.payables.save (Save / Confirm / Approve / Cancel / Validate Tax / Duplicate), POST v2.acc.payables.pay (dialog Payment Run).
 Dipakai oleh : Dibuka dari grid acc-payables (Open dan + New Record).
 Status       : Aktif. Draft baca-saja (sama seperti v1): batalkan lalu catat ulang. Posted tidak bisa dibatalkan di sini.
--}}
@php
    use App\Models\Acc\FinInvoice;
    use App\Models\Master\SupSupplier;
    use App\Services\Acc\VendorBillService as Vb;
    use App\Support\Finance\TaxRate;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'acc-payables');
    $svc = app(Vb::class);
    $user = Auth::user();
    $b = request('id') ? FinInvoice::with('voucher.lines.account', 'allocations.voucher')->where('invoice_type', 'SUPPLIER_BILL')->find((int) request('id')) : null;
    abort_if(request('id') && ! $b, 404);
    abort_unless($b || request('new') || request('supplier'), 404);
    $new = ! $b;
    $start = $new && ! request('supplier');
    $supplier = $new ? ($start ? null : SupSupplier::findOrFail((int) request('supplier'))) : SupSupplier::withoutGlobalScopes()->find($b->partner_id);
    $def = $new && $supplier ? $svc->defaults($supplier) : [];
    [$state, $stage] = $new ? ['new', null] : $svc->formState($b);
    $s = fn (string $col) => Src::of('tm2acc.fin_invoice.'.$col);
    $val = fn (string $col, $default = null) => old($col, $default);
    $ppnRate = Locale::number(TaxRate::ppn(), 0);
    $due = $new ? 0 : ($b->doc_status === 'POSTED' ? $b->outstanding() : (float) $b->total_amount - (float) $b->pph_amount);
    $idr = fn ($v) => 'IDR '.Locale::number((float) $v, 2);
    $posted = ! $new && $b->doc_status === 'POSTED';
    $pending = ! $new && in_array($b->doc_status, ['DRAFT', 'SUBMITTED'], true);
    $tabs = ['general' => 'General'] + ($posted && $b->voucher ? ['gl' => 'GL Voucher'] : []) + ($posted && $b->allocations->isNotEmpty() ? ['payments' => 'Payments'] : []);
    $payRows = $posted && $b->payment_status !== 'PAID' ? $svc->openForPay($b)->map(fn ($o) => ['invoice_id' => $o->invoice_id, 'bill' => ($o->partner_ref ?: $o->invoice_number), 'due' => Locale::date($o->due_date),
        'outstanding' => Locale::number((float) $o->outstanding(), 2), 'pay' => $o->invoice_id === $b->invoice_id ? (float) $o->outstanding() : ''])->values()->all() : [];
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if ($start)
    <x-dl::action-form method="get" action="{{ route('v2.screen', 'acc-payables-form') }}">
        <x-dl::card title="Start a vendor bill" description="Choose the vendor first: the due date follows the vendor's payment term, subcon vendors get PPh 23, and the vendor's goods receipts (PV) become selectable.">
            <x-dl::field.select name="supplier" label="Vendor" required :options="$svc->supplierOptions()" :source="Src::of('tm2mst.sup_supplier')" />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Start" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@else
    <x-dl::form id="acc-payables-form" :title="$new ? 'New Vendor Bill' : 'Vendor Bill'" :subtitle="$new ? raw($supplier->supplier_name) : raw(($supplier?->supplier_name ?? '#'.$b->partner_id).' · '.$b->invoice_number)"
        panel="top" kind="document" :status="$state" :stage="$stage" :pipeline="false" :approval-levels="1" :approval-at="1" :can-approve="! $new && $svc->canApprove($b, $user)" :can-input="$new"
        :action="route('v2.acc.payables.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hide="$new ? [] : ['delete', 'save', 'undo', 'hold', 'reject', 'cancel', 'email']"
        :hidden="$new ? ['supplier_id' => $supplier->id_supplier] : ['id' => $b->invoice_id]" :tabs="$tabs" :log="$new ? [] : $svc->history($b)"
        :created-by="$b?->created_by" :created-at="$b?->created_at?->toDateTimeString()" :updated-by="$b?->updated_by" :updated-at="$b?->updated_at?->toDateTimeString()">
        <x-slot:special>
            @if (! $new && $pending)
                <x-dl::button type="submit" name="action" value="cancel" variant="secondary" icon="fa-ban" :label="$b->doc_status === 'DRAFT' ? 'Cancel Draft' : 'Cancel Bill'" formnovalidate data-dl-confirm="{{ t('Cancel this bill?') }}" />
            @endif
            @if ($posted && (float) $b->tax_amount > 0 && ! $b->tax_validated)
                <x-dl::button type="submit" name="action" value="validate_tax" variant="secondary" icon="fa-circle-check" label="Validate Tax Invoice" formnovalidate />
            @endif
            @if ($posted && $b->payment_status !== 'PAID')
                <x-dl::button type="button" variant="primary" icon="fa-money-bill-wave" label="Pay Bill" data-dl-open="ap-pay" />
            @endif
        </x-slot:special>
        <x-slot:strip>
            <x-dl::strip-kv kind="doc" label="Vendor" :value="raw($supplier?->supplier_name ?? '#'.$b->partner_id)" />
            <x-dl::field.text name="partner_ref" label="Vendor bill no." required :case-toggle="false" placeholder="INV-ST-0973" :value="$val('partner_ref', $b?->partner_ref)" :source="$s('partner_ref')" />
            <x-dl::field.date name="bill_date" label="Bill date" required :value="$val('bill_date', $b?->invoice_date?->toDateString() ?? ($def['bill_date'] ?? now()->toDateString()))" :source="$s('invoice_date')" />
            <x-dl::field.date name="due_date" label="Due date" required :value="$val('due_date', $b?->due_date?->toDateString() ?? ($def['due_date'] ?? null))" :source="$s('due_date')" />
            <x-dl::strip-kv kind="grand" label="Amount due" :value="$new ? '—' : $idr($due)" />
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="1">
                <x-dl::field.text name="description" label="Description" required placeholder="Ivory paper 310 gsm supply" :value="$val('description', $b?->description)" :source="$s('description')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                @if ($new)
                    <x-dl::field.select name="pv_id" label="Goods receipt (PV)" placeholder="No PV (service / expense)" :options="$svc->pvOptions($supplier->id_supplier)" :value="$val('pv_id')" hint="3-way match: a bill from a PV debits GR/IR Clearing." :source="Src::of('tm2pur.pv_receipt')" />
                @else
                    <x-dl::field.display label="Source" :value="$b->source_type.($b->source_id ? ' #'.$b->source_id : '')" :source="$s('source_type')" />
                @endif
                <x-dl::field.select name="expense_account_id" label="Debit account" required :options="$svc->debitOptions($new ? null : $b->revenue_account_id)" :value="$val('expense_account_id', $b?->revenue_account_id ?? ($def['expense_account_id'] ?? null))" :source="$s('revenue_account_id')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.decimal name="subtotal" label="Net amount (IDR)" required :value="$val('subtotal', $b ? (float) $b->subtotal : null)" :source="$s('subtotal')" />
                <x-dl::field.select name="pph_code" label="Withholding tax" placeholder="None" :options="Vb::PPH" :value="$val('pph_code', $b?->pph_code ?? ($def['pph_code'] ?? ''))" :source="$s('pph_code')" />
                <x-dl::field.text name="tax_invoice_no" label="Tax invoice no. (e-Faktur)" :case-toggle="false" placeholder="Leave empty if not received yet" :value="$val('tax_invoice_no', $b?->tax_invoice_no)" :source="$s('tax_invoice_no')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.checkbox name="ppn" :text="'Input PPN '.$ppnRate.'%'" :value="old() ? (bool) old('ppn') : ($new ? true : (float) $b->tax_amount > 0)" :source="$s('tax_amount')" :disabled="! $new" />
            </x-dl::form.row>
            @unless ($new)
                <x-dl::card title="Amounts">
                    <x-dl::form.row :cols="3">
                        <x-dl::field.display label="Net amount" :value="$idr($b->subtotal)" :source="$s('subtotal')" />
                        <x-dl::field.display label="Input PPN" :value="$idr($b->tax_amount).((float) $b->tax_amount > 0 && ! $b->tax_validated ? ' (not validated)' : '')" :source="$s('tax_amount')" />
                        <x-dl::field.display :label="'Withholding '.($b->pph_code ?: 'PPh')" :value="$idr($b->pph_amount)" :source="$s('pph_amount')" />
                    </x-dl::form.row>
                    <x-dl::form.row :cols="3">
                        <x-dl::field.display label="Paid" :value="$idr($b->paid_amount)" :source="$s('paid_amount')" />
                        <x-dl::field.display label="Internal no." :value="$b->invoice_number" :source="$s('invoice_number')" />
                        <x-dl::field.display label="GL voucher" :value="$b->voucher?->voucher_number ?? t('Not posted')" :source="$s('voucher_id')" />
                    </x-dl::form.row>
                </x-dl::card>
            @endunless
        </x-slot:general>
        @if (isset($tabs['gl']))
            <x-slot:gl>
                <x-dl::table :columns="['account' => 'Account', 'debit' => ['Debit', 'num' => true], 'credit' => ['Credit', 'num' => true]]"
                    :rows="$b->voucher->lines->map(fn ($l) => ['account' => raw($l->account?->account_code.' · '.$l->account?->account_name), 'debit' => (float) $l->debit > 0 ? Locale::number((float) $l->debit, 2) : '—', 'credit' => (float) $l->credit > 0 ? Locale::number((float) $l->credit, 2) : '—'])->all()"
                    :source="Src::of('tm2acc.fin_voucher_line')" />
            </x-slot:gl>
        @endif
        @if (isset($tabs['payments']))
            <x-slot:payments>
                <x-dl::table :columns="['voucher' => 'Payment voucher', 'date' => 'Date', 'amount' => ['Amount', 'num' => true]]"
                    :rows="$b->allocations->map(fn ($a) => ['voucher' => $a->voucher?->voucher_number, 'date' => Locale::date($a->alloc_date), 'amount' => Locale::number((float) $a->amount, 2)])->all()"
                    :source="Src::of('tm2acc.fin_payment_alloc')" />
            </x-slot:payments>
        @endif
    </x-dl::form>

    @if ($payRows)
        <x-dl::modal id="ap-pay" :title="'Payment Run — '.raw($supplier?->supplier_name)" icon="fa-money-bill-wave" size="lg">
            <x-dl::action-form action="{{ route('v2.acc.payables.pay', $b->invoice_id) }}">
                <x-dl::form.row :cols="3">
                    <x-dl::field.date name="pay_date" label="Payment date" required :value="now()->toDateString()" :source="Src::of('tm2acc.fin_voucher.entry_date')" />
                    <x-dl::field.select name="pay_bank" label="Pay from" required :options="$svc->bankOptions()" :value="(int) \App\Models\Acc\FinAccount::where('account_code', '111200')->value('account_id')" :source="Src::of('tm2acc.fin_voucher.main_account_id')" />
                    <x-dl::field.text name="pay_note" label="Note / transfer reference" placeholder="TRF-009821" :case-toggle="false" :source="Src::of('tm2acc.fin_voucher.description')" />
                </x-dl::form.row>
                <x-dl::lines name="alloc" title="Bills to pay" key-field="invoice_id" :addable="false" :duplicable="false" :min-rows="0" :rows="$payRows" source="{{ Src::of('tm2acc.fin_payment_alloc') }}"
                    :columns="[
                        ['key' => 'bill', 'label' => 'Bill', 'type' => 'display'],
                        ['key' => 'due', 'label' => 'Due', 'type' => 'display'],
                        ['key' => 'outstanding', 'label' => 'Outstanding', 'type' => 'display', 'width' => '160px'],
                        ['key' => 'pay', 'label' => 'Pay', 'type' => 'money', 'sum' => true, 'width' => '160px'],
                    ]" />
                <x-dl::alert tone="info" message="Posting: Dr accounts payable / Cr bank. A payment cannot be more than what is still outstanding on each bill." />
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                    <x-dl::button preset="save" label="Post Payment" />
                </x-dl::action-bar>
            </x-dl::action-form>
        </x-dl::modal>
    @endif
@endif
