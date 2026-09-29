{{-- Pro-Dev. Flow & data: tmerp pages/production/prodev-list.blade.php (daftar part) -> prodev.blade.php (workbench per part).
     Tanpa ?part = daftar; ?part=<id> = Imposition Plano Optimizer part itu. Confirm -> Product Costing. --}}
@php
    use App\Support\V2\Data\Prodev;
    use App\Support\V2\Src;

    $partId = request('part') ? Prodev::resolve((string) request('part')) : null;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if ($partId)
    @php
        $revChanges = \App\Models\Sdi\IqpReviseChange::where('inquiry_id', \App\Models\Sdi\IqpPart::where('prod_part_id', $partId)->value('inquiry_id'))->with('part:id_part,part_name')->get();
    @endphp
    @if ($revChanges->isNotEmpty())
        <x-dl::card title="Revisi: yang berubah dari product induk" description="Dari Inquiry Revisi (iqp_revise_change) — ulangi yang ditandai ProDev.">
            <x-dl::table :columns="['part' => 'Part', 'field' => 'Yang berubah', 'old' => 'Sebelum', 'new' => 'Sesudah', 'impact' => 'Diulang di']"
                :rows="$revChanges->map(fn ($c) => ['part' => $c->part?->part_name ?? 'Dokumen', 'field' => $c->field_label, 'old' => $c->old_value ?? '—', 'new' => $c->new_value ?? '—', 'impact' => $c->impact])->all()"
                source="{{ Src::of('tm2sdi.iqp_revise_change') }}" />
        </x-dl::card>
    @endif
    @php
        $accProduct = \App\Models\Master\ProdPart::whereKey($partId)->value('product_id');
        $accDoc = $accProduct ? \App\Support\V2\Data\ProductMaster::doc($accProduct) : null;
    @endphp
    <x-mp::prodev.workbench :data="Prodev::workbench($partId, route('v2.screen', 'prodev'), route('v2.screen', 'procost').'?part='.$partId)" />
    @if ($accDoc)
        <x-dl::card :title="'ACC customer — '.$accDoc['name']" description="Catat ACC E-mail / ACC Digital (dari sample cetak plat CTP) / Sample Bentuk. Dokumen cetaknya: ACC Digital / Proof Digital.">
            <x-dl::action-bar><x-dl::button variant="secondary" icon="print" label="Print ACC / Proof Digital" :href="route('v2.screen', 'product-acc').'?id='.$accDoc['id']" /></x-dl::action-bar>
            @include('v2.partials.product-acc-forms', ['doc' => $accDoc])
        </x-dl::card>
    @endif
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
