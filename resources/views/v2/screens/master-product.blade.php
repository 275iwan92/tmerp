{{-- Master Product (docs/flow_product_master.md, mockup disetujui user 2026-09-29).
     Tanpa ?id = Grid for Master (entry): product lahir dari Quotation WIN, jadi tidak ada + New Record.
     ?id = detail: identitas (nama diubah Sales), rantai dokumen, pohon rakitan + panel part, ACC, riwayat order & harga, cabang revisi. --}}
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
    <p class="mp-hint">@t('A product is born automatically when a Quotation is WIN (the CO is created) — it is not created manually.') @t('Double-click a row to open it.')</p>
@else
    {{-- Halaman satu product = objek form khusus x-mp::sales.product-master (MockupParts). --}}
    <x-mp::sales.product-master :doc="$doc" :orders="ProductMaster::orders($doc['id'])" :types="Sales::dielineTypes()" :acc-types="ProdProductAcc::TYPES"
        :home="route('v2.screen', 'master-product')" :name-action="route('v2.product.name', $doc['id'])" :name-value="old('product_name')" />
@endif
