{{--
 REMARK
 Tujuan       : Depreciation Run v2 (menggantikan tombol Run Depreciation di halaman v1 Fixed Assets dan Amortization): pilih periode dan jenis aset, lihat aset yang belum disusutkan dan bebannya, lalu Post Depreciation Journal.
 Sumber hulu  : tm2acc.fin_asset + fin_asset_category + fin_asset_depr; pratinjau dan posting lewat App\Services\Acc\FixedAssetService -> AssetService::preview / run (satu voucher GEN untuk semua aset periode itu).
 Input        : Query period (YYYY-MM, bawaan bulan ini) dan kind (fixed | amortization); session v2_flash dan errors.
 Output       : POST v2.acc.depr.run -> voucher GEN (Dr beban / Cr akumulasi per aset) + fin_asset_depr per aset; tidak menulis apa pun saat hanya melihat pratinjau.
 Dipakai oleh : Menu Finance > Accounting > Depreciation Run (ACC_DEPR_RUN).
 Status       : Aktif. Aset yang sudah disusutkan pada periode itu tidak muncul lagi (tidak bisa dobel).
--}}
@php
    use App\Services\Acc\FixedAssetService as Fa;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $svc = app(Fa::class);
    $home = route('v2.screen', 'acc-depreciation-run');
    $periods = $svc->periodOptions();
    $period = array_key_exists((string) request('period'), $periods) ? request('period') : array_key_first($periods);
    $kind = array_key_exists((string) request('kind'), Fa::RUN_KINDS) ? request('kind') : 'fixed';
    $preview = $svc->runPreview($period, $kind);
    $expense = $svc->expenseCodes($preview);
    $total = (float) $preview->sum('amount');
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::action-form method="get" action="{{ $home }}">
    <x-dl::card title="Depreciation Run" description="Posts one journal for every asset not yet depreciated in the period: Dr expense / Cr accumulated depreciation. An asset that was already depreciated for the period does not appear again.">
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="period" label="Period" required :options="$periods" :value="$period" :source="Src::of('tm2acc.fin_asset_depr.period_no')" />
            <x-dl::field.select name="kind" label="Assets" required :options="Fa::RUN_LABELS" :value="$kind" :source="Src::of('tm2acc.fin_asset_category.asset_type')" />
        </x-dl::form.row>
        <x-dl::action-bar>
            <x-dl::button preset="save" label="Preview" />
        </x-dl::action-bar>
    </x-dl::card>
</x-dl::action-form>

<x-dl::card :title="Fa::RUN_LABELS[$kind].' · '.$periods[$period]" :description="$preview->isEmpty() ? 'Every asset is already depreciated for this period.' : $preview->count().' assets, total IDR '.Locale::number($total, 2)">
    @if ($preview->isNotEmpty())
        <x-dl::table :columns="['asset' => 'Asset', 'category' => 'Category', 'charge' => ['Monthly charge', 'num' => true], 'expense' => 'Expense account']"
            :rows="$preview->map(fn ($p) => ['asset' => raw($p->asset->asset_code.' · '.$p->asset->asset_name), 'category' => $p->asset->category?->category_name, 'charge' => Locale::number($p->amount, 2), 'expense' => $expense[$p->asset->category?->expense_account_id] ?? '—'])->all()"
            :source="Src::of('tm2acc.fin_asset')" />
        <x-dl::action-form action="{{ route('v2.acc.depr.run', ['period' => $period, 'kind' => $kind]) }}">
            <x-dl::action-bar>
                <x-dl::button preset="save" variant="primary" label="Post Depreciation Journal" />
            </x-dl::action-bar>
        </x-dl::action-form>
    @endif
</x-dl::card>
