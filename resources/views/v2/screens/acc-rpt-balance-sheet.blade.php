{{--
 REMARK
 Tujuan       : Laporan Neraca per tanggal dari voucher POSTED, dengan KPI, perbandingan dua tanggal dan unduh CSV.
 Sumber hulu  : App\Support\V2\Data\FinancialStatements (LedgerService::balanceSheet); tabel tm2acc.fin_voucher, fin_voucher_line, fin_account.
 Input        : Query string as_of, compare, compare_to.
 Output       : x-dl::card dengan filter, x-dl::kpi-row dan tabel Neraca; Export ke route v2.acc.statement.csv.
 Dipakai oleh : Menu Finance > Financial Reports > Balance Sheet (slug acc-rpt-balance-sheet, route v2.screen).
 Status       : Aktif.
--}}
@php
    use App\Support\V2\Data\FinancialStatements as FS;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $asOf = request('as_of') ?: now()->toDateString();
    $compare = request()->boolean('compare');
    $compareTo = request('compare_to') ?: now()->subYear()->endOfYear()->toDateString();
    $bs = FS::ledger()->balanceSheet($asOf);
    $prior = FS::ledger()->balanceSheet($compareTo);
    $diff = FS::balanceDifference($bs);
    $home = route('v2.screen', 'acc-rpt-balance-sheet');
    $src = Src::of('tm2acc.fin_voucher', 'tm2acc.fin_voucher_line', 'tm2acc.fin_account');
    $query = http_build_query(['as_of' => $asOf, 'compare_to' => $compareTo]);
@endphp

<x-dl::card title="Balance Sheet" description="Statement of financial position from posted journals. Unaudited management accounts.">
    <x-dl::action-form method="get" action="{{ $home }}">
        <x-dl::form.row :cols="4">
            <x-dl::field.date name="as_of" label="As At" :value="$asOf" :source="$src" />
            <x-dl::field.checkbox name="compare" text="Compare with another date" :value="$compare" :source="$src" />
            <x-dl::field.date name="compare_to" label="Compare With" :value="$compareTo" :source="$src" />
        </x-dl::form.row>
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="fa-filter" label="Filter" />
            <x-dl::button variant="secondary" icon="fa-download" label="Export" :href="route('v2.acc.statement.csv', 'balance-sheet').'?'.$query" />
        </x-dl::action-bar>
    </x-dl::action-form>
</x-dl::card>

<x-dl::kpi-row>
    @foreach (FS::balanceSheetKpis($bs, $prior) as $k)
        <x-dl::kpi :label="$k['label']" :value="$k['value']" :format="$k['format']" :previous="$k['previous']" :good-when="$k['goodWhen']" :icon="$k['icon']" :tone="$k['tone']" :note="$k['note']" />
    @endforeach
</x-dl::kpi-row>

<x-dl::statement title="Statement of Financial Position" :subtitle="raw(tf('As at :date · IDR', ['date' => Locale::d($asOf)]))" :rows="FS::balanceSheetRows($bs)" :source="$src" />

@if ($diff != 0.0)
    <x-dl::alert tone="danger" :message="raw(tf('The balance sheet is out of balance by :amount — check the Trial Balance.', ['amount' => Locale::money($diff)]))" />
@endif

@if ($compare)
    <x-dl::statement title="Comparative View" :subtitle="raw(Locale::d($asOf).' vs '.Locale::d($compareTo))" :columns="[Locale::d($asOf), Locale::d($compareTo), t('Change')]" :rows="FS::balanceSheetComparison($bs, $prior)" :source="$src" />
@endif
