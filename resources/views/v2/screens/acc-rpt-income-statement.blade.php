{{-- Finance > Financial Reports > Income Statement (v2). Data & aturan = App\Support\V2\Data\FinancialStatements (LedgerService, voucher POSTED saja, tanpa voucher penutup,
     sama dengan v1): laba rugi per periode, kartu KPI dengan margin, perbandingan bulanan, unduh CSV. Pendapatan positif, beban negatif. --}}
@php
    use App\Support\V2\Data\FinancialStatements as FS;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $from = request('from') ?: now()->startOfYear()->toDateString();
    $to = request('to') ?: now()->toDateString();
    $monthly = request()->boolean('monthly');
    $is = FS::ledger()->incomeStatement($from, $to);
    $home = route('v2.screen', 'acc-rpt-income-statement');
    $src = Src::of('tm2acc.fin_voucher', 'tm2acc.fin_voucher_line', 'tm2acc.fin_account');
@endphp

<x-dl::card title="Income Statement" description="Profit and loss statement from posted journals. Unaudited management accounts.">
    <x-dl::action-form method="get" action="{{ $home }}">
        <x-dl::form.row :cols="4">
            <x-dl::field.date name="from" label="From" :value="$from" :source="$src" />
            <x-dl::field.date name="to" label="To" :value="$to" :source="$src" />
            <x-dl::field.checkbox name="monthly" text="Compare monthly" :value="$monthly" :source="$src" />
        </x-dl::form.row>
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="fa-filter" label="Filter" />
            <x-dl::button variant="secondary" icon="fa-download" label="Export" :href="route('v2.acc.statement.csv', 'income-statement').'?'.http_build_query(['from' => $from, 'to' => $to])" />
        </x-dl::action-bar>
    </x-dl::action-form>
</x-dl::card>

<div class="dl-grid__stats">
    @foreach (FS::incomeStatementKpis($is, $from, $to) as $k)
        <x-dl::kpi :label="$k['label']" :value="$k['value']" :format="$k['format']" :previous="$k['previous']" :good-when="$k['goodWhen']" :icon="$k['icon']" :tone="$k['tone']" :note="$k['note']" />
    @endforeach
</div>

<x-dl::statement title="Statement of Profit or Loss" :subtitle="raw(Locale::d($from).' — '.Locale::d($to).' · IDR')" :rows="FS::incomeStatementRows($is)" :source="$src" />

@if ($monthly)
    <x-dl::card title="Monthly Comparison" description="Revenue, gross profit and net result per month.">
        <x-dl::table :columns="['month' => 'Month', 'revenue' => ['Revenue', 'num' => true], 'cogs' => ['Cost of Goods Sold', 'num' => true], 'gross' => ['Gross Profit', 'num' => true],
                'opex' => ['Operating Expenses', 'num' => true], 'net' => ['Net Profit', 'num' => true]]"
            :rows="array_map(fn ($m) => ['month' => raw($m['month']), 'revenue' => Locale::money($m['revenue']), 'cogs' => Locale::money($m['cogs']), 'gross' => Locale::money($m['gross']),
                'opex' => Locale::money($m['opex']), 'net' => Locale::money($m['net'])], FS::monthlyIncome($from, $to))" :source="$src" />
    </x-dl::card>
@endif
