{{--
 REMARK
 Tujuan       : Ringkasan stok per kelas umur (Active, Slow, Non-moving, Dead) dan usulan provisi; jurnal provisi hanya lahir saat tombol Post provision diklik.
 Sumber hulu  : App\Services\Inventory\Valuation\ObsolescenceService dan App\Support\V2\Data\ValuationDesk; persentase dan batas hari dari Settings > Constants (INV_OBSOLETE_PCT_*, INV_AGING_*_DAYS).
 Input        : Tanpa parameter; session v2_flash dan error validasi.
 Output       : x-dl::alert info, x-dl::card dengan x-dl::table ringkasan aging dan tombol Post provision; rincian per item x bin = laporan Stock Aging.
 Dipakai oleh : Layar v2 slug obsolete-stock lewat rute v2.screen (view v2.screen memuat berkas ini)
 Status       : Aktif
--}}
@php
    use App\Services\Inventory\Valuation\ObsolescenceService;
    use App\Support\V2\Data\ValuationDesk;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $service = app(ObsolescenceService::class);
    $rows = $service->rows();
    $summary = $service->summary($rows);
    $target = round((float) array_sum(array_column($summary, 'provision')), 2);
    $previous = ObsolescenceService::previousTotal();
    $columns = ['aging' => ['Aging', 'raw' => true], 'pct' => ['Provision %', 'num' => true, 'raw' => true], 'items' => ['Item x Bin', 'num' => true, 'raw' => true],
        'value' => ['Stock Value', 'num' => true, 'raw' => true], 'provision' => ['Provision', 'num' => true, 'raw' => true]];
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif
<x-dl::alert tone="info" message="Nothing is posted until you click Post provision. The percentages and day limits are in Settings > Constants (INV_OBSOLETE_PCT_*, INV_AGING_*_DAYS)." />

<x-dl::card title="Obsolete Stock" icon="fa-solid fa-hourglass-end"
    :description="raw(t('Proposed provision').' '.Locale::money($target).' · '.t('Posted before').' '.Locale::money($previous).' · '.t('To post').' '.Locale::money($target - $previous))">
    <x-dl::table :columns="$columns" :rows="ValuationDesk::agingRows($summary)" :source="Src::of('tm2inv.v_rm_onhand', 'tm2inv.v_sfg_onhand', 'tm2inv.v_fg_onhand', 'tm2sys.sys_constant')" />
    <x-dl::action-form action="{{ route('v2.val.obsolete.post') }}">
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="fa-hourglass-half" label="Stock Aging" :href="route('v2.screen', 'inv-stock-aging')" />
            <x-dl::button icon="fa-file-invoice-dollar" label="Post provision" type="submit" :onclick="'return confirm('.json_encode(t('Post the provision journal for the difference to the last posting?')).')'" />
        </x-dl::action-bar>
    </x-dl::action-form>
</x-dl::card>

<x-dl::card title="Provision History" description="Each posting stores the item x bin lines it was based on.">
    <x-dl::table :columns="['date' => ['Date', 'raw' => true], 'value' => ['Stock Value', 'num' => true, 'raw' => true], 'total' => ['Provision', 'num' => true, 'raw' => true],
        'previous' => ['Before', 'num' => true, 'raw' => true], 'adjustment' => ['Posted', 'num' => true, 'raw' => true], 'by' => ['Posted By', 'raw' => true]]"
        :rows="ValuationDesk::provisionHistory()" empty="Nothing posted yet." :source="Src::of('tm2inv.inv_obsolete_provision')" />
</x-dl::card>
