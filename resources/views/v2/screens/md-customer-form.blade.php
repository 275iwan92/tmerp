{{--
 REMARK
 Tujuan       : Form Master Customer v2 (menggantikan halaman v1 pages::master.customer): identitas, spek cetak, penjualan dan keuangan, alamat kantor pusat + PIC utama, kontak dan cabang tambahan (dialog), governance; Block / Unblock; Activate / Deactivate.
 Sumber hulu  : tm2mst.cust_customer, cust_customer_contact, cust_customer_branch, cust_category, org_division, fin_payment_term, v_geo_full_address; aturan di App\Services\Master\CustomerMasterService.
 Input        : Query id (customer) atau new=1, tab; session v2_flash dan errors.
 Output       : POST v2.md.customer.save (Save / Activate / Deactivate / Block), v2.md.customer.unblock, v2.md.customer.branch* dan v2.md.customer.contact* (dialog kontak dan cabang).
 Dipakai oleh : Dibuka dari grid md-customer (Open dan + New Record) dan tombol Master Customer di panel CRM.
 Status       : Aktif. Saldo piutang hanya tampil. Kontak dan cabang tambahan muncul sesudah Save pertama.
--}}
@php
    use App\Models\Master\CustCustomer;
    use App\Services\Master\CustomerMasterService as Cu;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'md-customer');
    $svc = app(Cu::class);
    $c = request('id') ? CustCustomer::with(['branches', 'contacts'])->find((int) request('id')) : null;
    abort_if(request('id') && ! $c, 404);
    abort_unless($c || request('new'), 404);
    $new = ! $c;
    $c ??= new CustCustomer(['customer_type' => 'local', 'approval_status' => 'DRAFT', 'tax_profile' => 'PPN_11', 'currency_code' => 'IDR', 'credit_rating' => 'B', 'customer_tier' => 2, 'credit_check_enabled' => true, 'kyc_status' => 'PENDING', 'activate' => true]);
    $blocked = ! $new && $c->is_blacklisted;
    $s = fn (string $col) => Src::of('tm2mst.cust_customer.'.$col);
    $v = fn (string $col) => old($col, $c->{$col});
    $flag = fn (string $col) => old() ? (bool) old($col) : (bool) $c->{$col};
    $idr = fn ($x) => 'IDR '.Locale::number((float) $x, 2);
    $tabs = ['overview' => 'Overview', 'printing' => 'Printing Specs', 'sales' => 'Sales & Finance', 'sites' => 'Sites & Contacts', 'governance' => 'Governance'];
    $area = old('default_area', $svc->areaValue($c->default_area_id, $c->default_area_level));
    $canUnblock = $svc->canUnblock(Auth::user());
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="md-customer-form" :title="$new ? 'New Customer' : raw($c->customer_name)" :subtitle="$new ? null : raw($c->customer_code)"
    panel="top" kind="master" :status="$new ? 'new' : ($c->activate ? 'active' : 'inactive')" :stage="$new ? null : ($blocked ? 'Blocked' : (CustCustomer::APPROVAL_STATUSES[$c->approval_status] ?? $c->approval_status))"
    :action="route('v2.md.customer.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'"
    :hidden="$new ? [] : ['id' => $c->id_customer]" :tabs="$tabs" :log="$new ? [] : $svc->history($c)"
    :created-by="$c->created_by" :created-at="$c->created_at?->toDateTimeString()" :updated-by="$c->updated_by" :updated-at="$c->updated_at?->toDateTimeString()">
    <x-slot:special>
        @if (! $new && ! $blocked)
            <x-dl::button type="submit" name="action" value="block" variant="secondary" icon="fa-ban" label="Block" formnovalidate data-dl-confirm="{{ t('Block this customer?') }}" />
        @endif
        @if ($blocked)
            <x-dl::button type="button" variant="secondary" icon="fa-lock-open" label="Unblock" data-dl-open="cu-unblock" />
        @endif
    </x-slot:special>
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Customer code" :value="$new ? t('(on Save)') : $c->customer_code" />
        <x-dl::strip-kv kind="grand" label="Credit limit" :value="$idr($c->credit_limit ?? 0)" />
        <x-dl::strip-kv kind="grand" label="Receivable balance" :value="$idr($c->current_balance ?? 0)" />
        <x-dl::strip-kv kind="grand" label="Branches" :value="$new ? '—' : (string) (1 + $c->branches->count())" />
        <x-dl::strip-kv kind="grand" label="Contacts" :value="$new ? '—' : (string) (1 + $c->contacts->count())" />
    </x-slot:strip>

    <x-slot:overview>
        <x-dl::card title="General identity">
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="customer_code" label="Customer code" required :case-toggle="false" :value="$v('customer_code')" :source="$s('customer_code')" />
                <x-dl::field.text name="customer_name" label="Customer name" required :value="$v('customer_name')" :source="$s('customer_name')" />
                <x-dl::field.text name="trade_name" label="Trade name / brand" :value="$v('trade_name')" :source="$s('trade_name')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="parent_group" label="Parent / customer group" :value="$v('parent_group')" :source="$s('parent_group')" />
                <x-dl::field.text name="industry_category" label="Industry category" placeholder="FMCG - Food and beverage packaging" :value="$v('industry_category')" :source="$s('industry_category')" />
                <x-dl::field.select name="customer_type" label="Type" required :options="$svc->typeOptions()" :value="$v('customer_type')" :source="$s('customer_type')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="tax_code" label="NPWP" placeholder="00.000.000.0-000.000" :case-toggle="false" :value="$v('tax_code')" :source="$s('tax_code')" />
                <x-dl::field.select name="category" label="Category (payment tracking)" placeholder="None" :options="$svc->categoryOptions()" :value="$v('category')" :source="$s('category')" />
                <x-dl::field.select name="approval_status" label="Approval status" required :options="$svc->approvalOptions()" :value="$v('approval_status')" :source="$s('approval_status')" />
            </x-dl::form.row>
            <x-dl::field.checkbox name="is_prospect" text="Prospective customer (not official yet)" :value="$flag('is_prospect')" :source="$s('is_prospect')" />
            <x-dl::field.text name="note" label="Internal notes" multiline placeholder="Operating preferences, past problems" :value="$v('note')" :source="$s('note')" />
        </x-dl::card>
    </x-slot:overview>

    <x-slot:printing>
        <x-dl::card title="Printing specifications">
            <x-dl::form.row :cols="4">
                <x-dl::field.text name="primary_product" label="Main product" :value="$v('primary_product')" :source="$s('primary_product')" />
                <x-dl::field.text name="secondary_product" label="Secondary product" :value="$v('secondary_product')" :source="$s('secondary_product')" />
                <x-dl::field.text name="default_substrate" label="Default substrate" :value="$v('default_substrate')" :source="$s('default_substrate')" />
                <x-dl::field.text name="color_standard" label="Color standard" :value="$v('color_standard')" :source="$s('color_standard')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.text name="proofing_method" label="Proofing method" :value="$v('proofing_method')" :source="$s('proofing_method')" />
                <x-dl::field.text name="artwork_approval" label="Artwork approval" :value="$v('artwork_approval')" :source="$s('artwork_approval')" />
                <x-dl::field.text name="retained_sample" label="Retained sample" :value="$v('retained_sample')" :source="$s('retained_sample')" />
                <x-dl::field.text name="barcode_verification" label="Barcode verification" :value="$v('barcode_verification')" :source="$s('barcode_verification')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="finishing_preference" label="Finishing preference" :value="$v('finishing_preference')" :source="$s('finishing_preference')" />
                <x-dl::field.text name="die_owner" label="Die-cut owner" :value="$v('die_owner')" :source="$s('die_owner')" />
                <x-dl::field.text name="packing_instruction" label="Packing instruction" :value="$v('packing_instruction')" :source="$s('packing_instruction')" />
            </x-dl::form.row>
            <x-dl::field.text name="quality_notes" label="Quality notes and reject criteria" multiline :value="$v('quality_notes')" :source="$s('quality_notes')" />
        </x-dl::card>
    </x-slot:printing>

    <x-slot:sales>
        <x-dl::card title="Sales division and pricing">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="sales_division_id" label="Division (SBU)" placeholder="None" :options="$svc->divisionOptions()" :value="$v('sales_division_id')" :source="Src::of('tm2mst.cust_customer.sales_division_id', 'tm2mst.org_division')" />
                <x-dl::field.text name="price_list_code" label="Price list" :case-toggle="false" :value="$v('price_list_code')" :source="$s('price_list_code')" />
                <x-dl::field.select name="tax_profile" label="Tax profile" required :options="$svc->taxProfileOptions()" :value="$v('tax_profile')" :source="$s('tax_profile')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="Terms and credit">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="payment_term" label="Payment term" placeholder="None" :options="$svc->paymentTermOptions()" :value="$v('payment_term')" :source="Src::of('tm2mst.cust_customer.payment_term', 'tm2mst.fin_payment_term')" />
                <x-dl::field.text name="currency_code" label="Currency" placeholder="IDR" :case-toggle="false" :value="$v('currency_code')" :source="$s('currency_code')" />
                <x-dl::field.decimal name="credit_limit" label="Credit limit (IDR)" :value="old('credit_limit', (float) $c->credit_limit)" :source="$s('credit_limit')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="credit_rating" label="Credit rating" required :options="CustCustomer::CREDIT_RATINGS" :value="$v('credit_rating')" :source="$s('credit_rating')" />
                <x-dl::field.select name="customer_tier" label="Customer tier (production priority)" required :options="CustCustomer::CUSTOMER_TIERS" :value="$v('customer_tier')" :source="$s('customer_tier')" />
                <x-dl::field.display label="Current receivable balance" :value="$idr($c->current_balance ?? 0)" :source="$s('current_balance')" />
            </x-dl::form.row>
            <x-dl::field.checkbox name="credit_check_enabled" text="Enable credit checking" :value="$flag('credit_check_enabled')" :source="$s('credit_check_enabled')" />
        </x-dl::card>
    </x-slot:sales>

    <x-slot:sites>
        <x-dl::card title="Head office (main address)">
            <x-dl::field.text name="default_address" label="Full address" multiline placeholder="Street, number, RT/RW, landmark" :value="$v('default_address')" :source="$s('default_address')" />
            <x-dl::form.row :cols="2">
                <x-dl::field.lookup name="default_area" lookup="geo_area" label="Area" placeholder="Search village / district / city / postcode..." :value="$area" :source="Src::of('tm2mst.cust_customer.default_area_id', 'default_area_level')" />
                <x-dl::field.text name="ship_postal_code" label="Postal code" hint="Filled from the area, can be changed." :case-toggle="false" :value="old('ship_postal_code', $v('postal_code'))" :source="$s('postal_code')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="Main PIC contact">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="mr_ms" label="Salutation" placeholder="—" :options="['Mr' => 'Mr', 'Ms' => 'Ms']" :value="$v('mr_ms')" :source="$s('mr_ms')" />
                <x-dl::field.text name="contact_name" label="PIC name" :value="$v('contact_name')" :source="$s('contact_name')" />
                <x-dl::field.text name="phone" label="Phone no." :case-toggle="false" :value="$v('phone')" :source="$s('phone')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                <x-dl::field.text name="email" label="Email" :case-toggle="false" :value="$v('email')" :source="$s('email')" />
                <x-dl::field.text name="website" label="Website" :case-toggle="false" :value="$v('website')" :source="$s('website')" />
            </x-dl::form.row>
        </x-dl::card>
        @if ($new)
            <x-dl::alert tone="info" message="Additional contacts and branches can be added after the customer is saved." />
        @else
            <x-dl::card title="Additional contacts">
                <x-dl::action-bar><x-dl::button type="button" size="sm" variant="secondary" icon="fa-plus" label="Add contact" data-dl-open="cu-contact-new" /></x-dl::action-bar>
                @forelse ($c->contacts as $k)
                    <x-dl::form.row :cols="4">
                        <x-dl::field.display label="Name" :value="raw($k->contact_name.($k->is_default ? ' (default)' : ''))" :source="Src::of('tm2mst.cust_customer_contact.contact_name')" />
                        <x-dl::field.display label="Position" :value="raw($k->position ?: '—')" :source="Src::of('tm2mst.cust_customer_contact.position')" />
                        <x-dl::field.display label="Phone / email" :value="raw(trim($k->phone.' '.$k->email) ?: '—')" :source="Src::of('tm2mst.cust_customer_contact.phone', 'email')" />
                        <x-dl::button type="button" size="sm" variant="secondary" icon="fa-pen" label="Edit" :data-dl-open="'cu-contact-'.$k->id_customer_contact" />
                    </x-dl::form.row>
                @empty
                    <x-dl::hint note>No additional contacts yet.</x-dl::hint>
                @endforelse
            </x-dl::card>
            <x-dl::card title="Additional branches / sites">
                <x-dl::action-bar><x-dl::button type="button" size="sm" variant="secondary" icon="fa-plus" label="Add branch" data-dl-open="cu-branch-new" /></x-dl::action-bar>
                @forelse ($c->branches as $b)
                    <x-dl::form.row :cols="4">
                        <x-dl::field.display label="Branch" :value="raw(($b->branch_name ?: '—').($b->is_default ? ' (default)' : ''))" :source="Src::of('tm2mst.cust_customer_branch.branch_name')" />
                        <x-dl::field.display label="Address" :value="raw($b->address ?: '—')" :source="Src::of('tm2mst.cust_customer_branch.address')" />
                        <x-dl::field.display label="Phone" :value="raw($b->phone ?: '—')" :source="Src::of('tm2mst.cust_customer_branch.phone')" />
                        <x-dl::button type="button" size="sm" variant="secondary" icon="fa-pen" label="Edit" :data-dl-open="'cu-branch-'.$b->id_customer_branch" />
                    </x-dl::form.row>
                @empty
                    <x-dl::hint note>No additional branches yet.</x-dl::hint>
                @endforelse
            </x-dl::card>
        @endif
    </x-slot:sites>

    <x-slot:governance>
        <x-dl::card title="Governance and compliance">
            <x-dl::form.row :cols="2">
                <x-dl::field.checkbox name="tax_validated" text="NPWP verified by Finance" :value="$flag('tax_validated')" :source="$s('tax_validated')" />
                <x-dl::field.select name="kyc_status" label="KYC status" required :options="Cu::KYC" :value="$v('kyc_status')" :source="$s('kyc_status')" />
            </x-dl::form.row>
            <x-dl::hint note>COA / GL and financial reports are internal (Side A). Data shared with Sales goes through publish.</x-dl::hint>
        </x-dl::card>
    </x-slot:governance>
</x-dl::form>

@if (! $new)
    @if ($blocked)
        <x-dl::modal id="cu-unblock" title="Unblock Customer" icon="fa-lock-open" size="md">
            <x-dl::action-form action="{{ route('v2.md.customer.unblock', $c->id_customer) }}">
                <x-dl::field.text name="unblock_reason" label="Unblock reason" required multiline placeholder="Reason for unblocking..." :source="$s('note')" />
                <x-dl::alert tone="info" message="Only an admin or a user with access to the Finance and Accounting module can unblock. The reason, time and your name are added to the customer's notes." />
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                    <x-dl::button preset="save" label="Unblock" />
                </x-dl::action-bar>
            </x-dl::action-form>
        </x-dl::modal>
    @endif

    @foreach ([null, ...$c->contacts->all()] as $k)
        <x-dl::modal :id="'cu-contact-'.($k?->id_customer_contact ?? 'new')" :title="$k ? 'Edit Contact' : 'Add Contact'" icon="fa-address-book" size="md">
            <x-dl::action-form action="{{ $k ? route('v2.md.customer.contact', [$c->id_customer, $k->id_customer_contact]) : route('v2.md.customer.contact', $c->id_customer) }}">
                <x-dl::form.row :cols="2">
                    <x-dl::field.text name="contact_name" label="Contact name" required :value="$k?->contact_name" :source="Src::of('tm2mst.cust_customer_contact.contact_name')" />
                    <x-dl::field.text name="position" label="Position" :value="$k?->position" :source="Src::of('tm2mst.cust_customer_contact.position')" />
                </x-dl::form.row>
                <x-dl::form.row :cols="2">
                    <x-dl::field.text name="phone" label="Phone" :case-toggle="false" :value="$k?->phone" :source="Src::of('tm2mst.cust_customer_contact.phone')" />
                    <x-dl::field.text name="email" label="Email" :case-toggle="false" :value="$k?->email" :source="Src::of('tm2mst.cust_customer_contact.email')" />
                </x-dl::form.row>
                <x-dl::field.text name="note" label="Notes" multiline :value="$k?->note" :source="Src::of('tm2mst.cust_customer_contact.note')" />
                <x-dl::field.checkbox name="is_default" text="Make default contact" :value="(bool) $k?->is_default" :source="Src::of('tm2mst.cust_customer_contact.is_default')" />
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                    <x-dl::button preset="save" />
                </x-dl::action-bar>
            </x-dl::action-form>
            @if ($k)
                <x-dl::action-form action="{{ route('v2.md.customer.contact.default', [$c->id_customer, $k->id_customer_contact]) }}">
                    <x-dl::action-bar>
                        @unless ($k->is_default)<x-dl::button variant="secondary" size="sm" icon="fa-star" label="Make default" />@endunless
                    </x-dl::action-bar>
                </x-dl::action-form>
                <x-dl::action-form action="{{ route('v2.md.customer.contact.delete', [$c->id_customer, $k->id_customer_contact]) }}">
                    <x-dl::action-bar>
                        <x-dl::button variant="secondary" size="sm" icon="fa-trash" label="Remove contact" data-dl-confirm="{{ t('Remove this contact?') }}" />
                    </x-dl::action-bar>
                </x-dl::action-form>
            @endif
        </x-dl::modal>
    @endforeach

    @foreach ([null, ...$c->branches->all()] as $b)
        <x-dl::modal :id="'cu-branch-'.($b?->id_customer_branch ?? 'new')" :title="$b ? 'Edit Branch' : 'Add Branch'" icon="fa-location-dot" size="lg">
            <x-dl::action-form action="{{ $b ? route('v2.md.customer.branch', [$c->id_customer, $b->id_customer_branch]) : route('v2.md.customer.branch', $c->id_customer) }}">
                <x-dl::form.row :cols="2">
                    <x-dl::field.text name="branch_name" label="Branch name" placeholder="Surabaya warehouse" :value="$b?->branch_name" :source="Src::of('tm2mst.cust_customer_branch.branch_name')" />
                    <x-dl::field.text name="phone" label="Phone" :case-toggle="false" :value="$b?->phone" :source="Src::of('tm2mst.cust_customer_branch.phone')" />
                </x-dl::form.row>
                <x-dl::field.text name="address" label="Address" multiline :value="$b?->address" :source="Src::of('tm2mst.cust_customer_branch.address')" />
                <x-dl::form.row :cols="2">
                    <x-dl::field.lookup name="branch_area" lookup="geo_area" label="Area" placeholder="Search village / district / city / postcode..." :value="$svc->areaValue($b?->area_id, $b?->area_level)" :source="Src::of('tm2mst.cust_customer_branch.area_id', 'area_level')" />
                    <x-dl::field.text name="ship_postal_code" label="Postal code" :case-toggle="false" :value="$b?->postal_code" :source="Src::of('tm2mst.cust_customer_branch.postal_code')" />
                </x-dl::form.row>
                <x-dl::field.checkbox name="is_default" text="Make default branch" :value="(bool) $b?->is_default" :source="Src::of('tm2mst.cust_customer_branch.is_default')" />
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                    <x-dl::button preset="save" />
                </x-dl::action-bar>
            </x-dl::action-form>
            @if ($b)
                <x-dl::action-form action="{{ route('v2.md.customer.branch.default', [$c->id_customer, $b->id_customer_branch]) }}">
                    <x-dl::action-bar>
                        @unless ($b->is_default)<x-dl::button variant="secondary" size="sm" icon="fa-star" label="Make default" />@endunless
                    </x-dl::action-bar>
                </x-dl::action-form>
                <x-dl::action-form action="{{ route('v2.md.customer.branch.delete', [$c->id_customer, $b->id_customer_branch]) }}">
                    <x-dl::action-bar>
                        <x-dl::button variant="secondary" size="sm" icon="fa-trash" label="Remove branch" data-dl-confirm="{{ t('Remove this branch?') }}" />
                    </x-dl::action-bar>
                </x-dl::action-form>
            @endif
        </x-dl::modal>
    @endforeach
@endif
