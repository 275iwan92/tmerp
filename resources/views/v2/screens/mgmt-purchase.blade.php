{{--
 REMARK
 Tujuan       : Purchase Details: KPI pembelian, tren bulanan, supplier terbesar, utang usaha (AP) seluruh periode dan daftar dokumen rantai PR - PO - Penerimaan - Invoice - Pembayaran.
 Sumber hulu  : App\Support\V2\Data\Mgmt\PurchaseDetail (tabel *_legacy tm2pur, sup_supplier, ManagementReportService); pindahan layar v1 pages::report.purchase.
 Input        : Query th (tahun), q (cari nomor), tab, page.
 Output       : Kartu filter x-dl::action-form (GET), x-dl::kpi, x-dl::chart, x-dl::table, x-dl::alert, tombol tab.
 Dipakai oleh : Layar v2 slug mgmt-purchase lewat rute v2.screen (menu Management Reports > Purchase Details, sys_app_menu MGMT_PURCHASE)
 Status       : Aktif
--}}
@php
    use App\Support\V2\Data\Mgmt\MgmtReport;
    use App\Support\V2\Data\Mgmt\PurchaseDetail;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $r = new PurchaseDetail(request()->query());
    $src = Src::of('tm2pur.po_order_legacy', 'tm2pur.po_invoice_legacy', 'tm2pur.po_payment_legacy', 'tm2mst.sup_supplier');
    $sum = $r->summary();
    $ap = $r->payable();
    $unmapped = $r->unmappedInvoices();
    $docs = $r->documents();
    $last = max(1, $docs->lastPage());
@endphp

<x-dl::card title="Purchase Details" description="Chain PR → PO → Goods Receipt → Invoice → Payment, from the migrated archive.">
    <x-dl::action-form method="get" action="{{ route('v2.screen', 'mgmt-purchase') }}">
        <x-dl::form.row :cols="2">
            <x-dl::field.select name="th" label="Year" :options="PurchaseDetail::yearOptions()" :value="$r->year" :placeholder="null" :source="$src" />
            <x-dl::field.text name="q" label="Search document number" :value="$r->search" placeholder="Type a document number..." :source="$src" />
        </x-dl::form.row>
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="fa-arrow-left" label="Dashboard" :href="route('v2.screen', 'mgmt-executive').'?th='.$r->year" />
            <x-dl::button variant="secondary" icon="fa-filter" label="Filter" />
        </x-dl::action-bar>
    </x-dl::action-form>
</x-dl::card>

<x-dl::kpi-row>
    <x-dl::kpi label="Purchase Order Value" :value="$sum['po_value']" format="money" icon="fa-cart-shopping" tone="accent" :note="tf(':n PO documents', ['n' => Locale::number($sum['po_docs'])])" />
    <x-dl::kpi label="Purchase Requisitions" :value="$sum['pr_docs']" icon="fa-clipboard-list" tone="warn" note="PR documents" />
    <x-dl::kpi label="Goods Receipts" :value="$sum['receive_docs']" icon="fa-truck-ramp-box" tone="success" note="receipt documents" />
    <x-dl::kpi label="Payments to Suppliers" :value="$sum['payment_value']" format="money" icon="fa-money-bill-transfer" tone="violet" :note="tf(':n vouchers', ['n' => Locale::number($sum['payment_docs'])])" />
</x-dl::kpi-row>

<x-dl::chart type="area" :title="raw(tf('Monthly Purchase Trend :year', ['year' => $r->year]))" format="money" :labels="MgmtReport::months()" :series="[['name' => t('Purchase'), 'data' => $r->monthlyValues()]]" :source="$src" />

@php $top = $r->topSuppliers(); @endphp
<x-dl::chart type="hbar" :title="raw(tf('Top Suppliers :year', ['year' => $r->year]))" format="money" :labels="array_column($top, 'name')" :series="[['name' => t('Value'), 'data' => array_column($top, 'value')]]" :source="$src" />

<x-dl::card title="Accounts Payable (AP) — all periods" description="From supplier invoices minus recorded payments.">
    <x-dl::kpi-row>
        <x-dl::kpi label="Total Supplier Invoices" :value="$ap['invoice_value']" format="money" icon="fa-file-invoice-dollar" tone="accent" :note="tf(':n invoices', ['n' => Locale::number($ap['invoice_docs'])])" />
        <x-dl::kpi label="Already Paid" :value="$ap['paid_value']" format="money" icon="fa-circle-check" tone="success" note="recorded payments" />
        <x-dl::kpi label="Outstanding" :value="$ap['outstanding']" format="money" icon="fa-triangle-exclamation" tone="danger" note="invoices minus payments" />
    </x-dl::kpi-row>
    <x-dl::table :columns="['name' => ['Supplier', 'raw' => true], 'docs' => ['Invoices', 'num' => true], 'invoiced' => ['Invoiced', 'num' => true], 'paid' => ['Paid', 'num' => true], 'outstanding' => ['Outstanding', 'num' => true]]"
        :rows="array_map(fn ($s) => ['name' => raw($s['name']), 'docs' => Locale::number($s['docs']), 'invoiced' => Locale::money($s['invoiced']), 'paid' => Locale::money($s['paid']), 'outstanding' => Locale::money($s['outstanding'])], $r->payableSuppliers())" :source="$src" />
    @if ($unmapped > 0)
        <x-dl::alert tone="danger" :message="raw(tf(':n invoices cannot be linked to any supplier (the old system stored the company name in the code column, e.g. MADJU/TANINDO/KEISHA), so the per-supplier table above does not cover the full value.', ['n' => Locale::number($unmapped)]))" />
    @endif
    <x-dl::hint>@t('A negative outstanding value means recorded payments exceed the invoices — probably the invoice was not captured in the old system. It is shown as it is, not forced to zero. The sales-side counterpart (receivables/AR) cannot be calculated: the customer payment receipt table in the old system (sdi_cpym) is completely empty.')</x-dl::hint>
</x-dl::card>

<x-dl::action-bar align="start">
    @foreach (PurchaseDetail::TABS as $key => $label)
        <x-dl::button :variant="$r->tab === $key ? 'primary' : 'secondary'" :label="$label" :href="$r->url(['tab' => $key, 'page' => null])" />
    @endforeach
</x-dl::action-bar>

<x-dl::card>
    <x-dl::table :columns="['no' => 'Number', 'date' => 'Date', 'supplier' => 'Supplier', 'status' => 'Status', 'value' => ['Value', 'num' => true]]" :rows="$r->documentRows($docs)" empty="No documents match this filter." :source="$src" />
    <x-dl::action-bar align="start">
        <x-dl::hint>{{ tf('Page :page of :last · :total documents', ['page' => $docs->currentPage(), 'last' => $last, 'total' => Locale::number($docs->total())]) }}</x-dl::hint>
        @if ($docs->currentPage() > 1)<x-dl::button variant="secondary" size="sm" icon="fa-chevron-left" label="Previous" :href="$r->url(['page' => $docs->currentPage() - 1])" />@endif
        @if ($docs->currentPage() < $last)<x-dl::button variant="secondary" size="sm" icon="fa-chevron-right" label="Next" :href="$r->url(['page' => $docs->currentPage() + 1])" />@endif
    </x-dl::action-bar>
</x-dl::card>
