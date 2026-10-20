{{--
 REMARK
 Tujuan       : Sales Details: nilai periode dan jumlah dokumen, daftar dokumen (Customer Order / Surat Jalan), peringkat customer dan per salesperson, dengan tembusan ke Customer 360.
 Sumber hulu  : App\Support\V2\Data\Mgmt\SalesDetail (co_order_legacy, co_delivery_legacy, cust_customer, ManagementReportService); pindahan layar v1 pages::report.sales.
 Input        : Query th (tahun), bln (bulan), basis, q (cari), tab, page.
 Output       : Kartu filter x-dl::action-form (GET), x-dl::kpi, tombol tab, x-dl::table / x-dl::chart.
 Dipakai oleh : Layar v2 slug mgmt-sales lewat rute v2.screen (menu Management Reports > Sales Details, sys_app_menu MGMT_SALES)
 Status       : Aktif
--}}
@php
    use App\Support\V2\Data\Mgmt\SalesDetail;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $r = new SalesDetail(request()->query());
    $src = Src::of('tm2sdi.co_order_legacy', 'tm2sdi.co_delivery_legacy', 'tm2mst.cust_customer');
    $total = $r->periodTotal();
    $months = SalesDetail::monthOptions();
@endphp

<x-dl::card title="Sales Details" description="Document list, customer ranking and salesperson performance.">
    <x-dl::action-form method="get" action="{{ route('v2.screen', 'mgmt-sales') }}">
        <x-dl::form.row :cols="4">
            <x-dl::field.select name="th" label="Year" :options="SalesDetail::yearOptions()" :value="$r->year" :placeholder="null" :source="$src" />
            <x-dl::field.select name="bln" label="Month" :options="$months" :value="$r->month" placeholder="All months" :source="$src" />
            <x-dl::field.select name="basis" label="Basis" :options="array_map('t', SalesDetail::BASES)" :value="$r->basis" :placeholder="null" :source="$src" />
            <x-dl::field.text name="q" label="Search" :value="$r->search" placeholder="Document number or customer name..." :source="$src" />
        </x-dl::form.row>
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="fa-arrow-left" label="Dashboard" :href="route('v2.screen', 'mgmt-executive').'?th='.$r->year" />
            <x-dl::button variant="secondary" icon="fa-filter" label="Filter" />
        </x-dl::action-bar>
    </x-dl::action-form>
</x-dl::card>

<x-dl::kpi-row>
    <x-dl::kpi label="Selected Period Value" :value="$total['value']" format="money" icon="fa-coins" tone="accent" :note="t($r->basis === 'shipment' ? 'based on delivery notes' : 'based on customer orders')" />
    <x-dl::kpi label="Number of Documents" :value="$total['docs']" icon="fa-file-lines" tone="success" :note="$r->month ? $months[$r->month].' '.$r->year : tf('All of :year', ['year' => $r->year])" />
</x-dl::kpi-row>

<x-dl::action-bar align="start">
    @foreach (SalesDetail::TABS as $key => $label)
        <x-dl::button :variant="$r->tab === $key ? 'primary' : 'secondary'" :label="$label" :href="$r->url(['tab' => $key, 'page' => null])" />
    @endforeach
</x-dl::action-bar>

@if ($r->tab === 'docs')
    @php
        $docs = $r->documents();
        $last = max(1, $docs->lastPage());
    @endphp
    <x-dl::card>
        <x-dl::table :columns="['no' => 'Number', 'date' => 'Date', 'customer' => ['Customer', 'raw' => true], 'status' => 'Status', 'value' => ['Value', 'num' => true], 'trace' => ['', 'raw' => true]]"
            :rows="$r->documentRows($docs)" empty="No documents match this filter." :source="$src" />
        <x-dl::action-bar align="start">
            <x-dl::hint>{{ tf('Page :page of :last · :total documents', ['page' => $docs->currentPage(), 'last' => $last, 'total' => Locale::number($docs->total())]) }}</x-dl::hint>
            @if ($docs->currentPage() > 1)<x-dl::button variant="secondary" size="sm" icon="fa-chevron-left" label="Previous" :href="$r->url(['page' => $docs->currentPage() - 1])" />@endif
            @if ($docs->currentPage() < $last)<x-dl::button variant="secondary" size="sm" icon="fa-chevron-right" label="Next" :href="$r->url(['page' => $docs->currentPage() + 1])" />@endif
        </x-dl::action-bar>
    </x-dl::card>
@elseif ($r->tab === 'customer')
    @php $rank = $r->customerRanking(); @endphp
    <x-dl::chart type="hbar" title="Customer Ranking" format="money" :labels="array_column($rank, 'name')" :series="[['name' => t('Value'), 'data' => array_column($rank, 'value')]]" :source="$src" />
    <x-dl::card>
        <x-dl::table :columns="['rank' => '#', 'name' => ['Customer', 'raw' => true], 'docs' => ['Documents', 'num' => true], 'value' => ['Value', 'num' => true]]"
            :rows="$r->customerRankRows($rank)" :source="$src" />
    </x-dl::card>
@elseif ($r->tab === 'customer')
    @php $rank = $r->customerRanking(); @endphp
    <x-dl::chart type="hbar" title="Customer Ranking" format="money" :labels="array_column($rank, 'name')" :series="[['name' => t('Value'), 'data' => array_column($rank, 'value')]]" :source="$src" />
    <x-dl::card>
        <x-dl::table :columns="['rank' => '#', 'name' => ['Customer', 'raw' => true], 'docs' => ['Documents', 'num' => true], 'value' => ['Value', 'num' => true]]"
            :rows="array_map(fn ($c, $i) => ['rank' => $i + 1, 'name' => raw($c['name']), 'docs' => Locale::number($c['docs']), 'value' => Locale::money($c['value'])], $rank, array_keys($rank))" :source="$src" />
        @foreach ($rank as $c)
            <x-dl::button variant="secondary" size="sm" icon="fa-arrow-right" :label="raw($c['name'])" :href="route('v2.screen', 'mgmt-customer').'?customer='.$c['id'].'&th='.$r->year" />
        @endforeach
    </x-dl::card>
@else
    @php $rank = $r->salesRanking(); @endphp
    <x-dl::chart type="hbar" title="Per Salesperson" format="money" :labels="array_column($rank, 'name')" :series="[['name' => t('Value'), 'data' => array_column($rank, 'value')]]" :source="$src" />
    <x-dl::card>
        <x-dl::table :columns="['name' => ['Salesperson', 'raw' => true], 'docs' => ['CO', 'num' => true], 'value' => ['Value', 'num' => true], 'pct' => ['Share', 'num' => true]]"
            :rows="array_map(fn ($s) => ['name' => raw($s['name']), 'docs' => Locale::number($s['docs']), 'value' => Locale::money($s['value']), 'pct' => Locale::number($s['pct'], 2).'%'], $rank)" :source="$src" />
    </x-dl::card>
@endif
