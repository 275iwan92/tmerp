{{--
 REMARK
 Tujuan       : Master Product: tanpa id = daftar produk (lahir otomatis dari Quotation WIN, tidak ada New Record manual); dengan id = halaman satu produk (identitas, rantai dokumen, pohon rakitan, ACC, riwayat order dan harga, cabang revisi).
 Sumber hulu  : App\Support\V2\Data\ProductMaster::doc dan ProductMaster::orders, Sales::dielineTypes; model ProdProductAcc; daftar = grid Sales\ProductMasterListGrid (tm2mst.prod_product).
 Input        : Query id (produk); sesi v2_flash dan errors.
 Output       : Grid lewat _dbgrid atau objek x-mp::sales.product-master; posting nama produk ke route v2.product.name.
 Dipakai oleh : Route v2.screen slug master-product (App\Support\V2\Screens, menu sys_app_menu target v2:master-product) di menu Master Data dan Pro-Dev; tautan dari inquiry-revise dan inquiry-recalc
 Status       : Aktif (flow docs/flow_product_master.md)
--}}
@php
    use App\Models\Master\ProdProductAcc;
    use App\Support\V2\Data\ProductMaster;
    use App\Support\V2\Data\Sales;
    use App\Support\V2\Src;

    $doc = request('id') ? ProductMaster::doc((int) request('id')) : null;
    abort_if(request('id') && ! $doc, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $doc)
    {{-- Daftar = objek grid App\Support\V2\Grid\Sales\ProductMasterListGrid (DocListGrid), tampil lewat view generik _dbgrid. --}}
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Sales\ProductMasterListGrid()]])
    <x-dl::hint note>@t('A product is born automatically when a Quotation is WIN (the CO is created) — it is not created manually.') @t('Double-click a row to open it.')</x-dl::hint>
@else
    {{-- Halaman satu product = objek form khusus x-mp::sales.product-master (MockupParts). --}}
    <x-mp::sales.product-master :doc="$doc" :orders="ProductMaster::orders($doc['id'])" :types="Sales::dielineTypes()" :acc-types="ProdProductAcc::TYPES"
        :home="route('v2.screen', 'master-product')" :name-action="route('v2.product.name', $doc['id'])" :name-value="old('product_name')" />
@endif
