{{--
 REMARK
 Tujuan       : Warehouse & Inventory: nilai stok bahan baku, WIP (SFG) dan barang jadi, bahan tidak bergerak > 90 hari, komposisi nilai stok, nilai RM per gudang dan bahan baku bernilai tertinggi.
 Sumber hulu  : App\Support\V2\Data\Mgmt\WarehouseReport (view v_rm_onhand, v_fg_onhand, v_sfg_onhand, mat_raw); pindahan layar v1 pages::report.warehouse.
 Input        : Tidak ada (angka berjalan).
 Output       : x-dl::kpi, x-dl::chart donat, x-dl::progress, x-dl::table.
 Dipakai oleh : Layar v2 slug mgmt-warehouse lewat rute v2.screen (menu Management Reports > Warehouse & Inventory, sys_app_menu MGMT_WAREHOUSE)
 Status       : Aktif
--}}
@php
    use App\Support\V2\Data\Mgmt\WarehouseReport;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $r = new WarehouseReport;
    $src = Src::of('tm2inv.v_rm_onhand', 'tm2inv.v_fg_onhand', 'tm2inv.v_sfg_onhand', 'tm2mst.mat_raw');
    $v = $r->stockValue();
    $sku = $r->skuCounts();
@endphp

<x-dl::card title="Warehouse & Inventory Report" description="Value of raw material, WIP and finished goods stock." />

<x-dl::kpi-row>
    <x-dl::kpi label="Raw Material Stock Value" :value="$v['rm']" format="money" icon="fa-boxes-stacked" tone="accent" :note="tf(':n active SKUs', ['n' => Locale::number($sku['rm'])])" />
    <x-dl::kpi label="WIP Stock Value (SFG)" :value="$v['sfg']" format="money" icon="fa-layer-group" tone="violet" note="Semi Finished Goods" />
    <x-dl::kpi label="Finished Goods Stock Value" :value="$v['fg']" format="money" icon="fa-box-open" tone="success" :note="tf(':n active SKUs', ['n' => Locale::number($sku['fg'])])" />
    <x-dl::kpi label="Non-moving Materials" :value="$r->staleStock()" icon="fa-triangle-exclamation" tone="danger" note="> 90 days without movement" />
</x-dl::kpi-row>

<x-dl::chart type="donut" title="Stock Value Composition" format="money" :labels="[t('Raw Material'), t('WIP'), t('Finished Goods')]" :series="[['name' => t('Value'), 'data' => [$v['rm'], $v['sfg'], $v['fg']]]]" :source="$src" />

<x-dl::card title="Raw Material Stock Value per Warehouse">
    @forelse ($r->warehouseSplit() as $w)
        <x-dl::progress :value="$v['rm'] > 0 ? round($w['value'] / $v['rm'] * 100, 1) : 0" :label="raw($w['name'].' · '.Locale::money($w['value']))" />
    @empty
        <x-dl::hint>@t('No raw material stock yet.')</x-dl::hint>
    @endforelse
</x-dl::card>

<x-dl::card title="Highest-value Raw Materials">
    <x-dl::table :columns="['name' => 'Material', 'qty' => ['Qty On Hand', 'num' => true], 'value' => ['Value', 'num' => true]]" :rows="$r->topRawMaterialRows()" empty="No stock data yet." :source="$src" />
</x-dl::card>
