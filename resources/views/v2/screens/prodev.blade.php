{{-- Pro-Dev. Flow & data: tmerp pages/production/prodev-list.blade.php (daftar part) -> prodev.blade.php (workbench per part).
     Tanpa ?part = daftar; ?part=<id> = tab Imposition (Imposition Plano Optimizer part itu) + tab Part & Dieline. Confirm -> Product Costing. --}}
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
        <x-dl::card title="Revision: what changed from the parent product" description="From Inquiry Revision (iqp_revise_change) — redo what ProDev marked.">
            <x-dl::table :columns="['part' => 'Part', 'field' => 'Yang berubah', 'old' => 'Sebelum', 'new' => 'Sesudah', 'impact' => 'Diulang di']"
                :rows="$revChanges->map(fn ($c) => ['part' => $c->part?->part_name ?? 'Dokumen', 'field' => $c->field_label, 'old' => $c->old_value ?? '—', 'new' => $c->new_value ?? '—', 'impact' => $c->impact])->all()"
                source="{{ Src::of('tm2sdi.iqp_revise_change') }}" />
        </x-dl::card>
    @endif
    @php
        $accProduct = \App\Models\Master\ProdPart::whereKey($partId)->value('product_id');
        $accDoc = $accProduct ? \App\Support\V2\Data\ProductMaster::doc($accProduct) : null;
    @endphp
    <x-dl::action-bar>
        <x-dl::doc-share target="[data-mp-prodev]" type="prodev" :doc-id="$partId" :number="\App\Models\Master\ProdPart::whereKey($partId)->value('mastercard_code')" title="Pro-Dev Layout" orientation="landscape" />
    </x-dl::action-bar>
    {{-- Part & Dieline (rulebook A.17): ProDev berhak mengubah dieline & mockup. Tab ke-2 supaya workbench tampil seperti semula. --}}
    @php $dieline = Prodev::dieline($partId); @endphp
    <x-dl::tabs :tabs="['impo' => ['Imposition', 'fa-table-cells'], 'dieline' => ['Part & Dieline', 'fa-box-open']]">
        <x-slot:impo>
            <x-mp::prodev.workbench :data="Prodev::workbench($partId, route('v2.screen', 'prodev'), route('v2.screen', 'procost').'?part='.$partId)" />
        </x-slot:impo>
        <x-slot:dieline>
            @if ($dieline)
                <x-mp::prodev.part-dieline :part="$dieline" :action="route('v2.prodev.dieline', $partId)" />
            @else
                <x-dl::alert tone="info" :message="t('This part has no dieline type yet (assembly, or a manual part without a Product Type), so there is no dieline to change here.')" />
            @endif
        </x-slot:dieline>
    </x-dl::tabs>
    @if ($accDoc)
        <x-dl::card :title="'ACC customer — '.$accDoc['name']" description="Record E-mail ACC / Digital ACC (from the CTP plate print sample) / Shape Sample. Its printed document: Digital ACC / Digital Proof.">
            <x-dl::action-bar><x-dl::button variant="secondary" icon="print" label="Print ACC / Proof Digital" :href="route('v2.screen', 'product-acc').'?id='.$accDoc['id']" /></x-dl::action-bar>
            @include('v2.partials.product-acc-forms', ['doc' => $accDoc])
        </x-dl::card>
    @endif
@else
    <x-dl::grid id="prodev-list" :rows="Prodev::parts()" actions="worklist" :active-key="null" :new-record="null" :open-url="route('v2.screen', 'prodev').'?part={id}'"
        search="Search part or product name/code…" source="{{ Src::of('tm2mst.prod_part', 'tm2mst.prod_product', 'tm2mst.prod_layout_group', 'tm2sdi.iqp_part.prod_part_id') }}"
        :columns="[
            ['key' => 'part_name', 'label' => 'Part', 'type' => 'identity', 'code' => 'code', 'src' => Src::of('tm2mst.prod_part.part_name', 'tm2mst.prod_part.mastercard_code')],
            ['key' => 'product', 'label' => 'Produk', 'src' => Src::of('tm2mst.prod_product.product_name')],
            ['key' => 'origin', 'label' => 'Asal', 'type' => 'badge', 'tones' => ['INQUIRY' => 'info', 'MANUAL' => 'violet'], 'src' => Src::of('tm2sdi.iqp_part.prod_part_id').' (ada = dari Inquiry, Mode #1)'],
            ['key' => 'status', 'label' => 'Status Layout', 'type' => 'badge', 'tones' => ['CONFIRMED' => 'success', 'DRAFT' => 'warn', 'BELUM MULAI' => 'neutral'], 'src' => Src::of('tm2mst.prod_layout_group.status').' (terbaru)'],
        ]"
        :stats="[
            ['label' => 'Total Part', 'where' => [], 'tone' => 'accent'],
            ['label' => t('Confirmed'), 'where' => ['status' => 'CONFIRMED'], 'tone' => 'success'],
            ['label' => t('Not Confirmed'), 'where' => ['status' => ['DRAFT', 'BELUM MULAI']], 'tone' => 'warn'],
        ]"
        :filters="['origin' => 'Asal', 'status' => 'Status Layout']" />
@endif
