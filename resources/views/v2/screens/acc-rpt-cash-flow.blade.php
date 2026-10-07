{{-- Finance > Financial Reports > Cash Flow (v2). Data & aturan = App\Support\V2\Data\FinancialStatements (LedgerService::cashFlow, metode tidak langsung, voucher POSTED,
     sama dengan v1): total arus kas selalu sama dengan perubahan saldo akun kas / bank; selisih berarti ada akun tanpa klasifikasi arus kas (fin_account.cf_line). --}}
@php
    use App\Support\V2\Data\FinancialStatements as FS;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $from = request('from') ?: now()->startOfYear()->toDateString();
    $to = request('to') ?: now()->toDateString();
    $cf = FS::ledger()->cashFlow($from, $to);
    $diff = FS::cashDifference($cf);
    $trend = FS::operatingCashTrend($from, $to);
    $home = route('v2.screen', 'acc-rpt-cash-flow');
    $src = Src::of('tm2acc.fin_voucher', 'tm2acc.fin_voucher_line', 'tm2acc.fin_account');
@endphp

<x-dl::card title="Cash Flow Statement" description="Indirect method, from posted journals. Unaudited management accounts.">
    <x-dl::action-form method="get" action="{{ $home }}">
        <x-dl::form.row :cols="4">
            <x-dl::field.date name="from" label="From" :value="$from" :source="$src" />
            <x-dl::field.date name="to" label="To" :value="$to" :source="$src" />
        </x-dl::form.row>
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="fa-filter" label="Filter" />
            <x-dl::button variant="secondary" icon="fa-download" label="Export" :href="route('v2.acc.statement.csv', 'cash-flow').'?'.http_build_query(['from' => $from, 'to' => $to])" />
        </x-dl::action-bar>
    </x-dl::action-form>
</x-dl::card>

<div class="dl-grid__stats">
    @foreach (FS::cashFlowKpis($cf, $from) as $k)
        <x-dl::kpi :label="$k['label']" :value="$k['value']" :format="$k['format']" :previous="$k['previous']" :good-when="$k['goodWhen']" :icon="$k['icon']" :tone="$k['tone']" :note="$k['note']" />
    @endforeach
</div>

<x-dl::statement title="Cash Flow Detail" :subtitle="raw(Locale::d($from).' — '.Locale::d($to).' · IDR')" :rows="FS::cashFlowRows($cf)" :source="$src" />

@if ($diff != 0.0)
    <x-dl::alert tone="warn" :message="raw(tf('Cash reconciliation difference :amount — some accounts have no cash flow classification (cf_line).', ['amount' => Locale::money($diff)]))" />
@endif

@if (array_filter($trend['series'][0]['data']))
    <x-dl::chart type="line" title="Operating Cash Trend" subtitle="Monthly operating cash movement — IDR" format="money" :labels="$trend['labels']" :series="$trend['series']" :source="$src" />
@else
    <x-dl::alert tone="info" message="There is no operating cash flow in this period yet." />
@endif
