{{--
 REMARK
 Tujuan       : Form Master Supplier v2 (menggantikan halaman v1 pages::master.supplier): identitas, alamat + cabang tambahan, PIC + kontak tambahan, termin dan pajak; Block / Unblock, Delete (atau nonaktif bila masih dipakai), Activate / Deactivate.
 Sumber hulu  : tm2mst.sup_supplier, sup_supplier_contact, sup_supplier_branch, sup_category, fin_payment_term, v_geo_full_address; aturan di App\Services\Master\SupplierMasterService dan SupplierGuardService.
 Input        : Query id (supplier) atau new=1, tab; session v2_flash dan errors.
 Output       : POST v2.md.supplier.save (Save / Activate / Deactivate / Block / Delete), v2.md.supplier.branch* dan v2.md.supplier.contact* (dialog cabang dan kontak).
 Dipakai oleh : Dibuka dari grid md-supplier (Open dan + New Record).
 Status       : Aktif. Kontak dan cabang tambahan muncul sesudah Save pertama. Delete permanen hanya bila tidak ada yang menunjuk supplier.
--}}
@php
    use App\Models\Master\SupSupplier;
    use App\Services\Master\SupplierMasterService as Su;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'md-supplier');
    $svc = app(Su::class);
    $c = request('id') ? SupSupplier::with(['branches', 'contacts', 'paymentTerm'])->find((int) request('id')) : null;
    abort_if(request('id') && ! $c, 404);
    abort_unless($c || request('new'), 404);
    $new = ! $c;
    $c ??= new SupSupplier(['activate' => true, 'is_subcon' => false, 'is_blacklisted' => false]);
    $blocked = ! $new && $c->is_blacklisted;
    $s = fn (string $col) => Src::of('tm2mst.sup_supplier.'.$col);
    $v = fn (string $col) => old($col, $c->{$col});
    $flag = fn (string $col) => old() ? (bool) old($col) : (bool) $c->{$col};
    $tabs = ['identity' => 'Identity', 'address' => 'Address', 'contact' => 'PIC Contact', 'terms' => 'Terms & Tax'];
    $area = old('default_area', $svc->areaValue($c->default_area_id, $c->default_area_level));
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="md-supplier-form" :title="$new ? 'New Supplier' : raw($c->supplier_name)" :subtitle="$new ? null : raw($c->supplier_code)"
    panel="top" kind="master" :status="$new ? 'new' : ($c->activate ? 'active' : 'inactive')" :stage="$blocked ? 'Blocked' : null"
    :action="route('v2.md.supplier.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'"
    :hidden="$new ? [] : ['id' => $c->id_supplier]" :tabs="$tabs" :log="$new ? [] : $svc->history($c)"
    :created-by="$c->created_by" :created-at="$c->created_at?->toDateTimeString()" :updated-by="$c->updated_by" :updated-at="$c->updated_at?->toDateTimeString()">
    <x-slot:special>
        @unless ($new)
            <x-dl::button type="submit" name="action" value="block" variant="secondary" icon="{{ $blocked ? 'fa-lock-open' : 'fa-ban' }}" :label="$blocked ? 'Unblock' : 'Block'" formnovalidate data-dl-confirm="{{ $blocked ? t('Unblock this supplier?') : t('Block this supplier?') }}" />
            <x-dl::button type="submit" name="action" value="delete" variant="secondary" icon="fa-trash" label="Delete" formnovalidate data-dl-confirm="{{ t('Delete this supplier? If it is still used by transactions, it will only be deactivated.') }}" />
        @endunless
    </x-slot:special>
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Supplier code" :value="$new ? t('(on Save)') : $c->supplier_code" />
        <x-dl::strip-kv kind="grand" label="Category" :value="$new ? '—' : raw($c->category ?: '—')" />
        <x-dl::strip-kv kind="grand" label="Payment term" :value="$new ? '—' : raw($c->paymentTerm?->term_label ?: '—')" />
        <x-dl::strip-kv kind="grand" label="Branches" :value="$new ? '—' : (string) (1 + $c->branches->count())" />
        <x-dl::strip-kv kind="grand" label="Contacts" :value="$new ? '—' : (string) (1 + $c->contacts->count())" />
    </x-slot:strip>

    <x-slot:identity>
        <x-dl::card title="Identity">
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="supplier_code" label="Supplier code" required :case-toggle="false" :value="$v('supplier_code')" :source="$s('supplier_code')" />
                <x-dl::field.text name="supplier_name" label="Supplier name" required :value="$v('supplier_name')" :source="$s('supplier_name')" />
                <x-dl::field.select name="category" label="Category" placeholder="None" :options="$svc->categoryOptions()" :value="$v('category')" :source="$s('category')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="tax_code" label="NPWP" placeholder="00.000.000.0-000.000" :case-toggle="false" :value="$v('tax_code')" :source="$s('tax_code')" />
                <x-dl::field.checkbox name="is_subcon" text="Subcontractor (PPh 23 and the subcon payable account by default)" :value="$flag('is_subcon')" :source="$s('is_subcon')" />
            </x-dl::form.row>
            <x-dl::field.text name="note" label="Notes" multiline placeholder="Reason for blocking, history of quality or delivery problems" :value="$v('note')" :source="$s('note')" />
        </x-dl::card>
    </x-slot:identity>

    <x-slot:address>
        <x-dl::card title="Address">
            <x-dl::field.text name="default_address" label="Full address" multiline placeholder="Street, number, RT/RW, landmark" :value="$v('default_address')" :source="$s('default_address')" />
            <x-dl::form.row :cols="2">
                <x-dl::field.lookup name="default_area" lookup="geo_area" label="Area" placeholder="Search village / district / city / postcode..." :value="$area" :source="Src::of('tm2mst.sup_supplier.default_area_id', 'default_area_level')" />
                <x-dl::field.text name="ship_postal_code" label="Postal code" hint="Filled from the area, can be changed." :case-toggle="false" :value="old('ship_postal_code', $v('postal_code'))" :source="$s('postal_code')" />
            </x-dl::form.row>
        </x-dl::card>
        @if ($new)
            <x-dl::alert tone="info" message="Additional branches and contacts can be added after the supplier is saved." />
        @else
            <x-dl::card title="Additional branches / sites">
                <x-dl::action-bar><x-dl::button type="button" size="sm" variant="secondary" icon="fa-plus" label="Add branch" data-dl-open="su-branch-new" /></x-dl::action-bar>
                @forelse ($c->branches as $b)
                    <x-dl::form.row :cols="4">
                        <x-dl::field.display label="Branch" :value="raw(($b->branch_name ?: '—').($b->is_default ? ' (default)' : ''))" :source="Src::of('tm2mst.sup_supplier_branch.branch_name')" />
                        <x-dl::field.display label="Address" :value="raw($b->address ?: '—')" :source="Src::of('tm2mst.sup_supplier_branch.address')" />
                        <x-dl::field.display label="Phone" :value="raw($b->phone ?: '—')" :source="Src::of('tm2mst.sup_supplier_branch.phone')" />
                        <x-dl::button type="button" size="sm" variant="secondary" icon="fa-pen" label="Edit" :data-dl-open="'su-branch-'.$b->id_supplier_branch" />
                    </x-dl::form.row>
                @empty
                    <x-dl::hint note>No additional branches yet.</x-dl::hint>
                @endforelse
            </x-dl::card>
        @endif
    </x-slot:address>

    <x-slot:contact>
        <x-dl::card title="PIC contact">
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="contact_name" label="PIC name" :value="$v('contact_name')" :source="$s('contact_name')" />
                <x-dl::field.text name="phone" label="Phone no." :case-toggle="false" :value="$v('phone')" :source="$s('phone')" />
                <x-dl::field.text name="phone_2" label="Phone 2" :case-toggle="false" :value="$v('phone_2')" :source="$s('phone_2')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="3">
                <x-dl::field.text name="fax" label="Fax" :case-toggle="false" :value="$v('fax')" :source="$s('fax')" />
                <x-dl::field.text name="email" label="Email" :case-toggle="false" :value="$v('email')" :source="$s('email')" />
                <x-dl::field.text name="website" label="Website" :case-toggle="false" :value="$v('website')" :source="$s('website')" />
            </x-dl::form.row>
        </x-dl::card>
        @unless ($new)
            <x-dl::card title="Additional contacts">
                <x-dl::action-bar><x-dl::button type="button" size="sm" variant="secondary" icon="fa-plus" label="Add contact" data-dl-open="su-contact-new" /></x-dl::action-bar>
                @forelse ($c->contacts as $k)
                    <x-dl::form.row :cols="4">
                        <x-dl::field.display label="Name" :value="raw($k->contact_name.($k->is_default ? ' (default)' : ''))" :source="Src::of('tm2mst.sup_supplier_contact.contact_name')" />
                        <x-dl::field.display label="Position" :value="raw($k->position ?: '—')" :source="Src::of('tm2mst.sup_supplier_contact.position')" />
                        <x-dl::field.display label="Phone / email" :value="raw(trim($k->phone.' '.$k->email) ?: '—')" :source="Src::of('tm2mst.sup_supplier_contact.phone', 'email')" />
                        <x-dl::button type="button" size="sm" variant="secondary" icon="fa-pen" label="Edit" :data-dl-open="'su-contact-'.$k->id_supplier_contact" />
                    </x-dl::form.row>
                @empty
                    <x-dl::hint note>No additional contacts yet.</x-dl::hint>
                @endforelse
            </x-dl::card>
        @endunless
    </x-slot:contact>

    <x-slot:terms>
        <x-dl::card title="Terms and tax">
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="payment_term" label="Payment term" placeholder="None" :options="$svc->paymentTermOptions()" :value="$v('payment_term')" :source="Src::of('tm2mst.sup_supplier.payment_term', 'tm2mst.fin_payment_term')" />
                <x-dl::field.decimal name="tax_purchase_value" label="Purchase tax rate (%)" :value="old('tax_purchase_value', (float) $c->tax_purchase_value)" :source="$s('tax_purchase_value')" />
                <x-dl::field.decimal name="tax_service_value" label="Service tax rate (%)" :value="old('tax_service_value', (float) $c->tax_service_value)" :source="$s('tax_service_value')" />
            </x-dl::form.row>
        </x-dl::card>
    </x-slot:terms>
</x-dl::form>

@if (! $new)
    @foreach ([null, ...$c->contacts->all()] as $k)
        <x-dl::modal :id="'su-contact-'.($k?->id_supplier_contact ?? 'new')" :title="$k ? 'Edit Contact' : 'Add Contact'" icon="fa-address-book" size="md">
            <x-dl::action-form action="{{ $k ? route('v2.md.supplier.contact', [$c->id_supplier, $k->id_supplier_contact]) : route('v2.md.supplier.contact', $c->id_supplier) }}">
                <x-dl::form.row :cols="2">
                    <x-dl::field.text name="contact_name" label="Contact name" required :value="$k?->contact_name" :source="Src::of('tm2mst.sup_supplier_contact.contact_name')" />
                    <x-dl::field.text name="position" label="Position" :value="$k?->position" :source="Src::of('tm2mst.sup_supplier_contact.position')" />
                </x-dl::form.row>
                <x-dl::form.row :cols="2">
                    <x-dl::field.text name="phone" label="Phone" :case-toggle="false" :value="$k?->phone" :source="Src::of('tm2mst.sup_supplier_contact.phone')" />
                    <x-dl::field.text name="email" label="Email" :case-toggle="false" :value="$k?->email" :source="Src::of('tm2mst.sup_supplier_contact.email')" />
                </x-dl::form.row>
                <x-dl::field.text name="note" label="Notes" multiline :value="$k?->note" :source="Src::of('tm2mst.sup_supplier_contact.note')" />
                <x-dl::field.checkbox name="is_default" text="Make default contact" :value="(bool) $k?->is_default" :source="Src::of('tm2mst.sup_supplier_contact.is_default')" />
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                    <x-dl::button preset="save" />
                </x-dl::action-bar>
            </x-dl::action-form>
            @if ($k)
                <x-dl::action-form action="{{ route('v2.md.supplier.contact.default', [$c->id_supplier, $k->id_supplier_contact]) }}">
                    <x-dl::action-bar>
                        @unless ($k->is_default)<x-dl::button variant="secondary" size="sm" icon="fa-star" label="Make default" />@endunless
                    </x-dl::action-bar>
                </x-dl::action-form>
                <x-dl::action-form action="{{ route('v2.md.supplier.contact.delete', [$c->id_supplier, $k->id_supplier_contact]) }}">
                    <x-dl::action-bar>
                        <x-dl::button variant="secondary" size="sm" icon="fa-trash" label="Remove contact" data-dl-confirm="{{ t('Remove this contact?') }}" />
                    </x-dl::action-bar>
                </x-dl::action-form>
            @endif
        </x-dl::modal>
    @endforeach

    @foreach ([null, ...$c->branches->all()] as $b)
        <x-dl::modal :id="'su-branch-'.($b?->id_supplier_branch ?? 'new')" :title="$b ? 'Edit Branch' : 'Add Branch'" icon="fa-location-dot" size="lg">
            <x-dl::action-form action="{{ $b ? route('v2.md.supplier.branch', [$c->id_supplier, $b->id_supplier_branch]) : route('v2.md.supplier.branch', $c->id_supplier) }}">
                <x-dl::form.row :cols="2">
                    <x-dl::field.text name="branch_name" label="Branch name" placeholder="Surabaya warehouse" :value="$b?->branch_name" :source="Src::of('tm2mst.sup_supplier_branch.branch_name')" />
                    <x-dl::field.text name="phone" label="Phone" :case-toggle="false" :value="$b?->phone" :source="Src::of('tm2mst.sup_supplier_branch.phone')" />
                </x-dl::form.row>
                <x-dl::field.text name="address" label="Address" multiline :value="$b?->address" :source="Src::of('tm2mst.sup_supplier_branch.address')" />
                <x-dl::form.row :cols="2">
                    <x-dl::field.lookup name="branch_area" lookup="geo_area" label="Area" placeholder="Search village / district / city / postcode..." :value="$svc->areaValue($b?->area_id, $b?->area_level)" :source="Src::of('tm2mst.sup_supplier_branch.area_id', 'area_level')" />
                    <x-dl::field.text name="ship_postal_code" label="Postal code" :case-toggle="false" :value="$b?->postal_code" :source="Src::of('tm2mst.sup_supplier_branch.postal_code')" />
                </x-dl::form.row>
                <x-dl::field.checkbox name="is_default" text="Make default branch" :value="(bool) $b?->is_default" :source="Src::of('tm2mst.sup_supplier_branch.is_default')" />
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" label="Close" data-dl-close-modal />
                    <x-dl::button preset="save" />
                </x-dl::action-bar>
            </x-dl::action-form>
            @if ($b)
                <x-dl::action-form action="{{ route('v2.md.supplier.branch.default', [$c->id_supplier, $b->id_supplier_branch]) }}">
                    <x-dl::action-bar>
                        @unless ($b->is_default)<x-dl::button variant="secondary" size="sm" icon="fa-star" label="Make default" />@endunless
                    </x-dl::action-bar>
                </x-dl::action-form>
                <x-dl::action-form action="{{ route('v2.md.supplier.branch.delete', [$c->id_supplier, $b->id_supplier_branch]) }}">
                    <x-dl::action-bar>
                        <x-dl::button variant="secondary" size="sm" icon="fa-trash" label="Remove branch" data-dl-confirm="{{ t('Remove this branch?') }}" />
                    </x-dl::action-bar>
                </x-dl::action-form>
            @endif
        </x-dl::modal>
    @endforeach
@endif
