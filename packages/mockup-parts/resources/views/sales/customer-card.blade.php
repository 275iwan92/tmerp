{{-- Tampilan objek x-mp::sales.customer-card (MockupParts\Components\Sales\CustomerCard). Judul / keterangan dari $card sudah
     jadi -> Lang::raw; judul grafik dan kolom tabel diterjemahkan di sini (Lang::text), sama seperti layar asalnya. --}}
@php
    use Devlib\Lang;
    $c = $card;
    $T = fn (string $s) => Lang::text($s);
@endphp
<div {{ $attributes->class('mp-flat') }} {!! $dl() !!}>
    <x-dl::card :title="Lang::raw($c['title'])" :description="Lang::raw($c['description'])" icon="fa-building">
        <x-slot:actions>
            @foreach ($c['buttons'] as [$icon, $label, $url])
                <x-dl::button size="sm" variant="secondary" :icon="$icon" :label="$label" :href="$url" />
            @endforeach
        </x-slot:actions>
        @foreach ($c['facts'] as $row)
            <x-dl::form.row :cols="5">
                @foreach ($row as [$label, $value, $sources])
                    <x-dl::field.display :label="$label" :value="$value" :source="$src(...$sources)" />
                @endforeach
            </x-dl::form.row>
        @endforeach
    </x-dl::card>

    <x-dl::tabs :tabs="$tabs()">
        <x-slot:overview>
            <x-dl::form.row :cols="$c['chart']['value'] ? 2 : 1">
                <x-dl::chart type="bar" :title="$T('Orders per month')" :subtitle="$T('Last 24 months, new system + archive')" :labels="$c['chart']['labels']" height="220px" :legend="false"
                    :series="[['name' => $T('Orders'), 'data' => $c['chart']['count'], 'tone' => 'accent']]" :source="$src('tm2sdi.co_order_legacy', 'tm2sdi.co_order')" />
                @if ($c['chart']['value'])
                    <x-dl::chart type="area" :title="$T('Order value per month')" :subtitle="$T('Last 24 months, archive of the old system')" :labels="$c['chart']['labels']" height="220px" format="money" :legend="false"
                        :series="[['name' => $T('Order value'), 'data' => $c['chart']['value'], 'tone' => 'success']]" :source="$src('tm2sdi.co_order_legacy.grand_total')" />
                @endif
            </x-dl::form.row>
            <x-dl::card title="Most Ordered Products" description="What this customer buys most often: use it when talking about a repeat order." icon="fa-box">
                <x-dl::table :columns="['product' => $T('Product'), 'code' => $T('Code'), 'n' => [$T('Times Ordered'), 'num' => true], 'qty' => [$T('Total Qty'), 'num' => true], 'unit' => $T('Unit'), 'last' => $T('Last Ordered')]"
                    :rows="$c['products']" :empty="$T('No order history yet.')" source="{{ $src('tm2sdi.co_order_legacy_detail', 'tm2sdi.co_order_item', 'tm2mst.prod_product') }}" />
            </x-dl::card>
            <x-dl::card title="Customer Profile" description="Read from Master Customer. Change it there." icon="fa-id-card">
                @foreach ($c['profile'] as [$cols, $row])
                    <x-dl::form.row :cols="$cols">
                        @foreach ($row as [$label, $value, $sources])
                            <x-dl::field.display :label="$label" :value="$value" :source="$src(...$sources)" />
                        @endforeach
                    </x-dl::form.row>
                @endforeach
            </x-dl::card>
        </x-slot:overview>
        <x-slot:activities>
            <x-dl::grid id="crm-customer-activities" mode="report" :rows="$c['activities']" search="Search notes, contact, next action..." :open-url="$c['activityUrl']" row-tone="row_tone" :per-page="25"
                source="{{ $src('tm2sdi.crm_activity') }}" :columns="$activityColumns()" :stats="\MockupParts\Components\Sales\CustomerCard::DUE_STATS" :filters="['type' => 'Type', 'topic' => 'Topic', 'by' => 'By']" />
        </x-slot:activities>
        <x-slot:quotations>
            <x-dl::grid id="crm-customer-quotations" mode="report" :rows="$c['quotations']" search="Search quotation no...." :open-url="$c['quotationUrl']" :per-page="25"
                source="{{ $src('tm2sdi.quo_quotation', 'tm2sdi.quo_quotation_item') }}" :columns="$quotationColumns()" :filters="['status' => 'Status']" />
        </x-slot:quotations>
        <x-slot:orders>
            <x-dl::grid id="crm-customer-orders" mode="report" :rows="$c['orders']" row-key="no" search="Search order no., customer PO..." :per-page="25"
                source="{{ $src('tm2sdi.co_order', 'tm2sdi.co_order_legacy (archive of the old system)') }}" :columns="$orderColumns()" :filters="['status' => 'Status', 'source' => 'Source']" />
        </x-slot:orders>
        <x-slot:deliveries>
            <x-dl::grid id="crm-customer-deliveries" mode="report" :rows="$c['deliveries']" row-key="no" search="Search delivery no...." :per-page="25"
                source="{{ $src('tm2sdi.do_delivery', 'tm2sdi.co_delivery_legacy (archive of the old system)') }}" :columns="$deliveryColumns()" :filters="['status' => 'Status', 'source' => 'Source']" />
        </x-slot:deliveries>
        <x-slot:complaints>
            <x-dl::grid id="crm-customer-complaints" mode="report" :rows="$c['complaints']" search="Search complaint no., product..." :open-url="$c['complaintUrl']" :per-page="25"
                source="{{ $src('tm2sdi.crm_complaint', 'tm2sdi.crm_complaint_item', 'tm2prd.qc_complaint_review') }}" :columns="$complaintColumns()" :filters="['status' => 'Status', 'category' => 'Category']" />
        </x-slot:complaints>
        <x-slot:contacts>
            <x-dl::card title="Contact Persons" description="Maintained in Master Customer." icon="fa-address-book">
                <x-dl::table :columns="['name' => $T('Name'), 'position' => $T('Position'), 'phone' => $T('Phone'), 'email' => 'E-mail', 'note' => $T('Notes')]" :rows="$c['contacts']"
                    :empty="$T('No contact person yet. Add one in Master Customer.')" source="{{ $src('tm2mst.cust_customer_contact') }}" />
            </x-dl::card>
            <x-dl::card title="Branches / Delivery Addresses" icon="fa-location-dot">
                <x-dl::table :columns="['name' => $T('Branch'), 'address' => $T('Address'), 'phone' => $T('Phone')]" :rows="$c['branches']" :empty="$T('No branch yet.')"
                    source="{{ $src('tm2mst.cust_customer_branch') }}" />
            </x-dl::card>
        </x-slot:contacts>
    </x-dl::tabs>
</div>
