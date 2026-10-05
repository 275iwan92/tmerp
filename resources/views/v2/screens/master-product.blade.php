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
    <x-dl::grid id="master-product" :rows="ProductMaster::rows()" search="Search product name, design no., customer..." :new-record="null" actions="worklist"
        :open-url="route('v2.screen', 'master-product').'?id={id}'" :active-key="null"
        source="{{ Src::of('tm2mst.prod_product', 'tm2mst.prod_part', 'tm2mst.prod_part_artwork', 'tm2mst.prod_product_acc', 'tm2mst.cust_customer') }}"
        :columns="[
            ['key' => 'photo', 'label' => 'Artwork', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Product', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'customer', 'label' => 'Customer (owner)'],
            ['key' => 'parts', 'label' => 'Part', 'type' => 'number'],
            ['key' => 'join', 'label' => 'Joint'],
            ['key' => 'revision', 'label' => 'Branch'],
            ['key' => 'acc_digital', 'label' => 'Digital ACC', 'type' => 'badge', 'tones' => [t('AVAILABLE') => 'success', t('NOT YET') => 'warn']],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['ACTIVE' => 'success', 'DRAFT' => 'neutral']],
            ['key' => 'updated', 'label' => t('Changed'), 'type' => 'date', 'visible' => false],
        ]"
        :stats="[
            ['label' => t('All Products')],
            ['label' => 'Official (Quotation WIN)', 'where' => ['status' => 'ACTIVE'], 'tone' => 'success'],
            ['label' => 'Draft (ProDev)', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'],
            ['label' => t('No Digital ACC yet'), 'where' => ['acc_digital' => t('NOT YET'), 'status' => 'ACTIVE'], 'tone' => 'warn'],
        ]"
        :filters="['customer' => 'Customer', 'join' => 'Joint', 'status' => 'Status']" />
    <p class="mp-hint">@t('A product is born automatically when a Quotation is WIN (the CO is created) — it is not created manually.') @t('Double-click a row to open it.')</p>
@else
    {{-- Halaman satu product = objek form khusus x-mp::sales.product-master (MockupParts). --}}
    <x-mp::sales.product-master :doc="$doc" :orders="ProductMaster::orders($doc['id'])" :types="Sales::dielineTypes()" :acc-types="ProdProductAcc::TYPES"
        :home="route('v2.screen', 'master-product')" :name-action="route('v2.product.name', $doc['id'])" :name-value="old('product_name')" />
@endif
