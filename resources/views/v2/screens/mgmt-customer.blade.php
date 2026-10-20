{{--
 REMARK
 Tujuan       : Customer 360: seluruh riwayat satu customer (nilai order, dikirim, ditagih, rentang hubungan, tren order tahunan, produk terbanyak) dan rantai dokumen satu CO: baris - Surat Jalan - Invoice.
 Sumber hulu  : App\Support\V2\Data\Mgmt\CustomerProfile (cust_customer, co_order_legacy*, co_delivery_legacy*, co_invoice_legacy*); pindahan layar v1 pages::report.customer.
 Input        : Query customer (id), th (tahun, opsional), co (id CO yang dibuka).
 Output       : Kartu filter x-dl::action-form (GET), x-dl::kpi, x-dl::chart, x-dl::table.
 Dipakai oleh : Layar v2 slug mgmt-customer (tersembunyi dari menu; dibuka dari mgmt-sales) lewat rute v2.screen
 Status       : Aktif
--}}
@php
    use App\Support\V2\Data\Mgmt\CustomerProfile;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $r = new CustomerProfile(request()->query());
    abort_unless($r->customer > 0, 404);
    $p = $r->profile();
    $src = Src::of('tm2mst.cust_customer', 'tm2sdi.co_order_legacy', 'tm2sdi.co_delivery_legacy', 'tm2sdi.co_invoice_legacy');
    $life = $r->lifetime();
    $trend = $r->yearlyTrend();
    $chain = $r->orderChain();
@endphp

<x-dl::card :title="raw($p->customer_name ?? 'Customer #'.$r->customer)" :description="raw($p?->customer_code ?? '')">
    <x-dl::action-form method="get" action="{{ route('v2.screen', 'mgmt-customer') }}">
        <x-dl::form.row :cols="2">
            <x-dl::field.display label="Customer" :value="raw($p->customer_name ?? '#'.$r->customer)" :source="$src" />
            <x-dl::field.select name="th" label="Year" :options="$r->customerYearOptions()" :value="$r->year" placeholder="All years" :source="$src" />
        </x-dl::form.row>
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="fa-arrow-left" label="Sales Details" :href="route('v2.screen', 'mgmt-sales').($r->year ? '?th='.$r->year : '')" />
            <x-dl::button variant="secondary" icon="fa-filter" label="Filter" />
        </x-dl::action-bar>
    </x-dl::action-form>
</x-dl::card>

<x-dl::kpi-row>
    <x-dl::kpi label="Total Order Value" :value="$life['co_value']" format="money" icon="fa-file-invoice" tone="accent" :note="tf(':n customer orders', ['n' => Locale::number($life['co_docs'])])" />
    <x-dl::kpi label="Total Shipped" :value="$life['sj_value']" format="money" icon="fa-truck-fast" tone="success" :note="tf(':n delivery notes', ['n' => Locale::number($life['sj_docs'])])" />
    <x-dl::kpi label="Total Invoiced" :value="$life['inv_value']" format="money" icon="fa-receipt" tone="violet" :note="tf(':n invoices', ['n' => Locale::number($life['inv_docs'])])" />
    <x-dl::kpi label="Relationship Span" :value="($life['first_date'] ? substr($life['first_date'], 0, 4) : '—').' – '.($life['last_date'] ? substr($life['last_date'], 0, 4) : '—')" icon="fa-calendar-days" tone="warn" note="first to last order" />
</x-dl::kpi-row>

<x-dl::chart type="bar" title="Yearly Order Trend" format="money" :labels="$trend['labels']" :series="$trend['series']" :source="$src" />

<x-dl::card title="Most Ordered Products">
    <x-dl::table :columns="['item' => 'Item Code', 'lines' => ['Lines', 'num' => true], 'qty' => ['Qty', 'num' => true], 'value' => ['Value', 'num' => true]]" :rows="$r->topProducts()" empty="No data yet." :source="$src" />
</x-dl::card>

<x-dl::card title="Customer Order History" description="Open an order number to see its document chain: order → delivery note → invoice.">
    <x-dl::table :columns="['no' => ['CO No.', 'raw' => true], 'date' => 'Date', 'po' => 'Customer PO', 'sales' => 'Sales', 'status' => 'Status', 'value' => ['Value', 'num' => true]]" :rows="$r->orderRows()" empty="No orders in this period." :source="$src" />
</x-dl::card>

@if ($r->openOrder)
    <x-dl::card title="Document Chain" :description="tf('Order :id', ['id' => $r->openOrder])">
        @forelse ($chain as $c)
            <x-dl::card :title="raw($c['title'])">
                <x-dl::table :columns="['no' => 'Delivery Note', 'date' => 'Date', 'qty' => ['Qty', 'num' => true]]" :rows="$c['deliveries']" empty="No deliveries yet." :source="$src" />
                <x-dl::table :columns="['no' => 'Invoice', 'date' => 'Date', 'value' => ['Value', 'num' => true]]" :rows="$c['invoices']" empty="Not invoiced yet." :source="$src" />
            </x-dl::card>
        @empty
            <x-dl::hint>@t('This order has no item lines.')</x-dl::hint>
        @endforelse
    </x-dl::card>
@endif
