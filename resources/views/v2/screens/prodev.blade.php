{{-- Pro-Dev. Flow & data: tmerp pages/production/prodev-list.blade.php (daftar part) -> prodev.blade.php (workbench per part).
     Tanpa ?part = daftar; ?part=<id> = Imposition Plano Optimizer part itu. Confirm -> Product Costing. --}}
@php
    use App\Support\V2\Data\Prodev;
    use App\Support\V2\Src;

    $partId = request('part') ? Prodev::resolve((string) request('part')) : null;
@endphp

@if ($partId)
    <x-mp::prodev.workbench :data="Prodev::workbench($partId, route('v2.screen', 'prodev'), route('v2.screen', 'procost').'?part='.$partId)" />
@else
    <x-dl::grid id="prodev-list" :rows="Prodev::parts()" actions="worklist" :active-key="null" :new-record="null" :open-url="route('v2.screen', 'prodev').'?part={id}'"
        search="Cari nama/kode part atau produk…" source="{{ Src::of('tm2mst.prod_part', 'tm2mst.prod_product', 'tm2mst.prod_layout_group', 'tm2sdi.iqp_part.prod_part_id') }}"
        :columns="[
            ['key' => 'part_name', 'label' => 'Part', 'type' => 'identity', 'code' => 'code', 'src' => Src::of('tm2mst.prod_part.part_name', 'tm2mst.prod_part.mastercard_code')],
            ['key' => 'product', 'label' => 'Produk', 'src' => Src::of('tm2mst.prod_product.product_name')],
            ['key' => 'origin', 'label' => 'Asal', 'type' => 'badge', 'tones' => ['INQUIRY' => 'info', 'MANUAL' => 'violet'], 'src' => Src::of('tm2sdi.iqp_part.prod_part_id').' (ada = dari Inquiry, Mode #1)'],
            ['key' => 'status', 'label' => 'Status Layout', 'type' => 'badge', 'tones' => ['CONFIRMED' => 'success', 'DRAFT' => 'warn', 'BELUM MULAI' => 'neutral'], 'src' => Src::of('tm2mst.prod_layout_group.status').' (terbaru)'],
        ]"
        :stats="[
            ['label' => 'Total Part', 'where' => [], 'tone' => 'accent'],
            ['label' => 'Sudah Confirm', 'where' => ['status' => 'CONFIRMED'], 'tone' => 'success'],
            ['label' => 'Belum Confirm', 'where' => ['status' => ['DRAFT', 'BELUM MULAI']], 'tone' => 'warn'],
        ]"
        :filters="['origin' => 'Asal', 'status' => 'Status Layout']" />
@endif
