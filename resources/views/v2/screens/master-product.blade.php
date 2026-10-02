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
    $rp = fn ($v) => 'Rp '.\Devlib\Locale::number((float) $v, 0);
    $num = fn ($v) => \Devlib\Locale::number((float) $v, 0);
    $orders = $doc ? ProductMaster::orders($doc['id']) : [];
    $types = $doc ? Sales::dielineTypes() : [];
    $flat = function ($nodes) use (&$flat) {
        $o = [];
        foreach ($nodes as $n) {
            $o[] = $n;
            $o = array_merge($o, $flat($n['children']));
        }

        return $o;
    };
    $lastAcc = fn ($t) => $doc ? collect($doc['accs'])->firstWhere('type', $t) : null;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $doc)
    <x-dl::grid id="master-product" :rows="ProductMaster::rows()" search="Search product name, design no., customer..." :new-record="null" actions="worklist"
        :open-url="route('v2.screen', 'master-product').'?id={id}'" :active-key="null"
        source="{{ Src::of('tm2mst.prod_product', 'tm2mst.prod_part', 'tm2mst.prod_part_artwork', 'tm2mst.prod_product_acc', 'tm2mst.cust_customer') }}"
        :columns="[
            ['key' => 'photo', 'label' => 'Artwork', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Product', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'customer', 'label' => 'Customer (owner)'],
            ['key' => 'parts', 'label' => 'Part', 'type' => 'number'],
            ['key' => 'join', 'label' => 'Join'],
            ['key' => 'revision', 'label' => 'Branch'],
            ['key' => 'acc_digital', 'label' => 'ACC Digital', 'type' => 'badge', 'tones' => ['ADA' => 'success', 'BELUM' => 'warn']],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['ACTIVE' => 'success', 'DRAFT' => 'neutral']],
            ['key' => 'updated', 'label' => t('Changed'), 'type' => 'date', 'visible' => false],
        ]"
        :stats="[
            ['label' => t('All Products')],
            ['label' => 'Official (Quotation WIN)', 'where' => ['status' => 'ACTIVE'], 'tone' => 'success'],
            ['label' => 'Draft (ProDev)', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'],
            ['label' => t('No Digital ACC yet'), 'where' => ['acc_digital' => 'BELUM', 'status' => 'ACTIVE'], 'tone' => 'warn'],
        ]"
        :filters="['customer' => 'Customer', 'join' => 'Join', 'status' => 'Status']" />
    <p class="mp-hint">{{ t('Products are born automatically when a Quotation is WON (CO created) — not created manually. Double-click a row to open.') }}</p>
@else
    <x-dl::card id="mp-doc">
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="back" label="Back" :href="route('v2.screen', 'master-product')" />
            <x-dl::doc-share target="#mp-doc" type="master-product" :doc-id="$doc['id']" :number="$doc['code']" title="Master Product" />
            <span class="mp-chips">
                <x-dl::badge :label="$doc['status'] === 'ACTIVE' ? t('Official · Quotation WIN') : 'DRAFT (ProDev)'" :tone="$doc['status'] === 'ACTIVE' ? 'success' : 'neutral'" />
                @if ($doc['join'])<x-dl::badge :label="$doc['join']" tone="warn" />@endif
                <x-dl::badge :label="$doc['parent'] ? str_replace(':n', $doc['revision_no'], t('Derived Rev.:n')) : t('Parent product')" />
            </span>
        </x-dl::action-bar>
        <x-dl::action-form action="{{ route('v2.product.name', $doc['id']) }}" data-dl-guard>
            <x-dl::form.row cols="3">
                <x-dl::field.text name="product_name" label="Product name" required :value="old('product_name', $doc['name'])" hint="Changed by Sales to match the customer PO; printed on the CO Detail & Delivery Note." :source="Src::of('tm2mst.prod_product.product_name')" />
                <x-dl::field.display label="Customer (product owner)" :value="$doc['customer']" :source="Src::of('tm2mst.prod_product.customer_id', 'tm2mst.cust_customer.customer_name')" />
                <x-dl::field.display label="Code · Design No." :value="$doc['code'].' · '.($doc['design_no'] ?? '—')" :source="Src::of('tm2mst.prod_product.product_code', 'tm2mst.prod_product.design_no')" />
            </x-dl::form.row>
            <x-dl::form.row cols="3">
                <x-dl::field.display label="Finished size (L × W × H)" :value="collect($doc['dims'])->map(fn ($v) => $v === null ? '—' : \Devlib\Locale::decimal($v, 2))->implode(' × ').' cm'" :source="Src::of('tm2mst.prod_product.cm_length', 'cm_width', 'cm_height')" />
                <x-dl::field.display label="Parent (when derived from a revision)" :value="$doc['parent']['label'] ?? '—'" :source="Src::of('tm2mst.prod_product.parent_product_id')" />
                <x-dl::field.display label="Last order" :value="$orders ? ($orders[0]['co'] ?? $orders[0]['quotation']).' · '.$num($orders[0]['qty']).' pcs · '.$orders[0]['date'] : '—'" :source="Src::of('tm2sdi.co_order_item.product_id')" />
            </x-dl::form.row>
            <x-dl::action-bar><x-dl::button preset="save" /></x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::card>

    <x-mp::sales.product-tree :tree="$doc['tree']" :product="$doc['name']">
        <div data-pm-panel="product">
            <x-dl::card :title="raw($doc['name'])" description="Product · all parts born from the Inquiry when the Quotation is WON">
                <div class="mp-thumbs">
                    @foreach ($doc['components'] as $c)
                        <x-mp::image-thumb :label="$c['name']" :src="$c['artwork']" empty="no artwork yet" />
                    @endforeach
                </div>
                @if ($doc['components'])
                    <x-mp::box3d :dims="$doc['dims']" :front="$doc['components'][0]['artwork']" :back="$doc['components'][1]['artwork'] ?? null" class="mp-mt" />
                @endif
            </x-dl::card>
        </div>
        @foreach ($flat($doc['tree']) as $n)
            <div data-pm-panel="{{ $n['id'] }}" hidden>
                <x-dl::card :title="raw($n['name'])" :description="$n['kind'] === 'ASSEMBLY' ? 'Assembly · '.raw(($n['join'] ?? t('join not filled yet')).' · '.count($n['children'])).' components' : 'Component · '.raw(($n['code'] ?? '').($n['flat'] ? ' · '.t('flat size').' '.$n['flat'] : ''))">
                    @if ($n['kind'] === 'ASSEMBLY')
                        <x-dl::alert tone="warn" :message="str_contains((string) $n['join_code'], 'DOUBLE') ? 'Glued on 2 sides — once joined it can NOT be unfolded again (affects packing & shipping volume).' : 'Glued on 1 side — can still be unfolded.'" />
                        <div class="mp-thumbs">@foreach ($n['children'] as $c)<x-mp::image-thumb :label="$c['name']" :src="$c['artwork']" empty="no artwork yet" />@endforeach</div>
                        <x-mp::box3d :dims="$doc['dims']" :front="$n['children'][0]['artwork'] ?? null" :back="$n['children'][1]['artwork'] ?? null" class="mp-mt" />
                        <x-dl::divider label="Assembly BOP" />
                        <ol class="mp-steps">@forelse ($n['bop'] as $s)<li><span><b>{{ $s['name'] }}</b>@if ($s['note'])<small>{{ $s['note'] }}</small>@endif</span></li>@empty<li><span>{{ t('No BOP yet.') }}</span></li>@endforelse</ol>
                    @else
                        <x-dl::tabs :tabs="['view' => 'Dieline & Mockup', 'bom' => 'Material & BOM', 'bop' => 'BOP', 'spec' => 'Colour & Finishing']">
                            <x-slot:view><x-mp::sales.part-visual :part="$n" :types="$types" /></x-slot:view>
                            <x-slot:bom>
                                <x-dl::table :columns="['seq' => '#', 'role' => 'Role', 'jenis' => 'Type / Grammage', 'brand' => 'Brand (reference)']" :rows="array_map(fn ($m) => $m + ['brand' => $m['brand'] ?? '—'], $n['materials'])"
                                    empty="No material yet." source="{{ Src::of('tm2mst.prod_part_material', 'tm2mst.mat_category', 'tm2mst.mat_raw.brand') }}" />
                                <x-dl::divider label="Ink" />
                                <div class="mp-chips">@forelse ($n['inks'] as $k)<x-dl::badge :label="'Unit '.$k['unit'].' · '.$k['ink']" />@empty<span class="mp-hint">{{ t('No ink yet.') }}</span>@endforelse</div>
                            </x-slot:bom>
                            <x-slot:bop>
                                <ol class="mp-steps">@forelse ($n['bop'] as $s)<li><span><b>{{ $s['name'] }}</b><small>{{ trim(($s['machine'] ? t('Machine').' '.$s['machine'].' · ' : '').($s['note'] ?? ''), ' ·') }}</small></span></li>@empty<li><span>{{ t('No BOP yet.') }}</span></li>@endforelse</ol>
                            </x-slot:bop>
                            <x-slot:spec>
                                <x-dl::form.row cols="3">
                                    <x-dl::field.display label="Colour notation" :value="$n['color'] ?? '—'" :source="Src::of('tm2mst.prod_part.cfg_color')" />
                                    <x-dl::field.display label="Ink" :value="$n['ink'] ?? '—'" :source="Src::of('tm2mst.prod_part.cfg_ink')" />
                                    <x-dl::field.display label="Flat size" :value="$n['flat'] ?? '—'" :source="Src::of('tm2mst.prod_part.flat_w', 'flat_h')" />
                                </x-dl::form.row>
                                <div class="mp-chips">@forelse ($n['finishings'] as $f)<x-dl::badge :label="$f" tone="info" />@empty<span class="mp-hint">{{ t('No finishing.') }}</span>@endforelse</div>
                            </x-slot:spec>
                        </x-dl::tabs>
                    @endif
                </x-dl::card>
            </div>
        @endforeach
    </x-mp::sales.product-tree>

    <x-dl::card>
        <x-dl::tabs :tabs="['acc' => 'ACC Customer', 'orders' => 'Order & Selling Price History', 'branch' => 'Revision Branches']">
            <x-slot:acc>
                <x-dl::form.row cols="3">
                    @foreach (ProdProductAcc::TYPES as $t => $label)
                        <x-dl::field.display :label="$label" :value="$lastAcc($t) ? $lastAcc($t)['at'].' · '.$lastAcc($t)['by'].' ('.$lastAcc($t)['channel'].')' : ($t === 'DIGITAL' ? t('NOT YET — required before Printing') : t('not recorded yet'))" :source="Src::of('tm2mst.prod_product_acc')" />
                    @endforeach
                </x-dl::form.row>
                <p class="mp-hint">{{ t('ACC is recorded & the Digital Proof is printed from the ProDev form (special Print ACC / Proof Digital button).') }}</p>
            </x-slot:acc>
            <x-slot:orders>
                <x-dl::table :columns="['date' => 'Date', 'quotation' => 'Quotation', 'co' => 'Customer Order', 'qty' => 'Qty (pcs)', 'price' => 'Selling price /pcs', 'result' => 'Result']"
                    :rows="array_map(fn ($o) => ['date' => $o['date'], 'quotation' => $o['quotation'] ?? '—', 'co' => $o['co'] ?? '—', 'qty' => $num($o['qty']), 'price' => $rp($o['price']), 'result' => ['WIN' => 'WIN · '.t('sold'), 'LOST' => 'LOST', 'OPEN' => t('Still open')][$o['result']]], $orders)"
                    empty="No order yet." source="{{ Src::of('tm2sdi.quo_quotation_item', 'tm2sdi.co_order_item.product_id') }}" />
            </x-slot:orders>
            <x-slot:branch>
                <x-dl::table :columns="['rel' => 'Relation', 'label' => 'Product', 'status' => 'Status']"
                    :rows="array_merge($doc['parent'] ? [['rel' => t('Parent'), 'label' => $doc['parent']['label'], 'status' => '']] : [], [['rel' => t('This product'), 'label' => $doc['name'].' · '.$doc['design_no'], 'status' => $doc['status']]],
                        array_map(fn ($r) => ['rel' => t('Derived'), 'label' => $r['label'], 'status' => $r['status']], $doc['revisions']))"
                    source="{{ Src::of('tm2mst.prod_product.parent_product_id') }}" />
                <p class="mp-hint">{{ t('Every Revision opens a new branch (derived product); the old product stays intact.') }}</p>
            </x-slot:branch>
        </x-dl::tabs>
    </x-dl::card>
@endif
