{{-- Tampilan objek x-mp::sales.product-master (MockupParts\Components\Sales\ProductMaster). Markup sama dengan layar
     asalnya; pembantu ($rp, $num, $flat, $lastAcc, $src) = method objek. --}}
@php
    use Devlib\Lang;
    use Devlib\Locale;
@endphp
<div {{ $attributes->class('mp-flat') }} {!! $dl() !!}>
<x-dl::card id="mp-doc">
    <x-dl::action-bar>
        <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
        <x-dl::doc-share target="#mp-doc" type="master-product" :doc-id="$doc['id']" :number="$doc['code']" title="Master Product" />
        <x-dl::chips as="span">
            <x-dl::badge :label="$doc['status'] === 'ACTIVE' ? 'Official · Quotation WIN' : 'DRAFT (ProDev)'" :tone="$doc['status'] === 'ACTIVE' ? 'success' : 'neutral'" />
            @if ($doc['join'])<x-dl::badge :label="$doc['join']" tone="warn" />@endif
            <x-dl::badge :label="$doc['parent'] ? 'Derived Rev.'.$doc['revision_no'] : 'Parent product'" />
        </x-dl::chips>
    </x-dl::action-bar>
    <x-dl::action-form action="{{ $nameAction }}" data-dl-guard>
        <x-dl::form.row cols="3">
            <x-dl::field.text name="product_name" label="Product name" required :value="$nameValue ?? $doc['name']" hint="Changed by Sales to match the customer PO; printed on the CO Detail & Delivery Note." :source="$src('tm2mst.prod_product.product_name')" />
            <x-dl::field.display label="Customer (product owner)" :value="$doc['customer']" :source="$src('tm2mst.prod_product.customer_id', 'tm2mst.cust_customer.customer_name')" />
            <x-dl::field.display label="Code · Design No." :value="$doc['code'].' · '.($doc['design_no'] ?? '—')" :source="$src('tm2mst.prod_product.product_code', 'tm2mst.prod_product.design_no')" />
        </x-dl::form.row>
        <x-dl::form.row cols="3">
            <x-dl::field.display label="Finished size (L × W × H)" :value="collect($doc['dims'])->map(fn ($v) => $v === null ? '—' : Locale::decimal($v, 2))->implode(' × ').' cm'" :source="$src('tm2mst.prod_product.cm_length', 'cm_width', 'cm_height')" />
            <x-dl::field.display label="Parent (when derived from a revision)" :value="$doc['parent']['label'] ?? '—'" :source="$src('tm2mst.prod_product.parent_product_id')" />
            <x-dl::field.display label="Last order" :value="$orders ? ($orders[0]['co'] ?? $orders[0]['quotation']).' · '.$num($orders[0]['qty']).' pcs · '.$orders[0]['date'] : '—'" :source="$src('tm2sdi.co_order_item.product_id')" />
        </x-dl::form.row>
        <x-dl::action-bar><x-dl::button preset="save" /></x-dl::action-bar>
    </x-dl::action-form>
</x-dl::card>

<x-mp::sales.product-tree :tree="$doc['tree']" :product="$doc['name']">
    <div data-pm-panel="product">
        <x-dl::card :title="Lang::raw($doc['name'])" description="Product · all parts born from the Inquiry when the Quotation is WON">
            <div class="mp-thumbs">
                @foreach ($doc['components'] as $c)
                    <x-mp::image-thumb :label="Lang::raw($c['name'])" :src="$c['artwork']" empty="no artwork yet" />
                @endforeach
            </div>
            @if ($doc['components'])
                <x-mp::box3d :dims="$doc['dims']" :front="$doc['components'][0]['artwork']" :back="$doc['components'][1]['artwork'] ?? null" class="mp-mt" />
            @endif
        </x-dl::card>
    </div>
    @foreach ($flat() as $n)
        <div data-pm-panel="{{ $n['id'] }}" hidden>
            <x-dl::card :title="Lang::raw($n['name'])" :description="$n['kind'] === 'ASSEMBLY' ? 'Assembly · '.Lang::raw(($n['join'] ?? Lang::text('joint not filled in yet')).' · '.count($n['children'])).' components' : 'Component · '.Lang::raw(($n['code'] ?? '').($n['flat'] ? ' · '.Lang::text('flat size').' '.$n['flat'] : ''))">
                @if ($n['kind'] === 'ASSEMBLY')
                    <x-dl::alert tone="warn" :message="str_contains((string) $n['join_code'], 'DOUBLE') ? 'Glued on 2 sides — once joined it can NOT be laid flat again (affects packing & shipping volume).' : 'Glued on 1 side — it can still be laid flat.'" />
                    <div class="mp-thumbs">@foreach ($n['children'] as $c)<x-mp::image-thumb :label="Lang::raw($c['name'])" :src="$c['artwork']" empty="no artwork yet" />@endforeach</div>
                    <x-mp::box3d :dims="$doc['dims']" :front="$n['children'][0]['artwork'] ?? null" :back="$n['children'][1]['artwork'] ?? null" class="mp-mt" />
                    <x-dl::divider label="Assembly BOP" />
                    <ol class="mp-steps">@forelse ($n['bop'] as $s)<li><span><b>{{ $s['name'] }}</b>@if ($s['note'])<small>{{ $s['note'] }}</small>@endif</span></li>@empty<li><span>{{ Lang::text('No BOP yet.') }}</span></li>@endforelse</ol>
                @else
                    <x-dl::tabs :tabs="['view' => 'Dieline & Mockup', 'bom' => 'Material & BOM', 'bop' => 'BOP', 'spec' => 'Colour & Finishing']">
                        <x-slot:view><x-mp::sales.part-visual :part="$n" :types="$types" /></x-slot:view>
                        <x-slot:bom>
                            <x-dl::table :columns="['seq' => '#', 'role' => 'Role', 'jenis' => 'Type / Grammage', 'brand' => 'Brand (reference)']" :rows="array_map(fn ($m) => $m + ['brand' => $m['brand'] ?? '—'], $n['materials'])"
                                empty="No material yet." :source="$src('tm2mst.prod_part_material', 'tm2mst.mat_category', 'tm2mst.mat_raw.brand')" />
                            <x-dl::divider label="Ink" />
                            <x-dl::chips>@forelse ($n['inks'] as $k)<x-dl::badge :label="'Unit '.$k['unit'].' · '.$k['ink']" />@empty<x-dl::hint note as="span">{{ Lang::text('No ink yet.') }}</x-dl::hint>@endforelse</x-dl::chips>
                        </x-slot:bom>
                        <x-slot:bop>
                            <ol class="mp-steps">@forelse ($n['bop'] as $s)<li><span><b>{{ $s['name'] }}</b><small>{{ trim(($s['machine'] ? Lang::text('Machine').' '.$s['machine'].' · ' : '').($s['note'] ?? ''), ' ·') }}</small></span></li>@empty<li><span>{{ Lang::text('No BOP yet.') }}</span></li>@endforelse</ol>
                        </x-slot:bop>
                        <x-slot:spec>
                            <x-dl::form.row cols="3">
                                <x-dl::field.display label="Colour notation" :value="$n['color'] ?? '—'" :source="$src('tm2mst.prod_part.cfg_color')" />
                                <x-dl::field.display label="Ink" :value="$n['ink'] ?? '—'" :source="$src('tm2mst.prod_part.cfg_ink')" />
                                <x-dl::field.display label="Flat size" :value="$n['flat'] ?? '—'" :source="$src('tm2mst.prod_part.flat_w', 'flat_h')" />
                            </x-dl::form.row>
                            <x-dl::chips>@forelse ($n['finishings'] as $f)<x-dl::badge :label="$f" tone="info" />@empty<x-dl::hint note as="span">{{ Lang::text('No finishing.') }}</x-dl::hint>@endforelse</x-dl::chips>
                        </x-slot:spec>
                    </x-dl::tabs>
                @endif
            </x-dl::card>
        </div>
    @endforeach
</x-mp::sales.product-tree>

<x-dl::card>
    <x-dl::tabs :tabs="['acc' => 'Customer ACC', 'orders' => 'Order & Selling Price History', 'branch' => 'Revision Branches']">
        <x-slot:acc>
            <x-dl::form.row cols="3">
                @foreach ($accTypes as $t => $label)
                    <x-dl::field.display :label="$label" :value="$lastAcc($t) ? $lastAcc($t)['at'].' · '.$lastAcc($t)['by'].' ('.$lastAcc($t)['channel'].')' : ($t === 'DIGITAL' ? Lang::text('NOT YET — required before the Printing process') : Lang::text('not recorded yet'))" :source="$src('tm2mst.prod_product_acc')" />
                @endforeach
            </x-dl::form.row>
            <x-dl::hint note>{{ Lang::text('ACC is recorded & the Digital Proof is printed from the ProDev form (special button Print ACC / Proof Digital).') }}</x-dl::hint>
        </x-slot:acc>
        <x-slot:orders>
            <x-dl::table :columns="['date' => 'Date', 'quotation' => 'Quotation', 'co' => 'Customer Order', 'qty' => 'Qty (pcs)', 'price' => 'Selling price /pcs', 'result' => 'Result']"
                :rows="array_map(fn ($o) => ['date' => $o['date'], 'quotation' => $o['quotation'] ?? '—', 'co' => $o['co'] ?? '—', 'qty' => $num($o['qty']), 'price' => $rp($o['price']), 'result' => ['WIN' => 'WIN · '.Lang::text('sold'), 'LOST' => 'LOST', 'OPEN' => Lang::text('Still open')][$o['result']]], $orders)"
                empty="No order yet." :source="$src('tm2sdi.quo_quotation_item', 'tm2sdi.co_order_item.product_id')" />
        </x-slot:orders>
        <x-slot:branch>
            <x-dl::table :columns="['rel' => 'Relation', 'label' => 'Product', 'status' => 'Status']"
                :rows="array_merge($doc['parent'] ? [['rel' => Lang::text('Parent'), 'label' => $doc['parent']['label'], 'status' => '']] : [], [['rel' => Lang::text('This product'), 'label' => $doc['name'].' · '.$doc['design_no'], 'status' => $doc['status']]],
                    array_map(fn ($r) => ['rel' => Lang::text('Derived'), 'label' => $r['label'], 'status' => $r['status']], $doc['revisions']))"
                :source="$src('tm2mst.prod_product.parent_product_id')" />
            <x-dl::hint note>{{ Lang::text('Every Revision opens a new branch (derived product); the old product stays intact.') }}</x-dl::hint>
        </x-slot:branch>
    </x-dl::tabs>
</x-dl::card>
</div>
