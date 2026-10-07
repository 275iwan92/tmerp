{{-- Finance > Financial Reports > Financial Statements (v2): ringkasan Neraca, Laba Rugi (tahun berjalan) dan Arus Kas dalam satu halaman, semuanya dari voucher POSTED
     (App\Support\V2\Data\FinancialStatements). Tautan membuka laporan rinci masing-masing. --}}
@php
    use App\Support\V2\Data\FinancialStatements as FS;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $asOf = request('as_of') ?: now()->toDateString();
    $from = FS::yearStart($asOf);
    $bs = FS::ledger()->balanceSheet($asOf);
    $is = FS::ledger()->incomeStatement($from, $asOf);
    $cf = FS::ledger()->cashFlow($from, $asOf);
    [$bsRows, $isRows, $cfRows] = FS::summaryRows($bs, $is, $cf);
    $src = Src::of('tm2acc.fin_voucher', 'tm2acc.fin_voucher_line', 'tm2acc.fin_account');
    $q = '?'.http_build_query(['as_of' => $asOf, 'to' => $asOf, 'from' => $from]);
@endphp

<x-dl::card title="Financial Statements" description="PSAK-oriented management reporting from posted general ledger balances.">
    <x-dl::action-form method="get" action="{{ route('v2.screen', 'acc-statements') }}">
        <x-dl::form.row :cols="4"><x-dl::field.date name="as_of" label="As At" :value="$asOf" :source="$src" /></x-dl::form.row>
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="fa-filter" label="Filter" />
            <x-dl::button variant="secondary" icon="fa-scale-balanced" label="Trial Balance" :href="route('v2.screen', 'acc-rpt-trial-balance')" />
        </x-dl::action-bar>
    </x-dl::action-form>
</x-dl::card>

<div class="dl-grid__stats">
    @foreach (FS::summaryKpis($bs, $is, FS::priorYearIncome($asOf)) as $k)
        <x-dl::kpi :label="$k['label']" :value="$k['value']" :format="$k['format']" :previous="$k['previous']" :good-when="$k['goodWhen']" :icon="$k['icon']" :tone="$k['tone']" :note="$k['note']" />
    @endforeach
</div>

<x-dl::statement title="Balance Sheet" :subtitle="raw(tf('Statement of financial position as at :date.', ['date' => Locale::d($asOf)]))" :rows="$bsRows" :source="$src" />
<x-dl::action-bar><x-dl::button variant="secondary" size="sm" icon="fa-arrow-right" label="Open report" :href="route('v2.screen', 'acc-rpt-balance-sheet').'?as_of='.$asOf" /></x-dl::action-bar>

<x-dl::statement title="Income Statement" :subtitle="raw(tf('Year to date :date.', ['date' => Locale::d($asOf)]))" :rows="$isRows" :source="$src" />
<x-dl::action-bar><x-dl::button variant="secondary" size="sm" icon="fa-arrow-right" label="Open report" :href="route('v2.screen', 'acc-rpt-income-statement').'?from='.$from.'&to='.$asOf" /></x-dl::action-bar>

<x-dl::statement title="Cash Flow Statement" :subtitle="raw(tf('Indirect method · year to date :date.', ['date' => Locale::d($asOf)]))" :rows="$cfRows" :source="$src" />
<x-dl::action-bar><x-dl::button variant="secondary" size="sm" icon="fa-arrow-right" label="Open report" :href="route('v2.screen', 'acc-rpt-cash-flow').'?from='.$from.'&to='.$asOf" /></x-dl::action-bar>
