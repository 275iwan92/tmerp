{{--
 REMARK
 Tujuan       : ACC Digital / Proof Digital = DOKUMEN CETAK (user 2026-09-29). Tanpa ?id = grid product + status ACC; ?id = lembar Proof Digital.
 Sumber hulu  : App\Support\V2\Data\ProductMaster (rows, doc, lineage); pencatatan ACC ada di ProDev (partial v2.partials.product-acc-forms).
 Input        : ?id= (id produk).
 Output       : Grid produk, atau objek x-mp::sales.proof-sheet (format Proof Digital DS003637) + tombol Back / Master Product / Share.
 Dipakai oleh : Rute v2.screen product-acc (menu Master Data > Product > ACC).
 Status       : Aktif (zona beku dibuka Jo 13 Okt: hanya markup dipindah ke objek, isi dokumen tidak berubah).
--}}
@php
    use App\Support\V2\Data\ProductMaster;
    use App\Support\V2\Src;

    $doc = request('id') ? ProductMaster::doc((int) request('id')) : null;
    abort_if(request('id') && ! $doc, 404);
    $inks = $doc ? collect($doc['components'])->flatMap(fn ($c) => array_column($c['inks'], 'ink'))->unique()->values()->all() : [];
    $digital = $doc ? collect($doc['accs'])->firstWhere('type', 'DIGITAL') : null;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $doc)
    <x-dl::grid id="product-acc" :rows="ProductMaster::rows()" search="Search product, design no., customer..." :new-record="null" actions="worklist" :active-key="null"
        :open-url="route('v2.screen', 'product-acc').'?id={id}'" source="{{ Src::of('tm2mst.prod_product', 'tm2mst.prod_product_acc') }}"
        :columns="[
            ['key' => 'photo', 'label' => 'Artwork', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Product', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'acc_digital', 'label' => 'Digital ACC', 'type' => 'badge', 'tones' => [t('AVAILABLE') => 'success', t('NOT YET') => 'warn']],
            ['key' => 'status', 'label' => 'Product status', 'type' => 'badge', 'tones' => ['ACTIVE' => 'success', 'DRAFT' => 'neutral']],
        ]"
        :stats="[['label' => t('All')], ['label' => t('No Digital ACC yet'), 'where' => ['acc_digital' => t('NOT YET')], 'tone' => 'warn'], ['label' => t('Digital ACC done'), 'where' => ['acc_digital' => t('AVAILABLE')], 'tone' => 'success']]" />
@else
    <x-dl::action-bar>
        <x-dl::button variant="secondary" icon="back" label="Back" :href="route('v2.screen', 'product-acc')" />
        <x-dl::button variant="secondary" icon="view" label="Master Product" :href="route('v2.screen', 'master-product').'?id='.$doc['id']" />
        <x-dl::doc-share target=".mp-proof" type="product-acc" :doc-id="$doc['id']" :number="$doc['code']" title="ACC / Proof Digital" orientation="landscape" />
    </x-dl::action-bar>

    <x-mp::sales.proof-sheet :doc="$doc" :revisions="ProductMaster::lineage($doc['id'])" :digital="$digital" :inks="$inks"
        :provenance="Src::of('tm2mst.prod_product', 'tm2mst.prod_part_artwork', 'tm2mst.prod_product_acc')" />

    <x-dl::alert tone="info" message="Printed document. Customer ACC (date, approver, evidence) is recorded on the ProDev screen." />
@endif
