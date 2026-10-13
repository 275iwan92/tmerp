{{-- Management Reports > Executive Summary (v2). Data = App\Support\V2\Data\ExecutiveSummary (App\Services\Report\ManagementReportService,
     sama dengan layar v1 report.executive): penjualan, pembelian, invoice dan utang usaha terintegrasi. Rincian: tautan ke laporan v1 per bidang. --}}
@php
    use App\Support\V2\Data\ExecutiveSummary;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $es = ExecutiveSummary::for(request()->integer('th') ?: null, request('basis'), request()->boolean('confirmed'));
    $home = route('v2.screen', 'mgmt-executive');
    $trend = $es->monthlyTrend();
    $mix = $es->customerMix();
    $quarter = $es->quarterly();
    $buy = $es->purchaseTrend();
    $docs = $es->purchaseDocs();
    $attention = $es->attention();
    $src = Src::of('tm2sdi.co_order_legacy', 'tm2sdi.co_delivery_legacy', 'tm2pur.po_order_legacy', 'tm2pur.po_invoice_legacy');
@endphp

<x-dl::card title="Executive Performance Summary" description="Sales, purchasing and accounts payable in one view for the whole company.">
    <x-dl::action-form method="get" action="{{ $home }}">
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="th" label="Year" :options="array_combine($es->years(), $es->years())" :value="$es->year" :placeholder="null" :source="$src" />
            <x-dl::field.select name="basis" label="Revenue Basis" :options="array_map('t', ExecutiveSummary::BASES)" :value="$es->basis" :placeholder="null" :source="$src" />
            <x-dl::field.checkbox name="confirmed" text="Confirmed COs only" :value="$es->confirmedOnly" :source="$src" />
        </x-dl::form.row>
        <x-dl::action-bar><x-dl::button variant="secondary" icon="fa-filter" label="Filter" /></x-dl::action-bar>
    </x-dl::action-form>
</x-dl::card>

@if ($es->shipmentDataWarning())
    <x-dl::alert tone="warn" :message="raw(tf('Attention: prices on delivery notes were only recorded by the old system from mid-2023. For :year the Shipment figures will look like 0 — use the Incoming PO basis for this period.', ['year' => $es->year]))" />
@endif

<x-dl::kpi-row>
    @foreach ($es->kpis() as $k)
        <x-dl::kpi :label="$k['label']" :value="$k['value']" :format="$k['format']" :previous="$k['previous'] ?? null" :good-when="$k['goodWhen'] ?? 'up'" :icon="$k['icon']" :tone="$k['tone']" :note="$k['note']" />
    @endforeach
</x-dl::kpi-row>

<x-dl::chart type="area" :title="raw(tf('Monthly revenue :year vs :prev', ['year' => $es->year, 'prev' => $es->year - 1]))" :subtitle="raw(t('Basis').': '.t(ExecutiveSummary::BASES[$es->basis]))"
    format="money" :labels="$trend['labels']" :series="$trend['series']" :source="$src" />

<x-dl::chart type="donut" title="Customer Composition" :subtitle="raw(tf('Top 5 customers :year', ['year' => $es->year]))" :labels="$mix['labels']" :series="$mix['series']" format="money" :source="$src" />

<x-dl::chart type="bar" :title="raw(tf('Achievement per quarter :year', ['year' => $es->year]))" format="money" :labels="$quarter['labels']" :series="$quarter['series']" :source="$src" />

<x-dl::card title="Top Customers">
    <x-dl::table :columns="['name' => 'Customer', 'docs' => ['Documents', 'num' => true], 'value' => ['Revenue', 'num' => true]]"
        :rows="array_map(fn ($c) => ['name' => raw($c['name']), 'docs' => Locale::number($c['docs']), 'value' => Locale::money($c['value'])], $es->topCustomers())" :source="$src" />
    <x-dl::button variant="secondary" size="sm" icon="fa-arrow-right" label="All customers" :href="route('report.sales', ['th' => $es->year, 'tab' => 'customer'])" />
</x-dl::card>

<x-dl::card title="Document Chain Health" description="Purchasing, all periods">
    @foreach ($es->dataHealth() as $h)
        <x-dl::progress :value="(float) $h['pct']" tone="auto" :label="raw($h['label'].' · '.Locale::number($h['pct'], 1).'%')" />
    @endforeach
</x-dl::card>

<x-dl::card title="Management Attention">
    @forelse ($attention as $a)
        <x-dl::alert :tone="$a['tone']" :message="raw($a['title'].': '.$a['text'])" />
    @empty
        <x-dl::alert tone="success" message="No open findings right now." />
    @endforelse
</x-dl::card>

@if ($docs['po'] === 0 && $es->purchaseLastDate())
    <x-dl::alert tone="info" :message="raw(tf('No Purchase Order recorded in :year. Purchase recording in the old system stopped on :date — it does not mean the company stopped buying.', ['year' => $es->year, 'date' => $es->purchaseLastDate()]))" />
@endif
<x-dl::chart type="area" :title="raw(tf('Purchase trend :year', ['year' => $es->year]))" format="money" :labels="$buy['labels']" :series="$buy['series']" :source="$src" />
<x-dl::card>
    <x-dl::field.display label="Purchase Requisitions (PR)" :value="raw(tf(':n documents', ['n' => Locale::number($docs['pr'])]))" :source="$src" />
    <x-dl::field.display label="Purchase Orders" :value="raw(tf(':n documents', ['n' => Locale::number($docs['po'])]))" :source="$src" />
    <x-dl::field.display label="Goods Receipts" :value="raw(tf(':n documents', ['n' => Locale::number($docs['receive'])]))" :source="$src" />
    <x-dl::button variant="secondary" size="sm" icon="fa-arrow-right" label="Purchase details" :href="route('report.purchase', ['th' => $es->year])" />
</x-dl::card>
