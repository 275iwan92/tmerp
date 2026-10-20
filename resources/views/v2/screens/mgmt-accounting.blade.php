{{--
 REMARK
 Tujuan       : Accounting & Finance: akun COA aktif, voucher terposting, nilai posting jurnal bulanan, sisa hutang usaha dan supplier dengan hutang terbesar.
 Sumber hulu  : App\Support\V2\Data\Mgmt\AccountingReport (fin_account, fin_voucher, fin_voucher_line, ManagementReportService); pindahan layar v1 pages::report.accounting.
 Input        : Query th (tahun, bawaan tahun berjalan).
 Output       : Kartu filter x-dl::action-form (GET), x-dl::kpi, x-dl::chart, x-dl::table.
 Dipakai oleh : Layar v2 slug mgmt-accounting lewat rute v2.screen (menu Management Reports > Accounting & Finance, sys_app_menu MGMT_ACCOUNTING)
 Status       : Aktif
--}}
@php
    use App\Support\V2\Data\Mgmt\AccountingReport;
    use App\Support\V2\Data\Mgmt\MgmtReport;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $r = new AccountingReport(request()->query());
    $src = Src::of('tm2acc.fin_account', 'tm2acc.fin_voucher', 'tm2acc.fin_voucher_line');
    $v = $r->voucherStats();
    $ap = $r->payable();
@endphp

<x-dl::card title="Accounting & Finance Report" description="Journal posting, accounts and accounts payable.">
    <x-dl::action-form method="get" action="{{ route('v2.screen', 'mgmt-accounting') }}">
        <x-dl::form.row :cols="1">
            <x-dl::field.select name="th" label="Year" :options="AccountingReport::recentYears()" :value="$r->year" :placeholder="null" :source="$src" />
        </x-dl::form.row>
        <x-dl::action-bar><x-dl::button variant="secondary" icon="fa-filter" label="Filter" /></x-dl::action-bar>
    </x-dl::action-form>
</x-dl::card>

<x-dl::kpi-row>
    <x-dl::kpi label="Active COA Accounts" :value="$r->coaActive()" icon="fa-sitemap" tone="accent" note="Chart of Accounts" />
    <x-dl::kpi label="Posted Vouchers" :value="$v['posted']" icon="fa-check-double" tone="success" :note="tf(':draft draft · :cancelled cancelled', ['draft' => $v['draft'], 'cancelled' => $v['cancelled']])" />
    <x-dl::kpi label="Posting Value This Year" :value="$r->postedValue()" format="money" icon="fa-money-bill-trend-up" tone="violet" note="Total posted debit side" />
    <x-dl::kpi label="Accounts Payable Outstanding" :value="$ap['outstanding']" format="money" icon="fa-scale-balanced" tone="danger" :note="tf('of :amount invoiced', ['amount' => Locale::money($ap['invoice_value'])])" />
</x-dl::kpi-row>

<x-dl::chart type="area" :title="raw(tf('Monthly Journal Posting Value :year', ['year' => $r->year]))" format="money" :labels="MgmtReport::months()" :series="[['name' => t('Posting'), 'data' => $r->monthlyPostedValue()]]" :source="$src" />

<x-dl::card title="Suppliers with the Largest Payables">
    <x-dl::table :columns="['name' => 'Supplier', 'outstanding' => ['Outstanding', 'num' => true]]" :rows="$r->payableRows()" empty="No open payables." :source="$src" />
</x-dl::card>
