{{-- Finance > Financial Reports > Balance Sheet (v2). Data & aturan = App\Support\V2\Data\FinancialStatements (LedgerService, voucher POSTED saja, sama dengan v1):
     posisi keuangan per tanggal, kartu KPI (dibanding akhir tahun lalu), perbandingan dua tanggal, unduh CSV. Laba tahun berjalan masuk Laba Ditahan selama belum ada voucher penutup. --}}
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

<div class="dl-grid__stats">
    @foreach (FS::balanceSheetKpis($bs, $prior) as $k)
        <x-dl::kpi :label="$k['label']" :value="$k['value']" :format="$k['format']" :previous="$k['previous']" :good-when="$k['goodWhen']" :icon="$k['icon']" :tone="$k['tone']" :note="$k['note']" />
    @endforeach
</div>

<x-dl::statement title="Statement of Financial Position" :subtitle="raw(tf('As at :date · IDR', ['date' => Locale::d($asOf)]))" :rows="FS::balanceSheetRows($bs)" :source="$src" />

@if ($diff != 0.0)
    <x-dl::alert tone="danger" :message="raw(tf('The balance sheet is out of balance by :amount — check the Trial Balance.', ['amount' => Locale::money($diff)]))" />
@endif

@if ($compare)
    <x-dl::statement title="Comparative View" :subtitle="raw(Locale::d($asOf).' vs '.Locale::d($compareTo))" :columns="[Locale::d($asOf), Locale::d($compareTo), t('Change')]" :rows="FS::balanceSheetComparison($bs, $prior)" :source="$src" />
@endif
