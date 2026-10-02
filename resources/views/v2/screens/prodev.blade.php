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
            <x-dl::table :columns="['part' => 'Part', 'field' => 'What changed', 'old' => 'Before', 'new' => 'After', 'impact' => 'Redone in']"
                :rows="$revChanges->map(fn ($c) => ['part' => $c->part?->part_name ?? t('Document'), 'field' => $c->field_label, 'old' => $c->old_value ?? '—', 'new' => $c->new_value ?? '—', 'impact' => $c->impact])->all()"
                source="{{ Src::of('tm2sdi.iqp_revise_change') }}" />
        </x-dl::card>
    @endif
    @php
        $accProduct = \App\Models\Master\ProdPart::whereKey($partId)->value('product_id');
        $accDoc = $accProduct ? \App\Support\V2\Data\ProductMaster::doc($accProduct) : null;
    @endphp
    {{-- Part & Dieline (rulebook A.17): ProDev berhak mengubah dieline & mockup. Tab ke-2 supaya workbench tampil seperti semula. --}}
    @php $dieline = Prodev::dieline($partId); @endphp
    <x-dl::tabs :tabs="['impo' => ['Imposition', 'fa-table-cells'], 'dieline' => ['Part & Dieline', 'fa-box-open']]">
        <x-slot:impo>
            {{-- Print / Email ada di panel atas workbench (Master Card + ACC Digital), bukan lagi bar terpisah di atas layar. --}}
            <x-mp::prodev.workbench :data="Prodev::workbench($partId, Prodev::listUrl($partId, (int) request('inquiry') ?: null), route('v2.screen', 'procost').'?part='.$partId,
                $accDoc ? route('v2.screen', 'product-acc').'?id='.$accDoc['id'] : null)" />
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
        <x-dl::card :title="'ACC customer — '.raw($accDoc['name'])" description="Record E-mail ACC / Digital ACC (from the CTP plate print sample) / Shape Sample. Its printed document: Digital ACC / Digital Proof.">
            <x-dl::action-bar><x-dl::button variant="secondary" icon="print" label="Print ACC / Proof Digital" :href="route('v2.screen', 'product-acc').'?id='.$accDoc['id']" /></x-dl::action-bar>
            @include('v2.partials.product-acc-forms', ['doc' => $accDoc])
        </x-dl::card>
    @endif
@else
    {{-- Pro-Dev > Manual (Mode #2): part tanpa Inquiry. Part dari Inquiry = grid Pro-Dev > New / Revise (rulebook A.20, C.28). --}}
    <x-dl::grid id="prodev-list" :rows="Prodev::parts()" actions="worklist" :active-key="null" :new-record="null" :open-url="route('v2.screen', 'prodev').'?part={id}'"
        search="Search part or product name/code…" source="{{ Src::of('tm2mst.prod_part', 'tm2mst.prod_product', 'tm2mst.prod_layout_group', 'tm2sdi.iqp_part.prod_part_id (empty = manual)') }}"
        :columns="[
            ['key' => 'code', 'label' => 'Master Card / Customer', 'type' => 'doc', 'party' => 'customer', 'src' => Src::of('tm2mst.prod_part.mastercard_code', 'tm2mst.cust_customer.customer_name')],
            ['key' => 'part_name', 'label' => 'Part', 'src' => Src::of('tm2mst.prod_part.part_name')],
            ['key' => 'product', 'label' => 'Product', 'src' => Src::of('tm2mst.prod_product.product_name')],
            ['key' => 'updated', 'label' => 'Last Updated', 'type' => 'date', 'src' => Src::of('tm2mst.prod_part.updated_at')],
            ['key' => 'status', 'label' => 'Status Layout', 'type' => 'badge', 'tones' => ['CONFIRMED' => 'success', 'DRAFT' => 'warn', t('NOT STARTED', 'badge') => 'neutral'], 'src' => Src::of('tm2mst.prod_layout_group.status').' (latest)'],
        ]"
        :stats="[
            ['label' => 'All'],
            ['label' => t('Not Confirmed'), 'where' => ['status' => ['DRAFT', t('NOT STARTED', 'badge')]], 'tone' => 'warn'],
            ['label' => t('Confirmed'), 'where' => ['status' => 'CONFIRMED'], 'tone' => 'success'],
        ]"
        :filters="['customer' => 'Customer', 'status' => 'Status Layout']"
        :detail="['key' => 'items', 'title' => 'Product', 'columns' => [
            ['key' => 'product', 'label' => 'Product', 'strong' => true],
            ['key' => 'part', 'label' => 'Part'],
            ['key' => 'code', 'label' => 'Part Code'],
            ['key' => 'size', 'label' => 'Size (cm)'],
            ['key' => 'status', 'label' => 'Layout'],
        ]]" />
@endif
